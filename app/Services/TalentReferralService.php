<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\TalentReferralRewardMail;
use App\Models\PromoterCode;
use App\Models\Talent;
use App\Models\TalentReferral;
use App\Models\TalentSubscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Gestiona la lógica de referidos de talentos y la aplicación de premios.
 *
 * Hitos:
 *  - 12 referidos activos  → +30 días (≈1 mes) de plan Básico gratis
 *  - 18 referidos activos  → +60 días (≈2 meses) de plan Básico gratis
 *  - Cada 3 referidos que pasan a pago → +90 días (≈3 meses) extra gratis
 */
final class TalentReferralService
{
    /**
     * Registra un referido nuevo cuando una banda se registra con un código.
     *
     * Busca si el código pertenece a un Talent (referral_code) o a un PromoterCode.
     * Crea el registro en talent_referrals con status=pending.
     */
    public function registerReferral(Talent $referred, string $code): void
    {
        if (blank($code)) {
            return;
        }

        // ¿Existe ya un referral para este referred?
        if (TalentReferral::query()->where('referred_talent_id', $referred->id)->exists()) {
            return;
        }

        $referrerId  = null;
        $promoterId  = null;

        // Buscar en referral_code de talentos
        $referrer = Talent::query()->where('referral_code', $code)->first();
        if ($referrer && $referrer->id !== $referred->id) {
            $referrerId = $referrer->id;
        }

        // Buscar en promoter_codes
        $promoter = PromoterCode::query()->where('code', $code)->where('is_archived', false)->first();
        if ($promoter) {
            $promoterId  = $promoter->id;
            $referrerId  = $referrerId ?? $promoter->assigned_talent_id;
        }

        if ($referrerId === null && $promoterId === null) {
            // Código no reconocido — lo ignoramos silenciosamente
            Log::info("TalentReferralService: código '{$code}' no reconocido para referred_talent_id={$referred->id}");
            return;
        }

        TalentReferral::query()->create([
            'referrer_talent_id' => $referrerId,
            'promoter_code_id'   => $promoterId,
            'referred_talent_id' => $referred->id,
            'code'               => $code,
            'status'             => 'pending',
            'reward_applied'     => null,
            'activated_at'       => null,
            'paid_at'            => null,
        ]);
    }

    /**
     * Activa un referido (cambiar status a "active") cuando la banda referida
     * es aprobada por el administrador.
     *
     * Después de activar, evalúa si el referidor alcanzó un nuevo hito.
     */
    public function activateReferral(Talent $referred): void
    {
        $referral = TalentReferral::query()
            ->where('referred_talent_id', $referred->id)
            ->where('status', 'pending')
            ->first();

        if (! $referral) {
            return;
        }

        $referral->update([
            'status'       => 'active',
            'activated_at' => now(),
        ]);

        if ($referral->referrer_talent_id) {
            $this->checkAndApplyMilestone(
                Talent::query()->find($referral->referrer_talent_id)
            );
        }
    }

    /**
     * Marca un referido como pagado (status=paid) y evalúa el hito de pago.
     * Llamar cuando el referido pasa a un plan de pago.
     */
    public function markReferralPaid(Talent $referred): void
    {
        $referral = TalentReferral::query()
            ->where('referred_talent_id', $referred->id)
            ->where('status', 'active')
            ->first();

        if (! $referral) {
            return;
        }

        $referral->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        if ($referral->referrer_talent_id) {
            $this->checkAndApplyPaidMilestone(
                Talent::query()->find($referral->referrer_talent_id)
            );
        }
    }

    // ──────────────────────────────────────────────
    //  Lógica de hitos
    // ──────────────────────────────────────────────

    /**
     * Evalúa si el referidor ha alcanzado el hito de 12 o 18 referidos activos.
     * Solo aplica UNA VEZ por hito (se detecta con reward_applied en el referral).
     */
    private function checkAndApplyMilestone(?Talent $referrer): void
    {
        if (! $referrer) {
            return;
        }

        $activeCount = TalentReferral::query()
            ->where('referrer_talent_id', $referrer->id)
            ->whereIn('status', ['active', 'paid'])
            ->count();

        // Hito 2: 18 activos → +60 días (solo si aún no se aplicó)
        if ($activeCount >= TalentReferral::REWARD_TIER_2_BANDS) {
            $alreadyGiven = TalentReferral::query()
                ->where('referrer_talent_id', $referrer->id)
                ->where('reward_applied', 'tier2')
                ->exists();

            if (! $alreadyGiven) {
                $this->applyBonusDays($referrer, 60, 'tier2', '2 meses gratis (Plan Básico)');
            }
            return;
        }

        // Hito 1: 12 activos → +30 días
        if ($activeCount >= TalentReferral::REWARD_TIER_1_BANDS) {
            $alreadyGiven = TalentReferral::query()
                ->where('referrer_talent_id', $referrer->id)
                ->where('reward_applied', 'tier1')
                ->exists();

            if (! $alreadyGiven) {
                $this->applyBonusDays($referrer, 30, 'tier1', '1 mes gratis (Plan Básico)');
            }
        }
    }

    /**
     * Evalúa el hito de pagos: por cada 3 referidos pagados no recompensados,
     * aplica +90 días.
     */
    private function checkAndApplyPaidMilestone(?Talent $referrer): void
    {
        if (! $referrer) {
            return;
        }

        $paidCount = TalentReferral::query()
            ->where('referrer_talent_id', $referrer->id)
            ->where('status', 'paid')
            ->count();

        $rewardedPaidCount = TalentReferral::query()
            ->where('referrer_talent_id', $referrer->id)
            ->where('reward_applied', 'paid_tier')
            ->count();

        // Cuántos ciclos de 3 se han completado sin recompensar
        $cycles = intdiv($paidCount, TalentReferral::REWARD_TIER_PAID_BANDS) - $rewardedPaidCount;

        for ($i = 0; $i < $cycles; $i++) {
            $this->applyBonusDays($referrer, 90, 'paid_tier', '3 meses extra (por 3 referidos de pago)');
        }
    }

    /**
     * Extiende el end_date de la suscripción activa del referidor.
     * Si no tiene suscripción activa, crea una nueva como premio.
     */
    private function applyBonusDays(Talent $referrer, int $days, string $rewardKey, string $rewardLabel): void
    {
        $sub = $referrer->activeSubscription();

        if ($sub) {
            $newEndDate = ($sub->end_date ?? today())->copy()->addDays($days);
            $updateData = ['end_date' => $newEndDate];
            if ($rewardKey === 'tier1' || $sub->plan === 'free') {
                $updateData['plan'] = 'basic';
                $referrer->update(['plan' => 'basic']);
            }
            $sub->update($updateData);
        } else {
            // Sin suscripción activa: crear una de regalo
            $referrer->subscriptions()->create([
                'plan'             => 'basic',
                'amount'           => 0,
                'currency'         => 'EUR',
                'payment_provider' => 'manual',
                'payment_id'       => null,
                'start_date'       => today(),
                'end_date'         => today()->addDays($days),
                'status'           => 'active',
            ]);

            $referrer->update([
                'plan'                => 'basic',
                'subscription_status' => 'active',
            ]);
        }

        // Marcar el último referido no marcado con este rewardKey
        $unmarkedReferral = TalentReferral::query()
            ->where('referrer_talent_id', $referrer->id)
            ->whereIn('status', ['active', 'paid'])
            ->whereNull('reward_applied')
            ->latest('id')
            ->first();

        if ($unmarkedReferral) {
            $unmarkedReferral->update(['reward_applied' => $rewardKey]);
        }

        // Notificar al referidor
        if (filled($referrer->email)) {
            try {
                Mail::to($referrer->email)->send(new TalentReferralRewardMail(
                    referrer: $referrer,
                    rewardLabel: $rewardLabel,
                    bonusDays: $days,
                    activeReferrals: $referrer->activeReferralsCount(),
                ));
            } catch (Throwable $e) {
                Log::error("TalentReferralService: error enviando reward email a {$referrer->email}: " . $e->getMessage());
            }
        }

        Log::info("TalentReferralService: recompensa '{$rewardKey}' aplicada ({$days} días) a {$referrer->band_name} (id={$referrer->id})");
    }
}
