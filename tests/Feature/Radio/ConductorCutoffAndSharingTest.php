<?php

declare(strict_types=1);

namespace Tests\Feature\Radio;

use App\Models\ConductorUploadAuthorization;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConductorCutoffAndSharingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $compiledPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sevenrock-view-cache';
        if (! is_dir($compiledPath)) {
            mkdir($compiledPath, 0777, true);
        }

        config(['view.compiled' => $compiledPath]);
        Carbon::setTestNow();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createConductorWithProgram(int $cutoffMinutes = 1440, string $hora = '20:00:00', string $dia = 'VIERNES', string $tz = 'Europe/Madrid'): array
    {
        $conductor = User::factory()->create([
            'name' => 'Conductor Prueba',
            'email' => 'conductor.test@sevenrock.local',
            'is_admin' => false,
            'is_conductor' => true,
        ]);

        $admin = User::factory()->create([
            'name' => 'Admin Reviewer',
            'email' => 'admin.test@sevenrock.local',
            'is_admin' => true,
            'is_conductor' => false,
        ]);

        $master = MasterProgram::factory()->create([
            'nombre' => 'La Noche del Rock',
            'conductor' => 'Conductor Prueba',
            'dia_transmision' => $dia,
            'hora_transmision' => $hora,
            'timezone' => $tz,
            'submission_cutoff_minutes' => $cutoffMinutes,
        ]);

        $master->authorizeConductor($conductor);

        return [$conductor, $admin, $master];
    }

    private function createEpisode(MasterProgram $master, User $conductor, array $overrides = []): RadioProgram
    {
        return RadioProgram::query()->create(array_merge([
            'master_program_id' => $master->id,
            'submitted_by_user_id' => $conductor->id,
            'titulo_programa' => (string) $master->nombre,
            'conductor' => (string) ($master->conductor ?: $conductor->name),
            'numero_episodio' => 1,
            'fecha_emision' => '2026-11-06',
            'live_title' => 'Episodio de Prueba',
            'editorial_summary' => 'Resumen editorial de prueba.',
            'resena' => 'Descripción completa de prueba.',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_PENDING,
            'editorial_version' => 1,
            'archivo_mp3' => 'programas_crudos/audio.mp3',
            'archivo_mp3_disk' => 'r2',
            'delivery_status' => 'pending_editorial',
            'radioboss_status' => 'pending_editorial',
        ], $overrides));
    }

    /**
     * Comprueba el cálculo del margen predeterminado de 90 minutos
     * y el formato legible «90 minutos».
     */
    public function test_default_cutoff_margin_is_90_minutes_and_formatted_correctly(): void
    {
        [$conductor, $admin, $master] = $this->createConductorWithProgram(90);

        $this->assertSame(90, $master->submission_cutoff_minutes);
        $this->assertSame('90 minutos', $master->formatCutoffHuman());

        // Emisión viernes 2026-11-06 a las 20:00 -> Cierre: 2026-11-06 18:30
        $emissionDate = '2026-11-06';
        $deadlineInfo = $master->calculateEmissionDeadline($emissionDate);

        $this->assertSame('ok', $deadlineInfo['status']);
        $this->assertSame(90, $deadlineInfo['cutoff_minutes']);
        $this->assertSame('90 minutos', $deadlineInfo['cutoff_human']);
        $this->assertSame('06/11/2026 18:30 (Europe/Madrid)', $deadlineInfo['deadline_formatted']);
        $this->assertFalse($deadlineInfo['crosses_midnight']);
    }

    /**
     * Comprueba que un programa con margen de 90 minutos acepta entregas a 100 minutos de la emisión
     * y bloquea entregas a 80 minutos.
     */
    public function test_program_with_90_minutes_cutoff_enforces_its_own_margin(): void
    {
        Storage::fake('r2');
        [$conductor, $admin, $master] = $this->createConductorWithProgram(90, '20:00:00');

        // Emisión: 2026-11-06 20:00. Cierre a 90 min: 18:30.
        // Caso A: 18:15 (105 minutos antes -> dentro del plazo)
        Carbon::setTestNow(Carbon::parse('2026-11-06 18:15:00', 'Europe/Madrid'));

        $mp3Content = "\xFF\xFB\x90\x00" . str_repeat('A', 5000);
        $file = UploadedFile::fake()->createWithContent('programa.mp3', $mp3Content);

        $okResponse = $this->actingAs($conductor)->post(route('conductor.episodes.store'), [
            'master_program_id' => $master->id,
            'numero_episodio' => 10,
            'fecha_emision' => '2026-11-06',
            'live_title' => 'Entrega 90 min a tiempo',
            'editorial_summary' => 'Resumen entregado 105 minutos antes de la emisión.',
            'resena' => 'Descripción completa.',
            'archivo_mp3' => $file,
        ]);
        $okResponse->assertSessionHasNoErrors();

        // Caso B: 18:40 (80 minutos antes -> cerrado porque el margen de 90 min venció a las 18:30)
        Carbon::setTestNow(Carbon::parse('2026-11-06 18:40:00', 'Europe/Madrid'));

        $blockedResponse = $this->actingAs($conductor)->post(route('conductor.episodes.store'), [
            'master_program_id' => $master->id,
            'numero_episodio' => 11,
            'fecha_emision' => '2026-11-06',
            'live_title' => 'Entrega 90 min fuera de plazo',
            'editorial_summary' => 'Resumen tardío.',
            'resena' => 'Descripción completa.',
            'archivo_mp3' => $file,
        ]);
        $blockedResponse->assertSessionHasErrors(['fecha_emision']);
    }

    /**
     * Comprueba que un programa con margen personalizado de 60 minutos (1 hora)
     * permite entregas a 75 minutos pero bloquea a 45 minutos.
     */
    public function test_program_with_60_minutes_cutoff_formats_as_1_hora_and_enforces_it(): void
    {
        Storage::fake('r2');
        [$conductor, $admin, $master] = $this->createConductorWithProgram(60, '20:00:00');

        $this->assertSame(60, $master->submission_cutoff_minutes);
        $this->assertSame('1 hora', $master->formatCutoffHuman());

        // Emisión: 2026-11-06 20:00. Cierre a 60 min: 19:00.
        // A las 18:45 (75 minutos antes de emisión) -> Aceptada
        Carbon::setTestNow(Carbon::parse('2026-11-06 18:45:00', 'Europe/Madrid'));

        $mp3Content = "\xFF\xFB\x90\x00" . str_repeat('A', 5000);
        $file = UploadedFile::fake()->createWithContent('programa.mp3', $mp3Content);

        $okResponse = $this->actingAs($conductor)->post(route('conductor.episodes.store'), [
            'master_program_id' => $master->id,
            'numero_episodio' => 12,
            'fecha_emision' => '2026-11-06',
            'live_title' => 'Entrega con margen de 1 hora',
            'editorial_summary' => 'Resumen para programa con margen de 1 hora.',
            'resena' => 'Descripción completa.',
            'archivo_mp3' => $file,
        ]);
        $okResponse->assertSessionHasNoErrors();

        // A las 19:15 (45 minutos antes de emisión) -> Bloqueada
        Carbon::setTestNow(Carbon::parse('2026-11-06 19:15:00', 'Europe/Madrid'));

        $blockedResponse = $this->actingAs($conductor)->post(route('conductor.episodes.store'), [
            'master_program_id' => $master->id,
            'numero_episodio' => 13,
            'fecha_emision' => '2026-11-06',
            'live_title' => 'Entrega 1 hora fuera de plazo',
            'editorial_summary' => 'Resumen tardío.',
            'resena' => 'Descripción completa.',
            'archivo_mp3' => $file,
        ]);
        $blockedResponse->assertSessionHasErrors(['fecha_emision']);
    }

    /**
     * Comprueba que las emisiones que cruzan medianoche calculan el límite exacto.
     * Ejemplo: Emisión a las 00:30 del miércoles 2026-11-11, con 24h (1440 min) de margen -> límite exacto a las 00:30 del martes 2026-11-10.
     */
    public function test_midnight_crossing_emission_calculates_exact_cutoff_date(): void
    {
        [$conductor, $admin, $master] = $this->createConductorWithProgram(1440, '00:30:00', 'MIERCOLES', 'Europe/Madrid');

        $emissionDate = '2026-11-11';
        $deadlineInfo = $master->calculateEmissionDeadline($emissionDate);

        $this->assertSame('ok', $deadlineInfo['status']);
        $this->assertSame('11/11/2026 00:30 (Europe/Madrid)', $deadlineInfo['emission_formatted']);
        $this->assertSame('10/11/2026 00:30 (Europe/Madrid)', $deadlineInfo['deadline_formatted']);
    }

    /**
     * Comprueba que si la hora de transmisión no está configurada, no se inventa una hora ficticia
     * y el estado devuelto es 'undetermined'.
     */
    public function test_undetermined_schedule_does_not_invent_fake_deadline(): void
    {
        [$conductor, $admin, $master] = $this->createConductorWithProgram(1440, '');
        $master->updateQuietly(['hora_transmision' => null]);

        $deadlineInfo = $master->calculateEmissionDeadline('2026-11-06');

        $this->assertSame('undetermined', $deadlineInfo['status']);
        $this->assertFalse($deadlineInfo['is_closed']);
        $this->assertNull($deadlineInfo['deadline_datetime']);
        $this->assertSame('Horario no determinado', $deadlineInfo['deadline_human']);
    }

    /**
     * Comprueba que la entrega antes del límite (ej. 26 horas antes) es aceptada.
     */
    public function test_submission_before_cutoff_is_accepted(): void
    {
        Storage::fake('r2');
        [$conductor, $admin, $master] = $this->createConductorWithProgram(1440, '20:00:00');

        // Emisión: 2026-11-06 20:00. Límite de 24h: 2026-11-05 20:00.
        // Ahora: 2026-11-05 18:00 (2 horas antes del límite).
        Carbon::setTestNow(Carbon::parse('2026-11-05 18:00:00', 'Europe/Madrid'));

        $mp3Content = "\xFF\xFB\x90\x00" . str_repeat('A', 5000);
        $file = UploadedFile::fake()->createWithContent('programa.mp3', $mp3Content);

        $response = $this->actingAs($conductor)->post(route('conductor.episodes.store'), [
            'master_program_id' => $master->id,
            'numero_episodio' => 1,
            'fecha_emision' => '2026-11-06',
            'live_title' => 'Entrega a Tiempo',
            'editorial_summary' => 'Resumen entregado con antelación suficiente al margen de 24 horas.',
            'resena' => 'Descripción completa del episodio entregado a tiempo.',
            'archivo_mp3' => $file,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('radio_programs', [
            'master_program_id' => $master->id,
            'numero_episodio' => 1,
            'live_title' => 'Entrega a Tiempo',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_PENDING,
        ]);
    }

    /**
     * Comprueba que la entrega posterior al límite de 24 horas es bloqueada en el servidor.
     */
    public function test_submission_after_cutoff_is_blocked_by_server(): void
    {
        Storage::fake('r2');
        [$conductor, $admin, $master] = $this->createConductorWithProgram(1440, '20:00:00');

        // Emisión: 2026-11-06 20:00. Límite: 2026-11-05 20:00.
        // Ahora: 2026-11-05 21:00 (1 hora DESPUÉS del cierre de 24h).
        Carbon::setTestNow(Carbon::parse('2026-11-05 21:00:00', 'Europe/Madrid'));

        $mp3Content = "\xFF\xFB\x90\x00" . str_repeat('A', 5000);
        $file = UploadedFile::fake()->createWithContent('programa.mp3', $mp3Content);

        $response = $this->actingAs($conductor)->post(route('conductor.episodes.store'), [
            'master_program_id' => $master->id,
            'numero_episodio' => 2,
            'fecha_emision' => '2026-11-06',
            'live_title' => 'Entrega Fuera de Plazo',
            'editorial_summary' => 'Intento de entrega posterior al límite de 24 horas.',
            'resena' => 'Descripción del episodio entregado tarde.',
            'archivo_mp3' => $file,
        ]);

        $response->assertSessionHasErrors(['fecha_emision']);
        $this->assertDatabaseMissing('radio_programs', [
            'master_program_id' => $master->id,
            'numero_episodio' => 2,
        ]);
    }

    /**
     * Comprueba que iniciar la subida antes pero enviar después del cierre es bloqueado por el servidor
     * (Comenzar la subida antes no reserva el plazo).
     */
    public function test_upload_started_before_cutoff_but_submitted_after_is_blocked(): void
    {
        Storage::fake('r2');
        [$conductor, $admin, $master] = $this->createConductorWithProgram(1440, '20:00:00');

        // Emisión: 2026-11-06 20:00. Cierre: 2026-11-05 20:00.
        // 1. Conductor solicita URL prefirmada 10 minutos antes del cierre (19:50)
        Carbon::setTestNow(Carbon::parse('2026-11-05 19:50:00', 'Europe/Madrid'));

        $presignRes = $this->actingAs($conductor)->postJson(route('conductor.episodes.presign'), [
            'master_program_id' => $master->id,
            'filename' => 'audio.mp3',
            'contentType' => 'audio/mpeg',
            'emission_date' => '2026-11-06',
        ]);
        $presignRes->assertOk();
        $authKey = $presignRes->json('key');
        $authToken = $presignRes->json('upload_token');

        // Simular que el archivo termina de subirse a R2
        Storage::disk('r2')->put($authKey, "\xFF\xFB\x90\x00" . str_repeat('X', 5000));

        // 2. Conductor envía el formulario 15 minutos DESPUÉS del cierre (20:15)
        Carbon::setTestNow(Carbon::parse('2026-11-05 20:15:00', 'Europe/Madrid'));

        $storeRes = $this->actingAs($conductor)->post(route('conductor.episodes.store'), [
            'master_program_id' => $master->id,
            'numero_episodio' => 3,
            'fecha_emision' => '2026-11-06',
            'live_title' => 'Entrega Retrasada por Subida Lenta',
            'editorial_summary' => 'La subida inició antes pero finalizó después del límite.',
            'resena' => 'Descripción completa.',
            'r2_key' => $authKey,
            'upload_token' => $authToken,
        ]);

        $storeRes->assertSessionHasErrors(['fecha_emision']);
        $this->assertDatabaseMissing('radio_programs', [
            'master_program_id' => $master->id,
            'numero_episodio' => 3,
        ]);
    }

    /**
     * Comprueba que el administrador puede revisar y aprobar un episodio después del cierre
     * (el margen existe precisamente para la revisión humana y producción de vídeos).
     */
    public function test_admin_can_review_and_approve_after_cutoff_without_auto_approving(): void
    {
        Storage::fake('r2');
        \Illuminate\Support\Facades\Queue::fake();
        [$conductor, $admin, $master] = $this->createConductorWithProgram(1440, '20:00:00');

        // Entregado a tiempo: 2026-11-05 15:00
        Carbon::setTestNow(Carbon::parse('2026-11-05 15:00:00', 'Europe/Madrid'));
        $episode = $this->createEpisode($master, $conductor, [
            'numero_episodio' => 4,
            'live_title' => 'Episodio a Tiempo para Revisión',
        ]);
        Storage::disk('r2')->put('programas_crudos/audio.mp3', "\xFF\xFB\x90\x00" . str_repeat('A', 5000));

        // Administrador revisa el 2026-11-06 a las 10:00 (14 horas DESPUÉS del cierre de 24h)
        Carbon::setTestNow(Carbon::parse('2026-11-06 10:00:00', 'Europe/Madrid'));

        // Comprobar que NO se auto-aprobó
        $this->assertSame(RadioProgram::EDITORIAL_STATUS_PENDING, $episode->fresh()->editorial_status);

        // Administrador aprueba explícitamente
        $approveRes = $this->actingAs($admin)->post(route('admin.podcast-uploads.editorial.approve', $episode), [
            'version' => 1,
            'vtoken' => $episode->editorial_version_token,
        ]);
        $approveRes->assertRedirect();

        $fresh = $episode->fresh();
        $this->assertSame(RadioProgram::EDITORIAL_STATUS_APPROVED, $fresh->editorial_status);
        $this->assertSame((int) $admin->id, (int) $fresh->editorial_approved_by);
        $this->assertNotNull($fresh->editorial_approved_at);
    }

    /**
     * Comprueba que el administrador puede autorizar una excepción para una entrega tardía,
     * registrando quién autoriza, cuándo y el motivo, sin auto-aprobar ni publicar.
     */
    public function test_admin_can_authorize_late_submission_exception(): void
    {
        Storage::fake('r2');
        [$conductor, $admin, $master] = $this->createConductorWithProgram(1440, '20:00:00');

        // Episodio existente creado previamente
        $episode = $this->createEpisode($master, $conductor, [
            'numero_episodio' => 5,
            'live_title' => 'Episodio Inicial',
            'archivo_mp3' => 'programas_crudos/original.mp3',
        ]);

        // Ya pasó el cierre de 24h: 2026-11-05 22:00
        Carbon::setTestNow(Carbon::parse('2026-11-05 22:00:00', 'Europe/Madrid'));

        // Intento de actualización por el conductor sin excepción -> BLOQUEADO
        $blockedUpdate = $this->actingAs($conductor)->put(route('conductor.episodes.update', $episode), [
            'numero_episodio' => 5,
            'fecha_emision' => '2026-11-06',
            'live_title' => 'Título Modificado Tarde',
            'editorial_summary' => 'Resumen modificado fuera de plazo.',
            'resena' => 'Descripción modificada.',
        ]);
        $blockedUpdate->assertSessionHasErrors(['fecha_emision']);

        // Administrador concede excepción
        $authRes = $this->actingAs($admin)->post(route('admin.podcast-uploads.editorial.authorize-late-submission', $episode), [
            'reason' => 'Conductor tuvo corte eléctrico documentado durante la grabación.',
        ]);
        $authRes->assertRedirect();

        $fresh = $episode->fresh();
        $this->assertTrue($fresh->hasActiveLateSubmissionException());
        $this->assertSame((int) $admin->id, (int) $fresh->late_submission_authorized_by);
        $this->assertSame('Conductor tuvo corte eléctrico documentado durante la grabación.', $fresh->late_submission_reason);
        // La excepción NO aprueba ni publica automáticamente
        $this->assertSame(RadioProgram::EDITORIAL_STATUS_PENDING, $fresh->editorial_status);

        // Ahora el conductor SI puede actualizar con la excepción activa
        $allowedUpdate = $this->actingAs($conductor)->put(route('conductor.episodes.update', $episode), [
            'numero_episodio' => 5,
            'fecha_emision' => '2026-11-06',
            'live_title' => 'Título Actualizado Bajo Excepción',
            'editorial_summary' => 'Resumen actualizado bajo la excepción administrativa autorizada.',
            'resena' => 'Descripción completa actualizada.',
        ]);
        $allowedUpdate->assertSessionHasNoErrors();
        $this->assertSame('Título Actualizado Bajo Excepción', $episode->fresh()->live_title);
    }

    /**
     * Comprueba que la modificación relevante tras la aprobación invalida la aprobación previa.
     */
    public function test_modification_after_approval_invalidates_prior_approval(): void
    {
        Storage::fake('r2');
        [$conductor, $admin, $master] = $this->createConductorWithProgram(1440, '20:00:00');

        // Episodio aprobado
        Carbon::setTestNow(Carbon::parse('2026-11-01 10:00:00', 'Europe/Madrid'));
        $episode = $this->createEpisode($master, $conductor, [
            'numero_episodio' => 6,
            'live_title' => 'Episodio Aprobado',
            'editorial_summary' => 'Resumen de versión 1 aprobado.',
            'resena' => 'Descripción de versión 1 aprobada.',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_approved_at' => now(),
            'editorial_approved_by' => $admin->id,
            'editorial_version' => 1,
        ]);

        $this->assertTrue($episode->isApproved());

        // Conductor modifica el resumen dentro del plazo
        Carbon::setTestNow(Carbon::parse('2026-11-03 12:00:00', 'Europe/Madrid'));
        $updateRes = $this->actingAs($conductor)->put(route('conductor.episodes.update', $episode), [
            'numero_episodio' => 6,
            'fecha_emision' => '2026-11-06',
            'live_title' => 'Episodio Aprobado con Texto Cambiado',
            'editorial_summary' => 'Nuevo resumen que altera el contenido aprobado.',
            'resena' => 'Descripción de versión 1 aprobada.',
        ]);
        $updateRes->assertSessionHasNoErrors();

        $fresh = $episode->fresh();
        // Aprobación previa invalidada
        $this->assertFalse($fresh->isApproved());
        $this->assertSame(RadioProgram::EDITORIAL_STATUS_PENDING, $fresh->editorial_status);
        $this->assertNull($fresh->editorial_approved_at);
        $this->assertNull($fresh->editorial_approved_by);
        $this->assertSame(2, (int) $fresh->editorial_version);
    }

    /**
     * Comprueba permisos de descarga pública: desactivada por defecto, activable por admin,
     * sirve archivo desde R2 con nombre legible y protege estados no autorizados.
     */
    public function test_public_download_requires_approval_r2_and_admin_toggle(): void
    {
        Storage::fake('r2');
        [$conductor, $admin, $master] = $this->createConductorWithProgram(1440, '20:00:00');

        $r2FinalPath = 'programas_procesados/la-noche-del-rock/007.- LA NOCHE DEL ROCK 06-11-2026.mp3';
        Storage::disk('r2')->put($r2FinalPath, "\xFF\xFB\x90\x00" . str_repeat('M', 8000));

        $episode = $this->createEpisode($master, $conductor, [
            'numero_episodio' => 7,
            'live_title' => 'Especial Glam Metal',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'delivery_status' => 'processed',
            'archivo_mp3' => $r2FinalPath,
            'archivo_mp3_disk' => 'r2',
            'delivery_metadata' => ['r2_verified' => true],
            'allow_public_download' => false, // Desactivado por defecto
        ]);

        $folderSlug = $episode->storageFolderSlug();

        // 1. Con descarga pública desactivada (por defecto) -> 403
        $this->get(route('public.podcasts.download', [
            'programSlug' => $folderSlug,
            'episodeNumber' => 7,
        ]))->assertForbidden();

        // 2. Administrador activa descarga pública
        $toggleRes = $this->actingAs($admin)->post(route('admin.podcast-uploads.editorial.toggle-public-download', $episode));
        $toggleRes->assertRedirect();
        $this->assertTrue($episode->fresh()->allow_public_download);

        // 3. Ahora la descarga pública tiene éxito y sirve con nombre legible
        $downloadRes = $this->get(route('public.podcasts.download', [
            'programSlug' => $folderSlug,
            'episodeNumber' => 7,
        ]));
        $downloadRes->assertOk();
        $downloadRes->assertHeader('Content-Type', 'audio/mpeg');
        $this->assertStringContainsString('007.- LA NOCHE DEL ROCK 06-11-2026.mp3', (string) $downloadRes->headers->get('Content-Disposition'));

        // 4. Si el episodio pasa a PENDING o es invalidado, la descarga pública se bloquea inmediatamente (403)
        $episode->updateQuietly(['editorial_status' => RadioProgram::EDITORIAL_STATUS_PENDING]);
        $this->get(route('public.podcasts.download', [
            'programSlug' => $folderSlug,
            'episodeNumber' => 7,
        ]))->assertForbidden();
    }

    /**
     * Comprueba que el conductor puede descargar su propio episodio aprobado desde el portal
     * con nombre legible, pero no puede descargar episodios pendientes o ajenos.
     */
    public function test_conductor_can_download_own_approved_episode_from_portal(): void
    {
        Storage::fake('r2');
        [$conductor, $admin, $master] = $this->createConductorWithProgram(1440, '20:00:00');

        $otherConductor = User::factory()->create([
            'email' => 'otro@sevenrock.local',
            'is_conductor' => true,
        ]);

        $r2FinalPath = 'programas_procesados/la-noche-del-rock/008.- LA NOCHE DEL ROCK 06-11-2026.mp3';
        Storage::disk('r2')->put($r2FinalPath, "\xFF\xFB\x90\x00" . str_repeat('Z', 8000));

        $episode = $this->createEpisode($master, $conductor, [
            'numero_episodio' => 8,
            'live_title' => 'Especial Thrash Metal',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'delivery_status' => 'processed',
            'archivo_mp3' => $r2FinalPath,
            'archivo_mp3_disk' => 'r2',
            'delivery_metadata' => ['r2_verified' => true],
        ]);

        // 1. El conductor legítimo puede descargar su episodio aprobado
        $res = $this->actingAs($conductor)->get(route('conductor.episodes.download', $episode));
        $res->assertOk();
        $this->assertStringContainsString('008.- LA NOCHE DEL ROCK 06-11-2026.mp3', (string) $res->headers->get('Content-Disposition'));

        // 2. Otro conductor no puede descargarlo -> 403
        $this->actingAs($otherConductor)
            ->get(route('conductor.episodes.download', $episode))
            ->assertForbidden();

        // 3. Conductor no puede descargarlo si no está aprobado -> 403
        $episode->updateQuietly(['editorial_status' => RadioProgram::EDITORIAL_STATUS_PENDING]);
        $this->actingAs($conductor)
            ->get(route('conductor.episodes.download', $episode))
            ->assertForbidden();
    }
}
