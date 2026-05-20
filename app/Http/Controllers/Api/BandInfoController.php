<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BandProfile;
use App\Models\Song;
use App\Support\BandProfileMatcher;
use App\Support\BandInfoResolver;
use App\Support\LyricsResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class BandInfoController extends Controller
{
    public function __construct(
        private readonly BandInfoResolver $resolver,
        private readonly LyricsResolver $lyricsResolver,
        private readonly BandProfileMatcher $matcher,
    )
    {
    }

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'artist' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $artist = trim((string) $validated['artist']);
        $title = trim((string) ($validated['title'] ?? ''));
        [$artist, $title] = $this->normalizeTrackContext($artist, $title);
        if ($artist === '' && $title === '') {
            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => 'No hay información ampliada disponible en este momento.',
                    'thumbnail' => '',
                    'social_links' => [],
                    'formed_year' => null,
                    'formed_label' => '',
                    'facts' => [],
                    'lyrics' => '',
                ],
            ]);
        }

        $payload = $this->resolver->resolve($artist);
        $lyrics = '';
        $bandProfile = null;

        try {
            $song = $this->resolveSong($artist, $title);
            if ($song && $artist === '') {
                $artist = trim((string) $song->artist);
            }
            $songMatchesArtist = $song ? $this->songArtistMatchesLookup($song, $artist) : false;

            if ($songMatchesArtist && $song?->band_info && trim((string) $song->band_info) !== '' && ! $this->isFallbackSummary((string) $song->band_info, $artist)) {
                $payload['summary'] = $this->formatSummaryText((string) $song->band_info);
            } elseif ($songMatchesArtist && $song?->bandProfile) {
                $bandProfile = $song->bandProfile;
                $payload['summary'] = $this->formatSummaryText((string) ($song->bandProfile->editorial_summary ?: $song->bandProfile->biography ?: $payload['summary']));
                $payload['thumbnail'] = $song->bandProfile->normalizedImageUrl() ?: $payload['thumbnail'];
                $payload['social_links'] = $song->bandProfile->official_links ?: $payload['social_links'];
            } else {
                $bandProfile = $this->resolveBandProfile($artist);
                if ($bandProfile) {
                    $payload['summary'] = $this->formatSummaryText((string) ($bandProfile->editorial_summary ?: $bandProfile->biography ?: $payload['summary']));
                    $payload['thumbnail'] = $bandProfile->normalizedImageUrl() ?: $payload['thumbnail'];
                    $payload['social_links'] = $bandProfile->official_links ?: $payload['social_links'];
                    $payload['formed_year'] = $payload['formed_year'] ?: $this->yearFromBandProfile($bandProfile);
                    $payload['formed_label'] = $payload['formed_label'] ?: ($payload['formed_year'] ? sprintf('Se formó en %d', $payload['formed_year']) : '');
                }
            }

            if ($songMatchesArtist && $song?->lyrics) {
                $lyrics = trim((string) $song->lyrics);
            } elseif ($artist !== '' && $title !== '') {
                $lyrics = trim((string) $this->lyricsResolver->resolve($artist, $title));
            }

            $this->persistResolvedMetadata($song, $bandProfile, $payload, $lyrics);
        } catch (\Throwable) {
            $song = null;
        }

        $payload['lyrics'] = $lyrics;

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    private function resolveBandProfile(string $artist): ?BandProfile
    {
        $artist = trim($artist);
        if ($artist === '') {
            return null;
        }

        return $this->matcher->exactMatch($artist)
            ?? $this->matcher->fuzzyMatch($artist);
    }

    private function resolveSong(string $artist, string $title): ?Song
    {
        if (! $this->hasTable('songs')) {
            return null;
        }

        $title = trim($title);
        $artist = trim($artist);

        $query = Song::query()->with('bandProfile');

        if ($title !== '' && $artist !== '') {
            $song = (clone $query)
                ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])
                ->whereRaw('LOWER(artist) = ?', [mb_strtolower($artist)])
                ->first();

            if ($song) {
                return $song;
            }
        }

        if ($title !== '') {
            $song = (clone $query)
                ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])
                ->first();

            if ($song) {
                return $song;
            }
        }

        if ($artist !== '') {
            $song = (clone $query)
                ->whereRaw('LOWER(artist) = ?', [mb_strtolower($artist)])
                ->first();

            if ($song) {
                return $song;
            }
        }

        if ($title !== '') {
            return (clone $query)
                ->whereRaw('LOWER(title) LIKE ?', ['%' . mb_strtolower($title) . '%'])
                ->orderByRaw('CHAR_LENGTH(title) ASC')
                ->first();
        }

        if ($artist !== '') {
            return (clone $query)
                ->whereRaw('LOWER(artist) LIKE ?', ['%' . mb_strtolower($artist) . '%'])
                ->orderByRaw('CHAR_LENGTH(artist) ASC')
                ->first();
        }

        return null;
    }

    private function songArtistMatchesLookup(Song $song, string $artist): bool
    {
        $artist = trim($artist);
        if ($artist === '') {
            return true;
        }

        $songArtist = $this->normalizeContextValue((string) $song->artist);
        $lookupArtist = $this->normalizeContextValue($artist);

        if ($songArtist === '' || $lookupArtist === '') {
            return false;
        }

        return $songArtist === $lookupArtist
            || str_contains($songArtist, $lookupArtist)
            || str_contains($lookupArtist, $songArtist);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function normalizeTrackContext(string $artist, string $title): array
    {
        $artist = $this->normalizeTrackArtist($artist);
        $title = $this->normalizeTrackTitle($title);

        if ($this->isPlaceholderArtist($artist)) {
            $parsed = $this->parseCompoundTrackTitle($title);
            if ($parsed !== null) {
                return [$parsed['artist'], $parsed['title']];
            }
        }

        return [$artist, $title];
    }

    private function isPlaceholderArtist(string $artist): bool
    {
        $normalized = $this->normalizeContextValue($artist);
        if ($normalized === '') {
            return true;
        }

        foreach (['transmision en vivo', 'senal al aire', 'seven rock radio', 'rock', 'live'] as $marker) {
            if ($normalized === $marker || str_contains($normalized, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeTrackArtist(string $artist): string
    {
        $artist = trim($artist);
        if ($artist === '') {
            return '';
        }

        $artist = preg_replace('/^\s*(?:remasterizado|remastered)\s*\d{0,4}\s*[-:]\s*/iu', '', $artist) ?? $artist;
        $artist = preg_replace('/\s*\((?:feat\.?|ft\.?|with)[^)]+\)\s*$/iu', '', $artist) ?? $artist;
        $artist = preg_replace('/\s+(?:feat\.?|ft\.?|featuring|with)\b.*$/iu', '', $artist) ?? $artist;
        $artist = preg_replace('/\s{2,}/u', ' ', $artist) ?? $artist;

        return trim($artist);
    }

    private function normalizeTrackTitle(string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            return '';
        }

        $title = preg_replace('/\s*\((?:feat\.?|ft\.?|with)[^)]+\)\s*$/iu', '', $title) ?? $title;
        $title = preg_replace('/\s+(?:feat\.?|ft\.?|featuring|with)\b.*$/iu', '', $title) ?? $title;
        $title = preg_replace('/\s{2,}/u', ' ', $title) ?? $title;

        return trim($title);
    }

    private function parseCompoundTrackTitle(string $title): ?array
    {
        if (! str_contains($title, ' - ')) {
            return null;
        }

        $parts = array_values(array_filter(array_map('trim', explode(' - ', $title)), fn (string $part): bool => $part !== ''));
        if (count($parts) < 2) {
            return null;
        }

        $artist = (string) array_pop($parts);
        $trackTitle = trim(implode(' - ', $parts));

        if ($artist === '' || $trackTitle === '') {
            return null;
        }

        return [
            'artist' => $artist,
            'title' => $trackTitle,
        ];
    }

    private function normalizeContextValue(string $value): string
    {
        $normalized = \Illuminate\Support\Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9\s:]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim();

        return (string) $normalized;
    }

    private function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    private function persistResolvedMetadata(?Song $song, ?BandProfile $bandProfile, array $payload, string $lyrics): void
    {
        if (! $song) {
            return;
        }

        $updates = [];

        if ($bandProfile && ! $song->band_profile_id) {
            $updates['band_profile_id'] = $bandProfile->id;
        }

        $summary = $this->formatSummaryText((string) ($payload['summary'] ?? ''));
        if ($summary !== '' && trim((string) $song->band_info) === '' && ! $this->isFallbackSummary($summary, (string) $song->artist)) {
            $updates['band_info'] = $summary;
        }

        if ($lyrics !== '' && $lyrics !== 'Letra no disponible' && trim((string) $song->lyrics) === '') {
            $updates['lyrics'] = $lyrics;
        }

        if ($updates !== []) {
            Song::query()->whereKey($song->getKey())->update($updates);
        }
    }

    private function yearFromBandProfile(BandProfile $bandProfile): ?int
    {
        $summary = trim((string) ($bandProfile->editorial_summary ?: $bandProfile->biography ?: ''));
        if ($summary !== '' && preg_match('/\b((?:18|19|20)\d{2})\b/u', $summary, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
    private function isFallbackSummary(string $summary, string $artist = ''): bool
    {
        $summary = trim($summary);
        if ($summary === '') {
            return true;
        }

        if (str_starts_with($summary, 'No hay información ampliada disponible')) {
            return true;
        }

        if ($artist !== '' && str_contains(mb_strtolower($summary), mb_strtolower($artist))) {
            return false;
        }

        return false;
    }

    private function formatSummaryText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim(strip_tags($text));
        $text = preg_replace('/\[(?:[A-Za-z]{1,3}\d+|\d+)\]/u', '', $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
        $text = preg_replace('/\b(Contexto de catalogo|Contexto de catálogo|Miembros relacionados|Alias \/ variaciones)\b/iu', "\n\n$1", $text) ?? $text;
        $text = preg_replace('/\n\s*\n\s*\n+/u', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
