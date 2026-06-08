<?php

declare(strict_types=1);

namespace App\Http\Controllers\Talent;

use App\Http\Controllers\Controller;
use App\Mail\NewInteractionMail;
use App\Models\Talent;
use App\Models\TalentInteraction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class PublicProfileController extends Controller
{
    public function index(Request $request): View
    {
        $query = Talent::query()->where('subscription_status', 'active');

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where('band_name', 'like', '%' . $search . '%');
        }

        if ($plan = trim((string) $request->input('plan', ''))) {
            $query->where('plan', $plan);
        }

        return view('talentos.public.index', [
            'talents' => $query
                ->orderByDesc('is_featured')
                ->orderByDesc('interacts')
                ->paginate(12)
                ->withQueryString(),
            'selectedPlan' => (string) $request->input('plan', ''),
            'search' => (string) $request->input('search', ''),
            'plans' => config('payment.plans', []),
        ]);
    }

    public function show(string $bandName): View
    {
        $talent = Talent::query()
            ->where('band_name', urldecode($bandName))
            ->firstOrFail();

        $talent->increment('interacts');

        TalentInteraction::query()->create([
            'talent_id' => $talent->id,
            'visitor_ip' => (string) request()->ip(),
            'type' => 'view',
        ]);

        $hasLiked = $talent->interactions()
            ->where('visitor_ip', (string) request()->ip())
            ->where('type', 'like')
            ->where('created_at', '>', now()->subDay())
            ->exists();

        return view('talentos.public.profile', [
            'talent' => $talent,
            'media' => $talent->media()->latest()->get(),
            'products' => $talent->products()->published()->latest()->get(),
            'likesCount' => $talent->interactions()->where('type', 'like')->count(),
            'viewsCount' => $talent->interactions()->where('type', 'view')->count(),
            'hasLiked' => $hasLiked,
            'topComments' => $talent->interactions()
                ->where('type', 'comment')
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function like(string $bandName): JsonResponse
    {
        $talent = Talent::query()
            ->where('band_name', urldecode($bandName))
            ->firstOrFail();

        $recent = $talent->interactions()
            ->where('visitor_ip', (string) request()->ip())
            ->where('type', 'like')
            ->where('created_at', '>', now()->subDay())
            ->exists();

        if ($recent) {
            return response()->json(['error' => 'Ya has dado like hoy'], 429);
        }

        TalentInteraction::query()->create([
            'talent_id' => $talent->id,
            'visitor_ip' => (string) request()->ip(),
            'type' => 'like',
        ]);

        $talent->increment('interacts');

        if (filled($talent->email) && $talent->notificationPreferenceEnabled('likes')) {
            Mail::to($talent->email)->queue(new NewInteractionMail($talent, 'like', (string) request()->ip()));
        }

        return response()->json([
            'likes' => $talent->interactions()->where('type', 'like')->count(),
        ]);
    }

    public function comment(Request $request, string $bandName): RedirectResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:500'],
        ]);

        $talent = Talent::query()
            ->where('band_name', urldecode($bandName))
            ->firstOrFail();

        TalentInteraction::query()->create([
            'talent_id' => $talent->id,
            'visitor_ip' => (string) $request->ip(),
            'type' => 'comment',
            'content' => strip_tags((string) $validated['content']),
        ]);

        $talent->increment('interacts');

        if (filled($talent->email) && $talent->notificationPreferenceEnabled('comments')) {
            Mail::to($talent->email)->queue(new NewInteractionMail($talent, 'comment', (string) $request->ip()));
        }

        return back()->with('success', 'Comentario publicado');
    }
}
