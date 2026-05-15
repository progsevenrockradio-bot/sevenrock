<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BandProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

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

        return Cache::remember('band-info:v4:' . Str::slug($artist), now()->addMinutes(30), function () use ($artist): array {
            $local = $this->resolveLocalProfile($artist);

            $discogs = $this->fetchDiscogsProfile($artist);
            $wikipedia = $this->fetchWikipediaProfile($artist);
            $lastfm = $this->fetchLastFmProfile($artist);
            $tadb = $this->fetchTadbProfile($artist);
            $musicBrainz = $this->fetchMusicBrainzProfile($artist);

            $summary = trim(implode("\n\n", array_filter([
                $local['summary'] ?? '',
                $discogs['extract'] ?? '',
                $wikipedia['extract'] ?? '',
                $lastfm['extract'] ?? '',
                $tadb['extract'] ?? '',
                $musicBrainz['extract'] ?? '',
            ])));

            $socialLinks = array_merge(
                $this->normalizeLocalLinks($local['social_links'] ?? []),
                $this->normalizeLinks($discogs['social_links'] ?? []),
                $this->normalizeLinks($wikipedia['social_links'] ?? []),
                $this->normalizeLinks($lastfm['social_links'] ?? []),
                $this->normalizeLinks($tadb['social_links'] ?? []),
                $this->normalizeLinks($musicBrainz['social_links'] ?? [])
            );

            $thumbnail = (string) (
                ($local['thumbnail'] ?? '') ??
                $discogs['thumbnail'] ??
                $wikipedia['thumbnail'] ??
                $lastfm['thumbnail'] ??
                $tadb['thumbnail'] ??
                ''
            );
            $formedYear = $this->firstPositiveInteger([
                $local['formed_year'] ?? null,
                $tadb['formed_year'] ?? null,
                $wikipedia['formed_year'] ?? null,
                $this->extractYearFromText((string) ($discogs['extract'] ?? '')),
                $this->extractYearFromText((string) ($wikipedia['extract'] ?? '')),
                $this->extractYearFromText((string) ($lastfm['extract'] ?? '')),
                $this->extractYearFromText((string) ($musicBrainz['extract'] ?? '')),
            ]);
            $formedLabel = $formedYear ? sprintf('Se formó en %d', $formedYear) : '';
            $facts = array_values(array_filter([
                $formedLabel,
                $this->firstFilledString([
                    (string) ($tadb['genre_label'] ?? ''),
                    (string) ($wikipedia['description'] ?? ''),
                    (string) ($musicBrainz['extract'] ?? ''),
                ]),
            ]));

            if ($summary === '' && empty($socialLinks) && $thumbnail === '') {
                return [
                    'summary' => sprintf('No hay información ampliada disponible para %s en este momento.', $artist),
                    'thumbnail' => '',
                    'social_links' => [],
                    'formed_year' => $formedYear,
                    'formed_label' => $formedLabel,
                    'facts' => $facts,
                ];
            }

            $payload = [
                'summary' => $summary,
                'thumbnail' => $thumbnail,
                'social_links' => $socialLinks,
                'formed_year' => $formedYear,
                'formed_label' => $formedLabel,
                'facts' => $facts,
            ];

            $this->persistLocalProfile($artist, $payload);

            return $payload;
        });
    }

    /**
     * @return array{summary:string,thumbnail:string,social_links:array<int,array{label:string,url:string}>}|null
     */
    private function resolveLocalProfile(string $artist): ?array
    {
        if (! $this->hasTable('band_profiles')) {
            return null;
        }

        $profile = BandProfile::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($artist)])
            ->first();

        if (! $profile) {
            return null;
        }

        return [
            'summary' => trim((string) ($profile->editorial_summary ?: $profile->biography ?: '')),
            'thumbnail' => (string) ($profile->normalizedImageUrl() ?? ''),
            'social_links' => $this->normalizeLocalLinks((array) ($profile->official_links ?? [])),
            'formed_year' => $this->extractYearFromText((string) ($profile->editorial_summary ?: $profile->biography ?: '')),
            'formed_label' => $this->extractYearFromText((string) ($profile->editorial_summary ?: $profile->biography ?: ''))
                ? sprintf('Se formó en %d', $this->extractYearFromText((string) ($profile->editorial_summary ?: $profile->biography ?: '')))
                : '',
            'facts' => $this->normalizeFacts((array) ($profile->featured_facts ?? [])),
        ];
    }

    /**
     * @return array{extract?:string,thumbnail?:string,social_links?:array<int,string>}|null
     */
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

            if (! $search->successful() || empty($search->json('results.0.id'))) {
                return null;
            }

            $artistId = (string) $search->json('results.0.id');
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
                'extract' => trim((string) ($data['profile'] ?? '')),
                'thumbnail' => (string) ($data['images'][0]['uri'] ?? ''),
                'social_links' => $data['urls'] ?? [],
            ];
        } catch (Throwable) {
            return null;
        }
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
                    'editorial_summary' => $payload['summary'] ?? '',
                    'image_path' => $payload['thumbnail'] ?? '',
                    'official_links' => $payload['social_links'] ?? [],
                    'last_verified_at' => now(),
                    'source' => 'Seven Rock Radio',
                ]
            );
        } catch (\Throwable) {
            // ignore cache persistence failures
        }
    }

    /**
     * @return array{extract?:string,thumbnail?:string,social_links?:array<int,string>}
     */
    private function fetchWikipediaProfile(string $artist): array
    {
        foreach (['es', 'en'] as $locale) {
            $payload = $this->fetchWikipediaProfileForLocale($artist, $locale);
            if (! empty($payload)) {
                return $payload;
            }
        }

        return [];
    }

    /**
     * @return array{extract?:string,thumbnail?:string,social_links?:array<int,string>}
     */
    private function fetchWikipediaProfileForLocale(string $artist, string $locale): array
    {
        try {
            $search = Http::retry(1, 100)
                ->connectTimeout(2)
                ->timeout(4)
                ->get("https://{$locale}.wikipedia.org/w/api.php", [
                    'action' => 'opensearch',
                    'search' => $artist,
                    'limit' => 1,
                    'namespace' => 0,
                    'format' => 'json',
                ]);

            if (! $search->successful()) {
                return [];
            }

            $results = $search->json();
            $title = is_array($results) ? (string) ($results[1][0] ?? '') : '';
            if ($title === '') {
                return [];
            }

            $summary = Http::retry(1, 100)
                ->connectTimeout(2)
                ->timeout(4)
                ->get('https://es.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode($title));

            if (! $summary->successful()) {
                return [];
            }

            $data = $summary->json();
            if (! is_array($data)) {
                return [];
            }

            return [
                'extract' => (string) ($data['extract'] ?? ''),
                'thumbnail' => (string) ($data['thumbnail']['source'] ?? ''),
                'formed_year' => $this->extractYearFromText((string) ($data['extract'] ?? '')),
            ];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array{extract?:string,thumbnail?:string,social_links?:array<int,string>}|null
     */
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

            if (! $response->successful() || empty($response->json('artist'))) {
                return null;
            }

            $data = $response->json('artist');

            return [
                'extract' => trim((string) ($data['bio']['summary'] ?? $data['bio']['content'] ?? '')),
                'thumbnail' => (string) (collect($data['image'] ?? [])->last()['#text'] ?? ''),
                'social_links' => [(string) ($data['url'] ?? '')],
                'formed_year' => $this->extractYearFromText((string) ($data['bio']['summary'] ?? $data['bio']['content'] ?? '')),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{extract?:string,thumbnail?:string,social_links?:array<int,string>}|null
     */
    private function fetchTadbProfile(string $artist): ?array
    {
        try {
            $response = Http::retry(1, 100)
                ->connectTimeout(2)
                ->timeout(4)
                ->get('https://www.theaudiodb.com/api/v1/json/2/search.php', [
                    's' => $artist,
                ]);

            if (! $response->successful() || empty($response->json('artists.0'))) {
                return null;
            }

            $data = $response->json('artists.0');

            return [
                'extract' => trim((string) ($data['strBiographyEN'] ?? '')),
                'thumbnail' => (string) ($data['strArtistThumb'] ?? ''),
                'social_links' => array_values(array_filter([
                    $data['strWebsite'] ?? null,
                    $data['strFacebook'] ?? null,
                    $data['strTwitter'] ?? null,
                    $data['strInstagram'] ?? null,
                    $data['strYoutube'] ?? null,
                ])),
                'formed_year' => $this->positiveInt($data['intFormedYear'] ?? null),
                'genre_label' => trim((string) ($data['strGenre'] ?? '')),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{extract?:string,social_links?:array<int,string>}
     */
    private function fetchMusicBrainzProfile(string $artist): array
    {
        try {
            $search = Http::withHeaders([
                'User-Agent' => 'SevenRockRadio/1.0 (metadata)',
            ])
                ->retry(1, 100)
                ->connectTimeout(2)
                ->timeout(4)
                ->get('https://musicbrainz.org/ws/2/artist/', [
                    'query' => 'artist:"' . $artist . '"',
                    'fmt' => 'json',
                    'limit' => 1,
                ]);

            if (! $search->successful() || empty($search->json('artists.0.id'))) {
                return [];
            }

            $id = (string) $search->json('artists.0.id');
            $details = Http::withHeaders([
                'User-Agent' => 'SevenRockRadio/1.0 (metadata)',
            ])
                ->retry(1, 100)
                ->connectTimeout(2)
                ->timeout(4)
                ->get("https://musicbrainz.org/ws/2/artist/{$id}", [
                    'fmt' => 'json',
                    'inc' => 'url-rels',
                ]);

            if (! $details->successful()) {
                return [];
            }

            $data = $details->json();
            if (! is_array($data)) {
                return [];
            }

            $links = collect($data['relations'] ?? [])
                ->filter(fn ($rel) => in_array(($rel['type'] ?? ''), ['official homepage', 'social network'], true))
                ->map(fn ($rel) => $rel['url']['resource'] ?? null)
                ->filter()
                ->values()
                ->all();

            return [
                'extract' => trim((string) ($data['disambiguation'] ?? $data['type'] ?? '')),
                'social_links' => $links,
            ];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param array<int, string> $links
     * @return array<int, array{label:string,url:string}>
     */
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
        if ($url === '' || $url === '1' || $url === '0') {
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
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<int, mixed> $values
     */
    private function firstPositiveInteger(array $values): ?int
    {
        foreach ($values as $value) {
            $int = $this->positiveInt($value);
            if ($int !== null) {
                return $int;
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
     * @return array{summary:string,thumbnail:string,social_links:array<int,array{label:string,url:string}>}
     */
    private function emptyPayload(): array
    {
        return [
            'summary' => '',
            'thumbnail' => '',
            'social_links' => [],
        ];
    }
}
