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
use App\Support\Radio\StreamMetadataReader;

class RadioPlayerService
{
    public const MARGIN_BEFORE_MINUTES = 2;
    public const MARGIN_AFTER_MINUTES = 2;
    public const MARGIN_EPISODE_MINUTES = 30;

    private const STATUS_CACHE_KEY = 'radio-player-status:v6';

    private StreamMetadataReader $streamReader;

    /**
     * @var array{
     *     match_found: bool,
     *     match_via: string|null,
     *     matched_program: string|null,
     *     matched_program_id: int|null,
     *     matched_episode: string|null,
     *     matched_episode_id: int|null,
     *     vias: array{
     *         fichero: string,
     *         caratula: string,
     *         album: string,
     *         texto: string,
     *         horario: string
     *     }
     * }
     */
    private array $lastDiagnosis = [
        'match_found' => false,
        'match_via' => null,
        'matched_program' => null,
        'matched_program_id' => null,
        'matched_episode' => null,
        'matched_episode_id' => null,
        'vias' => [
            'fichero' => 'ninguno',
            'caratula' => 'ninguna',
            'album' => 'ninguno',
            'texto' => 'ninguno',
            'horario' => 'ninguno',
        ],
    ];

    public function __construct(
        private readonly RadioPlayerStateStore $store,
        private readonly BandInfoResolver $bandInfoResolver,
        private readonly ProgramScheduleService $programScheduleService,
        ?StreamMetadataReader $streamReader = null,
    ) {
        $this->streamReader = $streamReader ?? new StreamMetadataReader();
    }

    public function setStreamReader(StreamMetadataReader $reader): void
    {
        $this->streamReader = $reader;
    }

    public function getLastDiagnosis(): array
    {
        return $this->lastDiagnosis;
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
        $this->lastDiagnosis = [
            'match_found' => false,
            'match_via' => null,
            'matched_program' => null,
            'matched_program_id' => null,
            'matched_episode' => null,
            'matched_episode_id' => null,
            'vias' => [
                'fichero' => 'ninguno',
                'caratula' => 'ninguna',
                'album' => 'ninguno',
                'texto' => 'ninguno',
                'horario' => 'ninguno',
            ],
        ];

        $defaults = config('player.defaults');
        $defaultTitle = $defaults['title'] ?? 'Transmisión oficial';
        $defaultArtist = $defaults['artist'] ?? 'Seven Rock Radio';

        // -------------------------------------------------------------
        // 1. LECTURA DE LAS 3 FUENTES (sin que ninguna decida aún)
        // -------------------------------------------------------------
        // Fuente 2: Webhook / estado guardado de RadioBOSS
        $stored = $this->currentState();

        // Fuente 3: API / status-json de RadioBOSS (y StreamNowPlaying para otros servidores)
        $api = array_replace($this->remoteNowPlayingState(), StreamNowPlaying::state());

        // Fuente 1: Stream de radio (ICY)
        $hintTitle = $this->firstFilledString([$stored['title'] ?? null, $api['title'] ?? null]);
        $hintArtist = $this->firstFilledString([$stored['artist'] ?? null, $api['artist'] ?? null]);
        $icy = $this->streamReader->read(false, $hintTitle, $hintArtist);

        // Registro de trazabilidad por campo
        $metaSources = [
            'title' => 'default',
            'artist' => 'default',
            'program' => 'none',
            'cover' => 'default',
            'duration' => 'none',
            'elapsed' => 'none',
        ];

        // -------------------------------------------------------------
        // Preparación del lookupState para matching de Canción y Programa
        // -------------------------------------------------------------
        $rawTitle = $this->firstFilledString([
            $icy['title'] ?? null,
            $stored['title'] ?? null,
            $stored['casttitle'] ?? null,
            $stored['tracktitle'] ?? null,
            $api['title'] ?? null,
        ]);

        $rawArtist = $this->firstFilledString([
            $icy['artist'] ?? null,
            $stored['artist'] ?? null,
            $stored['trackartist'] ?? null,
            $stored['program_name'] ?? null,
            $api['artist'] ?? null,
        ]);

        $rawCover = $this->firstFilledString([
            $stored['cover'] ?? null,
            $stored['cover_image'] ?? null,
            $stored['artwork'] ?? null,
            $stored['image'] ?? null,
            $api['cover'] ?? null,
        ]);

        $rawFilename = $this->firstFilledString([
            $stored['filename'] ?? null,
            $stored['file'] ?? null,
            $stored['song'] ?? null,
            $stored['raw']['filename'] ?? null,
            $stored['raw']['file'] ?? null,
            $stored['raw']['song'] ?? null,
            $api['filename'] ?? null,
            $api['file'] ?? null,
            $icy['raw'] ?? null,
        ]);

        $rawAlbum = $this->firstFilledString([
            $stored['album'] ?? null,
            $stored['raw']['album'] ?? null,
            $api['album'] ?? null,
        ]);

        $lookupState = array_replace($api, $stored);
        $lookupState['title'] = $rawTitle;
        $lookupState['artist'] = $rawArtist;
        $lookupState['cover'] = $rawCover;
        $lookupState['filename'] = $rawFilename;
        $lookupState['file'] = $rawFilename;
        $lookupState['album'] = $rawAlbum;

        $song = $this->resolveSong($lookupState);
        $programs = $this->loadPrograms();
        $matchedTrackProgram = $this->resolveCurrentProgram($lookupState, $song, $programs);
        $scheduleContext = $this->resolveActiveProgramContext();

        // Registrar diagnóstico de horario
        if ($matchedTrackProgram === null && ! empty($scheduleContext['es_bloque_programa'])) {
            $this->lastDiagnosis['match_found'] = true;
            $this->lastDiagnosis['match_via'] = 'horario';
            $this->lastDiagnosis['matched_program'] = $scheduleContext['program_name'];
            $this->lastDiagnosis['matched_program_id'] = $scheduleContext['program_id'];
            $this->lastDiagnosis['vias']['horario'] = 'emisión activa: ' . $scheduleContext['program_name'];
        } elseif ($matchedTrackProgram !== null) {
            $this->lastDiagnosis['vias']['horario'] = ! empty($scheduleContext['es_bloque_programa'])
                ? 'emisión activa: ' . $scheduleContext['program_name']
                : 'no evaluado por coincidencia previa';
        } else {
            $this->lastDiagnosis['vias']['horario'] = 'sin emisión activa en ventana horaria';
        }

        $currentProgram = $matchedTrackProgram ?? $scheduleContext['program'] ?? null;
        $nextProgram = $this->resolveNextProgram(
            $currentProgram,
            $programs,
            $this->remoteUpcomingPrograms()
        );
        $notices = $this->hasTable('notices')
            ? Notice::query()->active()->orderBy('sort_order')->get()
            : collect();

        // «Canción real» = título del stream no vacío, distinto del genérico de
        // fallback, y con artista no vacío distinto del genérico.
        $isDefaultTitle = ($rawTitle === '' || mb_strtolower($rawTitle) === mb_strtolower($defaultTitle));
        $isDefaultArtist = ($rawArtist === '' || mb_strtolower($rawArtist) === mb_strtolower($defaultArtist));
        $hasRealSong = ! $isDefaultTitle && ! $isDefaultArtist;

        $hasStoredProgram = filled($stored['program_id'] ?? null) || filled($stored['program_name'] ?? null);
        $isProgramBlock = (bool) ($hasStoredProgram || $matchedTrackProgram !== null || (! $hasRealSong && ! empty($scheduleContext['es_bloque_programa'])));

        // Log cuando no hay match (nivel info, sin ruido)
        if ($currentProgram === null && ! $isProgramBlock) {
            $throttleKey = 'log.no_prog_match.' . md5(
                ($this->lastDiagnosis['vias']['fichero'] ?? '') . '|' .
                ($this->lastDiagnosis['vias']['caratula'] ?? '') . '|' .
                ($this->lastDiagnosis['vias']['texto'] ?? '') . '|' .
                Carbon::now()->format('YmdH')
            );
            if (Cache::add($throttleKey, 1, 300)) {
                \Illuminate\Support\Facades\Log::info(sprintf(
                    'Programa no identificado. Vías probadas: fichero=<%s> carátula=<%s> texto=<%s> horario=<%s>',
                    $this->lastDiagnosis['vias']['fichero'] ?? 'ninguno',
                    $this->lastDiagnosis['vias']['caratula'] ?? 'ninguna',
                    $this->lastDiagnosis['vias']['texto'] ?? 'ninguno',
                    $this->lastDiagnosis['vias']['horario'] ?? 'ninguno'
                ));
            }
        }

        // -------------------------------------------------------------
        // 2. RESOLUCIÓN POR CAMPO (según la tabla de prioridades)
        // -------------------------------------------------------------

        // CAMPO: program_id / program_name / program_host
        // 1ª opción: program_id del estado
        // 2ª opción: vía fichero / carátula / álbum / texto
        // 3ª opción: horario (±30 min)
        // Último recurso: vacío
        $resolvedProgramId = null;
        $resolvedProgramName = null;
        $resolvedProgramDescription = null;
        $resolvedProgramHost = null;
        $resolvedProgramSchedule = null;

        if ($isProgramBlock) {
            $progSource = $matchedTrackProgram ?? $scheduleContext['program'] ?? null;
            $masterSource = $matchedTrackProgram?->masterProgram ?? $scheduleContext['master'] ?? null;

            $resolvedProgramId = $stored['program_id'] ?? $progSource?->id ?? $scheduleContext['program_id'] ?? null;
            $resolvedProgramName = $this->firstFilledString([
                $stored['program_name'] ?? null,
                $masterSource?->name,
                $masterSource?->nombre,
                $progSource?->name,
                $progSource?->titulo_programa,
                $scheduleContext['program_name'] ?? null,
            ]);
            $resolvedProgramDescription = $this->firstFilledString([
                $stored['program_description'] ?? null,
                $progSource?->live_description,
                $progSource?->description,
                $progSource?->informacion_fija_programa,
                $masterSource?->description,
                $scheduleContext['program_description'] ?? null,
            ]);
            $rawHost = $this->firstFilledString([
                $stored['program_host'] ?? null,
                $progSource?->host,
                $progSource?->conductor,
                $masterSource?->host,
                $masterSource?->conductor,
                $scheduleContext['program_host'] ?? null,
            ]);
            // Limpiar prefijos de host: «Conducido por:», «Conductor:», etc.
            $resolvedProgramHost = $this->cleanProgramHost($rawHost);

            $resolvedProgramSchedule = $this->firstFilledString([
                $stored['program_schedule'] ?? null,
                $progSource?->schedule,
                $masterSource?->schedule,
                $scheduleContext['program_schedule'] ?? null,
            ]);

            // Determinar fuente del programa para meta_sources
            if (! empty($stored['program_id']) || ! empty($stored['program_name'])) {
                $metaSources['program'] = ! empty($stored['program_id']) ? 'store:program_id' : 'store:program_name';
            } elseif ($matchedTrackProgram !== null) {
                $via = $this->lastDiagnosis['match_via'] ?? 'matched';
                $metaSources['program'] = 'store:' . $via;
            } elseif (! empty($scheduleContext['es_bloque_programa'])) {
                $metaSources['program'] = 'schedule';
            }
        }

        // CAMPO: title & artist
        // Dato: title
        // 1ª opción: ICY del stream
        // 2ª opción: estado guardado (webhook/API)
        // 3ª opción: programa identificado (live_title)
        // Último recurso: defaults.title
        // Dato: artist
        // 1ª opción: ICY del stream
        // 2ª opción: estado guardado
        // 3ª opción: nombre del programa / conductor
        // Último recurso: defaults.artist

        $episodeTitle = '';
        $programArtist = '';
        if ($isProgramBlock) {
            $progSource = $matchedTrackProgram ?? $scheduleContext['program'] ?? null;
            $episodeTitle = $this->firstFilledString([
                $progSource?->live_title,
                $progSource?->titulo_programa,
                $resolvedProgramName,
            ]);
            $programArtist = $this->firstFilledString([
                $resolvedProgramName,
                $resolvedProgramHost,
                $defaultArtist,
            ]);
        }

        $trackTitle = '';
        $trackArtist = '';

        if ($isProgramBlock && ($matchedTrackProgram !== null || ! $hasRealSong)) {
            // Programa al aire identificado: program_name + host + carátula del programa,
            // aunque el ICY traiga el nombre del fichero.
            $trackTitle = $episodeTitle !== '' ? $episodeTitle : ($rawTitle !== '' ? $rawTitle : $defaultTitle);
            $trackArtist = $programArtist !== '' ? $programArtist : ($rawArtist !== '' ? $rawArtist : $defaultArtist);
            $metaSources['title'] = $episodeTitle !== '' ? 'program' : 'store';
            $metaSources['artist'] = $programArtist !== '' ? 'program' : 'store';
        } else {
            // Emisión de música habitual
            // 1ª opción: ICY del stream
            $icyTitle = trim((string) ($icy['title'] ?? ''));
            $icyArtist = trim((string) ($icy['artist'] ?? ''));
            $icyHasReal = ($icyTitle !== '' && mb_strtolower($icyTitle) !== mb_strtolower($defaultTitle))
                || ($icyArtist !== '' && mb_strtolower($icyArtist) !== mb_strtolower($defaultArtist));

            if ($icyHasReal) {
                $trackTitle = $icyTitle !== '' ? $icyTitle : ($stored['title'] ?? $api['title'] ?? $defaultTitle);
                $trackArtist = $icyArtist !== '' ? $icyArtist : ($stored['artist'] ?? $api['artist'] ?? $defaultArtist);
                $metaSources['title'] = $icyTitle !== '' ? 'icy' : 'default';
                $metaSources['artist'] = $icyArtist !== '' ? 'icy' : 'default';
            } else {
                // 2ª opción: estado guardado (webhook / API)
                $storedTitle = trim((string) ($stored['title'] ?? ''));
                $storedArtist = trim((string) ($stored['artist'] ?? ''));
                $apiTitle = trim((string) ($api['title'] ?? ''));
                $apiArtist = trim((string) ($api['artist'] ?? ''));

                if ($storedTitle !== '' || $storedArtist !== '') {
                    $trackTitle = $storedTitle !== '' ? $storedTitle : $defaultTitle;
                    $trackArtist = $storedArtist !== '' ? $storedArtist : $defaultArtist;
                    $metaSources['title'] = $storedTitle !== '' ? 'store' : 'default';
                    $metaSources['artist'] = $storedArtist !== '' ? 'store' : 'default';
                } elseif ($apiTitle !== '' || $apiArtist !== '') {
                    $trackTitle = $apiTitle !== '' ? $apiTitle : $defaultTitle;
                    $trackArtist = $apiArtist !== '' ? $apiArtist : $defaultArtist;
                    $metaSources['title'] = $apiTitle !== '' ? 'api' : 'default';
                    $metaSources['artist'] = $apiArtist !== '' ? 'api' : 'default';
                } elseif ($episodeTitle !== '' || $programArtist !== '') {
                    // 3ª opción: programa identificado
                    $trackTitle = $episodeTitle !== '' ? $episodeTitle : $defaultTitle;
                    $trackArtist = $programArtist !== '' ? $programArtist : $defaultArtist;
                    $metaSources['title'] = 'program';
                    $metaSources['artist'] = 'program';
                } else {
                    // Último recurso: defaults
                    $trackTitle = $defaultTitle;
                    $trackArtist = $defaultArtist;
                    $metaSources['title'] = 'default';
                    $metaSources['artist'] = 'default';
                }
            }
        }

        // CAMPO: cover (+ cover_is_fallback)
        // Si es bloque de programa: carátula del programa
        // Si es canción real:
        //   1. songs.cover_image (la carátula del disco de nuestro catálogo)
        //   2. Artwork del tema en curso de RadioBOSS con cache-buster
        //   3. Imagen de la banda (radio_artists.image_path → logo_path)
        //   4. Logo del tema — último recurso, con cover_is_fallback = true
        $cover = null;
        $coverIsFallback = false;

        if ($isProgramBlock) {
            $progCover = $this->resolveProgramCoverCandidate($currentProgram, $scheduleContext)
                ?: ($stored['cover'] ?? $stored['cover_image'] ?? $stored['artwork'] ?? null);
            if ($progCover && ! $this->isFallbackCover($progCover)) {
                $cover = $this->resolveCover($progCover);
                $metaSources['cover'] = 'program';
                $coverIsFallback = false;
            }
        }

        if (! $cover) {
            // 1. songs.cover_image (la carátula del disco de nuestro catálogo)
            if ($song && filled($song->cover_image)) {
                $resolvedSongCover = $this->resolveCover($song->cover_image);
                if (! $this->isFallbackCover($resolvedSongCover)) {
                    $cover = $resolvedSongCover;
                    $metaSources['cover'] = 'song';
                    $coverIsFallback = false;
                }
            }
        }

        if (! $cover) {
            // 2. Artwork del tema en curso de RadioBOSS: https://c30.radioboss.fm/w/artwork/{station_id}
            // (solo si es artwork del tema, no el logo de la emisora), con cache-buster.
            $candidateArtwork = $this->firstFilledString([
                $stored['cover'] ?? null,
                $stored['cover_image'] ?? null,
                $stored['artwork'] ?? null,
                $api['cover'] ?? null,
            ]);

            if ($candidateArtwork !== '') {
                $resolvedStateCover = $this->resolveCover($candidateArtwork);
                if (! $this->isFallbackCover($resolvedStateCover)) {
                    if (str_contains($resolvedStateCover, '/w/artwork/')) {
                        $cacheBuster = substr(md5($trackTitle . '|' . $trackArtist), 0, 8);
                        $resolvedStateCover .= (str_contains($resolvedStateCover, '?') ? '&' : '?') . 'cb=' . $cacheBuster;
                    }
                    $cover = $resolvedStateCover;
                    $metaSources['cover'] = filled($stored['cover'] ?? $stored['artwork'] ?? null) ? 'store' : 'api';
                    $coverIsFallback = false;
                }
            }
        }

        if (! $cover) {
            // 3. Imagen de la banda (radio_artists.image_path → logo_path)
            $bandProfile = $this->resolveBandProfile($lookupState, $song);
            if ($bandProfile) {
                $bandImage = $bandProfile->image_path ?: $bandProfile->logo_path;
                if (filled($bandImage)) {
                    $resolvedBandCover = $this->resolveCover($bandImage);
                    if (! $this->isFallbackCover($resolvedBandCover)) {
                        $cover = $resolvedBandCover;
                        $metaSources['cover'] = 'band';
                        $coverIsFallback = false;
                    }
                }
            }
        }

        if (! $cover) {
            // 4. Logo del tema — último recurso, con cover_is_fallback = true
            $cover = $this->resolveCover($defaults['cover'] ?? 'assets/lucille/album3.jpg');
            $metaSources['cover'] = 'default';
            $coverIsFallback = true;
        }

        // CAMPO: duration / elapsed
        // 1ª opción: estado (started_at + duración)
        // 2ª opción: API de RadioBOSS
        // 3ª opción: derivado
        // Último recurso: 0
        $duration = 0;
        $elapsed = 0;

        if ((int) ($stored['duration_seconds'] ?? 0) > 0) {
            $duration = (int) $stored['duration_seconds'];
            $metaSources['duration'] = 'store';
        } elseif ((int) ($api['duration_seconds'] ?? 0) > 0) {
            $duration = (int) $api['duration_seconds'];
            $metaSources['duration'] = 'api';
        } elseif ($song && (int) ($song->duration_seconds ?? 0) > 0) {
            $duration = (int) $song->duration_seconds;
            $metaSources['duration'] = 'song';
        }

        if (filled($stored['started_at'] ?? null)) {
            try {
                $elapsed = max(0, Carbon::parse((string) $stored['started_at'])->diffInSeconds(now()));
                $metaSources['elapsed'] = 'store';
            } catch (\Throwable) {
                $elapsed = 0;
            }
        } elseif ((int) ($api['elapsed_seconds'] ?? 0) > 0) {
            $elapsed = (int) $api['elapsed_seconds'];
            $metaSources['elapsed'] = 'api';
        }

        if ($duration > 0 && $elapsed > $duration) {
            $elapsed = 0;
        }

        // CAMPO: listeners, queue, next_program
        // 1ª opción: API / status-json
        // Último recurso: vacío
        $listeners = (int) ($api['listeners'] ?? $stored['listeners'] ?? 0);

        // CAMPO: signature
        // calculada por el resolvedor (título+artista+carátula+programa)
        $trackSignature = $this->trackSignature($trackTitle, $trackArtist, $cover, $resolvedProgramName);

        // Determinación del meta_source principal
        if ($metaSources['title'] === 'icy' || $metaSources['artist'] === 'icy') {
            $metaSource = 'icy';
        } elseif ($isProgramBlock && ($metaSources['title'] === 'program' || $metaSources['program'] !== 'none')) {
            $metaSource = 'program';
        } elseif ($metaSources['title'] === 'store' || $metaSources['artist'] === 'store') {
            $metaSource = 'store';
        } elseif ($metaSources['title'] === 'api' || $metaSources['artist'] === 'api') {
            $metaSource = 'api';
        } else {
            $metaSource = 'default';
        }

        $metaUpdatedAt = now()->toIso8601String();
        $lyrics = $song?->lyrics ?? '';

        $track = [
            'id' => $song?->id,
            'title' => $trackTitle !== '' ? $trackTitle : (string) ($defaults['title'] ?? ''),
            'artist' => $trackArtist !== '' ? $trackArtist : (string) ($defaults['artist'] ?? ''),
            'album' => $this->firstFilledString([
                $song?->album,
                $stored['album'] ?? null,
                $api['album'] ?? null,
            ]),
            'cover' => $cover,
            'cover_is_fallback' => $coverIsFallback,
            'meta_source' => $metaSource,
            'meta_sources' => $metaSources,
            'meta_updated_at' => $metaUpdatedAt,
            'lyrics' => is_string($lyrics) ? $lyrics : '',
            'band_info' => '',  // Enriched via band-info API endpoint
            'band_thumbnail' => '',  // Enriched via band-info API endpoint
            'band_founded_year' => null,  // From band-info API
            'band_founded_label' => '',  // From band-info API
            'comment' => $stored['comment'] ?? $api['comment'] ?? null,
            'band_members' => $song?->band_members ?? $stored['band_members'] ?? [],
            'social_links' => ! empty($song?->social_links)
                ? $song->social_links
                : ($stored['social_links'] ?? []),
            'audio_url' => null, // LIVE streams use the global streamUrl, never override with podcast URL
            'program_id' => $resolvedProgramId,
            'program_name' => $resolvedProgramName,
            'program_description' => $resolvedProgramDescription,
            'program_host' => $resolvedProgramHost,
            'program_schedule' => $resolvedProgramSchedule,
            'es_bloque_programa' => $isProgramBlock,
            'is_live' => (bool) ($stored['is_live'] ?? $api['is_live'] ?? $song?->is_live ?? true),
            'signature' => $trackSignature,
            'started_at' => $stored['started_at'] ?? null,
            'published_at' => optional($song?->published_at)->toIso8601String(),
            'duration_seconds' => $duration,
            'elapsed_seconds' => $elapsed,
        ];

        return [
            'stream_url' => config('player.streams.direct'),
            'listen_url' => config('player.streams.listen'),
            'playlist_m3u' => config('player.streams.m3u'),
            'playlist_pls' => config('player.streams.pls'),
            'listeners' => $listeners,
            'es_bloque_programa' => $isProgramBlock,
            'program_id' => $resolvedProgramId,
            'program_name' => $resolvedProgramName,
            'program_description' => $resolvedProgramDescription,
            'meta_source' => $metaSource,
            'meta_sources' => $metaSources,
            'meta_updated_at' => $metaUpdatedAt,
            'cover_is_fallback' => $coverIsFallback,
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
            'updated_at' => $metaUpdatedAt,
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
        // 1. Coincidencia por ID explícito
        if ($programId = Arr::get($state, 'program_id')) {
            $program = $programs->firstWhere('id', (int) $programId);
            if ($program) {
                $this->recordMatch('id', $program->name, (int) $program->id, $program->live_title ?: $program->name, (int) $program->id);
                return $program;
            }
        }

        if ($song?->program_id) {
            $program = $programs->firstWhere('id', (int) $song->program_id);
            if ($program) {
                $this->recordMatch('id', $program->name, (int) $program->id, $program->live_title ?: $program->name, (int) $program->id);
                return $program;
            }
        }

        $rawTitle = trim((string) Arr::get($state, 'title', ''));
        $rawArtist = trim((string) Arr::get($state, 'artist', ''));
        $rawAlbum = trim((string) Arr::get($state, 'album', ''));
        $rawFile = $this->firstFilledString([
            Arr::get($state, 'filename'),
            Arr::get($state, 'file'),
            Arr::get($state, 'song'),
            Arr::get($state, 'raw.filename'),
            Arr::get($state, 'raw.file'),
            Arr::get($state, 'raw.song'),
        ]);
        $rawCover = $this->firstFilledString([
            Arr::get($state, 'cover'),
            Arr::get($state, 'cover_image'),
            Arr::get($state, 'artwork'),
            Arr::get($state, 'image'),
            Arr::get($state, 'raw.cover'),
            Arr::get($state, 'raw.cover_image'),
            Arr::get($state, 'raw.artwork'),
            Arr::get($state, 'raw.image'),
        ]);

        $cleanFile = '';
        if ($rawFile !== '') {
            $path = str_replace('\\', '/', $rawFile);
            $basename = basename($path);
            $withoutExt = preg_replace('/\.[a-zA-Z0-9]{2,5}$/', '', $basename) ?? $basename;
            $cleanFile = $this->cleanMetadataForProgramMatch($withoutExt);
        }

        $cleanCover = $this->cleanCoverBasename($rawCover);
        if ($this->isGenericCover($cleanCover)) {
            $cleanCover = '';
        }

        $cleanAlbum = $this->cleanMetadataForProgramMatch($rawAlbum);
        $cleanTitle = $this->cleanMetadataForProgramMatch($rawTitle);
        $cleanArtist = $this->cleanMetadataForProgramMatch($rawArtist);

        $defaultTitleClean = $this->cleanMetadataForProgramMatch(config('player.defaults.title', 'Transmisión oficial'));
        $defaultArtistClean = $this->cleanMetadataForProgramMatch(config('player.defaults.artist', 'Seven Rock Radio'));
        if ($cleanTitle === $defaultTitleClean) {
            $cleanTitle = '';
        }
        if ($cleanArtist === $defaultArtistClean) {
            $cleanArtist = '';
        }

        $this->lastDiagnosis['vias']['fichero'] = $rawFile !== '' ? "file={$rawFile} clean={$cleanFile}" : 'vacío';
        $this->lastDiagnosis['vias']['caratula'] = $cleanCover !== '' ? "cover={$rawCover} clean={$cleanCover}" : ($rawCover !== '' ? "cover={$rawCover} (genérica o no analizable)" : 'vacía');
        $this->lastDiagnosis['vias']['album'] = $cleanAlbum !== '' ? "album={$rawAlbum} clean={$cleanAlbum}" : 'vacío';
        $this->lastDiagnosis['vias']['texto'] = ($cleanTitle !== '' || $cleanArtist !== '') ? "title={$rawTitle} artist={$rawArtist}" : 'vacío o por defecto';

        $masters = $this->hasTable('master_programs')
            ? MasterProgram::query()->where('activo', true)->get()
            : collect();

        // 2. Vía NOMBRE DE FICHERO: filename/file/song
        if ($cleanFile !== '' && strlen($cleanFile) >= 3) {
            // 2a. Comparar contra episodios / programas
            foreach ($programs as $p) {
                $pLive = $this->cleanMetadataForProgramMatch((string) $p->live_title);
                $pProg = $this->cleanMetadataForProgramMatch((string) ($p->titulo_programa ?: $p->name ?: ''));
                $pSlug = $this->cleanMetadataForProgramMatch((string) ($p->slug ?: ''));

                $matchLive = ($pLive !== '' && strlen($pLive) >= 3 && (str_contains($cleanFile, $pLive) || str_contains($pLive, $cleanFile)));
                $matchProg = ($pProg !== '' && strlen($pProg) >= 3 && (str_contains($cleanFile, $pProg) || str_contains($pProg, $cleanFile)));
                $matchSlug = ($pSlug !== '' && strlen($pSlug) >= 3 && (str_contains($cleanFile, $pSlug) || str_contains($pSlug, $cleanFile)));

                if ($matchLive || $matchProg || $matchSlug) {
                    $p->match_via = 'fichero';
                    $this->recordMatch('fichero', $p->name, (int) $p->id, $p->live_title ?: $p->name, (int) $p->id);
                    return $p;
                }
            }

            // 2b. Comparar contra MasterProgram
            foreach ($masters as $m) {
                $mName = $this->cleanMetadataForProgramMatch((string) ($m->name ?: $m->nombre ?: ''));
                $mSlug = $this->cleanMetadataForProgramMatch((string) ($m->publicSlug() ?: ''));

                $matchName = ($mName !== '' && strlen($mName) >= 3 && (str_contains($cleanFile, $mName) || str_contains($mName, $cleanFile)));
                $matchSlug = ($mSlug !== '' && strlen($mSlug) >= 3 && (str_contains($cleanFile, $mSlug) || str_contains($mSlug, $cleanFile)));

                if ($matchName || $matchSlug) {
                    $episode = $this->matchEpisodeForMaster($m, $cleanFile, $cleanCover);
                    $program = $this->buildProgramFromMasterAndEpisode($m, $episode, 'fichero');
                    $this->recordMatch('fichero', $m->name, (int) $m->id, $episode?->live_title ?: $m->name, $episode?->id);
                    return $program;
                }
            }
        }

        // 3. Vía CARÁTULA: artwork / cover
        if ($cleanCover !== '' && strlen($cleanCover) >= 3) {
            // 3a. Comparar contra episodios / programas
            foreach ($programs as $p) {
                $pCover = $this->cleanCoverBasename((string) ($p->cover_image ?: $p->caratula_programa ?: $p->imagen_episodio ?: ''));
                $pName = $this->cleanMetadataForProgramMatch((string) ($p->name ?: $p->titulo_programa ?: ''));
                $pSlug = $this->cleanMetadataForProgramMatch((string) ($p->slug ?: ''));

                $matchCover = ($pCover !== '' && ! $this->isGenericCover($pCover) && (str_contains($cleanCover, $pCover) || str_contains($pCover, $cleanCover)));
                $matchName = ($pName !== '' && strlen($pName) >= 3 && (str_contains($cleanCover, $pName) || str_contains($pName, $cleanCover)));
                $matchSlug = ($pSlug !== '' && strlen($pSlug) >= 3 && (str_contains($cleanCover, $pSlug) || str_contains($pSlug, $cleanCover)));

                if ($matchCover || $matchName || $matchSlug) {
                    $p->match_via = 'caratula';
                    $this->recordMatch('caratula', $p->name, (int) $p->id, $p->live_title ?: $p->name, (int) $p->id);
                    return $p;
                }
            }

            // 3b. Comparar contra MasterProgram
            foreach ($masters as $m) {
                $mCover1 = $this->cleanCoverBasename((string) ($m->caratula_url ?: ''));
                $mCover2 = $this->cleanCoverBasename((string) ($m->live_image_url ?: ''));
                $mName = $this->cleanMetadataForProgramMatch((string) ($m->name ?: $m->nombre ?: ''));
                $mSlug = $this->cleanMetadataForProgramMatch((string) ($m->publicSlug() ?: ''));

                $matchCover = ($mCover1 !== '' && ! $this->isGenericCover($mCover1) && (str_contains($cleanCover, $mCover1) || str_contains($mCover1, $cleanCover)))
                    || ($mCover2 !== '' && ! $this->isGenericCover($mCover2) && (str_contains($cleanCover, $mCover2) || str_contains($mCover2, $cleanCover)));
                $matchName = ($mName !== '' && strlen($mName) >= 3 && (str_contains($cleanCover, $mName) || str_contains($mName, $cleanCover)));
                $matchSlug = ($mSlug !== '' && strlen($mSlug) >= 3 && (str_contains($cleanCover, $mSlug) || str_contains($mSlug, $cleanCover)));

                if ($matchCover || $matchName || $matchSlug) {
                    $episode = $this->matchEpisodeForMaster($m, $cleanFile, $cleanCover);
                    $program = $this->buildProgramFromMasterAndEpisode($m, $episode, 'caratula');
                    $this->recordMatch('caratula', $m->name, (int) $m->id, $episode?->live_title ?: $m->name, $episode?->id);
                    return $program;
                }
            }
        }

        // 4. Vía ÁLBUM
        if ($cleanAlbum !== '' && strlen($cleanAlbum) >= 3) {
            foreach ($programs as $p) {
                $pName = $this->cleanMetadataForProgramMatch((string) ($p->name ?: $p->titulo_programa ?: ''));
                $pSlug = $this->cleanMetadataForProgramMatch((string) ($p->slug ?: ''));

                if (($pName !== '' && (str_contains($cleanAlbum, $pName) || str_contains($pName, $cleanAlbum))) ||
                    ($pSlug !== '' && (str_contains($cleanAlbum, $pSlug) || str_contains($pSlug, $cleanAlbum)))) {
                    $p->match_via = 'album';
                    $this->recordMatch('album', $p->name, (int) $p->id, $p->live_title ?: $p->name, (int) $p->id);
                    return $p;
                }
            }

            foreach ($masters as $m) {
                $mName = $this->cleanMetadataForProgramMatch((string) ($m->name ?: $m->nombre ?: ''));
                $mSlug = $this->cleanMetadataForProgramMatch((string) ($m->publicSlug() ?: ''));

                if (($mName !== '' && (str_contains($cleanAlbum, $mName) || str_contains($mName, $cleanAlbum))) ||
                    ($mSlug !== '' && (str_contains($cleanAlbum, $mSlug) || str_contains($mSlug, $cleanAlbum)))) {
                    $episode = $this->matchEpisodeForMaster($m, $cleanFile, $cleanCover);
                    $program = $this->buildProgramFromMasterAndEpisode($m, $episode, 'album');
                    $this->recordMatch('album', $m->name, (int) $m->id, $episode?->live_title ?: $m->name, $episode?->id);
                    return $program;
                }
            }
        }

        // 5. Vía TEXTO: title / artist / host
        if ($cleanTitle !== '' || $cleanArtist !== '') {
            foreach ($programs as $p) {
                $pName = $this->cleanMetadataForProgramMatch((string) ($p->name ?: $p->titulo_programa ?: ''));
                $pLive = $this->cleanMetadataForProgramMatch((string) ($p->live_title ?: ''));
                if (($pName !== '' && (($cleanTitle !== '' && str_contains($cleanTitle, $pName)) || ($cleanArtist !== '' && str_contains($cleanArtist, $pName)))) ||
                    ($pLive !== '' && (($cleanTitle !== '' && str_contains($cleanTitle, $pLive)) || ($cleanArtist !== '' && str_contains($cleanArtist, $pLive))))) {
                    $p->match_via = 'texto';
                    $this->recordMatch('texto', $p->name, (int) $p->id, $p->live_title ?: $p->name, (int) $p->id);
                    return $p;
                }
            }

            foreach ($masters as $m) {
                $mName = $this->cleanMetadataForProgramMatch((string) ($m->name ?: $m->nombre ?: ''));
                $rawHost = preg_replace('/^\s*(conducido\s+por\s*:?\s*|conduce\s*:?\s*|host\s*:?\s*)/iu', '', (string) ($m->host ?: $m->conductor ?: ''));
                $mHost = $this->cleanMetadataForProgramMatch($rawHost);

                $matchName = ($mName !== '' && (($cleanTitle !== '' && str_contains($cleanTitle, $mName)) || ($cleanArtist !== '' && str_contains($cleanArtist, $mName))));
                $matchHost = ($mHost !== '' && strlen($mHost) >= 3 && (($cleanTitle !== '' && str_contains($cleanTitle, $mHost)) || ($cleanArtist !== '' && str_contains($cleanArtist, $mHost))));

                if ($matchName || $matchHost) {
                    $episode = $this->matchEpisodeForMaster($m, $cleanFile, $cleanCover);
                    $program = $this->buildProgramFromMasterAndEpisode($m, $episode, 'texto');
                    $this->recordMatch('texto', $m->name, (int) $m->id, $episode?->live_title ?: $m->name, $episode?->id);
                    return $program;
                }
            }
        }

        return null;
    }

    private function matchEpisodeForMaster(MasterProgram $master, string $cleanFile = '', string $cleanCover = ''): ?RadioProgram
    {
        if (! $this->hasTable('radio_programs')) {
            return null;
        }

        $episodes = RadioProgram::query()
            ->where('master_program_id', $master->id)
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->get();

        if ($episodes->isEmpty()) {
            return null;
        }

        // Si tenemos un nombre de fichero limpio, buscar si algún episodio coincide específicamente
        if ($cleanFile !== '') {
            foreach ($episodes as $ep) {
                $epLive = $this->cleanMetadataForProgramMatch((string) $ep->live_title);
                $epProg = $this->cleanMetadataForProgramMatch((string) $ep->titulo_programa);

                if ($epLive !== '' && strlen($epLive) >= 3 && (str_contains($cleanFile, $epLive) || str_contains($epLive, $cleanFile))) {
                    return $ep;
                }
                if ($epProg !== '' && strlen($epProg) >= 3 && (str_contains($cleanFile, $epProg) || str_contains($epProg, $cleanFile))) {
                    return $ep;
                }
                if ($ep->numero_episodio && preg_match('/\b' . $ep->numero_episodio . '\b/', $cleanFile)) {
                    return $ep;
                }
            }
        }

        // Si tenemos carátula, buscar si coincide con la carátula del episodio
        if ($cleanCover !== '') {
            foreach ($episodes as $ep) {
                foreach ([$ep->caratula_programa, $ep->imagen_episodio, $ep->live_image_url] as $img) {
                    $imgBase = $this->cleanCoverBasename((string) $img);
                    if ($imgBase !== '' && strlen($imgBase) >= 3 && (str_contains($cleanCover, $imgBase) || str_contains($imgBase, $cleanCover))) {
                        return $ep;
                    }
                }
            }
        }

        // Fallback: el más reciente del master_program_id
        return $episodes->first();
    }

    private function buildProgramFromMasterAndEpisode(MasterProgram $master, ?RadioProgram $episode, string $matchVia): Program
    {
        if ($episode) {
            $prog = Program::query()->find($episode->getKey());
            if ($prog) {
                $prog->match_via = $matchVia;
                $prog->matched_episode = $episode;
                if (! filled($prog->live_title) && filled($episode->live_title)) {
                    $prog->live_title = $episode->live_title;
                }
                return $prog;
            }
        }

        $dummy = new Program();
        $dummy->id = $episode ? $episode->id : $master->id;
        $dummy->exists = true;
        $dummy->fill([
            'name' => $master->name ?: $master->nombre,
            'description' => $episode?->live_description ?: $episode?->comentario_episodio ?: $episode?->resena ?: $master->description,
            'host' => $episode?->conductor ?: $master->host ?: $master->conductor,
            'cover_image' => $episode?->caratula_programa ?: $episode?->imagen_episodio ?: $master->live_image_url ?: $master->caratula_url,
            'schedule' => $master->schedule,
        ]);
        $dummy->setAttribute('id', $episode ? $episode->id : $master->id);
        $dummy->setAttribute('live_title', $episode?->live_title ?: $episode?->titulo_programa ?: ($master->name ?: $master->nombre));
        $dummy->match_via = $matchVia;
        $dummy->matched_episode = $episode;

        return $dummy;
    }

    private function cleanCoverBasename(string $urlOrPath): string
    {
        $urlOrPath = trim($urlOrPath);
        if ($urlOrPath === '' || str_starts_with($urlOrPath, 'data:image')) {
            return '';
        }

        $path = parse_url($urlOrPath, PHP_URL_PATH) ?? $urlOrPath;
        $basename = basename(str_replace('\\', '/', $path));
        $withoutExt = preg_replace('/\.[a-zA-Z0-9]{2,5}$/', '', $basename) ?? $basename;

        return $this->cleanMetadataForProgramMatch($withoutExt);
    }

    private function isGenericCover(string $cleanCover): bool
    {
        if ($cleanCover === '' || strlen($cleanCover) < 3) {
            return true;
        }

        $generics = [
            'album3', 'current cover', 'current-cover', 'artwork', 'default',
            'logo', 'stationlogo', 'station-logo', 'stream', 'transmision oficial',
            'seven rock radio', 'cover', 'image', 'radio', 'placeholder', 'null',
        ];

        return in_array($cleanCover, $generics, true);
    }

    private function recordMatch(string $via, ?string $programName, ?int $programId, ?string $episodeTitle, ?int $episodeId): void
    {
        $this->lastDiagnosis['match_found'] = true;
        $this->lastDiagnosis['match_via'] = $via;
        $this->lastDiagnosis['matched_program'] = $programName;
        $this->lastDiagnosis['matched_program_id'] = $programId;
        $this->lastDiagnosis['matched_episode'] = $episodeTitle;
        $this->lastDiagnosis['matched_episode_id'] = $episodeId;
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
            'program_host' => null,
            'program_schedule' => null,
            'master' => null,
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

        if ($master->live_starts_at && $master->live_ends_at) {
            $start = Carbon::parse((string) $master->live_starts_at, $timezone)->subMinutes(self::MARGIN_EPISODE_MINUTES);
            $end = Carbon::parse((string) $master->live_ends_at, $timezone)->addMinutes(self::MARGIN_EPISODE_MINUTES);

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

        if ($episode->fecha_emision) {
            $baseDate = Carbon::parse((string) $episode->fecha_emision, $timezone)->startOfDay();
            $refDate = $reference->copy()->setTimezone($timezone)->startOfDay();
            // Si la fecha del episodio difiere más de 1 día respecto a la fecha actual, descartar
            if (abs($baseDate->diffInDays($refDate, false)) > 1) {
                return null;
            }
        } else {
            $daySource = strtoupper(trim((string) ($episode->dia_transmision ?: $master->dia_transmision)));
            if ($daySource !== '' && $daySource !== $this->currentDayKey($reference)) {
                return null;
            }
            $baseDate = $reference->copy()->setTimezone($timezone)->startOfDay();
        }

        $start = $this->parseTimeToCarbon($baseDate, (string) ($episode->hora_inicio ?: $master->hora_transmision ?: ''));
        if ($start === null) {
            return null;
        }

        $currentDay = $this->currentDayKey($reference);
        $emision = $master->emisiones()
            ->where('activo', true)
            ->where('dia_semana', $currentDay)
            ->first();

        // Prioridad de duración: 1) duration_seconds de radio_programs (episodio real)
        // 2) duracion_segundos de emisión, 3) duracion_minutos de master
        $episodeDuration = (int) ($episode->duration_seconds ?? 0);
        $duracionRealSegundos = $episodeDuration > 0
            ? $episodeDuration
            : ($emision?->duracion_segundos ?? null);

        $endTime = trim((string) ($episode->hora_fin ?: ''));
        if ($endTime !== '') {
            $end = $this->parseTimeToCarbon($baseDate, $endTime);
            if ($end instanceof Carbon && $end->lessThan($start)) {
                $end = $end->copy()->addDay();
            }
        } elseif ($duracionRealSegundos && $duracionRealSegundos > 0) {
            $duracionMinutos = (int) ceil($duracionRealSegundos / 60);
            $end = $start->copy()->addMinutes($duracionMinutos);
        } elseif ((int) ($master->duracion_minutos ?? 0) > 0) {
            $end = $start->copy()->addMinutes((int) $master->duracion_minutos);
        } else {
            return null;
        }

        if (! $end instanceof Carbon) {
            return null;
        }

        // Añadir margen de ±30 minutos alrededor del inicio y fin
        $start = $start->copy()->subMinutes(self::MARGIN_EPISODE_MINUTES);
        $end = $end->copy()->addMinutes(self::MARGIN_EPISODE_MINUTES);

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

    public function isFallbackCover(?string $cover): bool
    {
        if (! filled($cover)) {
            return true;
        }

        $defaultCover = (string) config('player.defaults.cover', 'assets/lucille/album3.jpg');
        $resolvedDefault = asset($defaultCover);

        if ($cover === $resolvedDefault || str_contains($cover, $defaultCover)) {
            return true;
        }

        $lower = mb_strtolower((string) $cover);

        if (
            str_contains($lower, 'album3.jpg') ||
            str_contains($lower, 'logo.png') ||
            str_contains($lower, 'current-cover.jpg') ||
            str_contains($lower, 'stationlogo') ||
            str_contains($lower, 'station_logo')
        ) {
            return true;
        }

        return false;
    }

    private function cleanProgramHost(?string $host): ?string
    {
        if (! filled($host)) {
            return null;
        }

        $cleaned = trim((string) preg_replace('/^(conducido\s+por\s*:\s*|conductor\s*:\s*|host\s*:\s*)/iu', '', trim((string) $host)));

        return $cleaned !== '' ? $cleaned : null;
    }

    private function resolveProgramCoverCandidate(?Program $program, array $scheduleContext): ?string
    {
        if ($program) {
            $master = $program->masterProgram ?? $scheduleContext['master'] ?? null;
            $candidates = [
                $program->cover_image,
                $program->cover,
                $master?->cover_url,
                $master?->live_image_url,
                $master?->caratula_url,
            ];
            foreach ($candidates as $cand) {
                if (filled($cand)) {
                    $resolved = $this->resolveCover($cand);
                    if (! $this->isFallbackCover($resolved)) {
                        return $resolved;
                    }
                }
            }
        }

        $masterContext = $scheduleContext['master'] ?? null;
        if ($masterContext) {
            $candidates = [
                $masterContext->cover_url,
                $masterContext->live_image_url,
                $masterContext->caratula_url,
            ];
            foreach ($candidates as $cand) {
                if (filled($cand)) {
                    $resolved = $this->resolveCover($cand);
                    if (! $this->isFallbackCover($resolved)) {
                        return $resolved;
                    }
                }
            }
        }

        return null;
    }

    private function trackSignature(string $title, string $artist, string $cover, ?string $program = null): string
    {
        return md5(mb_strtolower(implode('|', [
            trim($title),
            trim($artist),
            trim($cover),
            trim((string) $program),
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
                'filename' => $normalized['filename'] ?? null,
                'file' => $normalized['file'] ?? null,
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
            $filename = $this->firstFilledString([
                Arr::get($item, 'filename'),
                Arr::get($item, 'file'),
                Arr::get($item, 'path'),
            ]);

            return array_filter([
                'title' => $title !== '' ? $title : null,
                'artist' => $artist !== '' ? $artist : null,
                'album' => $this->firstFilledString([Arr::get($item, 'album'), Arr::get($item, 'program')]),
                'filename' => $filename !== '' ? $filename : null,
                'file' => $filename !== '' ? $filename : null,
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

        $filename = $this->firstFilledString([
            trim((string) Arr::get($trackAttributes, 'FILENAME', '')),
            trim((string) Arr::get($trackAttributes, 'FILE', '')),
            trim((string) Arr::get($currentTrack, 'FILENAME', '')),
            trim((string) Arr::get($currentTrack, 'FILE', '')),
            trim((string) Arr::get($data, 'filename', '')),
            trim((string) Arr::get($data, 'file', '')),
            trim((string) Arr::get($recentTrack, 'filename', '')),
            trim((string) Arr::get($recentTrack, 'file', '')),
        ]);

        return [
            'title' => $title,
            'artist' => $artist,
            'filename' => $filename !== '' ? $filename : null,
            'file' => $filename !== '' ? $filename : null,
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
