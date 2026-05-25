<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ContentApprovedMail;
use App\Models\Talent;
use App\Models\TalentMedia;
use App\Models\TalentSubscription;
use App\Services\BackblazeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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
            'band_name' => ['required', 'string', 'max:255'],
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
        $payload = [
            'plan' => $plan,
            'amount' => (float) config("payment.plans.$plan.amount", 0),
            'currency' => (string) config("payment.plans.$plan.currency", 'EUR'),
            'status' => $status === 'active' ? 'active' : ($status === 'cancelled' ? 'cancelled' : 'pending'),
            'end_date' => $status === 'active' ? today()->addMonth() : today(),
        ];

        if ($subscription) {
            $subscription->update($payload);
            return;
        }

        $talent->subscriptions()->create($payload + [
            'payment_provider' => $talent->payment_provider ?: 'manual',
            'payment_id' => null,
            'start_date' => today(),
        ]);
    }
}
