<?php

declare(strict_types=1);

namespace App\Services\Gateways;

use App\Contracts\PaymentGateway;
use App\Models\Talent;
use App\Models\TalentSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeGateway implements PaymentGateway
{
    public function __construct()
    {
        if (class_exists(\Stripe\Stripe::class)) {
            \Stripe\Stripe::setApiKey((string) config('stripe.secret'));
        }
    }

    public function createCheckoutSession(Talent $talent, string $plan): array
    {
        if ($plan === 'free') {
            return ['url' => route('talents.dashboard'), 'provider' => 'stripe'];
        }

        $priceId = config("stripe.prices.$plan");
        if (! $priceId) {
            return ['url' => route('talents.dashboard'), 'provider' => 'stripe'];
        }

        if (! filled($talent->payment_customer_id) && class_exists(\Stripe\Customer::class)) {
            $customer = \Stripe\Customer::create([
                'email' => $talent->user->email,
                'name' => $talent->band_name,
                'metadata' => ['talent_id' => $talent->id],
            ]);

            $talent->update([
                'payment_customer_id' => $customer->id,
                'payment_provider' => 'stripe',
            ]);
        }

        $session = class_exists(\Stripe\Checkout\Session::class)
            ? \Stripe\Checkout\Session::create([
                'customer' => $talent->payment_customer_id,
                'mode' => 'subscription',
                'line_items' => [['price' => $priceId, 'quantity' => 1]],
                'success_url' => route('talents.payment.success') . '?gateway=stripe&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('talents.payment.cancel') . '?gateway=stripe',
                'metadata' => [
                    'talent_id' => (string) $talent->id,
                    'plan' => $plan,
                    'gateway' => 'stripe',
                ],
            ])
            : null;

        return [
            'url' => $session?->url ?: route('talents.dashboard'),
            'payment_id' => $session?->id,
            'provider' => 'stripe',
        ];
    }

    public function handleWebhook(Request $request): void
    {
        if (! class_exists(\Stripe\Webhook::class)) {
            return;
        }

        try {
            $payload = $request->getContent();
            $sigHeader = $request->header('Stripe-Signature');
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                (string) config('stripe.webhook_secret')
            );
        } catch (\Throwable $exception) {
            Log::error('Stripe webhook verification failed', [
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;
                $this->syncByMetadata((array) $session->metadata, (string) ($session->subscription ?? ''));
                break;
            case 'invoice.paid':
                $invoice = $event->data->object;
                $this->syncByCustomer((string) ($invoice->customer ?? ''), 'active', (string) ($invoice->subscription ?? ''));
                break;
            case 'customer.subscription.updated':
                $subscription = $event->data->object;
                $this->syncByCustomer((string) ($subscription->customer ?? ''), $this->mapStripeStatus((string) ($subscription->status ?? 'active')), (string) ($subscription->id ?? ''));
                break;
            case 'customer.subscription.deleted':
                $subscription = $event->data->object;
                $this->syncByCustomer((string) ($subscription->customer ?? ''), 'cancelled', (string) ($subscription->id ?? ''));
                break;
        }
    }

    public function cancelSubscription(string $subscriptionId): bool
    {
        if (! class_exists(\Stripe\Subscription::class) || $subscriptionId === '') {
            return false;
        }

        try {
            $subscription = \Stripe\Subscription::retrieve($subscriptionId);
            $subscription->cancel();

            $talentSubscription = TalentSubscription::query()->where('payment_id', $subscriptionId)->latest()->first();
            if ($talentSubscription) {
                $talentSubscription->update([
                    'status' => 'cancelled',
                    'end_date' => today(),
                ]);
                $talentSubscription->talent?->update([
                    'subscription_status' => 'cancelled',
                ]);
            }

            return true;
        } catch (\Throwable $exception) {
            Log::error('Stripe subscription cancellation failed', [
                'subscription_id' => $subscriptionId,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function getName(): string
    {
        return 'stripe';
    }

    private function syncByMetadata(array $metadata, string $subscriptionId): void
    {
        $talentId = (int) ($metadata['talent_id'] ?? 0);
        $plan = (string) ($metadata['plan'] ?? 'free');
        if ($talentId <= 0) {
            return;
        }

        $talent = Talent::query()->find($talentId);
        if (! $talent) {
            return;
        }

        $this->activateSubscription($talent, $plan, 'stripe', $subscriptionId);
    }

    private function syncByCustomer(string $customerId, string $status, string $paymentId): void
    {
        if ($customerId === '') {
            return;
        }

        $talent = Talent::query()->where('payment_customer_id', $customerId)->first();
        if (! $talent) {
            return;
        }

        if ($status === 'cancelled') {
            $this->markSubscriptionCancelled($talent, 'stripe', $paymentId);
            return;
        }

        $this->activateSubscription($talent, (string) $talent->plan, 'stripe', $paymentId);
    }

    private function activateSubscription(Talent $talent, string $plan, string $provider, string $paymentId): void
    {
        $subscription = $talent->subscriptions()->latest()->first();
        if (! $subscription) {
            $subscription = $talent->subscriptions()->create([
                'plan' => $plan,
                'amount' => (float) config("payment.plans.$plan.amount", 0),
                'currency' => (string) config("payment.plans.$plan.currency", 'EUR'),
                'payment_provider' => $provider,
                'payment_id' => $paymentId ?: null,
                'start_date' => today(),
                'end_date' => today()->addMonth(),
                'status' => 'pending',
            ]);
        }

        $subscription->update([
            'status' => 'active',
            'payment_provider' => $provider,
            'payment_id' => $paymentId ?: $subscription->payment_id,
            'start_date' => $subscription->start_date ?: today(),
            'end_date' => today()->addMonth(),
        ]);

        $talent->update([
            'subscription_status' => 'active',
            'payment_provider' => $provider,
        ]);
    }

    private function markSubscriptionCancelled(Talent $talent, string $provider, string $paymentId): void
    {
        $subscription = $talent->subscriptions()->latest()->first();
        if ($subscription) {
            $subscription->update([
                'status' => 'cancelled',
                'payment_provider' => $provider,
                'payment_id' => $paymentId ?: $subscription->payment_id,
                'end_date' => today(),
            ]);
        }

        $talent->update([
            'subscription_status' => 'cancelled',
            'payment_provider' => $provider,
        ]);
    }

    private function mapStripeStatus(string $status): string
    {
        return match ($status) {
            'active', 'trialing' => 'active',
            'canceled', 'incomplete_expired' => 'cancelled',
            default => 'pending',
        };
    }
}
