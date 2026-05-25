<?php

declare(strict_types=1);

namespace App\Services\Gateways;

use App\Contracts\PaymentGateway;
use App\Models\Talent;
use App\Models\TalentSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MercadoPagoGateway implements PaymentGateway
{
    public function __construct()
    {
        if (class_exists(\MercadoPago\SDK::class)) {
            \MercadoPago\SDK::setAccessToken((string) config('mercadopago.access_token'));
        }
    }

    public function createCheckoutSession(Talent $talent, string $plan): array
    {
        if ($plan === 'free') {
            return ['url' => route('talents.dashboard'), 'provider' => 'mercadopago'];
        }

        if (! class_exists(\MercadoPago\Preference::class)) {
            return ['url' => route('talents.dashboard'), 'provider' => 'mercadopago'];
        }

        $price = (float) config("payment.plans.$plan.amount", 0);
        if ($price <= 0) {
            return ['url' => route('talents.dashboard'), 'provider' => 'mercadopago'];
        }

        $preference = new \MercadoPago\Preference();
        $item = new \MercadoPago\Item();
        $item->title = 'Seven Rock Radio - ' . ucfirst($plan);
        $item->quantity = 1;
        $item->unit_price = $price;
        $item->currency_id = (string) config("payment.plans.$plan.currency", 'EUR');

        $preference->items = [$item];
        $preference->back_urls = [
            'success' => route('talents.payment.success') . '?gateway=mercadopago',
            'failure' => route('talents.payment.cancel') . '?gateway=mercadopago',
            'pending' => route('talents.payment.success') . '?gateway=mercadopago',
        ];
        $preference->auto_return = 'approved';
        $preference->external_reference = (string) $talent->id;
        $preference->save();

        return [
            'url' => (string) ($preference->init_point ?? $preference->sandbox_init_point ?? route('talents.dashboard')),
            'payment_id' => (string) ($preference->id ?? ''),
            'provider' => 'mercadopago',
        ];
    }

    public function handleWebhook(Request $request): void
    {
        try {
            $payload = json_decode($request->getContent(), true) ?: $request->all();
            $eventType = (string) data_get($payload, 'type', data_get($payload, 'action', ''));
            $resource = (array) data_get($payload, 'data', data_get($payload, 'resource', []));
            $paymentId = (string) data_get($resource, 'id', data_get($payload, 'id', ''));
            $talentId = (int) data_get($payload, 'external_reference', data_get($resource, 'external_reference', 0));

            if ($talentId <= 0 && $paymentId !== '') {
                $subscription = TalentSubscription::query()->where('payment_id', $paymentId)->latest()->first();
                $talentId = (int) ($subscription?->talent_id ?? 0);
            }

            if ($talentId <= 0) {
                return;
            }

            $talent = Talent::query()->find($talentId);
            if (! $talent) {
                return;
            }

            if (in_array($eventType, ['payment.updated', 'subscription.cancelled', 'subscription.deleted'], true)) {
                $talent->subscriptions()->latest()->first()?->update([
                    'status' => 'cancelled',
                    'payment_provider' => 'mercadopago',
                    'payment_id' => $paymentId ?: null,
                    'end_date' => today(),
                ]);

                $talent->update(['subscription_status' => 'cancelled', 'payment_provider' => 'mercadopago']);
                return;
            }

            if (in_array($eventType, ['payment.approved', 'subscription.updated', 'subscription.approved'], true)) {
                $subscription = $talent->subscriptions()->latest()->first();
                if (! $subscription) {
                    $subscription = $talent->subscriptions()->create([
                        'plan' => (string) $talent->plan,
                        'amount' => (float) config("payment.plans.{$talent->plan}.amount", 0),
                        'currency' => (string) config("payment.plans.{$talent->plan}.currency", 'EUR'),
                        'payment_provider' => 'mercadopago',
                        'payment_id' => $paymentId ?: null,
                        'start_date' => today(),
                        'end_date' => today()->addMonth(),
                        'status' => 'pending',
                    ]);
                }

                $subscription->update([
                    'status' => 'active',
                    'payment_provider' => 'mercadopago',
                    'payment_id' => $paymentId ?: $subscription->payment_id,
                    'end_date' => today()->addMonth(),
                ]);

                $talent->update(['subscription_status' => 'active', 'payment_provider' => 'mercadopago']);
            }
        } catch (\Throwable $exception) {
            Log::error('MercadoPago webhook handling failed', [
                'message' => $exception->getMessage(),
                'payload' => $request->getContent(),
            ]);
        }
    }

    public function cancelSubscription(string $subscriptionId): bool
    {
        if ($subscriptionId === '') {
            return false;
        }

        try {
            $talentSubscription = TalentSubscription::query()->where('payment_id', $subscriptionId)->latest()->first();
            if ($talentSubscription) {
                $talentSubscription->update([
                    'status' => 'cancelled',
                    'end_date' => today(),
                ]);
                $talentSubscription->talent?->update(['subscription_status' => 'cancelled']);
            }

            return true;
        } catch (\Throwable $exception) {
            Log::error('MercadoPago subscription cancellation failed', [
                'subscription_id' => $subscriptionId,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function getName(): string
    {
        return 'mercadopago';
    }
}
