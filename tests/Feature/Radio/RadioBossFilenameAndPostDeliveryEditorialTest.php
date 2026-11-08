<?php

declare(strict_types=1);

namespace Tests\Feature\Radio;

use App\Events\PodcastRadiobossUploaded;
use App\Jobs\ProcessMp3Job;
use App\Jobs\UploadArchiveOrgJob;
use App\Jobs\UploadRadiobossJob;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Models\User;
use App\Support\Radio\RadioBossPathValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RadioBossFilenameAndPostDeliveryEditorialTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $compiledPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sevenrock-view-cache';
        if (! is_dir($compiledPath)) {
            mkdir($compiledPath, 0777, true);
        }

        config([
            'view.compiled' => $compiledPath,
            'player.radioboss.api_url' => 'https://c30.radioboss.fm',
            'player.radioboss.station_id' => '569',
            'player.radioboss.api_key' => 'test-key-569',
            'filesystems.disks.r2.key' => 'fake-key',
            'filesystems.disks.r2.secret' => 'fake-secret',
            'filesystems.disks.r2.bucket' => 'fake-bucket',
            'filesystems.disks.r2.url' => 'https://fake-r2.test',
            'mail.default' => 'array',
        ]);

        Mail::fake();

        $this->adminUser = User::factory()->create([
            'name' => 'Admin Rock',
            'email' => 'admin@sevenrock.test',
            'is_admin' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * A.1 Nombre compatible (ASCII):
     * Valida que un nombre sin acentos ni caracteres especiales se clasifique como seguro,
     * no requiera confirmación obligatoria y se transfiera sin intervención manual.
     */
    public function test_compatible_ascii_name_is_marked_safe_and_needs_no_confirmation(): void
    {
        $master = MasterProgram::factory()->create([
            'nombre' => 'TROVADOR URBANO',
            'ruta_ftp' => '01.- LUNES/01.- TROVADOR URBANO',
        ]);

        $episode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'TROVADOR URBANO',
            'conductor' => 'David Calderon',
            'numero_episodio' => 200,
            'fecha_emision' => '2026-11-08',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => 'programas_procesados/trovador-urbano/200.- TROVADOR URBANO 08-11-2026.mp3',
            'archivo_mp3_disk' => 'r2',
        ]);

        $analysis = RadioBossPathValidator::analyzeEpisode($episode);

        $this->assertTrue($analysis['original_already_safe'], 'El nombre ASCII original debe ser seguro');
        $this->assertTrue($analysis['is_safe']);
        $this->assertFalse($analysis['needs_confirmation'], 'No debe pedir confirmación obligatoria si ya es seguro');
        $this->assertEmpty($analysis['issues']);
        $this->assertSame('200.- TROVADOR URBANO 08-11-2026.mp3', $analysis['effective_filename']);
    }

    /**
     * A.2 Detección de incompatibilidades y propuesta segura:
     * Un nombre con acentos o caracteres no ASCII (ej. CÓDIGO ZONA MISTERIO)
     * se detecta como incompatible, genera una propuesta limpia y requiere confirmación.
     */
    public function test_incompatible_name_detects_issues_generates_safe_proposal_and_flags_confirmation(): void
    {
        $master = MasterProgram::factory()->create([
            'nombre' => 'CÓDIGO ZONA MISTERIO',
            'ruta_ftp' => '07.- DOMINGO/10.- CODIGO ZONA MISTERIO',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '04:00:00',
        ]);

        $episode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'CÓDIGO ZONA MISTERIO',
            'conductor' => 'Iker',
            'numero_episodio' => 14,
            'fecha_emision' => '2026-11-08',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => 'programas_procesados/codigo-zona-misterio/014.- CÓDIGO ZONA MISTERIO 08-11-2026.mp3',
            'archivo_mp3_disk' => 'r2',
        ]);

        $analysis = RadioBossPathValidator::analyzeEpisode($episode);

        $this->assertFalse($analysis['original_already_safe'], 'El nombre con tilde no es ASCII seguro');
        $this->assertTrue($analysis['needs_confirmation'], 'Debe marcar que requiere confirmación previa');
        $this->assertNotEmpty($analysis['issues']);
        $this->assertSame('014.- CODIGO ZONA MISTERIO 08-11-2026.mp3', $analysis['safe_proposal']);

        // Los metadatos originales del episodio y la ruta en R2 se conservan intactos
        $this->assertSame('CÓDIGO ZONA MISTERIO', $episode->titulo_programa);
        $this->assertStringContainsString('CÓDIGO', (string) $episode->archivo_mp3);
    }

    /**
     * A.3 Confirmación previa desde administración y consistencia en el pipeline:
     * El administrador confirma el nombre técnico propuesto o personalizado;
     * el nombre confirmado se usa consistentemente en staging (.tmp_), destino, verificación y metadatos.
     */
    public function test_admin_can_confirm_radioboss_technical_filename_used_consistently(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');
        Event::fake([PodcastRadiobossUploaded::class]);

        $master = MasterProgram::factory()->create([
            'nombre' => 'CÓDIGO ZONA MISTERIO',
            'ruta_ftp' => '07.- DOMINGO/10.- CODIGO ZONA MISTERIO',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '04:00:00',
        ]);

        $r2Path = 'programas_procesados/codigo-zona-misterio/014.- CÓDIGO ZONA MISTERIO 08-11-2026.mp3';
        Storage::disk('r2')->put($r2Path, 'audio-content-test-czm-14');

        $episode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'CÓDIGO ZONA MISTERIO',
            'live_title' => 'CÓDIGO ZONA MISTERIO Ep 14',
            'conductor' => 'Iker',
            'numero_episodio' => 14,
            'fecha_emision' => '2026-11-08',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => $r2Path,
            'archivo_mp3_disk' => 'r2',
        ]);

        // Simular que el administrador confirma un nombre técnico personalizado desde el panel
        $confirmedFilename = '014.- CODIGO ZONA MISTERIO 08-11-2026.mp3';

        $response = $this->actingAs($this->adminUser)->put(
            route('admin.podcast-uploads.editorial.update', $episode),
            [
                'live_title' => 'CÓDIGO ZONA MISTERIO Ep 14 - Título Editado',
                'editorial_summary' => 'Resumen editorial del misterio',
                'resena' => 'Descripción completa del episodio',
                'biografia_invitado' => 'Invitado Dr. Experto',
                'radioboss_custom_filename' => $confirmedFilename,
                'confirm_radioboss_filename' => true,
            ]
        );

        $response->assertRedirect(route('admin.podcast-uploads.editorial.show', $episode));
        $episode->refresh();

        $this->assertSame($confirmedFilename, $episode->getConfirmedRadiobossFilename());

        // Ejecutar el Job de subida a RadioBOSS
        $job = new UploadRadiobossJob($episode->id);
        $job->handle();

        $episode->refresh();

        // 1. Verificación en RadioBOSS exitosa
        $this->assertSame('radioboss_verified', $episode->radioboss_status);
        $this->assertTrue((bool) $episode->enviado_radioboss);

        // 2. Ruta y nombre remoto consistentes en destino y metadatos
        $expectedRemotePath = '07.- DOMINGO/10.- CODIGO ZONA MISTERIO/' . $confirmedFilename;
        $this->assertSame($expectedRemotePath, $episode->radioboss_metadata['remote_path']);
        $this->assertTrue(Storage::disk('radioboss')->exists($expectedRemotePath));

        // 3. Staging limpio: no deben quedar temporales huérfanos
        $stagingFiles = Storage::disk('radioboss')->files('staging_programas/07.- DOMINGO/10.- CODIGO ZONA MISTERIO');
        $this->assertEmpty($stagingFiles);
    }

    /**
     * A.4 Bloqueo antes de tocar FTP si el nombre no puede validarse o la carpeta es insegura.
     */
    public function test_delivery_halts_with_clear_error_before_touching_ftp_if_path_invalid(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');

        $master = MasterProgram::factory()->create([
            'nombre' => 'Programa Desconocido',
            'ruta_ftp' => null, // Sin carpeta válida ni mapeo en events.txt
        ]);

        $r2Path = 'programas_procesados/desconocido/001.- PROGRAMA 08-11-2026.mp3';
        Storage::disk('r2')->put($r2Path, 'audio-data');

        $episode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Desconocido',
            'conductor' => 'Locutor',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => $r2Path,
            'archivo_mp3_disk' => 'r2',
        ]);

        $job = new UploadRadiobossJob($episode->id);
        $job->handle();

        $episode->refresh();

        $this->assertSame('radioboss_error', $episode->radioboss_status);
        $this->assertFalse((bool) $episode->enviado_radioboss);
        $this->assertStringContainsString('No se pudo resolver una carpeta de emisión comprobada', (string) $episode->radioboss_last_error);

        // No se crearon carpetas ni archivos en RadioBOSS
        $this->assertEmpty(Storage::disk('radioboss')->allFiles());
    }

    /**
     * B.1 Edición de metadatos tras la entrega a RadioBOSS:
     * Permite modificar título de emisión, resumen editorial, reseña e invitado especial
     * sin reprocesar el MP3, sin resubidas a FTP, sin enviar correos duplicados y conservando
     * el estado verificado de RadioBOSS.
     */
    public function test_post_delivery_editorial_edits_save_cleanly_without_reprocessing_or_duplicate_emails(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');
        Queue::fake();
        Event::fake([PodcastRadiobossUploaded::class]);

        $master = MasterProgram::factory()->create([
            'nombre' => 'CÓDIGO ZONA MISTERIO',
            'ruta_ftp' => '07.- DOMINGO/10.- CODIGO ZONA MISTERIO',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '04:00:00',
        ]);

        $remotePath = '07.- DOMINGO/10.- CODIGO ZONA MISTERIO/014.- CODIGO ZONA MISTERIO 08-11-2026.mp3';
        Storage::disk('radioboss')->put($remotePath, 'mp3-already-delivered-content');

        // Episodio ya entregado y verificado previamente
        $episode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'CÓDIGO ZONA MISTERIO',
            'live_title' => 'Título Inicial Emitido',
            'editorial_summary' => 'Resumen emitido',
            'resena' => 'Descripción inicial',
            'biografia_invitado' => 'Invitado Inicial',
            'conductor' => 'Iker',
            'numero_episodio' => 14,
            'fecha_emision' => '2026-11-08',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'enviado_radioboss' => true,
            'radioboss_status' => 'radioboss_verified',
            'radioboss_verified_at' => now()->subDay(),
            'radioboss_metadata' => [
                'remote_path' => $remotePath,
                'status' => 'radioboss_verified',
            ],
            'archive_org_status' => 'archive_verified',
            'archivo_mp3' => 'programas_procesados/codigo-zona-misterio/014.- CÓDIGO ZONA MISTERIO 08-11-2026.mp3',
            'archivo_mp3_disk' => 'r2',
        ]);

        // El administrador corrige el título de emisión, resumen e invitado especial
        $response = $this->actingAs($this->adminUser)->put(
            route('admin.podcast-uploads.editorial.update', $episode),
            [
                'live_title' => 'Nuevas Revelaciones del Misterio',
                'editorial_summary' => 'Resumen corregido y ampliado',
                'resena' => 'Descripción editorial enriquecida',
                'biografia_invitado' => 'Dra. Carmen Porter - Investigadora especializada',
            ]
        );

        $response->assertRedirect(route('admin.podcast-uploads.editorial.show', $episode));
        $response->assertSessionHas('status');

        $episode->refresh();

        // 1. Los campos editoriales se actualizaron correctamente
        $this->assertSame('Nuevas Revelaciones del Misterio', $episode->live_title);
        $this->assertSame('Resumen corregido y ampliado', $episode->editorial_summary);
        $this->assertSame('Descripción editorial enriquecida', $episode->resena);
        $this->assertSame('Dra. Carmen Porter - Investigadora especializada', $episode->biografia_invitado);

        // 2. El estado verificado de RadioBOSS y Archive.org NO fue alterado
        $this->assertTrue((bool) $episode->enviado_radioboss);
        $this->assertSame('radioboss_verified', $episode->radioboss_status);
        $this->assertSame('archive_verified', $episode->archive_org_status);
        $this->assertSame($remotePath, $episode->radioboss_metadata['remote_path']);

        // 3. El archivo físico en RadioBOSS sigue intacto
        $this->assertSame('mp3-already-delivered-content', Storage::disk('radioboss')->get($remotePath));

        // 4. CERO tareas de cola despachadas (sin reprocesamiento MP3 ni resubidas)
        Queue::assertNothingPushed();

        // 5. CERO correos enviados (sin duplicar notificaciones de entrega)
        Mail::assertNothingSent();
        Event::assertNotDispatched(PodcastRadiobossUploaded::class);
    }

    /**
     * B.2 Protección contra renombrado silencioso de un archivo ya entregado:
     * Si se intenta enviar un nombre de archivo diferente para un episodio ya verificado,
     * la operación rechaza el renombrado silencioso para proteger la emisión activa.
     */
    public function test_already_delivered_episode_rejects_silent_file_renaming(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');

        $master = MasterProgram::factory()->create([
            'nombre' => 'CÓDIGO ZONA MISTERIO',
            'ruta_ftp' => '07.- DOMINGO/10.- CODIGO ZONA MISTERIO',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '04:00:00',
        ]);

        $remotePath = '07.- DOMINGO/10.- CODIGO ZONA MISTERIO/014.- CODIGO ZONA MISTERIO 08-11-2026.mp3';

        $episode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'CÓDIGO ZONA MISTERIO',
            'live_title' => 'Título Inicial',
            'conductor' => 'Iker',
            'enviado_radioboss' => true,
            'radioboss_status' => 'radioboss_verified',
            'radioboss_metadata' => ['remote_path' => $remotePath],
            'archivo_mp3' => 'programas_procesados/codigo-zona-misterio/014.- CÓDIGO ZONA MISTERIO 08-11-2026.mp3',
            'archivo_mp3_disk' => 'r2',
        ]);

        $response = $this->actingAs($this->adminUser)->put(
            route('admin.podcast-uploads.editorial.update', $episode),
            [
                'live_title' => 'Nuevo Título',
                'editorial_summary' => 'Resumen',
                'resena' => 'Descripción',
                'radioboss_custom_filename' => '014.- OTRO NOMBRE SILENCIOSO 08-11-2026.mp3',
            ]
        );

        $response->assertSessionHasErrors(['radioboss_custom_filename']);
        $episode->refresh();

        // El remote_path en metadatos no fue cambiado
        $this->assertSame($remotePath, $episode->radioboss_metadata['remote_path']);
    }
}
