<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BandProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class BandInfoAggregator
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
    public function aggregate(string $artist): array
    {
        $artist = trim($artist);
        if ($artist === '') {
            return $this->emptyPayload();
        }

        $cacheKey = 'band-info:agg:v1:' . Str::slug($artist);

        $payload = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($artist): array {
            $local = $this->localProfile($artist);
            $discogs = $this->fetchDiscogsProfile($artist);
            $wikipedia = $this->fetchWikipediaProfile($artist);
            $lastfm = $this->fetchLastFmProfile($artist);
            $musicBrainz = $this->fetchMusicBrainzProfile($artist);
            $audioDb = $this->fetchTadbProfile($artist);

            $payload = $this->buildPayload($local, $discogs, $wikipedia, $lastfm, $musicBrainz, $audioDb);

            if (! $local && $this->hasMeaningfulExternalPayload($payload)) {
                $this->persistLocalProfile($artist, $payload);
            }

            return $payload;
        });

        return is_array($payload) ? $payload : $this->emptyPayload($artist);
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
    private function localProfile(string $artist): ?array
    {
        if (! $this->hasTable('band_profiles')) {
            return null;
        }

        $normalizedArtist = $this->normalizeKey($artist);
        $profile = BandProfile::query()->get()->first(function (BandProfile $candidate) use ($normalizedArtist): bool {
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
        $formedYear = $this->extractYearFromText($summary);

        return [
            'summary' => $summary,
            'thumbnail' => (string) ($profile->normalizedImageUrl() ?? ''),
            'social_links' => $this->normalizeLocalLinks((array) ($profile->official_links ?? [])),
            'formed_year' => $formedYear,
            'formed_label' => $formedYear ? sprintf('Se formó en %d', $formedYear) : '',
            'facts' => $this->normalizeFacts((array) ($profile->featured_facts ?? [])),
        ];
    }

    private function fetchDiscogsProfile(string $artist): ?array
    {
        $token = trim((string) config('services.discogs.token', ''));
        if ($token === '') {
            return null;
        }

        try {
            $headers = [
                'User-Agent' => 'SevenRockRadio/1.0',
                'Authorization' => "Discogs token={$token}",
            ];

            $search = Http::withHeaders($headers)
                ->retry(1, 100)
                ->connectTimeout(2)
                ->timeout(4)
                ->get('https://api.discogs.com/database/search', [
                    'q' => $artist,
                    'type' => 'artist',
                    'per_page' => 1,
                ]);

            if (! $search->successful() || ! data_get($search->json(), 'results.0.id')) {
                return null;
            }

            $artistId = (string) data_get($search->json(), 'results.0.id');
            $details = Http::withHeaders($headers)
                ->retry(1, 100)
                ->connectTimeout(2)
                ->timeout(4)
                ->get("https://api.discogs.com/artists/{$artistId}");

            if (! $details->successful()) {
                return null;
            }

            $data = $details->json();
            if (! is_array($data)) {
                return null;
            }

            return [
                'summary' => $this->cleanText((string) ($data['profile'] ?? '')),
                'thumbnail' => (string) data_get($data, 'images.0.uri', ''),
                'facts' => array_values(array_filter([
                    $this->cleanText((string) ($data['realname'] ?? '')),
                    $this->cleanText((string) ($data['profile'] ?? '')),
                ])),
                'social_links' => $this->normalizeLinks((array) ($data['urls'] ?? [])),
                'formed_year' => $this->positiveInt($data['members'][0]['active_year_range']['start'] ?? null),
                'formed_label' => '',
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function fetchWikipediaProfile(string $artist): ?array
    {
        try {
            $search = Http::retry(1, 100)
                ->connectTimeout(2)
                ->timeout(4)
                ->get('https://es.wikipedia.org/w/api.php', [
                    'action' => 'opensearch',
                    'search' => $artist,
                    'limit' => 1,
                    'namespace' => 0,
                    'format' => 'json',
                ]);

            if (! $search->successful()) {
                return null;
            }

            $title = (string) data_get($search->json(), '1.0', '');
            if ($title === '') {
                return null;
            }

            $summary = Http::retry(1, 100)
                ->connectTimeout(2)
                ->timeout(4)
                ->get('https://es.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode($title));

            if (! $summary->successful()) {
                return null;
            }

            $data = $summary->json();
            if (! is_array($data)) {
                return null;
            }

            $extract = $this->cleanText((string) ($data['extract'] ?? ''));

            return [
                'summary' => $extract,
                'thumbnail' => (string) data_get($data, 'thumbnail.source', ''),
                'facts' => array_values(array_filter([
                    $this->cleanText((string) ($data['description'] ?? '')),
                    $this->cleanText((string) data_get($data, 'content_urls.desktop.page', '')),
                ])),
                'social_links' => $this->normalizeLinks(array_filter([
                    (string) data_get($data, 'content_urls.desktop.page', ''),
                ])),
                'formed_year' => $this->extractYearFromText($extract),
                'formed_label' => '',
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function fetchLastFmProfile(string $artist): ?array
    {
        $apiKey = trim((string) (config('services.lastfm.key') ?: config('services.lastfm.api_key', '')));
        if ($apiKey === '') {
            return null;
        }

        try {
            $response = Http::retry(1, 100)
                ->connectTimeout(2)
                ->timeout(4)
                ->get('https://ws.audioscrobbler.com/2.0/', [
                    'method' => 'artist.getinfo',
                    'artist' => $artist,
                    'api_key' => $apiKey,
                    'format' => 'json',
                ]);

            if (! $response->successful() || ! data_get($response->json(), 'artist')) {
                return null;
            }

            $data = (array) $response->json('artist');
            $bio = $this->cleanText((string) data_get($data, 'bio.summary', ''));

            return [
                'summary' => $bio,
                'thumbnail' => (string) data_get($data, 'image.3.#text', data_get($data, 'image.2.#text', '')),
                'facts' => array_values(array_filter([
                    $this->cleanText((string) data_get($data, 'bio.content', '')),
                    $this->cleanText((string) data_get($data, 'tags.tag.0.name', '')),
                ])),
                'social_links' => $this->normalizeLinks(array_filter([
                    (string) data_get($data, 'url', ''),
                ])),
                'formed_year' => $this->extractYearFromText($bio),
                'formed_label' => '',
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function fetchMusicBrainzProfile(string $artist): ?array
    {
        try {
            $search = Http::withHeaders(['User-Agent' => 'SevenRockRadio/1.0 (metadata)'])
                ->retry(1, 100)
                ->connectTimeout(2)
                ->timeout(4)
                ->get('https://musicbrainz.org/ws/2/artist/', [
                    'query' => 'artist:"' . $artist . '"',
                    'fmt' => 'json',
                    'limit' => 1,
                ]);

            if (! $search->successful() || ! data_get($search->json(), 'artists.0.id')) {
                return null;
            }

            $id = (string) data_get($search->json(), 'artists.0.id');
            $details = Http::withHeaders(['User-Agent' => 'SevenRockRadio/1.0 (metadata)'])
                ->retry(1, 100)
                ->connectTimeout(2)
                ->timeout(4)
                ->get("https://musicbrainz.org/ws/2/artist/{$id}", [
                    'fmt' => 'json',
                    'inc' => 'url-rels',
                ]);

            if (! $details->successful()) {
                return null;
            }

            $data = (array) $details->json();

            return [
                'summary' => $this->cleanText((string) ($data['disambiguation'] ?? $data['type'] ?? '')),
                'thumbnail' => '',
                'facts' => [],
                'social_links' => $this->normalizeLinks(
                    collect($data['relations'] ?? [])
                        ->filter(fn ($relation) => in_array((string) ($relation['type'] ?? ''), ['official homepage', 'social network'], true))
                        ->map(fn ($relation) => (string) data_get($relation, 'url.resource', ''))
                        ->filter()
                        ->values()
                        ->all()
                ),
                'formed_year' => null,
                'formed_label' => '',
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function fetchTadbProfile(string $artist): ?array
    {
        try {
            $response = Http::retry(1, 100)
                ->connectTimeout(2)
                ->timeout(4)
                ->get('https://www.theaudiodb.com/api/v1/json/2/search.php', [
                    's' => $artist,
                ]);

            if (! $response->successful() || ! data_get($response->json(), 'artists.0')) {
                return null;
            }

            $data = (array) $response->json('artists.0');

            return [
                'summary' => $this->cleanText((string) ($data['strBiographyEN'] ?? '')),
                'thumbnail' => (string) ($data['strArtistThumb'] ?? ''),
                'facts' => array_values(array_filter([
                    $this->cleanText((string) ($data['strGenre'] ?? '')),
                    $this->cleanText((string) ($data['strStyle'] ?? '')),
                    $this->cleanText((string) ($data['strMood'] ?? '')),
                ])),
                'social_links' => $this->normalizeLinks(array_filter([
                    $data['strWebsite'] ?? null,
                    $data['strFacebook'] ?? null,
                    $data['strTwitter'] ?? null,
                    $data['strInstagram'] ?? null,
                    $data['strYoutube'] ?? null,
                ])),
                'formed_year' => $this->positiveInt($data['intFormedYear'] ?? null),
                'formed_label' => '',
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array{summary?:string,thumbnail?:string,social_links?:array<int,array{label:string,url:string}>,formed_year?:int|null,formed_label?:string,facts?:array<int,string>}|null $local
     * @param array{summary?:string,thumbnail?:string,social_links?:array<int,array{label:string,url:string}>,formed_year?:int|null,formed_label?:string,facts?:array<int,string>}|null $discogs
     * @param array{summary?:string,thumbnail?:string,social_links?:array<int,array{label:string,url:string}>,formed_year?:int|null,formed_label?:string,facts?:array<int,string>}|null $wikipedia
     * @param array{summary?:string,thumbnail?:string,social_links?:array<int,array{label:string,url:string}>,formed_year?:int|null,formed_label?:string,facts?:array<int,string>}|null $lastfm
     * @param array{summary?:string,thumbnail?:string,social_links?:array<int,array{label:string,url:string}>,formed_year?:int|null,formed_label?:string,facts?:array<int,string>}|null $musicBrainz
     * @param array{summary?:string,thumbnail?:string,social_links?:array<int,array{label:string,url:string}>,formed_year?:int|null,formed_label?:string,facts?:array<int,string>}|null $audioDb
     * @return array{
     *     summary:string,
     *     thumbnail:string,
     *     social_links:array<int,array{label:string,url:string}>,
     *     formed_year:int|null,
     *     formed_label:string,
     *     facts:array<int,string>
     * }
     */
    private function buildPayload(
        ?array $local,
        ?array $discogs,
        ?array $wikipedia,
        ?array $lastfm,
        ?array $musicBrainz,
        ?array $audioDb,
    ): array {
        $summary = $this->firstFilledString([
            $local['summary'] ?? '',
            $discogs['summary'] ?? '',
            $wikipedia['summary'] ?? '',
            $lastfm['summary'] ?? '',
            $audioDb['summary'] ?? '',
            $musicBrainz['summary'] ?? '',
        ]);

        $thumbnail = $this->firstFilledString([
            $local['thumbnail'] ?? '',
            $discogs['thumbnail'] ?? '',
            $wikipedia['thumbnail'] ?? '',
            $lastfm['thumbnail'] ?? '',
            $audioDb['thumbnail'] ?? '',
        ]);

        $socialLinks = collect([
            ...(array) ($local['social_links'] ?? []),
            ...(array) ($discogs['social_links'] ?? []),
            ...(array) ($wikipedia['social_links'] ?? []),
            ...(array) ($lastfm['social_links'] ?? []),
            ...(array) ($musicBrainz['social_links'] ?? []),
            ...(array) ($audioDb['social_links'] ?? []),
        ])->filter()->values()->all();

        $facts = $this->normalizeFacts(array_merge(
            (array) ($local['facts'] ?? []),
            (array) ($discogs['facts'] ?? []),
            (array) ($wikipedia['facts'] ?? []),
            (array) ($lastfm['facts'] ?? []),
            (array) ($musicBrainz['facts'] ?? []),
            (array) ($audioDb['facts'] ?? []),
        ));

        $formedYear = $this->firstPositiveInteger([
            (array) ($local['formed_year'] ?? null),
            [$discogs['formed_year'] ?? null],
            [$wikipedia['formed_year'] ?? null],
            [$lastfm['formed_year'] ?? null],
            [$musicBrainz['formed_year'] ?? null],
            [$audioDb['formed_year'] ?? null],
        ]);

        return [
            'summary' => $summary,
            'thumbnail' => $thumbnail,
            'social_links' => $this->normalizeLinks($socialLinks),
            'formed_year' => $formedYear,
            'formed_label' => $formedYear ? sprintf('Se formó en %d', $formedYear) : '',
            'facts' => $facts,
        ];
    }

    private function persistLocalProfile(string $artist, array $payload): void
    {
        if (! $this->hasTable('band_profiles')) {
            return;
        }

        try {
            BandProfile::query()->updateOrCreate(
                ['name' => $artist],
                [
                    'biography' => $payload['summary'] ?? '',
                    'editorial_summary' => $payload['summary'] ?? '',
                    'image_path' => $payload['thumbnail'] ?? '',
                    'featured_facts' => $payload['facts'] ?? [],
                    'official_links' => $payload['social_links'] ?? [],
                    'last_verified_at' => now(),
                    'source' => 'Seven Rock Radio',
                ]
            );
        } catch (Throwable) {
            // keep warmup failures silent
        }
    }

    private function hasMeaningfulExternalPayload(array $payload): bool
    {
        return trim((string) ($payload['summary'] ?? '')) !== ''
            || trim((string) ($payload['thumbnail'] ?? '')) !== ''
            || ! empty($payload['facts'])
            || ! empty($payload['social_links']);
    }

    private function normalizeLinks(array $links): array
    {
        return collect($links)
            ->map(function ($link): ?array {
                if (! is_string($link)) {
                    return null;
                }

                $url = $this->normalizeUrlString($link);
                if ($url === null) {
                    return null;
                }

                return [
                    'label' => $this->labelForUrl($url),
                    'url' => $url,
                ];
            })
            ->filter()
            ->unique(fn (array $link) => mb_strtolower((string) $link['url']))
            ->values()
            ->all();
    }

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

    private function cleanText(string $text): string
    {
        $text = trim($text);
        $text = strip_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function normalizeFacts(array $facts): array
    {
        return collect($facts)
            ->map(function ($fact): ?string {
                if (is_string($fact)) {
                    $text = $this->cleanText($fact);

                    return $text !== '' ? $text : null;
                }

                if (is_array($fact)) {
                    $text = $this->cleanText((string) ($fact['label'] ?? $fact['title'] ?? $fact['value'] ?? ''));

                    return $text !== '' ? $text : null;
                }

                return null;
            })
            ->filter()
            ->unique(fn (string $fact) => mb_strtolower($fact))
            ->values()
            ->all();
    }

    private function extractYearFromText(string $text): ?int
    {
        if ($text === '') {
            return null;
        }

        if (preg_match('/\b((?:18|19|20)\d{2})\b/u', $text, $matches)) {
            return $this->positiveInt($matches[1]);
        }

        return null;
    }

    private function firstFilledString(array $values): string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function firstPositiveInteger(array $groups): ?int
    {
        foreach ($groups as $group) {
            foreach ((array) $group as $value) {
                $int = $this->positiveInt($value);
                if ($int !== null) {
                    return $int;
                }
            }
        }

        return null;
    }

    private function positiveInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    private function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    private function normalizeKey(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/i', '', mb_strtolower(trim($value))) ?: '';
    }

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
