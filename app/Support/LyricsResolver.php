<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LyricsResolver
{
    public function resolve(string $artist, string $title): string
    {
        $artist = trim($artist);
        $title = trim($title);

        if ($artist === '' || $title === '') {
            return 'Letra no disponible';
        }

        $cacheKey = 'lyrics:v1:' . md5(mb_strtolower($artist . '|' . $title));

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($artist, $title): string {
            $lyrics = $this->fetchFromLrclib($artist, $title);
            if ($lyrics !== '') {
                return $lyrics;
            }

            $lyrics = $this->fetchFromLyricsOvh($artist, $title);
            if ($lyrics !== '') {
                return $lyrics;
            }

            return 'Letra no disponible';
        });
    }

    private function fetchFromLrclib(string $artist, string $title): string
    {
        try {
            $response = Http::retry(1, 100)
                ->connectTimeout(2)
                ->timeout(10)
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
            $response = Http::retry(1, 100)
                ->connectTimeout(2)
                ->timeout(10)
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
