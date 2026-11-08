<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PromoterCode;
use App\Models\Talent;
use App\Models\TalentReferral;
use App\Models\TalentReferralReward;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class TalentReferralPilotService
{
    public const CAMPAIGN = 'pilot_2026';
    public const BANDS_PER_REWARD = 3;
    public const MAX_REWARDS = 2;

    /**
     * Evalúa y concede recompensas del piloto a un código de promotor tras un primer pago real confirmado.
     */
    public function evaluatePromoterRewards(?PromoterCode $promoter, ?User $admin = null): void
    {
        if (! $promoter || $promoter->is_archived) {
            return;
        }

        // Si es un locutor o colaborador sin banda asignada, solo se realiza seguimiento:
        // "Locutores sin cuenta de banda: código y seguimiento de resultados, sin recompensa económica ni meses asignados a cuentas ficticias."
        if ($promoter->assigned_talent_id === null) {
            Log::info("TalentReferralPilotService: promotor {$promoter->code} no tiene banda asignada. Registro computado para métricas sin recompensa.");
            return;
        }

        // Serializar operaciones sobre este promotor para evitar duplicidades por concurrencia
        DB::transaction(function () use ($promoter, $admin): void {
            /** @var PromoterCode $lockedPromoter */
            $lockedPromoter = PromoterCode::query()->lockForUpdate()->find($promoter->id);
            if (! $lockedPromoter || $lockedPromoter->is_archived || $lockedPromoter->assigned_talent_id === null) {
                return;
            }

            $referrerBand = Talent::query()->find($lockedPromoter->assigned_talent_id);
            if (! $referrerBand) {
                return;
            }

            $campaign = $lockedPromoter->campaign ?: self::CAMPAIGN;

            // Obtener todas las bandas referidas que han completado su primer pago real no reembolsado
            $qualifyingReferrals = TalentReferral::query()
                ->where('promoter_code_id', $lockedPromoter->id)
                ->where('status', 'paid')
                ->where('is_refunded', false)
                ->where('referred_talent_id', '!=', $referrerBand->id) // Evitar autorreferidos
                ->orderBy('paid_at')
                ->orderBy('id')
                ->get();

            $validCount = $qualifyingReferrals->count();

            // Recompensas ya existentes en esta campaña para este promotor
            $existingRewards = TalentReferralReward::query()
                ->where('promoter_code_id', $lockedPromoter->id)
                ->where('campaign', $campaign)
                ->get()
                ->keyBy('reward_tier');

            $effectiveAdmin = $admin ?? User::first() ?? new User(['id' => 1]);

            // Hito 1: al alcanzar 3 bandas con primer pago confirmado (1 mes Básico)
            if ($validCount >= 3 && ! $existingRewards->has(1)) {
                $tier1Bands = $qualifyingReferrals->slice(0, 3)->pluck('id')->values()->all();
                $this->processTierReward($lockedPromoter, $referrerBand, 1, $tier1Bands, $effectiveAdmin, $campaign);
            }

            // Hito 2: al alcanzar 6 bandas con primer pago confirmado (1 mes Básico adicional, máximo 2 meses)
            if ($validCount >= 6 && ! $existingRewards->has(2)) {
                $tier2Bands = $qualifyingReferrals->slice(3, 3)->pluck('id')->values()->all();
                $this->processTierReward($lockedPromoter, $referrerBand, 2, $tier2Bands, $effectiveAdmin, $campaign);
            }

            // A partir de 7, 8, 9... no se conceden más recompensas en este piloto ("Máximo: dos meses de recompensa por participante en esta campaña piloto")
        });
    }

    private function processTierReward(
        PromoterCode $promoter,
        Talent $referrerBand,
        int $tier,
        array $qualifyingIds,
        User $admin,
        string $campaign
    ): void {
        // Regla: "No cambies automáticamente una cuenta Pro a Básico para conceder una recompensa. Si ocurre ese caso, déjala pendiente de resolución administrativa."
        if ($referrerBand->plan === 'pro') {
            TalentReferralReward::query()->create([
                'promoter_code_id' => $promoter->id,
                'referrer_talent_id' => $referrerBand->id,
                'campaign' => $campaign,
                'reward_tier' => $tier,
                'qualifying_referral_ids' => $qualifyingIds,
                'status' => TalentReferralReward::STATUS_PENDING_PRO_RESOLUTION,
                'granted_subscription_id' => null,
                'granted_days' => 30,
                'notes' => "Recompensa Tier {$tier} ({$tier}º mes de cortesía): la banda referidora '{$referrerBand->band_name}' se encuentra en Plan Pro. Retenida pendiente de resolución administrativa sin degradación de plan.",
            ]);

            Log::info("TalentReferralPilotService: recompensa Tier {$tier} retenida para promotor {$promoter->code} porque la banda {$referrerBand->id} está en Plan Pro.");
            return;
        }

        // Conceder 1 mes de cortesía de Básico
        $subscription = app(TalentSubscriptionService::class)->grantCourtesy(
            talent: $referrerBand,
            admin: $admin,
            months: 1,
            courtesyType: 'referral_reward',
            notes: "Recompensa Tier {$tier} de campaña {$campaign} por 3 primeros pagos confirmados de bandas invitadas."
        );

        TalentReferralReward::query()->create([
            'promoter_code_id' => $promoter->id,
            'referrer_talent_id' => $referrerBand->id,
            'campaign' => $campaign,
            'reward_tier' => $tier,
            'qualifying_referral_ids' => $qualifyingIds,
            'status' => TalentReferralReward::STATUS_GRANTED,
            'granted_subscription_id' => $subscription->id,
            'granted_days' => 30,
            'notes' => "Recompensa Tier {$tier} concedida automáticamente: +1 mes de Básico por 3 primeros pagos confirmados.",
        ]);

        Log::info("TalentReferralPilotService: recompensa Tier {$tier} concedida a {$referrerBand->band_name} (Sub ID {$subscription->id}) vía promotor {$promoter->code}.");
    }

    /**
     * Señala recompensas para revisión cuando un pago referenciado es reembolsado o anulado.
     * "No retires automáticamente días ya concedidos: señala cualquier recompensa afectada para revisión."
     */
    public function handleRefundedReferral(TalentReferral $referral): void
    {
        $affectedRewards = TalentReferralReward::query()
            ->whereJsonContains('qualifying_referral_ids', $referral->id)
            ->whereIn('status', [TalentReferralReward::STATUS_GRANTED, TalentReferralReward::STATUS_PENDING_PRO_RESOLUTION])
            ->get();

        foreach ($affectedRewards as $reward) {
            $noteAdd = "\n[Revisión requerida " . now()->format('d/m/Y H:i') . "]: El pago del referido #{$referral->id} (Banda: " . ($referral->referred?->band_name ?? 'ID ' . $referral->referred_talent_id) . ") fue marcado como anulado/reembolsado.";
            $reward->update([
                'status' => TalentReferralReward::STATUS_FLAGGED_REVIEW,
                'notes' => trim($reward->notes . $noteAdd),
            ]);

            Log::warning("TalentReferralPilotService: recompensa #{$reward->id} marcada para revisión por reembolso en referido #{$referral->id}.");
        }
    }
}
