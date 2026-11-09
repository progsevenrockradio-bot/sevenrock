<?php

declare(strict_types=1);

namespace Tests\Feature\Talent;

use App\Mail\ModerationNotification;
use App\Models\ModerationItem;
use App\Models\PendingStorageCleanup;
use App\Models\Talent;
use App\Models\TalentMedia;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\ModerationService;
use App\Services\PendingStorageCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TalentMediaUploadFailureAndCompensationTest extends TestCase
{
    use RefreshDatabase;

    private Talent $talent;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');
        Storage::fake('r2_private');

        config([
            'filesystems.disks.r2.key' => 'fake-key',
            'filesystems.disks.r2.secret' => 'fake-secret',
            'filesystems.disks.r2.bucket' => 'sevenrock-media-test',
            'filesystems.disks.r2.url' => 'https://pub-fake.r2.dev',
            'filesystems.disks.r2.endpoint' => 'https://fake.r2.cloudflarestorage.com',

            'filesystems.disks.r2_private.key' => 'fake-priv-key',
            'filesystems.disks.r2_private.secret' => 'fake-priv-secret',
            'filesystems.disks.r2_private.bucket' => 'sevenrock-media-private-test',
            'filesystems.disks.r2_private.url' => '',
            'filesystems.disks.r2_private.endpoint' => 'https://fake.r2.cloudflarestorage.com',
            'filesystems.disks.r2_private.visibility' => 'private',
        ]);

        ThemeSetting::clearCache();
        $settings = ThemeSetting::query()->firstOrCreate([], [
            'moderation_enabled' => true,
            'moderation_require_media' => true,
            'notification_email' => 'admin@sevenrockradio.com',
        ]);
        $settings->update([
            'moderation_enabled' => true,
            'moderation_require_media' => true,
            'notification_email' => 'admin@sevenrockradio.com',
        ]);
        ThemeSetting::clearCache();

        $this->admin = User::factory()->create([
            'is_admin' => true,
            'email' => 'admin@sevenrockradio.com',
        ]);

        $this->talent = Talent::factory()->create([
            'band_name' => 'Banda Blindada',
            'email' => 'banda@blindada.com',
            'subscription_status' => 'active',
            'is_hidden' => false,
            'email_verified_at' => now(),
            'terms_accepted_at' => now(),
            'terms_version' => 'v1_2026-11',
        ]);
    }

    /**
     * 1. Exactamente un elemento de moderación y un registro por subida válida.
     */
    public function test_upload_creates_exactly_one_media_record_and_one_moderation_item(): void
    {
        Mail::fake();

        $file = UploadedFile::fake()->create('cancion.mp3', 2048, 'audio/mpeg');

        $response = $this->actingAs($this->talent, 'talent')
            ->post(route('talents.media.upload'), [
                'type' => 'mp3',
                'title' => 'Single Oficial',
                'description' => 'Tema nuevo para moderación',
                'file' => $file,
            ]);

        $response->assertRedirect(route('talents.media.index'));

        // Exactamente un registro de TalentMedia
        $this->assertDatabaseCount('talent_media', 1);
        $media = TalentMedia::first();
        $this->assertNotNull($media);
        $this->assertEquals('pending', $media->status);
        $this->assertEquals('r2_private', $media->disk);

        // Exactamente un ModerationItem
        $this->assertDatabaseCount('moderation_items', 1);
        $modItem = ModerationItem::first();
        $this->assertNotNull($modItem);
        $this->assertEquals('media', $modItem->type);
        $this->assertEquals($media->id, $modItem->subject_id);

        // Exactamente una notificación lógica de moderación en cola
        Mail::assertQueued(ModerationNotification::class, 1);
    }

    /**
     * 2. Fallo al crear TalentMedia revierte BD y compensa almacenamiento eliminando el archivo.
     */
    public function test_failure_when_creating_talent_media_triggers_rollback_and_compensatory_delete(): void
    {
        TalentMedia::creating(function () {
            throw new \RuntimeException('Simulando fallo crítico en base de datos al crear TalentMedia.');
        });

        $file = UploadedFile::fake()->create('foto_falla.jpg', 500, 'image/jpeg');

        $response = $this->actingAs($this->talent, 'talent')
            ->post(route('talents.media.upload'), [
                'type' => 'photo',
                'title' => 'Foto de prueba',
                'file' => $file,
            ]);

        // Cero registros en BD
        $this->assertDatabaseCount('talent_media', 0);
        $this->assertDatabaseCount('moderation_items', 0);

        // Cero archivos en r2_private (compensación exitosa)
        $this->assertEmpty(Storage::disk('r2_private')->allFiles());
    }

    /**
     * 3. Fallo al actualizar la URL revierte BD y compensa almacenamiento.
     */
    public function test_failure_when_updating_url_triggers_rollback_and_compensatory_delete(): void
    {
        TalentMedia::updating(function () {
            throw new \RuntimeException('Simulando fallo en base de datos al actualizar la URL.');
        });

        $file = UploadedFile::fake()->create('audio_url_fail.mp3', 1000, 'audio/mpeg');

        $response = $this->actingAs($this->talent, 'talent')
            ->post(route('talents.media.upload'), [
                'type' => 'mp3',
                'title' => 'Audio URL Error',
                'file' => $file,
            ]);

        // La transacción completa revierte: no queda registro huérfano
        $this->assertDatabaseCount('talent_media', 0);
        $this->assertDatabaseCount('moderation_items', 0);

        // El archivo físico fue eliminado por la compensación
        $this->assertEmpty(Storage::disk('r2_private')->allFiles());
    }

    /**
     * 4. Fallo al registrar moderación (incluyendo retorno null) aborta la subida y compensa.
     */
    public function test_failure_when_moderation_registration_returns_null_aborts_upload_and_compensates(): void
    {
        // Mock de ModerationService para simular que registerIfRequired devuelve null
        $mock = $this->mock(ModerationService::class, function ($mock) {
            $mock->shouldReceive('needsModeration')->with('media')->andReturn(true);
            $mock->shouldReceive('registerIfRequired')->andReturn(null);
        });

        $file = UploadedFile::fake()->create('demo_mod_null.mp3', 1000, 'audio/mpeg');

        $response = $this->actingAs($this->talent, 'talent')
            ->post(route('talents.media.upload'), [
                'type' => 'mp3',
                'title' => 'Track Mod Null',
                'file' => $file,
            ]);

        // No se debe dar por completada la subida: BD limpia
        $this->assertDatabaseCount('talent_media', 0);
        $this->assertDatabaseCount('moderation_items', 0);

        // Archivo físico compensado
        $this->assertEmpty(Storage::disk('r2_private')->allFiles());
    }

    /**
     * 5. Fallo en el envío de notificación posterior al commit no corrompe el registro comprometido.
     */
    public function test_failure_in_post_commit_notification_does_not_corrupt_committed_media(): void
    {
        // Forzamos un error en Mail para que falle el envio post-commit
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Servidor SMTP no disponible.'));

        $file = UploadedFile::fake()->create('track_smtp_fail.mp3', 1500, 'audio/mpeg');

        $response = $this->actingAs($this->talent, 'talent')
            ->post(route('talents.media.upload'), [
                'type' => 'mp3',
                'title' => 'Track Resiliente a Mail',
                'file' => $file,
            ]);

        $response->assertRedirect(route('talents.media.index'));

        // El registro de base de datos se mantiene comprometido y seguro
        $this->assertDatabaseCount('talent_media', 1);
        $this->assertDatabaseCount('moderation_items', 1);

        // El archivo físico sigue intacto en almacenamiento
        $this->assertNotEmpty(Storage::disk('r2_private')->allFiles());
    }

    /**
     * 6. El borrado compensatorio lanza una excepción -> registra tarea en PendingStorageCleanup.
     */
    public function test_compensatory_storage_deletion_throwing_exception_persists_pending_cleanup(): void
    {
        // Simular fallo en BD para disparar compensación
        TalentMedia::creating(function () {
            throw new \RuntimeException('Error en BD');
        });

        // Mock FileUploadService para que delete() lance una excepción
        $uploadMock = $this->mock(FileUploadService::class, function ($mock) {
            $mock->shouldReceive('isR2PrivateConfigured')->andReturn(true);
            $mock->shouldReceive('upload')->andReturn([
                'key' => 'talents/1/media/audio/orphan_test.mp3',
                'url' => 'https://mock.storage/orphan_test.mp3',
            ]);
            $mock->shouldReceive('delete')->andThrow(new \RuntimeException('Fallo de red S3 durante compensación'));
        });

        $file = UploadedFile::fake()->create('orphan.mp3', 1000, 'audio/mpeg');

        $this->actingAs($this->talent, 'talent')
            ->post(route('talents.media.upload'), [
                'type' => 'mp3',
                'title' => 'Orphan Test',
                'file' => $file,
            ]);

        // Cero registros en talent_media
        $this->assertDatabaseCount('talent_media', 0);

        // Tarea registrada en pending_storage_cleanups
        $this->assertDatabaseCount('pending_storage_cleanups', 1);
        $task = PendingStorageCleanup::first();
        $this->assertNotNull($task);
        $this->assertEquals('pending', $task->status);
        $this->assertEquals('r2_private', $task->disk);
        $this->assertEquals('talents/1/media/audio/orphan_test.mp3', $task->storage_key);
        $this->assertStringContainsString('Fallo de red S3', (string) $task->last_error);
    }

    /**
     * 7. El borrado compensatorio devuelve false -> registra tarea en PendingStorageCleanup.
     */
    public function test_compensatory_storage_deletion_returning_false_persists_pending_cleanup(): void
    {
        TalentMedia::creating(function () {
            throw new \RuntimeException('Error en BD');
        });

        $uploadMock = $this->mock(FileUploadService::class, function ($mock) {
            $mock->shouldReceive('isR2PrivateConfigured')->andReturn(true);
            $mock->shouldReceive('upload')->andReturn([
                'key' => 'talents/1/media/audio/delete_false.mp3',
                'url' => 'https://mock.storage/delete_false.mp3',
            ]);
            $mock->shouldReceive('delete')->andReturn(false);
        });

        $file = UploadedFile::fake()->create('delete_false.mp3', 1000, 'audio/mpeg');

        $this->actingAs($this->talent, 'talent')
            ->post(route('talents.media.upload'), [
                'type' => 'mp3',
                'title' => 'Delete False Test',
                'file' => $file,
            ]);

        $this->assertDatabaseCount('talent_media', 0);
        $this->assertDatabaseCount('pending_storage_cleanups', 1);

        $task = PendingStorageCleanup::first();
        $this->assertNotNull($task);
        $this->assertEquals('pending', $task->status);
        $this->assertEquals('talents/1/media/audio/delete_false.mp3', $task->storage_key);
        $this->assertStringContainsString('retornó false', (string) $task->last_error);
    }

    /**
     * 8. Reintento seguro de la tarea de limpieza pendiente.
     */
    public function test_safe_retry_of_pending_cleanup_task(): void
    {
        $storageKey = 'talents/1/media/audio/pending_to_clean.mp3';
        Storage::disk('r2_private')->put($storageKey, 'contenido huérfano');
        $this->assertTrue(Storage::disk('r2_private')->exists($storageKey));

        $task = PendingStorageCleanup::create([
            'disk' => 'r2_private',
            'storage_key' => $storageKey,
            'operation_id' => 'op-12345',
            'reason' => 'compensation_after_db_failure',
            'status' => 'pending',
            'attempts' => 1,
            'last_error' => 'Error previo de red',
        ]);

        $cleanupService = app(PendingStorageCleanupService::class);
        $success = $cleanupService->retryTask($task);

        $this->assertTrue($success);
        $this->assertFalse(Storage::disk('r2_private')->exists($storageKey));

        $task->refresh();
        $this->assertEquals('completed', $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertNull($task->last_error);

        // Reintentar de nuevo no produce errores y sigue completado
        $secondRetry = $cleanupService->retryTask($task);
        $this->assertTrue($secondRetry);
    }

    /**
     * 9. Ausencia de registros en base de datos apuntando a objetos eliminados.
     */
    public function test_absence_of_db_records_pointing_to_deleted_objects(): void
    {
        TalentMedia::creating(function () {
            throw new \RuntimeException('Fallo al persistir');
        });

        $file = UploadedFile::fake()->create('no_dangling.mp3', 800, 'audio/mpeg');

        $this->actingAs($this->talent, 'talent')
            ->post(route('talents.media.upload'), [
                'type' => 'mp3',
                'title' => 'No Dangling',
                'file' => $file,
            ]);

        // Verificación estricta: cero registros en BD
        $this->assertEquals(0, TalentMedia::query()->count());
        $this->assertEquals(0, ModerationItem::query()->count());
    }

    /**
     * 10. Protección de objetos compartidos y archivos previos durante la compensación.
     */
    public function test_protection_of_shared_objects_during_compensation(): void
    {
        $sharedKey = 'talents/shared_audio.mp3';
        Storage::disk('r2_private')->put($sharedKey, 'audio compartido importante');

        // Registro pre-existente en base de datos que ya usa esa clave
        $existingMedia = TalentMedia::create([
            'talent_id' => $this->talent->id,
            'type' => 'mp3',
            'filename' => 'audio_existente.mp3',
            'disk' => 'r2_private',
            'backblaze_key' => $sharedKey,
            'url' => 'https://route/audio_existente',
            'status' => 'approved',
            'size' => 1024,
            'mime_type' => 'audio/mpeg',
        ]);

        // Nueva subida que por alguna razón devuelve la misma clave pero falla en BD
        TalentMedia::creating(function ($m) use ($existingMedia) {
            if ($m->id !== $existingMedia->id) {
                throw new \RuntimeException('Fallo al crear segundo registro');
            }
        });

        $uploadMock = $this->mock(FileUploadService::class, function ($mock) use ($sharedKey) {
            $mock->shouldReceive('isR2PrivateConfigured')->andReturn(true);
            $mock->shouldReceive('upload')->andReturn([
                'key' => $sharedKey,
                'url' => 'https://route/second_upload',
            ]);
            // delete() NO debe ser llamado porque el objeto está compartido con $existingMedia
            $mock->shouldNotReceive('delete');
        });

        $file = UploadedFile::fake()->create('segundo_intento.mp3', 1024, 'audio/mpeg');

        $this->actingAs($this->talent, 'talent')
            ->post(route('talents.media.upload'), [
                'type' => 'mp3',
                'title' => 'Segundo Intento',
                'file' => $file,
            ]);

        // El registro previo sigue existiendo intacto
        $this->assertDatabaseCount('talent_media', 1);
        $this->assertEquals($existingMedia->id, TalentMedia::first()->id);

        // El archivo físico NO fue eliminado de r2_private
        $this->assertTrue(Storage::disk('r2_private')->exists($sharedKey));
    }
}
