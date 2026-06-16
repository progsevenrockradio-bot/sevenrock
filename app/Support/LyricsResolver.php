<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class LyricsResolver
{
    public function resolve(string $artist, string $title): string
    {
        $artist = trim($artist);
        $title = trim($title);

        if ($artist === '' || $title === '') {
            return 'Letra no disponible';
        }

        $cacheKey = 'lyrics:v2:' . md5(mb_strtolower($artist . '|' . $title));
        
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && trim($cached) !== '') {
            return $cached;
        }

        $lock = Cache::lock($cacheKey . ':lock', 5);
        
        try {
            if ($lock->block(5)) {
                // Re-check cache after acquiring lock
                $cached = Cache::get($cacheKey);
                if (is_string($cached) && trim($cached) !== '') {
                    return $cached;
                }

                foreach ($this->artistVariants($artist) as $artistVariant) {
                    foreach ($this->titleVariants($title) as $titleVariant) {
                        $lyrics = $this->fetchFromLrclib($artistVariant, $titleVariant);
                        if ($lyrics !== '') {
                            Cache::put($cacheKey, $lyrics, now()->addHours(12));
                            return $lyrics;
                        }

                        $lyrics = $this->fetchFromLyricsOvh($artistVariant, $titleVariant);
                        if ($lyrics !== '') {
                            Cache::put($cacheKey, $lyrics, now()->addHours(12));
                            return $lyrics;
                        }
                    }
                }

                Cache::put($cacheKey, 'Letra no disponible', now()->addMinutes(3));
                return 'Letra no disponible';
            }
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            // If we couldn't get the lock, return a temporary fallback rather than hammering the API
            return 'Letra no disponible';
        } finally {
            $lock?->release();
        }

        return 'Letra no disponible';
    }

    /**
     * @return array<int, string>
     */
    private function artistVariants(string $artist): array
    {
        $artist = trim($artist);
        $variants = [$artist];

        $normalized = preg_replace('/\s*\((?:feat\.?|ft\.?|with)[^)]*\)/iu', '', $artist) ?? $artist;
        $normalized = preg_replace('/\s+(?:feat\.?|ft\.?|featuring|with)\b.*$/iu', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+and\s+/iu', ' & ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s{2,}/u', ' ', trim($normalized)) ?? $normalized;

        foreach ([$normalized, $this->asciiFriendly($artist)] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && ! in_array($candidate, $variants, true)) {
                $variants[] = $candidate;
            }
        }

        return $variants;
    }

    /**
     * @return array<int, string>
     */
    private function titleVariants(string $title): array
    {
        $title = trim($title);
        $variants = [$title];

        $stripped = preg_replace('/\s*\(([^)]*)\)\s*$/u', '', $title) ?? $title;
        $stripped = preg_replace('/\s+(?:feat\.?|ft\.?|featuring|with)\b.*$/iu', '', $stripped) ?? $stripped;
        $stripped = preg_replace('/\s*-\s*(?:radio\s+edit|remaster(?:ed)?|live|clean|explicit|version)\b.*$/iu', '', $stripped) ?? $stripped;
        $stripped = preg_replace('/\s{2,}/u', ' ', trim($stripped)) ?? $stripped;

        foreach ([$stripped, $this->asciiFriendly($title)] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && ! in_array($candidate, $variants, true)) {
                $variants[] = $candidate;
            }
        }

        return $variants;
    }

    private function asciiFriendly(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/[^\pL\pN\s&\-\']+/u', '', $value) ?? $value;
        $value = preg_replace('/\s{2,}/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function fetchFromLrclib(string $artist, string $title): string
    {
        try {
            $response = ExternalHttp::client()->retry(1, 100)
                ->connectTimeout(1)
                ->timeout(3)
                ->withHeaders(['User-Agent' => 'SevenRockRadio/1.0'])
                ->get('https://lrclib.net/api/get', [
                    'artist_name' => $artist,
                    'track_name' => $title,
                ]);

            if (! $response->successful()) {
                return '';
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                return '';
            }

            $plainLyrics = trim((string) ($payload['plainLyrics'] ?? ''));
            if ($plainLyrics === '') {
                $syncedLyrics = trim((string) ($payload['syncedLyrics'] ?? ''));
                if ($syncedLyrics !== '') {
                    $plainLyrics = preg_replace('/\[[0-9]{2}:[0-9]{2}(?:\.[0-9]{2})?\]/', '', $syncedLyrics) ?: '';
                }
            }

            return $this->normalizeLyricsText($plainLyrics);
        } catch (\Throwable) {
            return '';
        }
    }

    private function fetchFromLyricsOvh(string $artist, string $title): string
    {
        try {
            $response = ExternalHttp::client()->retry(1, 100)
                ->connectTimeout(1)
                ->timeout(3)
                ->get('https://api.lyrics.ovh/v1/' . rawurlencode($artist) . '/' . rawurlencode($title));

            if (! $response->successful()) {
                return '';
            }

            $payload = $response->json();
            if (! is_array($payload) || empty($payload['lyrics'])) {
                return '';
            }

            $lyrics = (string) $payload['lyrics'];
            $lyrics = preg_replace('/Paroles de la chanson .* par .*\r?\n/', '', $lyrics) ?: $lyrics;

            return $this->normalizeLyricsText($lyrics);
        } catch (\Throwable) {
            return '';
        }
    }

    private function normalizeLyricsText(string $lyrics): string
    {
        $lyrics = trim($lyrics);
        if ($lyrics === '') {
            return '';
        }

        $lyrics = str_replace(["\r\n", "\r"], "\n", $lyrics);
        $lyrics = preg_replace('/\n{3,}/', "\n\n", $lyrics) ?? $lyrics;
        $lyrics = preg_replace('/[ \t]+/u', ' ', $lyrics) ?? $lyrics;
        $lyrics = preg_replace('/(?:^|\n)\s*(lyrics\s+provided\s+by|powered\s+by).*/iu', '', $lyrics) ?? $lyrics;

        return trim($lyrics);
    }
}
