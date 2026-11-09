<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\ExpireTalentsCommand;
use App\Console\Commands\SendExpiryWarningsCommand;
use App\Mail\TalentExpiryWarningMail;
use App\Mail\TalentHiddenNotificationMail;
use App\Models\Talent;
use App\Models\TalentMedia;
use App\Models\TalentSubscription;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MuroDelRockIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'filesystems.disks.r2_private.key' => 'test-key',
            'filesystems.disks.r2_private.secret' => 'test-secret',
            'filesystems.disks.r2_private.endpoint' => 'https://test.r2.cloudflarestorage.com',
            'filesystems.disks.r2_private.bucket' => 'test-private-bucket',
        ]);
        Storage::fake('r2');
        Storage::fake('r2_private');
        Storage::fake('public');
    }

    public function test_public_redirect_from_talentos_to_muro_del_rock_preserves_query_string(): void
    {
        $response = $this->get('/talentos?search=rock&plan=free');
        $response->assertStatus(301);
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/muro-del-rock', $location);
        $this->assertStringContainsString('search=rock', $location);
        $this->assertStringContainsString('plan=free', $location);
    }

    public function test_muro_del_rock_only_lists_active_and_visible_talents(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Banda Activa
        $activeBand = Talent::query()->create([
            'user_id' => $user1->id,
            'band_name' => 'Active Rock Band',
            'email' => 'active@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        // Banda Oculta / Vencida
        $expiredBand = Talent::query()->create([
            'user_id' => $user2->id,
            'band_name' => 'Expired Hidden Band',
            'email' => 'expired@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'expired',
            'is_hidden' => true,
        ]);

        $response = $this->get(route('talents.explore'));
        $response->assertStatus(200);
        $response->assertSee('Active Rock Band');
        $response->assertDontSee('Expired Hidden Band');
    }

    public function test_strict_mime_type_validation_rejects_invalid_files(): void
    {
        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'TestBand',
            'email' => 'testband@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        // Archivo explícitamente con mime text/plain y extensión mp3
        $fakeTextAsMp3 = UploadedFile::fake()->create('fake.mp3', 10, 'text/plain');

        $response = $this->actingAs($talent, 'talent')
            ->from(route('talents.media.index'))
            ->post(route('talents.media.upload'), [
                'type' => 'mp3',
                'title' => 'Fake Song',
                'file' => $fakeTextAsMp3,
            ]);

        $response->assertSessionHasErrors('file');
        $this->assertEquals(0, TalentMedia::query()->where('talent_id', $talent->id)->count());
    }

    public function test_band_isolation_prevents_unauthorized_deletion_and_download(): void
    {
        $bandA = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'Band A',
            'email' => 'banda@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        $bandB = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'Band B',
            'email' => 'bandb@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        // Crear medio perteneciente a Banda A
        $mediaA = TalentMedia::query()->create([
            'talent_id' => $bandA->id,
            'type' => 'document',
            'filename' => 'dossier_banda_a.pdf',
            'disk' => 'r2_private',
            'backblaze_key' => 'talents/1/media/document/dossier.pdf',
            'url' => 'https://media.sevenrockradio.com/talents/1/media/document/dossier.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'status' => 'approved',
        ]);

        // Crear archivo simulado en storage privado
        Storage::disk('r2_private')->put($mediaA->backblaze_key, '%PDF-1.4 simulated pdf');

        // Intento de Banda B de descargar documento privado de Banda A -> 403 Forbidden
        $responseDownload = $this->actingAs($bandB, 'talent')
            ->get(route('talents.media.download', ['id' => $mediaA->id]));
        $responseDownload->assertStatus(403);

        // Intento de Banda B de borrar medio de Banda A -> 403 Forbidden
        $responseDelete = $this->actingAs($bandB, 'talent')
            ->delete(route('talents.media.destroy', ['id' => $mediaA->id]));
        $responseDelete->assertStatus(403);
        $this->assertDatabaseHas('talent_media', ['id' => $mediaA->id]);

        // Banda A (dueña) sí puede descargar su documento
        $responseOwnerDownload = $this->actingAs($bandA, 'talent')
            ->get(route('talents.media.download', ['id' => $mediaA->id]));
        $responseOwnerDownload->assertStatus(200);
    }

    public function test_expiry_and_renewal_lifecycle_restores_visibility_without_duplication(): void
    {
        Mail::fake();

        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'Renewal Band',
            'email' => 'renewal@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        $sub = $talent->subscriptions()->create([
            'plan' => 'free',
            'amount' => 0,
            'currency' => 'EUR',
            'payment_provider' => 'manual',
            'start_date' => today()->subDays(45),
            'end_date' => today(), // Vence hoy
            'status' => 'active',
        ]);

        // 1. Ejecutar comando de expiración
        $this->artisan('talents:expire')->assertExitCode(0);

        $talent->refresh();
        $this->assertTrue((bool) $talent->is_hidden);
        $this->assertEquals('expired', $talent->subscription_status);
        $this->assertNotNull($talent->expires_grace_at);

        Mail::assertSent(TalentHiddenNotificationMail::class, function ($mail) use ($talent) {
            return $mail->hasTo($talent->email);
        });

        // Verificar que ya no aparece en el listado público
        $this->assertFalse(Talent::publiclyVisible()->where('id', $talent->id)->exists());

        // 2. La banda renueva su plan gratuito desde el portal
        $responseRenewal = $this->actingAs($talent, 'talent')
            ->post(route('talents.subscriptions.checkout'), [
                'plan' => 'free',
                'gateway' => 'stripe', // Ignorado para free
            ]);

        $responseRenewal->assertRedirect(route('talents.dashboard'));

        $talent->refresh();
        $this->assertFalse((bool) $talent->is_hidden);
        $this->assertEquals('active', $talent->subscription_status);
        $this->assertNull($talent->expires_grace_at);

        // La fecha de vencimiento se extendió 45 días
        $latestSub = $talent->subscriptions()->latest('id')->first();
        $this->assertEquals('active', $latestSub->status);
        $this->assertTrue($latestSub->end_date->isFuture());

        // Vuelve a ser visible de inmediato en el Muro del Rock
        $this->assertTrue(Talent::publiclyVisible()->where('id', $talent->id)->exists());
    }

    public function test_send_expiry_warnings_notifies_bands_seven_days_before_expiration(): void
    {
        Mail::fake();

        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'Warning Band',
            'email' => 'warning@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        $talent->subscriptions()->create([
            'plan' => 'free',
            'amount' => 0,
            'currency' => 'EUR',
            'payment_provider' => 'manual',
            'start_date' => today()->subDays(38),
            'end_date' => today()->addDays(7), // Exactamente en 7 días
            'status' => 'active',
        ]);

        $this->artisan('talents:send-expiry-warnings --days=7')->assertExitCode(0);

        Mail::assertSent(TalentExpiryWarningMail::class, function ($mail) use ($talent) {
            return $mail->hasTo($talent->email);
        });
    }

    public function test_cloud_upload_failure_returns_clear_error_and_does_not_persist_local_file(): void
    {
        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'FailBand',
            'email' => 'failband@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        // Simular que FileUploadService lanza excepción por fallo en R2
        $mockUploadService = $this->createMock(FileUploadService::class);
        $mockUploadService->method('isR2Configured')->willReturn(true);
        $mockUploadService->method('isR2PrivateConfigured')->willReturn(true);
        $mockUploadService->method('upload')->willThrowException(new \RuntimeException('Connection timeout to Cloudflare R2'));
        $this->app->instance(FileUploadService::class, $mockUploadService);

        $validPhoto = UploadedFile::fake()->image('test_cover.jpg', 600, 600);

        $response = $this->actingAs($talent, 'talent')
            ->from(route('talents.media.index'))
            ->post(route('talents.media.upload'), [
                'type' => 'photo',
                'title' => 'Cover Photo',
                'file' => $validPhoto,
            ]);

        $response->assertSessionHasErrors('file');
        $errorMsg = session('errors')->first('file');
        $this->assertStringContainsString('almacenamiento en la nube', $errorMsg);

        // Verificar que no se creó registro ni se guardó copia
        $this->assertEquals(0, TalentMedia::query()->where('talent_id', $talent->id)->count());
        $this->assertEmpty(Storage::disk('public')->allFiles('talents'));
    }

    public function test_document_uploads_use_r2_private_disk_and_restricted_download_route(): void
    {
        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'ProBand',
            'email' => 'proband@example.com',
            'password' => 'password123',
            'plan' => 'pro', // Pro plan allows document uploads
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        $pdfFile = UploadedFile::fake()->create('press_kit.pdf', 500, 'application/pdf');

        $response = $this->actingAs($talent, 'talent')
            ->post(route('talents.media.upload'), [
                'type' => 'document',
                'title' => 'Press Kit Privado',
                'file' => $pdfFile,
            ]);

        $response->assertRedirect(route('talents.media.index'));

        $media = TalentMedia::query()->where('talent_id', $talent->id)->where('type', 'document')->first();
        $this->assertNotNull($media);

        // Debe guardarse en el disco r2_private
        $this->assertTrue(Storage::disk('r2_private')->exists($media->backblaze_key));
        $this->assertEquals('r2_private', $media->resolvedDisk());

        // La URL pública debe apuntar a la ruta interna de descarga protegida, no al CDN abierto
        $this->assertEquals(route('talents.media.download', ['id' => $media->id]), $media->url);
    }

    public function test_missing_private_configuration_fails_without_uploading(): void
    {
        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'ProBandNoPrivate',
            'email' => 'probandnoprivate@example.com',
            'password' => 'password123',
            'plan' => 'pro',
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        // Configurar R2_PRIVATE_BUCKET como ausente
        config(['filesystems.disks.r2_private.bucket' => '']);

        $pdfFile = UploadedFile::fake()->create('contract.pdf', 500, 'application/pdf');

        $response = $this->actingAs($talent, 'talent')
            ->from(route('talents.media.index'))
            ->post(route('talents.media.upload'), [
                'type' => 'document',
                'title' => 'Contrato Confidencial',
                'file' => $pdfFile,
            ]);

        $response->assertSessionHasErrors('file');
        $errorMsg = session('errors')->first('file');
        $this->assertStringContainsString('R2_PRIVATE_BUCKET ausente', $errorMsg);

        // Comprobar que no se subió a ningún disco ni se creó registro
        $this->assertEquals(0, TalentMedia::query()->where('talent_id', $talent->id)->count());
        $this->assertEmpty(Storage::disk('public')->allFiles('talents'));
        $this->assertEmpty(Storage::disk('r2')->allFiles('talents'));
    }

    public function test_storage_returning_false_on_delete_preserves_database_records(): void
    {
        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'DeleteFailBand',
            'email' => 'deletefail@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        $media = TalentMedia::query()->create([
            'talent_id' => $talent->id,
            'type' => 'photo',
            'filename' => 'photo_to_delete.jpg',
            'disk' => 'r2',
            'backblaze_key' => 'talents/test/photo_to_delete.jpg',
            'url' => 'https://media.sevenrockradio.com/talents/test/photo_to_delete.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'status' => 'approved',
        ]);

        Storage::disk('r2')->put($media->backblaze_key, 'image bytes');

        // Simular que el disco r2 retorna false al intentar delete
        $mockDisk = $this->createMock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $mockDisk->method('exists')->willReturn(true);
        $mockDisk->method('delete')->willReturn(false);
        Storage::set('r2', $mockDisk);

        $response = $this->actingAs($talent, 'talent')
            ->from(route('talents.media.index'))
            ->delete(route('talents.media.destroy', ['id' => $media->id]));

        $response->assertSessionHasErrors('file');
        $this->assertStringContainsString('No se pudo eliminar el archivo físico', session('errors')->first('file'));

        // El registro en BD DEBE conservarse intacto
        $this->assertDatabaseHas('talent_media', ['id' => $media->id]);
    }

    public function test_network_or_permission_error_on_delete_preserves_database_records(): void
    {
        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'NetworkErrorBand',
            'email' => 'neterror@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        $media = TalentMedia::query()->create([
            'talent_id' => $talent->id,
            'type' => 'mp3',
            'filename' => 'song_error.mp3',
            'disk' => 'r2',
            'backblaze_key' => 'talents/test/song_error.mp3',
            'url' => 'https://media.sevenrockradio.com/talents/test/song_error.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => 2048,
            'status' => 'approved',
        ]);

        // Simular excepción de red / permisos en el disco r2
        $mockDisk = $this->createMock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $mockDisk->method('exists')->willReturn(true);
        $mockDisk->method('delete')->willThrowException(new \RuntimeException('Access Denied 403 S3 error'));
        Storage::set('r2', $mockDisk);

        $response = $this->actingAs($talent, 'talent')
            ->from(route('talents.media.index'))
            ->delete(route('talents.media.destroy', ['id' => $media->id]));

        $response->assertSessionHasErrors('file');
        $this->assertStringContainsString('Error de conexión', session('errors')->first('file'));

        // El registro en BD se conserva intacto
        $this->assertDatabaseHas('talent_media', ['id' => $media->id]);
    }

    public function test_same_key_on_different_disks_only_deletes_from_registered_disk(): void
    {
        $talentA = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'Band Disk A',
            'email' => 'banddiska@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        $talentB = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'Band Disk B',
            'email' => 'banddiskb@example.com',
            'password' => 'password123',
            'plan' => 'pro',
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        $sharedKey = 'talents/common/doc.pdf';

        // Archivo en r2
        $mediaA = TalentMedia::query()->create([
            'talent_id' => $talentA->id,
            'type' => 'photo',
            'filename' => 'file_a.jpg',
            'disk' => 'r2',
            'backblaze_key' => $sharedKey,
            'url' => 'https://media.sevenrockradio.com/' . $sharedKey,
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'status' => 'approved',
        ]);

        // Archivo con la MISMA clave pero en r2_private
        $mediaB = TalentMedia::query()->create([
            'talent_id' => $talentB->id,
            'type' => 'document',
            'filename' => 'file_b.pdf',
            'disk' => 'r2_private',
            'backblaze_key' => $sharedKey,
            'url' => route('talents.media.download', ['id' => 9999]),
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'status' => 'approved',
        ]);

        Storage::disk('r2')->put($sharedKey, 'r2 content');
        Storage::disk('r2_private')->put($sharedKey, 'r2_private content');

        // Eliminar Media A (debe borrar de r2 pero DEJAR INTACTO r2_private)
        $this->actingAs($talentA, 'talent')
            ->delete(route('talents.media.destroy', ['id' => $mediaA->id]))
            ->assertRedirect(route('talents.media.index'));

        $this->assertFalse(Storage::disk('r2')->exists($sharedKey));
        $this->assertTrue(Storage::disk('r2_private')->exists($sharedKey));
        $this->assertEquals('r2_private content', Storage::disk('r2_private')->get($sharedKey));
    }

    public function test_expire_talents_command_handles_partial_purge_failures_without_losing_references(): void
    {
        Mail::fake();

        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'PartialPurgeBand',
            'email' => 'partialpurge@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'expired',
            'is_hidden' => true,
            'expires_grace_at' => now()->subDay(), // Gracia ya vencida
        ]);

        // Media 1: exitoso
        $media1 = TalentMedia::query()->create([
            'talent_id' => $talent->id,
            'type' => 'mp3',
            'filename' => 'success.mp3',
            'disk' => 'r2',
            'backblaze_key' => 'talents/partial/success.mp3',
            'url' => 'https://media.sevenrockradio.com/talents/partial/success.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => 1024,
            'status' => 'approved',
        ]);
        Storage::disk('r2')->put($media1->backblaze_key, 'mp3 bytes');

        // Media 2: falla al borrar en almacenamiento
        $media2 = TalentMedia::query()->create([
            'talent_id' => $talent->id,
            'type' => 'photo',
            'filename' => 'fail.jpg',
            'disk' => 'r2',
            'backblaze_key' => 'talents/partial/fail.jpg',
            'url' => 'https://media.sevenrockradio.com/talents/partial/fail.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'status' => 'approved',
        ]);
        Storage::disk('r2')->put($media2->backblaze_key, 'photo bytes');

        // Simular que el delete de fail.jpg retorna false
        $realR2 = Storage::disk('r2');
        $partialMock = $this->createMock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $partialMock->method('exists')->willReturn(true);
        $partialMock->method('delete')->willReturnCallback(function ($path) use ($media2, $realR2) {
            if ($path === $media2->backblaze_key) {
                return false;
            }
            return $realR2->delete($path);
        });
        Storage::set('r2', $partialMock);

        // Ejecutar ExpireTalentsCommand
        $this->artisan('talents:expire')->assertExitCode(0);

        // Media 1 sí fue purgado de BD
        $this->assertDatabaseMissing('talent_media', ['id' => $media1->id]);

        // Media 2 NO fue purgado de BD para poder reintentar
        $this->assertDatabaseHas('talent_media', ['id' => $media2->id]);

        // La purga NO debe haberse marcado como completada: expires_grace_at no es null
        $talent->refresh();
        $this->assertNotNull($talent->expires_grace_at);

        // NO se debe haber enviado el correo de material purgado
        Mail::assertNotSent(\App\Mail\TalentMaterialDeletedMail::class);
    }

    public function test_submitting_media_without_file_fails_with_specific_message_and_creates_no_records(): void
    {
        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'EmptySubmitBand',
            'email' => 'emptysubmit@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        // Petición HTML estándar
        $responseHtml = $this->actingAs($talent, 'talent')
            ->from(route('talents.media.index'))
            ->post(route('talents.media.upload'), [
                'type' => 'photo',
                'title' => 'Foto sin archivo',
                'description' => 'Descripción prueba',
            ]);

        $responseHtml->assertSessionHasErrors(['file' => 'Selecciona un archivo antes de subir.']);
        $this->assertEquals(0, TalentMedia::query()->where('talent_id', $talent->id)->count());
        $this->assertEmpty(Storage::disk('r2')->allFiles('talents'));

        // Petición AJAX / JSON
        $responseJson = $this->actingAs($talent, 'talent')
            ->json('POST', route('talents.media.upload'), [
                'type' => 'photo',
                'title' => 'Foto sin archivo JSON',
            ]);

        $responseJson->assertStatus(422);
        $responseJson->assertJsonValidationErrors(['file']);
        $this->assertStringContainsString('Selecciona un archivo antes de subir', $responseJson->json('errors.file.0'));
        $this->assertEquals(0, TalentMedia::query()->where('talent_id', $talent->id)->count());
    }

    public function test_submitting_valid_media_updates_quota_and_distinguishes_moderation_status(): void
    {
        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'QuotaTestBand',
            'email' => 'quotatest@example.com',
            'password' => 'password123',
            'plan' => 'free', // Límites: 1 foto, 3 canciones, 50MB
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        $initialPhotos = $talent->media()->where('type', 'photo')->count();
        $this->assertEquals(0, $initialPhotos);

        $validPhoto = UploadedFile::fake()->image('my_cover.jpg', 800, 800);

        // Envío AJAX con visual progress
        $response = $this->actingAs($talent, 'talent')
            ->json('POST', route('talents.media.upload'), [
                'type' => 'photo',
                'title' => 'Portada Banda',
                'file' => $validPhoto,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $statusLabel = $response->json('status_label');
        $this->assertTrue(in_array($statusLabel, ['Publicado', 'Pendiente de revisión'], true));

        // Verificar cuota actualizada
        $talent->refresh();
        $updatedPhotos = $talent->media()->where('type', 'photo')->count();
        $this->assertEquals(1, $updatedPhotos);

        // Intentar subir una 2da foto (excede límite de 1 foto en plan free)
        $secondPhoto = UploadedFile::fake()->image('second_photo.jpg', 800, 800);
        $quotaFailResponse = $this->actingAs($talent, 'talent')
            ->json('POST', route('talents.media.upload'), [
                'type' => 'photo',
                'title' => 'Segunda Foto',
                'file' => $secondPhoto,
            ]);

        $quotaFailResponse->assertStatus(422);
        $this->assertStringContainsString('Tu plan ya alcanzó el límite', $quotaFailResponse->json('message'));

        // La cuota no aumentó
        $this->assertEquals(1, $talent->media()->where('type', 'photo')->count());
    }

    public function test_media_specs_and_early_validation_rules_are_coherent_and_exposed_to_view(): void
    {
        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'SpecsTestBand',
            'email' => 'specs@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        $specs = \App\Http\Controllers\Talent\MediaController::getMediaTypeSpecs($talent);

        $this->assertArrayHasKey('types', $specs);
        $this->assertArrayHasKey('storage', $specs);
        $this->assertEquals(12, $specs['types']['mp3']['max_mb']);
        $this->assertEquals(10, $specs['types']['photo']['max_mb']);
        $this->assertEquals(10, $specs['types']['document']['max_mb']);
        $this->assertEquals(100, $specs['types']['video']['max_mb']);
        $this->assertFalse($specs['types']['document']['included']);
        $this->assertFalse($specs['types']['video']['included']);
        $this->assertTrue($specs['types']['photo']['included']);
        $this->assertTrue($specs['types']['mp3']['included']);

        $response = $this->actingAs($talent, 'talent')->get(route('talents.media.index'));
        $response->assertStatus(200);
        $response->assertViewHas('typeSpecs');
        $response->assertViewHas('storageInfo');
        $response->assertSee('Resumen de Límites');
        $response->assertSee('(Máx. 12MB c/u)');
        $response->assertSee('(Máx. 10MB c/u)');
    }

    public function test_tampered_request_with_mismatched_file_type_is_rejected_by_server(): void
    {
        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'TamperBand',
            'email' => 'tamper@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        // Intentar subir un MP3 enviando type=photo
        $mpegFrame = "\xFF\xFB\x90\x64" . str_repeat("\x00", 413);
        $mp3Content = "ID3\x03\x00\x00\x00\x00\x00\x10" . str_repeat("\x00", 16) . str_repeat($mpegFrame, 10);
        $fakeMp3 = UploadedFile::fake()->createWithContent('song.mp3', $mp3Content);

        $response = $this->actingAs($talent, 'talent')
            ->json('POST', route('talents.media.upload'), [
                'type' => 'photo',
                'title' => 'Tampered Audio As Photo',
                'file' => $fakeMp3,
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('El archivo no tiene el formato permitido para photo', $response->json('message'));
        $this->assertEquals(0, TalentMedia::query()->where('talent_id', $talent->id)->count());
    }

    public function test_tampered_request_exceeding_max_file_size_is_rejected_by_server(): void
    {
        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'SizeTamperBand',
            'email' => 'sizetamper@example.com',
            'password' => 'password123',
            'plan' => 'free', // Límite MP3: 12MB
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        // Crear un archivo de 13 MB (supera 12 MB)
        $oversizedFile = UploadedFile::fake()->create('huge_track.mp3', 13 * 1024, 'audio/mpeg');

        $response = $this->actingAs($talent, 'talent')
            ->json('POST', route('talents.media.upload'), [
                'type' => 'mp3',
                'title' => 'Oversized MP3',
                'file' => $oversizedFile,
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('supera el tamaño máximo permitido', $response->json('message'));
        $this->assertEquals(0, TalentMedia::query()->where('talent_id', $talent->id)->count());
    }

    public function test_tampered_request_for_unsupported_plan_type_is_rejected_by_server(): void
    {
        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'TypeTamperBand',
            'email' => 'typetamper@example.com',
            'password' => 'password123',
            'plan' => 'free', // No incluye doc ni video
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        $pdfFile = UploadedFile::fake()->create('press_kit.pdf', 500, 'application/pdf');

        $response = $this->actingAs($talent, 'talent')
            ->json('POST', route('talents.media.upload'), [
                'type' => 'document',
                'title' => 'Documento en Plan Free',
                'file' => $pdfFile,
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('no está incluido en tu plan actual', $response->json('message'));
        $this->assertEquals(0, TalentMedia::query()->where('talent_id', $talent->id)->count());
    }

    public function test_full_four_stage_renewal_notice_lifecycle_with_controlled_clock(): void
    {
        Mail::fake();

        $startDate = \Illuminate\Support\Carbon::create(2026, 11, 1, 9, 0, 0);
        \Illuminate\Support\Carbon::setTestNow($startDate);

        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'Cycle Band',
            'email' => 'cycleband@test.com',
            'password' => bcrypt('Secret123!'),
            'plan' => 'free',
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        $subscription = TalentSubscription::query()->create([
            'talent_id' => $talent->id,
            'plan' => 'free',
            'amount' => 0,
            'currency' => 'EUR',
            'payment_provider' => 'manual',
            'status' => 'active',
            'start_date' => $startDate->toDateString(),
            'end_date' => $startDate->copy()->addDays(45)->toDateString(), // 2026-12-16
        ]);

        $media = TalentMedia::query()->create([
            'talent_id' => $talent->id,
            'type' => 'mp3',
            'filename' => 'original_track.mp3',
            'disk' => 'r2',
            'backblaze_key' => 'talents/' . $talent->id . '/media/mp3/track.mp3',
            'url' => 'https://media.sevenrockradio.com/talents/' . $talent->id . '/media/mp3/track.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => 5000000,
            'status' => 'approved',
        ]);
        Storage::disk('r2')->put($media->backblaze_key, 'audio contents');

        // --- ETAPA 1: 7 días antes del vencimiento (Día 38: 2026-12-09) ---
        \Illuminate\Support\Carbon::setTestNow($startDate->copy()->addDays(38));
        $this->artisan('talents:send-expiry-warnings')->assertExitCode(0);

        Mail::assertSent(TalentExpiryWarningMail::class, function ($mail) use ($talent) {
            return $mail->hasTo($talent->email) && $mail->daysLeft === 7 && $mail->stage === '7_days_before';
        });
        $this->assertDatabaseHas('talent_subscription_reminders', [
            'talent_id' => $talent->id,
            'stage' => '7_days_before',
        ]);
        $reminder1 = \App\Models\TalentSubscriptionReminder::where('talent_id', $talent->id)->where('stage', '7_days_before')->first();
        $this->assertStringStartsWith('2026-12-16', (string) $reminder1->cycle_end_date);

        // Reejecución no duplica
        Mail::fake();
        $this->artisan('talents:send-expiry-warnings')->assertExitCode(0);
        Mail::assertNothingSent();

        // --- ETAPA 2: 1 día antes del vencimiento (Día 44: 2026-12-15) ---
        \Illuminate\Support\Carbon::setTestNow($startDate->copy()->addDays(44));
        $this->artisan('talents:send-expiry-warnings')->assertExitCode(0);

        Mail::assertSent(TalentExpiryWarningMail::class, function ($mail) use ($talent) {
            return $mail->hasTo($talent->email) && $mail->daysLeft === 1 && $mail->stage === '1_day_before';
        });
        $this->assertDatabaseHas('talent_subscription_reminders', [
            'talent_id' => $talent->id,
            'stage' => '1_day_before',
        ]);
        $reminder2 = \App\Models\TalentSubscriptionReminder::where('talent_id', $talent->id)->where('stage', '1_day_before')->first();
        $this->assertStringStartsWith('2026-12-16', (string) $reminder2->cycle_end_date);

        // Reejecución no duplica
        Mail::fake();
        $this->artisan('talents:send-expiry-warnings')->assertExitCode(0);
        Mail::assertNothingSent();

        // --- ETAPA 3: Al vencer (Día 45: 2026-12-16) ---
        \Illuminate\Support\Carbon::setTestNow($startDate->copy()->addDays(45));
        $this->artisan('talents:expire')->assertExitCode(0);

        $talent->refresh();
        $this->assertTrue($talent->is_hidden);
        $this->assertEquals('expired', $talent->subscription_status);
        $this->assertNotNull($talent->expires_grace_at);

        Mail::assertSent(TalentHiddenNotificationMail::class, function ($mail) use ($talent) {
            return $mail->hasTo($talent->email);
        });
        $this->assertDatabaseHas('talent_subscription_reminders', [
            'talent_id' => $talent->id,
            'stage' => 'at_expiry',
        ]);
        $reminder3 = \App\Models\TalentSubscriptionReminder::where('talent_id', $talent->id)->where('stage', 'at_expiry')->first();
        $this->assertStringStartsWith('2026-12-16', (string) $reminder3->cycle_end_date);

        // En Muro del Rock ya no aparece en el listado público
        $exploreResponse = $this->get(route('talents.explore'));
        $exploreResponse->assertDontSee($talent->band_name);

        // Reejecución no duplica
        Mail::fake();
        $this->artisan('talents:expire')->assertExitCode(0);
        Mail::assertNothingSent();

        // --- ETAPA 4: 3 días antes de finalizar la gracia (Día 57: 2026-12-28) ---
        \Illuminate\Support\Carbon::setTestNow($startDate->copy()->addDays(57));
        $this->artisan('talents:send-expiry-warnings')->assertExitCode(0);

        Mail::assertSent(TalentExpiryWarningMail::class, function ($mail) use ($talent) {
            return $mail->hasTo($talent->email) && $mail->daysLeft === 3 && $mail->stage === '3_days_before_purge';
        });
        $this->assertDatabaseHas('talent_subscription_reminders', [
            'talent_id' => $talent->id,
            'stage' => '3_days_before_purge',
        ]);
        $reminder4 = \App\Models\TalentSubscriptionReminder::where('talent_id', $talent->id)->where('stage', '3_days_before_purge')->first();
        $this->assertStringStartsWith('2026-12-16', (string) $reminder4->cycle_end_date);

        // Reejecución no duplica
        Mail::fake();
        $this->artisan('talents:send-expiry-warnings')->assertExitCode(0);
        Mail::assertNothingSent();

        // --- RENOVACIÓN: La banda renueva su plan Free (Día 58: 2026-12-29) ---
        \Illuminate\Support\Carbon::setTestNow($startDate->copy()->addDays(58));
        $renewResponse = $this->actingAs($talent, 'talent')
            ->post(route('talents.subscriptions.checkout'), [
                'plan' => 'free',
                'gateway' => 'stripe',
            ]);
        $renewResponse->assertRedirect(route('talents.dashboard'));

        $talent->refresh();
        $this->assertFalse($talent->is_hidden);
        $this->assertEquals('active', $talent->subscription_status);
        $this->assertNull($talent->expires_grace_at);

        // Comprobar que reaparece de inmediato en el Muro del Rock
        $exploreAfterRenew = $this->get(route('talents.explore'));
        $exploreAfterRenew->assertSee($talent->band_name);

        // Comprobar que conserva todos sus medios sin pérdida
        $this->assertDatabaseHas('talent_media', [
            'id' => $media->id,
            'talent_id' => $talent->id,
            'deleted_at' => null,
        ]);
        $this->assertTrue(Storage::disk('r2')->exists($media->backblaze_key));

        \Illuminate\Support\Carbon::setTestNow(null);
    }

    public function test_media_without_demonstrated_disk_blocks_deletion_and_purge(): void
    {
        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'Legacy Band',
            'email' => 'legacy@example.com',
            'password' => bcrypt('password123'),
            'plan' => 'free',
            'subscription_status' => 'expired',
            'is_hidden' => true,
            'expires_grace_at' => now()->subDay(), // Gracia ya vencida
        ]);

        // Archivo histórico con disk = NULL (disco no demostrado)
        $ambiguousMedia = TalentMedia::query()->create([
            'talent_id' => $talent->id,
            'type' => 'mp3',
            'filename' => 'historical.mp3',
            'disk' => null, // Disco no demostrado
            'backblaze_key' => 'talents/' . $talent->id . '/historical.mp3',
            'url' => 'https://media.sevenrockradio.com/talents/' . $talent->id . '/historical.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => 123456,
            'status' => 'approved',
        ]);

        // 1. Intentar borrar mediante MediaController::destroy -> debe ser rechazado
        $response = $this->actingAs($talent, 'talent')
            ->delete(route('talents.media.destroy', ['id' => $ambiguousMedia->id]));

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseHas('talent_media', ['id' => $ambiguousMedia->id, 'deleted_at' => null]);

        // 2. Ejecutar ExpireTalentsCommand -> debe bloquear la purga y conservar el registro
        $this->artisan('talents:expire')->assertExitCode(0);

        $this->assertDatabaseHas('talent_media', ['id' => $ambiguousMedia->id, 'deleted_at' => null]);
        $talent->refresh();
        // La banda debe conservar expires_grace_at para permitir reintento manual (no se marca purga completada)
        $this->assertNotNull($talent->expires_grace_at);
    }

    public function test_login_redirect_preserves_intended_destination_to_renewal_plans(): void
    {
        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'Login Redirect Band',
            'email' => 'redirectband@example.com',
            'password' => bcrypt('Secret123!'),
            'plan' => 'free',
            'subscription_status' => 'expired',
            'is_hidden' => true,
        ]);

        $plansUrl = route('talents.subscriptions.plans');

        // Acceder a la página de login con el parámetro redirect_to
        $showLoginResponse = $this->get(route('talents.login', ['redirect_to' => $plansUrl]));
        $showLoginResponse->assertStatus(200);
        $showLoginResponse->assertSee('name="redirect_to"', false);

        // Enviar login con redirect_to
        $loginResponse = $this->post(route('talents.login.store'), [
            'email' => 'redirectband@example.com',
            'password' => 'Secret123!',
            'redirect_to' => $plansUrl,
        ]);

        $loginResponse->assertRedirect($plansUrl);
    }
}
