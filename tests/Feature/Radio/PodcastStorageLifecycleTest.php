<?php

declare(strict_types=1);

namespace Tests\Feature\Radio;

use App\Console\Commands\CleanupLocalPodcastsCommand;
use App\Contracts\ArchiveOrgPodcastServiceContract;
use App\Jobs\ProcessMp3Job;
use App\Jobs\UploadRadiobossJob;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Models\RadioProgramEvent;
use App\Models\User;
use App\Services\RadioBossService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use JamesHeinrich\GetID3\GetID3;
use RuntimeException;
use Tests\TestCase;

class PodcastStorageLifecycleTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $radiobossDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sevenrock-radioboss-lifecycle';
        if (is_dir($radiobossDir)) {
            File::deleteDirectory($radiobossDir);
        }
        parent::tearDown();
    }

    public function test_complete_upload_stores_tagged_definitive_only_in_r2_serves_playback_and_readable_download_from_r2_without_local_copies_or_temp_leaks(): void
    {
        Mail::fake();
        Storage::fake('public');
        Storage::fake('r2');
        $this->configurePodcastDisks();
        $this->fakeRadioBossService();
        $this->fakeArchiveOrgService();

        Carbon::setTestNow(Carbon::parse('2026-06-18 12:00:00'));

        $admin = User::factory()->create(['is_admin' => true]);
        $master = MasterProgram::factory()->create([
            'nombre' => 'ADN Sonoro del Underground',
            'conductor' => 'Carlos Rock',
            'dia_transmision' => 'JUEVES',
            'hora_transmision' => '21:00:00',
            'genero' => 'Rock Underground',
            'ruta_ftp' => 'Programas',
            'activo' => true,
        ]);

        $rawInboxKey = 'podcast-inbox/992212df-7f56-4c5c-b5ec-df8f3843aa93-raw-audio.mp3';
        Storage::disk('r2')->put($rawInboxKey, $this->sampleMp3Content());

        $coverFile = UploadedFile::fake()->image('cover.jpg', 300, 300);

        $beforeTmpProcessing = $this->snapshotTempFileNames('app/tmp/podcast-processing');
        $beforeTmpR2 = $this->snapshotTempFileNames('app/tmp/r2');

        // Incluso si el formulario envía download_processed_mp3=true, no debe quedar copia local permanente
        $response = $this->actingAs($admin)->post(route('admin.podcast-uploads.store'), [
            'master_program_id' => $master->id,
            'numero_episodio' => 1,
            'live_title' => 'Especial Underground #1',
            'fecha_emision' => '2026-06-18',
            'biografia_invitado' => 'Banda Invitada',
            'resena' => 'Recorrido completo del episodio.',
            'imagen_episodio_file' => $coverFile,
            'download_processed_mp3' => true,
            'sync_archive_org' => true,
            'r2_key' => $rawInboxKey,
        ]);

        $response->assertStatus(302);

        $episode = RadioProgram::query()->latest('id')->first();
        $this->assertNotNull($episode);

        $expectedFinalPath = 'programas_procesados/adn-sonoro-del-underground/001.- ADN SONORO DEL UNDERGROUND 18-06-2026.mp3';
        $expectedReadableName = '001.- ADN SONORO DEL UNDERGROUND 18-06-2026.mp3';

        // 1. Verificar almacenamiento definitivo en R2 y ausencia de copias locales permanentes
        $this->assertSame($expectedFinalPath, $episode->archivo_mp3);
        $this->assertSame('r2', $episode->archivo_mp3_disk);
        $this->assertSame($expectedReadableName, $episode->readableDownloadFilename());
        $this->assertSame('delivery_verified', $episode->delivery_status);
        $this->assertFalse((bool) data_get($episode->delivery_metadata, 'preserve_local_copy'));

        $this->assertTrue(Storage::disk('r2')->exists($expectedFinalPath), 'El archivo definitivo procesado debe conservarse en R2.');
        $this->assertFalse(Storage::disk('r2')->exists($rawInboxKey), 'El archivo original en podcast-inbox debe liberarse tras verificar R2.');
        $this->assertFalse(Storage::disk('public')->exists($expectedFinalPath), 'No debe crearse copia local permanente en public/programas_procesados.');
        $this->assertCount(0, Storage::disk('public')->allFiles('programas_procesados'), 'El directorio local programas_procesados debe permanecer vacío.');
        $this->assertCount(0, Storage::disk('public')->allFiles('podcast-inbox/*.mp3'), 'No deben quedar MP3 en podcast-inbox local.');

        // 2. Verificar etiquetas ID3v2 + portada incrustada y SHA-256 registrado en el definitivo de R2
        $r2ProcessedPath = Storage::disk('r2')->path($expectedFinalPath);
        $getID3 = new GetID3();
        $analysis = $getID3->analyze($r2ProcessedPath);
        $id3v2 = data_get($analysis, 'tags.id3v2', []);
        $this->assertSame('ADN SONORO DEL UNDERGROUND - Ep. 1 (18/06/2026)', $id3v2['title'][0] ?? null);
        $this->assertSame('Carlos Rock', $id3v2['artist'][0] ?? null);
        $this->assertSame('ADN SONORO DEL UNDERGROUND', $id3v2['album'][0] ?? null);
        $this->assertNotEmpty(data_get($analysis, 'comments.picture.0.data'), 'El MP3 definitivo en R2 debe conservar la portada incrustada.');
        $this->assertSame(hash_file('sha256', $r2ProcessedPath), data_get($episode->delivery_metadata, 'r2_sha256'));

        // 3. Verificar que no quedaron temporales abandonados tras el procesamiento y entregas
        $this->assertSame($beforeTmpProcessing, $this->snapshotTempFileNames('app/tmp/podcast-processing'));
        $this->assertSame($beforeTmpR2, $this->snapshotTempFileNames('app/tmp/r2'));

        // 4. Verificar reproducción y descarga desde el panel apuntando a R2 con nombre legible
        $this->assertSame(
            'https://media.sevenrockradio.test/' . $expectedFinalPath,
            $episode->audioPlaybackUrl()
        );

        $recentFragment = $this->actingAs($admin)->get(route('admin.podcast-uploads.recent'));
        $recentFragment->assertOk();
        $recentFragment->assertSee($expectedReadableName, false);
        $recentFragment->assertSee('data-podcast-playback-url="https://media.sevenrockradio.test/' . $expectedFinalPath . '"', false);

        $downloadResponse = $this->actingAs($admin)->get(route('admin.podcast-uploads.download', $episode));
        $downloadResponse->assertOk();
        $this->assertStringContainsString(
            $expectedReadableName,
            (string) $downloadResponse->headers->get('content-disposition')
        );

        // Enviar la respuesta binaria para disparar deleteFileAfterSend(true) sobre el temporal de descarga
        ob_start();
        $downloadResponse->sendContent();
        ob_end_clean();

        $this->assertFalse(Storage::disk('public')->exists($expectedFinalPath), 'La descarga desde R2 no debe crear una copia local permanente.');
        $this->assertTrue(Storage::disk('r2')->exists($expectedFinalPath), 'El definitivo en R2 debe seguir intacto tras la descarga.');
        $this->assertSame($beforeTmpR2, $this->snapshotTempFileNames('app/tmp/r2'), 'El temporal de descarga de R2 debe liberarse al terminar el envío.');
    }

    public function test_failure_followed_by_retry_preserves_original_until_r2_succeeds_and_cleans_all_temporaries(): void
    {
        Mail::fake();
        Storage::fake('public');
        Storage::fake('r2');
        $this->configurePodcastDisks();
        $this->fakeRadioBossService();
        $this->fakeArchiveOrgService();

        $admin = User::factory()->create(['is_admin' => true]);
        $master = MasterProgram::factory()->create([
            'nombre' => 'Programa Reintento',
            'conductor' => 'Host Retry',
            'dia_transmision' => 'VIERNES',
            'hora_transmision' => '20:00:00',
            'genero' => 'Hard Rock',
            'ruta_ftp' => 'Programas',
            'activo' => true,
        ]);

        $rawInboxPath = 'podcast-inbox/2026-06-19-programa-reintento-uuid.mp3';
        Storage::disk('public')->put($rawInboxPath, $this->sampleMp3Content());

        $episode = RadioProgram::query()->create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Programa Reintento',
            'conductor' => 'Host Retry',
            'numero_episodio' => 7,
            'fecha_emision' => '2026-06-19',
            'live_title' => 'Emisión con fallo inicial',
            'archivo_mp3' => $rawInboxPath,
            'archivo_mp3_disk' => 'public',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_NOT_REQUIRED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'radioboss_status' => 'radioboss_pending',
            'archive_org_status' => 'archive_pending',
            'delivery_status' => 'delivery_pending',
        ]);

        // Paso 1: Provocar fallo de R2 en el primer intento de ProcessMp3Job
        config([
            'filesystems.disks.r2.key' => '',
            'filesystems.disks.r2.secret' => '',
            'filesystems.disks.r2.bucket' => '',
            'filesystems.disks.r2.url' => '',
        ]);

        $beforeTmpProcessing = $this->snapshotTempFileNames('app/tmp/podcast-processing');
        $beforeTmpR2 = $this->snapshotTempFileNames('app/tmp/r2');

        $job = new ProcessMp3Job($episode, $rawInboxPath, false, $admin->id, $admin->name, $admin->email, true);
        $thrown = null;
        try {
            $job->handle();
        } catch (RuntimeException $exception) {
            $thrown = $exception;
            $job->failed($exception);
        }

        $this->assertNotNull($thrown, 'El primer intento debe fallar cuando R2 no está disponible.');
        $this->assertTrue(
            Storage::disk('public')->exists($rawInboxPath),
            'Ante un fallo, el original en podcast-inbox debe conservarse intacto para poder recuperar/reintentar.'
        );
        $this->assertSame($beforeTmpProcessing, $this->snapshotTempFileNames('app/tmp/podcast-processing'), 'Los temporales de trabajo del intento fallido deben liberarse.');

        $episode->refresh();
        $this->assertSame('delivery_failed', $episode->delivery_status);
        $this->assertSame($rawInboxPath, $episode->archivo_mp3);

        // Paso 2: Restaurar configuración de R2 y ejecutar el reintento desde el panel
        $this->configurePodcastDisks();

        $retryResponse = $this->actingAs($admin)->post(route('admin.podcast-uploads.retry', $episode), [
            'target' => 'all',
        ]);
        $retryResponse->assertStatus(302);

        $episode->refresh();
        $expectedFinalPath = 'programas_procesados/programa-reintento/007.- PROGRAMA REINTENTO 19-06-2026.mp3';

        $this->assertSame($expectedFinalPath, $episode->archivo_mp3);
        $this->assertSame('r2', $episode->archivo_mp3_disk);
        $this->assertSame('radioboss_verified', $episode->radioboss_status);
        $this->assertSame('archive_verified', $episode->archive_org_status);
        $this->assertSame('delivery_verified', $episode->delivery_status);

        $this->assertTrue(Storage::disk('r2')->exists($expectedFinalPath), 'Tras el reintento exitoso, el definitivo debe estar en R2.');
        $this->assertFalse(Storage::disk('public')->exists($rawInboxPath), 'Tras verificar el definitivo en R2 en el reintento, el original temporal en podcast-inbox debe liberarse.');
        $this->assertFalse(Storage::disk('public')->exists($expectedFinalPath), 'No debe quedar copia local permanente en public.');
        $this->assertSame($beforeTmpProcessing, $this->snapshotTempFileNames('app/tmp/podcast-processing'));
        $this->assertSame($beforeTmpR2, $this->snapshotTempFileNames('app/tmp/r2'));
    }

    public function test_download_always_uses_readable_filename_even_if_internal_key_has_technical_uuid(): void
    {
        Storage::fake('public');
        Storage::fake('r2');
        $this->configurePodcastDisks();

        $admin = User::factory()->create(['is_admin' => true]);
        $master = MasterProgram::factory()->create([
            'nombre' => 'Trovador Urbano',
            'conductor' => 'Locutor',
        ]);

        $technicalKey = 'podcast-inbox/2026-05-31-trovador-urbano-5966c37a-cf49-4e71-980b-2f204827a215.mp3';
        Storage::disk('r2')->put($technicalKey, 'ID3-FAKE-MP3-CONTENT');

        $episode = RadioProgram::query()->create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Trovador Urbano',
            'conductor' => 'Locutor',
            'numero_episodio' => 14,
            'fecha_emision' => '2026-05-31',
            'live_title' => 'Emisión 14',
            'archivo_mp3' => $technicalKey,
            'archivo_mp3_disk' => 'r2',
            'enviado_radioboss' => true,
            'radioboss_status' => 'radioboss_verified',
            'sync_archive_org' => false,
            'archive_org_status' => 'archive_skipped',
            'delivery_status' => 'delivery_verified',
        ]);

        $this->assertSame('014.- TROVADOR URBANO 31-05-2026.mp3', $episode->readableDownloadFilename());

        $response = $this->actingAs($admin)->get(route('admin.podcast-uploads.download', $episode));
        $response->assertOk();
        $this->assertStringContainsString(
            '014.- TROVADOR URBANO 31-05-2026.mp3',
            (string) $response->headers->get('content-disposition')
        );
        $this->assertStringNotContainsString(
            '5966c37a-cf49-4e71-980b-2f204827a215',
            (string) $response->headers->get('content-disposition')
        );
    }

    public function test_cleanup_command_fails_closed_when_queue_query_fails_and_preserves_local_copy(): void
    {
        Storage::fake('public');
        Storage::fake('r2');
        $this->configurePodcastDisks();

        $master = MasterProgram::factory()->create(['nombre' => 'Fallo Cola']);
        $path = 'programas_procesados/001.- FALLO COLA 01-06-2026.mp3';
        $payload = str_repeat('AUDIO-BYTES-', 128);

        Storage::disk('public')->put($path, $payload);
        Storage::disk('r2')->put($path, $payload);

        $episode = RadioProgram::query()->create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Fallo Cola',
            'conductor' => 'Host',
            'numero_episodio' => 1,
            'fecha_emision' => '2026-06-01',
            'archivo_mp3' => $path,
            'archivo_mp3_disk' => 'r2',
            'processing_started_at' => now()->subDays(12),
            'processing_finished_at' => now()->subDays(12),
            'radioboss_status' => 'radioboss_verified',
            'archive_org_status' => 'archive_verified',
            'delivery_status' => 'delivery_verified',
        ]);

        // Simular fallo de consulta / ausencia de tabla failed_jobs
        Schema::drop('failed_jobs');

        $this->artisan('podcast:cleanup-local', ['--days' => 7])->assertFailed();

        $this->assertTrue(
            Storage::disk('public')->exists($path),
            'Si no puede comprobarse completamente la cola (jobs/failed_jobs), debe conservarse la copia local.'
        );

        $auditEvent = RadioProgramEvent::query()
            ->where('radio_program_id', $episode->id)
            ->where('event_type', 'LOCAL_CLEANUP_SKIPPED')
            ->latest('id')
            ->first();

        $this->assertNotNull($auditEvent, 'Debe registrarse el motivo por el que se conservó la copia.');
        $this->assertSame('queue_verification_failed', data_get($auditEvent->metadata, 'reason_code'));
    }

    public function test_cleanup_command_inspects_more_than_500_jobs_and_detects_real_laravel_serialized_job_beyond_500(): void
    {
        Storage::fake('public');
        Storage::fake('r2');
        $this->configurePodcastDisks();

        $master = MasterProgram::factory()->create(['nombre' => 'Cola Grande']);
        $path1 = 'programas_procesados/001.- COLA GRANDE 01-06-2026.mp3';
        $path2 = 'programas_procesados/002.- COLA GRANDE 02-06-2026.mp3';
        $payloadBytes = str_repeat('VALID-MP3-CONTENT-', 100);

        Storage::disk('public')->put($path1, $payloadBytes);
        Storage::disk('r2')->put($path1, $payloadBytes);
        Storage::disk('public')->put($path2, $payloadBytes);
        Storage::disk('r2')->put($path2, $payloadBytes);

        $episodeInJobs = RadioProgram::query()->create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Cola Grande',
            'conductor' => 'Host',
            'numero_episodio' => 1,
            'fecha_emision' => '2026-06-01',
            'archivo_mp3' => $path1,
            'archivo_mp3_disk' => 'r2',
            'processing_started_at' => now()->subDays(15),
            'processing_finished_at' => now()->subDays(15),
            'radioboss_status' => 'radioboss_verified',
            'archive_org_status' => 'archive_verified',
            'delivery_status' => 'delivery_verified',
        ]);

        $episodeInFailedJobs = RadioProgram::query()->create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Cola Grande',
            'conductor' => 'Host',
            'numero_episodio' => 2,
            'fecha_emision' => '2026-06-02',
            'archivo_mp3' => $path2,
            'archivo_mp3_disk' => 'r2',
            'processing_started_at' => now()->subDays(15),
            'processing_finished_at' => now()->subDays(15),
            'radioboss_status' => 'radioboss_verified',
            'archive_org_status' => 'archive_verified',
            'delivery_status' => 'delivery_verified',
        ]);

        // Insertar 520 trabajos no relacionados delante para superar el antiguo límite de 500
        $unrelatedCommand = serialize(new UploadRadiobossJob(999999));
        $unrelatedPayload = json_encode([
            'uuid' => '00000000-0000-0000-0000-000000000000',
            'displayName' => UploadRadiobossJob::class,
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data' => [
                'commandName' => UploadRadiobossJob::class,
                'command' => $unrelatedCommand,
            ],
        ], JSON_THROW_ON_ERROR);

        $rows = [];
        $nowTs = now()->timestamp;
        for ($i = 1; $i <= 520; $i++) {
            $rows[] = [
                'queue' => 'default',
                'payload' => $unrelatedPayload,
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $nowTs,
                'created_at' => $nowTs,
            ];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('jobs')->insert($chunk);
        }

        // Trabajo #521: formato real serializado de Laravel (PHP serialize de UploadRadiobossJob sin ruta ni JSON "radioProgramId":...)
        $realSerializedJob = new UploadRadiobossJob((int) $episodeInJobs->id);
        $realLaravelPayload = json_encode([
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'displayName' => UploadRadiobossJob::class,
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data' => [
                'commandName' => UploadRadiobossJob::class,
                'command' => serialize($realSerializedJob),
            ],
        ], JSON_THROW_ON_ERROR);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => $realLaravelPayload,
            'attempts' => 1,
            'reserved_at' => $nowTs, // trabajo en ejecución
            'available_at' => $nowTs,
            'created_at' => $nowTs,
        ]);

        // Trabajo fallido reintentable en failed_jobs usando SerializesModels (ModelIdentifier de RadioProgram)
        $serializedProcessJob = new ProcessMp3Job($episodeInFailedJobs, 'other/unrelated-name.mp3', false);
        $failedPayload = json_encode([
            'uuid' => '66666666-7777-8888-9999-000000000000',
            'displayName' => ProcessMp3Job::class,
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data' => [
                'commandName' => ProcessMp3Job::class,
                'command' => serialize($serializedProcessJob),
            ],
        ], JSON_THROW_ON_ERROR);

        DB::table('failed_jobs')->insert([
            'uuid' => '66666666-7777-8888-9999-000000000000',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => $failedPayload,
            'exception' => 'Simulated transient failure',
            'failed_at' => now(),
        ]);

        $this->assertGreaterThan(500, DB::table('jobs')->count());

        $this->artisan('podcast:cleanup-local', ['--days' => 7])->assertSuccessful();

        $this->assertTrue(
            Storage::disk('public')->exists($path1),
            'El trabajo serializado real en la posición #521 (> 500) debe proteger la copia local.'
        );
        $this->assertTrue(
            Storage::disk('public')->exists($path2),
            'El trabajo fallido reintentable con ModelIdentifier en failed_jobs debe proteger la copia local.'
        );
    }

    public function test_cleanup_command_refuses_to_delete_when_remote_r2_object_has_same_size_but_different_sha256_content(): void
    {
        Storage::fake('public');
        Storage::fake('r2');
        $this->configurePodcastDisks();

        $master = MasterProgram::factory()->create(['nombre' => 'Integridad SHA256']);
        $path = 'programas_procesados/009.- INTEGRIDAD SHA256 01-06-2026.mp3';

        // Ambos archivos tienen exactamente 4096 bytes, pero distinto contenido (distinto SHA-256)
        $localBytes = str_repeat('A', 4096);
        $remoteCorruptedBytes = str_repeat('B', 4096);

        Storage::disk('public')->put($path, $localBytes);
        Storage::disk('r2')->put($path, $remoteCorruptedBytes);

        $this->assertSame(
            Storage::disk('public')->size($path),
            Storage::disk('r2')->size($path),
            'Precondición: ambos objetos tienen exactamente el mismo tamaño en bytes.'
        );

        $episode = RadioProgram::query()->create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Integridad SHA256',
            'conductor' => 'Host',
            'numero_episodio' => 9,
            'fecha_emision' => '2026-06-01',
            'archivo_mp3' => $path,
            'archivo_mp3_disk' => 'r2',
            'processing_started_at' => now()->subDays(14),
            'processing_finished_at' => now()->subDays(14),
            'radioboss_status' => 'radioboss_verified',
            'archive_org_status' => 'archive_verified',
            'delivery_status' => 'delivery_verified',
        ]);

        $this->artisan('podcast:cleanup-local', ['--days' => 7])->assertSuccessful();

        $this->assertTrue(
            Storage::disk('public')->exists($path),
            'Aunque el objeto remoto exista y tenga idéntico tamaño en bytes, si el SHA-256 difiere NO debe borrarse la copia local.'
        );

        $auditEvent = RadioProgramEvent::query()
            ->where('radio_program_id', $episode->id)
            ->where('event_type', 'LOCAL_CLEANUP_SKIPPED')
            ->latest('id')
            ->first();

        $this->assertNotNull($auditEvent);
        $this->assertSame('sha256_mismatch', data_get($auditEvent->metadata, 'reason_code'));
    }

    public function test_cleanup_command_protects_against_cross_references_and_active_download_or_retry_races(): void
    {
        Storage::fake('public');
        Storage::fake('r2');
        $this->configurePodcastDisks();

        $master = MasterProgram::factory()->create(['nombre' => 'Proteccion Carreras']);
        $sharedPath = 'programas_procesados/010.- PROTECCION CARRERAS 01-06-2026.mp3';
        $leasedPath = 'programas_procesados/011.- PROTECCION CARRERAS 02-06-2026.mp3';
        $bytes = str_repeat('IDENTICAL-CONTENT-', 128);

        Storage::disk('public')->put($sharedPath, $bytes);
        Storage::disk('r2')->put($sharedPath, $bytes);
        Storage::disk('public')->put($leasedPath, $bytes);
        Storage::disk('r2')->put($leasedPath, $bytes);

        // Episodio 1 vencido y en R2, pero otro registro (#2) sigue apuntando al mismo archivo local
        RadioProgram::query()->create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Proteccion Carreras',
            'conductor' => 'Host',
            'numero_episodio' => 10,
            'fecha_emision' => '2026-06-01',
            'archivo_mp3' => $sharedPath,
            'archivo_mp3_disk' => 'r2',
            'processing_started_at' => now()->subDays(10),
            'processing_finished_at' => now()->subDays(10),
            'radioboss_status' => 'radioboss_verified',
            'archive_org_status' => 'archive_verified',
            'delivery_status' => 'delivery_verified',
        ]);

        RadioProgram::query()->create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Proteccion Carreras Duplicado',
            'conductor' => 'Host',
            'numero_episodio' => 100,
            'fecha_emision' => '2026-06-01',
            'archivo_mp3' => $sharedPath,
            'archivo_mp3_disk' => 'public',
            'radioboss_status' => 'radioboss_pending',
            'archive_org_status' => 'archive_pending',
            'delivery_status' => 'delivery_pending',
        ]);

        // Episodio con descarga/reintento activo en curso
        $leasedEpisode = RadioProgram::query()->create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Proteccion Carreras',
            'conductor' => 'Host',
            'numero_episodio' => 11,
            'fecha_emision' => '2026-06-02',
            'archivo_mp3' => $leasedPath,
            'archivo_mp3_disk' => 'r2',
            'processing_started_at' => now()->subDays(10),
            'processing_finished_at' => now()->subDays(10),
            'radioboss_status' => 'radioboss_verified',
            'archive_org_status' => 'archive_verified',
            'delivery_status' => 'delivery_verified',
        ]);

        Cache::put(
            CleanupLocalPodcastsCommand::activeAccessCacheKey((int) $leasedEpisode->id),
            now()->toIso8601String(),
            now()->addMinutes(10)
        );

        $this->artisan('podcast:cleanup-local', ['--days' => 7])->assertSuccessful();

        $this->assertTrue(Storage::disk('public')->exists($sharedPath), 'No debe eliminarse si otro registro de radio_programs referencia el mismo archivo.');
        $this->assertTrue(Storage::disk('public')->exists($leasedPath), 'No debe eliminarse si hay una descarga o reintento activo sobre el episodio.');
    }

    public function test_cleanup_command_respects_7_day_window_supports_dry_run_and_cleans_verified_expired_copy(): void
    {
        Storage::fake('public');
        Storage::fake('r2');
        $this->configurePodcastDisks();

        Carbon::setTestNow(Carbon::parse('2026-06-10 10:00:00'));

        $master = MasterProgram::factory()->create(['nombre' => 'Vencimiento Valido']);
        $path = 'programas_procesados/012.- VENCIMIENTO VALIDO 10-06-2026.mp3';
        $bytes = str_repeat('VERIFIED-AUDIO-STREAM-', 256);
        $sha256 = hash('sha256', $bytes);

        Storage::disk('public')->put($path, $bytes);
        Storage::disk('r2')->put($path, $bytes);

        $episode = RadioProgram::query()->create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Vencimiento Valido',
            'conductor' => 'Host',
            'numero_episodio' => 12,
            'fecha_emision' => '2026-06-10',
            'archivo_mp3' => $path,
            'archivo_mp3_disk' => 'r2',
            'processing_started_at' => now(),
            'processing_finished_at' => now(),
            'radioboss_status' => 'radioboss_verified',
            'archive_org_status' => 'archive_verified',
            'delivery_status' => 'delivery_verified',
            'delivery_metadata' => [
                'r2_verified' => true,
                'r2_verified_at' => now()->toIso8601String(),
                'r2_sha256' => $sha256,
            ],
        ]);

        // 1. Antes de los 7 días (día 5): conserva la copia
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00'));
        $this->artisan('podcast:cleanup-local', ['--days' => 7])->assertSuccessful();
        $this->assertTrue(Storage::disk('public')->exists($path), 'Antes de cumplirse los 7 días debe conservarse.');

        // 2. Cumplidos los 7 días (día 8) en modo --dry-run: verifica SHA-256 pero NO elimina el archivo
        Carbon::setTestNow(Carbon::parse('2026-06-18 11:00:00'));
        $this->artisan('podcast:cleanup-local', ['--days' => 7, '--dry-run' => true])->assertSuccessful();
        $this->assertTrue(Storage::disk('public')->exists($path), 'En modo --dry-run no debe eliminarse ningún archivo.');

        // 3. Ejecución real tras vencer los 7 días con SHA-256 idéntico y cola limpia: elimina copia local y conserva R2
        $this->artisan('podcast:cleanup-local', ['--days' => 7])->assertSuccessful();
        $this->assertFalse(Storage::disk('public')->exists($path), 'Vencidos los 7 días y verificado el SHA-256 en R2, la copia local debe limpiarse.');
        $this->assertTrue(Storage::disk('r2')->exists($path), 'El definitivo en R2 debe permanecer intacto.');

        $episode->refresh();
        $this->assertSame($sha256, data_get($episode->delivery_metadata, 'local_copy_verified_sha256'));
        $this->assertNotNull(data_get($episode->delivery_metadata, 'local_copy_cleaned_at'));
    }

    public function test_program_folder_in_r2_is_stable_across_schedule_and_name_changes_and_avoids_collisions(): void
    {
        Mail::fake();
        Storage::fake('public');
        Storage::fake('r2');
        $this->configurePodcastDisks();
        $this->fakeRadioBossService();
        $this->fakeArchiveOrgService();

        $master1 = MasterProgram::factory()->create([
            'nombre' => 'Trovador Urbano',
            'conductor' => 'Locutor Urbano',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '20:00:00',
        ]);

        // Un segundo programa con el mismo nombre inicial recibe carpeta desambiguada sin colisión
        $master2 = MasterProgram::factory()->create([
            'nombre' => 'Trovador Urbano',
            'conductor' => 'Otro Locutor',
            'dia_transmision' => 'MARTES',
            'hora_transmision' => '22:00:00',
        ]);

        $this->assertSame('trovador-urbano', $master1->storage_folder);
        $this->assertNotSame($master1->storage_folder, $master2->storage_folder);
        $this->assertStringStartsWith('trovador-urbano-', (string) $master2->storage_folder);

        // Modificar horario y nombre visible NO debe alterar la carpeta estable del programa maestro
        $master1->update([
            'nombre' => 'Trovador Urbano Edición Nocturna',
            'dia_transmision' => 'VIERNES',
            'hora_transmision' => '23:30:00',
        ]);
        $master1->refresh();
        $this->assertSame('trovador-urbano', $master1->storage_folder);

        $episode = RadioProgram::query()->create([
            'master_program_id' => $master1->id,
            'titulo_programa' => 'Trovador Urbano',
            'conductor' => 'Locutor Urbano',
            'numero_episodio' => 15,
            'fecha_emision' => '2026-06-29',
            'archivo_mp3' => 'podcast-inbox/temp-trovador-15.mp3',
            'archivo_mp3_disk' => 'r2',
        ]);

        $this->assertSame(
            'programas_procesados/trovador-urbano/015.- TROVADOR URBANO 29-06-2026.mp3',
            $episode->canonicalProcessedPath()
        );
    }

    public function test_legacy_flat_paths_without_program_subfolder_remain_compatible_for_playback_download_and_retry_without_moving_files(): void
    {
        Mail::fake();
        Storage::fake('public');
        Storage::fake('r2');
        $this->configurePodcastDisks();
        $this->fakeRadioBossService();
        $this->fakeArchiveOrgService();

        $admin = User::factory()->create(['is_admin' => true]);
        $master = MasterProgram::factory()->create([
            'nombre' => 'Trovador Urbano',
            'conductor' => 'Locutor Urbano',
            'ruta_ftp' => 'Programas',
        ]);

        $legacyFlatPath = 'programas_procesados/001.- TROVADOR URBANO 18-06-2026.mp3';
        Storage::disk('r2')->put($legacyFlatPath, $this->sampleMp3Content());

        $episode = RadioProgram::query()->create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Trovador Urbano',
            'conductor' => 'Locutor Urbano',
            'numero_episodio' => 1,
            'fecha_emision' => '2026-06-18',
            'archivo_mp3' => $legacyFlatPath,
            'archivo_mp3_disk' => 'r2',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_NOT_REQUIRED,
            'radioboss_status' => 'radioboss_error',
            'archive_org_status' => 'archive_verified',
            'delivery_status' => 'delivery_partial',
        ]);

        $this->assertTrue(RadioProgram::isProcessedStoragePath($legacyFlatPath));
        $this->assertSame('https://media.sevenrockradio.test/' . $legacyFlatPath, $episode->audioPlaybackUrl());

        $downloadResponse = $this->actingAs($admin)->get(route('admin.podcast-uploads.download', $episode));
        $downloadResponse->assertOk();
        $this->assertStringContainsString('001.- TROVADOR URBANO 18-06-2026.mp3', (string) $downloadResponse->headers->get('content-disposition'));

        ob_start();
        $downloadResponse->sendContent();
        ob_end_clean();

        // El reintento selectivo reutiliza la ruta antigua sin moverla ni exigir reprocesar desde inbox
        $retryResponse = $this->actingAs($admin)->post(route('admin.podcast-uploads.retry', $episode), [
            'target' => 'radioboss',
        ]);
        $retryResponse->assertStatus(302);

        $episode->refresh();
        $this->assertSame($legacyFlatPath, $episode->archivo_mp3, 'La ruta antigua no debe moverse ni renombrarse automáticamente.');
        $this->assertTrue(Storage::disk('r2')->exists($legacyFlatPath));
        $this->assertSame('radioboss_verified', $episode->radioboss_status);
    }

    public function test_failure_persisting_definitive_reference_in_db_preserves_original_source_in_inbox_for_retry(): void
    {
        Mail::fake();
        Storage::fake('public');
        Storage::fake('r2');
        $this->configurePodcastDisks();
        $this->fakeRadioBossService();
        $this->fakeArchiveOrgService();

        $admin = User::factory()->create(['is_admin' => true]);
        $master = MasterProgram::factory()->create([
            'nombre' => 'Trovador Urbano',
            'conductor' => 'Locutor Urbano',
            'ruta_ftp' => 'Programas',
        ]);

        $rawInboxKey = 'podcast-inbox/trovador-urbano-ep15-source.mp3';
        Storage::disk('r2')->put($rawInboxKey, $this->sampleMp3Content());

        $episode = RadioProgram::query()->create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Trovador Urbano',
            'conductor' => 'Locutor Urbano',
            'numero_episodio' => 15,
            'fecha_emision' => '2026-06-29',
            'archivo_mp3' => $rawInboxKey,
            'archivo_mp3_disk' => 'r2',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_NOT_REQUIRED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'radioboss_status' => 'radioboss_pending',
            'archive_org_status' => 'archive_pending',
            'delivery_status' => 'delivery_pending',
        ]);

        // Simular fallo al persistir la referencia definitiva en BD (después de subir a R2 pero antes de limpiar el original)
        $failDbPersistOnce = true;
        RadioProgram::saving(static function (RadioProgram $savingModel) use (&$failDbPersistOnce): void {
            if ($failDbPersistOnce && RadioProgram::isProcessedStoragePath((string) $savingModel->archivo_mp3)) {
                $failDbPersistOnce = false;
                throw new RuntimeException('Simulated DB failure while persisting definitive R2 path');
            }
        });

        $job = new ProcessMp3Job($episode, $rawInboxKey, false, $admin->id, $admin->name, $admin->email, true);
        $caught = null;
        try {
            $job->handle();
        } catch (RuntimeException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertStringContainsString('Simulated DB failure', $caught->getMessage());

        // Salvaguarda crítica: el original en podcast-inbox DEBE seguir existiendo porque la BD no guardó la referencia definitiva
        $this->assertTrue(
            Storage::disk('r2')->exists($rawInboxKey),
            'Si falla el guardado de archivo_mp3 en la BD, el archivo original en podcast-inbox NO debe borrarse.'
        );

        $episode->refresh();
        $this->assertSame($rawInboxKey, $episode->archivo_mp3);

        // Ahora el reintento funciona porque el original no se perdió
        $retryJob = new ProcessMp3Job($episode, $rawInboxKey, false, $admin->id, $admin->name, $admin->email, true);
        $retryJob->handle();

        $episode->refresh();
        $expectedFinalPath = 'programas_procesados/trovador-urbano/015.- TROVADOR URBANO 29-06-2026.mp3';
        $this->assertSame($expectedFinalPath, $episode->archivo_mp3);
        $this->assertTrue(Storage::disk('r2')->exists($expectedFinalPath));
        $this->assertFalse(Storage::disk('r2')->exists($rawInboxKey), 'Una vez persistida la referencia definitiva en BD, el original de inbox sí se limpia.');
    }

    public function test_panel_retry_radioboss_on_unprocessed_inbox_episode_normalizes_first_and_passes_only_definitive_path_to_radioboss(): void
    {
        Mail::fake();
        Storage::fake('public');
        Storage::fake('r2');
        $this->configurePodcastDisks();
        Storage::fake('radioboss');
        $this->fakeRadioBossService();
        $this->fakeArchiveOrgService();

        Carbon::setTestNow(Carbon::parse('2026-11-12 10:00:00', 'America/Caracas'));

        $admin = User::factory()->create(['is_admin' => true]);
        $master = MasterProgram::factory()->create([
            'nombre' => 'Estación Rock',
            'conductor' => 'Oswaldo',
            'dia_transmision' => 'MIERCOLES',
            'hora_transmision' => '18:00:00',
            'genero' => 'Rock',
            'ruta_ftp' => 'programas',
            'activo' => true,
        ]);

        $rawInboxKey = 'podcast-inbox/8a50ed8e-9138-459e-9b6e-572857117899-er-p389-6-nov-26.mp3';
        Storage::disk('r2')->put($rawInboxKey, $this->sampleMp3Content());

        $episode = RadioProgram::query()->create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Estación Rock',
            'conductor' => 'Oswaldo',
            'numero_episodio' => 170,
            'fecha_emision' => '2026-11-11',
            'live_title' => 'Estación Rock #170',
            'archivo_mp3' => $rawInboxKey,
            'archivo_mp3_disk' => 'r2',
            'enviado_radioboss' => false,
            'radioboss_status' => 'skipped',
            'archive_org_status' => 'skipped',
            'delivery_status' => 'skipped',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_NOT_REQUIRED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'ruta_ftp_radioboss' => 'programas',
            'delivery_metadata' => [
                'storage_folder' => 'estacion-rock',
                'pipeline' => 'save-only',
                'test_flag' => 'initial',
            ],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.podcast-uploads.retry', $episode), [
            'target' => 'radioboss',
        ]);
        $response->assertStatus(302);

        $episode->refresh();
        $expectedFinalPath = 'programas_procesados/estacion-rock/170.- ESTACIóN ROCK 11-11-2026.mp3';

        $this->assertSame($expectedFinalPath, $episode->archivo_mp3);
        $this->assertSame('r2', $episode->archivo_mp3_disk);
        $this->assertTrue((bool) data_get($episode->delivery_metadata, 'r2_verified'));
        $this->assertTrue(Storage::disk('r2')->exists($expectedFinalPath));
        $this->assertNull($episode->radioboss_last_error, 'radioboss_last_error: ' . $episode->radioboss_last_error . ' | status: ' . $episode->radioboss_status . ' | msg: ' . $episode->status_message);
        $this->assertTrue($episode->enviado_radioboss);
        $this->assertSame('radioboss_verified', $episode->radioboss_status);
        $this->assertTrue(Storage::disk('radioboss')->exists('03.- MIERCOLES/22.- ESTACION ROCK/170.- ESTACION ROCK 11-11-2026.mp3'));
        $this->assertSame('skipped', $episode->archive_org_status, 'El reintento selectivo de RadioBOSS no debe disparar Archive.org.');
        $this->assertArrayNotHasKey(
            'requested_distribution_target',
            (array) ($episode->delivery_metadata ?? []),
            'requested_distribution_target debe quedar consumido y eliminado para no afectar reintentos posteriores.'
        );

        // Comprobación de que un reintento posterior no queda alterado por flags residuales
        $subsequentResponse = $this->actingAs($admin)->post(route('admin.podcast-uploads.retry', $episode), [
            'target' => 'archive',
        ]);
        $subsequentResponse->assertStatus(302);

        $episode->refresh();
        $this->assertSame('archive_verified', $episode->archive_org_status, 'El reintento posterior de Archive.org debe procesarse sin interferencia.');
        $this->assertSame('radioboss_verified', $episode->radioboss_status, 'RadioBOSS sigue verificado.');
        $this->assertArrayNotHasKey(
            'requested_distribution_target',
            (array) ($episode->delivery_metadata ?? []),
            'requested_distribution_target no debe persistir tras el segundo procesamiento.'
        );
    }

    public function test_dispatcher_registers_podcast_processed_listener_exactly_once_and_radioboss_event_never_queues_archive(): void
    {
        // 1. Confirmar con el dispatcher real de Laravel que DispatchPodcastDistributionJobs está registrado exactamente 1 vez
        $listeners = app('events')->getListeners(\App\Events\PodcastProcessed::class);
        $this->assertCount(
            1,
            $listeners,
            'PodcastProcessed debe tener exactamente un listener registrado en el dispatcher real para evitar ejecuciones dobles.'
        );

        // 2. Probar que un único evento con destino 'radioboss' despacha RadioBOSS y NUNCA encola/despacha Archive.org
        \Illuminate\Support\Facades\Bus::fake([
            \App\Jobs\UploadRadiobossJob::class,
            \App\Jobs\UploadArchiveOrgJob::class,
        ]);

        $master = MasterProgram::factory()->create([
            'nombre' => 'Estación Rock',
            'conductor' => 'Oswaldo',
            'dia_transmision' => 'MIERCOLES',
            'hora_transmision' => '18:00:00',
            'ruta_ftp' => 'programas',
            'activo' => true,
        ]);

        $processedPath = 'programas_procesados/estacion-rock/170.- ESTACION ROCK 11-11-2026.mp3';
        Storage::fake('r2');
        Storage::disk('r2')->put($processedPath, 'dummy mp3 data');

        $episode = RadioProgram::query()->create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Estación Rock',
            'conductor' => 'Oswaldo',
            'numero_episodio' => 170,
            'fecha_emision' => '2026-11-11',
            'archivo_mp3' => $processedPath,
            'archivo_mp3_disk' => 'r2',
            'enviado_radioboss' => false,
            'radioboss_status' => 'skipped',
            'archive_org_status' => 'skipped',
            'delivery_status' => 'delivery_pending',
            'delivery_metadata' => [
                'r2_verified' => true,
                'requested_distribution_target' => 'radioboss',
            ],
        ]);

        // Disparar el evento real a través del dispatcher de Laravel
        event(new \App\Events\PodcastProcessed($episode->id));

        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\UploadRadiobossJob::class, 1);
        \Illuminate\Support\Facades\Bus::assertNotDispatched(\App\Jobs\UploadArchiveOrgJob::class);

        $episode->refresh();
        $this->assertArrayNotHasKey(
            'requested_distribution_target',
            (array) ($episode->delivery_metadata ?? []),
            'requested_distribution_target debe consumirse y limpiarse inmediatamente.'
        );
    }

    public function test_failed_normalization_never_dispatches_radioboss_never_marks_delivered_and_shows_clear_error_in_panel(): void
    {
        Mail::fake();
        Storage::fake('public');
        Storage::fake('r2');
        $this->configurePodcastDisks();
        $this->fakeRadioBossService();
        $this->fakeArchiveOrgService();

        $admin = User::factory()->create(['is_admin' => true]);
        $master = MasterProgram::factory()->create([
            'nombre' => 'Estación Rock',
            'conductor' => 'Oswaldo',
            'dia_transmision' => 'MIERCOLES',
            'hora_transmision' => '18:00:00',
            'genero' => 'Rock',
            'ruta_ftp' => 'programas',
            'activo' => true,
        ]);

        $rawInboxKey = 'podcast-inbox/8a50ed8e-9138-459e-9b6e-572857117899-er-p389-6-nov-26.mp3';
        Storage::disk('public')->put($rawInboxKey, $this->sampleMp3Content());

        $episode = RadioProgram::query()->create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Estación Rock',
            'conductor' => 'Oswaldo',
            'numero_episodio' => 170,
            'fecha_emision' => '2026-11-11',
            'live_title' => 'Estación Rock #170 Fallo Normalización',
            'archivo_mp3' => $rawInboxKey,
            'archivo_mp3_disk' => 'public',
            'enviado_radioboss' => false,
            'radioboss_status' => 'skipped',
            'archive_org_status' => 'skipped',
            'delivery_status' => 'skipped',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_NOT_REQUIRED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
            'ruta_ftp_radioboss' => 'programas',
        ]);

        // Desconfigurar R2 para provocar fallo en la normalización/publicación definitiva
        config([
            'filesystems.disks.r2.key' => '',
            'filesystems.disks.r2.secret' => '',
            'filesystems.disks.r2.bucket' => '',
            'filesystems.disks.r2.url' => '',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.podcast-uploads.retry', $episode), [
            'target' => 'radioboss',
        ]);
        $response->assertStatus(302);

        $episode->refresh();

        $this->assertSame($rawInboxKey, $episode->archivo_mp3);
        $this->assertFalse($episode->enviado_radioboss, 'No debe marcarse como entregado si falla la normalización.');
        $this->assertSame('skipped', $episode->radioboss_status, 'No debe quedar como pendiente ni verificado en RadioBOSS con audio crudo.');
        $this->assertSame('delivery_failed', $episode->delivery_status);
        $this->assertNotEmpty($episode->delivery_last_error);
        $this->assertStringContainsString('Cloudflare R2 no está configurado', (string) $episode->delivery_last_error);
        $this->assertCount(0, Storage::disk('radioboss')->allFiles(), 'No debe haberse subido ningún archivo a RadioBOSS.');

        // Verificar que el panel muestra claramente el estado de fallo de audio y el detalle del error
        $fragment = $this->actingAs($admin)->get(route('admin.podcast-uploads.recent'));
        $fragment->assertOk();
        $fragment->assertSee('Audio · Falló proceso', false);
        $fragment->assertSee('Error en procesamiento de audio:', false);
        $fragment->assertSee('Cloudflare R2 no está configurado', false);

        // Comprobar también que el listener DispatchPodcastDistributionJobs bloquea cualquier evento directo con ruta cruda
        \Illuminate\Support\Facades\Bus::fake([UploadRadiobossJob::class]);
        app(\App\Listeners\DispatchPodcastDistributionJobs::class)->handle(new \App\Events\PodcastProcessed($episode->id));
        \Illuminate\Support\Facades\Bus::assertNotDispatched(UploadRadiobossJob::class);
    }

    /**
     * @return array<int, string>
     */
    private function snapshotTempFileNames(string $relativeStorageDir): array
    {
        $dir = storage_path($relativeStorageDir);
        if (! File::isDirectory($dir)) {
            return [];
        }

        $names = array_map(
            static fn (\SplFileInfo $file): string => $file->getFilename(),
            File::files($dir)
        );
        sort($names);

        return $names;
    }

    private function configurePodcastDisks(): void
    {
        $radiobossDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sevenrock-radioboss-lifecycle';
        if (is_dir($radiobossDir)) {
            File::deleteDirectory($radiobossDir);
        }

        config([
            'filesystems.disks.r2.key' => 'test-r2-key',
            'filesystems.disks.r2.secret' => 'test-r2-secret',
            'filesystems.disks.r2.bucket' => 'sevenrock-media',
            'filesystems.disks.r2.url' => 'https://media.sevenrockradio.test',
            'filesystems.disks.radioboss' => [
                'driver' => 'local',
                'root' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sevenrock-radioboss-lifecycle',
                'throw' => false,
                'report' => false,
            ],
        ]);
    }

    private function fakeRadioBossService(): void
    {
        $this->app->instance(RadioBossService::class, new class extends RadioBossService {
            public function canSync(): bool
            {
                return true;
            }

            public function upload(string $folder, string $remotePath, string $localPath, bool $clearBeforeUpload = false): void
            {
                $disk = Storage::disk('radioboss');
                $disk->makeDirectory($folder);
                $disk->put($remotePath, file_get_contents($localPath) ?: '');
            }

            public function exists(string $remotePath): bool
            {
                return Storage::disk('radioboss')->exists($remotePath);
            }

            public function read(string $remotePath): ?string
            {
                $contents = Storage::disk('radioboss')->get($remotePath);

                return is_string($contents) && $contents !== '' ? $contents : null;
            }

            public function files(string $folder): array
            {
                return Storage::disk('radioboss')->files($folder);
            }
        });
    }

    private function fakeArchiveOrgService(): void
    {
        $this->app->instance(ArchiveOrgPodcastServiceContract::class, new class implements ArchiveOrgPodcastServiceContract {
            public function canSync(): bool
            {
                return true;
            }

            public function syncEpisode(RadioProgram $episode): array
            {
                return [
                    'success' => true,
                    'created' => true,
                    'identifier' => 'test-archive-' . $episode->id,
                    'remote_path' => 'archive/' . basename((string) $episode->archivo_mp3),
                    'item_exists_before' => false,
                    'verification' => [
                        'verified' => true,
                        'pending_indexing' => false,
                        'item_url' => 'https://archive.org/details/test-archive-' . $episode->id,
                    ],
                    'status' => 'archive_verified',
                    'pending_indexing' => false,
                ];
            }

            public function patchFileMetadata(RadioProgram $episode): void
            {
            }
        });
    }

    private function sampleMp3Content(): string
    {
        $mpegFrame = "\xFF\xFB\x90\x64" . str_repeat("\x00", 413);

        return "ID3\x03\x00\x00\x00\x00\x00\x10"
            . str_repeat("\x00", 16)
            . str_repeat($mpegFrame, 55);
    }
}
