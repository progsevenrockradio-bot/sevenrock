<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ContentApprovedMail;
use App\Mail\TalentApprovedMail;
use App\Models\Talent;
use App\Models\TalentMedia;
use App\Models\TalentSubscription;
use App\Services\BackblazeService;
use App\Services\TalentReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;
use Illuminate\View\View;

class TalentAdminController extends Controller
{
    public function index(Request $request): View
    {
        $query = Talent::query()->withCount(['media', 'interactions', 'subscriptions']);

        if ($plan = trim((string) $request->input('plan', ''))) {
            $query->where('plan', $plan);
        }

        if (($state = trim((string) $request->input('state', ''))) !== '') {
            if ($state === 'active') {
                $query->where('subscription_status', 'active');
            } elseif ($state === 'inactive') {
                $query->whereIn('subscription_status', ['inactive', 'cancelled']);
            }
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($inner) use ($search): void {
                $inner->where('band_name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $talents = $query->orderByDesc('is_featured')->orderByDesc('interacts')->paginate(20)->withQueryString();

        $subscriptions = TalentSubscription::query()->where('status', 'active')->get();
        $monthlyRevenue = (float) $subscriptions->sum('amount');
        $mostPopularPlan = $subscriptions
            ->groupBy('plan')
            ->map->count()
            ->sortDesc()
            ->keys()
            ->first();

        return view('admin.talents.index', [
            'talents' => $talents,
            'stats' => [
                'total' => Talent::query()->count(),
                'active' => Talent::query()->where('subscription_status', 'active')->count(),
                'inactive' => Talent::query()->whereIn('subscription_status', ['inactive', 'cancelled'])->count(),
                'featured' => Talent::query()->where('is_featured', true)->count(),
                'interactions' => (int) Talent::query()->sum('interacts'),
                'monthly_revenue' => $monthlyRevenue,
                'most_popular_plan' => $mostPopularPlan ? ucfirst($mostPopularPlan) : 'N/D',
                'storage_mb' => round((float) (TalentMedia::query()->sum('size') / 1024 / 1024), 2),
            ],
            'filters' => [
                'plan' => (string) $request->input('plan', ''),
                'state' => (string) $request->input('state', ''),
                'search' => (string) $request->input('search', ''),
            ],
        ]);
    }

    public function edit(Talent $talent): View
    {
        return view('admin.talents.edit', [
            'talent' => $talent->load(['subscriptions' => fn ($query) => $query->latest()]),
        ]);
    }

    public function update(Request $request, Talent $talent): RedirectResponse
    {
        $validated = $request->validate([
            'band_name' => ['required', 'string', 'max:255', Rule::unique('talents', 'band_name')->ignore($talent->id)],
            'bio' => ['nullable', 'string'],
            'plan' => ['required', 'in:free,basic,pro,premium'],
            'is_featured' => ['nullable', 'boolean'],
            'subscription_status' => ['required', 'in:active,inactive,cancelled'],
        ]);

        $talent->update([
            'band_name' => $validated['band_name'],
            'bio' => $validated['bio'] ?? null,
            'plan' => $validated['plan'],
            'is_featured' => $request->boolean('is_featured'),
            'subscription_status' => $validated['subscription_status'],
        ]);

        $this->syncSubscription($talent, $validated['plan'], $validated['subscription_status']);

        return redirect()->route('admin.talents.index')->with('status', 'Talento actualizado.');
    }

    public function toggleFeatured(Talent $talent): RedirectResponse
    {
        $talent->update(['is_featured' => ! $talent->is_featured]);

        return back()->with('status', $talent->is_featured ? 'Talent marked as featured.' : 'Talent unmarked as featured.');
    }

    public function suspend(Talent $talent): RedirectResponse
    {
        $talent->update(['subscription_status' => 'cancelled']);
        $talent->subscriptions()->latest()->first()?->update(['status' => 'cancelled', 'end_date' => today()]);

        return back()->with('status', 'Talento suspendido.');
    }

    public function activate(Talent $talent): RedirectResponse
    {
        $talent->update(['subscription_status' => 'active']);
        $talent->subscriptions()->latest()->first()?->update(['status' => 'active']);

        return back()->with('status', 'Talento activado.');
    }

    /**
     * Aprueba un talento registrado:
     *  - Si es FREE: crea/actualiza la suscripción con start=hoy, end=hoy+45 días.
     *  - Activa el referido (si vino con código) y evalúa hitos del referidor.
     *  - Envía email de aprobación a la banda.
     */
    public function approve(Talent $talent, TalentReferralService $referralService): RedirectResponse
    {
        $plan = $talent->plan ?: 'free';
        $durationDays = ($talent->referred_by_code && $plan === 'free')
            ? (Talent::FREE_DURATION_DAYS + Talent::REFERRAL_BONUS_DAYS)
            : Talent::FREE_DURATION_DAYS;
        $endDate = $plan === 'free'
            ? today()->addDays($durationDays)
            : today()->addMonth();

        $subscription = $talent->subscriptions()->latest()->first();

        if ($subscription) {
            $subscription->update([
                'plan'       => $plan,
                'start_date' => today(),
                'end_date'   => $endDate,
                'status'     => 'active',
            ]);
        } else {
            $talent->subscriptions()->create([
                'plan'             => $plan,
                'amount'           => (float) config("payment.plans.{$plan}.amount", 0),
                'currency'         => (string) config("payment.plans.{$plan}.currency", 'EUR'),
                'payment_provider' => 'manual',
                'payment_id'       => null,
                'start_date'       => today(),
                'end_date'         => $endDate,
                'status'           => 'active',
            ]);
        }

        $talent->update([
            'subscription_status' => 'active',
            'is_hidden'           => false,
            'expires_grace_at'    => null,
        ]);

        // Activar referido y evaluar premios del referidor
        $referralService->activateReferral($talent);

        // Enviar email de aprobación
        if (filled($talent->email)) {
            try {
                Mail::to($talent->email)->send(new TalentApprovedMail($talent->fresh()));
            } catch (Throwable $e) {
                Log::error("TalentAdminController@approve: error enviando email a {$talent->email}: " . $e->getMessage());
            }
        }

        return back()->with('status', "Talento '{$talent->band_name}' aprobado y notificado.");
    }

    public function reject(Talent $talent, ?Request $request = null): RedirectResponse
    {
        $talent->update([
            'subscription_status' => 'cancelled',
            'is_hidden'           => true,
            'rejection_reason'    => $request?->input('rejection_reason'),
        ]);

        $talent->subscriptions()->latest()->first()?->update([
            'status'   => 'cancelled',
            'end_date' => today(),
        ]);

        return back()->with('status', "Talento '{$talent->band_name}' rechazado.");
    }

    public function approveFromEmail(Request $request, Talent $talent, TalentReferralService $referralService): View
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'El enlace ha caducado o no es válido.');
        }

        $wasPending = in_array($talent->subscription_status, ['pending', 'inactive'], true);

        if ($wasPending) {
            $plan = $talent->plan ?: 'free';
            $durationDays = ($talent->referred_by_code && $plan === 'free')
                ? (Talent::FREE_DURATION_DAYS + Talent::REFERRAL_BONUS_DAYS)
                : Talent::FREE_DURATION_DAYS;
            $endDate = $plan === 'free'
                ? today()->addDays($durationDays)
                : today()->addMonth();

            $subscription = $talent->subscriptions()->latest()->first();

            if ($subscription) {
                $subscription->update([
                    'plan'       => $plan,
                    'start_date' => today(),
                    'end_date'   => $endDate,
                    'status'     => 'active',
                ]);
            } else {
                $talent->subscriptions()->create([
                    'plan'             => $plan,
                    'amount'           => (float) config("payment.plans.{$plan}.amount", 0),
                    'currency'         => (string) config("payment.plans.{$plan}.currency", 'EUR'),
                    'payment_provider' => 'manual',
                    'payment_id'       => null,
                    'start_date'       => today(),
                    'end_date'         => $endDate,
                    'status'           => 'active',
                ]);
            }

            $talent->update([
                'subscription_status' => 'active',
                'is_hidden'           => false,
                'expires_grace_at'    => null,
            ]);

            // Activar referido y evaluar premios del referidor
            $referralService->activateReferral($talent);

            // Enviar email de aprobación al talento
            if (filled($talent->email)) {
                try {
                    Mail::to($talent->email)->send(new TalentApprovedMail($talent->fresh()));
                } catch (Throwable $e) {
                    Log::error("TalentAdminController@approveFromEmail: error enviando email a {$talent->email}: " . $e->getMessage());
                }
            }
        }

        return view('admin.talents.email_action_result', [
            'talent'     => $talent->fresh(),
            'action'     => 'Banda aprobada',
            'message'    => "La banda '{$talent->band_name}' ha sido aprobada con éxito.",
            'wasPending' => $wasPending,
        ]);
    }

    public function rejectFromEmail(Request $request, Talent $talent): View
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'El enlace ha caducado o no es válido.');
        }

        $wasPending = in_array($talent->subscription_status, ['pending', 'inactive'], true);

        if ($wasPending) {
            $talent->update([
                'subscription_status' => 'cancelled',
                'is_hidden'           => true,
            ]);

            $talent->subscriptions()->latest()->first()?->update([
                'status'   => 'cancelled',
                'end_date' => today(),
            ]);
        }

        return view('admin.talents.email_action_result', [
            'talent'     => $talent->fresh(),
            'action'     => 'Banda rechazada',
            'message'    => "La solicitud de la banda '{$talent->band_name}' ha sido rechazada.",
            'wasPending' => $wasPending,
        ]);
    }

    public function media(Request $request): View
    {
        $query = TalentMedia::query()->with('talent')->latest();

        if ($type = trim((string) $request->input('type', ''))) {
            $query->where('type', $type);
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $query->whereHas('talent', function ($inner) use ($search): void {
                $inner->where('band_name', 'like', '%' . $search . '%');
            });
        }

        return view('admin.talents.media', [
            'media' => $query->paginate(25)->withQueryString(),
            'filters' => [
                'type' => (string) $request->input('type', ''),
                'search' => (string) $request->input('search', ''),
            ],
        ]);
    }

    public function deleteMedia(TalentMedia $media, BackblazeService $backblaze): RedirectResponse
    {
        $media->loadMissing('talent');

        if ($media->talent && filled($media->talent->email)) {
            Mail::to($media->talent->email)->send(new ContentApprovedMail($media));
        }

        if (filled($media->backblaze_key)) {
            try {
                $backblaze->delete($media->backblaze_key);
            } catch (\Throwable) {
                //
            }
        }

        $media->delete();

        return back()->with('status', 'Contenido eliminado.');
    }

    private function syncSubscription(Talent $talent, string $plan, string $status): void
    {
        $subscription = $talent->subscriptions()->latest()->first();

        // Para free: la vigencia es 45 días (regla contractual)
        $endDate = match (true) {
            $status !== 'active' => today(),
            $plan === 'free'     => today()->addDays(Talent::FREE_DURATION_DAYS),
            default              => today()->addMonth(),
        };

        $payload = [
            'plan'     => $plan,
            'amount'   => (float) config("payment.plans.$plan.amount", 0),
            'currency' => (string) config("payment.plans.$plan.currency", 'EUR'),
            'status'   => $status === 'active' ? 'active' : ($status === 'cancelled' ? 'cancelled' : 'pending'),
            'end_date' => $endDate,
        ];

        if ($subscription) {
            $subscription->update($payload);
            return;
        }

        $talent->subscriptions()->create($payload + [
            'payment_provider' => $talent->payment_provider ?: 'manual',
            'payment_id'       => null,
            'start_date'       => today(),
        ]);
    }
}
