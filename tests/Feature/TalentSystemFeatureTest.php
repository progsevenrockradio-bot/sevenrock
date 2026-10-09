<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\TalentApprovedMail;
use App\Mail\TalentHiddenNotificationMail;
use App\Mail\TalentMaterialDeletedMail;
use App\Mail\TalentPendingApprovalMail;
use App\Mail\TalentReferralRewardMail;
use App\Models\PromoterCode;
use App\Models\Talent;
use App\Models\TalentMedia;
use App\Models\TalentReferral;
use App\Models\TalentSubscription;
use App\Models\User;
use App\Services\TalentReferralService;
use App\Http\Controllers\Admin\TalentAdminController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TalentSystemFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function createTalent(array $attributes = []): Talent
    {
        return Talent::create(array_merge([
            'password'            => Hash::make('Secret123!'),
            'subscription_status' => 'pending',
            'plan'                => 'free',
        ], $attributes));
    }

    /**
     * 1. Banda en free aprobada → end_date = +45 días, visible, status = active.
     */
    public function test_free_band_approved_has_45_days_visible_and_active_status(): void
    {
        Mail::fake();

        $talent = $this->createTalent([
            'band_name'           => 'Banda Test 1',
            'email'               => 'banda1@test.com',
            'plan'                => 'free',
            'subscription_status' => 'pending',
            'is_hidden'           => true,
        ]);

        $talent->subscriptions()->create([
            'plan'             => 'free',
            'amount'           => 0,
            'currency'         => 'EUR',
            'payment_provider' => 'manual',
            'start_date'       => today(),
            'end_date'         => today()->addDays(10),
            'status'           => 'pending',
        ]);

        app(TalentAdminController::class)->approve($talent, app(TalentReferralService::class));

        $talent->refresh();
        $this->assertEquals('active', $talent->subscription_status);
        $this->assertFalse((bool) $talent->is_hidden);
        $this->assertNull($talent->expires_grace_at);

        $subscription = $talent->subscriptions()->latest()->first();
        $this->assertNotNull($subscription);
        $this->assertEquals('active', $subscription->status);
        $this->assertTrue($subscription->end_date->isSameDay(today()->addDays(Talent::FREE_DURATION_DAYS)));
        Mail::assertSent(TalentApprovedMail::class);
    }

    /**
     * 2. Al vencer (45) → oculta, no borrada, con su correo enviado.
     */
    public function test_upon_expiry_band_is_hidden_not_deleted_and_notification_sent(): void
    {
        Mail::fake();

        $talent = $this->createTalent([
            'band_name'           => 'Banda Vencida',
            'email'               => 'vencida@test.com',
            'plan'                => 'free',
            'subscription_status' => 'active',
            'is_hidden'           => false,
        ]);

        $talent->subscriptions()->create([
            'plan'             => 'free',
            'amount'           => 0,
            'currency'         => 'EUR',
            'payment_provider' => 'manual',
            'start_date'       => today()->subDays(45),
            'end_date'         => today(),
            'status'           => 'active',
        ]);

        $this->artisan('talents:expire')->assertSuccessful();

        $talent->refresh();
        $this->assertTrue((bool) $talent->is_hidden);
        $this->assertEquals('expired', $talent->subscription_status);
        $this->assertNotNull($talent->expires_grace_at);
        $this->assertDatabaseHas('talents', ['id' => $talent->id]); // Ficha conservada

        Mail::assertSent(TalentHiddenNotificationMail::class, function ($mail) use ($talent) {
            return $mail->hasTo($talent->email);
        });
    }

    /**
     * 3. Renovar dentro de los 15 días de gracia → visible otra vez con +45 días.
     */
    public function test_renew_during_15_day_grace_period_makes_band_visible_with_45_days(): void
    {
        $talent = $this->createTalent([
            'band_name'           => 'Banda En Gracia',
            'email'               => 'gracia@test.com',
            'plan'                => 'free',
            'subscription_status' => 'expired',
            'is_hidden'           => true,
            'expires_grace_at'    => now()->addDays(10),
        ]);

        $talent->subscriptions()->create([
            'plan'             => 'free',
            'amount'           => 0,
            'currency'         => 'EUR',
            'payment_provider' => 'manual',
            'start_date'       => today()->subDays(50),
            'end_date'         => today()->subDays(5),
            'status'           => 'expired',
        ]);

        // Renovación dentro del período de gracia vía checkout de plan free
        $response = $this->actingAs($talent, 'talent')->post(route('talents.subscriptions.checkout'), [
            'plan'    => 'free',
            'gateway' => 'mercadopago',
        ]);

        $response->assertRedirect(route('talents.dashboard'));

        $talent->refresh();
        $this->assertFalse((bool) $talent->is_hidden);
        $this->assertEquals('active', $talent->subscription_status);
        $this->assertNull($talent->expires_grace_at);

        $sub = $talent->subscriptions()->latest()->first();
        $this->assertEquals('active', $sub->status);
        $this->assertTrue($sub->end_date->isSameDay(today()->addDays(Talent::FREE_DURATION_DAYS)));
    }

    /**
     * 4. Sin renovar a los 15 días → material borrado, ficha conservada.
     */
    public function test_without_renewal_after_15_days_grace_media_purged_profile_preserved(): void
    {
        Mail::fake();

        $talent = $this->createTalent([
            'band_name'           => 'Banda Purgada',
            'email'               => 'purgada@test.com',
            'plan'                => 'free',
            'subscription_status' => 'expired',
            'is_hidden'           => true,
            'expires_grace_at'    => now()->subDay(), // Gracia ya vencida
        ]);

        TalentMedia::create([
            'talent_id'     => $talent->id,
            'type'          => 'mp3',
            'title'         => 'Cancion Demo',
            'filename'      => 'cancion_test.mp3',
            'url'           => 'https://example.com/cancion_test.mp3',
            'path'          => 'talents/media/cancion_test.mp3',
            'backblaze_key' => 'cancion_test.mp3',
            'mime_type'     => 'audio/mpeg',
            'size'          => 1024,
        ]);

        $this->assertDatabaseHas('talent_media', ['talent_id' => $talent->id]);

        $this->artisan('talents:expire')->assertSuccessful();

        // Material borrado de base de datos
        $this->assertDatabaseMissing('talent_media', ['talent_id' => $talent->id]);

        // Ficha conservada
        $this->assertDatabaseHas('talents', ['id' => $talent->id]);
        $talent->refresh();
        $this->assertTrue((bool) $talent->is_hidden);
        $this->assertEquals('expired', $talent->subscription_status);
        $this->assertNull($talent->expires_grace_at);

        Mail::assertSent(TalentMaterialDeletedMail::class, function ($mail) use ($talent) {
            return $mail->hasTo($talent->email);
        });
    }

    /**
     * 5. Código de referido válido → el nuevo recibe 60 días y el referidor suma 1 referido pending.
     */
    public function test_valid_referral_code_grants_60_days_to_new_and_pending_referral_to_referrer(): void
    {
        $referrer = $this->createTalent([
            'band_name'     => 'Referidor Alpha',
            'email'         => 'alpha@test.com',
            'referral_code' => 'ALPHA123',
            'plan'          => 'free',
        ]);

        $response = $this->post(route('talents.register.store'), [
            'band_name'             => 'Nuevo Referido',
            'email'                 => 'nuevo@test.com',
            'password'              => 'Secret1234!',
            'password_confirmation' => 'Secret1234!',
            'plan'                  => 'free',
            'referral_code'         => 'ALPHA123',
        ]);

        $newTalent = Talent::where('email', 'nuevo@test.com')->first();
        $this->assertNotNull($newTalent);

        // El nuevo recibe 60 días (45 + 15 bono de referido)
        $sub = $newTalent->subscriptions()->latest()->first();
        $this->assertNotNull($sub);
        $this->assertTrue($sub->end_date->isSameDay(today()->addDays(Talent::FREE_DURATION_DAYS + Talent::REFERRAL_BONUS_DAYS)));

        // El referidor suma 1 referido con status pending
        $this->assertDatabaseHas('talent_referrals', [
            'referrer_talent_id' => $referrer->id,
            'referred_talent_id' => $newTalent->id,
            'status'             => 'pending',
        ]);
        $this->assertEquals(1, $referrer->pendingReferralsCount());
        $this->assertEquals(0, $referrer->activeReferralsCount());
    }

    /**
     * 6. Referido que no activa → no cuenta.
     */
    public function test_unactivated_referral_does_not_count_towards_milestones(): void
    {
        $referrer = $this->createTalent([
            'band_name'     => 'Referidor Beta',
            'email'         => 'beta@test.com',
            'referral_code' => 'BETA999',
            'plan'          => 'free',
        ]);

        $referred = $this->createTalent([
            'band_name'           => 'Referido Inactivo',
            'email'               => 'inactivo@test.com',
            'plan'                => 'free',
            'subscription_status' => 'pending',
        ]);

        app(TalentReferralService::class)->registerReferral($referred, 'BETA999');

        $this->assertEquals(1, $referrer->pendingReferralsCount());
        $this->assertEquals(0, $referrer->activeReferralsCount());
        $this->assertNull(TalentReferral::where('referred_talent_id', $referred->id)->first()->activated_at);
    }

    /**
     * 7. 12 referidos activos → premio aplicado: +30 días y plan basic, y no se repite.
     */
    public function test_twelve_active_referrals_awards_30_bonus_days_and_basic_plan_and_does_not_repeat(): void
    {
        Mail::fake();

        $referrer = $this->createTalent([
            'band_name'           => 'Banda Campeona',
            'email'               => 'campeona@test.com',
            'referral_code'       => 'CAMP12',
            'plan'                => 'free',
            'subscription_status' => 'active',
        ]);

        $initialEndDate = today()->addDays(20);
        $referrer->subscriptions()->create([
            'plan'             => 'free',
            'amount'           => 0,
            'currency'         => 'EUR',
            'payment_provider' => 'manual',
            'start_date'       => today(),
            'end_date'         => $initialEndDate->copy(),
            'status'           => 'active',
        ]);

        $service = app(TalentReferralService::class);

        // Crear 12 referidos y activarlos sucesivamente
        for ($i = 1; $i <= 12; $i++) {
            $refTalent = $this->createTalent([
                'band_name'           => "Ref Talent {$i}",
                'email'               => "ref{$i}@test.com",
                'plan'                => 'free',
                'subscription_status' => 'pending',
            ]);

            $service->registerReferral($refTalent, 'CAMP12');
            $service->activateReferral($refTalent);
        }

        $referrer->refresh();
        $this->assertEquals(12, $referrer->activeReferralsCount());

        // Debe haber subido a plan basic
        $this->assertEquals('basic', $referrer->plan);

        // La suscripción debe tener +30 días y plan basic
        $activeSub = $referrer->activeSubscription();
        $this->assertNotNull($activeSub);
        $this->assertEquals('basic', $activeSub->plan);
        $this->assertTrue(Carbon::parse($activeSub->end_date)->isSameDay($initialEndDate->copy()->addDays(30)));

        // Hito tier1 aplicado exactamente 1 vez
        $this->assertEquals(1, TalentReferral::where('referrer_talent_id', $referrer->id)->where('reward_applied', 'tier1')->count());
        Mail::assertSent(TalentReferralRewardMail::class);

        // Un 13vo referido activo NO vuelve a aplicar el premio de 12
        $refTalent13 = $this->createTalent([
            'band_name'           => 'Ref Talent 13',
            'email'               => 'ref13@test.com',
            'plan'                => 'free',
            'subscription_status' => 'pending',
        ]);
        $service->registerReferral($refTalent13, 'CAMP12');
        $service->activateReferral($refTalent13);

        $this->assertEquals(1, TalentReferral::where('referrer_talent_id', $referrer->id)->where('reward_applied', 'tier1')->count());
        $this->assertTrue(Carbon::parse($activeSub->fresh()->end_date)->isSameDay($initialEndDate->copy()->addDays(30)));
    }

    /**
     * 8. 18 referidos activos → +60 días.
     */
    public function test_eighteen_active_referrals_awards_60_bonus_days(): void
    {
        Mail::fake();

        $referrer = $this->createTalent([
            'band_name'           => 'Banda Master',
            'email'               => 'master@test.com',
            'referral_code'       => 'MASTER18',
            'plan'                => 'basic',
            'subscription_status' => 'active',
        ]);

        $initialEndDate = today()->addDays(30);
        $referrer->subscriptions()->create([
            'plan'             => 'basic',
            'amount'           => 0,
            'currency'         => 'EUR',
            'payment_provider' => 'manual',
            'start_date'       => today(),
            'end_date'         => $initialEndDate->copy(),
            'status'           => 'active',
        ]);

        $service = app(TalentReferralService::class);

        // Crear y activar 18 referidos
        for ($i = 1; $i <= 18; $i++) {
            $refTalent = $this->createTalent([
                'band_name'           => "Master Ref {$i}",
                'email'               => "master_ref{$i}@test.com",
                'plan'                => 'free',
                'subscription_status' => 'pending',
            ]);
            $service->registerReferral($refTalent, 'MASTER18');
            $service->activateReferral($refTalent);
        }

        $referrer->refresh();
        $this->assertEquals(18, $referrer->activeReferralsCount());

        // Se aplicó tier2 (+60 días)
        $this->assertTrue(TalentReferral::where('referrer_talent_id', $referrer->id)->where('reward_applied', 'tier2')->exists());
        $activeSub = $referrer->activeSubscription();
        // Recibió tier1 (+30d) al 12 y tier2 (+60d) al 18 = +90d total
        $this->assertTrue(Carbon::parse($activeSub->end_date)->isSameDay($initialEndDate->copy()->addDays(30 + 60)));
    }

    /**
     * 9. 3 referidos en plan de pago → +90 días.
     */
    public function test_three_paid_referrals_awards_90_bonus_days(): void
    {
        Mail::fake();

        $referrer = $this->createTalent([
            'band_name'           => 'Banda Sponsor',
            'email'               => 'sponsor@test.com',
            'referral_code'       => 'SPONSOR3',
            'plan'                => 'basic',
            'subscription_status' => 'active',
        ]);

        $initialEndDate = today()->addDays(20);
        $referrer->subscriptions()->create([
            'plan'             => 'basic',
            'amount'           => 0,
            'currency'         => 'EUR',
            'payment_provider' => 'manual',
            'start_date'       => today(),
            'end_date'         => $initialEndDate->copy(),
            'status'           => 'active',
        ]);

        $service = app(TalentReferralService::class);

        // Crear 3 referidos activos y pasarlos a pago
        for ($i = 1; $i <= 3; $i++) {
            $refTalent = $this->createTalent([
                'band_name'           => "Paid Ref {$i}",
                'email'               => "paid_ref{$i}@test.com",
                'plan'                => 'basic',
                'subscription_status' => 'active',
            ]);
            $service->registerReferral($refTalent, 'SPONSOR3');
            $service->activateReferral($refTalent);
            $service->markReferralPaid($refTalent);
        }

        $referrer->refresh();
        $this->assertEquals(3, $referrer->paidReferralsCount());
        $this->assertTrue(TalentReferral::where('referrer_talent_id', $referrer->id)->where('reward_applied', 'paid_tier')->exists());

        // La suscripción debe tener +90 días sumados
        $activeSub = $referrer->activeSubscription();
        $this->assertTrue(Carbon::parse($activeSub->end_date)->isSameDay($initialEndDate->copy()->addDays(90)));
    }

    /**
     * 10. Alta sin capturas de Facebook/Instagram → no se envía el aviso de aprobación.
     */
    public function test_registration_without_facebook_or_instagram_screenshots_does_not_send_approval_email(): void
    {
        Mail::fake();

        // 1. Registro SIN capturas
        $this->post(route('talents.register.store'), [
            'band_name'             => 'Banda Sin Capturas',
            'email'                 => 'sincapturas@test.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
            'plan'                  => 'free',
            'facebook_screenshot'   => null,
            'instagram_screenshot'  => null,
        ])->assertRedirect(route('talents.dashboard'));

        Mail::assertNotSent(TalentPendingApprovalMail::class);

        // Desloguear para que la siguiente petición de registro sea como invitado
        Auth::guard('talent')->logout();
        session()->flush();

        // 2. Registro CON captura
        $this->post(route('talents.register.store'), [
            'band_name'             => 'Banda Con Captura',
            'email'                 => 'concaptura@test.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
            'plan'                  => 'free',
            'facebook_screenshot'   => 'talents/screenshots/facebook_fb.jpg',
        ])->assertRedirect(route('talents.dashboard'));

        Mail::assertSent(TalentPendingApprovalMail::class, function ($mail) {
            return $mail->hasTo('prog.sevenrockradio@gmail.com');
        });
    }

    /**
     * 11. Un código de promotor que llega a 18 → avisa y no bloquea: al ampliar el tope se puede seguir; al renovar el código, el contador arranca a cero y el anterior queda archivado.
     */
    public function test_promoter_code_limit_warns_does_not_block_can_expand_and_renew(): void
    {
        $promoter = PromoterCode::create([
            'code'        => 'PROMO18',
            'owner_name'  => 'Locutor Rock',
            'owner_email' => 'locutor@test.com',
            'owner_type'  => 'conductor',
            'max_bands'   => 18,
            'is_archived' => false,
        ]);

        $service = app(TalentReferralService::class);

        // Crear 18 bandas activas
        for ($i = 1; $i <= 18; $i++) {
            $t = $this->createTalent([
                'band_name'           => "Promoted Band {$i}",
                'email'               => "promoted{$i}@test.com",
                'plan'                => 'free',
                'subscription_status' => 'pending',
            ]);
            $service->registerReferral($t, 'PROMO18');
            $service->activateReferral($t);
        }

        $this->assertEquals(18, $promoter->activeBandsCount());
        $this->assertTrue($promoter->hasReachedLimit());

        // La banda 19 se registra sin bloquearse
        $band19 = $this->createTalent([
            'band_name'           => 'Promoted Band 19',
            'email'               => 'promoted19@test.com',
            'plan'                => 'free',
            'subscription_status' => 'pending',
        ]);
        $service->registerReferral($band19, 'PROMO18');
        $this->assertDatabaseHas('talent_referrals', [
            'promoter_code_id'   => $promoter->id,
            'referred_talent_id' => $band19->id,
        ]);

        // Ampliar el tope
        $promoter->expandLimit(25);
        $this->assertEquals(25, $promoter->fresh()->max_bands);
        $this->assertFalse($promoter->fresh()->hasReachedLimit());

        // Renovar código: se archiva el actual y el nuevo arranca con contador a cero
        $newPromoter = $promoter->archiveAndRenew();
        $this->assertTrue($promoter->fresh()->is_archived);
        $this->assertFalse($newPromoter->is_archived);
        $this->assertEquals(0, $newPromoter->activeBandsCount());
        $this->assertNotEquals($promoter->code, $newPromoter->code);
    }

    /**
     * 12. La ruta firmada de aprobar por correo funciona, y con firma inválida no funciona.
     */
    public function test_signed_email_approval_route_works_with_valid_signature_and_fails_with_invalid_signature(): void
    {
        Mail::fake();

        $talent = $this->createTalent([
            'band_name'           => 'Banda Correo',
            'email'               => 'correo@test.com',
            'plan'                => 'free',
            'subscription_status' => 'pending',
            'is_hidden'           => true,
        ]);

        $talent->subscriptions()->create([
            'plan'             => 'free',
            'amount'           => 0,
            'currency'         => 'EUR',
            'payment_provider' => 'manual',
            'start_date'       => today(),
            'end_date'         => today()->addDays(10),
            'status'           => 'pending',
        ]);

        // 1. Sin firma -> 403
        $noSigUrl = route('admin.talents.approve-email', ['talent' => $talent->id]);
        $this->get($noSigUrl)->assertStatus(403);

        // 2. Con firma manipulada / inválida -> 403
        $validUrl = URL::temporarySignedRoute('admin.talents.approve-email', now()->addDays(7), ['talent' => $talent->id]);
        $manipulatedUrl = $validUrl . 'fake';
        $this->get($manipulatedUrl)->assertStatus(403);

        // El talento sigue pendiente
        $this->assertEquals('pending', $talent->fresh()->subscription_status);

        // 3. Con firma válida -> 200 y aprueba
        $response = $this->get($validUrl);
        $response->assertStatus(200);
        $response->assertViewIs('admin.talents.email_action_result');

        $talent->refresh();
        $this->assertEquals('active', $talent->subscription_status);
        $this->assertFalse((bool) $talent->is_hidden);
        Mail::assertSent(TalentApprovedMail::class);
    }

    /**
     * 13. Se puede crear un talento con status 'pending' y user_id nulo.
     */
    public function test_can_create_talent_with_pending_status_and_null_user_id(): void
    {
        $talent = Talent::create([
            'band_name'           => 'Banda Nueva Null',
            'email'               => 'bandanull@test.com',
            'password'            => Hash::make('Secret123!'),
            'subscription_status' => 'pending',
            'plan'                => 'free',
            'user_id'             => null,
        ]);

        $this->assertDatabaseHas('talents', [
            'id' => $talent->id,
            'subscription_status' => 'pending',
            'user_id' => null,
        ]);
    }
}
