<?php

declare(strict_types=1);

namespace App\Http\Controllers\Talent;

use App\Http\Controllers\Controller;
use App\Models\Talent;
use App\Models\TalentSubscription;
use App\Services\PaymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(private readonly PaymentManager $paymentManager)
    {
    }

    public function selectPlan(): View
    {
        return view('talentos.subscriptions.plans', [
            'plans' => config('payment.plans', []),
            'gateways' => config('payment.gateways', []),
            'currentPlan' => Auth::guard('talent')->user()?->plan ?? 'free',
        ]);
    }

    public function checkout(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'in:basic,pro,premium,free'],
            'gateway' => ['required', 'in:stripe,paypal,mercadopago'],
        ]);

        $talent = Auth::guard('talent')->user();
        if (! $talent) {
            return redirect()->route('talents.login');
        }

        if ($validated['plan'] === 'free') {
            $this->activateFreePlan($talent);

            return redirect()->route('talents.dashboard')->with('status', 'Tu plan Free ya está activo.');
        }

        $gateway = $this->paymentManager->driver($validated['gateway']);
        $result = $gateway->createCheckoutSession($talent, $validated['plan']);

        $this->syncPendingSubscription($talent, $validated['plan'], $validated['gateway'], (string) ($result['payment_id'] ?? ''));

        return redirect()->away($result['url']);
    }

    public function success(Request $request): View
    {
        return view('talentos.subscriptions.success', [
            'gateway' => (string) $request->query('gateway', ''),
            'sessionId' => (string) $request->query('session_id', ''),
        ]);
    }

    public function cancel(Request $request): View
    {
        return view('talentos.subscriptions.cancel', [
            'gateway' => (string) $request->query('gateway', ''),
        ]);
    }

    public function webhook(Request $request, string $gateway): Response
    {
        try {
            $this->paymentManager->driver($gateway)->handleWebhook($request);

            return response('OK', 200);
        } catch (\Throwable $exception) {
            Log::error("Webhook {$gateway} error", [
                'message' => $exception->getMessage(),
            ]);

            return response('Error', 400);
        }
    }

    private function activateFreePlan(Talent $talent): void
    {
        $subscription = $talent->subscriptions()->latest()->first();
        if (! $subscription) {
            $talent->subscriptions()->create([
                'plan' => 'free',
                'amount' => 0,
                'currency' => 'EUR',
                'payment_provider' => 'manual',
                'payment_id' => null,
                'start_date' => today(),
                'end_date' => today()->addMonth(),
                'status' => 'active',
            ]);
        } else {
            $subscription->update([
                'plan' => 'free',
                'amount' => 0,
                'currency' => 'EUR',
                'payment_provider' => 'manual',
                'status' => 'active',
                'start_date' => today(),
                'end_date' => today()->addMonth(),
            ]);
        }

        $talent->update([
            'plan' => 'free',
            'subscription_status' => 'active',
            'payment_provider' => 'manual',
        ]);
    }

    private function syncPendingSubscription(Talent $talent, string $plan, string $gateway, string $paymentId): void
    {
        $subscription = $talent->subscriptions()->latest()->first();

        if (! $subscription) {
            $subscription = $talent->subscriptions()->create([
                'plan' => $plan,
                'amount' => (float) config("payment.plans.$plan.amount", 0),
                'currency' => (string) config("payment.plans.$plan.currency", 'EUR'),
                'payment_provider' => $gateway,
                'payment_id' => $paymentId ?: null,
                'start_date' => today(),
                'end_date' => today()->addMonth(),
                'status' => 'pending',
            ]);
        } else {
            $subscription->update([
                'plan' => $plan,
                'amount' => (float) config("payment.plans.$plan.amount", 0),
                'currency' => (string) config("payment.plans.$plan.currency", 'EUR'),
                'payment_provider' => $gateway,
                'payment_id' => $paymentId ?: $subscription->payment_id,
                'status' => 'pending',
            ]);
        }

        $talent->update([
            'plan' => $plan,
            'subscription_status' => 'inactive',
            'payment_provider' => $gateway,
        ]);
    }
}
