<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\MasterProgram;
use App\Models\NewRelease;
use App\Models\Post;
use App\Services\ArchiveOrgService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * PwaAppController
 *
 * Maneja todas las vistas de la Progressive Web App (PWA) móvil de
 * Seven Rock Radio accesibles bajo el prefijo /app.
 */
class PwaAppController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // Vistas principales
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Feed / Inicio de la PWA.
     * Muestra posts recientes, últimos podcasts y artistas destacados.
     */
    public function index(ArchiveOrgService $archiveOrgService): View
    {
        // Posts recientes (noticias y efemérides)
        $posts = $this->safeValue(
            fn () => Post::query()
                ->published()
                ->orderByDesc('published_at')
                ->take(12)
                ->get(),
            collect()
        );

        // Últimos episodios de Archive.org
        $podcastPayload = $this->safeValue(
            fn () => $archiveOrgService->homePodcastPayload(12),
            ['episodes' => [], 'featured' => null]
        );

        $episodes = $podcastPayload['episodes'] ?? [];

        // Artistas/Agencias destacados
        $artists = $this->safeValue(
            fn () => Agency::query()
                ->where('is_active', true)
                ->whereNotNull('logo_path')
                ->orderBy('sort_order')
                ->take(10)
                ->get(),
            collect()
        );

        // New Releases para "Lo Último"
        $newReleases = $this->safeValue(
            fn () => NewRelease::query()
                ->where('is_active', true)
                ->where('show_in_feed', true)
                ->orderByDesc('released_at')
                ->take(6)
                ->get(),
            collect()
        );

        return view('pwa.index', compact('posts', 'episodes', 'artists', 'newReleases'));
    }

    /**
     * Vista "En Vivo" — señal de radio en streaming.
     */
    public function live(): View
    {
        $streamUrl    = config('player.streams.listen');
        $artworkUrl   = 'https://c30.radioboss.fm/w/artwork/569.jpg';
        $stationLogo  = 'https://c30.radioboss.fm/stationlogo/569.jpg';
        $stationId    = config('player.radioboss.station_id', '569');

        return view('pwa.live', compact('streamUrl', 'artworkUrl', 'stationLogo', 'stationId'));
    }

    /**
     * Vista "Podcasts" — programas y episodios.
     */
    public function podcasts(ArchiveOrgService $archiveOrgService): View
    {
        // Programas activos agrupados por día
        $programs = $this->safeValue(
            fn () => MasterProgram::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get()
                ->map(fn (MasterProgram $p) => [
                    'id'                 => $p->id,
                    'title'              => $p->nombre,
                    'host'               => $p->host ?? $p->conductor ?? '',
                    'cover'              => $p->cover_url,
                    'description'        => $p->description ?? '',
                    'schedule'           => $p->schedule ?? '',
                    'day'                => $p->dia_transmision ?? '',
                    'hour'               => $p->hora_transmision ?? '',
                    'archive_identifier' => $p->archive_identifier ?? '',
                    'slug'               => $p->publicSlug(),
                ]),
            collect()
        );

        // Últimos episodios del payload de Archive.org
        $payload  = $this->safeValue(fn () => $archiveOrgService->homePodcastPayload(20), []);
        $episodes = $payload['episodes'] ?? [];

        return view('pwa.podcasts', compact('programs', 'episodes'));
    }

    /**
     * Vista "Mi Música / Biblioteca" — artistas y new releases.
     */
    public function library(): View
    {
        $artists = $this->safeValue(
            fn () => Agency::query()
                ->where('is_active', true)
                ->whereNotNull('logo_path')
                ->orderBy('sort_order')
                ->get(),
            collect()
        );

        $newReleases = $this->safeValue(
            fn () => NewRelease::query()
                ->where('is_active', true)
                ->orderByDesc('released_at')
                ->take(24)
                ->get(),
            collect()
        );

        return view('pwa.library', compact('artists', 'newReleases'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // API JSON interna (consumida por el Service Worker y el Mini Player)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Devuelve los metadatos "Now Playing" de RadioBoss en formato JSON.
     * El Mini Player llama a este endpoint cada ~15 segundos via fetch().
     * Cascada de endpoints: nowplaying2 → nowplaying → widget JSON.
     */
    public function nowPlaying(): JsonResponse
    {
        $stationId = config('player.radioboss.station_id', '569');
        $apiUrl    = config('player.radioboss.api_url', 'https://c30.radioboss.fm');

        $data = Cache::remember('pwa.now_playing', 12, function () use ($apiUrl, $stationId): array {
            $defaults = [
                'title'    => config('player.defaults.title',  'Transmisión oficial'),
                'artist'   => config('player.defaults.artist', 'Seven Rock Radio'),
                'cover'    => "{$apiUrl}/w/artwork/{$stationId}.jpg",
                'program'  => config('player.defaults.show',   'Programación habitual'),
                'is_live'  => true,
                'duration' => null,
            ];

            // Endpoint 1: RadioBoss API nowplayinginfo
            try {
                $r = Http::timeout(4)->withoutVerifying()
                    ->get("{$apiUrl}/w/nowplayinginfo", ['u' => $stationId]);
                if ($r->successful()) {
                    $j = $r->json();
                    $title  = trim((string)($j['currenttrack_title'] ?? ''));
                    $artist = trim((string)($j['currenttrack_artist'] ?? ''));
                    
                    if (!$title && !empty($j['nowplaying'])) {
                        $parts = explode(' - ', $j['nowplaying'], 2);
                        if (count($parts) === 2) {
                            $title = trim($parts[0]);
                            $artist = trim($parts[1]);
                        } else {
                            $title = trim($j['nowplaying']);
                        }
                    }
                    if ($title) {
                        return array_merge($defaults, array_filter(compact('title', 'artist')));
                    }
                }
            } catch (\Throwable) {}

            return $defaults;
        });

        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Devuelve el historial reciente de tracks reproducidos en RadioBoss.
     * Usado por la vista En Vivo para mostrar la lista de tracks.
     */
    public function recentTracks(): JsonResponse
    {
        $stationId = config('player.radioboss.station_id', '569');
        $apiUrl    = config('player.radioboss.api_url', 'https://c30.radioboss.fm');

        $tracks = Cache::remember('pwa.recent_tracks', 25, function () use ($apiUrl, $stationId): array {
            try {
                $r = Http::timeout(5)->withoutVerifying()
                    ->get("{$apiUrl}/w/recenttrackslist", [
                        'u' => $stationId,
                    ]);

                if ($r->successful()) {
                    $history = $r->json();
                    
                    if (is_array($history) && !isset($history['error'])) {
                        // RadioBoss recent tracks list usually includes current playing as index 0. Shift it.
                        array_shift($history);
                        
                        return array_map(function ($t) use ($apiUrl, $stationId) {
                            $title = trim((string)($t['tracktitle'] ?? $t['title'] ?? 'Canción'));
                            $artist = trim((string)($t['trackartist'] ?? ''));
                            
                            // If title doesn't exist but full title does, split it
                            if (!$title && !empty($t['title'])) {
                                $parts = explode(' - ', $t['title'], 2);
                                if (count($parts) === 2) {
                                    $title = trim($parts[0]);
                                    $artist = trim($parts[1]);
                                } else {
                                    $title = trim($t['title']);
                                }
                            }
                            
                            return [
                                'title'     => $title,
                                'artist'    => $artist,
                                'played_at' => trim((string)($t['started'] ?? '')),
                                'duration'  => '', // API doesn't return duration directly here
                                'cover'     => !empty($t['artworkid']) ? "{$apiUrl}/w/artwork_recent_{$t['artworkid']}/{$stationId}.jpg" : "{$apiUrl}/w/artwork/{$stationId}.jpg",
                            ];
                        }, array_slice($history, 0, 8));
                    }
                }
            } catch (\Throwable) {}

            return [];
        });

        return response()->json(['tracks' => $tracks])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }



    // ─────────────────────────────────────────────────────────────────────────
    // Helpers internos
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ejecuta un callable de forma segura; devuelve $fallback en caso de error.
     *
     * @template T
     * @param  callable(): T $callable
     * @param  T             $fallback
     * @return T
     */
    private function safeValue(callable $callable, mixed $fallback): mixed
    {
        try {
            return $callable();
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
