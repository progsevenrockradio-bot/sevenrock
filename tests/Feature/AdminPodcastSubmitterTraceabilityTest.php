<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessMp3Job;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Models\RadioProgramEvent;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class AdminPodcastSubmitterTraceabilityTest extends TestCase
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

    public function test_admin_upload_records_authenticated_actor_on_save_and_process_and_ignores_forged_input(): void
    {
        Bus::fake();
        Mail::fake();
        Storage::fake('public');
        Storage::fake('r2');

        $adminUploader = User::factory()->create(['is_admin' => true, 'name' => 'Admin Subidor']);
        $otherUser = User::factory()->create(['is_admin' => true, 'name' => 'Usuario Suplantado']);

        $program = MasterProgram::factory()->create([
            'nombre' => 'Rock Nocturno',
            'conductor' => 'Conductor Base',
            'dia_transmision' => 'MARTES',
            'hora_transmision' => '21:00:00',
            'timezone' => 'America/Caracas',
            'activo' => true,
        ]);

        // 1. Guardar sin procesar (pipeline_action = save), intentando manipular submitted_by_user_id
        $saveResponse = $this->actingAs($adminUploader)->post(route('admin.podcast-uploads.store'), [
            'master_program_id' => $program->id,
            'numero_episodio' => 11,
            'live_title' => 'Episodio Guardado',
            'fecha_emision' => '2026-12-01',
            'pipeline_action' => 'save',
            'submitted_by_user_id' => $otherUser->id,
            'archivo_mp3' => UploadedFile::fake()->create('ep11.mp3', 256, 'audio/mpeg'),
        ]);

        $saveResponse->assertStatus(302);
        $savedEpisode = RadioProgram::query()->where('numero_episodio', 11)->firstOrFail();
        $this->assertSame($adminUploader->id, (int) $savedEpisode->submitted_by_user_id);
        $this->assertNotSame($otherUser->id, (int) $savedEpisode->submitted_by_user_id);

        // 2. Crear y procesar (pipeline_action = process), con Bus::fake() sin distribuir audio real
        $processResponse = $this->actingAs($adminUploader)->post(route('admin.podcast-uploads.store'), [
            'master_program_id' => $program->id,
            'numero_episodio' => 12,
            'live_title' => 'Episodio Para Procesar',
            'fecha_emision' => '2026-12-08',
            'pipeline_action' => 'process',
            'submitted_by_user_id' => $otherUser->id,
            'archivo_mp3' => UploadedFile::fake()->create('ep12.mp3', 256, 'audio/mpeg'),
        ]);

        $processResponse->assertStatus(302);
        $processedEpisode = RadioProgram::query()->where('numero_episodio', 12)->firstOrFail();
        $this->assertSame($adminUploader->id, (int) $processedEpisode->submitted_by_user_id);

        $uploadEvent = RadioProgramEvent::query()
            ->where('radio_program_id', $processedEpisode->id)
            ->where('event_type', 'UPLOAD_RECEIVED')
            ->latest('id')
            ->first();

        $this->assertNotNull($uploadEvent);
        $this->assertSame($adminUploader->id, (int) data_get($uploadEvent->metadata, 'submitted_by_user_id'));

        Bus::assertDispatched(ProcessMp3Job::class);
        Mail::assertNothingSent();
    }

    public function test_editing_and_replacing_audio_preserves_original_submitter_and_audits_editor(): void
    {
        Bus::fake();
        Mail::fake();
        Storage::fake('r2');

        $mockFileUpload = $this->createMock(FileUploadService::class);
        $mockFileUpload->method('isR2Configured')->willReturn(true);
        $this->app->instance(FileUploadService::class, $mockFileUpload);

        $originalUploader = User::factory()->create(['is_admin' => true, 'name' => 'Responsable Original']);
        $editingAdmin = User::factory()->create(['is_admin' => true, 'name' => 'Editor Posterior']);

        $program = MasterProgram::factory()->create([
            'nombre' => 'Zona Metal',
            'conductor' => 'Conductor X',
            'dia_transmision' => 'JUEVES',
            'hora_transmision' => '20:00:00',
            'timezone' => 'America/Caracas',
            'activo' => true,
        ]);

        $episode = RadioProgram::withoutEvents(static function () use ($program, $originalUploader): RadioProgram {
            return RadioProgram::query()->create([
                'master_program_id' => $program->id,
                'submitted_by_user_id' => $originalUploader->id,
                'titulo_programa' => $program->nombre,
                'conductor' => $program->conductor,
                'numero_episodio' => 50,
                'live_title' => 'Titulo Inicial',
                'fecha_emision' => '2026-12-03',
                'archivo_mp3' => 'podcast-inbox/zona-metal/original.mp3',
                'archivo_mp3_disk' => 'r2',
                'enviado_radioboss' => false,
                'radioboss_status' => 'skipped',
                'archive_org_status' => 'skipped',
                'delivery_status' => 'skipped',
                'editorial_status' => RadioProgram::EDITORIAL_STATUS_NOT_REQUIRED,
                'editorial_version' => 1,
            ]);
        });

        // Otro administrador edita y sustituye el audio (incluso enviando submitted_by_user_id manipulado)
        $response = $this->actingAs($editingAdmin)->put(route('admin.podcast-uploads.editorial.update', $episode), [
            'live_title' => 'Titulo Editado',
            'editorial_summary' => 'Resumen actualizado',
            'resena' => 'Reseña actualizada',
            'submitted_by_user_id' => $editingAdmin->id,
            'r2_key' => 'podcast-inbox/zona-metal/reemplazo-v2.mp3',
        ]);

        $response->assertRedirect(route('admin.podcast-uploads.editorial.show', $episode));

        $episode->refresh();
        $this->assertSame($originalUploader->id, (int) $episode->submitted_by_user_id);
        $this->assertSame('podcast-inbox/zona-metal/reemplazo-v2.mp3', $episode->archivo_mp3);

        $auditEvent = RadioProgramEvent::query()
            ->where('radio_program_id', $episode->id)
            ->where('event_type', 'ADMIN_EDITORIAL_UPDATED')
            ->latest('id')
            ->first();

        $this->assertNotNull($auditEvent);
        $this->assertSame($editingAdmin->id, (int) data_get($auditEvent->metadata, 'admin_id'));
        $this->assertSame('Editor Posterior', data_get($auditEvent->metadata, 'admin_name'));
        $this->assertTrue((bool) data_get($auditEvent->metadata, 'audio_replaced'));
        $this->assertSame($originalUploader->id, (int) data_get($auditEvent->metadata, 'original_submitted_by_user_id'));

        Mail::assertNothingSent();
    }
}
