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
                ->map(fn ($p) => [
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
     */
    public function nowPlaying(): JsonResponse
    {
        $stationId = config('player.radioboss.station_id', '569');
        $apiUrl    = config('player.radioboss.api_url', 'https://c30.radioboss.fm');

        $data = Cache::remember('pwa.now_playing', 14, function () use ($apiUrl, $stationId): array {
            try {
                $response = Http::timeout(5)
                    ->withoutVerifying()
                    ->get("{$apiUrl}/w/api.php", [
                        'u'    => $stationId,
                        'mode' => 'nowplaying',
                    ]);

                if ($response->successful()) {
                    $json = $response->json();

                    return [
                        'title'    => $json['current']['title']  ?? $json['title']  ?? config('player.defaults.title'),
                        'artist'   => $json['current']['artist'] ?? $json['artist'] ?? config('player.defaults.artist'),
                        'cover'    => "{$apiUrl}/w/artwork/{$stationId}.jpg?t=" . time(),
                        'is_live'  => true,
                    ];
                }
            } catch (\Throwable) {
                // Fallback silencioso
            }

            return [
                'title'  => config('player.defaults.title', 'Transmisión oficial'),
                'artist' => config('player.defaults.artist', 'Seven Rock Radio'),
                'cover'  => asset('assets/lucille/album3.jpg'),
                'is_live'=> true,
            ];
        });

        return response()->json($data)
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
