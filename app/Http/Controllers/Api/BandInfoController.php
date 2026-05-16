<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Song;
use App\Support\BandInfoResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BandInfoController extends Controller
{
    public function __construct(
        private readonly BandInfoResolver $resolver,
    )
    {
    }

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'artist' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $artist = trim((string) $validated['artist']);
        $title = trim((string) ($validated['title'] ?? ''));
        $payload = $this->resolver->resolve($artist);
        $lyrics = '';

        try {
            $song = Song::query()
                ->when($title !== '', fn ($query) => $query->whereRaw('LOWER(title) = ?', [mb_strtolower($title)]))
                ->whereRaw('LOWER(artist) = ?', [mb_strtolower($artist)])
                ->with('bandProfile')
                ->first();

            if ($song?->band_info && trim((string) $song->band_info) !== '') {
                $payload['summary'] = trim((string) $song->band_info);
            } elseif ($song?->bandProfile) {
                $payload['summary'] = trim((string) ($song->bandProfile->editorial_summary ?: $song->bandProfile->biography ?: $payload['summary']));
                $payload['thumbnail'] = $song->bandProfile->normalizedImageUrl() ?: $payload['thumbnail'];
                $payload['social_links'] = $song->bandProfile->official_links ?: $payload['social_links'];
            }

            if ($song?->lyrics) {
                $lyrics = trim((string) $song->lyrics);
            }
        } catch (\Throwable) {
            $song = null;
        }

        $payload['lyrics'] = $lyrics;

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }
}
