<?php

declare(strict_types=1);

namespace App\Http\Controllers\Talent;

use App\Http\Controllers\Controller;
use App\Services\BackblazeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('talentos.profile', [
            'talent' => Auth::guard('talent')->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $talent = Auth::guard('talent')->user();
        if (! $talent) {
            return redirect()->route('talents.login');
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'band_name' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'logo' => ['nullable', 'image', 'max:4096'],
            'instagram_url' => ['nullable', 'url', 'max:2048'],
            'youtube_url' => ['nullable', 'url', 'max:2048'],
            'tiktok_url' => ['nullable', 'url', 'max:2048'],
            'spotify_url' => ['nullable', 'url', 'max:2048'],
            'website_url' => ['nullable', 'url', 'max:2048'],
        ]);

        try {
            if ($request->hasFile('logo')) {
                $backblaze = app(BackblazeService::class);
                if (filled($talent->logo)) {
                    try {
                        $backblaze->delete($talent->logo);
                    } catch (\Throwable) {
                        //
                    }
                }

                $upload = $backblaze->upload($request->file('logo'), "talents/{$talent->id}/logo");
                $talent->logo = $upload['key'] ?: null;
            }

            $talent->band_name = trim((string) ($validated['name'] ?? $validated['band_name'] ?? $talent->band_name));
            $talent->bio = $validated['bio'] ?? null;
            $talent->instagram_url = $validated['instagram_url'] ?? null;
            $talent->youtube_url = $validated['youtube_url'] ?? null;
            $talent->tiktok_url = $validated['tiktok_url'] ?? null;
            $talent->spotify_url = $validated['spotify_url'] ?? null;
            $talent->website_url = $validated['website_url'] ?? null;
            $talent->social_links = array_filter([
                'instagram' => $talent->instagram_url,
                'youtube' => $talent->youtube_url,
                'tiktok' => $talent->tiktok_url,
                'spotify' => $talent->spotify_url,
                'website' => $talent->website_url,
            ], static fn ($value): bool => filled($value));
            $talent->save();
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors([
                'logo' => 'No se pudo actualizar el perfil.',
            ]);
        }

        return redirect()->route('talents.profile')->with('status', 'Profile updated.');
    }
}
