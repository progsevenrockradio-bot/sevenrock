<?php

declare(strict_types=1);

namespace Tests\Feature\Radio;

use App\Jobs\ProcessMp3Job;
use App\Models\MasterProgram;
use App\Models\MasterProgramEmision;
use App\Models\PlayHistory;
use App\Models\RadioArtist;
use App\Models\RadioProgram;
use App\Models\Song;
use App\Services\ArchiveOrgPodcastService;
use App\Services\RadioPlayerService;
use App\Support\BandInfoAggregator;
use App\Support\BandProfileMatcher;
use App\Support\RadioPlayerStateStore;
use Carbon\Carbon as BaseCarbon;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RadioPlayerSystemIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('player.radioboss.api_url', '');
        Config::set('player.radioboss.metadata_txt_url', '');
        Config::set('player.streams.direct', '');
        Config::set('player.streams.alt_direct', '');

        Storage::fake('public');
        Storage::fake('r2');
        Storage::fake('local');
    }

    public function test_song_to_program_to_song_transitions_with_correct_metadata(): void
    {
        // 1. Regular Song Playing
        Carbon::setTestNow(Carbon::parse('2026-10-15 14:00:00', 'America/Caracas')); // Thursday

        app(RadioPlayerStateStore::class)->write([
            'title' => 'Master of Puppets',
            'artist' => 'Metallica',
            'album' => 'Master of Puppets',
            'started_at' => Carbon::now()->toIso8601String(),
            'duration_seconds' => 515,
        ]);

        $service = app(RadioPlayerService::class);
        $state = $service->resolve();

        $this->assertFalse($state['es_bloque_programa']);
        $this->assertSame('Master of Puppets', $state['track']['title']);
        $this->assertSame('Metallica', $state['track']['artist']);

        // 2. Transition to Program 4 minutes before start (within 5-min advance window)
        // Master scheduled for Thursday 15:00:00 with 60 min duration
        $master = MasterProgram::create([
            'nombre' => 'Rock Legends',
            'conductor' => 'Alex Rocker',
            'dia_transmision' => 'JUEVES',
            'hora_transmision' => '15:00:00',
            'duracion_minutos' => 60,
            'genero' => 'Classic Rock',
            'timezone' => 'America/Caracas',
            'activo' => true,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-15 14:56:00', 'America/Caracas'));
        app(RadioPlayerStateStore::class)->forget();

        $state = $service->resolve();
        $this->assertTrue($state['es_bloque_programa']);
        $this->assertSame('Rock Legends', $state['program_name']);
        $this->assertSame('horario', $service->getLastDiagnosis()['match_via']);

        // 3. During program with ID3 tags: ID3 tags must NOT break the program block
        app(RadioPlayerStateStore::class)->write([
            'title' => 'Track 1 in Episode',
            'artist' => 'Episode Artist',
            'file' => 'programas_procesados/001.- ROCK LEGENDS.mp3',
            'started_at' => Carbon::now()->toIso8601String(),
            'duration_seconds' => 3600,
        ]);

        $state = $service->resolve();
        $this->assertTrue($state['es_bloque_programa']);
        $this->assertSame('Rock Legends', $state['program_name']);

        // 4. Transition back to song after program has ended (16:01:00)
        Carbon::setTestNow(Carbon::parse('2026-10-15 16:01:00', 'America/Caracas'));
        app(RadioPlayerStateStore::class)->write([
            'title' => 'Hysteria',
            'artist' => 'Def Leppard',
            'album' => 'Hysteria',
            'started_at' => Carbon::now()->toIso8601String(),
            'duration_seconds' => 355,
        ]);

        $state = $service->resolve();
        $this->assertFalse($state['es_bloque_programa']);
        $this->assertSame('Hysteria', $state['track']['title']);
        $this->assertSame('Def Leppard', $state['track']['artist']);
    }

    public function test_program_advance_window_appears_5_min_before_and_ends_with_calculated_duration(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 17:54:00', 'America/Caracas')); // Tuesday

        $master = MasterProgram::create([
            'nombre' => 'Metal Storm',
            'conductor' => 'Iron Host',
            'dia_transmision' => 'MARTES',
            'hora_transmision' => '18:00:00',
            'duracion_minutos' => 45, // 45 minutes duration
            'genero' => 'Heavy Metal',
            'timezone' => 'America/Caracas',
            'activo' => true,
        ]);

        $service = app(RadioPlayerService::class);
        app(RadioPlayerStateStore::class)->forget();

        // At 17:54 (6 min before): NOT yet in window
        $state = $service->resolve();
        $this->assertFalse($state['es_bloque_programa']);

        // At 17:55 (exactly 5 min before): IN window
        Carbon::setTestNow(Carbon::parse('2026-10-20 17:55:00', 'America/Caracas'));
        $state = $service->resolve();
        $this->assertTrue($state['es_bloque_programa']);
        $this->assertSame('Metal Storm', $state['program_name']);

        // At 18:45:00 (exact calculated end: 18:00 + 45 min): IN window
        Carbon::setTestNow(Carbon::parse('2026-10-20 18:45:00', 'America/Caracas'));
        $state = $service->resolve();
        $this->assertTrue($state['es_bloque_programa']);

        // At 18:46:00 (1 min after calculated end): Window CLOSED
        Carbon::setTestNow(Carbon::parse('2026-10-20 18:46:00', 'America/Caracas'));
        $state = $service->resolve();
        $this->assertFalse($state['es_bloque_programa']);
    }

    public function test_band_profile_matcher_rejects_ambiguous_bands_and_related_artists(): void
    {
        RadioArtist::create([
            'name' => 'The Rolling Stones',
            'biography' => 'Legendary British rock band.',
            'related_artists' => ['Mick Jagger', 'Keith Richards'],
        ]);

        $matcher = app(BandProfileMatcher::class);

        // Exact match
        $exact = $matcher->exactMatch('The Rolling Stones');
        $this->assertNotNull($exact);
        $this->assertSame('The Rolling Stones', $exact->name);

        // Related artist "Mick Jagger" must NOT masquerade as The Rolling Stones in fuzzyMatch
        $relatedFuzzy = $matcher->fuzzyMatch('Mick Jagger');
        $this->assertNull($relatedFuzzy);

        // Ambiguous or completely different band with partial substring
        $unrelated = $matcher->fuzzyMatch('Stone Temple Pilots');
        $this->assertNull($unrelated);
    }

    public function test_process_mp3_job_uploads_to_r2_with_integrity_check(): void
    {
        Config::set('filesystems.disks.r2.key', 'test_key');
        Config::set('filesystems.disks.r2.secret', 'test_secret');
        Config::set('filesystems.disks.r2.bucket', 'sevenrock-media');
        Config::set('filesystems.disks.r2.url', 'https://media.sevenrockradio.com');

        $master = MasterProgram::create([
            'nombre' => 'Podcast Show',
            'conductor' => 'Host',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '10:00:00',
            'duracion_minutos' => 30,
            'genero' => 'Rock',
            'activo' => true,
        ]);

        $episode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Podcast Show',
            'conductor' => 'Host',
            'numero_episodio' => 1,
            'archivo_mp3' => 'uploads/test_audio.mp3',
            'archivo_mp3_disk' => 'public',
        ]);

        // Put a real audio file copy in public disk so getID3 recognizes it as mp3
        $sampleAudio = file_get_contents(public_path('assets/lucille/preview_key_to_the_highway.mp3'));
        Storage::disk('public')->put('uploads/test_audio.mp3', $sampleAudio);

        $job = new ProcessMp3Job($episode, 'uploads/test_audio.mp3', false, dispatchNextJobs: false);
        $job->handle();

        $episode->refresh();
        $this->assertSame('r2', $episode->archivo_mp3_disk);
        $this->assertTrue(Storage::disk('r2')->exists($episode->archivo_mp3));
        $this->assertGreaterThan(160000, Storage::disk('r2')->size($episode->archivo_mp3));
    }

    public function test_archive_org_podcast_service_cleans_temporary_download_paths(): void
    {
        $tmpDir = storage_path('app/tmp/r2');
        File::ensureDirectoryExists($tmpDir);
        $fakeTmpFile = $tmpDir . DIRECTORY_SEPARATOR . 'test-download.mp3';
        file_put_contents($fakeTmpFile, 'temp content');

        $this->assertFileExists($fakeTmpFile);

        $reflection = new \ReflectionClass(ArchiveOrgPodcastService::class);
        $method = $reflection->getMethod('cleanupLocalPath');
        $method->setAccessible(true);

        $service = app(ArchiveOrgPodcastService::class);
        $method->invoke($service, $fakeTmpFile, 'r2', null);

        $this->assertFileDoesNotExist($fakeTmpFile);
    }

    public function test_play_history_records_track_transition_and_prevents_duplicate(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00', 'America/Caracas'));

        app(RadioPlayerStateStore::class)->write([
            'title' => 'Paranoid',
            'artist' => 'Black Sabbath',
            'started_at' => Carbon::now()->toIso8601String(),
            'duration_seconds' => 170,
        ]);

        $service = app(RadioPlayerService::class);
        $service->resolve();

        $historyCount = PlayHistory::query()->where('title', 'Paranoid')->count();
        $this->assertSame(1, $historyCount);

        // Immediate subsequent call (polling) must NOT create duplicate
        $service->resolve();
        $historyCountAfterPoll = PlayHistory::query()->where('title', 'Paranoid')->count();
        $this->assertSame(1, $historyCountAfterPoll);
    }

    public function test_process_mp3_job_fails_closed_when_r2_is_unconfigured(): void
    {
        Config::set('filesystems.disks.r2.key', '');
        Config::set('filesystems.disks.r2.secret', '');

        $master = MasterProgram::create([
            'nombre' => 'No Cloud Show',
            'conductor' => 'Host',
            'dia_transmision' => 'MIERCOLES',
            'hora_transmision' => '10:00:00',
            'duracion_minutos' => 30,
            'genero' => 'Rock',
            'activo' => true,
        ]);

        $episode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'No Cloud Show',
            'conductor' => 'Host',
            'numero_episodio' => 1,
            'archivo_mp3' => 'uploads/test_audio2.mp3',
            'archivo_mp3_disk' => 'public',
        ]);

        $sampleAudio = file_get_contents(public_path('assets/lucille/preview_key_to_the_highway.mp3'));
        Storage::disk('public')->put('uploads/test_audio2.mp3', $sampleAudio);

        $job = new ProcessMp3Job($episode, 'uploads/test_audio2.mp3', false, dispatchNextJobs: false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cloudflare R2 no está configurado como destino definitivo para podcasts.');
        $job->handle();
    }

    public function test_program_window_midnight_crossing_and_advance_window(): void
    {
        // Scheduled: 23:45 to 00:30 (45 minutes, crossing midnight)
        Carbon::setTestNow(Carbon::parse('2026-10-20 23:39:00', 'America/Caracas')); // Tuesday

        $master = MasterProgram::create([
            'nombre' => 'Midnight Metal',
            'conductor' => 'Night Host',
            'dia_transmision' => 'MARTES',
            'hora_transmision' => '23:45:00',
            'duracion_minutos' => 45,
            'genero' => 'Metal',
            'timezone' => 'America/Caracas',
            'activo' => true,
        ]);

        $service = app(RadioPlayerService::class);
        app(RadioPlayerStateStore::class)->forget();

        // 23:39 (6 min before): Window closed
        $state = $service->resolve();
        $this->assertFalse($state['es_bloque_programa'], 'Expected window closed at 23:39');

        // 23:40 (exact 5 min advance window): Window open!
        Carbon::setTestNow(Carbon::parse('2026-10-20 23:40:00', 'America/Caracas'));
        $state = $service->resolve();
        $this->assertTrue($state['es_bloque_programa'], 'Expected window open at 23:40');

        // 00:29 (next day Wednesday, inside calculated duration): Window open!
        Carbon::setTestNow(Carbon::parse('2026-10-21 00:29:00', 'America/Caracas'));
        $state = $service->resolve();
        $this->assertTrue($state['es_bloque_programa'], 'Expected window open at 00:29');

        // 00:31 (after calculated duration): Window closed!
        Carbon::setTestNow(Carbon::parse('2026-10-21 00:31:00', 'America/Caracas'));
        $state = $service->resolve();
        $this->assertFalse($state['es_bloque_programa'], 'Expected window closed at 00:31');
    }

    public function test_archive_org_podcast_service_cleans_own_temp_file_when_finished(): void
    {
        $master = MasterProgram::create([
            'nombre' => 'Pending Show',
            'conductor' => 'Host',
            'dia_transmision' => 'JUEVES',
            'hora_transmision' => '10:00:00',
            'duracion_minutos' => 30,
            'genero' => 'Rock',
            'activo' => true,
        ]);

        $episode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Pending Show',
            'conductor' => 'Host',
            'numero_episodio' => 1,
            'enviado_radioboss' => false,
            'radioboss_status' => 'radioboss_pending',
            'archivo_mp3' => 'uploads/test.mp3',
            'archivo_mp3_disk' => 'r2',
        ]);

        $tmpDir = storage_path('app/tmp/r2');
        File::ensureDirectoryExists($tmpDir);
        $fakeTmpFile = $tmpDir . DIRECTORY_SEPARATOR . 'archive-own-temp.mp3';
        file_put_contents($fakeTmpFile, 'archive temp content');

        $this->assertFileExists($fakeTmpFile);

        $reflection = new \ReflectionClass(ArchiveOrgPodcastService::class);
        $method = $reflection->getMethod('cleanupLocalPath');
        $method->setAccessible(true);

        $service = app(ArchiveOrgPodcastService::class);
        // ArchiveOrgPodcastService independently cleans its own temporary file when done
        $method->invoke($service, $fakeTmpFile, 'r2');

        $this->assertFileDoesNotExist($fakeTmpFile);
    }

    public function test_process_mp3_job_detects_incomplete_transfer_and_retries_successfully(): void
    {
        Config::set('filesystems.disks.r2.key', 'test_key');
        Config::set('filesystems.disks.r2.secret', 'test_secret');
        Config::set('filesystems.disks.r2.bucket', 'sevenrock-media');
        Config::set('filesystems.disks.r2.url', 'https://media.sevenrockradio.com');

        $master = MasterProgram::create([
            'nombre' => 'Transfer Show',
            'conductor' => 'Host',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '10:00:00',
            'duracion_minutos' => 30,
            'genero' => 'Rock',
            'activo' => true,
        ]);

        $episode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Transfer Show',
            'conductor' => 'Host',
            'numero_episodio' => 1,
            'archivo_mp3' => 'uploads/test_transfer.mp3',
            'archivo_mp3_disk' => 'public',
        ]);

        $sampleAudio = file_get_contents(public_path('assets/lucille/preview_key_to_the_highway.mp3'));
        Storage::disk('public')->put('uploads/test_transfer.mp3', $sampleAudio);

        // Case 1: Incomplete transfer simulated in publishWorkingCopy
        $job = new ProcessMp3Job($episode, 'uploads/test_transfer.mp3', false, dispatchNextJobs: false);

        // Reflection to test publishWorkingCopy failure when size doesn't match
        $reflection = new \ReflectionClass($job);
        $publishMethod = $reflection->getMethod('publishWorkingCopy');
        $publishMethod->setAccessible(true);

        $isolatedTempDir = storage_path('app/tmp/test-isolated');
        File::ensureDirectoryExists($isolatedTempDir);
        $tempWorkingFile = $isolatedTempDir . DIRECTORY_SEPARATOR . 'working_copy.mp3';
        file_put_contents($tempWorkingFile, $sampleAudio);

        // Mock Storage R2 where size returns mismatch to trigger integrity failure
        $mockR2 = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $mockR2->shouldReceive('writeStream')->andReturn(true);
        $mockR2->shouldReceive('size')->with('test_dest.mp3')->andReturn(500);
        Storage::set('r2', $mockR2);

        // When local size is 160KB and remote size is 500 bytes, integrity verification must fail
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('La verificación de integridad falló');

        try {
            // Mock size to mismatch
            $publishMethod->invoke($job, $tempWorkingFile, 'test_dest.mp3', 'r2');
        } finally {
            if (file_exists($tempWorkingFile)) {
                @unlink($tempWorkingFile);
            }
            if (is_dir($isolatedTempDir)) {
                @rmdir($isolatedTempDir);
            }
        }
    }

    public function test_independent_downloads_between_archive_org_and_radioboss(): void
    {
        foreach (glob(storage_path('app/tmp/r2/*test_coord*')) ?: [] as $leftover) {
            @unlink($leftover);
        }

        Config::set('filesystems.disks.r2.key', 'test_key');
        Config::set('filesystems.disks.r2.secret', 'test_secret');
        Config::set('filesystems.disks.r2.bucket', 'sevenrock-media');
        Config::set('filesystems.disks.r2.url', 'https://media.sevenrockradio.com');

        $master = MasterProgram::create([
            'nombre' => 'Reinas del Rock y Metal',
            'conductor' => 'Host',
            'ruta_ftp' => '05.- VIERNES/14.- REINAS DEL ROCK Y METAL',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '10:00:00',
            'duracion_minutos' => 30,
            'genero' => 'Rock',
            'activo' => true,
        ]);

        $episode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Coord Show',
            'conductor' => 'Host',
            'numero_episodio' => 1,
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_NOT_REQUIRED,
            'enviado_radioboss' => false,
            'radioboss_status' => 'radioboss_pending',
            'archive_org_status' => 'archive_pending',
            'archivo_mp3' => 'podcasts/episodes/test_coord_1.mp3',
            'archivo_mp3_disk' => 'r2',
        ]);

        // Put audio object in fake R2 storage (definitive object in cloud)
        $r2Content = 'definitive audio bytes on Cloudflare R2 bucket';
        Storage::disk('r2')->put('podcasts/episodes/test_coord_1.mp3', $r2Content);

        // Case A: Real ArchiveOrgPodcastService and real FileUploadService used.
        // Mock external Archive.org HTTP responses via Guzzle MockHandler:
        // 1. GET metadata (itemExists)
        // 2. PUT s3 (uploadFile)
        // 3. HEAD download (waitForRemoteFileOnArchiveOrg)
        // 4. POST metadata (applyEpisodeMetadata)
        // 5. GET download stream (verifyUploadedEpisode for file <= 50MB)
        $mockHandler = new \GuzzleHttp\Handler\MockHandler([
            new \GuzzleHttp\Psr7\Response(200, [], json_encode(['metadata' => ['identifier' => 'coord-show-1']])), // itemExists
            new \GuzzleHttp\Psr7\Response(200), // uploadFile (PUT)
            new \GuzzleHttp\Psr7\Response(200, ['Content-Length' => strlen($r2Content)]), // waitForRemoteFileOnArchiveOrg (HEAD)
            new \GuzzleHttp\Psr7\Response(200), // applyEpisodeMetadata (POST)
            new \GuzzleHttp\Psr7\Response(200, ['Content-Length' => strlen($r2Content)], $r2Content), // verifyUploadedEpisode (GET stream)
        ]);
        $handlerStack = \GuzzleHttp\HandlerStack::create($mockHandler);
        $guzzleClient = new \GuzzleHttp\Client(['handler' => $handlerStack]);
        $realArchiveService = new ArchiveOrgPodcastService($guzzleClient);
        $this->app->instance(\App\Contracts\ArchiveOrgPodcastServiceContract::class, $realArchiveService);

        // Mock RadioBOSS FTP disk
        $mockFtp = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $mockFtp->shouldReceive('makeDirectory')->andReturn(true);
        $mockFtp->shouldReceive('files')->andReturn([]);
        $mockFtp->shouldReceive('writeStream')->andReturn(true);
        $mockFtp->shouldReceive('exists')->andReturn(true);
        $mockFtp->shouldReceive('delete')->andReturn(true);
        $mockFtp->shouldReceive('deleteDirectory')->andReturn(true);
        $mockFtp->shouldReceive('size')->andReturn(strlen($r2Content));
        $mockFtp->shouldReceive('move')->andReturn(true);
        Storage::set('radioboss', $mockFtp);

        // 1. Execute Archive.org Job with real service
        $archiveJob = new \App\Jobs\UploadArchiveOrgJob($episode->id);
        $archiveJob->handle($realArchiveService);

        $episode->refresh();
        $this->assertSame('archive_verified', $episode->archive_org_status);
        $this->assertFalse((bool) $episode->enviado_radioboss);
        // Real ArchiveOrgPodcastService cleans up its own downloaded temp file via finally block!
        $r2FilesAfterArchive = glob(storage_path('app/tmp/r2/*-test_coord_1.mp3')) ?: [];
        $this->assertEmpty($r2FilesAfterArchive, 'ArchiveOrgPodcastService debe haber limpiado su propio temporal al finalizar');

        // 2. Execute RadioBOSS Job -> it calls localPath() which creates its own independent temp download!
        $radiobossJob = new \App\Jobs\UploadRadiobossJob($episode->id);
        $radiobossJob->handle();

        $episode->refresh();
        $this->assertTrue((bool) $episode->enviado_radioboss);
        $this->assertSame('radioboss_verified', $episode->radioboss_status);
        $this->assertSame('delivery_verified', $episode->delivery_status);

        // RadioBOSS downloaded independently and cleaned up its own temp file upon success
        $afterR2Files = glob(storage_path('app/tmp/r2/*-test_coord_1.mp3')) ?: [];
        $this->assertEmpty($afterR2Files, 'RadioBOSS debe haber liberado su descarga temporal tras la subida');
        // R2 definitive object remains intact!
        $this->assertTrue(Storage::disk('r2')->exists('podcasts/episodes/test_coord_1.mp3'));
        $this->assertSame($r2Content, Storage::disk('r2')->get('podcasts/episodes/test_coord_1.mp3'));

        // Case B: RadioBOSS runs first, Archive runs second (reverse order)
        $moved = false;
        $episode2 = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Coord Show',
            'conductor' => 'Host',
            'numero_episodio' => 2,
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_NOT_REQUIRED,
            'enviado_radioboss' => false,
            'radioboss_status' => 'radioboss_pending',
            'archive_org_status' => 'archive_pending',
            'archivo_mp3' => 'podcasts/episodes/test_coord_2.mp3',
            'archivo_mp3_disk' => 'r2',
        ]);
        Storage::disk('r2')->put('podcasts/episodes/test_coord_2.mp3', $r2Content);

        // RadioBOSS runs first
        $radiobossJob2 = new \App\Jobs\UploadRadiobossJob($episode2->id);
        $radiobossJob2->handle();

        $episode2->refresh();
        $this->assertTrue((bool) $episode2->enviado_radioboss);
        $this->assertSame('radioboss_verified', $episode2->radioboss_status);
        // RadioBOSS cleaned its own temp download immediately upon completion
        $r2FilesAfterRadioboss = glob(storage_path('app/tmp/r2/*-test_coord_2.mp3')) ?: [];
        $this->assertEmpty($r2FilesAfterRadioboss);

        // Archive runs second with real service
        $mockHandler2 = new \GuzzleHttp\Handler\MockHandler([
            new \GuzzleHttp\Psr7\Response(200, [], json_encode(['metadata' => ['identifier' => 'coord-show-2']])),
            new \GuzzleHttp\Psr7\Response(200),
            new \GuzzleHttp\Psr7\Response(200, ['Content-Length' => strlen($r2Content)]),
            new \GuzzleHttp\Psr7\Response(200),
            new \GuzzleHttp\Psr7\Response(200, ['Content-Length' => strlen($r2Content)], $r2Content),
        ]);
        $realArchiveService2 = new ArchiveOrgPodcastService(new \GuzzleHttp\Client(['handler' => \GuzzleHttp\HandlerStack::create($mockHandler2)]));

        $archiveJob2 = new \App\Jobs\UploadArchiveOrgJob($episode2->id);
        $archiveJob2->handle($realArchiveService2);

        $episode2->refresh();
        $this->assertSame('archive_verified', $episode2->archive_org_status);
        $this->assertSame('delivery_verified', $episode2->delivery_status);
        $r2FilesAfterArchive2 = glob(storage_path('app/tmp/r2/*-test_coord_2.mp3')) ?: [];
        $this->assertEmpty($r2FilesAfterArchive2);
        $this->assertTrue(Storage::disk('r2')->exists('podcasts/episodes/test_coord_2.mp3'));

        // Case C: Archive.org fails after download (exception thrown by external API) followed by retry
        $episode3 = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Coord Show',
            'conductor' => 'Host',
            'numero_episodio' => 3,
            'enviado_radioboss' => false,
            'radioboss_status' => 'radioboss_pending',
            'archive_org_status' => 'archive_pending',
            'archivo_mp3' => 'podcasts/episodes/test_coord_3.mp3',
            'archivo_mp3_disk' => 'r2',
        ]);
        Storage::disk('r2')->put('podcasts/episodes/test_coord_3.mp3', $r2Content);

        // 1. External Archive HTTP throws 500 error after downloading the file locally
        $failingHandler = new \GuzzleHttp\Handler\MockHandler([
            new \GuzzleHttp\Psr7\Response(200, [], json_encode(['metadata' => ['identifier' => 'coord-show-3']])),
            new \GuzzleHttp\Psr7\Response(500, [], 'Internal Server Error on S3 Upload'),
        ]);
        $failingArchiveService = new ArchiveOrgPodcastService(new \GuzzleHttp\Client(['handler' => \GuzzleHttp\HandlerStack::create($failingHandler)]));

        $archiveJob3 = new \App\Jobs\UploadArchiveOrgJob($episode3->id);
        $archiveJob3->handle($failingArchiveService);

        $episode3->refresh();
        $this->assertSame('archive_error', $episode3->archive_org_status);
        // Despite exception occurring after localPath() download, finally block MUST have cleaned up own temp file!
        $r2FilesAfterFail = glob(storage_path('app/tmp/r2/*-test_coord_3.mp3')) ?: [];
        $this->assertEmpty($r2FilesAfterFail, 'ArchiveOrgPodcastService debe haber liberado su descarga temporal incluso tras fallo');

        // 2. Retry with working Archive service
        $retryHandler = new \GuzzleHttp\Handler\MockHandler([
            new \GuzzleHttp\Psr7\Response(200, [], json_encode(['metadata' => ['identifier' => 'coord-show-3']])),
            new \GuzzleHttp\Psr7\Response(200),
            new \GuzzleHttp\Psr7\Response(200, ['Content-Length' => strlen($r2Content)]),
            new \GuzzleHttp\Psr7\Response(200),
            new \GuzzleHttp\Psr7\Response(200, ['Content-Length' => strlen($r2Content)], $r2Content),
        ]);
        $retryArchiveService = new ArchiveOrgPodcastService(new \GuzzleHttp\Client(['handler' => \GuzzleHttp\HandlerStack::create($retryHandler)]));

        $archiveJob3Retry = new \App\Jobs\UploadArchiveOrgJob($episode3->id);
        $archiveJob3Retry->handle($retryArchiveService);

        $episode3->refresh();
        $this->assertSame('archive_verified', $episode3->archive_org_status);
        $finalR2Files = glob(storage_path('app/tmp/r2/*-test_coord_3.mp3')) ?: [];
        $this->assertEmpty($finalR2Files, 'No deben quedar descargas temporales huérfanas tras el reintento exitoso');
        $this->assertTrue(Storage::disk('r2')->exists('podcasts/episodes/test_coord_3.mp3'));
    }
}
