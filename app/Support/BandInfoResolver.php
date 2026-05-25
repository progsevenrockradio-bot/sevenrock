<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\RadioArtist;
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

        $local = $this->resolveLocalProfile($artist);
        if ($local !== null) {
            return $local;
        }

        $payload = app(BandInfoAggregator::class)->aggregate($artist);
        $payload = is_array($payload) ? $payload : $this->emptyPayload($artist);

        return $payload;
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
        $matcher = app(BandProfileMatcher::class);
        $profile = $matcher->exactMatch($artist);

        if (! $profile) {
            return null;
        }

        return $this->buildProfilePayload($profile);
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
    private function buildProfilePayload(RadioArtist $profile): ?array
    {
        $summary = $this->formatSummaryText((string) ($profile->editorial_summary ?: $profile->biography ?: ''));
        $thumbnail = (string) ($profile->normalizedImageUrl() ?? '');
        $socialLinks = $this->normalizeLocalLinks((array) ($profile->official_links ?? []));
        $facts = $this->normalizeFacts((array) ($profile->featured_facts ?? []));
        $formedYear = $this->extractYearFromText($summary);

        if (! $this->hasMeaningfulPayload([
            'summary' => $summary,
            'thumbnail' => $thumbnail,
            'social_links' => $socialLinks,
            'formed_year' => $formedYear,
            'facts' => $facts,
        ])) {
            return null;
        }

        return [
            'summary' => $summary,
            'thumbnail' => $thumbnail,
            'social_links' => $socialLinks,
            'formed_year' => $formedYear,
            'formed_label' => $formedYear ? sprintf('Se formó en %d', $formedYear) : '',
            'facts' => $facts,
        ];
    }

    private function formatSummaryText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = strip_tags($text);
        $text = preg_replace('/\[(?:[A-Za-z]{1,3}\d+|\d+)\]/u', '', $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
        $text = preg_replace('/\b(Contexto de catalogo|Contexto de catálogo|Miembros relacionados|Alias \/ variaciones)\b/iu', "\n\n$1", $text) ?? $text;
        $text = preg_replace('/\n\s*\n\s*\n+/u', "\n\n", $text) ?? $text;

        return trim($text);
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

    /**
     * @param array{summary?:string,thumbnail?:string,social_links?:array<int,array{label:string,url:string}>,formed_year?:int|null,formed_label?:string,facts?:array<int,string>} $payload
     */
    private function hasMeaningfulPayload(array $payload): bool
    {
        return trim((string) ($payload['summary'] ?? '')) !== ''
            || trim((string) ($payload['thumbnail'] ?? '')) !== ''
            || ! empty($payload['social_links'])
            || ! empty($payload['facts'])
            || ! empty($payload['formed_year']);
    }
}
