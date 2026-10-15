<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ArchiveOrgPodcastServiceContract;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Models\User;
use App\Services\RadioBossService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PodcastChunkUploadTest extends TestCase
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
        Storage::fake('public');
    }

    public function test_it_can_upload_chunks_and_complete_them(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $uploadId = 'test_upload_' . uniqid();
        $chunk1 = UploadedFile::fake()->create('part1.mp3', 100, 'audio/mpeg');
        $chunk2 = UploadedFile::fake()->create('part2.mp3', 100, 'audio/mpeg');

        // Chunk 0
        $response0 = $this->actingAs($admin)->postJson(route('admin.podcast-uploads.chunk'), [
            'upload_id' => $uploadId,
            'chunk_index' => 0,
            'total_chunks' => 2,
            'file' => $chunk1,
        ]);
        $response0->assertStatus(200);
        $response0->assertJson(['status' => 'chunk_uploaded', 'chunk_index' => 0]);

        // Chunk 1
        $response1 = $this->actingAs($admin)->postJson(route('admin.podcast-uploads.chunk'), [
            'upload_id' => $uploadId,
            'chunk_index' => 1,
            'total_chunks' => 2,
            'file' => $chunk2,
        ]);
        $response1->assertStatus(200);
        $response1->assertJson(['status' => 'chunk_uploaded', 'chunk_index' => 1]);

        // Complete
        $completeResponse = $this->actingAs($admin)->postJson(route('admin.podcast-uploads.chunk.complete'), [
            'upload_id' => $uploadId,
            'total_chunks' => 2,
            'filename' => 'large-show.mp3',
        ]);
        $completeResponse->assertStatus(200);
        $completeResponse->assertJsonStructure(['status', 'chunked_key']);

        $chunkedKey = $completeResponse->json('chunked_key');
        $this->assertTrue(Storage::disk('public')->exists($chunkedKey));
    }

    public function test_it_can_store_episode_using_chunked_key(): void
    {
        Mail::fake();
        $this->fakeRadioBossService();
        $this->fakeArchiveOrgService();

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $master = MasterProgram::factory()->create([
            'nombre' => 'Rock Legends',
            'conductor' => 'Host Name',
            'dia_transmision' => 'MARTES',
            'hora_transmision' => '18:00:00',
            'genero' => 'Classic Rock',
            'ruta_ftp' => 'Programas',
            'email_notificacion' => 'rock@example.test',
            'activo' => true,
        ]);

        // Put a fake file on public disk as if it was created by chunk complete
        $chunkedKey = 'podcast-inbox/2026-10-15-rock-legends-fake.mp3';
        Storage::disk('public')->put($chunkedKey, 'fake-mp3-audio-content');

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.podcast-uploads.store'), [
                'master_program_id' => $master->id,
                'numero_episodio' => 10,
                'live_title' => 'Especial Chunked',
                'fecha_emision' => '2026-10-15',
                'chunked_key' => $chunkedKey,
                'pipeline_action' => 'save',
            ]);

        $response->assertStatus(302);

        $episode = RadioProgram::query()->latest('id')->first();
        $this->assertNotNull($episode);
        $this->assertSame('Especial Chunked', $episode->live_title);
        $this->assertSame($chunkedKey, $episode->archivo_mp3);
        $this->assertSame('public', $episode->archivo_mp3_disk);
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
            }

            public function exists(string $remotePath): bool
            {
                return true;
            }

            public function read(string $remotePath): ?string
            {
                return null;
            }

            public function files(string $folder): array
            {
                return [];
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
}
