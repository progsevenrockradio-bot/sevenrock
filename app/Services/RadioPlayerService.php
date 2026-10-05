<?php

namespace App\Services;

use App\Models\Notice;
use App\Models\PlayHistory;
use App\Models\MasterProgram;
use App\Models\MasterProgramEmision;
use App\Models\Program;
use App\Models\RadioProgram;
use App\Models\Song;
use App\Support\BandProfileMatcher;
use App\Support\BandInfoResolver;
use App\Support\ExternalHttp;
use App\Support\PublicMediaUrl;
use App\Support\ProgramScheduleService;
use App\Support\RadioPlayerStateStore;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Support\Radio\StreamNowPlaying;

class RadioPlayerService
{
    public const MARGIN_BEFORE_MINUTES = 2;
    public const MARGIN_AFTER_MINUTES = 2;

    private const STATUS_CACHE_KEY = 'radio-player-status:v6';

    public function __construct(
        private readonly RadioPlayerStateStore $store,
        private readonly BandInfoResolver $bandInfoResolver,
        private readonly ProgramScheduleService $programScheduleService,
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

        // Emisora activa distinta de RadioBOSS: su "suena ahora" manda sobre lo anterior.
        // Si la activa es RadioBOSS esto devuelve [] y NO cambia absolutamente nada.
        $state = array_replace($state, StreamNowPlaying::state());
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
        $lyrics = $song?->lyrics ?? '';  // From band-info API only
        $programs = $this->loadPrograms();
        $matchedTrackProgram = $this->resolveCurrentProgram($lookupState, $song, $programs);
        $scheduleContext = $this->resolveActiveProgramContext();

        $currentProgram = $matchedTrackProgram ?? $scheduleContext['program'] ?? null;
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

        $isProgramBlock = (bool) ($matchedTrackProgram !== null || !empty($scheduleContext['es_bloque_programa']));
        $resolvedProgramId = $isProgramBlock ? ($matchedTrackProgram?->id ?? $scheduleContext['program_id'] ?? null) : null;
        $resolvedProgramName = $isProgramBlock ? ($matchedTrackProgram?->name ?? $matchedTrackProgram?->titulo_programa ?? $scheduleContext['program_name'] ?? null) : null;
        $resolvedProgramDescription = $isProgramBlock ? ($matchedTrackProgram?->description ?? $matchedTrackProgram?->informacion_fija_programa ?? $scheduleContext['program_description'] ?? null) : null;
        $resolvedProgramHost = $isProgramBlock ? ($matchedTrackProgram?->host ?? $matchedTrackProgram?->conductor ?? $currentProgram?->host ?? $scheduleContext['program_host'] ?? null) : null;
        $resolvedProgramSchedule = $isProgramBlock ? ($matchedTrackProgram?->schedule ?? $currentProgram?->schedule ?? $scheduleContext['program_schedule'] ?? null) : null;

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
            'band_info' => '',  // Enriched via band-info API endpoint
            'band_thumbnail' => '',  // Enriched via band-info API endpoint
            'band_founded_year' => null,  // From band-info API
            'band_founded_label' => '',  // From band-info API
            'comment' => Arr::get($state, 'comment'),
            'band_members' => $song?->band_members ?? Arr::get($state, 'band_members', []),
            'social_links' => ! empty($song?->social_links)
                ? $song->social_links
                : (Arr::get($state, 'social_links', []) ?: []),
            'audio_url' => null, // LIVE streams use the global streamUrl, never override with podcast URL
            'program_id' => $resolvedProgramId,
            'program_name' => $resolvedProgramName,
            'program_description' => $resolvedProgramDescription,
            'program_host' => $resolvedProgramHost,
            'program_schedule' => $resolvedProgramSchedule,
            'es_bloque_programa' => $isProgramBlock,
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
            'es_bloque_programa' => $isProgramBlock,
            'program_id' => $resolvedProgramId,
            'program_name' => $resolvedProgramName,
            'program_description' => $resolvedProgramDescription,
            'track' => $track,
            'program' => $this->programPayload($currentProgram) ?? ($isProgramBlock ? $this->syntheticProgramPayload(
                $resolvedProgramId,
                $resolvedProgramName,
                $resolvedProgramDescription,
                $resolvedProgramHost,
                $resolvedProgramSchedule,
                $cover,
                $scheduleContext['master'] ?? null
            ) : null),
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

    private function resolveCurrentProgram(array $state, ?Song $song, \Illuminate\Support\Collection $programs): ?Program
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

        $rawTitle = trim((string) Arr::get($state, 'title', ''));
        $rawArtist = trim((string) Arr::get($state, 'artist', ''));

        $cleanTitle = $this->cleanMetadataForProgramMatch($rawTitle);
        $cleanArtist = $this->cleanMetadataForProgramMatch($rawArtist);

        // 1. Coincidencia por metadatos en colección de programas/episodios (sin exigir horario)
        if ($cleanTitle !== '' || $cleanArtist !== '') {
            foreach ($programs as $p) {
                $pName = $this->cleanMetadataForProgramMatch((string) ($p->name ?: $p->titulo_programa ?: ''));
                if ($pName !== '' && (($cleanTitle !== '' && str_contains($cleanTitle, $pName)) || ($cleanArtist !== '' && str_contains($cleanArtist, $pName)))) {
                    return $p;
                }
            }
        }

        // 2. Coincidencia por metadatos en MasterProgram (sin exigir horario)
        if ($this->hasTable('master_programs') && ($cleanTitle !== '' || $cleanArtist !== '')) {
            $masters = MasterProgram::query()->where('activo', true)->get();
            $matchedMaster = null;

            foreach ($masters as $m) {
                $mName = $this->cleanMetadataForProgramMatch((string) ($m->name ?: $m->nombre ?: ''));
                $rawHost = preg_replace('/^\s*(conducido\s+por\s*:?\s*|conduce\s*:?\s*|host\s*:?\s*)/iu', '', (string) ($m->host ?: $m->conductor ?: ''));
                $mHost = $this->cleanMetadataForProgramMatch($rawHost);

                $matchName = ($mName !== '' && (($cleanTitle !== '' && str_contains($cleanTitle, $mName)) || ($cleanArtist !== '' && str_contains($cleanArtist, $mName))));
                $matchHost = ($mHost !== '' && strlen($mHost) >= 3 && (($cleanTitle !== '' && str_contains($cleanTitle, $mHost)) || ($cleanArtist !== '' && str_contains($cleanArtist, $mHost))));

                if ($matchName || $matchHost) {
                    $matchedMaster = $m;
                    break;
                }
            }

            if ($matchedMaster) {
                if ($this->hasTable('radio_programs')) {
                    $latestEpisode = RadioProgram::query()
                        ->where('master_program_id', $matchedMaster->id)
                        ->orderByDesc('fecha_emision')
                        ->orderByDesc('id')
                        ->first();

                    if ($latestEpisode) {
                        return Program::query()->find($latestEpisode->getKey());
                    }
                }

                $dummy = new Program();
                $dummy->id = $matchedMaster->id;
                $dummy->exists = true;
                $dummy->fill([
                    'name' => $matchedMaster->name ?: $matchedMaster->nombre,
                    'description' => $matchedMaster->description,
                    'host' => $matchedMaster->host,
                    'cover_image' => $matchedMaster->live_image_url ?: $matchedMaster->caratula_url,
                    'schedule' => $matchedMaster->schedule,
                ]);
                $dummy->setAttribute('id', $matchedMaster->id);
                return $dummy;
            }
        }

        return null;
    }

    private function cleanMetadataForProgramMatch(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        // Quitar prefijos numéricos o de fecha al inicio como "06.12.- ", "01 - ", "12. "
        $text = preg_replace('/^\s*(?:\d+[\.\-\/]\d+[\.\-\/]?\s*[\.\-]?\s*|\d+\s*[\.\-]\s*)+/iu', '', $text) ?? $text;

        $ascii = \Illuminate\Support\Str::ascii($text);
        $lower = mb_strtolower($ascii);
        $clean = preg_replace('/[^a-z0-9]+/iu', ' ', $lower) ?? $lower;

        return trim(preg_replace('/\s+/', ' ', $clean) ?? $clean);
    }

    /**
     * Returns true only if the episode's associated master_program is currently on-air.
     * Used to prevent stale/inactive episodes from triggering the program block mode.
     */
    private function isProgramCurrentlyOnAir(Program $program): bool
    {
        if (! $this->hasTable('master_programs')) {
            return false;
        }

        $masterProgramId = $program->master_program_id ?? null;
        if (! $masterProgramId) {
            return false;
        }

        $master = MasterProgram::query()->find((int) $masterProgramId);
        if (! $master || ! $master->activo) {
            return false;
        }

        return $this->isMasterOnAir($master, Carbon::now(config('app.timezone')));
    }

    /**
     * @return array{es_bloque_programa:bool,program_id:int|null,program_name:string|null,program_description:string|null,program:?Program}
     */
    private function resolveActiveProgramContext(): array
    {
        $empty = [
            'es_bloque_programa' => false,
            'program_id' => null,
            'program_name' => null,
            'program_description' => null,
            'program' => null,
        ];

        if (! $this->hasTable('master_programs') || ! $this->hasTable('radio_programs')) {
            return $empty;
        }

        $now = Carbon::now(config('app.timezone'));
        $episodes = RadioProgram::query()
            ->with('masterProgram')
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->get();

        /** @var RadioProgram $episode */
        foreach ($episodes as $episode) {
            $master = $episode->masterProgram;
            if (! $master || ! $master->activo) {
                continue;
            }

            if (! $this->isEpisodeOnAir($episode, $master, $now)) {
                continue;
            }

            $program = Program::query()->find($episode->id);

            return [
                'es_bloque_programa' => true,
                'program_id' => (int) $episode->id,
                'program_name' => $this->firstFilledString([
                    $episode->titulo_programa,
                    $episode->live_title,
                    $master->name,
                    $master->nombre,
                ]),
                'program_description' => $this->firstFilledString([
                    $episode->informacion_fija_programa,
                    $episode->live_description,
                    $episode->resena,
                    $episode->comentario_episodio,
                    $master->description,
                    $master->live_description,
                    $master->comentario_predeterminado,
                ]),
                'program' => $program,
                'program_host' => $this->firstFilledString([
                    $program?->host,
                    $program?->conductor,
                    $master->host,
                    $master->conductor,
                ]),
                'program_schedule' => $this->firstFilledString([
                    $program?->schedule,
                    $master->schedule,
                ]),
                'master' => $master,
            ];
        }

        foreach (MasterProgram::query()->where('activo', true)->orderBy('dia_transmision')->orderBy('hora_transmision')->get() as $master) {
            if (! $this->isMasterOnAir($master, $now)) {
                continue;
            }

            $episode = $episodes->firstWhere('master_program_id', (int) $master->id);
            $program = $episode ? Program::query()->find($episode->id) : null;

            return [
                'es_bloque_programa' => true,
                'program_id' => $episode ? (int) $episode->id : (int) $master->id,
                'program_name' => $this->firstFilledString([
                    $episode?->titulo_programa,
                    $episode?->live_title,
                    $master->name,
                    $master->nombre,
                ]),
                'program_description' => $this->firstFilledString([
                    $episode?->informacion_fija_programa,
                    $episode?->live_description,
                    $episode?->resena,
                    $episode?->comentario_episodio,
                    $master->description,
                    $master->live_description,
                    $master->comentario_predeterminado,
                ]),
                'program' => $program,
                'program_host' => $this->firstFilledString([
                    $program?->host,
                    $program?->conductor,
                    $master->host,
                    $master->conductor,
                ]),
                'program_schedule' => $this->firstFilledString([
                    $program?->schedule,
                    $master->schedule,
                ]),
                'master' => $master,
            ];
        }

        return $empty;
    }

    private function isMasterOnAir(MasterProgram $master, Carbon $now): bool
    {
        if (! $master->activo) {
            return false;
        }

        $timezone = $this->normalizeTimezone((string) ($master->timezone ?: config('app.timezone')));
        $localNow = $now->copy()->setTimezone($timezone);

        if ($master->live_starts_at && $master->live_ends_at) {
            $start = Carbon::parse((string) $master->live_starts_at, $timezone);
            $end = Carbon::parse((string) $master->live_ends_at, $timezone);

            if ($end->lessThan($start)) {
                return $localNow->greaterThanOrEqualTo($start) || $localNow->lessThanOrEqualTo($end);
            }

            return $localNow->betweenIncluded($start, $end);
        }

        $currentDay = $this->currentDayKey($localNow);

        $isLegacyActive = false;
        if (strtoupper(trim((string) $master->dia_transmision)) === $currentDay) {
            $isLegacyActive = $this->isWindowActive(
                $localNow,
                (string) $master->hora_transmision,
                '',
                (int) $master->duracion_minutos,
                $this->programScheduleService->nextProgramStartFor($master, $localNow)
            );
        }

        if ($isLegacyActive) {
            return true;
        }

        $emisiones = $master->emisiones()->where('activo', true)->get();
        foreach ($emisiones as $emision) {
            if (strtoupper(trim((string) $emision->dia_semana)) !== $currentDay) {
                continue;
            }

            if ($this->isEmisionWindowActive(
                $localNow,
                $emision,
                $this->programScheduleService->nextProgramStartFor($master, $localNow)
            )) {
                return true;
            }
        }

        return false;
    }

    private function isEpisodeOnAir(RadioProgram $episode, MasterProgram $master, Carbon $now): bool
    {
        $timezone = $this->normalizeTimezone((string) ($master->timezone ?: config('app.timezone')));
        $localNow = $now->copy()->setTimezone($timezone);

        if ($episode->fecha_emision) {
            $episodeDate = Carbon::parse((string) $episode->fecha_emision, $timezone)->toDateString();
            if ($episodeDate !== $localNow->toDateString()) {
                return false;
            }
        } else {
            $daySource = strtoupper(trim((string) ($episode->dia_transmision ?: $master->dia_transmision)));
            if ($daySource !== '' && $daySource !== $this->currentDayKey($localNow)) {
                return false;
            }
        }

        if ($master->live_starts_at && $master->live_ends_at) {
            $start = Carbon::parse((string) $master->live_starts_at, $timezone);
            $end = Carbon::parse((string) $master->live_ends_at, $timezone);

            if ($end->lessThan($start)) {
                return $localNow->greaterThanOrEqualTo($start) || $localNow->lessThanOrEqualTo($end);
            }

            return $localNow->betweenIncluded($start, $end);
        }

        $window = $this->resolveEpisodeWindow($episode, $master, $localNow);
        if ($window === null) {
            return false;
        }

        [$start, $end] = $window;

        return $localNow->betweenIncluded($start, $end);
    }

    public function isEmisionWindowActive(\Carbon\CarbonInterface $moment, MasterProgramEmision $emision, ?\Carbon\CarbonInterface $cutoff = null): bool
    {
        $moment = Carbon::instance($moment);
        $scheduledStart = $this->parseTimeToCarbon($moment, (string) $emision->hora_inicio);
        if ($scheduledStart === null) {
            return false;
        }

        if (!empty($emision->duracion_segundos) && $emision->duracion_segundos > 0) {
            $start = $scheduledStart->copy()->subMinutes(self::MARGIN_BEFORE_MINUTES);
            $duracion = (int) ceil($emision->duracion_segundos / 60) + self::MARGIN_AFTER_MINUTES;
            $end = $scheduledStart->copy()->addMinutes($duracion);
        } else {
            $start = $scheduledStart;
            $durationMinutes = max(15, (int) ($emision->duracion_minutos ?? 120));
            $end = $start->copy()->addMinutes($durationMinutes);
        }

        if ($cutoff instanceof Carbon && $cutoff->greaterThan($start) && $cutoff->lessThan($end)) {
            $end = $cutoff->copy();
        }

        return $moment->betweenIncluded($start, $end);
    }

    private function isWindowActive(Carbon $moment, string $startTime, string $endTime, int $durationMinutes, ?Carbon $cutoff = null): bool
    {
        $start = $this->parseTimeToCarbon($moment, $startTime);
        if ($start === null) {
            return false;
        }

        $cleanEndTime = trim($endTime);
        $end = $this->parseTimeToCarbon($moment, $cleanEndTime);
        if ($end === null) {
            if ($durationMinutes <= 0) {
                return false;
            }
            $end = $start->copy()->addMinutes($durationMinutes);
        }

        if ($cleanEndTime !== '' && $end->lessThan($start)) {
            $end = $end->copy()->addDay();
        }

        if ($cutoff instanceof Carbon && $cutoff->greaterThan($start) && $cutoff->lessThan($end)) {
            $end = $cutoff->copy();
        }

        return $moment->betweenIncluded($start, $end);
    }

    /**
     * @return array{0:Carbon,1:Carbon}|null
     */
    private function resolveEpisodeWindow(RadioProgram $episode, MasterProgram $master, Carbon $reference): ?array
    {
        $timezone = $this->normalizeTimezone((string) ($master->timezone ?: config('app.timezone')));
        $baseDate = $episode->fecha_emision
            ? Carbon::parse((string) $episode->fecha_emision, $timezone)->startOfDay()
            : $reference->copy()->setTimezone($timezone)->startOfDay();

        $start = $this->parseTimeToCarbon($baseDate, (string) ($episode->hora_inicio ?: $master->hora_transmision ?: ''));
        if ($start === null) {
            return null;
        }

        $currentDay = $this->currentDayKey($reference);
        $emision = $master->emisiones()
            ->where('activo', true)
            ->where('dia_semana', $currentDay)
            ->first();

        $duracionRealSegundos = $emision?->duracion_segundos ?? ((int) ($episode->duration_seconds ?? 0) > 0 ? (int) $episode->duration_seconds : null);

        $endTime = trim((string) ($episode->hora_fin ?: ''));
        if ($endTime !== '') {
            $end = $this->parseTimeToCarbon($baseDate, $endTime);
            if ($end instanceof Carbon && $end->lessThan($start)) {
                $end = $end->copy()->addDay();
            }
        } elseif ($duracionRealSegundos && $duracionRealSegundos > 0) {
            $scheduledStart = $start->copy();
            $start = $start->copy()->subMinutes(self::MARGIN_BEFORE_MINUTES);
            $duracionMinutos = (int) ceil($duracionRealSegundos / 60) + self::MARGIN_AFTER_MINUTES;
            $end = $scheduledStart->copy()->addMinutes($duracionMinutos);
        } elseif ((int) ($master->duracion_minutos ?? 0) > 0) {
            $end = $start->copy()->addMinutes((int) $master->duracion_minutos);
        } else {
            return null;
        }

        if (! $end instanceof Carbon) {
            return null;
        }

        $cutoff = $this->programScheduleService->nextProgramStartFor($master, $reference);
        if ($cutoff instanceof Carbon && $cutoff->greaterThan($start) && $cutoff->lessThan($end)) {
            $end = $cutoff->copy();
        }

        return [$start, $end];
    }

    private function parseTimeToCarbon(Carbon $base, string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $matches) !== 1) {
            return null;
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];
        $seconds = (int) ($matches[3] ?? 0);

        return $base->copy()->startOfDay()->setTime($hours, $minutes, $seconds);
    }

    private function currentDayKey(Carbon $moment): string
    {
        return match ($moment->dayOfWeekIso) {
            1 => 'LUNES',
            2 => 'MARTES',
            3 => 'MIERCOLES',
            4 => 'JUEVES',
            5 => 'VIERNES',
            6 => 'SABADO',
            7 => 'DOMINGO',
            default => 'LUNES',
        };
    }

    private function normalizeTimezone(string $timezone): string
    {
        $timezone = trim($timezone);

        return $timezone !== '' ? $timezone : config('app.timezone');
    }

    private function resolveNextProgram(?Program $currentProgram, \Illuminate\Support\Collection $programs, array $remoteUpcomingPrograms = []): ?Program
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
            'schedule_time' => $program->schedule_time,
            'cover' => $program->cover_url,
            'social_links' => $program->social_links ?? [],
        ];
    }

    private function syntheticProgramPayload(
        ?int $id,
        ?string $name,
        ?string $description,
        ?string $host,
        ?string $schedule,
        ?string $fallbackCover = null,
        ?MasterProgram $master = null
    ): array {
        if (! $master && $id && $this->hasTable('master_programs')) {
            $master = MasterProgram::query()->find($id);
        }

        $cover = null;
        if ($master) {
            $masterCover = $master->cover_url ?: $master->live_image_url ?: $master->caratula_url;
            if (filled($masterCover)) {
                $cover = $this->resolveCover($masterCover);
            }
        }

        if (! filled($cover)) {
            $cover = $fallbackCover ? $this->resolveCover($fallbackCover) : null;
        }

        return [
            'id' => $id,
            'slug' => \Illuminate\Support\Str::slug($name ?: 'programa'),
            'name' => (string) ($name ?? ''),
            'description' => (string) ($description ?? ''),
            'host' => (string) ($host ?? ''),
            'schedule' => (string) ($schedule ?? ''),
            'schedule_time' => (string) ($schedule ?? ''),
            'cover' => $cover,
            'social_links' => [],
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
