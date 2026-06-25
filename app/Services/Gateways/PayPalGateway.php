<?php

declare(strict_types=1);

namespace App\Services\Gateways;

use App\Contracts\PaymentGateway;
use App\Models\Talent;
use App\Models\TalentSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalGateway implements PaymentGateway
{
    private function getAccessToken(): ?string
    {
        $clientId = config('paypal.client_id');
        $secret = config('paypal.secret');
        $mode = config('paypal.mode', 'sandbox');
        $baseUrl = $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';

        if (!$clientId || !$secret) {
            return null;
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($clientId, $secret)
                ->post("{$baseUrl}/v1/oauth2/token", [
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->successful()) {
                return (string) $response->json('access_token');
            }
        } catch (\Throwable $e) {
            Log::error('PayPal getAccessToken failed', ['message' => $e->getMessage()]);
        }

        return null;
    }

    public function createCheckoutSession(Talent $talent, string $plan): array
    {
        if ($plan === 'free') {
            return ['url' => route('talents.dashboard'), 'provider' => 'paypal'];
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return ['url' => route('talents.dashboard'), 'provider' => 'paypal'];
        }

        $price = (float) config("payment.plans.$plan.amount", 0);
        if ($price <= 0) {
            return ['url' => route('talents.dashboard'), 'provider' => 'paypal'];
        }

        $mode = config('paypal.mode', 'sandbox');
        $baseUrl = $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
        $currency = (string) config("payment.plans.$plan.currency", 'EUR');
        $startDate = now()->addDay()->toIso8601String();
        $planName = 'Seven Rock Radio - ' . ucfirst($plan) . ' plan';

        try {
            // 1. Create Billing Plan
            $planResponse = Http::withToken($token)
                ->post("{$baseUrl}/v1/payments/billing-plans", [
                    'name' => $planName,
                    'description' => 'Suscripción mensual ' . $plan,
                    'type' => 'INFINITE',
                    'payment_definitions' => [
                        [
                            'name' => 'Monthly Payments',
                            'type' => 'REGULAR',
                            'frequency' => 'Month',
                            'frequency_interval' => '1',
                            'amount' => [
                                'value' => number_format($price, 2, '.', ''),
                                'currency' => $currency,
                            ],
                            'cycles' => '0',
                        ],
                    ],
                    'merchant_preferences' => [
                        'return_url' => route('talents.payment.success') . '?gateway=paypal',
                        'cancel_url' => route('talents.payment.cancel') . '?gateway=paypal',
                        'auto_bill_amount' => 'yes',
                        'initial_fail_amount_action' => 'CONTINUE',
                        'max_fail_attempts' => '1',
                        'setup_fee' => [
                            'value' => number_format($price, 2, '.', ''),
                            'currency' => $currency,
                        ],
                    ],
                ]);

            if (!$planResponse->successful()) {
                Log::error('PayPal Billing Plan creation failed', [
                    'status' => $planResponse->status(),
                    'body' => $planResponse->body(),
                ]);
                return ['url' => route('talents.dashboard'), 'provider' => 'paypal'];
            }

            $createdPlanId = $planResponse->json('id');

            // 2. Activate the Billing Plan
            $activateResponse = Http::withToken($token)
                ->patch("{$baseUrl}/v1/payments/billing-plans/{$createdPlanId}", [
                    [
                        'op' => 'replace',
                        'path' => '/',
                        'value' => [
                            'state' => 'ACTIVE',
                        ],
                    ],
                ]);

            if (!$activateResponse->successful()) {
                Log::error('PayPal Billing Plan activation failed', [
                    'status' => $activateResponse->status(),
                    'body' => $activateResponse->body(),
                ]);
                return ['url' => route('talents.dashboard'), 'provider' => 'paypal'];
            }

            // 3. Create Billing Agreement
            $agreementResponse = Http::withToken($token)
                ->post("{$baseUrl}/v1/payments/billing-agreements", [
                    'name' => $planName,
                    'description' => 'Suscripción Seven Rock Radio',
                    'start_date' => $startDate,
                    'plan' => [
                        'id' => $createdPlanId,
                    ],
                    'payer' => [
                        'payment_method' => 'paypal',
                    ],
                ]);

            if (!$agreementResponse->successful()) {
                Log::error('PayPal Billing Agreement creation failed', [
                    'status' => $agreementResponse->status(),
                    'body' => $agreementResponse->body(),
                ]);
                return ['url' => route('talents.dashboard'), 'provider' => 'paypal'];
            }

            $agreementData = $agreementResponse->json();
            $approvalUrl = '';
            foreach ($agreementData['links'] ?? [] as $link) {
                if ($link['rel'] === 'approval_url') {
                    $approvalUrl = $link['href'];
                    break;
                }
            }

            return [
                'url' => $approvalUrl ?: route('talents.dashboard'),
                'payment_id' => (string) ($agreementData['id'] ?? ''),
                'provider' => 'paypal',
            ];
        } catch (\Throwable $exception) {
            Log::error('PayPal checkout creation failed', [
                'talent_id' => $talent->id,
                'plan' => $plan,
                'message' => $exception->getMessage(),
            ]);

            return ['url' => route('talents.dashboard'), 'provider' => 'paypal'];
        }
    }

    public function handleWebhook(Request $request): void
    {
        try {
            $payload = json_decode($request->getContent(), true) ?: [];
            $eventType = (string) data_get($payload, 'event_type', data_get($payload, 'eventType', ''));

            $resource = (array) data_get($payload, 'resource', []);
            $subscriptionId = (string) data_get($resource, 'billing_agreement_id', data_get($resource, 'id', ''));
            $talentId = (int) data_get($resource, 'custom', data_get($resource, 'custom_id', 0));

            if ($talentId <= 0 && $subscriptionId !== '') {
                $subscription = TalentSubscription::query()->where('payment_id', $subscriptionId)->latest()->first();
                $talentId = (int) ($subscription?->talent_id ?? 0);
            }

            if ($talentId <= 0) {
                return;
            }

            $talent = Talent::query()->find($talentId);
            if (! $talent) {
                return;
            }

            if (in_array($eventType, ['BILLING.SUBSCRIPTION.CANCELLED', 'PAYMENT.SALE.REFUNDED'], true)) {
                $talent->subscriptions()->latest()->first()?->update([
                    'status' => 'cancelled',
                    'payment_provider' => 'paypal',
                    'payment_id' => $subscriptionId ?: null,
                    'end_date' => today(),
                ]);

                $talent->update(['subscription_status' => 'cancelled', 'payment_provider' => 'paypal']);
                return;
            }

            if (in_array($eventType, ['PAYMENT.SALE.COMPLETED', 'BILLING.SUBSCRIPTION.ACTIVATED', 'BILLING.SUBSCRIPTION.UPDATED'], true)) {
                $subscription = $talent->subscriptions()->latest()->first();
                if (! $subscription) {
                    $subscription = $talent->subscriptions()->create([
                        'plan' => (string) $talent->plan,
                        'amount' => (float) config("payment.plans.{$talent->plan}.amount", 0),
                        'currency' => (string) config("payment.plans.{$talent->plan}.currency", 'EUR'),
                        'payment_provider' => 'paypal',
                        'payment_id' => $subscriptionId ?: null,
                        'start_date' => today(),
                        'end_date' => today()->addMonth(),
                        'status' => 'pending',
                    ]);
                }

                $subscription->update([
                    'status' => 'active',
                    'payment_provider' => 'paypal',
                    'payment_id' => $subscriptionId ?: $subscription->payment_id,
                    'end_date' => today()->addMonth(),
                ]);

                $talent->update(['subscription_status' => 'active', 'payment_provider' => 'paypal']);
            }
        } catch (\Throwable $exception) {
            Log::error('PayPal webhook handling failed', [
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

        $token = $this->getAccessToken();
        if (!$token) {
            return false;
        }

        $mode = config('paypal.mode', 'sandbox');
        $baseUrl = $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';

        try {
            $response = Http::withToken($token)
                ->post("{$baseUrl}/v1/payments/billing-agreements/{$subscriptionId}/cancel", [
                    'note' => 'Cancelación solicitada por el talento.',
                ]);

            if ($response->successful()) {
                $talentSubscription = TalentSubscription::query()->where('payment_id', $subscriptionId)->latest()->first();
                if ($talentSubscription) {
                    $talentSubscription->update([
                        'status' => 'cancelled',
                        'end_date' => today(),
                    ]);
                    $talentSubscription->talent?->update(['subscription_status' => 'cancelled']);
                }
                return true;
            }

            Log::error('PayPal subscription cancellation request failed', [
                'subscription_id' => $subscriptionId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        } catch (\Throwable $exception) {
            Log::error('PayPal subscription cancellation failed', [
                'subscription_id' => $subscriptionId,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function getName(): string
    {
        return 'paypal';
    }
}
