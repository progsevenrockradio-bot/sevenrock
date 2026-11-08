<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Models\RadioArtist;
use App\Models\Song;
use App\Support\RadioPlayerStateStore;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlayerScenarioController extends Controller
{
    public function __construct(
        private readonly RadioPlayerStateStore $store,
    ) {
    }

    public function index(): View
    {
        $this->ensureSyntheticDataExists();

        $currentScenario = session('dev_player_scenario', 'song');
        $hasNextTrack = session('dev_player_has_next_track', true);

        $this->applyScenario($currentScenario, $hasNextTrack);

        return view('dev.player-scenario', [
            'currentScenario' => $currentScenario,
            'hasNextTrack' => $hasNextTrack,
        ]);
    }

    public function setScenario(Request $request): JsonResponse
    {
        $scenario = (string) $request->input('scenario', 'song');
        $hasNextTrack = (bool) $request->input('has_next_track', session('dev_player_has_next_track', true));

        session(['dev_player_scenario' => $scenario]);
        session(['dev_player_has_next_track' => $hasNextTrack]);

        $this->applyScenario($scenario, $hasNextTrack);

        return response()->json([
            'success' => true,
            'scenario' => $scenario,
            'has_next_track' => $hasNextTrack,
        ]);
    }

    public function toggleNextTrack(Request $request): JsonResponse
    {
        $hasNextTrack = (bool) $request->input('has_next_track');
        session(['dev_player_has_next_track' => $hasNextTrack]);

        $scenario = session('dev_player_scenario', 'song');
        $this->applyScenario($scenario, $hasNextTrack);

        return response()->json([
            'success' => true,
            'has_next_track' => $hasNextTrack,
            'scenario' => $scenario,
        ]);
    }

    private function applyScenario(string $scenario, bool $hasNextTrack): void
    {
        $now = Carbon::now('America/Caracas');
        $timestamp = $now->toIso8601String();

        $nextTrackPayload = $hasNextTrack ? [
            'title' => 'Painkiller',
            'artist' => 'Judas Priest',
            'album' => 'Painkiller',
        ] : null;

        $defaultHistory = [
            [
                'id' => 5001,
                'title' => 'The Trooper',
                'artist' => 'Iron Maiden',
                'cover' => '/assets/dev/covers/scenario_1_trooper.svg',
                'source' => 'radioboss',
                'is_program' => false,
                'program' => null,
                'duration_seconds' => 252,
                'played_at' => $now->copy()->subMinutes(4)->toIso8601String(),
            ],
            [
                'id' => 5002,
                'title' => 'Hallowed Be Thy Name',
                'artist' => 'Iron Maiden',
                'cover' => '/assets/dev/covers/scenario_1_trooper.svg',
                'source' => 'radioboss',
                'is_program' => false,
                'program' => null,
                'duration_seconds' => 431,
                'played_at' => $now->copy()->subMinutes(12)->toIso8601String(),
            ],
            [
                'id' => 5003,
                'title' => 'Especial NWOBHM: Del Underground a los Estadios',
                'artist' => 'Carlos Metalhead',
                'cover' => '/assets/dev/covers/scenario_3_program_spec.svg',
                'source' => 'program',
                'is_program' => true,
                'program' => 'La Hora del Metal',
                'duration_seconds' => 3600,
                'played_at' => $now->copy()->subMinutes(45)->toIso8601String(),
            ],
            [
                'id' => 5004,
                'title' => 'Holy Diver',
                'artist' => 'Dio',
                'cover' => '/assets/dev/covers/scenario_5_dio.svg',
                'source' => 'radioboss',
                'is_program' => false,
                'program' => null,
                'duration_seconds' => 336,
                'played_at' => $now->copy()->subMinutes(75)->toIso8601String(),
            ],
            [
                'id' => 5005,
                'title' => 'The Trooper',
                'artist' => 'Iron Maiden',
                'cover' => '/assets/dev/covers/scenario_1_trooper.svg',
                'source' => 'radioboss',
                'is_program' => false,
                'program' => null,
                'duration_seconds' => 252,
                'played_at' => $now->copy()->subMinutes(150)->toIso8601String(),
            ],
            [
                'id' => 5006,
                'title' => 'Bark at the Moon',
                'artist' => 'Ozzy Osbourne',
                'cover' => '',
                'source' => 'radioboss',
                'is_program' => false,
                'program' => null,
                'duration_seconds' => 257,
                'played_at' => $now->copy()->subMinutes(185)->toIso8601String(),
            ],
            [
                'id' => 5007,
                'title' => 'Paranoid',
                'artist' => 'Black Sabbath',
                'cover' => '/assets/dev/covers/scenario_1_trooper.svg',
                'source' => 'radioboss',
                'is_program' => false,
                'program' => null,
                'duration_seconds' => 170,
                'played_at' => $now->copy()->subMinutes(210)->toIso8601String(),
            ],
        ];

        $basePayload = [
            'stream_url' => 'https://radios.mipanel.stream:6796/;',
            'stream_candidates' => [
                'https://radios.mipanel.stream:6796/;',
                config('player.streams.direct'),
                url('/assets/dev/audio/sample.mp3'),
            ],
            'listen_url' => config('player.streams.listen'),
            'playlist_m3u' => config('player.streams.m3u'),
            'playlist_pls' => config('player.streams.pls'),
            'listeners' => 12,
            'meta_source' => 'radioboss',
            'meta_updated_at' => $timestamp,
            'cover_is_fallback' => false,
            'next_track' => $nextTrackPayload,
            'queue' => [],
            'notices' => [],
            'history' => $defaultHistory,
            'history_stale' => false,
            'updated_at' => $timestamp,
        ];

        switch ($scenario) {
            case 'song':
                // 1. Canción activa: Iron Maiden - The Trooper. Con info de banda y letras.
                $status = array_merge($basePayload, [
                    'es_bloque_programa' => false,
                    'es_programa_anticipo' => false,
                    'program_id' => null,
                    'program_name' => null,
                    'program_description' => null,
                    'program' => null,
                    'next_program' => [
                        'id' => 901,
                        'name' => 'La Hora del Metal',
                        'host' => 'Carlos Metalhead',
                        'schedule' => 'Miércoles 18:00',
                        'schedule_time' => '18:00',
                        'description' => 'Especial de heavy metal clásico.',
                        'cover' => '/assets/dev/covers/scenario_3_program_spec.svg',
                        'country' => 'España',
                    ],
                    'track' => [
                        'id' => 101,
                        'title' => 'The Trooper',
                        'artist' => 'Iron Maiden',
                        'album' => 'Piece of Mind',
                        'cover' => '/assets/dev/covers/scenario_1_trooper.svg',
                        'cover_is_fallback' => false,
                        'meta_source' => 'radioboss',
                        'meta_updated_at' => $timestamp,
                        'lyrics' => "You'll take my life, but I'll take yours too\nYou'll fire your musket, but I'll run you through\nSo when you're waiting for the next attack\nYou'd better stand, there's no turning back\n\nThe smoke blinds us and the smell of acid reek\nThe horses rear, they shiver, they weep\nThe bullets fly, they whisper through the air\nA final breath, a soldier's silent prayer...",
                        'band_info' => 'Iron Maiden es una banda británica de heavy metal fundada en 1975 por el bajista Steve Harris. Pionera indiscutible de la Nueva Ola del Heavy Metal Británico (NWOBHM) con más de 100 millones de discos vendidos.',
                        'band_biography' => "Iron Maiden es una de las bandas más influyentes de la historia del heavy metal. Fundada en Londres en 1975 por Steve Harris, alcanzaron el estrellato mundial con álbumes legendarios como 'The Number of the Beast', 'Piece of Mind' y 'Powerslave'.\n\nSu mascota Eddie the Head se ha convertido en un ícono cultural del rock en todo el planeta.",
                        'band_biography_source' => 'editorial',
                        'band_thumbnail' => '/assets/dev/covers/scenario_1_trooper.svg',
                        'band_founded_year' => 1975,
                        'band_founded_label' => 'Fundada en 1975 (Londres, Reino Unido)',
                        'band_country' => 'Reino Unido',
                        'band_genre' => 'Heavy Metal',
                        'band_status' => 'active',
                        'program_id' => null,
                        'program_name' => null,
                        'program_description' => null,
                        'program_host' => null,
                        'program_schedule' => null,
                        'es_bloque_programa' => false,
                        'es_programa_anticipo' => false,
                        'is_live' => true,
                        'signature' => md5('The Trooper-Iron Maiden-' . $timestamp),
                        'duration_seconds' => 252,
                        'elapsed_seconds' => 45,
                    ],
                ]);
                break;

            case 'preview':
                // 2. 5 minutos antes: aviso previo manteniendo la canción actual.
                $status = array_merge($basePayload, [
                    'es_bloque_programa' => false,
                    'es_programa_anticipo' => true,
                    'program_id' => 901,
                    'program_name' => 'La Hora del Metal',
                    'program_description' => 'Especial NWOBHM: Del Underground a los Estadios. Conduce Carlos Metalhead.',
                    'program' => [
                        'id' => 901,
                        'nombre' => 'La Hora del Metal',
                        'name' => 'La Hora del Metal',
                        'conductor' => 'Carlos Metalhead',
                        'host' => 'Carlos Metalhead',
                        'descripcion' => 'Especial NWOBHM: Del Underground a los Estadios.',
                        'horario' => 'Miércoles 18:00 - 19:00',
                        'cover' => '/assets/dev/covers/scenario_2_preview.svg',
                    ],
                    'next_program' => [
                        'id' => 901,
                        'name' => 'La Hora del Metal',
                        'host' => 'Carlos Metalhead',
                        'schedule' => 'Miércoles 18:00 (Inicia en 5 min)',
                        'schedule_time' => '18:00',
                        'cover' => '/assets/dev/covers/scenario_2_preview.svg',
                    ],
                    'track' => [
                        'id' => 101,
                        'title' => 'The Trooper',
                        'artist' => 'Iron Maiden',
                        'album' => 'Piece of Mind',
                        'cover' => '/assets/dev/covers/scenario_2_preview.svg',
                        'cover_is_fallback' => false,
                        'meta_source' => 'radioboss',
                        'meta_updated_at' => $timestamp,
                        'lyrics' => "You'll take my life, but I'll take yours too\nYou'll fire your musket, but I'll run you through...",
                        'band_info' => 'Iron Maiden es una banda británica de heavy metal fundada en 1975 por el bajista Steve Harris.',
                        'band_biography' => "Aviso previo: La canción actual sigue sonando normalmente mientras el banner de anticipo avisa que 'La Hora del Metal' iniciará en 5 minutos.",
                        'band_biography_source' => 'editorial',
                        'band_thumbnail' => '/assets/dev/covers/scenario_2_preview.svg',
                        'band_founded_year' => 1975,
                        'band_founded_label' => 'Fundada en 1975 (Londres, Reino Unido)',
                        'band_country' => 'Reino Unido',
                        'band_genre' => 'Heavy Metal',
                        'band_status' => 'active',
                        'program_id' => 901,
                        'program_name' => 'La Hora del Metal',
                        'program_description' => 'Especial NWOBHM: Del Underground a los Estadios.',
                        'program_host' => 'Carlos Metalhead',
                        'program_schedule' => 'Miércoles 18:00 - 19:00',
                        'program_country' => 'España',
                        'es_bloque_programa' => false,
                        'es_programa_anticipo' => true,
                        'is_live' => true,
                        'signature' => md5('The Trooper-Iron Maiden-preview-' . $timestamp),
                        'duration_seconds' => 252,
                        'elapsed_seconds' => 210,
                    ],
                ]);
                break;

            case 'program_specific':
                // 3. Programa en emisión con información específica de esa emisión.
                $status = array_merge($basePayload, [
                    'es_bloque_programa' => true,
                    'es_programa_anticipo' => false,
                    'program_id' => 901,
                    'program_name' => 'La Hora del Metal',
                    'program_description' => 'Episodio especial: Himnos legendarios NWOBHM 1980 a 1984.',
                    'program' => [
                        'id' => 901,
                        'nombre' => 'La Hora del Metal',
                        'name' => 'La Hora del Metal',
                        'conductor' => 'Carlos Metalhead',
                        'host' => 'Carlos Metalhead',
                        'descripcion' => 'Episodio especial: Himnos legendarios NWOBHM 1980 a 1984.',
                        'horario' => 'Miércoles 18:00 - 19:00 (En Vivo)',
                        'cover' => '/assets/dev/covers/scenario_3_program_spec.svg',
                        'country' => 'España',
                    ],
                    'next_program' => null,
                    'track' => [
                        'id' => null,
                        'title' => 'Especial NWOBHM: Del Underground a los Estadios',
                        'artist' => 'La Hora del Metal - Carlos Metalhead',
                        'album' => 'La Hora del Metal',
                        'cover' => '/assets/dev/covers/scenario_3_program_spec.svg',
                        'cover_is_fallback' => false,
                        'meta_source' => 'program:emission',
                        'meta_updated_at' => $timestamp,
                        'lyrics' => '',
                        'band_info' => 'Programa especializado en la Nueva Ola del Heavy Metal Británico. Conducido por Carlos Metalhead.',
                        'band_biography' => 'Edición exclusiva con audios remasterizados, entrevistas de archivo y análisis histórico de las grabaciones entre 1980 y 1984.',
                        'band_biography_source' => 'program',
                        'band_thumbnail' => '/assets/dev/covers/scenario_3_program_spec.svg',
                        'program_id' => 901,
                        'program_name' => 'La Hora del Metal',
                        'program_description' => 'Episodio especial: Himnos legendarios NWOBHM 1980 a 1984.',
                        'program_host' => 'Carlos Metalhead',
                        'program_schedule' => 'Miércoles 18:00 - 19:00',
                        'program_country' => 'España',
                        'es_bloque_programa' => true,
                        'es_programa_anticipo' => false,
                        'is_live' => true,
                        'signature' => md5('program-specific-' . $timestamp),
                        'duration_seconds' => 3600,
                        'elapsed_seconds' => 320,
                    ],
                ]);
                break;

            case 'program_generic':
                // 4. Programa sin emisión específica: datos generales del máster (ej. El Garaje del Rock).
                $status = array_merge($basePayload, [
                    'es_bloque_programa' => true,
                    'es_programa_anticipo' => false,
                    'program_id' => 903,
                    'program_name' => 'El Garaje del Rock',
                    'program_description' => 'Programa insignia de garage rock, grunge y bandas independientes conducido por Laura Garaje.',
                    'program' => [
                        'id' => 903,
                        'nombre' => 'El Garaje del Rock',
                        'name' => 'El Garaje del Rock',
                        'conductor' => 'Laura Garaje',
                        'host' => 'Laura Garaje',
                        'descripcion' => 'Programa insignia de garage rock, grunge y bandas independientes conducido por Laura Garaje.',
                        'horario' => 'Viernes 20:00 - 21:00',
                        'cover' => '/assets/dev/covers/scenario_4_program_gen.svg',
                        'country' => 'Argentina',
                    ],
                    'next_program' => null,
                    'track' => [
                        'id' => null,
                        'title' => 'El Garaje del Rock',
                        'artist' => 'Laura Garaje',
                        'album' => 'Programación Habitual',
                        'cover' => '/assets/dev/covers/scenario_4_program_gen.svg',
                        'cover_is_fallback' => false,
                        'meta_source' => 'program:master',
                        'meta_updated_at' => $timestamp,
                        'lyrics' => '',
                        'band_info' => 'Emisión basada en la ficha del máster general.',
                        'band_biography' => 'Cuando la emisión no registra un episodio específico en la base de datos, el sistema toma los datos de cabecera del programa maestro para garantizar información constante al oyente.',
                        'band_biography_source' => 'program',
                        'band_thumbnail' => '/assets/dev/covers/scenario_4_program_gen.svg',
                        'program_id' => 903,
                        'program_name' => 'El Garaje del Rock',
                        'program_description' => 'Programa insignia de garage rock, grunge y bandas independientes conducido por Laura Garaje.',
                        'program_host' => 'Laura Garaje',
                        'program_schedule' => 'Viernes 20:00 - 21:00',
                        'program_country' => 'Argentina',
                        'es_bloque_programa' => true,
                        'es_programa_anticipo' => false,
                        'is_live' => true,
                        'signature' => md5('program-generic-' . $timestamp),
                        'duration_seconds' => 3600,
                        'elapsed_seconds' => 600,
                    ],
                ]);
                break;

            case 'program_ended':
                // 5. Final del programa: regreso limpio a canción, sin información antigua.
                $status = array_merge($basePayload, [
                    'es_bloque_programa' => false,
                    'es_programa_anticipo' => false,
                    'program_id' => null,
                    'program_name' => null,
                    'program_description' => null,
                    'program' => null,
                    'next_program' => [
                        'id' => 902,
                        'name' => 'Rock Clásico de Medianoche',
                        'host' => 'Seven Rock Radio',
                        'schedule' => '23:00',
                        'schedule_time' => '23:00',
                        'cover' => '',
                    ],
                    'track' => [
                        'id' => 102,
                        'title' => 'Holy Diver',
                        'artist' => 'Dio',
                        'album' => 'Holy Diver',
                        'cover' => '/assets/dev/covers/scenario_5_dio.svg',
                        'cover_is_fallback' => false,
                        'meta_source' => 'radioboss',
                        'meta_updated_at' => $timestamp,
                        'lyrics' => "Holy Diver\nYou've been down too long in the midnight sea\nOh what's becoming of me\nRide the tiger\nYou can see his stripes but you know he's clean\nOh don't you see what I mean...\n\nGotta get away, Holy Diver!",
                        'band_info' => 'Dio fue una banda estadounidense de heavy metal formada en 1982 por Ronnie James Dio tras su salida de Black Sabbath.',
                        'band_biography' => "Dio debutó en 1983 con la obra maestra 'Holy Diver', certificada platino en Estados Unidos y considerada uno de los pilares del metal de los 80.",
                        'band_biography_source' => 'editorial',
                        'band_thumbnail' => '/assets/dev/covers/scenario_5_dio.svg',
                        'band_founded_year' => 1982,
                        'band_founded_label' => 'Fundada en 1982 (Estados Unidos)',
                        'band_country' => 'Estados Unidos',
                        'band_genre' => 'Heavy Metal',
                        'band_status' => 'disbanded',
                        'program_id' => null,
                        'program_name' => null,
                        'program_description' => null,
                        'program_host' => null,
                        'program_schedule' => null,
                        'es_bloque_programa' => false,
                        'es_programa_anticipo' => false,
                        'is_live' => true,
                        'signature' => md5('Holy Diver-Dio-' . $timestamp),
                        'duration_seconds' => 340,
                        'elapsed_seconds' => 20,
                    ],
                ]);
                break;

            case 'missing_time':
                // 6. Emisión en directo con tiempos ausentes o incoherentes (debe verse «EN DIRECTO»)
                $status = array_merge($basePayload, [
                    'es_bloque_programa' => false,
                    'es_programa_anticipo' => false,
                    'program_id' => null,
                    'program_name' => null,
                    'program_description' => null,
                    'program' => null,
                    'next_program' => null,
                    'track' => [
                        'id' => 103,
                        'title' => 'Transmisión Oficial Seven Rock Radio',
                        'artist' => 'Seven Rock Radio',
                        'album' => 'En Vivo',
                        'cover' => '/assets/dev/covers/scenario_1_trooper.svg',
                        'cover_is_fallback' => true,
                        'meta_source' => 'radioboss',
                        'meta_updated_at' => $timestamp,
                        'lyrics' => '',
                        'band_info' => 'Transmisión en directo sin metadatos de duración fijos.',
                        'band_biography' => 'Transmisión continua de rock en directo.',
                        'band_biography_source' => '',
                        'band_thumbnail' => '',
                        'band_founded_year' => null,
                        'band_founded_label' => '',
                        'band_country' => 'Venezuela',
                        'band_genre' => 'Rock',
                        'band_status' => 'active',
                        'program_id' => null,
                        'program_name' => null,
                        'program_description' => null,
                        'program_host' => null,
                        'program_schedule' => null,
                        'es_bloque_programa' => false,
                        'es_programa_anticipo' => false,
                        'is_live' => true,
                        'signature' => md5('missing-time-' . $timestamp),
                        'duration_seconds' => 0,
                        'elapsed_seconds' => 0,
                    ],
                ]);
                break;

            case 'podcast':
                // 7. Podcast bajo demanda de Archive.org con control interactivo
                $status = array_merge($basePayload, [
                    'es_bloque_programa' => false,
                    'es_programa_anticipo' => false,
                    'program_id' => null,
                    'program_name' => 'Podcast Show',
                    'program_description' => 'Programa de podcast oficial alojado en Archive.org.',
                    'program' => null,
                    'next_program' => null,
                    'track' => [
                        'id' => 119,
                        'title' => '001.- PODCAST SHOW 27-10-2026',
                        'artist' => 'Seven Rock Radio',
                        'album' => 'Podcasts Seven Rock Radio',
                        'cover' => '/assets/dev/covers/scenario_3_program_spec.svg',
                        'cover_is_fallback' => false,
                        'meta_source' => 'archive_org',
                        'meta_updated_at' => $timestamp,
                        'lyrics' => '',
                        'band_info' => 'Episodio bajo demanda verificado alojado en Archive.org con barra interactiva de salto temporal.',
                        'band_biography' => 'Grabación original de Archive.org: Seven Rock Radio Podcast Show #001.',
                        'band_biography_source' => 'archive_org',
                        'band_thumbnail' => '/assets/dev/covers/scenario_3_program_spec.svg',
                        'band_founded_year' => null,
                        'band_founded_label' => '',
                        'band_country' => 'Venezuela',
                        'band_genre' => 'Rock / Podcast',
                        'band_status' => 'active',
                        'program_id' => null,
                        'program_name' => 'Podcast Show',
                        'program_description' => 'Edición 001 de Podcast Show',
                        'program_host' => 'Seven Rock Radio',
                        'program_schedule' => 'Bajo Demanda',
                        'es_bloque_programa' => false,
                        'es_programa_anticipo' => false,
                        'is_live' => false,
                        'audio_url' => 'https://archive.org/download/sevenrockradio/podcast-show/001.-%20PODCAST%20SHOW%2027-10-2026.mp3',
                        'signature' => md5('podcast-001-' . $timestamp),
                        'duration_seconds' => 3240,
                        'elapsed_seconds' => 315,
                    ],
                ]);
                break;

            case 'local_sample':
                // Muestra local MP3 offline para comprobar controles sin depender de red externa
                $status = array_merge($basePayload, [
                    'es_bloque_programa' => false,
                    'es_programa_anticipo' => false,
                    'program_id' => null,
                    'program_name' => 'Audio Local de Prueba',
                    'program_description' => 'Muestra de audio MP3 local almacenada en la aplicación.',
                    'program' => null,
                    'next_program' => null,
                    'track' => [
                        'id' => 999,
                        'title' => 'Paint It Black (Muestra Local)',
                        'artist' => 'The Rolling Stones',
                        'album' => 'Aftermath (Muestra Offline)',
                        'cover' => '/assets/dev/covers/scenario_1_trooper.svg',
                        'cover_is_fallback' => false,
                        'meta_source' => 'local_sample',
                        'meta_updated_at' => $timestamp,
                        'lyrics' => "I see a red door and I want it painted black\nNo colors anymore, I want them to turn black\nI see the girls walk by dressed in their summer clothes\nI have to turn my head until my darkness goes...",
                        'band_info' => 'Muestra MP3 local de prueba offline ubicada en /assets/dev/audio/sample.mp3.',
                        'band_biography' => 'Pista de prueba almacenada localmente para validar reproducción HTML5, volumen, pausa/reanudación y arrastre de progreso sin requerir conexión a internet.',
                        'band_biography_source' => 'local',
                        'band_thumbnail' => '/assets/dev/covers/scenario_1_trooper.svg',
                        'band_founded_year' => 1962,
                        'band_founded_label' => 'Fundada en 1962 (Londres, Reino Unido)',
                        'band_country' => 'Reino Unido',
                        'band_genre' => 'Classic Rock',
                        'band_status' => 'active',
                        'program_id' => null,
                        'program_name' => 'Audio Local de Prueba',
                        'program_description' => 'Muestra local offline',
                        'program_host' => 'Equipo Técnico Seven Rock',
                        'program_schedule' => 'Offline / Local',
                        'es_bloque_programa' => false,
                        'es_programa_anticipo' => false,
                        'is_live' => false,
                        'audio_url' => '/assets/dev/audio/sample.mp3',
                        'signature' => md5('local-sample-' . $timestamp),
                        'duration_seconds' => 160,
                        'elapsed_seconds' => 0,
                    ],
                ]);
                break;

            case 'delayed_widget':
                // 8. Dos canciones de duración similar (The Trooper 252s -> Rainbow in the Dark 255s) con widget retrasado
                $status = array_merge($basePayload, [
                    'es_bloque_programa' => false,
                    'es_programa_anticipo' => false,
                    'program_id' => null,
                    'program_name' => null,
                    'program_description' => null,
                    'program' => null,
                    'next_program' => null,
                    'track' => [
                        'id' => 104,
                        'title' => 'Rainbow in the Dark',
                        'artist' => 'Dio',
                        'album' => 'Holy Diver',
                        'cover' => '/assets/dev/covers/scenario_5_dio.svg',
                        'cover_is_fallback' => false,
                        'meta_source' => 'radioboss',
                        'meta_updated_at' => $timestamp,
                        'lyrics' => "When there's lightning, you know it always brings me down...",
                        'band_info' => 'Dio - Rainbow in the Dark.',
                        'band_biography' => 'Canción clásica de Dio.',
                        'band_biography_source' => 'editorial',
                        'band_thumbnail' => '/assets/dev/covers/scenario_5_dio.svg',
                        'program_id' => null,
                        'es_bloque_programa' => false,
                        'es_programa_anticipo' => false,
                        'is_live' => true,
                        'signature' => md5('Rainbow in the Dark-Dio-' . $timestamp),
                        'duration_seconds' => 255,
                        'elapsed_seconds' => 12,
                    ],
                ]);
                break;

            case 'data_loss_same_track':
                // 9. Pérdida de datos de tiempo sin cambio de pista (The Trooper pierde duration/elapsed)
                $status = array_merge($basePayload, [
                    'es_bloque_programa' => false,
                    'es_programa_anticipo' => false,
                    'program_id' => null,
                    'program_name' => null,
                    'program_description' => null,
                    'program' => null,
                    'next_program' => null,
                    'track' => [
                        'id' => 101,
                        'title' => 'The Trooper',
                        'artist' => 'Iron Maiden',
                        'album' => 'Piece of Mind',
                        'cover' => '/assets/dev/covers/scenario_1_trooper.svg',
                        'cover_is_fallback' => false,
                        'meta_source' => 'radioboss',
                        'meta_updated_at' => $timestamp,
                        'lyrics' => "You'll take my life, but I'll take yours too...",
                        'band_info' => 'Iron Maiden.',
                        'band_biography' => 'Iron Maiden.',
                        'band_biography_source' => 'editorial',
                        'band_thumbnail' => '/assets/dev/covers/scenario_1_trooper.svg',
                        'program_id' => null,
                        'es_bloque_programa' => false,
                        'es_programa_anticipo' => false,
                        'is_live' => true,
                        'signature' => md5('The Trooper-Iron Maiden-fixed-id'),
                        'duration_seconds' => 0,
                        'elapsed_seconds' => 0,
                    ],
                ]);
                break;

            case 'next_track_started':
                // 10. Cambio de pista: Judas Priest (antes próxima canción) comienza a sonar y se retira de "A continuación"
                $status = array_merge($basePayload, [
                    'es_bloque_programa' => false,
                    'es_programa_anticipo' => false,
                    'next_track' => [
                        'title' => 'Master of Puppets',
                        'artist' => 'Metallica',
                        'album' => 'Master of Puppets',
                        'cover' => '/assets/dev/covers/scenario_1_trooper.svg',
                    ],
                    'track' => [
                        'id' => 105,
                        'title' => 'Painkiller',
                        'artist' => 'Judas Priest',
                        'album' => 'Painkiller',
                        'cover' => '/assets/dev/covers/scenario_1_trooper.svg',
                        'cover_is_fallback' => false,
                        'meta_source' => 'radioboss',
                        'meta_updated_at' => $timestamp,
                        'lyrics' => "Faster than a bullet\nTerrifying scream\nEnraged and full of anger\nHe's half man and half machine...",
                        'band_info' => 'Judas Priest es una banda británica de heavy metal fundada en 1969 en Birmingham.',
                        'band_biography' => 'Judas Priest es uno de los máximos exponentes del heavy metal mundial.',
                        'band_biography_source' => 'editorial',
                        'band_thumbnail' => '/assets/dev/covers/scenario_1_trooper.svg',
                        'band_founded_year' => 1969,
                        'band_founded_label' => 'Fundada en 1969 (Birmingham, Reino Unido)',
                        'band_country' => 'Reino Unido',
                        'band_genre' => 'Heavy Metal',
                        'band_status' => 'active',
                        'program_id' => null,
                        'es_bloque_programa' => false,
                        'es_programa_anticipo' => false,
                        'is_live' => true,
                        'signature' => md5('Painkiller-Judas Priest-' . $timestamp),
                        'duration_seconds' => 366,
                        'elapsed_seconds' => 10,
                    ],
                    'history' => array_merge([
                        [
                            'title' => 'The Trooper',
                            'artist' => 'Iron Maiden',
                            'cover' => '/assets/dev/covers/scenario_1_trooper.svg',
                            'source' => 'radioboss',
                            'is_program' => false,
                            'program' => null,
                            'played_at' => $now->toIso8601String(),
                        ]
                    ], $defaultHistory),
                ]);
                break;

            case 'empty_history':
                // 11. Historial vacío sin pistas registradas
                $status = array_merge($basePayload, [
                    'history' => [],
                    'history_stale' => false,
                ]);
                break;

            case 'source_failure':
                // 12. Caída de la fuente: conserva el último historial disponible y muestra estado "No actualizado"
                $status = array_merge($basePayload, [
                    'history' => $defaultHistory,
                    'history_stale' => true,
                    'next_track' => null, // Fuente caída pierde datos de próxima canción
                ]);
                break;

            case 'duplicate_polling':
                // 13. Sondeo duplicado: registros idénticos consecutivos emitidos con 10s de diferencia (deben deduplicarse en el visor)
                $status = array_merge($basePayload, [
                    'history' => [
                        [
                            'title' => 'The Trooper',
                            'artist' => 'Iron Maiden',
                            'cover' => '/assets/dev/covers/scenario_1_trooper.svg',
                            'source' => 'radioboss',
                            'is_program' => false,
                            'program' => null,
                            'played_at' => $now->toIso8601String(),
                        ],
                        [
                            'title' => 'The Trooper',
                            'artist' => 'Iron Maiden',
                            'cover' => '/assets/dev/covers/scenario_1_trooper.svg',
                            'source' => 'radioboss',
                            'is_program' => false,
                            'program' => null,
                            'played_at' => $now->copy()->subSeconds(10)->toIso8601String(), // Duplicado de sondeo
                        ],
                        [
                            'title' => 'Holy Diver',
                            'artist' => 'Dio',
                            'cover' => '/assets/dev/covers/scenario_5_dio.svg',
                            'source' => 'radioboss',
                            'is_program' => false,
                            'program' => null,
                            'played_at' => $now->copy()->subMinutes(15)->toIso8601String(),
                        ],
                    ],
                    'history_stale' => false,
                ]);
                break;

            case 'same_title_diff_artist':
                // 14. Identidad Completa: Canción actual "Changes" de Black Sabbath, y próxima "Changes" de David Bowie
                // Demuestra que dos artistas con canciones homónimas no ocultan erróneamente la próxima pista
                $status = array_merge($basePayload, [
                    'es_bloque_programa' => false,
                    'es_programa_anticipo' => false,
                    'track' => [
                        'id' => 7001,
                        'title' => 'Changes',
                        'artist' => 'Black Sabbath',
                        'album' => 'Vol. 4',
                        'cover' => '/assets/dev/covers/scenario_1_trooper.svg',
                        'cover_is_fallback' => false,
                        'meta_source' => 'radioboss',
                        'meta_updated_at' => $timestamp,
                        'lyrics' => "I'm going through changes...",
                        'band_info' => 'Black Sabbath - Changes.',
                        'band_biography' => 'Balada clásica del álbum Vol. 4.',
                        'band_biography_source' => 'editorial',
                        'band_thumbnail' => '/assets/dev/covers/scenario_1_trooper.svg',
                        'is_live' => true,
                        'duration_seconds' => 284,
                        'elapsed_seconds' => 45,
                    ],
                    'next_track' => [
                        'id' => 7002,
                        'title' => 'Changes',
                        'artist' => 'David Bowie',
                        'album' => 'Hunky Dory',
                        'cover' => '/assets/dev/covers/scenario_5_dio.svg',
                        'line' => 'David Bowie - Changes',
                        'source' => 'radioboss',
                    ],
                ]);
                break;

            case 'short_song_fast_repetition':
                // 15. Repetición auténtica < 5 min: Canción punk de 72 segundos repetida a los 85 segundos
                // Demuestra que la deduplicación no elimina erróneamente repeticiones genuinas con duración transcurrida
                $status = array_merge($basePayload, [
                    'history' => [
                        [
                            'id' => 8001,
                            'title' => 'Blitzkrieg Bop',
                            'artist' => 'Ramones',
                            'cover' => '/assets/dev/covers/scenario_1_trooper.svg',
                            'source' => 'radioboss',
                            'is_program' => false,
                            'program' => null,
                            'duration_seconds' => 72,
                            'played_at' => $now->toIso8601String(),
                        ],
                        [
                            'id' => 8002,
                            'title' => 'Blitzkrieg Bop',
                            'artist' => 'Ramones',
                            'cover' => '/assets/dev/covers/scenario_1_trooper.svg',
                            'source' => 'radioboss',
                            'is_program' => false,
                            'program' => null,
                            'duration_seconds' => 72,
                            'played_at' => $now->copy()->subSeconds(85)->toIso8601String(), // Repetición legítima a los 85s (> 72s duración)
                        ],
                        [
                            'id' => 8003,
                            'title' => 'Paranoid',
                            'artist' => 'Black Sabbath',
                            'cover' => '/assets/dev/covers/scenario_1_trooper.svg',
                            'source' => 'radioboss',
                            'is_program' => false,
                            'program' => null,
                            'duration_seconds' => 170,
                            'played_at' => $now->copy()->subMinutes(6)->toIso8601String(),
                        ],
                    ],
                    'history_stale' => false,
                ]);
                break;

            case 'jingle_bell_rock_legitimate':
                // 16. Filtro editorial estricto: Canción real con palabra "Jingle" no se confunde con jingle técnico
                $status = array_merge($basePayload, [
                    'es_bloque_programa' => false,
                    'es_programa_anticipo' => false,
                    'track' => [
                        'id' => 9001,
                        'title' => 'Jingle Bell Rock',
                        'artist' => 'Bobby Helms',
                        'album' => 'Jingle Bell Rock',
                        'cover' => '/assets/dev/covers/scenario_1_trooper.svg',
                        'cover_is_fallback' => false,
                        'meta_source' => 'radioboss',
                        'meta_updated_at' => $timestamp,
                        'lyrics' => "Jingle bell, jingle bell, jingle bell rock...",
                        'band_info' => 'Bobby Helms.',
                        'band_biography' => 'Clásico del rock.',
                        'band_biography_source' => 'editorial',
                        'band_thumbnail' => '/assets/dev/covers/scenario_1_trooper.svg',
                        'is_live' => true,
                        'duration_seconds' => 130,
                        'elapsed_seconds' => 20,
                    ],
                    'next_track' => [
                        'id' => 9002,
                        'title' => 'Cuna de Caín',
                        'artist' => 'Mägo de Oz',
                        'album' => 'Finisterra',
                        'cover' => '/assets/dev/covers/scenario_5_dio.svg',
                        'line' => 'Mägo de Oz - Cuna de Caín',
                        'source' => 'radioboss',
                    ],
                ]);
                break;

            default:
                $status = $basePayload;
                break;
        }

        \Illuminate\Support\Facades\Cache::put('dev_player_simulated_status', $status, 3600);
    }

    private function ensureSyntheticDataExists(): void
    {
        // 1. MasterProgram 1: La Hora del Metal
        MasterProgram::query()->updateOrCreate(
            ['nombre' => 'La Hora del Metal'],
            [
                'conductor' => 'Carlos Metalhead',
                'dia_transmision' => 'MIERCOLES',
                'hora_transmision' => '18:00:00',
                'duracion_minutos' => 60,
                'genero' => 'Heavy Metal',
                'pais' => 'España',
                'activo' => true,
                'descripcion' => 'Programa insignia de heavy metal clásico conducido por Carlos Metalhead.',
            ]
        );

        // 2. MasterProgram 2: El Garaje del Rock (para escenario genérico sin emisión específica)
        MasterProgram::query()->updateOrCreate(
            ['nombre' => 'El Garaje del Rock'],
            [
                'conductor' => 'Laura Garaje',
                'dia_transmision' => 'VIERNES',
                'hora_transmision' => '20:00:00',
                'duracion_minutos' => 60,
                'genero' => 'Garage Rock / Indie',
                'pais' => 'Argentina',
                'activo' => true,
                'descripcion' => 'Programa insignia de garage rock, grunge y bandas independientes conducido por Laura Garaje.',
            ]
        );

        // 3. RadioArtist 1: Iron Maiden
        RadioArtist::query()->updateOrCreate(
            ['name' => 'Iron Maiden'],
            [
                'country' => 'Reino Unido',
                'genre' => 'Heavy Metal',
                'biography' => 'Iron Maiden es una banda británica de heavy metal fundada en 1975 por el bajista Steve Harris. Pionera de la Nueva Ola del Heavy Metal Británico (NWOBHM).',
                'formed_year' => 1975,
            ]
        );

        // 4. RadioArtist 2: Dio
        RadioArtist::query()->updateOrCreate(
            ['name' => 'Dio'],
            [
                'country' => 'Estados Unidos',
                'genre' => 'Heavy Metal',
                'biography' => 'Dio fue una banda estadounidense de heavy metal formada en 1982 por Ronnie James Dio.',
                'formed_year' => 1982,
            ]
        );
    }
}
