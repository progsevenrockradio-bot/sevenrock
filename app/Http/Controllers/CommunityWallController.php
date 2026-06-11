<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CommunityPost;
use App\Models\Talent;
use App\Models\TalentMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CommunityWallController extends Controller
{
    public function muro(): View
    {
        $posts = CommunityPost::query()
            ->with(['user', 'talent'])
            ->latest()
            ->paginate(20);

        // Fetch recommended/featured bands for the sidebar
        $featuredBands = Talent::query()
            ->where('subscription_status', 'active')
            ->orderByDesc('is_featured')
            ->orderByDesc('interacts')
            ->limit(5)
            ->get();

        return view('comunidad.muro', [
            'posts' => $posts,
            'featuredBands' => $featuredBands,
        ]);
    }

    public function post(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:500'],
            'youtube_url' => ['nullable', 'url', 'max:255'],
        ]);

        $userId = Auth::guard('web')->id();
        $talentId = Auth::guard('talent')->id();

        if (!$userId && !$talentId) {
            abort(403, 'Debes iniciar sesión para publicar.');
        }

        CommunityPost::query()->create([
            'user_id' => $userId,
            'talent_id' => $talentId,
            'content' => strip_tags((string) $validated['content']),
            'youtube_url' => $validated['youtube_url'] ?: null,
        ]);

        return back()->with('status', '¡Mensaje publicado en el Muro!');
    }

    public function exclusivos(): View
    {
        $exclusiveMedia = TalentMedia::query()
            ->where('is_exclusive', true)
            ->with('talent')
            ->latest()
            ->paginate(12);

        return view('comunidad.exclusivos', [
            'media' => $exclusiveMedia,
        ]);
    }
}
