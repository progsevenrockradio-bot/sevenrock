<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\ProgramUploadedNotification;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Models\User;
use App\Services\RadioBossService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPodcastUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_processes_a_podcast_upload_from_the_admin_section(): void
    {
        Storage::fake('public');
        Storage::fake('radioboss');
        Mail::fake();
        $this->fakeRadioBossService();
        config(['services.archive_org.access_key' => '', 'services.archive_org.secret_key' => '']);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $master = MasterProgram::query()->create([
            'nombre' => 'Metal Adicto',
            'conductor' => 'John Doe',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '20:00:00',
            'genero' => 'Metal',
            'ruta_ftp' => 'Programas',
            'email_notificacion' => 'press@example.test',
            'email_copia_notificacion' => null,
            'activo' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.podcast-uploads.store'), [
                'master_program_id' => $master->id,
                'numero_episodio' => 42,
                'live_title' => 'Especial de prueba',
                'fecha_emision' => '2026-05-19',
                'biografia_invitado' => 'Invitado X',
                'resena' => 'Descripción de prueba',
                'imagen_episodio_url' => 'https://example.com/cover.jpg',
                'imagen_episodio_file' => UploadedFile::fake()->image('cover.jpg'),
                'sync_archive_org' => true,
                'archivo_mp3' => UploadedFile::fake()->create('episode.mp3', 1024, 'audio/mpeg'),
            ]);

        $response->assertRedirect(route('admin.podcast-uploads.index'));

        $episode = RadioProgram::query()->latest('id')->first();
        $this->assertNotNull($episode, 'No se creó el episodio.');
        $this->assertSame(42, $episode->numero_episodio, 'El capítulo manual no se respetó.');
        $this->assertTrue((bool) $episode->enviado_radioboss, 'RadioBOSS no quedó marcado como enviado.');
        $this->assertSame('skipped', $episode->archive_org_status, 'Archive.org no quedó en estado skipped.');
        $this->assertNotEmpty($episode->imagen_episodio, 'La imagen del episodio no quedó guardada en la base.');
        $this->assertTrue(Storage::disk('public')->exists((string) $episode->imagen_episodio), 'La imagen del episodio no quedó en el disco public.');

        $this->assertTrue(Storage::disk('public')->exists((string) $episode->archivo_mp3), 'El MP3 no quedó en el disco public.');
        $this->assertSame('verified', $episode->radioboss_status, 'RadioBOSS no quedó verificado.');

        Mail::assertSent(ProgramUploadedNotification::class);
    }

    public function test_it_auto_assigns_episode_number_when_field_is_empty(): void
    {
        Storage::fake('public');
        Storage::fake('radioboss');
        Mail::fake();
        $this->fakeRadioBossService();
        config(['services.archive_org.access_key' => '', 'services.archive_org.secret_key' => '']);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $master = MasterProgram::query()->create([
            'nombre' => 'Metal Adicto',
            'conductor' => 'John Doe',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '20:00:00',
            'genero' => 'Metal',
            'ruta_ftp' => 'Programas',
            'email_notificacion' => 'press@example.test',
            'email_copia_notificacion' => null,
            'activo' => true,
        ]);

        RadioProgram::query()->create([
            'master_program_id' => $master->id,
            'titulo_programa' => $master->nombre,
            'conductor' => $master->conductor,
            'numero_episodio' => 7,
            'fecha_emision' => '2026-05-18',
            'archivo_mp3' => 'podcast-inbox/programa/test.mp3',
            'enviado_radioboss' => false,
            'sync_archive_org' => false,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.podcast-uploads.store'), [
                'master_program_id' => $master->id,
                'numero_episodio' => null,
                'live_title' => 'Especial sin capítulo',
                'fecha_emision' => '2026-05-19',
                'resena' => 'Descripción de prueba',
                'sync_archive_org' => true,
                'archivo_mp3' => UploadedFile::fake()->create('episode.mp3', 1024, 'audio/mpeg'),
            ]);

        $response->assertRedirect(route('admin.podcast-uploads.index'));

        $episode = RadioProgram::query()->latest('id')->first();
        $this->assertNotNull($episode, 'No se creó el episodio.');
        $this->assertSame(8, $episode->numero_episodio, 'El correlativo automático no continuó desde el máximo existente.');
        Mail::assertSent(ProgramUploadedNotification::class);
    }

    public function test_it_preserves_a_local_copy_when_download_is_requested(): void
    {
        Storage::fake('public');
        Storage::fake('radioboss');
        Mail::fake();
        $this->fakeRadioBossService();
        config(['services.archive_org.access_key' => '', 'services.archive_org.secret_key' => '']);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $master = MasterProgram::query()->create([
            'nombre' => 'Metal Adicto',
            'conductor' => 'John Doe',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '20:00:00',
            'genero' => 'Metal',
            'ruta_ftp' => 'Programas',
            'email_notificacion' => 'press@example.test',
            'email_copia_notificacion' => null,
            'activo' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.podcast-uploads.store'), [
                'master_program_id' => $master->id,
                'live_title' => 'Especial para descargar',
                'fecha_emision' => '2026-05-19',
                'resena' => 'Descripción de prueba',
                'download_processed_mp3' => true,
                'sync_archive_org' => true,
                'archivo_mp3' => UploadedFile::fake()->create('episode.mp3', 1024, 'audio/mpeg'),
            ]);

        $response->assertRedirect(route('admin.podcast-uploads.index'));

        $episode = RadioProgram::query()->latest('id')->first();
        $this->assertNotNull($episode, 'No se creó el episodio.');
        $this->assertNotEmpty($episode->archivo_mp3, 'No se guardó el MP3 procesado.');
        $this->assertTrue(Storage::disk('public')->exists((string) $episode->archivo_mp3), 'La copia local no se preservó para descarga.');

        $downloadResponse = $this->actingAs($admin)->get(route('admin.podcast-uploads.download', $episode));
        $downloadResponse->assertOk();
    }

    public function test_podcast_uploads_index_groups_programs_by_day_and_opens_current_day(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 19, 10, 0, 0, 'America/Caracas'));

        try {
            $admin = User::factory()->create([
                'is_admin' => true,
            ]);

            MasterProgram::query()->create([
                'nombre' => 'Lunes Show',
                'conductor' => 'DJ Monday',
                'dia_transmision' => 'LUNES',
                'hora_transmision' => '08:00:00',
                'genero' => 'Rock',
                'timezone' => 'America/Caracas',
                'activo' => true,
            ]);

            MasterProgram::query()->create([
                'nombre' => 'Martes Show',
                'conductor' => 'DJ Tuesday',
                'dia_transmision' => 'MARTES',
                'hora_transmision' => '09:00:00',
                'genero' => 'Rock',
                'timezone' => 'America/Caracas',
                'activo' => true,
            ]);

            $response = $this->actingAs($admin)->get(route('admin.podcast-uploads.index'));

            $response->assertOk();
            $response->assertSee("x-data=\"podcastUploadForm({ initialDay: 'MARTES' })\"", false);
            $response->assertSee('data-day-panel="MARTES"', false);
            $response->assertSee('Martes Show');
            $response->assertSee('Lunes Show');
        } finally {
            Carbon::setTestNow();
        }
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
}
