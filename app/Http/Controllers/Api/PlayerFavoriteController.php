<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlayerFavorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

final class PlayerFavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$ownerKey, $cookie] = $this->resolveOwner($request);
        $signature = trim((string) $request->query('signature', ''));

        $favorites = $this->favoritesQuery($ownerKey)
            ->orderByDesc('created_at')
            ->get(['signature', 'title', 'artist', 'cover', 'created_at'])
            ->map(static fn (PlayerFavorite $favorite): array => [
                'signature' => $favorite->signature,
                'title' => $favorite->title,
                'artist' => $favorite->artist,
                'cover' => $favorite->cover,
                'created_at' => optional($favorite->created_at)?->toISOString(),
            ])
            ->values()
            ->all();

        $currentTrackCount = $signature !== ''
            ? PlayerFavorite::query()->where('signature', $signature)->count()
            : 0;

        return response()
            ->json([
                'success' => true,
                'data' => [
                    'favorites' => $favorites,
                    'count' => count($favorites),
                    'track_count' => $currentTrackCount,
                    'is_favorite' => $signature !== ''
                        ? $this->favoritesQuery($ownerKey)->where('signature', $signature)->exists()
                        : false,
                ],
            ])
            ->cookie($cookie);
    }

    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'signature' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'artist' => ['nullable', 'string', 'max:255'],
            'cover' => ['nullable', 'string', 'max:2048'],
        ]);

        [$ownerKey, $cookie] = $this->resolveOwner($request);
        $signature = trim((string) $validated['signature']);

        $query = $this->favoritesQuery($ownerKey)->where('signature', $signature);
        $existing = $query->first();

        if ($existing) {
            $existing->delete();
            $isFavorite = false;
        } else {
            PlayerFavorite::query()->create([
                'owner_key' => $ownerKey,
                'signature' => $signature,
                'title' => trim((string) ($validated['title'] ?? '')) ?: null,
                'artist' => trim((string) ($validated['artist'] ?? '')) ?: null,
                'cover' => trim((string) ($validated['cover'] ?? '')) ?: null,
            ]);
            $isFavorite = true;
        }

        $favorites = $this->favoritesQuery($ownerKey)
            ->orderByDesc('created_at')
            ->get(['signature', 'title', 'artist', 'cover', 'created_at'])
            ->map(static fn (PlayerFavorite $favorite): array => [
                'signature' => $favorite->signature,
                'title' => $favorite->title,
                'artist' => $favorite->artist,
                'cover' => $favorite->cover,
                'created_at' => optional($favorite->created_at)?->toISOString(),
            ])
            ->values()
            ->all();
        $currentTrackCount = PlayerFavorite::query()->where('signature', $signature)->count();

        return response()
            ->json([
                'success' => true,
                'data' => [
                    'is_favorite' => $isFavorite,
                    'favorites' => $favorites,
                    'count' => count($favorites),
                    'track_count' => $currentTrackCount,
                ],
            ])
            ->cookie($cookie);
    }

    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'favorites' => ['required', 'array'],
            'favorites.*.signature' => ['required', 'string', 'max:255'],
            'favorites.*.title' => ['nullable', 'string', 'max:255'],
            'favorites.*.artist' => ['nullable', 'string', 'max:255'],
            'favorites.*.cover' => ['nullable', 'string', 'max:2048'],
        ]);

        [$ownerKey, $cookie] = $this->resolveOwner($request);

        foreach ($validated['favorites'] as $favorite) {
            $signature = trim((string) ($favorite['signature'] ?? ''));
            if ($signature === '') {
                continue;
            }

            PlayerFavorite::query()->updateOrCreate(
                [
                    'owner_key' => $ownerKey,
                    'signature' => $signature,
                ],
                [
                    'title' => trim((string) ($favorite['title'] ?? '')) ?: null,
                    'artist' => trim((string) ($favorite['artist'] ?? '')) ?: null,
                    'cover' => trim((string) ($favorite['cover'] ?? '')) ?: null,
                ]
            );
        }

        $favorites = $this->favoritesQuery($ownerKey)
            ->orderByDesc('created_at')
            ->get(['signature', 'title', 'artist', 'cover', 'created_at'])
            ->map(static fn (PlayerFavorite $favorite): array => [
                'signature' => $favorite->signature,
                'title' => $favorite->title,
                'artist' => $favorite->artist,
                'cover' => $favorite->cover,
                'created_at' => optional($favorite->created_at)?->toISOString(),
            ])
            ->values()
            ->all();

        return response()
            ->json([
                'success' => true,
                'data' => [
                    'favorites' => $favorites,
                    'count' => count($favorites),
                ],
            ])
            ->cookie($cookie);
    }

    private function resolveOwner(Request $request): array
    {
        $tokenName = 'sr_player_favorites_owner';
        $cookieValue = (string) $request->cookie($tokenName, '');

        if (trim($cookieValue) === '') {
            $cookieValue = (string) Str::uuid();
        }

        $ownerKey = Auth::check()
            ? 'user:' . (string) Auth::id()
            : 'visitor:' . $cookieValue;

        $cookie = cookie(
            $tokenName,
            $cookieValue,
            60 * 24 * 365,
            '/',
            null,
            app()->isProduction(),
            true,
            false,
            'lax'
        );

        return [$ownerKey, $cookie];
    }

    private function favoritesQuery(string $ownerKey)
    {
        return PlayerFavorite::query()->where('owner_key', $ownerKey);
    }
}
