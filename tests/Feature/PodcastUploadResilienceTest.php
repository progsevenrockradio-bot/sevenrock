<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PodcastUploadResilienceTest extends TestCase
{
    private User $admin;
    private MasterProgram $master;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('r2');

        $this->admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->master = MasterProgram::create([
            'nombre' => 'Test Rock Program',
            'conductor' => 'Test Host',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '20:00:00',
            'duracion_minutos' => 60,
            'ruta_ftp' => '/test_rock',
            'activo' => true,
        ]);
    }

    public function test_presign_controller_accepts_common_mp3_mimes_and_normalizes_to_audio_mpeg(): void
    {
        $this->actingAs($this->admin);

        $mimes = ['audio/mpeg', 'audio/mp3', 'audio/x-mp3', 'audio/x-mpeg', 'audio/mpg'];

        foreach ($mimes as $mime) {
            $response = $this->postJson(route('admin.podcast-uploads.presign'), [
                'filename' => 'episodio_rock.mp3',
                'contentType' => $mime,
                'attempt_id' => 'test_attempt_123',
            ]);

            $response->assertOk();
            $data = $response->json();

            $this->assertArrayHasKey('url', $data);
            $this->assertArrayHasKey('headers', $data);
            $this->assertSame('audio/mpeg', $data['headers']['Content-Type'] ?? null);
            // Must NOT include Host header
            $this->assertArrayNotHasKey('Host', $data['headers']);
            $this->assertArrayNotHasKey('host', $data['headers']);
        }
    }

    public function test_presign_controller_sanitizes_forbidden_headers_returned_by_storage(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson(route('admin.podcast-uploads.presign'), [
            'filename' => 'test_sanitize.mp3',
            'contentType' => 'audio/mpeg',
        ]);

        $response->assertOk();
        $headers = $response->json('headers');

        $forbidden = ['host', 'connection', 'content-length', 'date', 'user-agent'];
        foreach ($forbidden as $forbiddenKey) {
            $this->assertArrayNotHasKey($forbiddenKey, $headers);
            $this->assertArrayNotHasKey(ucfirst($forbiddenKey), $headers);
        }
    }

    public function test_chunk_upload_and_assembly_workflow_with_safe_chunk_size(): void
    {
        $this->actingAs($this->admin);

        $uploadId = 'test_chk_' . Str::random(8);
        $totalChunks = 2;
        $chunk1Content = str_repeat('A', 2 * 1024 * 1024); // 2 MB
        $chunk2Content = str_repeat('B', 1 * 1024 * 1024); // 1 MB

        $file1 = UploadedFile::fake()->createWithContent('chunk_0.part', $chunk1Content);
        $res1 = $this->postJson(route('admin.podcast-uploads.chunk'), [
            'upload_id' => $uploadId,
            'chunk_index' => 0,
            'total_chunks' => $totalChunks,
            'file' => $file1,
        ]);
        $res1->assertOk()->assertJson(['status' => 'chunk_uploaded']);

        $file2 = UploadedFile::fake()->createWithContent('chunk_1.part', $chunk2Content);
        $res2 = $this->postJson(route('admin.podcast-uploads.chunk'), [
            'upload_id' => $uploadId,
            'chunk_index' => 1,
            'total_chunks' => $totalChunks,
            'file' => $file2,
        ]);
        $res2->assertOk()->assertJson(['status' => 'chunk_uploaded']);

        $completeRes = $this->postJson(route('admin.podcast-uploads.chunk.complete'), [
            'upload_id' => $uploadId,
            'total_chunks' => $totalChunks,
            'filename' => 'fanaticos_metal.mp3',
        ]);

        $completeRes->assertOk()->assertJsonStructure(['status', 'chunked_key']);
        $chunkedKey = $completeRes->json('chunked_key');

        $this->assertTrue(Storage::disk('public')->exists($chunkedKey));
        $assembledContent = Storage::disk('public')->get($chunkedKey);
        $this->assertSame($chunk1Content . $chunk2Content, $assembledContent);
    }

    public function test_duplicate_file_submission_is_blocked(): void
    {
        $this->actingAs($this->admin);

        $existingKey = 'podcast-inbox/already-uploaded-file.mp3';
        RadioProgram::create([
            'master_program_id' => $this->master->id,
            'titulo_programa' => 'Test Rock Program',
            'conductor' => 'Test Host',
            'numero_episodio' => 1,
            'fecha_emision' => '2026-11-23',
            'live_title' => 'Episodio 1',
            'archivo_mp3' => $existingKey,
            'archivo_mp3_disk' => 'r2',
            'duration_seconds' => 3600,
            'radioboss_status' => 'skipped',
            'archive_org_status' => 'skipped',
            'delivery_status' => 'delivered',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_NOT_REQUIRED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
        ]);

        $response = $this->from(route('admin.podcast-uploads.index'))->post(route('admin.podcast-uploads.store'), [
            'master_program_id' => $this->master->id,
            'live_title' => 'Intento Duplicado',
            'fecha_emision' => '2026-11-24',
            'r2_key' => $existingKey,
            'pipeline_action' => 'save',
        ]);

        $response->assertRedirect(route('admin.podcast-uploads.index'));
        $response->assertSessionHasErrors(['archivo_mp3']);
    }

    public function test_duplicate_episode_number_is_blocked(): void
    {
        $this->actingAs($this->admin);

        RadioProgram::create([
            'master_program_id' => $this->master->id,
            'titulo_programa' => 'Test Rock Program',
            'conductor' => 'Test Host',
            'numero_episodio' => 45,
            'fecha_emision' => '2026-11-20',
            'live_title' => 'Episodio 45',
            'archivo_mp3' => 'podcast-inbox/ep45.mp3',
            'archivo_mp3_disk' => 'r2',
            'duration_seconds' => 3600,
            'radioboss_status' => 'skipped',
            'archive_org_status' => 'skipped',
            'delivery_status' => 'delivered',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_NOT_REQUIRED,
            'editorial_version' => 1,
            'editorial_approved_version' => 1,
        ]);

        $response = $this->from(route('admin.podcast-uploads.index'))->post(route('admin.podcast-uploads.store'), [
            'master_program_id' => $this->master->id,
            'numero_episodio' => 45,
            'live_title' => 'Episodio 45 Duplicado',
            'fecha_emision' => '2026-11-24',
            'r2_key' => 'podcast-inbox/ep45-new.mp3',
            'pipeline_action' => 'save',
        ]);

        $response->assertRedirect(route('admin.podcast-uploads.index'));
        $response->assertSessionHasErrors(['numero_episodio']);
    }

    public function test_concurrent_upload_lock_blocks_simultaneous_requests(): void
    {
        $this->actingAs($this->admin);

        // Simulate an active lock
        $lock = Cache::lock("podcast_upload_master_{$this->master->id}", 60);
        $this->assertTrue($lock->get());

        try {
            $response = $this->from(route('admin.podcast-uploads.index'))->post(route('admin.podcast-uploads.store'), [
                'master_program_id' => $this->master->id,
                'live_title' => 'Episodio en Concurrencia',
                'fecha_emision' => '2026-11-24',
                'r2_key' => 'podcast-inbox/ep-concurrent.mp3',
                'pipeline_action' => 'save',
            ]);

            $response->assertRedirect(route('admin.podcast-uploads.index'));
            $response->assertSessionHasErrors(['archivo_mp3']);
        } finally {
            $lock->release();
        }
    }

    public function test_audit_trail_captures_uploaded_file_metadata_before_controller_moves_it(): void
    {
        $this->actingAs($this->admin);

        $uploadId = 'test_chk_audit_' . Str::random(8);
        $chunkContent = str_repeat('X', 512 * 1024); // 512 KB
        $chunkFile = UploadedFile::fake()->createWithContent('chunk_0.part', $chunkContent);

        $response = $this->postJson(route('admin.podcast-uploads.chunk'), [
            'upload_id' => $uploadId,
            'chunk_index' => 0,
            'total_chunks' => 1,
            'file' => $chunkFile,
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'chunk_uploaded']);

        $auditLog = \App\Models\AuditLog::query()
            ->where('route_name', 'admin.podcast-uploads.chunk')
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame(200, $auditLog->status_code);
        $this->assertArrayHasKey('files', $auditLog->request_meta);
        $this->assertSame('chunk_0.part', $auditLog->request_meta['files']['file']['name'] ?? null);
        $this->assertSame(strlen($chunkContent), $auditLog->request_meta['files']['file']['size'] ?? null);
    }
}
