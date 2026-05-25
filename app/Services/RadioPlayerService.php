<?php

namespace App\Services;

use App\Models\Notice;
use App\Models\PlayHistory;
use App\Models\Program;
use App\Models\Song;
use App\Support\BandProfileMatcher;
use App\Support\BandInfoResolver;
use App\Support\ExternalHttp;
use App\Support\PublicMediaUrl;
use App\Support\RadioPlayerStateStore;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class RadioPlayerService
{
    private const STATUS_CACHE_KEY = 'radio-player-status:v6';

    public function __construct(
        private readonly RadioPlayerStateStore $store,
        private readonly BandInfoResolver $bandInfoResolver,
    ) {
    }

    public function resolve(): array
    {
        return $this->build();
    }

    public function forgetCache(): void
    {
        // No-op: the public status endpoint is generated on demand.
    }

    public function storeTrack(array $payload): void
    {
        $this->store->write($payload);
        $this->forgetCache();
    }

    public function currentState(): array
    {
        return $this->store->read();
    }

    public function latestHistory(int $limit): array
    {
        if (! $this->hasTable('play_history')) {
            return [];
        }

        return PlayHistory::query()
            ->with('program')
            ->latest('played_at')
            ->limit($limit)
            ->get()
            ->map(fn (PlayHistory $history): array => [
                'title' => $history->title,
                'artist' => $history->artist,
                'cover' => $this->resolveCover($history->cover_image),
                'source' => $history->source,
                'played_at' => optional($history->played_at)->toIso8601String(),
                'program' => $history->program?->name,
            ])
            ->all();
    }

    private function build(): array
    {
        $defaults = config('player.defaults');
        $state = array_replace($this->currentState(), $this->remoteNowPlayingState());
        $rawTitle = $this->firstFilledString([
            Arr::get($state, 'title'),
            Arr::get($state, 'casttitle'),
            Arr::get($state, 'tracktitle'),
        ]);
        $rawArtist = $this->firstFilledString([
            Arr::get($state, 'artist'),
            Arr::get($state, 'trackartist'),
            Arr::get($state, 'program_name'),
        ]);
        $rawCover = $this->firstFilledString([
            Arr::get($state, 'cover'),
            Arr::get($state, 'cover_image'),
            Arr::get($state, 'artwork'),
            Arr::get($state, 'image'),
        ]);
        $lookupState = $state;
        $lookupState['title'] = $rawTitle;
        $lookupState['artist'] = $rawArtist;
        $lookupState['cover'] = $rawCover;

        $song = $this->resolveSong($lookupState);
        $bandProfile = $song?->bandProfile
            ?? $this->resolveBandProfile($state, $song);
        $trackTitle = $this->firstFilledString([
            $song?->title,
            $rawTitle,
            $defaults['title'] ?? '',
        ]);
        $trackArtist = $this->firstFilledString([
            $song?->artist,
            $rawArtist,
            $defaults['artist'] ?? '',
        ]);
        $stateSignature = $this->trackSignature(
            $rawTitle !== '' ? $rawTitle : $trackTitle,
            $rawArtist !== '' ? $rawArtist : $trackArtist,
            $this->resolveCover(
                $rawCover
                    ?: Arr::get($state, 'cover')
                    ?? Arr::get($state, 'cover_image')
                    ?? Arr::get($state, 'artwork')
                    ?? Arr::get($state, 'image')
                    ?? $defaults['cover']
            )
        );
        $bandProfilePayload = $bandProfile
            ? $this->bandInfoResolver->resolve((string) $bandProfile->name)
            : ($trackArtist !== '' ? $this->bandInfoResolver->resolve($trackArtist) : []);
        $lyrics = $this->firstFilledString([
            $song?->lyrics,
            Arr::get($state, 'lyrics'),
        ]);
        $programs = $this->loadPrograms();
        $currentProgram = $this->resolveCurrentProgram($state, $song, $programs);
        $nextProgram = $this->resolveNextProgram(
            $currentProgram,
            $programs,
            $this->remoteUpcomingPrograms()
        );
        $notices = $this->hasTable('notices')
            ? Notice::query()->active()->orderBy('sort_order')->get()
            : collect();

        $cover = $this->resolveCover(
            $song?->cover_image
                ?? Arr::get($state, 'cover')
                ?? $currentProgram?->cover_image
                ?? $defaults['cover']
        );
        $trackSignature = $this->trackSignature($trackTitle, $trackArtist, $cover);
        $trackChanged = $stateSignature !== $trackSignature;

        $duration = (int) Arr::get($state, 'duration_seconds', $song?->duration_seconds ?? 0);
        $elapsed = (int) Arr::get($state, 'elapsed_seconds', 0);
        if ($trackChanged) {
            $duration = (int) ($song?->duration_seconds ?? 0);
            $elapsed = 0;
        } elseif ($elapsed <= 0 && filled(Arr::get($state, 'started_at'))) {
            try {
                $elapsed = max(0, Carbon::parse((string) Arr::get($state, 'started_at'))->diffInSeconds(now()));
            } catch (\Throwable) {
                $elapsed = 0;
            }
        }

        if ($duration > 0 && $elapsed > $duration) {
            $elapsed = 0;
        }

        $track = [
            'id' => $song?->id,
            'title' => $trackTitle !== '' ? $trackTitle : (string) ($defaults['title'] ?? ''),
            'artist' => $trackArtist !== '' ? $trackArtist : (string) ($defaults['artist'] ?? ''),
            'album' => $this->firstFilledString([
                $song?->album,
                Arr::get($state, 'album'),
            ]),
            'cover' => $cover,
            'lyrics' => is_string($lyrics) ? $lyrics : '',
            'band_info' => $song?->band_info
                ?: ($bandProfile?->editorial_summary ?: $bandProfile?->biography)
                ?: Arr::get($state, 'band_info')
                ?: Arr::get($state, 'comment')
                ?: ($bandProfilePayload['summary'] ?? ''),
            'band_thumbnail' => Arr::get($state, 'band_thumbnail')
                ?: ($bandProfile?->normalizedImageUrl() ?? '')
                ?: ($bandProfilePayload['thumbnail'] ?? ''),
            'band_founded_year' => Arr::get($bandProfilePayload, 'formed_year'),
            'band_founded_label' => Arr::get($bandProfilePayload, 'formed_label'),
            'comment' => Arr::get($state, 'comment'),
            'band_members' => $song?->band_members ?? Arr::get($state, 'band_members', []),
            'social_links' => ! empty($song?->social_links)
                ? $song->social_links
                : (Arr::get($state, 'social_links', []) ?: ($bandProfilePayload['social_links'] ?? [])),
            'audio_url' => $song?->audio_url ?: Arr::get($state, 'audio_url'),
            'program_id' => $currentProgram?->id ?? Arr::get($state, 'program_id'),
            'program_name' => $currentProgram?->name,
            'program_host' => $currentProgram?->host,
            'program_schedule' => $currentProgram?->schedule,
            'is_live' => (bool) Arr::get($state, 'is_live', $song?->is_live ?? true),
            'signature' => $trackSignature,
            'started_at' => Arr::get($state, 'started_at'),
            'published_at' => optional($song?->published_at)->toIso8601String(),
            'duration_seconds' => $duration,
            'elapsed_seconds' => $elapsed,
        ];

        return [
            'stream_url' => config('player.streams.direct'),
            'listen_url' => config('player.streams.listen'),
            'playlist_m3u' => config('player.streams.m3u'),
            'playlist_pls' => config('player.streams.pls'),
            'listeners' => (int) Arr::get($state, 'listeners', 0),
            'track' => $track,
            'program' => $this->programPayload($currentProgram),
            'next_program' => $this->programPayload($nextProgram),
            'queue' => $this->resolveQueue($song, $currentProgram),
            'notices' => $notices->map(fn (Notice $notice): array => [
                'title' => $notice->title,
                'content' => $notice->content,
                'type' => $notice->type,
            ])->all(),
            'history' => $this->latestHistory(config('player.history_limit', 10)),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param array<int, mixed> $values
     */
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

    private function resolveSong(array $state): ?Song
    {
        if (! $this->hasTable('songs')) {
            return null;
        }

        if ($songId = Arr::get($state, 'song_id')) {
            $song = Song::query()->with('bandProfile')->find((int) $songId);
            if ($song) {
                return $song;
            }
        }

        $title = trim((string) Arr::get($state, 'title', ''));
        $artist = trim((string) Arr::get($state, 'artist', ''));

        if ($title === '' && $artist === '') {
            return null;
        }

        return Song::query()
            ->with('bandProfile')
            ->when($title !== '', fn ($query) => $query->whereRaw('LOWER(title) = ?', [mb_strtolower($title)]))
            ->when($artist !== '', fn ($query) => $query->whereRaw('LOWER(artist) = ?', [mb_strtolower($artist)]))
            ->first();
    }

    private function resolveBandProfile(array $state, ?Song $song)
    {
        if (! $this->hasTable('radio_artists')) {
            return null;
        }

        if ($song?->band_profile_id) {
            return $song->bandProfile;
        }

        $artist = trim((string) ($song?->artist ?: Arr::get($state, 'artist', '')));
        if ($artist === '') {
            return null;
        }

        $matcher = app(BandProfileMatcher::class);

        return $matcher->exactMatch($artist)
            ?? $matcher->fuzzyMatch($artist);
    }

    private function resolveCurrentProgram(array $state, ?Song $song, $programs): ?Program
    {
        if ($programId = Arr::get($state, 'program_id')) {
            $program = $programs->firstWhere('id', (int) $programId);
            if ($program) {
                return $program;
            }
        }

        if ($song?->program_id) {
            $program = $programs->firstWhere('id', (int) $song->program_id);
            if ($program) {
                return $program;
            }
        }

        return $programs->first();
    }

    private function resolveNextProgram(?Program $currentProgram, $programs, array $remoteUpcomingPrograms = []): ?Program
    {
        if (! $currentProgram) {
            return $programs->skip(1)->first() ?? $this->programFromUpcomingEvent($remoteUpcomingPrograms[0] ?? null);
        }

        $currentIndex = $programs->values()->search(fn (Program $program): bool => (int) $program->id === (int) $currentProgram->id);
        if ($currentIndex === false) {
            return $programs->skip(1)->first() ?? $this->programFromUpcomingEvent($remoteUpcomingPrograms[0] ?? null);
        }

        return $programs->values()
            ->slice($currentIndex + 1)
            ->first()
            ?? $this->programFromUpcomingEvent($remoteUpcomingPrograms[0] ?? null);
    }

    private function resolveQueue(?Song $song, ?Program $program): array
    {
        if (! $song || ! $program) {
            return [];
        }

        $query = Song::query()
            ->published()
            ->where('program_id', $program->id);

        if ($this->hasColumn('songs', 'sort_order')) {
            $query->where('sort_order', '>', (int) $song->sort_order);
            $query->orderBy('sort_order');
        } else {
            $query->whereKeyNot($song->getKey());
        }

        return $query
            ->limit(5)
            ->get()
            ->map(fn (Song $item): array => [
                'title' => $item->title,
                'artist' => $item->artist,
                'cover' => $item->cover_url,
                'audio_url' => $item->audio_url,
            ])
            ->all();
    }

    private function programPayload(?Program $program): ?array
    {
        if (! $program) {
            return null;
        }

        return [
            'id' => $program->id,
            'slug' => $program->slug,
            'name' => $program->name,
            'description' => $program->description,
            'host' => $program->host,
            'schedule' => $program->schedule,
            'cover' => $program->cover_url,
            'social_links' => $program->social_links ?? [],
        ];
    }

    private function resolveCover(?string $cover): string
    {
        if ($resolved = PublicMediaUrl::normalizePublicUrl($cover)) {
            return $resolved;
        }

        return $cover ? asset($cover) : asset(config('player.defaults.cover'));
    }

    private function trackSignature(string $title, string $artist, string $cover): string
    {
        return md5(mb_strtolower(implode('|', [
            trim($title),
            trim($artist),
            trim($cover),
        ])));
    }

    private function remoteNowPlayingState(): array
    {
        $metadataTxt = $this->remoteMetadataTxtState();
        if (! empty($metadataTxt)) {
            return $metadataTxt;
        }

        $apiUrl = trim((string) config('player.radioboss.api_url', ''));
        $stationId = trim((string) config('player.radioboss.station_id', ''));
        $apiKey = trim((string) config('player.radioboss.api_key', ''));

        if ($apiUrl === '' || $stationId === '' || $apiKey === '') {
            return [];
        }

        try {
            $response = ExternalHttp::client()->connectTimeout(1)
                ->timeout(2)
                ->acceptJson()
                ->get(rtrim($apiUrl, '/') . '/api/info/' . $stationId, [
                    'key' => $apiKey,
                ]);

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json();
            if (! is_array($data)) {
                return [];
            }

            $normalized = $this->normalizeRadioBossInfo($data);
            if (($normalized['title'] === '' && $normalized['artist'] === '') || empty($normalized['title']) || empty($normalized['artist'])) {
                $playlistState = $this->remotePlaylistState();
                if (! empty($playlistState)) {
                    return $playlistState;
                }
            }

            return array_filter([
                'title' => $normalized['title'] !== '' ? $normalized['title'] : null,
                'artist' => $normalized['artist'] !== '' ? $normalized['artist'] : null,
                'album' => $normalized['album'] !== '' ? $normalized['album'] : null,
                'cover' => $normalized['cover'] !== '' ? $normalized['cover'] : null,
                'is_live' => (bool) Arr::get($data, 'live', true),
                'program_name' => $normalized['program_name'] !== '' ? $normalized['program_name'] : null,
                'listeners' => $normalized['listeners'] > 0 ? $normalized['listeners'] : null,
            ], static fn ($value): bool => $value !== null && $value !== '');
        } catch (\Throwable) {
            return [];
        }
    }

    private function remotePlaylistState(): array
    {
        $apiUrl = trim((string) config('player.radioboss.api_url', ''));
        $stationId = trim((string) config('player.radioboss.station_id', ''));
        $apiKey = trim((string) config('player.radioboss.api_key', ''));

        if ($apiUrl === '' || $stationId === '' || $apiKey === '') {
            return [];
        }

        try {
            $response = ExternalHttp::client()->connectTimeout(1)
                ->timeout(2)
                ->acceptJson()
                ->get(rtrim($apiUrl, '/') . '/api/getplaylist/' . $stationId, [
                    'key' => $apiKey,
                ]);

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json();
            $items = [];

            if (is_array($data)) {
                $items = array_is_list($data) ? $data : (Arr::get($data, 'items') ?? Arr::get($data, 'playlist') ?? []);
            }

            $items = is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
            $item = $items[0] ?? [];
            if (! $item) {
                return [];
            }

            $title = $this->firstFilledString([
                Arr::get($item, 'title'),
                Arr::get($item, 'tracktitle'),
                Arr::get($item, 'song'),
                Arr::get($item, 'name'),
            ]);
            $artist = $this->firstFilledString([
                Arr::get($item, 'artist'),
                Arr::get($item, 'trackartist'),
                Arr::get($item, 'performer'),
            ]);

            return array_filter([
                'title' => $title !== '' ? $title : null,
                'artist' => $artist !== '' ? $artist : null,
                'album' => $this->firstFilledString([Arr::get($item, 'album'), Arr::get($item, 'program')]),
                'cover' => $this->firstFilledString([Arr::get($item, 'artwork'), Arr::get($item, 'cover'), Arr::get($item, 'image')]),
            ], static fn ($value): bool => $value !== null && $value !== '');
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array{title:string,nextstart?:string,sectostart?:int}>
     */
    private function remoteUpcomingPrograms(): array
    {
        $apiUrl = trim((string) config('player.radioboss.api_url', ''));
        $stationId = trim((string) config('player.radioboss.station_id', ''));
        $apiKey = trim((string) config('player.radioboss.api_key', ''));

        if ($apiUrl === '' || $stationId === '' || $apiKey === '') {
            return [];
        }

        try {
            $response = ExternalHttp::client()->connectTimeout(1)
                ->timeout(2)
                ->acceptJson()
                ->get(rtrim($apiUrl, '/') . '/api/getupcomingevents/' . $stationId, [
                    'key' => $apiKey,
                ]);

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json();
            return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function programFromUpcomingEvent(?array $event): ?Program
    {
        if (! $event) {
            return null;
        }

        $title = trim((string) Arr::get($event, 'title', ''));
        if ($title === '') {
            return null;
        }

        return new Program([
            'name' => $title,
            'schedule' => trim((string) Arr::get($event, 'nextstart', '')),
            'description' => null,
            'host' => null,
            'slug' => null,
            'cover_image' => null,
            'sort_order' => 999999,
        ]);
    }

    private function remoteMetadataTxtState(): array
    {
        $metadataTxtUrl = trim((string) config('player.radioboss.metadata_txt_url', ''));
        if ($metadataTxtUrl === '') {
            return [];
        }

        try {
            $response = ExternalHttp::client()->connectTimeout(1)->timeout(2)->get($metadataTxtUrl);
            if (! $response->successful()) {
                return [];
            }

            $line = trim((string) $response->body());
            if ($line === '') {
                return [];
            }

            [$artist, $title] = $this->splitNowPlaying($line);

            return array_filter([
                'title' => $title !== '' ? $title : null,
                'artist' => $artist !== '' ? $artist : null,
            ], static fn ($value): bool => $value !== null && $value !== '');
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{title:string,artist:string,album:string,cover:string,program_name:string,listeners:int}
     */
    private function normalizeRadioBossInfo(array $data): array
    {
        $currentTrack = Arr::get($data, 'currenttrack_info', []);
        $currentTrack = is_array($currentTrack) ? $currentTrack : [];

        $trackAttributes = Arr::get($currentTrack, '@attributes', Arr::get($data, 'currenttrack_info.@attributes', []));
        $trackAttributes = is_array($trackAttributes) ? $trackAttributes : [];

        $recent = Arr::get($data, 'recent', []);
        $recent = is_array($recent) ? array_values(array_filter($recent, 'is_array')) : [];
        $recentTrack = $recent[0] ?? [];

        $line = $this->firstFilledString([
            trim((string) Arr::get($data, 'autodj_title', '')),
            trim((string) Arr::get($data, 'nowplaying', '')),
            trim((string) Arr::get($trackAttributes, 'CASTTITLE', '')),
            trim((string) Arr::get($trackAttributes, 'ITEMTITLE', '')),
            trim((string) Arr::get($trackAttributes, 'TITLE', '')),
            trim((string) Arr::get($recentTrack, 'title', '')),
            trim((string) Arr::get($recentTrack, 'tracktitle', '')),
        ]);
        [$parsedArtist, $parsedTitle] = $this->splitNowPlaying($line);

        $title = $this->firstFilledString([
            trim((string) Arr::get($trackAttributes, 'TITLE', '')),
            trim((string) Arr::get($currentTrack, 'TITLE', '')),
            $parsedTitle,
            trim((string) Arr::get($recentTrack, 'title', '')),
            trim((string) Arr::get($recentTrack, 'tracktitle', '')),
        ]);

        $artist = $this->firstFilledString([
            trim((string) Arr::get($trackAttributes, 'ARTIST', '')),
            trim((string) Arr::get($currentTrack, 'ARTIST', '')),
            $parsedArtist,
            trim((string) Arr::get($recentTrack, 'artist', '')),
            trim((string) Arr::get($recentTrack, 'trackartist', '')),
        ]);

        if ($title === '' && $artist === '' && $line !== '') {
            $title = $line;
        }

        return [
            'title' => $title,
            'artist' => $artist,
            'album' => $this->firstFilledString([
                trim((string) Arr::get($trackAttributes, 'ALBUM', '')),
                trim((string) Arr::get($currentTrack, 'ALBUM', '')),
                trim((string) Arr::get($recentTrack, 'album', '')),
            ]),
            'duration_seconds' => $this->extractPlaybackDurationSeconds($data),
            'elapsed_seconds' => $this->extractPlaybackElapsedSeconds($data),
            'comment' => $this->firstFilledString([
                trim((string) Arr::get($data, 'comment', '')),
                trim((string) Arr::get($trackAttributes, 'COMMENT', '')),
                trim((string) Arr::get($currentTrack, 'COMMENT', '')),
                trim((string) Arr::get($recentTrack, 'comment', '')),
            ]),
            'cover' => $this->firstFilledString([
                trim((string) Arr::get($data, 'links.artwork', '')),
                trim((string) Arr::get($data, 'links.artwork_recent', '')),
                trim((string) Arr::get($data, 'links.stationlogo', '')),
            ]),
            'program_name' => $this->firstFilledString([
                trim((string) Arr::get($data, 'station_name', '')),
                trim((string) Arr::get($data, 'station_title', '')),
            ]),
            'listeners' => $this->extractListeners($data, $currentTrack, $trackAttributes),
        ];
    }

    private function extractPlaybackElapsedSeconds(array $data): int
    {
        $playback = Arr::get($data, 'playback', []);
        $playback = is_array($playback) ? $playback : [];

        return $this->millisecondsToSeconds(Arr::get($playback, 'pos', 0));
    }

    private function extractPlaybackDurationSeconds(array $data): int
    {
        $playback = Arr::get($data, 'playback', []);
        $playback = is_array($playback) ? $playback : [];

        return $this->millisecondsToSeconds(Arr::get($playback, 'len', 0));
    }

    private function millisecondsToSeconds(mixed $value): int
    {
        $milliseconds = (int) $value;
        if ($milliseconds <= 0) {
            return 0;
        }

        return (int) max(0, round($milliseconds / 1000));
    }

    private function extractListeners(array $data, array $currentTrack, array $trackAttributes): int
    {
        foreach ([
            Arr::get($data, 'listeners'),
            Arr::get($data, 'listener_count'),
            Arr::get($data, 'online'),
            Arr::get($currentTrack, 'listeners'),
            Arr::get($trackAttributes, 'LISTENERS'),
            Arr::get($trackAttributes, 'ONLINE'),
        ] as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            return max(0, (int) $value);
        }

        return 0;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitNowPlaying(string $value): array
    {
        if (str_contains($value, ' - ')) {
            [$artist, $title] = explode(' - ', $value, 2);

            return [trim($artist), trim($title)];
        }

        return ['', trim($value)];
    }

    private function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, Program>
     */
    private function loadPrograms()
    {
        if (! $this->hasTable('radio_programs')) {
            return collect();
        }

        $query = Program::query()->active();

        if ($this->hasColumn('radio_programs', 'sort_order')) {
            $query->orderBy('sort_order');
        } elseif ($this->hasColumn('radio_programs', 'numero_episodio')) {
            $query->orderBy('numero_episodio');
        } elseif ($this->hasColumn('radio_programs', 'fecha_emision')) {
            $query->orderByDesc('fecha_emision');
        }

        if ($this->hasColumn('radio_programs', 'name')) {
            $query->orderBy('name');
        } elseif ($this->hasColumn('radio_programs', 'titulo_programa')) {
            $query->orderBy('titulo_programa');
        }

        return $query->get();
    }
}
