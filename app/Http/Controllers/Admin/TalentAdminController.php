<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\DuplicatePaymentOperationException;
use App\Http\Controllers\Controller;
use App\Mail\ContentApprovedMail;
use App\Mail\TalentApprovedMail;
use App\Mail\TalentMaterialDeletedMail;
use App\Models\Talent;
use App\Models\TalentMedia;
use App\Models\TalentSubscription;
use App\Rules\SafeExternalHttpsUrl;
use App\Services\BackblazeService;
use App\Services\TalentReferralService;
use App\Services\TalentSubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function edit(Talent $talent, TalentSubscriptionService $subscriptionService): View
    {
        $talent->load(['subscriptions' => fn ($query) => $query->latest()]);
        $renewalPreview = $subscriptionService->previewRenewal($talent);

        return view('admin.talents.edit', [
            'talent' => $talent,
            'renewalPreview' => $renewalPreview,
            'idempotencyToken' => (string) \Illuminate\Support\Str::uuid(),
        ]);
    }

    public function confirmRenewal(Request $request, Talent $talent, TalentSubscriptionService $subscriptionService): RedirectResponse
    {
        /** @var \App\Models\User $admin */
        $admin = Auth::guard('web')->user();

        $validated = $request->validate([
            'payment_reference' => ['required', 'string', 'min:3', 'max:255'],
            'payment_provider' => ['nullable', 'string', 'in:paypal,manual'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_token' => ['nullable', 'string', 'max:100'],
        ], [
            'payment_reference.required' => 'El ID de la operación de PayPal recibido en Seven Rock Radio es obligatorio para confirmar la renovación.',
        ]);

        try {
            $subscription = $subscriptionService->confirmPaymentRenewal(
                $talent,
                $admin,
                $validated['payment_reference'],
                $validated['notes'] ?? null,
                $validated['idempotency_token'] ?? null,
                $validated['payment_provider'] ?? 'paypal'
            );

            $endDateFormatted = $subscription->end_date ? $subscription->end_date->format('d/m/Y') : 'N/D';

            return redirect()->route('admin.talents.edit', $talent)->with('status', "Pago confirmado y suscripción renovada para '{$talent->band_name}'. Servicio incluido hasta el {$endDateFormatted} inclusive (Ref: {$subscription->payment_reference}).");
        } catch (DuplicatePaymentOperationException $e) {
            return redirect()->route('admin.talents.edit', $talent)->withInput()->withErrors(['renewal' => $e->getMessage()]);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('admin.talents.edit', $talent)->withInput()->withErrors(['renewal' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error("TalentAdminController::confirmRenewal error: " . $e->getMessage());
            return redirect()->route('admin.talents.edit', $talent)->withInput()->withErrors(['renewal' => 'Ocurrió un error inesperado al registrar la renovación.']);
        }
    }

    public function grantCourtesy(Request $request, Talent $talent, TalentSubscriptionService $subscriptionService): RedirectResponse
    {
        /** @var \App\Models\User $admin */
        $admin = Auth::guard('web')->user() ?? \App\Models\User::first() ?? new \App\Models\User(['id' => 1]);

        $validated = $request->validate([
            'months' => ['required', 'integer', 'in:1,2'],
            'courtesy_type' => ['required', 'string', 'in:initial_collaborator,referral_reward,other'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $subscription = $subscriptionService->grantCourtesy(
                talent: $talent,
                admin: $admin,
                months: (int) $validated['months'],
                courtesyType: $validated['courtesy_type'],
                notes: $validated['notes'] ?? null
            );

            $endDateFormatted = $subscription->end_date ? $subscription->end_date->format('d/m/Y') : 'N/D';

            return redirect()->route('admin.talents.edit', $talent)->with('status', "Cortesía de {$validated['months']} meses (Plan Básico) concedida con éxito a '{$talent->band_name}'. Vigencia hasta el {$endDateFormatted} inclusive.");
        } catch (\Throwable $e) {
            Log::error("TalentAdminController::grantCourtesy error: " . $e->getMessage());
            return redirect()->route('admin.talents.edit', $talent)->withErrors(['courtesy' => 'Error al conceder la cortesía: ' . $e->getMessage()]);
        }
    }

    public function refundSubscription(Request $request, Talent $talent, TalentSubscription $subscription, TalentSubscriptionService $subscriptionService): RedirectResponse
    {
        /** @var \App\Models\User $admin */
        $admin = Auth::guard('web')->user() ?? \App\Models\User::first() ?? new \App\Models\User(['id' => 1]);

        $validated = $request->validate([
            'refund_reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $subscriptionService->refundSubscription($subscription, $admin, $validated['refund_reason'] ?? null);

            return redirect()->route('admin.talents.edit', $talent)->with('status', "La suscripción #{$subscription->id} fue marcada como anulada/reembolsada. Las recompensas de referidos asociadas han sido señaladas para revisión administrativa.");
        } catch (\Throwable $e) {
            Log::error("TalentAdminController::refundSubscription error: " . $e->getMessage());
            return redirect()->route('admin.talents.edit', $talent)->withErrors(['refund' => 'Error al marcar reembolso: ' . $e->getMessage()]);
        }
    }

    public function update(Request $request, Talent $talent): RedirectResponse
    {
        $validated = $request->validate([
            'band_name' => ['required', 'string', 'max:255', Rule::unique('talents', 'band_name')->ignore($talent->id)],
            'bio' => ['nullable', 'string'],
            'plan' => ['required', 'in:free,basic,pro,premium'],
            'is_featured' => ['nullable', 'boolean'],
            'subscription_status' => ['required', 'in:active,inactive,cancelled'],
            'payment_links' => ['nullable', 'array'],
            'payment_links.support' => ['nullable', 'string', 'max:2048', new SafeExternalHttpsUrl()],
            'payment_links.merch' => ['nullable', 'string', 'max:2048', new SafeExternalHttpsUrl()],
        ]);

        // Actualización segura de enlaces de pago preservando histórico
        $rawPaymentLinks = $request->input('payment_links');
        if (is_array($rawPaymentLinks)) {
            $existingPaymentLinks = is_array($talent->payment_links) ? $talent->payment_links : [];

            if (array_key_exists('support', $rawPaymentLinks)) {
                $supportVal = trim((string) ($validated['payment_links']['support'] ?? ''));
                if ($supportVal !== '') {
                    $existingPaymentLinks['support'] = $supportVal;
                } else {
                    unset($existingPaymentLinks['support'], $existingPaymentLinks['paypal']);
                }
            }

            if (array_key_exists('merch', $rawPaymentLinks)) {
                $merchVal = trim((string) ($validated['payment_links']['merch'] ?? ''));
                if ($merchVal !== '') {
                    $existingPaymentLinks['merch'] = $merchVal;
                } else {
                    unset($existingPaymentLinks['merch'], $existingPaymentLinks['store'], $existingPaymentLinks['otro'], $existingPaymentLinks['bandcamp']);
                }
            }

            $talent->payment_links = empty($existingPaymentLinks) ? null : $existingPaymentLinks;
        }

        // La edición de perfil/biografía/destacados NO debe alterar fechas de vencimiento ni renovar.
        $talent->fill([
            'band_name' => $validated['band_name'],
            'bio' => $validated['bio'] ?? null,
            'plan' => $validated['plan'],
            'is_featured' => $request->boolean('is_featured'),
            'subscription_status' => $validated['subscription_status'],
        ]);
        $talent->save();

        return redirect()->route('admin.talents.index')->with('status', 'Talento actualizado.');
    }

    public function toggleFeatured(Talent $talent): RedirectResponse
    {
        $talent->update(['is_featured' => ! $talent->is_featured]);

        return back()->with('status', $talent->is_featured ? 'Talent marked as featured.' : 'Talent unmarked as featured.');
    }

    public function suspend(Talent $talent): RedirectResponse
    {
        // La suspensión desactiva el servicio sin alterar la fecha original de fin ni confirmar pagos
        $talent->update([
            'subscription_status' => 'cancelled',
            'is_hidden' => true,
        ]);
        $talent->subscriptions()->latest()->first()?->update(['status' => 'cancelled']);

        return back()->with('status', 'Talento suspendido.');
    }

    public function activate(Talent $talent): RedirectResponse
    {
        // La reactivación administrativa solo restablece el estado activo sin extender fechas ni confirmar pago
        $talent->update([
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);
        $talent->subscriptions()->latest()->first()?->update(['status' => 'active']);

        return back()->with('status', 'Talento activado.');
    }

    /**
     * Aprueba un talento registrado:
     *  - Si es FREE: crea/actualiza la suscripción con start=hoy, end=hoy+45 días.
     *  - Si es Básico/Pro: usa mes calendario sin desbordamiento (addMonthNoOverflow).
     *  - Activa el referido (si vino con código) y evalúa hitos del referidor.
     *  - Envía email de aprobación a la banda.
     */
    public function approve(Talent $talent, TalentReferralService $referralService): RedirectResponse
    {
        $plan = $talent->plan ?: 'free';
        $durationDays = ($talent->referred_by_code && $plan === 'free')
            ? (Talent::FREE_DURATION_DAYS + Talent::REFERRAL_BONUS_DAYS)
            : Talent::FREE_DURATION_DAYS;

        // Mes calendario sin desbordamiento para planes de pago
        $endDate = $plan === 'free'
            ? today()->addDays($durationDays)
            : today()->addMonthNoOverflow();

        $subscription = $talent->subscriptions()->latest()->first();

        if ($subscription) {
            $subscription->update([
                'plan'       => $plan,
                'start_date' => today(),
                'end_date'   => $endDate,
                'status'     => 'active',
            ]);
        } else {
            $subscription = $talent->subscriptions()->create([
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

    public function approveMedia(TalentMedia $media): RedirectResponse
    {
        $media->loadMissing('talent');

        $before = $media->getOriginal();
        $media->update(['status' => 'approved']);

        if ($media->talent && filled($media->talent->email)) {
            try {
                Mail::to($media->talent->email)->send(new ContentApprovedMail($media));
            } catch (Throwable $e) {
                Log::warning('TalentAdminController::approveMedia: no se pudo enviar correo de aprobación', [
                    'media_id' => $media->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $moderationItem = \App\Models\ModerationItem::query()
            ->where('subject_type', TalentMedia::class)
            ->where('subject_id', $media->id)
            ->where('status', 'pending')
            ->first();

        if ($moderationItem) {
            $moderationItem->update([
                'status' => 'approved',
                'decided_by' => Auth::id(),
                'decided_at' => now(),
                'decision_note' => 'Aprobado desde la gestión de archivos de bandas',
            ]);
        }

        try {
            app(\App\Services\AuditTrailService::class)->recordModel(
                'talent.media.approved',
                $media,
                $before,
                $media->fresh()?->toArray() ?? [],
                [],
                ['action' => 'approve', 'admin_id' => Auth::id()],
                'info'
            );
        } catch (Throwable $e) {
            Log::warning('TalentAdminController::approveMedia: fallo de auditoría', ['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Archivo aprobado y publicado. Se ha notificado al artista.');
    }

    public function rejectMedia(TalentMedia $media): RedirectResponse
    {
        $media->loadMissing('talent');

        $before = $media->getOriginal();
        $media->update(['status' => 'rejected']);

        $moderationItem = \App\Models\ModerationItem::query()
            ->where('subject_type', TalentMedia::class)
            ->where('subject_id', $media->id)
            ->where('status', 'pending')
            ->first();

        if ($moderationItem) {
            $moderationItem->update([
                'status' => 'rejected',
                'decided_by' => Auth::id(),
                'decided_at' => now(),
                'decision_note' => 'Rechazado desde la gestión de archivos de bandas',
            ]);
        }

        try {
            app(\App\Services\AuditTrailService::class)->recordModel(
                'talent.media.rejected',
                $media,
                $before,
                $media->fresh()?->toArray() ?? [],
                [],
                ['action' => 'reject', 'admin_id' => Auth::id()],
                'warning'
            );
        } catch (Throwable $e) {
            Log::warning('TalentAdminController::rejectMedia: fallo de auditoría', ['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Archivo rechazado.');
    }

    public function deleteMedia(TalentMedia $media): RedirectResponse
    {
        $media->loadMissing('talent');

        if ($media->talent && filled($media->talent->email)) {
            try {
                Mail::to($media->talent->email)->send(new TalentMaterialDeletedMail($media->talent));
            } catch (Throwable $e) {
                Log::warning('TalentAdminController::deleteMedia: no se pudo enviar aviso por correo', [
                    'media_id' => $media->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (filled($media->backblaze_key)) {
            try {
                $targetDisk = $media->resolvedDisk() ?? 'r2';
                app(\App\Services\FileUploadService::class)->delete($media->backblaze_key, $targetDisk);
            } catch (Throwable $e) {
                Log::warning('TalentAdminController::deleteMedia: fallo al eliminar del almacenamiento en nube', [
                    'media_id' => $media->id,
                    'key' => $media->backblaze_key,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $media->delete();

        return back()->with('status', 'Contenido eliminado y artista notificado.');
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
