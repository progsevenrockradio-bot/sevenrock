<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BandProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BandInfoResolver
{
    /**
     * @return array{
     *     summary:string,
     *     thumbnail:string,
     *     social_links:array<int,array{label:string,url:string}>,
     *     formed_year:int|null,
     *     formed_label:string,
     *     facts:array<int,string>
     * }
     */
    public function resolve(string $artist): array
    {
        $artist = trim($artist);
        if ($artist === '') {
            return $this->emptyPayload();
        }

        return Cache::remember('band-info:v5:' . Str::slug($artist), now()->addMinutes(60), function () use ($artist): array {
            return $this->resolveLocalProfile($artist)
                ?? $this->emptyPayload($artist);
        });
    }

    /**
     * @return array{
     *     summary:string,
     *     thumbnail:string,
     *     social_links:array<int,array{label:string,url:string}>,
     *     formed_year:int|null,
     *     formed_label:string,
     *     facts:array<int,string>
     * }|null
     */
    private function resolveLocalProfile(string $artist): ?array
    {
        if (! $this->hasTable('band_profiles')) {
            return null;
        }

        $normalizedArtist = $this->normalizeKey($artist);

        $profile = BandProfile::query()
            ->get()
            ->first(function (BandProfile $candidate) use ($artist, $normalizedArtist): bool {
                if ($this->normalizeKey($candidate->name) === $normalizedArtist) {
                    return true;
                }

                foreach ((array) $candidate->related_artists as $relatedArtist) {
                    if (is_string($relatedArtist) && $this->normalizeKey($relatedArtist) === $normalizedArtist) {
                        return true;
                    }
                }

                return false;
            });

        if (! $profile) {
            return null;
        }

        $summary = trim((string) ($profile->editorial_summary ?: $profile->biography ?: ''));
        $facts = $this->normalizeFacts((array) ($profile->featured_facts ?? []));
        $formedYear = $this->extractYearFromText($summary);

        return [
            'summary' => $summary,
            'thumbnail' => (string) ($profile->normalizedImageUrl() ?? ''),
            'social_links' => $this->normalizeLocalLinks((array) ($profile->official_links ?? [])),
            'formed_year' => $formedYear,
            'formed_label' => $formedYear ? sprintf('Se formó en %d', $formedYear) : '',
            'facts' => $facts,
        ];
    }

    /**
     * @param array<int, mixed> $links
     * @return array<int, array{label:string,url:string}>
     */
    private function normalizeLocalLinks(array $links): array
    {
        return collect($links)
            ->map(function ($link): ?array {
                if (is_string($link) && trim($link) !== '') {
                    $url = $this->normalizeUrlString($link);
                    if ($url === null) {
                        return null;
                    }

                    return [
                        'label' => $this->labelForUrl($url),
                        'url' => $url,
                    ];
                }

                if (! is_array($link)) {
                    return null;
                }

                $url = trim((string) ($link['url'] ?? $link['href'] ?? ''));
                $url = $this->normalizeUrlString($url) ?? '';
                if ($url === '') {
                    return null;
                }

                $label = trim((string) ($link['label'] ?? $link['title'] ?? ''));

                return [
                    'label' => $label !== '' ? $label : $this->labelForUrl($url),
                    'url' => $url,
                ];
            })
            ->filter()
            ->unique(fn (array $link) => mb_strtolower((string) $link['url']))
            ->values()
            ->all();
    }

    private function labelForUrl(string $url): string
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?: $host;

        return match ($host) {
            'facebook.com' => 'Facebook',
            'instagram.com' => 'Instagram',
            'x.com', 'twitter.com' => 'X',
            'youtube.com', 'youtu.be' => 'YouTube',
            'soundcloud.com' => 'SoundCloud',
            'open.spotify.com' => 'Spotify',
            default => 'Web',
        };
    }

    private function normalizeUrlString(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || $url === '0' || $url === '1') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url) && str_contains($url, '.')) {
            $url = 'https://' . ltrim($url, '/');
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    private function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalizeKey(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/i', '', mb_strtolower(trim($value))) ?: '';
    }

    private function extractYearFromText(string $text): ?int
    {
        if ($text === '') {
            return null;
        }

        if (preg_match('/\b((?:18|19|20)\d{2})\b/u', $text, $matches)) {
            $year = (int) $matches[1];

            return $year > 0 ? $year : null;
        }

        return null;
    }

    /**
     * @param array<int, mixed> $facts
     * @return array<int, string>
     */
    private function normalizeFacts(array $facts): array
    {
        return collect($facts)
            ->map(function ($fact): ?string {
                if (is_string($fact)) {
                    $text = trim($fact);

                    return $text !== '' ? $text : null;
                }

                if (is_array($fact)) {
                    $text = trim((string) ($fact['label'] ?? $fact['title'] ?? $fact['value'] ?? ''));

                    return $text !== '' ? $text : null;
                }

                return null;
            })
            ->filter()
            ->unique(fn (string $fact) => mb_strtolower($fact))
            ->values()
            ->all();
    }

    /**
     * @return array{summary:string,thumbnail:string,social_links:array<int,array{label:string,url:string}>,formed_year:int|null,formed_label:string,facts:array<int,string>}
     */
    private function emptyPayload(string $artist = ''): array
    {
        return [
            'summary' => $artist !== ''
                ? sprintf(
                    'No hay información ampliada disponible para %s en este momento. Completa el perfil en el panel de bandas para mostrar biografía, año de formación, imágenes y enlaces.',
                    $artist
                )
                : 'No hay información ampliada disponible en este momento.',
            'thumbnail' => '',
            'social_links' => [],
            'formed_year' => null,
            'formed_label' => '',
            'facts' => [],
        ];
    }
}
