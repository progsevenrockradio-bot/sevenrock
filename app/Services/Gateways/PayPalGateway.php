<?php

declare(strict_types=1);

namespace App\Services\Gateways;

use App\Contracts\PaymentGateway;
use App\Models\Talent;
use App\Models\TalentSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayPalGateway implements PaymentGateway
{
    private $apiContext;

    public function __construct()
    {
        if (class_exists(\PayPal\Rest\ApiContext::class)) {
            $this->apiContext = new \PayPal\Rest\ApiContext(
                new \PayPal\Auth\OAuthTokenCredential(
                    (string) config('paypal.client_id'),
                    (string) config('paypal.secret')
                )
            );
            $this->apiContext->setConfig(['mode' => config('paypal.mode')]);
        }
    }

    public function createCheckoutSession(Talent $talent, string $plan): array
    {
        if ($plan === 'free') {
            return ['url' => route('talents.dashboard'), 'provider' => 'paypal'];
        }

        if (! class_exists(\PayPal\Api\Agreement::class)) {
            return ['url' => route('talents.dashboard'), 'provider' => 'paypal'];
        }

        $price = (float) config("payment.plans.$plan.amount", 0);
        if ($price <= 0) {
            return ['url' => route('talents.dashboard'), 'provider' => 'paypal'];
        }

        $startDate = now()->addDay()->toIso8601String();
        $planName = 'Seven Rock Radio - ' . ucfirst($plan) . ' plan';

        $payer = new \PayPal\Api\Payer();
        $payer->setPaymentMethod('paypal');

        $amount = new \PayPal\Api\Currency();
        $amount->setCurrency((string) config("payment.plans.$plan.currency", 'EUR'));
        $amount->setValue(number_format($price, 2, '.', ''));

        $paymentDefinition = new \PayPal\Api\PaymentDefinition();
        $paymentDefinition->setName('Monthly Payments')
            ->setType('REGULAR')
            ->setFrequency('Month')
            ->setFrequencyInterval('1')
            ->setCycles('0')
            ->setAmount($amount);

        $merchantPreferences = new \PayPal\Api\MerchantPreferences();
        $merchantPreferences->setReturnUrl(route('talents.payment.success') . '?gateway=paypal')
            ->setCancelUrl(route('talents.payment.cancel') . '?gateway=paypal')
            ->setAutoBillAmount('yes')
            ->setInitialFailAmountAction('CONTINUE')
            ->setMaxFailAttempts('1')
            ->setSetupFee($amount);

        $planObject = new \PayPal\Api\Plan();
        $planObject->setName($planName)
            ->setDescription('Suscripción mensual ' . $plan)
            ->setType('INFINITE')
            ->setPaymentDefinitions([$paymentDefinition])
            ->setMerchantPreferences($merchantPreferences);

        try {
            $createdPlan = $planObject->create($this->apiContext);
            $agreement = new \PayPal\Api\Agreement();
            $agreement->setName($planName)
                ->setDescription('Suscripción Seven Rock Radio')
                ->setStartDate($startDate)
                ->setPlan($createdPlan)
                ->setPayer($payer);

            $agreement = $agreement->create($this->apiContext);

            return [
                'url' => (string) $agreement->getApprovalLink(),
                'payment_id' => (string) $agreement->getId(),
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
        if ($subscriptionId === '' || ! class_exists(\PayPal\Api\Agreement::class)) {
            return false;
        }

        try {
            $agreement = \PayPal\Api\Agreement::get($subscriptionId, $this->apiContext);
            $descriptor = new \PayPal\Api\AgreementStateDescriptor();
            $descriptor->setNote('Cancelación solicitada por el talento.');
            $agreement->cancel($descriptor, $this->apiContext);

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
