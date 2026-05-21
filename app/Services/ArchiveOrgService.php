<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MasterProgram;
use App\Support\ExternalHttp;
use App\Support\PublicMediaUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

final class ArchiveOrgService
{
    private const HOME_CACHE_KEY = 'archive-org:home-podcasts:v4';
    private const EPISODE_CACHE_KEY = 'archive-org:latest-episode:v3';
    private const WARMUP_CACHE_KEY = 'archive-org:warmup:v3';
    private const MAX_NETWORK_CALLS_PER_REQUEST = 20;

    /** @var array<string, bool> */
    private static array $queuedEpisodeKeys = [];
    private static int $queuedEpisodeCount = 0;

    public function homePodcastPayload(int $limit = 10): array
    {
        $limit = max(1, min(20, $limit));

        return Cache::remember(self::HOME_CACHE_KEY . ':' . $limit, now()->addMinutes(20), function () use ($limit): array {
            $episodes = $this->latestPodcastEpisodes($limit);

            if ($episodes === []) {
                return $this->fallbackPayload();
            }

            $featured = $this->mapFeaturedEpisode($episodes[0]);

            return [
                'featured' => $featured,
                'episodes' => array_map(
                    fn (array $episode): array => $this->mapEpisodeRow($episode),
                    $episodes
                ),
            ];
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function latestPodcastEpisodes(int $limit = 10): array
    {
        $limit = max(1, min(60, $limit));

        if (! $this->hasArchiveTable()) {
            return [];
        }

        $episodes = [];
        foreach ($this->latestEpisodesFromRadioPrograms($limit * 3) as $episode) {
            if (! is_array($episode) || empty($episode['src'])) {
                continue;
            }

            $episodes[] = $episode;

            if (count($episodes) >= $limit) {
                break;
            }
        }

        if (count($episodes) < $limit) {
            $programs = MasterProgram::query()
                ->when($this->masterProgramHasColumn('activo'), fn ($query) => $query->where('activo', true))
                ->whereNotNull('archive_identifier')
                ->where('archive_identifier', '!=', '')
                ->orderBy('nombre')
                ->limit(80)
                ->get();

            if ($programs->isNotEmpty()) {
                $networkBudget = self::MAX_NETWORK_CALLS_PER_REQUEST;
                $forceNetwork = in_array(config('cache.default'), ['array', 'null'], true);

                foreach ($programs as $program) {
                    $cover = PublicMediaUrl::normalizePublicUrl((string) ($program->caratula_url ?: ''));
                    $allowNetwork = $forceNetwork || $networkBudget > 0;

                    try {
                        $episode = $this->getLatestEpisodeCached(
                            (string) $program->archive_identifier,
                            (string) $program->nombre,
                            $cover,
                            allowNetwork: $allowNetwork
                        );

                        if ($allowNetwork && $networkBudget > 0) {
                            $networkBudget--;
                        }

                        if (! is_array($episode) || empty($episode['src'])) {
                            continue;
                        }

                        $episode['show'] = (string) $program->nombre;
                        $episode['slug'] = Str::slug((string) $program->nombre);
                        $episode['description'] = trim(strip_tags((string) $program->descripcion));
                        $episode['cover'] = PublicMediaUrl::normalizePublicUrl((string) ($episode['cover'] ?? $cover));
                        $episode['archive_url'] = $episode['archive_url'] ?? ('https://archive.org/details/' . rawurlencode((string) $program->archive_identifier));
                        $episode['host'] = (string) ($program->conductor ?? '');
                        $episodes[] = $episode;
                    } catch (Throwable) {
                        // Keep the home page resilient.
                    }

                    if (count($episodes) >= $limit) {
                        break;
                    }
                }
            }
        }

        usort($episodes, static fn (array $left, array $right): int => ((int) ($right['published_at'] ?? 0)) <=> ((int) ($left['published_at'] ?? 0)));

        $unique = [];
        $seen = [];

        foreach ($episodes as $episode) {
            $dedupeKey = implode('|', array_filter([
                trim((string) ($episode['id'] ?? '')),
                trim((string) ($episode['src'] ?? '')),
                trim((string) ($episode['archive_url'] ?? '')),
                trim((string) ($episode['show'] ?? '')),
                trim((string) ($episode['title'] ?? '')),
                trim((string) ((string) ($episode['published_at'] ?? ''))),
                trim((string) ($episode['host'] ?? '')),
            ], static fn (string $value): bool => $value !== ''));
            if ($dedupeKey !== '' && isset($seen[$dedupeKey])) {
                continue;
            }

            if ($dedupeKey !== '') {
                $seen[$dedupeKey] = true;
            }

            $unique[] = $episode;
        }

        return array_slice($unique, 0, $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function latestEpisodesFromRadioPrograms(int $limit): array
    {
        $limit = max(1, min(120, $limit));

        if (! $this->hasArchiveTable() || ! Schema::hasTable('radio_programs')) {
            return [];
        }

        try {
            $rows = DB::table('radio_programs as rp')
                ->leftJoin('master_programs as mp', 'mp.id', '=', 'rp.master_program_id')
                ->select([
                    'rp.id',
                    'rp.master_program_id',
                    'rp.titulo_programa',
                    'rp.conductor',
                    'rp.fecha_emision',
                    'rp.resena',
                    'rp.live_title',
                    'rp.live_description',
                    'rp.comentario_episodio',
                    'rp.archivo_mp3',
                    'rp.caratula_programa',
                    'rp.imagen_episodio',
                    'rp.imagen_invitado',
                    'rp.ruta_ftp_radioboss',
                    'rp.archive_org_status',
                    'rp.archive_org_remote_path',
                    'rp.archive_org_uploaded_at',
                    'rp.archive_org_metadata',
                    'rp.created_at',
                    'rp.updated_at',
                    'mp.nombre as master_name',
                    'mp.archive_identifier as master_archive_identifier',
                    'mp.caratula_url as master_cover',
                    'mp.descripcion as master_description',
                ])
                ->where(function ($query): void {
                    $query->where('rp.sync_archive_org', true)
                        ->orWhere('rp.archive_org_status', 'synced')
                        ->orWhere('rp.archive_org_status', 'uploaded')
                        ->orWhereNotNull('rp.archive_org_uploaded_at');
                })
                ->orderByDesc('rp.fecha_emision')
                ->orderByDesc('rp.archive_org_uploaded_at')
                ->orderByDesc('rp.updated_at')
                ->limit($limit)
                ->get();
        } catch (Throwable) {
            return [];
        }

        $episodes = [];

        foreach ($rows as $row) {
            $snapshot = $this->normalizeArchiveMetadata($row->archive_org_metadata ?? null);
            $identifier = trim((string) data_get($snapshot, 'identifier', ''));
            if ($identifier === '') {
                $identifier = trim((string) ($row->master_archive_identifier ?? ''));
            }

            $remotePath = trim((string) data_get($snapshot, 'remote_path', ''));
            if ($remotePath === '') {
                $remotePath = trim((string) ($row->archive_org_remote_path ?? ''));
            }

            $src = trim((string) data_get($snapshot, 'src', ''));
            if ($src === '' && $identifier !== '' && $remotePath !== '') {
                $src = 'https://archive.org/download/' . rawurlencode($identifier) . '/' . implode('/', array_map('rawurlencode', explode('/', ltrim($remotePath, '/'))));
            }

            if ($src === '' && $identifier !== '') {
                $storedPath = trim((string) ($row->archivo_mp3 ?? ''));
                if ($storedPath !== '') {
                    $src = $this->buildArchiveDownloadSrc($identifier, $storedPath);
                }
            }

            if ($src === '') {
                $storedPath = trim((string) ($row->archivo_mp3 ?? ''));
                if ($storedPath !== '') {
                    $src = asset('storage/' . ltrim(str_replace('\\', '/', $storedPath), '/'));
                }
            }

            if ($src === '') {
                continue;
            }

            $publishedAt = $this->publishedAtToDate(
                data_get($row, 'fecha_emision')
                ?: data_get($row, 'archive_org_uploaded_at')
                ?: data_get($row, 'updated_at')
                ?: data_get($snapshot, 'synced_at')
            );

            $title = trim((string) (
                $row->live_title
                ?: data_get($snapshot, 'episode.live_title')
                ?: $row->titulo_programa
                ?: $row->master_name
                ?: 'Podcast'
            ));

            $description = trim((string) (
                $row->live_description
                ?: $row->resena
                ?: $row->comentario_episodio
                ?: data_get($snapshot, 'episode.live_description')
                ?: data_get($snapshot, 'episode.resena')
                ?: data_get($snapshot, 'episode.comentario_episodio')
                ?: $row->master_description
                ?: ''
            ));

            $cover = PublicMediaUrl::normalizePublicUrl((string) (
                $row->imagen_episodio
                ?: $row->caratula_programa
                ?: $row->master_cover
                ?: ''
            ));

            if ($cover === '') {
                $cover = trim((string) data_get($snapshot, 'episode_metadata.image', ''));
            }

            $episodes[] = [
                'id' => trim((string) ($row->id ?? '')),
                'show' => trim((string) ($row->titulo_programa ?: $row->master_name ?: $title)),
                'slug' => Str::slug((string) ($row->titulo_programa ?: $row->master_name ?: $title)),
                'title' => $title !== '' ? $title : 'Podcast',
                'description' => $description,
                'cover' => $cover,
                'src' => $src,
                'published_at' => $publishedAt?->getTimestamp() ?? 0,
                'archive_url' => $identifier !== '' ? 'https://archive.org/details/' . rawurlencode($identifier) : '',
                'host' => trim((string) ($row->conductor ?? '')),
            ];
        }

        return $episodes;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeArchiveMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_string($metadata) && $metadata !== '') {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function buildArchiveDownloadSrc(string $identifier, string $path): string
    {
        $identifier = trim($identifier);
        $path = trim(str_replace('\\', '/', $path), '/');

        if ($identifier === '' || $path === '') {
            return '';
        }

        return 'https://archive.org/download/' . rawurlencode($identifier) . '/' . implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    public function getPodcastItem(string $identifier, string $defaultTitle = 'Podcast', string $defaultCover = '', int $limit = 12): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $limit = max(0, $limit);
        $cacheKey = 'archive-org:item:v1:' . md5($identifier . '|' . ($limit > 0 ? (string) $limit : 'all'));

        return Cache::remember($cacheKey, now()->addHours(2), function () use ($identifier, $defaultTitle, $defaultCover, $limit): ?array {
            try {
                $response = ExternalHttp::client()->retry(2, 250)->timeout(8)
                    ->get('https://archive.org/details/' . urlencode($identifier) . '?output=json');

                $data = $response->json();
                if (! is_array($data)) {
                    return null;
                }

                $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
                $files = is_array($data['files'] ?? null) ? $data['files'] : [];

                $episodes = [];
                foreach ($files as $key => $file) {
                    $name = trim((string) ($file['name'] ?? ltrim((string) $key, '/')));
                    if ($name === '') {
                        continue;
                    }

                    $format = strtolower(trim((string) ($file['format'] ?? '')));
                    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $isMp3 = $extension === 'mp3' || str_contains($format, 'mp3');

                    if (! $isMp3) {
                        continue;
                    }

                    $episodes[] = [
                        'id' => $name,
                        'title' => trim((string) ($file['title'] ?? $name)),
                        'src' => 'https://archive.org/download/' . rawurlencode($identifier) . '/' . implode('/', array_map('rawurlencode', explode('/', $name))),
                        'published_at' => isset($file['mtime']) ? (int) $file['mtime'] : null,
                        'views' => isset($file['downloads']) ? (int) $file['downloads'] : null,
                        'duration' => trim((string) ($file['length'] ?? '')),
                        'format' => trim((string) ($file['format'] ?? '')),
                        'size' => isset($file['size']) ? (int) $file['size'] : null,
                    ];
                }

                usort($episodes, static function (array $left, array $right): int {
                    return ((int) ($right['published_at'] ?? 0)) <=> ((int) ($left['published_at'] ?? 0));
                });

                $episodes = $limit > 0 ? array_slice(array_values($episodes), 0, $limit) : array_values($episodes);
                $latestEpisode = $episodes[0] ?? null;
                $title = trim((string) ($metadata['title'] ?? $defaultTitle));
                $description = $this->normalizeArchiveDescription($metadata['description'] ?? '');
                $creator = trim((string) ($metadata['creator'] ?? $metadata['uploader'] ?? ''));
                $date = trim((string) ($metadata['date'] ?? ''));
                $cover = $this->resolveArchiveCover($identifier, $metadata, $defaultCover);
                $downloads = isset($data['item']['downloads']) ? (int) $data['item']['downloads'] : null;

                return [
                    'id' => $identifier,
                    'title' => $title !== '' ? $title : $defaultTitle,
                    'description' => $description,
                    'creator' => $creator,
                    'date' => $date,
                    'cover' => $cover !== '' ? $cover : $defaultCover,
                    'downloads' => $downloads,
                    'episodes' => $episodes,
                    'latest_episode' => $latestEpisode,
                    'archive_url' => 'https://archive.org/details/' . rawurlencode($identifier),
                ];
            } catch (Throwable) {
                return null;
            }
        });
    }

    public function getLatestEpisodeCached(
        string $identifier,
        string $defaultTitle = 'Podcast',
        string $defaultCover = '',
        bool $allowNetwork = false
    ): ?array {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $cacheKey = self::EPISODE_CACHE_KEY . ':' . md5($identifier);

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['src'])) {
            return $cached;
        }

        if (! $allowNetwork) {
            try {
                if (isset(self::$queuedEpisodeKeys[$cacheKey])) {
                    return null;
                }

                if (self::$queuedEpisodeCount >= self::MAX_NETWORK_CALLS_PER_REQUEST) {
                    return null;
                }

                self::$queuedEpisodeKeys[$cacheKey] = true;
                self::$queuedEpisodeCount++;

                app()->terminating(function () use ($cacheKey, $identifier, $defaultTitle, $defaultCover): void {
                    $fresh = $this->fetchLatestEpisode($identifier, $defaultTitle, $defaultCover);
                    if ($fresh) {
                        Cache::put($cacheKey, $fresh, now()->addHours(6));
                    }
                });
            } catch (Throwable) {
                // ignore
            }

            return null;
        }

        $fresh = $this->fetchLatestEpisode($identifier, $defaultTitle, $defaultCover);
        if ($fresh) {
            Cache::put($cacheKey, $fresh, now()->addHours(6));
        }

        return $fresh;
    }

    private function fetchLatestEpisode(string $identifier, string $defaultTitle, string $defaultCover): ?array
    {
        return Cache::remember(self::EPISODE_CACHE_KEY . ':fetch:' . md5($identifier), now()->addMinutes(2), function () use ($identifier, $defaultTitle, $defaultCover) {
            try {
                $response = ExternalHttp::client()->retry(2, 200)->timeout(6)
                    ->get('https://archive.org/details/' . urlencode($identifier) . '?output=json');

                $data = $response->json();
                if (! $data || empty($data['files']) || ! is_array($data['files'])) {
                    return null;
                }

                $bestEpisode = null;
                $bestRank = -1;
                $fileName = '';

                foreach ($data['files'] as $key => $file) {
                    $name = (string) ($file['name'] ?? ltrim((string) $key, '/'));
                    $format = strtolower((string) ($file['format'] ?? ''));

                    $isMp3 = $name !== '' && strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'mp3';
                    $isMp3 = $isMp3 || ($format !== '' && str_contains($format, 'mp3'));

                    if (! $isMp3 || $name === '') {
                        continue;
                    }

                    $rank = isset($file['mtime']) ? (int) $file['mtime'] : 0;
                    if ($bestEpisode === null || $rank > $bestRank) {
                        $bestRank = $rank;
                        $bestEpisode = $file;
                        $fileName = $name;
                    }
                }

                if (! $bestEpisode) {
                    return null;
                }

                $src = 'https://archive.org/download/' . rawurlencode($identifier) . '/' . implode('/', array_map('rawurlencode', explode('/', $fileName)));
                $title = (string) ($bestEpisode['title'] ?? $fileName);
                $publishedAt = isset($bestEpisode['mtime']) ? (int) $bestEpisode['mtime'] : null;
                $views = isset($data['item']['downloads']) ? (int) $data['item']['downloads'] : 0;
                $description = '';
                if (isset($data['metadata']['description'])) {
                    $mDescription = $data['metadata']['description'];
                    $description = is_array($mDescription) ? (string) ($mDescription[0] ?? '') : (string) $mDescription;
                }

                return [
                    'id' => $identifier,
                    'title' => $title !== '' ? $title : $defaultTitle,
                    'show' => $defaultTitle,
                    'description' => trim(strip_tags($description)),
                    'src' => $src,
                    'cover' => $defaultCover,
                    'published_at' => $publishedAt,
                    'views' => $views > 0 ? $views : null,
                    'archive_url' => 'https://archive.org/details/' . rawurlencode($identifier),
                ];
            } catch (Throwable) {
                return null;
            }
        });
    }

    private function mapFeaturedEpisode(array $episode): array
    {
        $publishedAt = $this->publishedAtToDate($episode['published_at'] ?? null);
        $show = trim((string) ($episode['show'] ?? $episode['title'] ?? 'Podcast'));
        $episodeTitle = trim((string) ($episode['title'] ?? ''));
        $description = trim((string) ($episode['description'] ?? ''));
        $src = trim((string) ($episode['src'] ?? ''));
        $archiveUrl = trim((string) ($episode['archive_url'] ?? ''));

        return [
            'title' => $show !== '' ? $show : 'Podcast',
            'program' => $show !== '' ? $show : 'Podcast',
            'episode_title' => $episodeTitle !== '' ? $episodeTitle : $show,
            'episode' => 'Último episodio',
            'date' => $publishedAt?->format('d/m/Y') ?? '',
            'host' => trim((string) ($episode['host'] ?? 'Seven Rock Radio')),
            'image' => trim((string) ($episode['cover'] ?? '')),
            'summary' => $description !== '' ? $description : 'Episodio listo para escuchar desde la portada.',
            'src' => $src,
            'archive_url' => $archiveUrl,
            'url' => trim((string) ($archiveUrl !== '' ? $archiveUrl : $src)),
        ];
    }

    private function mapEpisodeRow(array $episode): array
    {
        $publishedAt = $this->publishedAtToDate($episode['published_at'] ?? null);
        $show = trim((string) ($episode['show'] ?? $episode['title'] ?? 'Podcast'));
        $episodeTitle = trim((string) ($episode['title'] ?? ''));
        $src = trim((string) ($episode['src'] ?? ''));
        $archiveUrl = trim((string) ($episode['archive_url'] ?? ''));

        return [
            'title' => $show !== '' ? $show : 'Podcast',
            'program' => $show !== '' ? $show : 'Podcast',
            'episode_title' => $episodeTitle !== '' ? $episodeTitle : $show,
            'episode' => $show !== '' ? $show : 'Podcast',
            'date' => $publishedAt?->format('d/m/Y') ?? '',
            'image' => trim((string) ($episode['cover'] ?? '')),
            'summary' => trim((string) ($episode['description'] ?? 'Episodio listo para escuchar desde la portada.')),
            'src' => $src,
            'archive_url' => $archiveUrl,
            'url' => trim((string) ($archiveUrl !== '' ? $archiveUrl : $src)),
            'host' => trim((string) ($episode['host'] ?? 'Seven Rock Radio')),
        ];
    }

    private function fallbackPayload(): array
    {
        return [
            'featured' => [
                'title' => 'Seven Rock Radio',
                'program' => 'Seven Rock Radio',
                'episode_title' => 'Podcast',
                'episode' => 'Podcast',
                'date' => '',
                'host' => 'Seven Rock Radio',
                'image' => '',
                'summary' => 'No hay podcasts listos todavía. El módulo queda conectado a Archive.org.',
                'src' => '',
                'archive_url' => '',
                'url' => '',
            ],
            'episodes' => [],
        ];
    }

    private function resolveArchiveCover(string $identifier, array $metadata, string $defaultCover): string
    {
        $candidates = [
            $metadata['image'] ?? null,
            $metadata['image_url'] ?? null,
            $metadata['cover'] ?? null,
            $metadata['thumbnail'] ?? null,
            $metadata['poster'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $url = trim((string) $candidate);
            if ($url === '') {
                continue;
            }

            if (Str::startsWith($url, ['http://', 'https://', '//'])) {
                return $url;
            }

            return 'https://archive.org/download/' . rawurlencode($identifier) . '/' . ltrim($url, '/');
        }

        return $defaultCover;
    }

    private function normalizeArchiveDescription(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value[0] ?? '';
        }

        return trim(strip_tags((string) $value));
    }

    private function publishedAtToDate(mixed $value): ?Carbon
    {
        if (is_numeric($value) && (int) $value > 0) {
            return Carbon::createFromTimestamp((int) $value);
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }

            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function hasArchiveTable(): bool
    {
        try {
            return Schema::hasTable('master_programs');
        } catch (Throwable) {
            return false;
        }
    }

    private function masterProgramHasColumn(string $column): bool
    {
        try {
            return Schema::hasColumn('master_programs', $column);
        } catch (Throwable) {
            return false;
        }
    }
}
