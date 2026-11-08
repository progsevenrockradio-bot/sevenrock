<?php

declare(strict_types=1);

namespace Tests\Feature\Talent;

use App\Mail\ContentApprovedMail;
use App\Models\Talent;
use App\Models\TalentMedia;
use App\Models\TrackSubmission;
use App\Models\User;
use App\Notifications\TalentVerifyEmailNotification;
use App\Services\FileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TalentMediaSecurityAndVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $regularWebUser;
    private Talent $ownerTalent;
    private Talent $otherTalent;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');
        Storage::fake('r2_private');

        $this->adminUser = User::factory()->create([
            'is_admin' => true,
            'email' => 'admin@sevenrockradio.com',
        ]);

        $this->regularWebUser = User::factory()->create([
            'is_admin' => false,
            'email' => 'fan@example.com',
        ]);

        $this->ownerTalent = Talent::factory()->create([
            'band_name' => 'Los Rockeros Propietarios',
            'email' => 'propietario@banda.com',
            'is_hidden' => false,
            'subscription_status' => 'active',
            'email_verified_at' => now(),
            'terms_accepted_at' => now(),
            'terms_version' => 'v1_2026-11',
        ]);

        $this->otherTalent = Talent::factory()->create([
            'band_name' => 'Otra Banda Ajena',
            'email' => 'otra@banda.com',
            'is_hidden' => false,
            'subscription_status' => 'active',
            'email_verified_at' => now(),
            'terms_accepted_at' => now(),
            'terms_version' => 'v1_2026-11',
        ]);
    }

    private function createMediaFile(
        Talent $talent,
        string $type = 'mp3',
        string $status = 'approved',
        bool $isExclusive = false,
        string $disk = 'r2'
    ): TalentMedia {
        $filename = "test_file_{$type}_{$status}." . ($type === 'document' ? 'pdf' : ($type === 'photo' ? 'jpg' : 'mp3'));
        $key = "talents/{$talent->id}/media/{$type}/{$filename}";

        Storage::disk($disk)->put($key, 'dummy-content-for-testing-seven-rock');

        return TalentMedia::create([
            'talent_id' => $talent->id,
            'type' => $type,
            'filename' => $filename,
            'disk' => $disk,
            'backblaze_key' => $key,
            'url' => "https://storage.test/{$key}",
            'mime_type' => $type === 'document' ? 'application/pdf' : ($type === 'photo' ? 'image/jpeg' : 'audio/mpeg'),
            'size' => 1024,
            'is_exclusive' => $isExclusive,
            'status' => $status,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Archivo pendiente o rechazado por acceso directo
    // ─────────────────────────────────────────────────────────────────────────

    public function test_pending_media_cannot_be_streamed_or_downloaded_by_guest_or_other_users(): void
    {
        $pendingMedia = $this->createMediaFile($this->ownerTalent, 'mp3', 'pending');

        // Invitado
        $this->get(route('talents.media.stream', ['id' => $pendingMedia->id]))
            ->assertStatus(403);
        $this->get(route('talents.media.download', ['id' => $pendingMedia->id]))
            ->assertStatus(403);

        // Usuario web autenticado (no propietario)
        $this->actingAs($this->regularWebUser, 'web')
            ->get(route('talents.media.stream', ['id' => $pendingMedia->id]))
            ->assertStatus(403);
        $this->actingAs($this->regularWebUser, 'web')
            ->get(route('talents.media.download', ['id' => $pendingMedia->id]))
            ->assertStatus(403);

        // Otra banda ajena
        $this->actingAs($this->otherTalent, 'talent')
            ->get(route('talents.media.stream', ['id' => $pendingMedia->id]))
            ->assertStatus(403);
    }

    public function test_rejected_media_cannot_be_accessed_by_guest_or_other_users(): void
    {
        $rejectedMedia = $this->createMediaFile($this->ownerTalent, 'mp3', 'rejected');

        $this->get(route('talents.media.stream', ['id' => $rejectedMedia->id]))
            ->assertStatus(403);

        $this->actingAs($this->regularWebUser, 'web')
            ->get(route('talents.media.stream', ['id' => $rejectedMedia->id]))
            ->assertStatus(403);

        $this->actingAs($this->otherTalent, 'talent')
            ->get(route('talents.media.stream', ['id' => $rejectedMedia->id]))
            ->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Archivo de banda oculta o inactiva
    // ─────────────────────────────────────────────────────────────────────────

    public function test_media_of_hidden_band_is_forbidden_for_public_and_web_users(): void
    {
        $hiddenTalent = Talent::factory()->create([
            'band_name' => 'Banda Oculta',
            'is_hidden' => true,
            'subscription_status' => 'active',
        ]);

        $approvedMedia = $this->createMediaFile($hiddenTalent, 'mp3', 'approved');

        // Visitante
        $this->get(route('talents.media.stream', ['id' => $approvedMedia->id]))
            ->assertStatus(403);

        // Usuario web
        $this->actingAs($this->regularWebUser, 'web')
            ->get(route('talents.media.stream', ['id' => $approvedMedia->id]))
            ->assertStatus(403);

        // Propietario sí puede revisarlo
        $this->actingAs($hiddenTalent, 'talent')
            ->get(route('talents.media.stream', ['id' => $approvedMedia->id]))
            ->assertStatus(200);

        // Administrador sí puede revisarlo
        $this->actingAs($this->adminUser, 'web')
            ->get(route('talents.media.stream', ['id' => $approvedMedia->id]))
            ->assertStatus(200);
    }

    public function test_media_of_inactive_band_is_forbidden_for_public_and_web_users(): void
    {
        $inactiveTalent = Talent::factory()->create([
            'band_name' => 'Banda Inactiva',
            'is_hidden' => false,
            'subscription_status' => 'inactive',
        ]);

        $approvedMedia = $this->createMediaFile($inactiveTalent, 'mp3', 'approved');

        $this->get(route('talents.media.stream', ['id' => $approvedMedia->id]))
            ->assertStatus(403);

        $this->actingAs($this->regularWebUser, 'web')
            ->get(route('talents.media.stream', ['id' => $approvedMedia->id]))
            ->assertStatus(403);

        $this->actingAs($inactiveTalent, 'talent')
            ->get(route('talents.media.stream', ['id' => $approvedMedia->id]))
            ->assertStatus(200);

        $this->actingAs($this->adminUser, 'web')
            ->get(route('talents.media.stream', ['id' => $approvedMedia->id]))
            ->assertStatus(200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Prioridad de la privacidad documental sobre is_exclusive
    // ─────────────────────────────────────────────────────────────────────────

    public function test_private_document_with_is_exclusive_is_strictly_forbidden_to_authenticated_web_users(): void
    {
        // Un PDF marcado con is_exclusive = true
        $docMedia = $this->createMediaFile($this->ownerTalent, 'document', 'approved', true, 'r2_private');

        // Usuario web autenticado (no admin, no propietario): DEBE ser 403
        $this->actingAs($this->regularWebUser, 'web')
            ->get(route('talents.media.download', ['id' => $docMedia->id]))
            ->assertStatus(403);

        $this->actingAs($this->regularWebUser, 'web')
            ->get(route('talents.media.stream', ['id' => $docMedia->id]))
            ->assertStatus(403);

        // Visitante
        $this->get(route('talents.media.download', ['id' => $docMedia->id]))
            ->assertStatus(403);

        // Otra banda
        $this->actingAs($this->otherTalent, 'talent')
            ->get(route('talents.media.download', ['id' => $docMedia->id]))
            ->assertStatus(403);

        // Propietario: SÍ puede acceder
        $this->actingAs($this->ownerTalent, 'talent')
            ->get(route('talents.media.download', ['id' => $docMedia->id]))
            ->assertStatus(200);

        // Administrador: SÍ puede acceder
        $this->actingAs($this->adminUser, 'web')
            ->get(route('talents.media.download', ['id' => $docMedia->id]))
            ->assertStatus(200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. Propietario y administrador revisando material pendiente
    // ─────────────────────────────────────────────────────────────────────────

    public function test_owner_and_admin_can_review_pending_material(): void
    {
        $pendingMedia = $this->createMediaFile($this->ownerTalent, 'mp3', 'pending');

        // Propietario
        $this->actingAs($this->ownerTalent, 'talent')
            ->get(route('talents.media.stream', ['id' => $pendingMedia->id]))
            ->assertStatus(200);

        // Administrador
        $this->actingAs($this->adminUser, 'web')
            ->get(route('talents.media.stream', ['id' => $pendingMedia->id]))
            ->assertStatus(200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. Circuito completo de verificación de correo electrónico
    // ─────────────────────────────────────────────────────────────────────────

    public function test_talent_registration_leaves_email_unverified_and_sends_notification(): void
    {
        Notification::fake();

        $response = $this->post(route('talents.register.store'), [
            'band_name' => 'Banda Nueva No Verificada',
            'email' => 'nueva@bandano-verificada.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'plan' => 'free',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('talents.dashboard'));

        $talent = Talent::where('email', 'nueva@bandano-verificada.com')->first();
        $this->assertNotNull($talent);
        $this->assertNull($talent->email_verified_at);
        $this->assertFalse($talent->hasVerifiedEmail());

        Notification::assertSentTo($talent, TalentVerifyEmailNotification::class);
    }

    public function test_unverified_talent_cannot_view_historical_submissions(): void
    {
        $unverifiedTalent = Talent::factory()->create([
            'email' => 'historico@banda.com',
            'email_verified_at' => null,
        ]);

        // Maqueta histórica previa
        TrackSubmission::create([
            'band_name' => 'Historicos',
            'song_title' => 'Canto Antiguo',
            'contact_email' => 'historico@banda.com',
            'file_path' => 'submissions/canto_antiguo.mp3',
            'status' => 'approved',
        ]);

        $this->assertFalse($unverifiedTalent->hasVerifiedEmail());
        $this->assertCount(0, $unverifiedTalent->submissions);

        // En el dashboard, la colección de maquetas históricas viene vacía
        $response = $this->actingAs($unverifiedTalent, 'talent')
            ->get(route('talents.dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('submissions', fn ($subs) => $subs->isEmpty());
    }

    public function test_valid_signed_verification_url_marks_email_verified_and_unlocks_submissions(): void
    {
        $unverifiedTalent = Talent::factory()->create([
            'email' => 'acreditar@banda.com',
            'email_verified_at' => null,
        ]);

        TrackSubmission::create([
            'band_name' => 'Acreditar Band',
            'song_title' => 'Tema Exclusivo',
            'contact_email' => 'acreditar@banda.com',
            'file_path' => 'submissions/tema_exclusivo.mp3',
            'status' => 'approved',
        ]);

        $validSignedUrl = URL::temporarySignedRoute(
            'talents.verification.verify',
            now()->addMinutes(60),
            [
                'id' => $unverifiedTalent->id,
                'hash' => sha1((string) $unverifiedTalent->getEmailForVerification()),
            ]
        );

        // Talento autenticado pulsa el enlace
        $response = $this->actingAs($unverifiedTalent, 'talent')->get($validSignedUrl);
        $response->assertRedirect(route('talents.dashboard'));
        $response->assertSessionHas('status');

        $unverifiedTalent->refresh();
        $this->assertTrue($unverifiedTalent->hasVerifiedEmail());
        $this->assertNotNull($unverifiedTalent->email_verified_at);

        // Ahora sí tiene acceso a sus maquetas históricas
        $this->assertCount(1, $unverifiedTalent->submissions);
    }

    public function test_expired_or_tampered_verification_link_fails(): void
    {
        $unverifiedTalent = Talent::factory()->create([
            'email' => 'caducada@banda.com',
            'email_verified_at' => null,
        ]);

        // Enlace expirado (fecha pasada)
        $expiredUrl = URL::temporarySignedRoute(
            'talents.verification.verify',
            now()->subMinutes(10),
            [
                'id' => $unverifiedTalent->id,
                'hash' => sha1((string) $unverifiedTalent->getEmailForVerification()),
            ]
        );

        $this->get($expiredUrl)->assertStatus(403);
        $this->assertNull($unverifiedTalent->fresh()->email_verified_at);

        // Enlace con hash manipulado
        $tamperedUrl = URL::temporarySignedRoute(
            'talents.verification.verify',
            now()->addMinutes(60),
            [
                'id' => $unverifiedTalent->id,
                'hash' => 'hash_invalido_manipulado',
            ]
        );

        $this->get($tamperedUrl)->assertStatus(403);
        $this->assertNull($unverifiedTalent->fresh()->email_verified_at);
    }

    public function test_resend_verification_email_is_rate_limited(): void
    {
        Notification::fake();

        $unverifiedTalent = Talent::factory()->create([
            'email' => 'reintento@banda.com',
            'email_verified_at' => null,
        ]);

        // Las primeras 6 peticiones dentro del límite pasan
        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($unverifiedTalent, 'talent')
                ->post(route('talents.verification.send'))
                ->assertRedirect();
        }

        // La 7ma petición debe ser bloqueada por el limitador (429 Too Many Requests)
        $this->actingAs($unverifiedTalent, 'talent')
            ->post(route('talents.verification.send'))
            ->assertStatus(429);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. Concurrencia y fallos de correo en approveMedia
    // ─────────────────────────────────────────────────────────────────────────

    public function test_approve_media_prevents_duplicate_emails_on_concurrency(): void
    {
        Mail::fake();

        $pendingMedia = $this->createMediaFile($this->ownerTalent, 'mp3', 'pending');

        // Primera aprobación
        $this->actingAs($this->adminUser, 'web')
            ->post(route('admin.talents.media.approve', $pendingMedia))
            ->assertRedirect()
            ->assertSessionHas('status', 'Archivo aprobado y publicado. Se ha notificado al artista por correo electrónico.');

        $this->assertEquals('approved', $pendingMedia->fresh()->status);
        Mail::assertSent(ContentApprovedMail::class, 1);

        // Segundo clic simultáneo / concurrente
        $this->actingAs($this->adminUser, 'web')
            ->post(route('admin.talents.media.approve', $pendingMedia))
            ->assertRedirect()
            ->assertSessionHas('status', 'El archivo ya fue aprobado previamente.');

        // No se envió correo duplicado
        Mail::assertSent(ContentApprovedMail::class, 1);
    }

    public function test_approve_media_does_not_claim_artist_notified_if_mail_fails(): void
    {
        $pendingMedia = $this->createMediaFile($this->ownerTalent, 'mp3', 'pending');

        // Simular fallo en Mail::to()->send()
        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new \RuntimeException('Servidor SMTP inaccesible'));

        $this->actingAs($this->adminUser, 'web')
            ->post(route('admin.talents.media.approve', $pendingMedia))
            ->assertRedirect()
            ->assertSessionHas('status_warning', fn ($msg) => str_contains($msg, 'falló el envío de la notificación por correo al artista'));

        // El medio quedó aprobado
        $this->assertEquals('approved', $pendingMedia->fresh()->status);
    }

    public function test_resend_media_approval_notice_allows_retry_after_failure(): void
    {
        Mail::fake();

        $approvedMedia = $this->createMediaFile($this->ownerTalent, 'mp3', 'approved');

        $this->actingAs($this->adminUser, 'web')
            ->post(route('admin.talents.media.resend-notice', $approvedMedia))
            ->assertRedirect()
            ->assertSessionHas('status', 'Notificación de aprobación enviada correctamente al artista.');

        Mail::assertSent(ContentApprovedMail::class, 1);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. Pruebas de protección de almacenamiento, ciclo y ausencia de huérfanos
    // ─────────────────────────────────────────────────────────────────────────

    public function test_pending_upload_without_private_storage_configured_is_rejected(): void
    {
        $this->mock(\App\Services\ModerationService::class, function ($mock) {
            $mock->shouldReceive('needsModeration')->with('media')->andReturn(true);
        });

        config(['filesystems.disks.r2_private.bucket' => '']);

        $songFile = UploadedFile::fake()->create('nueva_cancion.mp3', 2048, 'audio/mpeg');

        $response = $this->actingAs($this->ownerTalent, 'talent')
            ->from(route('talents.media.index'))
            ->post(route('talents.media.upload'), [
                'type' => 'mp3',
                'title' => 'Canción Pendiente Sin Almacenamiento Privado',
                'file' => $songFile,
            ]);

        $response->assertSessionHasErrors('file');
        $errorMsg = session('errors')->first('file');
        $this->assertStringContainsString('R2_PRIVATE_BUCKET ausente', $errorMsg);
    }

    public function test_rejected_upload_leaves_no_orphaned_files_or_database_records(): void
    {
        $this->mock(\App\Services\ModerationService::class, function ($mock) {
            $mock->shouldReceive('needsModeration')->with('media')->andReturn(true);
        });

        config(['filesystems.disks.r2_private.bucket' => '']);

        $initialMediaCount = TalentMedia::query()->count();
        $initialModerationCount = \App\Models\ModerationItem::query()->count();

        $songFile = UploadedFile::fake()->create('cancion_abortada.mp3', 1024, 'audio/mpeg');

        $this->actingAs($this->ownerTalent, 'talent')
            ->post(route('talents.media.upload'), [
                'type' => 'mp3',
                'title' => 'Canción que debe abortar',
                'file' => $songFile,
            ]);

        // Cero registros creados en base de datos
        $this->assertEquals($initialMediaCount, TalentMedia::query()->count());
        $this->assertEquals($initialModerationCount, \App\Models\ModerationItem::query()->count());

        // Cero archivos huérfanos creados en disco público o privado
        $this->assertEmpty(Storage::disk('r2')->allFiles('talents'));
        $this->assertEmpty(Storage::disk('r2_private')->allFiles('talents'));
    }

    public function test_full_lifecycle_access_after_approval_rejection_and_band_hiding(): void
    {
        // 1. Objeto subido a r2_private en estado pending
        $media = $this->createMediaFile($this->ownerTalent, 'mp3', 'pending', false, 'r2_private');

        // Estado PENDIENTE: Visitante recibe 403, Propietario recibe 200
        auth()->guard('talent')->logout();
        $this->get(route('talents.media.stream', ['id' => $media->id]))->assertStatus(403);

        $this->actingAs($this->ownerTalent, 'talent')
            ->get(route('talents.media.stream', ['id' => $media->id]))->assertStatus(200);

        // 2. APROBACIÓN: Visitante recibe 200
        $media->update(['status' => 'approved']);
        auth()->guard('talent')->logout();
        $this->get(route('talents.media.stream', ['id' => $media->id]))->assertStatus(200);

        // 3. OCULTACIÓN DE BANDA: Visitante recibe 403, Propietario recibe 200
        $this->ownerTalent->update(['is_hidden' => true]);
        auth()->guard('talent')->logout();
        $this->get(route('talents.media.stream', ['id' => $media->id]))->assertStatus(403);

        $this->actingAs($this->ownerTalent, 'talent')
            ->get(route('talents.media.stream', ['id' => $media->id]))->assertStatus(200);

        // 4. RECHAZO: Restaurar visibilidad de banda pero rechazar el archivo: Visitante recibe 403
        $this->ownerTalent->update(['is_hidden' => false]);
        $media->update(['status' => 'rejected']);
        auth()->guard('talent')->logout();
        $this->get(route('talents.media.stream', ['id' => $media->id]))->assertStatus(403);

        $this->actingAs($this->ownerTalent, 'talent')
            ->get(route('talents.media.stream', ['id' => $media->id]))->assertStatus(200);
    }

    public function test_difference_between_internal_route_protection_and_direct_storage_access(): void
    {
        $fileUploadService = app(FileUploadService::class);

        $privateMedia = $this->createMediaFile($this->ownerTalent, 'mp3', 'approved', false, 'r2_private');

        // 1. Para objetos en r2_private, FileUploadService::url() devuelve cadena vacía (no existe URL pública de almacenamiento)
        $this->assertSame('', $fileUploadService->url($privateMedia->backblaze_key, 'r2_private'));

        // 2. En el modelo, el mutador getUrlAttribute devuelve la ruta interna controlada
        $this->assertEquals(route('talents.media.stream', ['id' => $privateMedia->id]), $privateMedia->url);

        // 3. Para un objeto histórico en r2 público con URL directa guardada, el mutador getUrlAttribute
        // fuerza la ruta interna si el archivo es exclusivo o no aprobado
        $historicalPendingMedia = TalentMedia::create([
            'talent_id' => $this->ownerTalent->id,
            'type' => 'mp3',
            'filename' => 'historico.mp3',
            'disk' => 'r2',
            'backblaze_key' => 'talents/historico.mp3',
            'url' => 'https://pub-sevenrock.r2.dev/talents/historico.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => 1024,
            'is_exclusive' => true,
            'status' => 'pending',
        ]);

        // Laravel no expone la URL directa antigua en la vista
        $this->assertEquals(route('talents.media.stream', ['id' => $historicalPendingMedia->id]), $historicalPendingMedia->url);
        // Aunque la columna raw de BD contiene la URL directa original (demostrando que se requiere regularización física en storage)
        $this->assertEquals('https://pub-sevenrock.r2.dev/talents/historico.mp3', $historicalPendingMedia->getRawOriginal('url'));
    }
}
