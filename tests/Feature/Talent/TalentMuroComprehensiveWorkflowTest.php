<?php

declare(strict_types=1);

namespace Tests\Feature\Talent;

use App\Mail\ContentApprovedMail;
use App\Mail\TalentMaterialDeletedMail;
use App\Models\CommunityPost;
use App\Models\NewRelease;
use App\Models\Talent;
use App\Models\TalentMedia;
use App\Models\TrackSubmission;
use App\Models\User;
use App\Notifications\TalentResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TalentMuroComprehensiveWorkflowTest extends TestCase
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

    public function test_registration_normalizes_email_and_prevents_duplicates(): void
    {
        Mail::fake();

        // 1. Registro con espacios y mayúsculas
        $response = $this->post(route('talents.register.store'), [
            'band_name' => 'Los Rockeros del Norte',
            'email' => '  LosRockeros@Example.COM  ',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'plan' => 'free',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('talents.dashboard'));

        $talent = Talent::query()->where('band_name', 'Los Rockeros del Norte')->first();
        $this->assertNotNull($talent);
        $this->assertSame('losrockeros@example.com', $talent->email);

        // 2. Cerrar sesión de la primera banda antes de intentar un segundo registro
        \Illuminate\Support\Facades\Auth::guard('talent')->logout();
        $this->app['session']->flush();

        // 3. Intentar registrar de nuevo con variaciones de mayúsculas/espacios debe ser rechazado
        $duplicateResponse = $this->post(route('talents.register.store'), [
            'band_name' => 'Otra Banda Con Mismo Email',
            'email' => 'LOSROCKEROS@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'plan' => 'free',
            'terms' => '1',
        ]);

        $duplicateResponse->assertSessionHasErrors(['email']);
    }

    public function test_registration_links_prior_maquetas_reliably_by_email_without_account_takeover(): void
    {
        // 1. Simular maqueta previa enviada desde el formulario público
        $priorSubmission = TrackSubmission::query()->create([
            'band_name' => 'Banda Legado',
            'song_title' => 'Himno del Rock 2026',
            'contact_email' => 'contacto@bandalegado.com',
            'file_path' => 'submissions/himno.mp3',
            'status' => 'approved',
            'published_to_hub' => true,
        ]);

        // 2. Maqueta de otra banda distinta
        $otherSubmission = TrackSubmission::query()->create([
            'band_name' => 'Banda Ajena',
            'song_title' => 'Canción Ajena',
            'contact_email' => 'otra@ajena.com',
            'file_path' => 'submissions/ajena.mp3',
            'status' => 'pending',
        ]);

        // 3. Registrar al talento con el mismo email que la maqueta previa
        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'Banda Legado Oficial',
            'email' => 'contacto@bandalegado.com',
            'password' => 'Secret1234!',
            'plan' => 'free',
            'subscription_status' => 'active',
            'email_verified_at' => now(),
        ]);

        // 4. Comprobar que la relación submissions vincula la maqueta propia y no la ajena
        $submissions = $talent->submissions;
        $this->assertCount(1, $submissions);
        $this->assertSame($priorSubmission->id, $submissions->first()->id);
        $this->assertSame('Himno del Rock 2026', $submissions->first()->song_title);

        // 5. En el dashboard autenticado debe listarse su maqueta
        $dashboardResponse = $this->actingAs($talent, 'talent')->get(route('talents.dashboard'));
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Himno del Rock 2026');
        $dashboardResponse->assertDontSee('Canción Ajena');
    }

    public function test_talent_forgot_password_and_reset_workflow(): void
    {
        Notification::fake();

        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'Banda Reseteo',
            'email' => 'reseteo@example.com',
            'password' => 'OldPassword123!',
            'plan' => 'free',
            'subscription_status' => 'active',
            'email_verified_at' => now(),
        ]);

        // 1. Ver formulario de solicitud
        $response = $this->get(route('talents.password.request'));
        $response->assertStatus(200);
        $response->assertSee('Recuperar Contraseña');

        // 2. Enviar solicitud con correo (incluso con mayúsculas/espacios)
        $postResponse = $this->post(route('talents.password.email'), [
            'email' => '  RESETEO@example.com  ',
        ]);
        $postResponse->assertSessionHas('status');

        Notification::assertSentTo($talent, TalentResetPasswordNotification::class, function ($notification) use ($talent) {
            $token = $notification->token;
            $this->assertNotEmpty($token);

            // 3. Ver formulario de cambio con token
            $formResponse = $this->get(route('talents.password.reset', [
                'token' => $token,
                'email' => $talent->email,
            ]));
            $formResponse->assertStatus(200);
            $formResponse->assertSee('Nueva Contraseña');

            // 4. Cambiar contraseña con éxito
            $resetResponse = $this->post(route('talents.password.update'), [
                'token' => $token,
                'email' => $talent->email,
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ]);
            $resetResponse->assertRedirect(route('talents.login'));

            return true;
        });

        // 5. Verificar que la contraseña cambió y permite login
        $talent->refresh();
        $this->assertTrue(Hash::check('NewPassword456!', $talent->password));

        $loginResponse = $this->post(route('talents.login.store'), [
            'email' => 'reseteo@example.com',
            'password' => 'NewPassword456!',
        ]);
        $loginResponse->assertRedirect(route('talents.dashboard'));
    }

    public function test_reset_password_fails_with_invalid_token_or_unconfirmed_password(): void
    {
        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'Banda Token Invalido',
            'email' => 'tokenfail@example.com',
            'password' => 'Password123!',
            'plan' => 'free',
            'subscription_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $response = $this->post(route('talents.password.update'), [
            'token' => 'token-invalido-o-caducado',
            'email' => $talent->email,
            'password' => 'NuevaClave123!',
            'password_confirmation' => 'NuevaClave123!',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_public_profile_only_exposes_approved_media_and_hides_pending_or_private_docs(): void
    {
        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'Banda Moderacion Media',
            'email' => 'moderacion@example.com',
            'password' => 'password123',
            'plan' => 'basic',
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        // Canción aprobada
        TalentMedia::query()->create([
            'talent_id' => $talent->id,
            'type' => 'mp3',
            'filename' => 'aprobada.mp3',
            'title' => 'Canción Aprobada',
            'mime_type' => 'audio/mpeg',
            'disk' => 'r2',
            'backblaze_key' => 'talents/1/media/mp3/aprobada.mp3',
            'url' => 'https://r2.sevenrockradio.com/aprobada.mp3',
            'status' => 'approved',
            'size' => 1024,
        ]);

        // Canción pendiente
        TalentMedia::query()->create([
            'talent_id' => $talent->id,
            'type' => 'mp3',
            'filename' => 'pendiente.mp3',
            'title' => 'Canción Pendiente Secreta',
            'mime_type' => 'audio/mpeg',
            'disk' => 'r2',
            'backblaze_key' => 'talents/1/media/mp3/pendiente.mp3',
            'url' => 'https://r2.sevenrockradio.com/pendiente.mp3',
            'status' => 'pending',
            'size' => 1024,
        ]);

        // Documento privado (ej. contrato en r2_private)
        TalentMedia::query()->create([
            'talent_id' => $talent->id,
            'type' => 'document',
            'filename' => 'contrato_privado.pdf',
            'title' => 'Contrato Confidencial',
            'mime_type' => 'application/pdf',
            'disk' => 'r2_private',
            'backblaze_key' => 'talents/1/media/document/contrato.pdf',
            'url' => route('talents.media.download', ['id' => 9999]),
            'status' => 'approved',
            'size' => 1024,
        ]);

        // Visitar el perfil público como invitado
        $response = $this->get('/muro-del-rock/' . rawurlencode($talent->band_name));
        $response->assertStatus(200);

        // Debe ver el contenido aprobado
        $response->assertSee('Canción Aprobada');

        // NO debe ver el contenido pendiente de moderación
        $response->assertDontSee('Canción Pendiente Secreta');

        // NO debe ver el documento privado
        $response->assertDontSee('Contrato Confidencial');
    }

    public function test_admin_deleting_talent_media_sends_material_deleted_mail_not_content_approved(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);

        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'Banda Borrado Test',
            'email' => 'borrado@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $media = TalentMedia::query()->create([
            'talent_id' => $talent->id,
            'type' => 'photo',
            'filename' => 'foto_inadecuada.jpg',
            'title' => 'Foto Inadecuada',
            'mime_type' => 'image/jpeg',
            'disk' => 'r2',
            'backblaze_key' => 'talents/1/media/photo/inadecuada.jpg',
            'url' => 'https://r2.sevenrockradio.com/inadecuada.jpg',
            'status' => 'approved',
            'size' => 1024,
        ]);

        $response = $this->actingAs($admin, 'web')
            ->delete(route('admin.talents.media.destroy', $media));

        $response->assertRedirect();

        // Debe enviar TalentMaterialDeletedMail
        Mail::assertSent(TalentMaterialDeletedMail::class, function ($mail) use ($talent) {
            return $mail->hasTo($talent->email);
        });

        // La base de datos debe haber eliminado el registro
        $this->assertSoftDeleted('talent_media', ['id' => $media->id]);
    }

    public function test_login_redirect_preserves_safe_destination_after_auth(): void
    {
        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'Banda Intended',
            'email' => 'intended@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $response = $this->post(route('talents.login.store'), [
            'email' => 'intended@example.com',
            'password' => 'password123',
            'redirect_to' => '/talentos/media',
        ]);

        $response->assertRedirect('/talentos/media');
    }

    public function test_unverified_talent_registration_does_not_reveal_historical_submissions_until_verified(): void
    {
        // 1. Simular maqueta previa en la base de datos
        $priorSubmission = TrackSubmission::query()->create([
            'band_name' => 'Banda Secreta',
            'song_title' => 'Maqueta Confidencial',
            'contact_email' => 'artista@secreto.es',
            'file_path' => 'submissions/secreto.mp3',
            'status' => 'approved',
            'published_to_hub' => false,
        ]);

        // 2. Registrar cuenta con ese correo ajeno desde el formulario público
        $registerResponse = $this->post(route('talents.register.store'), [
            'band_name' => 'Banda Que Intenta Suplantar',
            'email' => 'artista@secreto.es',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'plan' => 'free',
            'terms' => '1',
        ]);
        $registerResponse->assertRedirect(route('talents.dashboard'));

        $talent = Talent::query()->where('email', 'artista@secreto.es')->first();
        $this->assertNotNull($talent);
        $this->assertNull($talent->email_verified_at);

        // 3. Comprobar que submissions() no revela la maqueta mientras la cuenta no esté verificada
        $this->assertSame(0, $talent->submissions()->count());

        // 4. Comprobar que en el dashboard NO aparece la maqueta histórica y se muestra aviso de verificación
        $dashboardResponse = $this->actingAs($talent, 'talent')->get(route('talents.dashboard'));
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertDontSee('Maqueta Confidencial');
        $dashboardResponse->assertSee('Cuenta pendiente de verificación de correo');

        // 5. Una vez verificado el correo, las maquetas se vinculan legítimamente
        $talent->email_verified_at = now();
        $talent->save();

        $this->assertSame(1, $talent->submissions()->count());
        $verifiedDashboardResponse = $this->actingAs($talent, 'talent')->get(route('talents.dashboard'));
        $verifiedDashboardResponse->assertSee('Maqueta Confidencial');
        $verifiedDashboardResponse->assertDontSee('Cuenta pendiente de verificación de correo');
    }

    public function test_registration_requires_terms_acceptance_and_records_evidence(): void
    {
        Mail::fake();

        // 1. Envío sin marcar términos debe ser rechazado
        $responseNoTerms = $this->post(route('talents.register.store'), [
            'band_name' => 'Banda Sin Terminos',
            'email' => 'sinterminos@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'plan' => 'free',
        ]);
        $responseNoTerms->assertSessionHasErrors(['terms']);

        // 2. Envío con término = 0 debe ser rechazado
        $responseZeroTerms = $this->post(route('talents.register.store'), [
            'band_name' => 'Banda Terminos Cero',
            'email' => 'cero@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'plan' => 'free',
            'terms' => '0',
        ]);
        $responseZeroTerms->assertSessionHasErrors(['terms']);

        // 3. Envío con términos aceptados se procesa y guarda evidencia
        $responseAccepted = $this->post(route('talents.register.store'), [
            'band_name' => 'Banda Con Terminos Aceptados',
            'email' => 'conterminos@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'plan' => 'free',
            'terms' => '1',
        ]);
        $responseAccepted->assertRedirect(route('talents.dashboard'));

        $talent = Talent::query()->where('email', 'conterminos@example.com')->first();
        $this->assertNotNull($talent);
        $this->assertNotNull($talent->terms_accepted_at);
        $this->assertSame('v1_2026-11', $talent->terms_version);
        $this->assertIsArray($talent->notification_preferences);
        $this->assertSame('v1_2026-11', $talent->notification_preferences['terms_version']);
    }

    public function test_admin_can_approve_pending_media_and_notify_artist(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);

        $talent = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'Banda Aprobacion Media',
            'email' => 'aprobacion@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'active',
            'email_verified_at' => now(),
            'is_hidden' => false,
        ]);

        $media = TalentMedia::query()->create([
            'talent_id' => $talent->id,
            'type' => 'mp3',
            'filename' => 'tema_inédito.mp3',
            'title' => 'Tema Inédito Editorial',
            'mime_type' => 'audio/mpeg',
            'disk' => 'r2',
            'backblaze_key' => 'talents/1/media/mp3/tema.mp3',
            'url' => 'https://r2.sevenrockradio.com/tema.mp3',
            'status' => 'pending',
            'size' => 2048,
        ]);

        // 1. No debe aparecer en el perfil público aún
        $publicResponse = $this->get('/muro-del-rock/' . rawurlencode($talent->band_name));
        $publicResponse->assertStatus(200);
        $publicResponse->assertDontSee('Tema Inédito Editorial');

        // 2. Admin aprueba el archivo
        $approveResponse = $this->actingAs($admin, 'web')
            ->post(route('admin.talents.media.approve', $media));

        $approveResponse->assertRedirect();

        // 3. Comprobar actualización en base de datos
        $media->refresh();
        $this->assertSame('approved', $media->status);

        // 4. Correo de aprobación enviado
        Mail::assertSent(ContentApprovedMail::class, function ($mail) use ($talent) {
            return $mail->hasTo($talent->email);
        });

        // 5. Ahora sí aparece en el perfil público
        $publicAfterResponse = $this->get('/muro-del-rock/' . rawurlencode($talent->band_name));
        $publicAfterResponse->assertStatus(200);
        $publicAfterResponse->assertSee('Tema Inédito Editorial');
    }

    public function test_exclusive_media_stored_in_private_disk_and_blocks_unauthorized_guests(): void
    {
        $band = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'Banda Exclusiva VIP',
            'email' => 'vip@example.com',
            'password' => 'password123',
            'plan' => 'pro',
            'subscription_status' => 'active',
            'email_verified_at' => now(),
            'is_hidden' => false,
        ]);

        // Simular archivo físico en r2_private
        Storage::disk('r2_private')->put('talents/exclusive/track_vip.mp3', 'AUDIO_DATA_BYTES');

        $media = TalentMedia::query()->create([
            'talent_id' => $band->id,
            'type' => 'mp3',
            'filename' => 'track_vip.mp3',
            'title' => 'Pista VIP Solo Afiliados',
            'mime_type' => 'audio/mpeg',
            'disk' => 'r2_private',
            'backblaze_key' => 'talents/exclusive/track_vip.mp3',
            'url' => route('talents.media.stream', ['id' => 999]),
            'is_exclusive' => true,
            'status' => 'approved',
            'size' => 1024,
        ]);

        // 1. Invitado intenta reproducir el streaming directo: debe recibir 403 Forbidden
        $guestResponse = $this->get(route('talents.media.stream', ['id' => $media->id]));
        $guestResponse->assertStatus(403);

        // 2. Usuario web autenticado (afiliado / suscriptor) accede al streaming: debe recibir 200 OK
        $fan = User::factory()->create();
        $fanResponse = $this->actingAs($fan, 'web')->get(route('talents.media.stream', ['id' => $media->id]));
        $fanResponse->assertStatus(200);

        // 3. Dueño de la banda accede al streaming: debe recibir 200 OK
        $ownerResponse = $this->actingAs($band, 'talent')->get(route('talents.media.stream', ['id' => $media->id]));
        $ownerResponse->assertStatus(200);

        // 4. Administrador accede al streaming: debe recibir 200 OK
        $admin = User::factory()->create(['is_admin' => true]);
        $adminResponse = $this->actingAs($admin, 'web')->get(route('talents.media.stream', ['id' => $media->id]));
        $adminResponse->assertStatus(200);
    }

    public function test_hidden_talents_are_excluded_from_all_recommendations_and_public_queries(): void
    {
        $visibleBand = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'Banda Visible y Activa',
            'email' => 'visible@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'active',
            'email_verified_at' => now(),
            'is_hidden' => false,
            'bio' => 'Banda de rock alternativo',
        ]);

        $hiddenBand = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'Banda Oculta Confidencial',
            'email' => 'oculta@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'active',
            'email_verified_at' => now(),
            'is_hidden' => true,
            'bio' => 'Banda de rock alternativo oculta',
        ]);

        TalentMedia::query()->create([
            'talent_id' => $hiddenBand->id,
            'type' => 'mp3',
            'filename' => 'oculta.mp3',
            'title' => 'Pista de Banda Oculta',
            'mime_type' => 'audio/mpeg',
            'disk' => 'r2_private',
            'backblaze_key' => 'talents/hidden/oculta.mp3',
            'url' => 'https://r2.sevenrockradio.com/oculta.mp3',
            'is_exclusive' => true,
            'status' => 'approved',
            'size' => 1024,
        ]);

        // 1. En la ficha de la banda visible, la banda oculta NO debe aparecer recomendada
        $profileResponse = $this->get('/muro-del-rock/' . rawurlencode($visibleBand->band_name));
        $profileResponse->assertStatus(200);
        $profileResponse->assertDontSee('Banda Oculta Confidencial');

        // 2. En el sidebar del Muro de Comunidad, la banda oculta NO debe figurar
        $fan = User::factory()->create();
        $muroResponse = $this->actingAs($fan, 'web')->get(route('comunidad.muro'));
        $muroResponse->assertStatus(200);
        $muroResponse->assertDontSee('Banda Oculta Confidencial');

        // 3. En la sección de Exclusivos, el material de la banda oculta NO debe figurar
        $exclusivosResponse = $this->actingAs($fan, 'web')->get(route('comunidad.exclusivos'));
        $exclusivosResponse->assertStatus(200);
        $exclusivosResponse->assertDontSee('Pista de Banda Oculta');
    }

    public function test_profile_shows_clean_empty_states_without_soundhelix_or_demo_photos(): void
    {
        $band = Talent::query()->create([
            'user_id' => User::factory()->create()->id,
            'band_name' => 'Banda Sin Material Aún',
            'email' => 'sinmaterial@example.com',
            'password' => 'password123',
            'plan' => 'free',
            'subscription_status' => 'active',
            'email_verified_at' => now(),
            'is_hidden' => false,
        ]);

        $response = $this->get('/muro-del-rock/' . rawurlencode($band->band_name));
        $response->assertStatus(200);

        // Debe mostrar estados vacíos auténticos
        $response->assertSee('Sin pistas publicadas');
        $response->assertSee('Esta banda aún no ha publicado pistas de audio en su perfil.');
        $response->assertSee('Sin fotografías');
        $response->assertSee('Esta banda aún no ha compartido fotografías en su galería.');

        // NUNCA debe contener canciones de SoundHelix ni fotos demo en el HTML
        $response->assertDontSee('SoundHelix');
        $response->assertDontSee('Canción de Rock Demo');
        $response->assertDontSee('muro-del-rock-banner.webp');
    }
}
