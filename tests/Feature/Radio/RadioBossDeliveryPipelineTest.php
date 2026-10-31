<?php

declare(strict_types=1);

namespace Tests\Feature\Radio;

use App\Events\PodcastDeliveryVerified;
use App\Events\PodcastRadiobossUploaded;
use App\Jobs\UploadRadiobossJob;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Models\Song;
use App\Models\User;
use App\Services\RadioPlayerService;
use App\Support\Radio\StreamMetadataReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RadioBossDeliveryPipelineTest extends TestCase
{
    use RefreshDatabase;

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
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * 1. Datos reales disponibles, próxima pista sin inversión y ausencia de datos.
     */
    public function test_metadata_resolution_and_fallback_when_api_empty(): void
    {
        // Caso A: Simulación de respuesta exacta de RadioBOSS Cloud (con nexttrack estructurado)
        $simulatedRadiobossJson = [
            'station_name' => 'SEVEN ROCK RADIO',
            'nowplaying' => 'Forest Of October - Opeth',
            'playback' => [
                'pos' => 384650,
                'len' => 785026,
                'state' => 'play',
            ],
            'currenttrack_info' => [
                '@attributes' => [
                    'ARTIST' => 'Opeth',
                    'TITLE' => 'Forest Of October',
                    'ALBUM' => 'Orchid',
                    'DURATION' => '13:05',
                ],
            ],
            'nexttrack' => 'War? - System of a Down',
            'nexttrack_info' => [
                '@attributes' => [
                    'ARTIST' => 'System of a Down',
                    'TITLE' => 'War?',
                    'ALBUM' => 'Corrosion',
                    'DURATION' => '02:43',
                ],
            ],
            'listeners' => 8,
        ];

        Http::fake([
            '*api/info*' => Http::response($simulatedRadiobossJson, 200),
            '*' => Http::response('', 200),
        ]);

        $streamReader = $this->createMock(StreamMetadataReader::class);
        $streamReader->method('read')->willReturn([
            'raw' => 'Forest Of October - Opeth',
            'title' => 'Forest Of October',
            'artist' => 'Opeth',
            'success' => true,
        ]);

        $player = app(RadioPlayerService::class);
        $player->setStreamReader($streamReader);

        $state = $player->resolve();

        $this->assertSame('Forest Of October', $state['track']['title']);
        $this->assertSame('Opeth', $state['track']['artist']);
        $this->assertSame(785, $state['track']['duration_seconds']);
        $this->assertSame(385, $state['track']['elapsed_seconds']);

        // Próxima pista: validar que el título es War? y el artista System of a Down (NO invertidos)
        $this->assertNotNull($state['next_track']);
        $this->assertSame('War?', $state['next_track']['title']);
        $this->assertSame('System of a Down', $state['next_track']['artist']);

        // Caso B: Ausencia total de API (fallback a ICY y defaults sin error 500)
        Http::fake([
            '*api/info*' => Http::response(null, 500),
            '*' => Http::response('', 200),
        ]);

        $stateB = $player->resolve();
        $this->assertSame('Forest Of October', $stateB['track']['title']);
        $this->assertSame('Opeth', $stateB['track']['artist']);
    }

    /**
     * 2. Cambio de pista y registro en historial al confirmar transición.
     */
    public function test_track_change_and_history_recording(): void
    {
        Carbon::setTestNow('2026-10-31 12:00:00');

        Http::fake([
            '*c30.radioboss.fm/api/info*' => Http::response([
                'nowplaying' => 'Shoot to Thrill - AC/DC',
                'currenttrack_info' => ['@attributes' => ['ARTIST' => 'AC/DC', 'TITLE' => 'Shoot to Thrill']],
                'playback' => ['pos' => 10000, 'len' => 300000],
            ], 200),
        ]);

        $streamReader = $this->createMock(StreamMetadataReader::class);
        $streamReader->method('read')->willReturn([
            'raw' => 'Shoot to Thrill - AC/DC',
            'title' => 'Shoot to Thrill',
            'artist' => 'AC/DC',
            'success' => true,
        ]);

        $player = app(RadioPlayerService::class);
        $player->setStreamReader($streamReader);

        $state1 = $player->resolve();
        $this->assertSame('Shoot to Thrill', $state1['track']['title']);

        // Transición a la siguiente canción
        Carbon::setTestNow('2026-10-31 12:05:00');

        Http::fake([
            '*c30.radioboss.fm/api/info*' => Http::response([
                'nowplaying' => 'Forest Of October - Opeth',
                'currenttrack_info' => ['@attributes' => ['ARTIST' => 'Opeth', 'TITLE' => 'Forest Of October']],
                'playback' => ['pos' => 5000, 'len' => 600000],
            ], 200),
        ]);

        $streamReader2 = $this->createMock(StreamMetadataReader::class);
        $streamReader2->method('read')->willReturn([
            'raw' => 'Forest Of October - Opeth',
            'title' => 'Forest Of October',
            'artist' => 'Opeth',
            'success' => true,
        ]);
        $player->setStreamReader($streamReader2);

        $state2 = $player->resolve();
        $this->assertSame('Forest Of October', $state2['track']['title']);

        // El tema anterior (AC/DC) debe haberse grabado en play_history
        $this->assertDatabaseHas('play_history', [
            'title' => 'Shoot to Thrill',
            'artist' => 'AC/DC',
        ]);
    }

    /**
     * 3. Entrega a RadioBOSS: versión aprobada vs. versión no aprobada.
     */
    public function test_delivery_blocks_unapproved_or_unprocessed_versions(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');
        Event::fake([PodcastRadiobossUploaded::class]);

        $master = MasterProgram::factory()->create([
            'nombre' => 'Metal Storm',
            'ruta_ftp' => 'Programas/MetalStorm',
        ]);

        // Caso 3A: Episodio NO aprobado editorialmente
        $unapprovedEpisode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Metal Storm Ep 1',
            'conductor' => 'Locutor Test',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_PENDING,
            'archivo_mp3' => 'programas_procesados/metal-storm/001.- METAL STORM 31-10-2026.mp3',
            'archivo_mp3_disk' => 'r2',
        ]);
        Storage::disk('r2')->put('programas_procesados/metal-storm/001.- METAL STORM 31-10-2026.mp3', 'mp3-content-fake');

        $jobA = new UploadRadiobossJob($unapprovedEpisode->id);
        $jobA->handle();

        $unapprovedEpisode->refresh();
        $this->assertSame('radioboss_error', $unapprovedEpisode->radioboss_status);
        $this->assertStringContainsString('no está aprobado editorialmente', $unapprovedEpisode->radioboss_last_error);
        $this->assertFalse((bool) $unapprovedEpisode->enviado_radioboss);
        $this->assertFalse(Storage::disk('radioboss')->exists('Programas/MetalStorm/001.- METAL STORM 31-10-2026.mp3'));

        // Caso 3B: Episodio aprobado pero archivo NO procesado
        $unprocessedEpisode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Metal Storm Ep 2',
            'conductor' => 'Locutor Test',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => 'raw_uploads/raw_audio.mp3', // Ruta no procesada
            'archivo_mp3_disk' => 'r2',
        ]);
        Storage::disk('r2')->put('raw_uploads/raw_audio.mp3', 'raw-fake-audio');

        $jobB = new UploadRadiobossJob($unprocessedEpisode->id);
        $jobB->handle();

        $unprocessedEpisode->refresh();
        $this->assertSame('radioboss_error', $unprocessedEpisode->radioboss_status);
        $this->assertStringContainsString('no es una versión procesada definitiva', $unprocessedEpisode->radioboss_last_error);

        // Caso 3C: Episodio aprobado y procesado correctamente
        $validEpisode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Metal Storm Ep 3',
            'conductor' => 'Locutor Test',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => 'programas_procesados/metal-storm/003.- METAL STORM 31-10-2026.mp3',
            'archivo_mp3_disk' => 'r2',
        ]);
        Storage::disk('r2')->put('programas_procesados/metal-storm/003.- METAL STORM 31-10-2026.mp3', 'valid-processed-audio-12345');

        $jobC = new UploadRadiobossJob($validEpisode->id);
        $jobC->handle();

        $validEpisode->refresh();
        $this->assertSame('radioboss_verified', $validEpisode->radioboss_status);
        $this->assertTrue((bool) $validEpisode->enviado_radioboss);
        $this->assertTrue(Storage::disk('radioboss')->exists('Programas/MetalStorm/003.- METAL STORM 31-10-2026.mp3'));
        $this->assertSame('valid-processed-audio-12345', Storage::disk('radioboss')->get('Programas/MetalStorm/003.- METAL STORM 31-10-2026.mp3'));
        Event::assertDispatched(PodcastRadiobossUploaded::class);
    }

    /**
     * 4. Respeto de plazo de entrega y autorización de excepciones administrativas.
     */
    public function test_deadline_enforcement_and_administrative_exception(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');

        Carbon::setTestNow(Carbon::parse('2026-10-31 19:00:00', 'America/Caracas'));

        $master = MasterProgram::factory()->create([
            'nombre' => 'Rock Nite',
            'dia_transmision' => 'SABADO',
            'hora_transmision' => '20:00:00', // Emisión a las 20:00 Caracas
            'timezone' => 'America/Caracas',
            'submission_cutoff_minutes' => 180, // Plazo cierra a las 17:00 Caracas (a las 19:00 está cerrado)
            'ruta_ftp' => 'Programas/RockNite',
        ]);

        // Episodio entregado tarde sin excepción
        $lateEpisode = RadioProgram::create([
            'master_program_id' => $master->id,
            'submitted_by_user_id' => 99,
            'titulo_programa' => 'Rock Nite Ep 10',
            'conductor' => 'Locutor Test',
            'fecha_emision' => '2026-10-31',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'submitted_at' => Carbon::parse('2026-10-31 19:00:00', 'America/Caracas'),
            'archivo_mp3' => 'programas_procesados/rock-nite/010.- ROCK NITE 31-10-2026.mp3',
            'archivo_mp3_disk' => 'r2',
        ]);
        Storage::disk('r2')->put('programas_procesados/rock-nite/010.- ROCK NITE 31-10-2026.mp3', 'audio-late');

        $jobLate = new UploadRadiobossJob($lateEpisode->id);
        $jobLate->handle();

        $lateEpisode->refresh();
        $this->assertSame('radioboss_error', $lateEpisode->radioboss_status);
        $this->assertStringContainsString('Plazo de entrega de la emisión vencido sin excepción', $lateEpisode->radioboss_last_error);

        // Ahora autorizamos excepción administrativa
        $admin = User::factory()->create(['is_admin' => true]);
        $lateEpisode->update([
            'late_submission_authorized_by' => $admin->id,
            'late_submission_authorized_at' => now(),
            'late_submission_reason' => 'Problema técnico del conductor autorizado por dirección.',
        ]);

        $jobAuthorized = new UploadRadiobossJob($lateEpisode->id);
        $jobAuthorized->handle();

        $lateEpisode->refresh();
        $this->assertSame('radioboss_verified', $lateEpisode->radioboss_status);
        $this->assertTrue((bool) $lateEpisode->enviado_radioboss);
        $this->assertTrue(Storage::disk('radioboss')->exists('Programas/RockNite/010.- ROCK NITE 31-10-2026.mp3'));
    }

    /**
     * 5. Fallo de transferencia: conservación del programa de respaldo anterior y limpieza del temporal.
     */
    public function test_transfer_failure_preserves_backup_episode(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');

        $master = MasterProgram::factory()->create([
            'nombre' => 'Underground Show',
            'ruta_ftp' => 'Programas/Underground',
        ]);

        // Simular que en la carpeta remota existe el episodio de la semana anterior (el respaldo que está en el aire)
        Storage::disk('radioboss')->put('Programas/Underground/001.- UNDERGROUND 24-10-2026.mp3', 'audio-respaldo-anterior-en-aire');

        $newEpisode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Underground Show Ep 2',
            'conductor' => 'Locutor Test',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => 'programas_procesados/underground/002.- UNDERGROUND 31-10-2026.mp3',
            'archivo_mp3_disk' => 'r2',
        ]);
        Storage::disk('r2')->put('programas_procesados/underground/002.- UNDERGROUND 31-10-2026.mp3', 'audio-nuevo-en-proceso');

        // Simulamos fallo en la escritura de RadioBOSS
        $failingDisk = new class(Storage::disk('radioboss')) {
            public function __construct(private $inner) {}
            public function __call($name, $arguments) {
                if ($name === 'writeStream' || $name === 'put') {
                    // Simular corte de conexión FTP durante la subida
                    return false;
                }
                return $this->inner->$name(...$arguments);
            }
        };
        Storage::set('radioboss', $failingDisk);

        $job = new UploadRadiobossJob($newEpisode->id);
        $job->handle();

        $newEpisode->refresh();
        $this->assertSame('radioboss_error', $newEpisode->radioboss_status);

        // VERIFICACIÓN CRÍTICA:
        // 1. El episodio anterior DEBE seguir existiendo intacto en RadioBOSS (respaldo conservado)
        $this->assertTrue(Storage::disk('radioboss')->exists('Programas/Underground/001.- UNDERGROUND 24-10-2026.mp3'));
        $this->assertSame('audio-respaldo-anterior-en-aire', Storage::disk('radioboss')->get('Programas/Underground/001.- UNDERGROUND 24-10-2026.mp3'));

        // 2. El archivo temporal .part NO debe haber quedado huérfano
        $files = Storage::disk('radioboss')->files('Programas/Underground');
        foreach ($files as $file) {
            $this->assertStringNotContainsString('.tmp_', $file);
            $this->assertStringNotContainsString('.part', $file);
        }
    }

    /**
     * 6. Idempotencia, evitar re-subida innecesaria y separación estricta de programación.
     */
    public function test_idempotency_and_schedule_status_separation(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');

        $master = MasterProgram::factory()->create([
            'nombre' => 'Rock Legends',
            'ruta_ftp' => 'Programas/Legends',
        ]);

        $content = 'legends-mp3-audio-bytes-content';
        Storage::disk('r2')->put('programas_procesados/legends/005.- ROCK LEGENDS 31-10-2026.mp3', $content);

        $episode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Legends Ep 5',
            'conductor' => 'Locutor Test',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => 'programas_procesados/legends/005.- ROCK LEGENDS 31-10-2026.mp3',
            'archivo_mp3_disk' => 'r2',
            'schedule_inclusion_status' => 'pending_schedule',
        ]);

        // Primera subida
        $job = new UploadRadiobossJob($episode->id);
        $job->handle();

        $episode->refresh();
        $this->assertSame('radioboss_verified', $episode->radioboss_status);
        $this->assertTrue((bool) $episode->enviado_radioboss);

        // Separación estricta: la subida a FTP NO marca automáticamente como programado en parrilla
        $this->assertSame('pending_schedule', $episode->schedule_inclusion_status);

        // Segunda ejecución (idempotente): no debe fallar ni recrear el archivo
        $jobSecond = new UploadRadiobossJob($episode->id);
        $jobSecond->handle();

        $episode->refresh();
        $this->assertSame('radioboss_verified', $episode->radioboss_status);
        $this->assertSame('pending_schedule', $episode->schedule_inclusion_status);
        $this->assertTrue(Storage::disk('radioboss')->exists('Programas/Legends/005.- ROCK LEGENDS 31-10-2026.mp3'));
    }

    /**
     * 7. Fallo de renombrado (rename failure): elimina el archivo temporal,
     * conserva el archivo previo intacto y registra radioboss_error sin dejar éxito.
     */
    public function test_rename_failure_leaves_temporary_deleted_and_previous_file_untouched(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');

        $master = MasterProgram::factory()->create([
            'nombre' => 'Rename Fail Show',
            'ruta_ftp' => 'Programas/RenameFail',
        ]);

        Storage::disk('radioboss')->put('Programas/RenameFail/001.- RENAME FAIL 24-10-2026.mp3', 'previous-backup-file-untouched');

        $episode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Rename Fail Ep 2',
            'conductor' => 'Locutor Test',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => 'programas_procesados/rename-fail/002.- RENAME FAIL 31-10-2026.mp3',
            'archivo_mp3_disk' => 'r2',
        ]);
        Storage::disk('r2')->put('programas_procesados/rename-fail/002.- RENAME FAIL 31-10-2026.mp3', 'new-audio-trying-to-rename');

        // Simulamos fallo específicamente en el método move() (rename) del archivo temporal
        $failingMoveDisk = new class(Storage::disk('radioboss')) {
            public function __construct(private $inner) {}
            public function __call($name, $arguments) {
                if ($name === 'move' && (str_contains((string) ($arguments[0] ?? ''), '.tmp_') || str_ends_with((string) ($arguments[0] ?? ''), '.part'))) {
                    return false; // Simula fallo en RNFR / RNTO del temporal
                }
                return $this->inner->$name(...$arguments);
            }
        };
        Storage::set('radioboss', $failingMoveDisk);

        $job = new UploadRadiobossJob($episode->id);
        $job->handle();

        $episode->refresh();
        $this->assertSame('radioboss_error', $episode->radioboss_status);
        $this->assertFalse((bool) $episode->enviado_radioboss);
        $this->assertStringContainsString('Fallo al renombrar archivo temporal', (string) $episode->radioboss_last_error);

        // El respaldo previo DEBE estar intacto
        $this->assertTrue(Storage::disk('radioboss')->exists('Programas/RenameFail/001.- RENAME FAIL 24-10-2026.mp3'));
        $this->assertSame('previous-backup-file-untouched', Storage::disk('radioboss')->get('Programas/RenameFail/001.- RENAME FAIL 24-10-2026.mp3'));

        // El archivo definitivo NO debe existir
        $this->assertFalse(Storage::disk('radioboss')->exists('Programas/RenameFail/002.- RENAME FAIL 31-10-2026.mp3'));

        // Los temporales fueron eliminados (ningún .tmp_ o .part en la carpeta)
        $files = Storage::disk('radioboss')->files('Programas/RenameFail');
        foreach ($files as $file) {
            $this->assertStringNotContainsString('.tmp_', $file);
            $this->assertStringNotContainsString('.part', $file);
        }
    }

    /**
     * 8. Fallo de verificación post-renombrado: si el archivo final no existe o su tamaño es discrepante,
     * el estado queda en radioboss_error y no se anuncia éxito.
     */
    public function test_post_rename_verification_failure_marks_radioboss_error(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');

        $master = MasterProgram::factory()->create([
            'nombre' => 'Verify Fail Show',
            'ruta_ftp' => 'Programas/VerifyFail',
        ]);

        $episode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Verify Fail Ep 1',
            'conductor' => 'Locutor Test',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => 'programas_procesados/verify-fail/001.- VERIFY FAIL 31-10-2026.mp3',
            'archivo_mp3_disk' => 'r2',
        ]);
        Storage::disk('r2')->put('programas_procesados/verify-fail/001.- VERIFY FAIL 31-10-2026.mp3', 'correct-audio-content');

        // Simulamos que el move() funciona, pero el size() post-renombrado devuelve 0 o discrepancia
        $failingVerifyDisk = new class(Storage::disk('radioboss')) {
            public function __construct(private $inner) {}
            public function __call($name, $arguments) {
                if ($name === 'size' && str_ends_with((string) ($arguments[0] ?? ''), '001.- VERIFY FAIL 31-10-2026.mp3')) {
                    return 0; // Simula archivo corrupto o truncado post-renombrado
                }
                return $this->inner->$name(...$arguments);
            }
        };
        Storage::set('radioboss', $failingVerifyDisk);

        $job = new UploadRadiobossJob($episode->id);
        $job->handle();

        $episode->refresh();
        $this->assertSame('radioboss_error', $episode->radioboss_status);
        $this->assertFalse((bool) $episode->enviado_radioboss);
        $this->assertStringContainsString('Verificación post-renombrado fallida', (string) $episode->radioboss_last_error);
    }

    /**
     * 9. Colisión en destino: si ya existe el destino base y también el versionado,
     * la entrega se detiene para no sobrescribir ningún archivo potencialmente en uso o emisión.
     */
    public function test_existing_destination_collision_halts_delivery_and_preserves_files(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');
        config(['services.radioboss.archive_to_respaldo' => false]);

        $master = MasterProgram::factory()->create([
            'nombre' => 'Collision Show',
            'ruta_ftp' => 'Programas/Collision',
        ]);

        // Ambos archivos ya existen en el destino
        Storage::disk('radioboss')->put('Programas/Collision/001.- COLLISION 31-10-2026.mp3', 'existing-base-file');
        Storage::disk('radioboss')->put('Programas/Collision/001.- COLLISION 31-10-2026_v1.mp3', 'existing-versioned-file');

        $episode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Collision Ep 1',
            'conductor' => 'Locutor Test',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => 'programas_procesados/collision/001.- COLLISION 31-10-2026.mp3',
            'archivo_mp3_disk' => 'r2',
        ]);
        Storage::disk('r2')->put('programas_procesados/collision/001.- COLLISION 31-10-2026.mp3', 'new-attempted-content');

        $job = new UploadRadiobossJob($episode->id);
        $job->handle();

        $episode->refresh();
        $this->assertSame('radioboss_error', $episode->radioboss_status);
        $this->assertFalse((bool) $episode->enviado_radioboss);
        $this->assertStringContainsString('Destino existente en RadioBOSS', (string) $episode->radioboss_last_error);

        // Ninguno de los dos archivos existentes fue modificado o borrado
        $this->assertSame('existing-base-file', Storage::disk('radioboss')->get('Programas/Collision/001.- COLLISION 31-10-2026.mp3'));
        $this->assertSame('existing-versioned-file', Storage::disk('radioboss')->get('Programas/Collision/001.- COLLISION 31-10-2026_v1.mp3'));
    }

    /**
     * 10. Bloqueo atómico de concurrencia: si otra transferencia ya está en curso para el mismo episodio,
     * la segunda ejecución se omite sin interferir.
     */
    public function test_concurrency_lock_prevents_overlapping_runs(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');

        $master = MasterProgram::factory()->create([
            'nombre' => 'Concurrent Show',
            'ruta_ftp' => 'Programas/Concurrent',
        ]);

        $episode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Concurrent Ep 1',
            'conductor' => 'Locutor Test',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => 'programas_procesados/concurrent/001.- CONCURRENT 31-10-2026.mp3',
            'archivo_mp3_disk' => 'r2',
            'radioboss_status' => null,
        ]);
        Storage::disk('r2')->put('programas_procesados/concurrent/001.- CONCURRENT 31-10-2026.mp3', 'concurrent-audio');

        // Adquirimos el lock de forma externa simulando otro worker en proceso
        $lockKey = "upload_radioboss_program_{$episode->id}";
        $externalLock = Cache::lock($lockKey, 600);
        $this->assertTrue($externalLock->get());

        try {
            $job = new UploadRadiobossJob($episode->id);
            $job->handle();

            $episode->refresh();
            // No debe haber modificado nada porque el lock estaba tomado
            $this->assertNull($episode->radioboss_status);
            $this->assertFalse((bool) $episode->enviado_radioboss);
            $this->assertFalse(Storage::disk('radioboss')->exists('Programas/Concurrent/001.- CONCURRENT 31-10-2026.mp3'));
        } finally {
            $externalLock->release();
        }
    }

    /**
     * 11. Puntualidad evaluada según la entrega completada por el conductor (submitted_at):
     * un episodio entregado en plazo antes del deadline puede ser procesado y transferido después sin error.
     */
    public function test_on_time_submission_processed_late_is_allowed(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');

        $master = MasterProgram::factory()->create([
            'nombre' => 'On Time Show',
            'dia_transmision' => 'SABADO',
            'hora_transmision' => '20:00:00', // Emisión a las 20:00 Caracas
            'duracion_minutos' => 60,
            'timezone' => 'America/Caracas',
            'submission_cutoff_minutes' => 180, // Plazo cierra a las 17:00 Caracas
            'ruta_ftp' => 'Programas/OnTime',
        ]);

        // El conductor entregó a las 16:30 Caracas (media hora ANTES del cierre a las 17:00)
        $onTimeEpisode = RadioProgram::create([
            'master_program_id' => $master->id,
            'submitted_by_user_id' => 88,
            'titulo_programa' => 'On Time Ep 1',
            'conductor' => 'Locutor Cumplido',
            'fecha_emision' => '2026-10-31',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'submitted_at' => Carbon::parse('2026-10-31 16:30:00', 'America/Caracas'),
            'archivo_mp3' => 'programas_procesados/ontime/001.- ON TIME 31-10-2026.mp3',
            'archivo_mp3_disk' => 'r2',
        ]);
        Storage::disk('r2')->put('programas_procesados/ontime/001.- ON TIME 31-10-2026.mp3', 'on-time-audio-content');

        // El worker procesa la cola a las 21:30 Caracas (MUCHO DESPUÉS del deadline y de la hora de emisión)
        Carbon::setTestNow(Carbon::parse('2026-10-31 21:30:00', 'America/Caracas'));

        $job = new UploadRadiobossJob($onTimeEpisode->id);
        $job->handle();

        $onTimeEpisode->refresh();
        // La entrega DEBE ser exitosa porque el conductor entregó antes del deadline
        $this->assertSame('radioboss_verified', $onTimeEpisode->radioboss_status, (string) $onTimeEpisode->radioboss_last_error);
        $this->assertTrue((bool) $onTimeEpisode->enviado_radioboss);
        $this->assertTrue(Storage::disk('radioboss')->exists('Programas/OnTime/001.- ON TIME 31-10-2026.mp3'));
    }

    /**
     * 12. Preservación estricta de TODOS los archivos previos (cero eliminaciones):
     * los episodios históricos, backups y archivos en la carpeta remota no se borran automáticamente.
     */
    public function test_preservation_of_all_previous_files_zero_deletions(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');
        config(['services.radioboss.archive_to_respaldo' => false]);

        $master = MasterProgram::factory()->create([
            'nombre' => 'Archive Preserve Show',
            'ruta_ftp' => 'Programas/ArchivePreserve',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '04:00:00',
        ]);

        // Crear varios archivos existentes en la carpeta remota
        Storage::disk('radioboss')->put('Programas/ArchivePreserve/001.- ARCHIVE 10-10-2026.mp3', 'content-ep-1');
        Storage::disk('radioboss')->put('Programas/ArchivePreserve/002.- ARCHIVE 17-10-2026.mp3', 'content-ep-2');
        Storage::disk('radioboss')->put('Programas/ArchivePreserve/backup_emergency.mp3', 'backup-emergency');
        Storage::disk('radioboss')->put('Programas/ArchivePreserve/playlist_notes.txt', 'notes');

        $newEpisode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Archive Preserve Ep 3',
            'conductor' => 'Locutor Test',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => 'programas_procesados/archivepreserve/003.- ARCHIVE 31-10-2026.mp3',
            'archivo_mp3_disk' => 'r2',
        ]);
        Storage::disk('r2')->put('programas_procesados/archivepreserve/003.- ARCHIVE 31-10-2026.mp3', 'content-ep-3');

        $job = new UploadRadiobossJob($newEpisode->id);
        $job->handle();

        $newEpisode->refresh();
        $this->assertSame('radioboss_verified', $newEpisode->radioboss_status);

        // Verificamos que NINGÚN archivo previo fue borrado (cero eliminaciones)
        $this->assertTrue(Storage::disk('radioboss')->exists('Programas/ArchivePreserve/001.- ARCHIVE 10-10-2026.mp3'));
        $this->assertTrue(Storage::disk('radioboss')->exists('Programas/ArchivePreserve/002.- ARCHIVE 17-10-2026.mp3'));
        $this->assertTrue(Storage::disk('radioboss')->exists('Programas/ArchivePreserve/backup_emergency.mp3'));
        $this->assertTrue(Storage::disk('radioboss')->exists('Programas/ArchivePreserve/playlist_notes.txt'));
        $this->assertTrue(Storage::disk('radioboss')->exists('Programas/ArchivePreserve/003.- ARCHIVE 31-10-2026.mp3'));

        $this->assertCount(5, Storage::disk('radioboss')->files('Programas/ArchivePreserve'));
    }

    /**
     * 13. Idempotencia estricta: igualdad de tamaño con diferente sha256 no omite la subida,
     * exige que consten versión y sha256 verificados previamente en el episodio.
     */
    public function test_strict_idempotency_different_sha256_triggers_reupload(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');

        $master = MasterProgram::factory()->create([
            'nombre' => 'Hash Check Show',
            'ruta_ftp' => 'Programas/HashCheck',
        ]);

        $destinationPath = 'Programas/HashCheck/001.- HASH CHECK 31-10-2026.mp3';
        $oldContent = 'AAAABBBBCCCCDDDDEEEE'; // 20 bytes
        $newContent = 'ZZZZYYYYXXXXWWWWVVVV'; // 20 bytes (mismo tamaño, diferente sha256!)

        $this->assertSame(strlen($oldContent), strlen($newContent));
        $this->assertNotSame(hash('sha256', $oldContent), hash('sha256', $newContent));

        // En RadioBOSS está el archivo con el tamaño pero diferente contenido
        Storage::disk('radioboss')->put($destinationPath, $oldContent);

        // En R2 está el nuevo contenido
        Storage::disk('r2')->put('programas_procesados/hashcheck/001.- HASH CHECK 31-10-2026.mp3', $newContent);

        // El episodio tiene el hash VIEJO registrado en metadata
        $episode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Hash Check Ep 1',
            'conductor' => 'Locutor Test',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 2, // Nueva versión
            'editorial_approved_version' => 2,
            'archivo_mp3' => 'programas_procesados/hashcheck/001.- HASH CHECK 31-10-2026.mp3',
            'archivo_mp3_disk' => 'r2',
            'enviado_radioboss' => true,
            'radioboss_status' => 'radioboss_verified',
            'radioboss_metadata' => [
                'status' => 'radioboss_verified',
                'remote_path' => $destinationPath,
                'content_sha256' => hash('sha256', $oldContent),
                'version' => 1, // Versión anterior
                'size' => strlen($oldContent),
            ],
        ]);

        $job = new UploadRadiobossJob($episode->id);
        $job->handle();

        $episode->refresh();
        $this->assertSame('radioboss_verified', $episode->radioboss_status);
        $meta = (array) $episode->radioboss_metadata;
        // Debe haberse actualizado al NUEVO sha256 y a la NUEVA versión
        $this->assertSame(hash('sha256', $newContent), $meta['content_sha256']);
        $this->assertSame(2, $meta['version']);
    }

    /**
     * 14. Edición del conductor durante la subida:
     * La subida transfiere el temporal, pero el conductor edita el episodio antes de promoverlo.
     * La verificación condicional pre-promoción detecta el cambio de versión, limpia el temporal,
     * conserva respaldos, no sobrescribe archivos, marca radioboss_superseded y NO dispara eventos de entrega.
     */
    public function test_conductor_edition_during_upload_marks_superseded_and_cleans_temporary(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');
        Event::fake([
            PodcastRadiobossUploaded::class,
            PodcastDeliveryVerified::class,
        ]);

        $master = MasterProgram::factory()->create([
            'nombre' => 'Rock Edition Test',
            'ruta_ftp' => 'Programas/EditionTest',
        ]);

        // Respaldo anterior existente en el servidor
        Storage::disk('radioboss')->put('Programas/EditionTest/001.- ROCK EDITION 24-10-2026.mp3', 'backup-on-air-content');

        $episode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Rock Edition Ep 2',
            'conductor' => 'Locutor Test',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => 'programas_procesados/editiontest/002.- ROCK EDITION 31-10-2026.mp3',
            'archivo_mp3_disk' => 'r2',
        ]);
        Storage::disk('r2')->put('programas_procesados/editiontest/002.- ROCK EDITION 31-10-2026.mp3', 'v1-audio-bytes-being-transferred');

        // Simulador de filesystem: durante la escritura del temporal (writeStream), el conductor edita el episodio en BD
        $modifyingDisk = new class(Storage::disk('radioboss'), $episode) {
            public function __construct(private $inner, private RadioProgram $ep) {}
            public function __call($name, $arguments) {
                if ($name === 'writeStream') {
                    $result = $this->inner->$name(...$arguments);
                    // Simulamos edición concurrente del conductor mientras se transfería el MP3:
                    // Incrementa versión editorial a 2 y pasa a pending_editorial_review
                    RadioProgram::where('id', $this->ep->id)->update([
                        'editorial_version' => 2,
                        'editorial_status' => RadioProgram::EDITORIAL_STATUS_PENDING,
                        'editorial_approved_version' => 1,
                        'live_title' => 'Título modificado durante la subida',
                    ]);
                    return $result;
                }
                return $this->inner->$name(...$arguments);
            }
        };
        Storage::set('radioboss', $modifyingDisk);

        $job = new UploadRadiobossJob($episode->id);
        $job->handle();

        $episode->refresh();

        // 1. Estado marcado como obsoleto/superseded
        $this->assertSame('radioboss_superseded', $episode->radioboss_status);
        $this->assertFalse((bool) $episode->enviado_radioboss);
        $this->assertStringContainsString('quedó obsoleta porque el episodio fue modificado durante la subida', (string) $episode->radioboss_last_error);

        // 2. Metadatos reflejan versión invalidada
        $meta = (array) $episode->radioboss_metadata;
        $this->assertSame('radioboss_superseded', $meta['status'] ?? null);
        $this->assertSame(1, $meta['superseded_version'] ?? null);

        // 3. El archivo temporal fue eliminado del servidor FTP
        $files = Storage::disk('radioboss')->files('Programas/EditionTest');
        foreach ($files as $file) {
            $this->assertStringNotContainsString('.tmp_', $file);
            $this->assertStringNotContainsString('.part', $file);
        }

        // 4. El archivo final v1 NO fue promovido
        $this->assertFalse(Storage::disk('radioboss')->exists('Programas/EditionTest/002.- ROCK EDITION 31-10-2026.mp3'));

        // 5. El respaldo previo permanece intacto
        $this->assertTrue(Storage::disk('radioboss')->exists('Programas/EditionTest/001.- ROCK EDITION 24-10-2026.mp3'));
        $this->assertSame('backup-on-air-content', Storage::disk('radioboss')->get('Programas/EditionTest/001.- ROCK EDITION 24-10-2026.mp3'));

        // 6. NINGÚN evento de entrega verificada fue disparado para la versión invalidada
        Event::assertNotDispatched(PodcastRadiobossUploaded::class);
        Event::assertNotDispatched(PodcastDeliveryVerified::class);
    }

    /**
     * 15. Coordinación de colisiones por destino entre episodios distintos:
     * Si dos episodios compiten por el mismo destino simultáneamente,
     * el bloqueo por destino detiene la segunda transferencia sin sobrescribir ni colisionar.
     */
    public function test_two_episodes_competing_for_same_destination_avoids_collision(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');
        Event::fake([PodcastRadiobossUploaded::class]);

        $master = MasterProgram::factory()->create([
            'nombre' => 'Shared Destination Show',
            'ruta_ftp' => 'Programas/SharedShow',
        ]);

        $sameSourcePath = 'programas_procesados/shared/001.- SHARED SHOW 31-10-2026.mp3';
        Storage::disk('r2')->put($sameSourcePath, 'audio-content-same-target');

        $episode1 = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Shared Show Ep 1',
            'conductor' => 'Locutor A',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => $sameSourcePath,
            'archivo_mp3_disk' => 'r2',
        ]);

        $episode2 = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Shared Show Ep 2 Competing',
            'conductor' => 'Locutor B',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => $sameSourcePath, // Misma ruta base -> mismo destino esperado
            'archivo_mp3_disk' => 'r2',
        ]);

        $expectedDest = 'Programas/SharedShow/001.- SHARED SHOW 31-10-2026.mp3';
        $destLockKey = 'upload_radioboss_dest_' . sha1(strtolower($expectedDest));

        // Simulamos que el Episodio 1 está transfiriendo activamente hacia ese destino y tiene el bloqueo de destino
        $destLock = Cache::lock($destLockKey, 1800);
        $this->assertTrue($destLock->get());

        try {
            // El Episodio 2 intenta transferir al mismo destino
            $job2 = new UploadRadiobossJob($episode2->id);
            $job2->handle();

            $episode2->refresh();
            // Debe haberse detenido limpiamente sin colisionar
            $this->assertSame('radioboss_error', $episode2->radioboss_status);
            $this->assertFalse((bool) $episode2->enviado_radioboss);
            $this->assertStringContainsString('bloqueado por otra transferencia activa a RadioBOSS', (string) $episode2->radioboss_last_error);

            // El destino NO fue escrito por el Episodio 2
            $this->assertFalse(Storage::disk('radioboss')->exists($expectedDest));
        } finally {
            $destLock->release();
        }

        // Una vez liberado el bloqueo de destino, la transferencia del Episodio 1 puede completarse normalmente
        $job1 = new UploadRadiobossJob($episode1->id);
        $job1->handle();

        $episode1->refresh();
        $this->assertSame('radioboss_verified', $episode1->radioboss_status, (string) $episode1->radioboss_last_error);
        $this->assertTrue((bool) $episode1->enviado_radioboss);
        $this->assertTrue(Storage::disk('radioboss')->exists($expectedDest));
    }

    /**
     * 16. Selección determinista en RadioBOSS y preservación en subcarpeta de respaldo:
     * Cuando se activa la rotación de respaldo, el episodio previo se traslada a {folder}/respaldo/
     * para que la carpeta del evento contenga exactamente 1 archivo de audio, garantizando que
     * la acción {CARPETA}*1 seleccione inequívocamente el episodio nuevo sin riesgo de colisión
     * alfabética ni selección aleatoria, manteniendo el episodio anterior como respaldo seguro.
     */
    public function test_respaldo_subfolder_rotation_ensures_single_active_file_for_radioboss_selection(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');
        config(['services.radioboss.archive_to_respaldo' => true]);

        $master = MasterProgram::factory()->create([
            'nombre' => 'Polución',
            'ruta_ftp' => '04.- JUEVES\18.- POLUCION*1', // Formato exacto del evento de RadioBOSS
        ]);

        $activeFolder = '04.- JUEVES/18.- POLUCION';

        // 1. Semana anterior: el episodio 88 reside en la carpeta remota
        $previousEpPath = $activeFolder . '/088.- POLUCION 24-10-2026.mp3';
        Storage::disk('radioboss')->put($previousEpPath, 'audio-episodio-88-anterior');

        // 2. Nueva semana: el conductor entrega el episodio 89
        $newEpSource = 'programas_procesados/polucion/089.- POLUCION 31-10-2026.mp3';
        $newEpContent = 'audio-episodio-89-nuevo-aprobado';
        Storage::disk('r2')->put($newEpSource, $newEpContent);

        $newEpisode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Polución Ep 89',
            'conductor' => 'Locutor Polución',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => $newEpSource,
            'archivo_mp3_disk' => 'r2',
        ]);

        $job = new UploadRadiobossJob($newEpisode->id);
        $job->handle();

        $newEpisode->refresh();
        $this->assertSame('radioboss_verified', $newEpisode->radioboss_status);
        $this->assertTrue((bool) $newEpisode->enviado_radioboss);

        // VERIFICACIÓN DETERMINISTA:
        // A. En la raíz de la carpeta remota solo debe haber UN archivo de audio: el nuevo episodio 89
        $rootFiles = Storage::disk('radioboss')->files($activeFolder);
        $audioFiles = array_values(array_filter($rootFiles, fn ($f) => str_ends_with($f, '.mp3')));
        $this->assertCount(1, $audioFiles, 'La carpeta de emisión de RadioBOSS debe contener exactamente 1 archivo activo para que *1 no elija el anterior');
        $this->assertSame($activeFolder . '/089.- POLUCION 31-10-2026.mp3', $audioFiles[0]);
        $this->assertSame($newEpContent, Storage::disk('radioboss')->get($audioFiles[0]));

        // B. El episodio anterior 88 NO fue eliminado: reside intacto en el directorio externo 'respaldos_programas/'
        $backupPath = 'respaldos_programas/' . $activeFolder . '/088.- POLUCION 24-10-2026.mp3';
        $this->assertTrue(Storage::disk('radioboss')->exists($backupPath), 'El episodio anterior debe estar preservado en respaldos_programas/ fuera del árbol del evento');
        $this->assertSame('audio-episodio-88-anterior', Storage::disk('radioboss')->get($backupPath));

        // C. Si la siguiente semana falla la entrega, el episodio 89 permanece en la raíz y sirve de respaldo
        // Simulamos que el episodio 90 falla durante la transferencia:
        $failingDisk = new class(Storage::disk('radioboss')) {
            public function __construct(private $inner) {}
            public function __call($name, $arguments) {
                if ($name === 'writeStream' || $name === 'put') {
                    return false;
                }
                return $this->inner->$name(...$arguments);
            }
        };
        Storage::set('radioboss', $failingDisk);

        $failingEp = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Polución Ep 90',
            'conductor' => 'Locutor Polución',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => 'programas_procesados/polucion/090.- POLUCION 07-11-2026.mp3',
            'archivo_mp3_disk' => 'r2',
        ]);
        Storage::disk('r2')->put('programas_procesados/polucion/090.- POLUCION 07-11-2026.mp3', 'audio-fallido');

        $failingJob = new UploadRadiobossJob($failingEp->id);
        $failingJob->handle();

        $failingEp->refresh();
        $this->assertSame('radioboss_error', $failingEp->radioboss_status);

        // El episodio 89 SIGUE en la raíz de emisión intacto para sonar como respaldo en RadioBOSS
        $this->assertTrue(Storage::disk('radioboss')->exists($activeFolder . '/089.- POLUCION 31-10-2026.mp3'));
        $this->assertSame($newEpContent, Storage::disk('radioboss')->get($activeFolder . '/089.- POLUCION 31-10-2026.mp3'));
    }

    /**
     * 17. Bloqueo por carpeta de emisión: dos episodios con nombres distintos destinados a la misma carpeta
     * no pueden transferirse ni rotar simultáneamente.
     */
    public function test_two_concurrent_episodes_with_different_filenames_for_same_folder_blocked_by_folder_lock(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');

        $master = MasterProgram::factory()->create([
            'nombre' => 'Rock al Palo',
            'ruta_ftp' => '02.- MARTES/15.- ROCK AL PALO',
        ]);

        $folder = '02.- MARTES/15.- ROCK AL PALO';

        // Episodio A con nombre A
        $pathA = 'programas_procesados/rockalpalo/010.- ROCK AL PALO 31-10-2026.mp3';
        Storage::disk('r2')->put($pathA, 'audio-ep-a');
        $epA = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Rock al Palo Ep 10',
            'conductor' => 'Locutor',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => $pathA,
            'archivo_mp3_disk' => 'r2',
        ]);

        // Episodio B con nombre B (distinto nombre, misma carpeta de emisión)
        $pathB = 'programas_procesados/rockalpalo/011.- ROCK AL PALO 07-11-2026.mp3';
        Storage::disk('r2')->put($pathB, 'audio-ep-b');
        $epB = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Rock al Palo Ep 11',
            'conductor' => 'Locutor',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => $pathB,
            'archivo_mp3_disk' => 'r2',
        ]);

        // Simulamos que el Episodio A adquirió el bloqueo por carpeta de emisión
        $folderLockKey = 'upload_radioboss_folder_' . sha1(strtolower($folder));
        $folderLock = Cache::lock($folderLockKey, 1800);
        $this->assertTrue($folderLock->get());

        try {
            // El Episodio B intenta transferir a la misma carpeta con nombre diferente
            $jobB = new UploadRadiobossJob($epB->id);
            $jobB->handle();

            $epB->refresh();
            // Debe ser rechazado limpiamente por el bloqueo de carpeta
            $this->assertSame('radioboss_error', $epB->radioboss_status);
            $this->assertFalse((bool) $epB->enviado_radioboss);
            $this->assertStringContainsString('Carpeta de emisión (\'02.- MARTES/15.- ROCK AL PALO\') bloqueada por otra transferencia o rotación activa', (string) $epB->radioboss_last_error);

            // El archivo B no fue escrito
            $this->assertFalse(Storage::disk('radioboss')->exists($folder . '/011.- ROCK AL PALO 07-11-2026.mp3'));
        } finally {
            $folderLock->release();
        }

        // Una vez liberado el bloqueo de carpeta, el Episodio A puede completar
        $jobA = new UploadRadiobossJob($epA->id);
        $jobA->handle();

        $epA->refresh();
        $this->assertSame('radioboss_verified', $epA->radioboss_status);
        $this->assertTrue((bool) $epA->enviado_radioboss);
        $this->assertTrue(Storage::disk('radioboss')->exists($folder . '/010.- ROCK AL PALO 31-10-2026.mp3'));
    }

    /**
     * 18. Aborto inmediato y comprobado de la rotación si falla la creación del directorio de respaldo
     * o el traslado del archivo previo. No continúa promoviendo el nuevo archivo.
     */
    public function test_backup_rotation_aborts_immediately_on_directory_creation_or_move_failure(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');
        config(['services.radioboss.archive_to_respaldo' => true]);

        $master = MasterProgram::factory()->create([
            'nombre' => 'Zona del Metal',
            'ruta_ftp' => '02.- MARTES/22.- ZONA DEL METAL',
        ]);

        $folder = '02.- MARTES/22.- ZONA DEL METAL';
        $prevAudio = $folder . '/001.- ZONA DEL METAL 24-10-2026.mp3';
        Storage::disk('radioboss')->put($prevAudio, 'audio-previo-intacto');

        $newSource = 'programas_procesados/zonadelmetal/002.- ZONA DEL METAL 31-10-2026.mp3';
        Storage::disk('r2')->put($newSource, 'nuevo-audio-intento');
        $ep = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Zona del Metal Ep 2',
            'conductor' => 'Locutor',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => $newSource,
            'archivo_mp3_disk' => 'r2',
        ]);

        // Simular que makeDirectory para el directorio de respaldo externo falla (devuelve false)
        $failingMakeDirDisk = new class(Storage::disk('radioboss')) {
            public function __construct(private $inner) {}
            public function __call($name, $arguments) {
                if ($name === 'makeDirectory' && str_starts_with((string) ($arguments[0] ?? ''), 'respaldos_programas')) {
                    return false; // Simula fallo en permisos o creación FTP del respaldo
                }
                return $this->inner->$name(...$arguments);
            }
        };
        Storage::set('radioboss', $failingMakeDirDisk);

        $job = new UploadRadiobossJob($ep->id);
        $job->handle();

        $ep->refresh();
        $this->assertSame('radioboss_error', $ep->radioboss_status);
        $this->assertFalse((bool) $ep->enviado_radioboss);
        $this->assertStringContainsString('Fallo al crear directorio de respaldo externo', (string) $ep->radioboss_last_error);

        // INVARIANTE: El archivo previo DEBE seguir existiendo en la raíz de emisión intacto
        $this->assertTrue(Storage::disk('radioboss')->exists($prevAudio));
        $this->assertSame('audio-previo-intacto', Storage::disk('radioboss')->get($prevAudio));

        // El nuevo archivo NO fue promovido
        $this->assertFalse(Storage::disk('radioboss')->exists($folder . '/002.- ZONA DEL METAL 31-10-2026.mp3'));
    }

    /**
     * 19. Protección de emisión en vivo: si la cola se procesa durante la ventana de emisión del programa
     * (15 min antes hasta 15 min después), la rotación se aplaza para no interrumpir el audio en el aire.
     */
    public function test_live_broadcast_protection_window_postpones_rotation_to_prevent_airplay_interruption(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');

        $master = MasterProgram::factory()->create([
            'nombre' => 'Polución',
            'dia_transmision' => 'JUEVES',
            'hora_transmision' => '18:00:00', // Emisión a las 18:00 Caracas
            'duracion_minutos' => 120, // Finaliza a las 20:00 Caracas
            'timezone' => 'America/Caracas',
            'ruta_ftp' => '04.- JUEVES/18.- POLUCION',
        ]);

        $source = 'programas_procesados/polucion/089.- POLUCION 31-10-2026.mp3';
        Storage::disk('r2')->put($source, 'audio-content');
        $ep = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Polución Ep 89',
            'conductor' => 'Locutor',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => $source,
            'archivo_mp3_disk' => 'r2',
        ]);

        // Simular que el worker ejecuta la cola un JUEVES a las 18:30 Caracas (en plena emisión en vivo al aire)
        Carbon::setTestNow(Carbon::parse('2026-10-29 18:30:00', 'America/Caracas')); // 2026-10-29 es Jueves

        $job = new UploadRadiobossJob($ep->id);
        $job->handle();

        $ep->refresh();
        $this->assertSame('radioboss_pending', $ep->radioboss_status);
        $this->assertFalse((bool) $ep->enviado_radioboss);
        $this->assertNull($ep->radioboss_last_error);
        $this->assertStringContainsString('Emisión en vivo protegida en curso', (string) $ep->status_message);
        $this->assertSame('radioboss_postponed', $ep->radioboss_metadata['status'] ?? null);
        $this->assertGreaterThan(0, $job->postponedSeconds);

        // El archivo NO fue tocado ni transferido durante el aire
        $this->assertFalse(Storage::disk('radioboss')->exists('04.- JUEVES/18.- POLUCION/089.- POLUCION 31-10-2026.mp3'));
    }

    /**
     * 20. Validación de destino exacto contra events.txt:
     * Si un programa no tiene correspondencia verificada ni ruta configurada, se rechaza
     * la carpeta genérica 'Programas'.
     */
    public function test_generic_programas_folder_rejected_when_no_match_in_events_txt(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');

        // Programa desconocido sin ruta FTP configurada
        $unknownMaster = MasterProgram::factory()->create([
            'nombre' => 'Programa Desconocido Sin Mapeo',
            'ruta_ftp' => '', // Vacío
        ]);

        $source = 'programas_procesados/unknown/001.- UNKNOWN 31-10-2026.mp3';
        Storage::disk('r2')->put($source, 'audio-content');
        $ep = RadioProgram::create([
            'master_program_id' => $unknownMaster->id,
            'titulo_programa' => 'Unknown Show Ep 1',
            'conductor' => 'Locutor',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => $source,
            'archivo_mp3_disk' => 'r2',
        ]);

        $job = new UploadRadiobossJob($ep->id);
        $job->handle();

        $ep->refresh();
        $this->assertSame('radioboss_error', $ep->radioboss_status);
        $this->assertFalse((bool) $ep->enviado_radioboss);
        $this->assertStringContainsString('No se pudo resolver una carpeta de emisión comprobada para \'Programa Desconocido Sin Mapeo\'', (string) $ep->radioboss_last_error);
        $this->assertStringContainsString('Se rechaza \'Programas\' como destino genérico', (string) $ep->radioboss_last_error);

        // NADA fue escrito en Programas/
        $this->assertFalse(Storage::disk('radioboss')->exists('Programas/001.- UNKNOWN 31-10-2026.mp3'));
    }

    /**
     * 21. Protección con cruce de medianoche: una emisión que inicia en un día (Jueves 23:30)
     * y termina al día siguiente (Viernes 01:30) protege adecuadamente la emisión en la madrugada del Viernes.
     */
    public function test_live_broadcast_protection_window_handles_midnight_crossing(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');

        $master = MasterProgram::factory()->create([
            'nombre' => 'ADN Sonoro del Underground',
            'dia_transmision' => 'JUEVES',
            'hora_transmision' => '23:30:00', // Inicia Jueves a las 23:30
            'duracion_minutos' => 120, // Finaliza Viernes a la 01:30 (ventana hasta la 01:45)
            'timezone' => 'America/Caracas',
            'ruta_ftp' => '04.- JUEVES/23.- ADN SONORO DEL UNDERGROUND',
        ]);

        $folder = '04.- JUEVES/23.- ADN SONORO DEL UNDERGROUND';
        Storage::disk('radioboss')->put($folder . '/001.- ADN 23-10-2026.mp3', 'audio-actual-al-aire');

        $source = 'programas_procesados/adn/002.- ADN 30-10-2026.mp3';
        Storage::disk('r2')->put($source, 'nuevo-audio');
        $ep = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'ADN Ep 2',
            'conductor' => 'Locutor',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => $source,
            'archivo_mp3_disk' => 'r2',
        ]);

        // Simular que el worker corre en la madrugada del VIERNES a las 00:30 Caracas (día distinto al inicio)
        Carbon::setTestNow(Carbon::parse('2026-10-30 00:30:00', 'America/Caracas')); // 2026-10-30 es Viernes

        $job = new UploadRadiobossJob($ep->id);
        $job->handle();

        $ep->refresh();
        // Debe aplazarse (radioboss_pending), sin marcar error
        $this->assertSame('radioboss_pending', $ep->radioboss_status);
        $this->assertFalse((bool) $ep->enviado_radioboss);
        $this->assertNull($ep->radioboss_last_error);
        $this->assertSame('radioboss_postponed', $ep->radioboss_metadata['status'] ?? null);
        $this->assertStringContainsString('Emisión en vivo protegida en curso', (string) $ep->status_message);
        $this->assertGreaterThan(0, $job->postponedSeconds);

        // El audio al aire no fue tocado
        $this->assertTrue(Storage::disk('radioboss')->exists($folder . '/001.- ADN 23-10-2026.mp3'));
        $this->assertFalse(Storage::disk('radioboss')->exists($folder . '/002.- ADN 30-10-2026.mp3'));
    }

    /**
     * 22. Duración superior a 1 hora y duración no confirmada:
     * - Si dura 150 min (2.5h), protege la emisión pasados los 60 min.
     * - Si la duración es nula o no confirmada, no asume 60 min, conserva el episodio y deja la entrega pendiente.
     */
    public function test_live_broadcast_duration_exceeding_one_hour_and_unconfirmed_duration_handling(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');

        // Caso 22A: Duración de 150 minutos (2 horas y media) de 14:00 a 16:30
        $masterLong = MasterProgram::factory()->create([
            'nombre' => 'Reinas del Rock y Metal',
            'dia_transmision' => 'VIERNES',
            'hora_transmision' => '14:00:00',
            'duracion_minutos' => 150, // 2h 30m -> ventana hasta las 16:45
            'timezone' => 'America/Caracas',
            'ruta_ftp' => '05.- VIERNES/14.- REINAS DEL ROCK Y METAL',
        ]);

        $sourceLong = 'programas_procesados/reinas/002.- REINAS 30-10-2026.mp3';
        Storage::disk('r2')->put($sourceLong, 'audio-reinas');
        $epLong = RadioProgram::create([
            'master_program_id' => $masterLong->id,
            'titulo_programa' => 'Reinas Ep 2',
            'conductor' => 'Locutora',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => $sourceLong,
            'archivo_mp3_disk' => 'r2',
        ]);

        // Worker corre a las 15:45 (a los 105 minutos de iniciado, superando ampliamente la hora)
        Carbon::setTestNow(Carbon::parse('2026-10-30 15:45:00', 'America/Caracas')); // Viernes

        $jobLong = new UploadRadiobossJob($epLong->id);
        $jobLong->handle();

        $epLong->refresh();
        $this->assertSame('radioboss_pending', $epLong->radioboss_status);
        $this->assertSame('radioboss_postponed', $epLong->radioboss_metadata['status'] ?? null);

        // Caso 22B: Duración no confirmada (duracion_minutos = 0). NO debe suponer 60 minutos.
        $masterUnconfirmed = MasterProgram::factory()->create([
            'nombre' => 'Alto Voltaje',
            'dia_transmision' => 'SABADO',
            'hora_transmision' => '19:00:00',
            'duracion_minutos' => 0, // No confirmada / 0
            'timezone' => 'America/Caracas',
            'ruta_ftp' => '06.- SABADO/19.- ALTO VOLTAJE',
        ]);

        $folderUnconfirmed = '06.- SABADO/19.- ALTO VOLTAJE';
        Storage::disk('radioboss')->put($folderUnconfirmed . '/001.- ALTO VOLTAJE 24-10-2026.mp3', 'audio-actual-vigente');

        $sourceUnconfirmed = 'programas_procesados/altovoltaje/002.- ALTO VOLTAJE 31-10-2026.mp3';
        Storage::disk('r2')->put($sourceUnconfirmed, 'audio-nuevo');
        $epUnconfirmed = RadioProgram::create([
            'master_program_id' => $masterUnconfirmed->id,
            'titulo_programa' => 'Alto Voltaje Ep 2',
            'conductor' => 'Locutor',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => $sourceUnconfirmed,
            'archivo_mp3_disk' => 'r2',
        ]);

        $jobUnconfirmed = new UploadRadiobossJob($epUnconfirmed->id);
        $jobUnconfirmed->handle();

        $epUnconfirmed->refresh();
        // Deja la entrega claramente pendiente sin marcar fallo definitivo
        $this->assertSame('radioboss_pending', $epUnconfirmed->radioboss_status);
        $this->assertFalse((bool) $epUnconfirmed->enviado_radioboss);
        $this->assertSame('radioboss_pending_info', $epUnconfirmed->radioboss_metadata['status'] ?? null);
        $this->assertStringContainsString('Duración del programa no confirmada o inválida', (string) $epUnconfirmed->status_message);

        // El episodio vigente permanece 100% conservado
        $this->assertTrue(Storage::disk('radioboss')->exists($folderUnconfirmed . '/001.- ALTO VOLTAJE 24-10-2026.mp3'));
    }

    /**
     * 23. Transferencia que entra en la ventana de emisión durante la subida:
     * La subida inicia fuera de la ventana, pero durante la transferencia el tiempo avanza al interior
     * de la ventana. La revalidación pre-movimiento limpia el temporal y aplaza sin tocar la emisión.
     */
    public function test_transfer_entering_window_during_upload_aborts_promotion_and_postpones(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');

        $master = MasterProgram::factory()->create([
            'nombre' => 'Polución',
            'dia_transmision' => 'JUEVES',
            'hora_transmision' => '18:00:00', // Ventana inicia a las 17:45
            'duracion_minutos' => 120,
            'timezone' => 'America/Caracas',
            'ruta_ftp' => '04.- JUEVES/18.- POLUCION',
        ]);

        $folder = '04.- JUEVES/18.- POLUCION';
        Storage::disk('radioboss')->put($folder . '/088.- POLUCION 24-10-2026.mp3', 'audio-actual-al-aire');

        $source = 'programas_procesados/polucion/089.- POLUCION 31-10-2026.mp3';
        Storage::disk('r2')->put($source, 'audio-pesado-100mb');
        $ep = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Polución Ep 89',
            'conductor' => 'Locutor',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => $source,
            'archivo_mp3_disk' => 'r2',
        ]);

        // Inicia a las 17:40 Caracas (FUERA de la ventana protegida)
        Carbon::setTestNow(Carbon::parse('2026-10-29 17:40:00', 'America/Caracas')); // Jueves

        // Simulamos que la subida del stream toma tiempo y finaliza a las 17:48 (DENTRO de la ventana)
        $delayedDisk = new class(Storage::disk('radioboss')) {
            public function __construct(private $inner) {}
            public function __call($name, $arguments) {
                if ($name === 'writeStream' || $name === 'put') {
                    $res = $this->inner->$name(...$arguments);
                    // Avanza el reloj a las 17:48 (dentro de la ventana de protección de las 17:45)
                    Carbon::setTestNow(Carbon::parse('2026-10-29 17:48:00', 'America/Caracas'));
                    return $res;
                }
                return $this->inner->$name(...$arguments);
            }
        };
        Storage::set('radioboss', $delayedDisk);

        $job = new UploadRadiobossJob($ep->id);
        $job->handle();

        $ep->refresh();
        // Debe aplazarse sin rotar
        $this->assertSame('radioboss_pending', $ep->radioboss_status);
        $this->assertFalse((bool) $ep->enviado_radioboss);
        $this->assertSame('radioboss_postponed', $ep->radioboss_metadata['status'] ?? null);

        // El archivo temporal .part fue limpiado
        $files = Storage::disk('radioboss')->files($folder);
        foreach ($files as $file) {
            $this->assertStringNotContainsString('.tmp_', $file);
            $this->assertStringNotContainsString('.part', $file);
        }

        // El audio al aire sigue intacto en la raíz
        $this->assertTrue(Storage::disk('radioboss')->exists($folder . '/088.- POLUCION 24-10-2026.mp3'));
        $this->assertFalse(Storage::disk('radioboss')->exists($folder . '/089.- POLUCION 31-10-2026.mp3'));
    }

    /**
     * 24. Reintento efectivo posterior:
     * Una entrega aplazada durante la ventana de protección se reintenta tras el cierre
     * de la ventana y completa exitosamente la transferencia y rotación a RadioBOSS.
     */
    public function test_postponed_delivery_retried_successfully_after_window_closes(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');
        config(['services.radioboss.archive_to_respaldo' => true]);

        $master = MasterProgram::factory()->create([
            'nombre' => 'Polución',
            'dia_transmision' => 'JUEVES',
            'hora_transmision' => '18:00:00', // Emisión de 18:00 a 20:00 (ventana cierra a las 20:15)
            'duracion_minutos' => 120,
            'timezone' => 'America/Caracas',
            'ruta_ftp' => '04.- JUEVES/18.- POLUCION',
        ]);

        $folder = '04.- JUEVES/18.- POLUCION';
        Storage::disk('radioboss')->put($folder . '/088.- POLUCION 24-10-2026.mp3', 'audio-anterior-en-aire');

        $source = 'programas_procesados/polucion/089.- POLUCION 31-10-2026.mp3';
        Storage::disk('r2')->put($source, 'audio-nuevo-definitivo');
        $ep = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Polución Ep 89',
            'conductor' => 'Locutor',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => $source,
            'archivo_mp3_disk' => 'r2',
        ]);

        // 1. Primer intento a las 19:00 Caracas (en pleno aire): se aplaza
        Carbon::setTestNow(Carbon::parse('2026-10-29 19:00:00', 'America/Caracas')); // Jueves
        $job1 = new UploadRadiobossJob($ep->id);
        $job1->handle();

        $ep->refresh();
        $this->assertSame('radioboss_pending', $ep->radioboss_status);
        $this->assertSame('radioboss_postponed', $ep->radioboss_metadata['status'] ?? null);

        // 2. Reintento a las 20:30 Caracas (después del cierre de la ventana a las 20:15)
        Carbon::setTestNow(Carbon::parse('2026-10-29 20:30:00', 'America/Caracas'));
        $job2 = new UploadRadiobossJob($ep->id);
        $job2->handle();

        $ep->refresh();
        // Ahora sí debe haber completado con éxito
        $this->assertSame('radioboss_verified', $ep->radioboss_status);
        $this->assertTrue((bool) $ep->enviado_radioboss);
        $this->assertNull($ep->radioboss_last_error);

        // El nuevo archivo está en la raíz de emisión
        $this->assertTrue(Storage::disk('radioboss')->exists($folder . '/089.- POLUCION 31-10-2026.mp3'));

        // El archivo anterior fue rotado con seguridad al directorio de respaldo externo
        $this->assertTrue(Storage::disk('radioboss')->exists('respaldos_programas/' . $folder . '/088.- POLUCION 24-10-2026.mp3'));
    }

    /**
     * 25. Corrección de cálculo en segundos con Carbon:
     * Cuando windowEnd es futuro, se calculan los segundos desde $now hasta $windowEnd de forma positiva,
     * añadiendo 60s de margen, y postponed_until guarda la fecha real del reintento con ese margen.
     */
    public function test_postponed_seconds_calculation_direction_and_margined_until_timestamp(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');

        $master = MasterProgram::factory()->create([
            'nombre' => 'Polución',
            'dia_transmision' => 'JUEVES',
            'hora_transmision' => '18:00:00', // 18:00 a 20:00 Caracas
            'duracion_minutos' => 120,        // Ventana de protección [-15m, +15m]: 17:45 a 20:15
            'timezone' => 'America/Caracas',
            'ruta_ftp' => '04.- JUEVES/18.- POLUCION',
        ]);

        $source = 'programas_procesados/polucion/089.- POLUCION 31-10-2026.mp3';
        Storage::disk('r2')->put($source, 'audio-nuevo');

        $ep = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Polución Ep 89',
            'conductor' => 'Locutor',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => $source,
            'archivo_mp3_disk' => 'r2',
        ]);

        // Momento actual: Jueves 18:15:00 Caracas.
        // Faltan exactamente 2 horas (7200 segundos) hasta windowEnd (20:15:00 Caracas).
        $tNow = Carbon::parse('2026-10-29 18:15:00', 'America/Caracas');
        Carbon::setTestNow($tNow);

        $job = new UploadRadiobossJob($ep->id);
        $job->handle();

        $ep->refresh();
        $this->assertSame('radioboss_pending', $ep->radioboss_status);

        // Retraso esperado: 7200s + 60s = 7260s (2 horas y 1 minuto)
        $this->assertSame(7260, $job->postponedSeconds);
        $this->assertSame(7260, $ep->radioboss_metadata['retry_delay_seconds'] ?? null);

        // postponed_until debe ser exactamente windowEnd + 1 minuto
        $expectedRetryAt = '2026-10-29T20:16:00-04:00';
        $this->assertSame($expectedRetryAt, $ep->radioboss_metadata['postponed_until'] ?? null);
    }

    /**
     * 26. Worker y cola reales: más de 3 aplazamientos normales no agotan los intentos del worker,
     * no generan duplicados, conservan la programación y ejecutan con éxito tras el cierre de la ventana.
     */
    public function test_worker_queue_permits_multiple_postponements_without_attempt_exhaustion(): void
    {
        Storage::fake('radioboss');
        Storage::fake('r2');

        // Configurar tabla jobs en base de datos de pruebas
        if (! \Illuminate\Support\Facades\Schema::hasTable('jobs')) {
            \Illuminate\Support\Facades\Schema::create('jobs', function ($table) {
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        config(['queue.default' => 'database']);
        \Illuminate\Support\Facades\Cache::flush();

        $master = MasterProgram::factory()->create([
            'nombre' => 'Polución',
            'dia_transmision' => 'JUEVES',
            'hora_transmision' => '18:00:00',
            'duracion_minutos' => 120, // Ventana: 17:45 a 20:15 Caracas
            'timezone' => 'America/Caracas',
            'ruta_ftp' => '04.- JUEVES/18.- POLUCION',
        ]);

        $folder = '04.- JUEVES/18.- POLUCION';
        Storage::disk('radioboss')->put($folder . '/088.- POLUCION 24-10-2026.mp3', 'prev-audio');

        $source = 'programas_procesados/polucion/089.- POLUCION 31-10-2026.mp3';
        Storage::disk('r2')->put($source, 'audio-definitivo-polucion-89');

        $ep = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Polución Ep 89',
            'conductor' => 'Locutor',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'archivo_mp3' => $source,
            'archivo_mp3_disk' => 'r2',
        ]);

        // Momento 1: 18:15 Caracas (2 horas pendientes)
        $t0 = Carbon::parse('2026-10-29 18:15:00', 'America/Caracas');
        Carbon::setTestNow($t0);

        UploadRadiobossJob::dispatch($ep->id);
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('jobs')->count());

        $worker = app('queue.worker');
        $options = new \Illuminate\Queue\WorkerOptions();

        // Ejecución Worker 1
        $worker->runNextJob('database', 'default', $options);
        $jobRecord1 = \Illuminate\Support\Facades\DB::table('jobs')->first();
        $this->assertNotNull($jobRecord1);
        $this->assertSame(7260, $jobRecord1->available_at - $t0->getTimestamp());
        $this->assertSame(1, (int) $jobRecord1->attempts);

        // Ejecución Worker 2: a las 18:45 Caracas
        $t1 = Carbon::parse('2026-10-29 18:45:00', 'America/Caracas');
        Carbon::setTestNow($t1);
        \Illuminate\Support\Facades\DB::table('jobs')->update(['available_at' => $t1->getTimestamp()]);
        $worker->runNextJob('database', 'default', $options);
        $jobRecord2 = \Illuminate\Support\Facades\DB::table('jobs')->first();
        $this->assertSame(2, (int) $jobRecord2->attempts);

        // Ejecución Worker 3: a las 19:15 Caracas
        $t2 = Carbon::parse('2026-10-29 19:15:00', 'America/Caracas');
        Carbon::setTestNow($t2);
        \Illuminate\Support\Facades\DB::table('jobs')->update(['available_at' => $t2->getTimestamp()]);
        $worker->runNextJob('database', 'default', $options);
        $jobRecord3 = \Illuminate\Support\Facades\DB::table('jobs')->first();
        $this->assertSame(3, (int) $jobRecord3->attempts);

        // Ejecución Worker 4: a las 19:45 Caracas (CUARTO despacho tras 3 aplazamientos)
        // El trabajo NO debe fallar con MaxAttemptsExceededException; debe aplazarse normalmente
        $t3 = Carbon::parse('2026-10-29 19:45:00', 'America/Caracas');
        Carbon::setTestNow($t3);
        \Illuminate\Support\Facades\DB::table('jobs')->update(['available_at' => $t3->getTimestamp()]);
        $worker->runNextJob('database', 'default', $options);

        // Demostración de que sigue disponible en cola y no hay duplicados
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('jobs')->count());
        $jobRecord4 = \Illuminate\Support\Facades\DB::table('jobs')->first();
        $this->assertSame(4, (int) $jobRecord4->attempts);

        // Ejecución Worker 5: a las 20:16:01 Caracas (cierre de ventana a las 20:15)
        $tFinal = Carbon::parse('2026-10-29 20:16:01', 'America/Caracas');
        Carbon::setTestNow($tFinal);
        // A las 20:16:01, available_at (20:16:00) ya fue superado
        $worker->runNextJob('database', 'default', $options);

        // Finalizado con éxito: removido de la cola
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('jobs')->count());

        $ep->refresh();
        $this->assertSame('radioboss_verified', $ep->radioboss_status);
        $this->assertTrue((bool) $ep->enviado_radioboss);
        $this->assertTrue(Storage::disk('radioboss')->exists($folder . '/089.- POLUCION 31-10-2026.mp3'));
        $this->assertTrue(Storage::disk('radioboss')->exists('respaldos_programas/' . $folder . '/088.- POLUCION 24-10-2026.mp3'));
    }
}
