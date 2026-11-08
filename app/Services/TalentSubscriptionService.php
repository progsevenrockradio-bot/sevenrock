<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DuplicatePaymentOperationException;
use App\Models\Talent;
use App\Models\TalentSubscription;
use App\Models\User;
use App\Support\TalentPlan;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class TalentSubscriptionService
{
    /**
     * Calcula las fechas exactas que corresponden a una activación o renovación.
     * Aplica la regla aprobada de mes calendario sin desbordamiento (Carbon::addMonthNoOverflow).
     *
     * @return array{
     *     plan: string,
     *     amount: float,
     *     formatted_amount: string,
     *     is_currently_active: bool,
     *     current_end_date: Carbon|null,
     *     new_start_date: Carbon,
     *     new_end_date: Carbon
     * }
     */
    public function calculateRenewalDates(Talent $talent, ?CarbonInterface $asOf = null): array
    {
        $now = $asOf ? Carbon::instance($asOf) : today();
        $plan = (string) $talent->plan;
        $planDefinition = TalentPlan::definition($plan);
        $amount = (float) ($planDefinition['price'] ?? 0.0);
        $formattedAmount = (string) ($planDefinition['monthly_label'] ?? number_format($amount, 2) . ' €/mes');

        /** @var TalentSubscription|null $latestSubscription */
        $latestSubscription = $talent->subscriptions()
            ->whereIn('status', ['active', 'expired'])
            ->latest('end_date')
            ->first();

        $currentEndDate = $latestSubscription?->end_date ? Carbon::parse($latestSubscription->end_date)->startOfDay() : null;

        // Está vigente si el estado es active y la fecha de fin es igual o posterior a hoy (inclusive todo el día)
        $isCurrentlyActive = $talent->subscription_status === 'active'
            && $currentEndDate !== null
            && $currentEndDate->greaterThanOrEqualTo($now->copy()->startOfDay());

        if ($isCurrentlyActive && $currentEndDate !== null) {
            // Renovación anticipada: se añade un mes calendario a la fecha de vencimiento actual, sin perder días
            $newStartDate = $latestSubscription?->start_date ? Carbon::parse($latestSubscription->start_date) : $now->copy()->startOfDay();
            $newEndDate = $currentEndDate->copy()->addMonthNoOverflow();
        } else {
            // Primera activación o suscripción ya vencida: el nuevo período inicia hoy
            $newStartDate = $now->copy()->startOfDay();
            $newEndDate = $now->copy()->startOfDay()->addMonthNoOverflow();
        }

        return [
            'plan' => $plan,
            'amount' => $amount,
            'formatted_amount' => $formattedAmount,
            'is_currently_active' => $isCurrentlyActive,
            'current_end_date' => $currentEndDate,
            'new_start_date' => $newStartDate,
            'new_end_date' => $newEndDate,
        ];
    }

    /**
     * Helper de cálculo directo de fechas a partir de valores arbitrarios (ideal para pruebas y simulaciones).
     *
     * @return array{start_date: Carbon, end_date: Carbon}
     */
    public function calculateDatesFrom(
        ?CarbonInterface $currentEndDate,
        bool $isCurrentlyActive,
        ?CarbonInterface $baseDate = null
    ): array {
        $now = $baseDate ? Carbon::instance($baseDate)->startOfDay() : today();

        if ($isCurrentlyActive && $currentEndDate !== null) {
            $parsedCurrent = Carbon::instance($currentEndDate)->startOfDay();
            $newStartDate = $parsedCurrent->copy();
            $newEndDate = $parsedCurrent->copy()->addMonthNoOverflow();
        } else {
            $newStartDate = $now->copy();
            $newEndDate = $now->copy()->addMonthNoOverflow();
        }

        return [
            'start_date' => $newStartDate,
            'end_date' => $newEndDate,
        ];
    }

    /**
     * Devuelve una previsualización estructurada para el panel administrativo.
     */
    public function previewRenewal(Talent $talent, ?CarbonInterface $asOf = null): array
    {
        return $this->calculateRenewalDates($talent, $asOf);
    }

    /**
     * Confirma un pago manual y renueva la suscripción de forma atómica e idempotente.
     *
     * @throws InvalidArgumentException
     */
    public function confirmPaymentRenewal(
        Talent $talent,
        User $admin,
        ?string $paymentReference = null,
        ?string $notes = null,
        ?string $idempotencyToken = null,
        string $paymentProvider = 'paypal'
    ): TalentSubscription {
        if (! in_array($talent->plan, ['basic', 'pro'], true)) {
            throw new InvalidArgumentException("La confirmación manual de ciclo mensual solo aplica a los planes Básico y Pro. Plan actual: '{$talent->plan}'.");
        }

        $canonicalReference = filled($paymentReference) ? strtoupper(trim((string) $paymentReference)) : null;

        // Para PayPal, exige el ID de la operación recibida en la cuenta vendedora de Seven Rock Radio
        if ($paymentProvider === 'paypal' && ($canonicalReference === null || $canonicalReference === '')) {
            throw new InvalidArgumentException("Para registrar un pago de PayPal es obligatorio ingresar el ID canónico de la operación recibido en Seven Rock Radio.");
        }

        $token = filled($idempotencyToken) ? trim((string) $idempotencyToken) : null;

        return DB::transaction(function () use ($talent, $admin, $canonicalReference, $notes, $token, $paymentProvider): TalentSubscription {
            // Bloqueo pesimista para serializar solicitudes concurrentes sobre la misma banda
            /** @var Talent $lockedTalent */
            $lockedTalent = Talent::query()->lockForUpdate()->findOrFail($talent->id);

            // 1. Idempotencia pura con token: si la confirmación ya fue procesada bajo este token exacto para esta banda
            if ($token !== null) {
                $existingSub = $lockedTalent->subscriptions()
                    ->where('idempotency_token', $token)
                    ->first();

                if ($existingSub instanceof TalentSubscription) {
                    Log::info("TalentSubscriptionService: confirmación ya procesada previamente con token persistente '{$token}' para talento {$lockedTalent->id}.");
                    return $existingSub;
                }
            }

            // 2. Comprobación persistente anti-duplicados del ID de operación (PayPal u otro medio con referencia única)
            if ($canonicalReference !== null) {
                $existingOperation = TalentSubscription::query()
                    ->where('payment_reference', $canonicalReference)
                    ->with(['talent', 'confirmedBy'])
                    ->first();

                if ($existingOperation instanceof TalentSubscription) {
                    // Si es exactamente el mismo registro generado bajo este token en esta banda (idempotente)
                    if ($token !== null && $existingOperation->idempotency_token === $token && (int) $existingOperation->talent_id === (int) $lockedTalent->id) {
                        return $existingOperation;
                    }

                    // Duplicado ilegal: mismo pago con token nuevo, misma banda o presentada para otra banda
                    throw new DuplicatePaymentOperationException($canonicalReference, $existingOperation);
                }
            }

            $dates = $this->calculateRenewalDates($lockedTalent);

            // Si la suscripción previa estaba activa, se marca como expirada antes de registrar el nuevo período
            $lockedTalent->subscriptions()
                ->where('status', 'active')
                ->update(['status' => 'expired']);

            try {
                /** @var TalentSubscription $subscription */
                $subscription = $lockedTalent->subscriptions()->create([
                    'plan' => $lockedTalent->plan,
                    'amount' => $dates['amount'],
                    'currency' => 'EUR',
                    'payment_provider' => $paymentProvider,
                    'payment_id' => $canonicalReference,
                    'payment_reference' => $canonicalReference,
                    'confirmed_by_user_id' => $admin->id,
                    'confirmed_at' => now(),
                    'notes' => filled($notes) ? trim($notes) : null,
                    'idempotency_token' => $token,
                    'start_date' => $dates['new_start_date']->toDateString(),
                    'end_date' => $dates['new_end_date']->toDateString(),
                    'status' => 'active',
                ]);
            } catch (QueryException $e) {
                if ($canonicalReference !== null && (
                    str_contains($e->getMessage(), 'talent_subscriptions_payment_reference_unique')
                    || str_contains($e->getMessage(), 'Duplicate entry')
                    || str_contains($e->getMessage(), 'UNIQUE constraint failed')
                )) {
                    $existing = TalentSubscription::query()
                        ->where('payment_reference', $canonicalReference)
                        ->with(['talent', 'confirmedBy'])
                        ->first();
                    throw new DuplicatePaymentOperationException($canonicalReference, $existing);
                }
                throw $e;
            }

            $lockedTalent->update([
                'subscription_status' => 'active',
                'is_hidden' => false,
                'expires_grace_at' => null,
            ]);

            // Conexión del primer pago real con referidos del piloto
            $isFirstRealPayment = ! $lockedTalent->subscriptions()
                ->where('id', '!=', $subscription->id)
                ->where('amount', '>', 0)
                ->where('is_courtesy', false)
                ->where('is_refunded', false)
                ->whereIn('status', ['active', 'expired'])
                ->exists();

            if ($isFirstRealPayment) {
                $referral = \App\Models\TalentReferral::query()
                    ->where('referred_talent_id', $lockedTalent->id)
                    ->whereNotNull('promoter_code_id')
                    ->first();

                if ($referral && $referral->status !== 'paid' && ! $referral->is_refunded) {
                    $referral->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'first_paid_subscription_id' => $subscription->id,
                    ]);

                    app(TalentReferralPilotService::class)->evaluatePromoterRewards($referral->promoterCode, $admin);
                }
            }

            Log::info("TalentSubscriptionService: pago confirmado y suscripción renovada", [
                'talent_id' => $lockedTalent->id,
                'band_name' => $lockedTalent->band_name,
                'plan' => $lockedTalent->plan,
                'confirmed_by' => $admin->id,
                'new_end_date' => $dates['new_end_date']->toDateString(),
                'reference' => $canonicalReference,
            ]);

            return $subscription;
        });
    }

    /**
     * Concede una cortesía de Básico sin registrar cobro ni inventar identificadores de pago.
     * "Distingue «Conceder cortesía» de «Confirmar pago»: no registres ingresos ni inventes IDs de PayPal.
     * Para cuentas Básico vigentes, añade el tiempo sin perder días.
     * Al vencer una cortesía de Básico, aplica su ciclo aprobado de pausa y recuperación, sin purga automática."
     */
    public function grantCourtesy(
        Talent $talent,
        User $admin,
        int $months = 2,
        string $courtesyType = 'initial_collaborator',
        ?string $notes = null
    ): TalentSubscription {
        return DB::transaction(function () use ($talent, $admin, $months, $courtesyType, $notes): TalentSubscription {
            /** @var Talent $lockedTalent */
            $lockedTalent = Talent::query()->lockForUpdate()->findOrFail($talent->id);

            $latestSubscription = $lockedTalent->subscriptions()
                ->whereIn('status', ['active', 'expired'])
                ->latest('end_date')
                ->first();

            $now = today();
            $currentEndDate = $latestSubscription?->end_date ? Carbon::parse($latestSubscription->end_date)->startOfDay() : null;
            $isCurrentlyActive = $lockedTalent->subscription_status === 'active'
                && $currentEndDate !== null
                && $currentEndDate->greaterThanOrEqualTo($now->copy()->startOfDay());

            if ($isCurrentlyActive && $currentEndDate !== null && $lockedTalent->plan === 'basic') {
                // Cuenta Básico vigente: añade el tiempo sin perder días
                $newStartDate = $latestSubscription?->start_date ? Carbon::parse($latestSubscription->start_date) : $now->copy();
                $newEndDate = $currentEndDate->copy()->addMonthsNoOverflow($months);
            } else {
                $newStartDate = $now->copy();
                $newEndDate = $now->copy()->addMonthsNoOverflow($months);
            }

            // Expirar previas activas
            $lockedTalent->subscriptions()
                ->where('status', 'active')
                ->update(['status' => 'expired']);

            /** @var TalentSubscription $subscription */
            $subscription = $lockedTalent->subscriptions()->create([
                'plan' => 'basic',
                'amount' => 0.00,
                'currency' => 'EUR',
                'payment_provider' => 'courtesy',
                'payment_id' => null,
                'payment_reference' => null,
                'confirmed_by_user_id' => $admin->id,
                'confirmed_at' => now(),
                'notes' => filled($notes) ? trim($notes) : "Cortesía ({$months} meses) concedida por {$admin->name}",
                'idempotency_token' => null,
                'start_date' => $newStartDate->toDateString(),
                'end_date' => $newEndDate->toDateString(),
                'status' => 'active',
                'is_courtesy' => true,
                'courtesy_type' => $courtesyType,
            ]);

            $lockedTalent->update([
                'plan' => 'basic',
                'subscription_status' => 'active',
                'is_hidden' => false,
                'expires_grace_at' => null,
            ]);

            Log::info("TalentSubscriptionService: cortesía concedida ({$months} meses, tipo '{$courtesyType}') a {$lockedTalent->band_name} (id={$lockedTalent->id}) por {$admin->name}.");

            return $subscription;
        });
    }

    /**
     * Marca un pago manual como anulado o reembolsado y señala cualquier recompensa afectada para revisión.
     */
    public function refundSubscription(TalentSubscription $subscription, User $admin, ?string $reason = null): void
    {
        DB::transaction(function () use ($subscription, $admin, $reason): void {
            $subscription->update([
                'is_refunded' => true,
                'refunded_at' => now(),
                'refund_reason' => $reason,
                'refunded_by_user_id' => $admin->id,
            ]);

            // Comprobar si era el primer pago vinculado en un referido
            $referral = \App\Models\TalentReferral::query()
                ->where('first_paid_subscription_id', $subscription->id)
                ->first();

            if ($referral) {
                $referral->update([
                    'is_refunded' => true,
                    'refunded_at' => now(),
                    'refund_reason' => $reason,
                ]);

                app(TalentReferralPilotService::class)->handleRefundedReferral($referral);
            }

            Log::warning("TalentSubscriptionService: suscripción #{$subscription->id} marcada como anulada/reembolsada por {$admin->name}. Motivo: {$reason}");
        });
    }
}
