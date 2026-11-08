<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PromoterCode;
use App\Models\Talent;
use App\Models\TalentReferral;
use App\Models\TalentReferralReward;
use App\Models\TalentSubscription;
use App\Models\User;
use App\Services\TalentReferralPilotService;
use App\Services\TalentSubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TalentReferralPilotTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create([
            'name' => 'José Font',
            'email' => 'admin@sevenrock.test',
            'password' => Hash::make('Secret123!'),
        ]);
    }

    private function createBand(string $plan = 'basic', array $attributes = []): Talent
    {
        return Talent::create(array_merge([
            'band_name' => 'Banda ' . uniqid(),
            'email' => 'banda_' . uniqid() . '@sevenrock.test',
            'password' => Hash::make('Secret123!'),
            'subscription_status' => 'active',
            'plan' => $plan,
            'is_hidden' => false,
        ], $attributes));
    }

    private function createPromoterCode(array $attributes = []): PromoterCode
    {
        return PromoterCode::create(array_merge([
            'code' => PromoterCode::generateUniqueCode(),
            'owner_name' => 'Carlos Colaborador',
            'owner_email' => 'colaborador_' . uniqid() . '@sevenrock.test',
            'owner_type' => 'conductor',
            'campaign' => 'pilot_2026',
            'max_bands' => 18,
            'is_archived' => false,
        ], $attributes));
    }

    /**
     * 1. Las bandas invitadas al Plan Gratuito conservan 15 días extra (45+15=60 días),
     * mientras que en Básico o Pro pagan precio normal sin mes adicional.
     */
    public function test_invited_band_to_free_keeps_15_bonus_days_while_basic_and_pro_stay_standard(): void
    {
        $promoter = $this->createPromoterCode();

        // A) Registro en Free con código
        $responseFree = $this->post(route('talents.register.store'), [
            'band_name' => 'Banda Free Invitada',
            'contact_name' => 'Juan',
            'email' => 'free_invitada@sevenrock.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'plan' => 'free',
            'referral_code' => $promoter->code,
        ]);
        $responseFree->assertRedirect();

        $freeBand = Talent::where('email', 'free_invitada@sevenrock.test')->firstOrFail();
        $freeSub = $freeBand->subscriptions()->latest()->first();

        // 45 días iniciales + 15 días de bono = 60 días
        $expectedEndDate = today()->addDays(Talent::FREE_DURATION_DAYS + Talent::REFERRAL_BONUS_DAYS);
        $this->assertEquals($expectedEndDate->toDateString(), $freeSub->end_date->toDateString());

        // B) Registro en Básico con código (no recibe mes extra)
        auth()->guard('talent')->logout();
        $responseBasic = $this->post(route('talents.register.store'), [
            'band_name' => 'Banda Básico Invitada',
            'contact_name' => 'Pedro',
            'email' => 'basic_invitada@sevenrock.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'plan' => 'basic',
            'referral_code' => $promoter->code,
        ]);
        $responseBasic->assertRedirect();

        $basicBand = Talent::where('email', 'basic_invitada@sevenrock.test')->firstOrFail();
        $basicSub = $basicBand->subscriptions()->latest()->first();
        // Permanece en 45 días estándar de período de prueba/registro previo a pago, sin bono de 15 días
        $this->assertEquals(today()->addDays(Talent::FREE_DURATION_DAYS)->toDateString(), $basicSub->end_date->toDateString());
    }

    /**
     * 2. Conexión del referido con el primer pago real: registro, aprobación o cortesía no computan.
     * Solo el primer pago confirmado de Básico o Pro computa; renovaciones posteriores no suman.
     */
    public function test_first_real_payment_triggers_paid_referral_while_courtesy_and_renewal_do_not(): void
    {
        $referrerBand = $this->createBand('basic');
        $promoter = $this->createPromoterCode(['assigned_talent_id' => $referrerBand->id]);

        $invitedBand = $this->createBand('basic', ['subscription_status' => 'pending']);

        // Crear referido en estado pending
        $referral = TalentReferral::create([
            'promoter_code_id' => $promoter->id,
            'referrer_talent_id' => $referrerBand->id,
            'referred_talent_id' => $invitedBand->id,
            'code' => $promoter->code,
            'status' => 'pending',
            'campaign' => 'pilot_2026',
        ]);

        $subService = app(TalentSubscriptionService::class);

        // A) Una cortesía no computa como pago
        $courtesySub = $subService->grantCourtesy($invitedBand, $this->admin, 1, 'other', 'Prueba cortesía');
        $referral->refresh();
        $this->assertEquals('pending', $referral->status);
        $this->assertNull($referral->paid_at);

        // B) Primer pago real manual confirmado con importe > 0
        $firstRealSub = $subService->confirmPaymentRenewal(
            talent: $invitedBand,
            admin: $this->admin,
            paymentReference: 'PAYPAL-REAL-001',
            notes: 'Primer pago verificado',
            paymentProvider: 'paypal'
        );

        $referral->refresh();
        $this->assertEquals('paid', $referral->status);
        $this->assertNotNull($referral->paid_at);
        $this->assertEquals($firstRealSub->id, $referral->first_paid_subscription_id);

        // C) Renovación posterior (segundo pago real) de la misma banda
        $secondSub = $subService->confirmPaymentRenewal(
            talent: $invitedBand,
            admin: $this->admin,
            paymentReference: 'PAYPAL-REAL-002',
            notes: 'Segundo pago (renovación)',
            paymentProvider: 'paypal'
        );

        // No se duplica el referido y sigue habiendo exactamente 1 pago registrado
        $this->assertEquals(1, TalentReferral::where('promoter_code_id', $promoter->id)->where('status', 'paid')->count());
    }

    /**
     * 3. Regla 3-6-9: 3 bandas distintas generan la 1ª recompensa (+1 mes Básico);
     * 6 bandas generan la 2ª (+1 mes Básico); 9 bandas respetan el tope de 2 meses.
     */
    public function test_three_and_six_bands_grant_rewards_and_nine_bands_respect_cap(): void
    {
        $referrerBand = $this->createBand('basic');
        $initialEndDate = today()->addMonthNoOverflow();
        $referrerBand->subscriptions()->create([
            'plan' => 'basic',
            'amount' => 3.50,
            'currency' => 'EUR',
            'payment_provider' => 'paypal',
            'start_date' => today(),
            'end_date' => $initialEndDate,
            'status' => 'active',
        ]);

        $promoter = $this->createPromoterCode(['assigned_talent_id' => $referrerBand->id]);
        $subService = app(TalentSubscriptionService::class);

        // Simular 3 bandas que realizan su primer pago real
        for ($i = 1; $i <= 3; $i++) {
            $invited = $this->createBand('basic');
            TalentReferral::create([
                'promoter_code_id' => $promoter->id,
                'referrer_talent_id' => $referrerBand->id,
                'referred_talent_id' => $invited->id,
                'code' => $promoter->code,
                'status' => 'pending',
                'campaign' => 'pilot_2026',
            ]);

            $subService->confirmPaymentRenewal(
                talent: $invited,
                admin: $this->admin,
                paymentReference: 'PP-PILOT-T1-' . $i,
                paymentProvider: 'paypal'
            );
        }

        // Se debe haber generado el Reward Tier 1
        $this->assertDatabaseHas('talent_referral_rewards', [
            'promoter_code_id' => $promoter->id,
            'reward_tier' => 1,
            'status' => TalentReferralReward::STATUS_GRANTED,
        ]);
        $this->assertEquals(1, TalentReferralReward::where('promoter_code_id', $promoter->id)->count());

        // La banda referidora tiene ahora 1 mes añadido
        $latestSub = $referrerBand->subscriptions()->latest('end_date')->first();
        $this->assertEquals($initialEndDate->copy()->addMonthNoOverflow()->toDateString(), $latestSub->end_date->toDateString());

        // Simular bandas 4, 5 y 6 (completando el 2º hito)
        for ($i = 4; $i <= 6; $i++) {
            $invited = $this->createBand('basic');
            TalentReferral::create([
                'promoter_code_id' => $promoter->id,
                'referrer_talent_id' => $referrerBand->id,
                'referred_talent_id' => $invited->id,
                'code' => $promoter->code,
                'status' => 'pending',
                'campaign' => 'pilot_2026',
            ]);

            $subService->confirmPaymentRenewal(
                talent: $invited,
                admin: $this->admin,
                paymentReference: 'PP-PILOT-T2-' . $i,
                paymentProvider: 'paypal'
            );
        }

        // Se debe haber generado el Reward Tier 2
        $this->assertDatabaseHas('talent_referral_rewards', [
            'promoter_code_id' => $promoter->id,
            'reward_tier' => 2,
            'status' => TalentReferralReward::STATUS_GRANTED,
        ]);
        $this->assertEquals(2, TalentReferralReward::where('promoter_code_id', $promoter->id)->count());

        // Simular bandas 7, 8 y 9
        for ($i = 7; $i <= 9; $i++) {
            $invited = $this->createBand('basic');
            TalentReferral::create([
                'promoter_code_id' => $promoter->id,
                'referrer_talent_id' => $referrerBand->id,
                'referred_talent_id' => $invited->id,
                'code' => $promoter->code,
                'status' => 'pending',
                'campaign' => 'pilot_2026',
            ]);

            $subService->confirmPaymentRenewal(
                talent: $invited,
                admin: $this->admin,
                paymentReference: 'PP-PILOT-T3-' . $i,
                paymentProvider: 'paypal'
            );
        }

        // El número de recompensas no debe superar 2 ("Máximo: dos meses de recompensa por participante")
        $this->assertEquals(2, TalentReferralReward::where('promoter_code_id', $promoter->id)->count());
        $metrics = $promoter->pilotMetrics();
        $this->assertEquals(9, $metrics['paid_count']);
        $this->assertEquals(2, $metrics['granted_rewards']);
        $this->assertEquals(0, $metrics['remaining_rewards']);
    }

    /**
     * 4. Idempotencia y protección contra reintentos duplicados.
     */
    public function test_re_evaluation_does_not_duplicate_rewards(): void
    {
        $referrerBand = $this->createBand('basic');
        $promoter = $this->createPromoterCode(['assigned_talent_id' => $referrerBand->id]);
        $subService = app(TalentSubscriptionService::class);
        $pilotService = app(TalentReferralPilotService::class);

        for ($i = 1; $i <= 3; $i++) {
            $invited = $this->createBand('basic');
            TalentReferral::create([
                'promoter_code_id' => $promoter->id,
                'referrer_talent_id' => $referrerBand->id,
                'referred_talent_id' => $invited->id,
                'code' => $promoter->code,
                'status' => 'pending',
                'campaign' => 'pilot_2026',
            ]);

            $subService->confirmPaymentRenewal(
                talent: $invited,
                admin: $this->admin,
                paymentReference: 'PP-IDEMP-' . $i,
                paymentProvider: 'paypal'
            );
        }

        $this->assertEquals(1, TalentReferralReward::where('promoter_code_id', $promoter->id)->count());

        // Reejecución explícita
        $pilotService->evaluatePromoterRewards($promoter);
        $pilotService->evaluatePromoterRewards($promoter);

        // Permanece exactamente 1 recompensa
        $this->assertEquals(1, TalentReferralReward::where('promoter_code_id', $promoter->id)->count());
    }

    /**
     * 5. Pagos anulados/reembolsados: señalan la recompensa para revisión sin deducir días automáticamente.
     */
    public function test_annulled_payment_flags_reward_for_review_without_automatic_day_reduction(): void
    {
        $referrerBand = $this->createBand('basic');
        $promoter = $this->createPromoterCode(['assigned_talent_id' => $referrerBand->id]);
        $subService = app(TalentSubscriptionService::class);

        $invitedSubs = [];
        for ($i = 1; $i <= 3; $i++) {
            $invited = $this->createBand('basic');
            TalentReferral::create([
                'promoter_code_id' => $promoter->id,
                'referrer_talent_id' => $referrerBand->id,
                'referred_talent_id' => $invited->id,
                'code' => $promoter->code,
                'status' => 'pending',
                'campaign' => 'pilot_2026',
            ]);

            $invitedSubs[$i] = $subService->confirmPaymentRenewal(
                talent: $invited,
                admin: $this->admin,
                paymentReference: 'PP-REFUND-' . $i,
                paymentProvider: 'paypal'
            );
        }

        $reward = TalentReferralReward::where('promoter_code_id', $promoter->id)->where('reward_tier', 1)->firstOrFail();
        $this->assertEquals(TalentReferralReward::STATUS_GRANTED, $reward->status);

        // Anular el pago de la banda 2
        $subService->refundSubscription($invitedSubs[2], $this->admin, 'Disputa de comprador en PayPal');

        $reward->refresh();
        $this->assertEquals(TalentReferralReward::STATUS_FLAGGED_REVIEW, $reward->status);
        $this->assertStringContainsString('[Revisión requerida', (string) $reward->notes);

        // La suscripción de la banda referidora sigue activa (no se retiraron días automáticamente)
        $this->assertEquals('active', $reward->grantedSubscription->status);
    }

    /**
     * 6. Referidor en Plan Pro: no se degrada automáticamente a Básico; la recompensa queda retenida para resolución.
     */
    public function test_pro_plan_referrer_held_pending_resolution_without_downgrade(): void
    {
        $referrerPro = $this->createBand('pro');
        $promoter = $this->createPromoterCode(['assigned_talent_id' => $referrerPro->id]);
        $subService = app(TalentSubscriptionService::class);

        for ($i = 1; $i <= 3; $i++) {
            $invited = $this->createBand('basic');
            TalentReferral::create([
                'promoter_code_id' => $promoter->id,
                'referrer_talent_id' => $referrerPro->id,
                'referred_talent_id' => $invited->id,
                'code' => $promoter->code,
                'status' => 'pending',
                'campaign' => 'pilot_2026',
            ]);

            $subService->confirmPaymentRenewal(
                talent: $invited,
                admin: $this->admin,
                paymentReference: 'PP-PRO-HOLD-' . $i,
                paymentProvider: 'paypal'
            );
        }

        $reward = TalentReferralReward::where('promoter_code_id', $promoter->id)->where('reward_tier', 1)->firstOrFail();
        $this->assertEquals(TalentReferralReward::STATUS_PENDING_PRO_RESOLUTION, $reward->status);
        $this->assertNull($reward->granted_subscription_id);

        // La banda permanece en Plan Pro
        $referrerPro->refresh();
        $this->assertEquals('pro', $referrerPro->plan);
    }

    /**
     * 7. Concesión de cortesía: 2 meses iniciales para colaboradores, acumulativos en Básico,
     * y al vencer aplican período de recuperación de 30 días sin purga de archivos.
     */
    public function test_courtesy_granting_adds_time_and_enters_recovery_on_expiry(): void
    {
        $collaboratorBand = $this->createBand('basic');
        $subService = app(TalentSubscriptionService::class);

        // Conceder 2 meses iniciales
        $courtesySub = $subService->grantCourtesy(
            talent: $collaboratorBand,
            admin: $this->admin,
            months: 2,
            courtesyType: 'initial_collaborator',
            notes: 'Cortesía colaborador Rock Nite'
        );

        $this->assertEquals('basic', $courtesySub->plan);
        $this->assertEquals(0.00, (float) $courtesySub->amount);
        $this->assertTrue($courtesySub->is_courtesy);
        $this->assertEquals('initial_collaborator', $courtesySub->courtesy_type);
        $this->assertEquals(today()->addMonthsNoOverflow(2)->toDateString(), $courtesySub->end_date->toDateString());

        // Conceder 1 mes adicional acumulativo sin perder días
        $secondCourtesy = $subService->grantCourtesy(
            talent: $collaboratorBand,
            admin: $this->admin,
            months: 1,
            courtesyType: 'other',
            notes: 'Mes extra'
        );
        $this->assertEquals(today()->addMonthsNoOverflow(3)->toDateString(), $secondCourtesy->end_date->toDateString());

        // Al vencer el período de cortesía, ExpireTalentsCommand oculta el perfil pero activa recuperación (sin purga)
        Carbon::setTestNow(today()->addMonthsNoOverflow(3)->addDay());
        $this->artisan('talents:expire')->assertSuccessful();

        $collaboratorBand->refresh();
        $this->assertTrue($collaboratorBand->is_hidden);
        $this->assertEquals('expired', $collaboratorBand->subscription_status);
        $this->assertTrue($collaboratorBand->isInRecoveryPeriod());

        Carbon::setTestNow();
    }

    /**
     * 9. Los autorreferidos (la misma banda invitándose a sí misma) no computan hacia recompensas.
     */
    public function test_self_referral_is_prevented_and_does_not_count_towards_rewards(): void
    {
        $band = $this->createBand('basic');
        $promoter = $this->createPromoterCode(['assigned_talent_id' => $band->id]);
        $subService = app(TalentSubscriptionService::class);

        // Intento de autorreferido: la banda invitada es la misma banda asignada al promotor
        TalentReferral::create([
            'promoter_code_id' => $promoter->id,
            'referrer_talent_id' => $band->id,
            'referred_talent_id' => $band->id,
            'code' => $promoter->code,
            'status' => 'pending',
            'campaign' => 'pilot_2026',
        ]);

        $subService->confirmPaymentRenewal(
            talent: $band,
            admin: $this->admin,
            paymentReference: 'PP-SELF-001',
            paymentProvider: 'paypal'
        );

        // No debe generar recompensas para sí misma
        $this->assertEquals(0, TalentReferralReward::where('promoter_code_id', $promoter->id)->count());
    }

    /**
     * 10. Convivencia con reglas históricas: un código de promotor del piloto NO dispara
     * las antiguas recompensas de 12/18 activaciones gratuitas de TalentReferralService.
     */
    public function test_pilot_participants_do_not_trigger_legacy_milestones(): void
    {
        $referrerBand = $this->createBand('free');
        $promoter = $this->createPromoterCode(['assigned_talent_id' => $referrerBand->id]);
        $referralService = app(\App\Services\TalentReferralService::class);

        // Simular 12 bandas que se registran y son aprobadas (en el sistema antiguo 12 activas daba +30 días Básico)
        for ($i = 1; $i <= 12; $i++) {
            $invited = $this->createBand('free');
            TalentReferral::create([
                'promoter_code_id' => $promoter->id,
                'referrer_talent_id' => $referrerBand->id,
                'referred_talent_id' => $invited->id,
                'code' => $promoter->code,
                'status' => 'pending',
                'campaign' => 'pilot_2026',
            ]);

            $referralService->activateReferral($invited);
        }

        // Para participantes del piloto, la banda referidora NO debe haber recibido automáticamente plan Básico
        $referrerBand->refresh();
        $this->assertEquals('free', $referrerBand->plan);
        $this->assertEquals(0, TalentReferralReward::where('promoter_code_id', $promoter->id)->count());
    }
}
