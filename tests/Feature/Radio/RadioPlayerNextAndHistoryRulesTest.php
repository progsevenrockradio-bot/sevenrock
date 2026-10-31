<?php

declare(strict_types=1);

namespace Tests\Feature\Radio;

use App\Models\PlayHistory;
use App\Models\Program;
use App\Services\RadioPlayerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RadioPlayerNextAndHistoryRulesTest extends TestCase
{
    use RefreshDatabase;

    private RadioPlayerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = app(RadioPlayerService::class);
    }

    /**
     * Regla 1: resolveNextTrack comprueba procedencia y vigencia.
     */
    public function test_api_next_track_prevalece_sobre_stored_antiguo(): void
    {
        $stored = [
            'next_track' => [
                'title' => 'Canción Vieja en Stored',
                'artist' => 'Banda Antigua',
                'updated_at' => now()->subMinutes(10)->toIso8601String(),
            ],
        ];

        $api = [
            'title' => 'Tema al Aire',
            'artist' => 'Artista al Aire',
            'next_track' => [
                'title' => 'Tema Fresco API',
                'artist' => 'Artista Fresco',
            ],
        ];

        $currentTrack = [
            'title' => 'Tema al Aire',
            'artist' => 'Artista al Aire',
        ];

        $resolved = $this->service->resolveNextTrack($stored, $api, $currentTrack, null);

        $this->assertNotNull($resolved);
        $this->assertSame('Tema Fresco API', $resolved['title']);
        $this->assertSame('Artista Fresco', $resolved['artist']);
        $this->assertSame('api', $resolved['source']);
    }

    public function test_api_sin_next_track_no_permite_stored_antiguo(): void
    {
        // La API está activa (reporta pista actual), pero su cola no tiene próxima pista
        $stored = [
            'next_track' => [
                'title' => 'Canción Antigua Stored',
                'artist' => 'Banda',
                'updated_at' => now()->subMinutes(2)->toIso8601String(),
            ],
        ];

        $api = [
            'title' => 'Tema al Aire',
            'artist' => 'Artista al Aire',
            // next_track ausente
        ];

        $currentTrack = [
            'title' => 'Tema al Aire',
            'artist' => 'Artista al Aire',
        ];

        $resolved = $this->service->resolveNextTrack($stored, $api, $currentTrack, null);

        // Debe retornar null para no arrastrar el stored obsoleto
        $this->assertNull($resolved);
    }

    public function test_stored_next_track_caducado_se_oculta_ante_caida_de_fuente(): void
    {
        // API caída (vacía), stored con más de 5 minutos
        $stored = [
            'next_track' => [
                'title' => 'Antiguo',
                'artist' => 'Banda',
                'updated_at' => now()->subMinutes(6)->toIso8601String(),
            ],
        ];

        $api = [];
        $currentTrack = ['title' => 'Tema Actual', 'artist' => 'Artista'];

        $resolved = $this->service->resolveNextTrack($stored, $api, $currentTrack, null);

        $this->assertNull($resolved);
    }

    public function test_politica_sin_fecha_caduca_si_cancion_actual_ha_terminado(): void
    {
        // La fuente no proporciona fecha en next_track, pero la canción actual ya superó su duración
        $stored = [
            'next_track' => [
                'title' => 'Tema Sin Fecha',
                'artist' => 'Banda',
            ],
        ];

        $api = [];
        $currentTrack = [
            'title' => 'Tema en Curso',
            'artist' => 'Artista',
            'duration_seconds' => 200,
            'elapsed_seconds' => 205, // Duración superada -> el next_track ya no está vigente
        ];

        $resolved = $this->service->resolveNextTrack($stored, $api, $currentTrack, null);

        $this->assertNull($resolved);
    }

    /**
     * Regla 2: hasValidNextTrack / resolveNextTrack compara identidad completa (artista y título).
     */
    public function test_mismo_titulo_pero_distinto_artista_no_se_oculta(): void
    {
        // Caso: Canción actual "Changes" de Black Sabbath, próxima "Changes" de David Bowie
        $currentTrack = [
            'title' => 'Changes',
            'artist' => 'Black Sabbath',
        ];

        $api = [
            'next_track' => [
                'title' => 'Changes',
                'artist' => 'David Bowie',
            ],
        ];

        $resolved = $this->service->resolveNextTrack([], $api, $currentTrack, null);

        $this->assertNotNull($resolved, 'Dos artistas distintos con el mismo título deben conservarse como próxima canción');
        $this->assertSame('Changes', $resolved['title']);
        $this->assertSame('David Bowie', $resolved['artist']);
    }

    public function test_mismo_titulo_y_mismo_artista_se_retira_porque_ya_inicio(): void
    {
        $currentTrack = [
            'title' => 'The Trooper',
            'artist' => 'Iron Maiden',
        ];

        $api = [
            'next_track' => [
                'title' => 'The Trooper',
                'artist' => 'Iron Maiden',
            ],
        ];

        $resolved = $this->service->resolveNextTrack([], $api, $currentTrack, null);

        $this->assertNull($resolved, 'Si coincide título y artista, ya está sonando y debe retirarse de próxima canción');
    }

    /**
     * Regla 3: Filtro estricto sin descarte ciego de palabras jingle/promo/cuna en canciones reales.
     */
    public function test_canciones_reales_con_palabras_clave_no_se_clasifican_como_jingles(): void
    {
        // "Jingle Bell Rock" - Bobby Helms
        $this->assertFalse(
            $this->service->isTechnicalMarkerOrJingle('Jingle Bell Rock', 'Bobby Helms')
        );

        // "Cuna de Caín" - Mägo de Oz
        $this->assertFalse(
            $this->service->isTechnicalMarkerOrJingle('Cuna de Caín', 'Mägo de Oz')
        );

        // "Promo Song" - The Promos
        $this->assertFalse(
            $this->service->isTechnicalMarkerOrJingle('Promo Song', 'The Promos')
        );
    }

    public function test_clasificacion_explicita_y_evidencia_tecnica_se_clasifican_como_jingles(): void
    {
        // Clasificación explícita de metadatos
        $this->assertTrue(
            $this->service->isTechnicalMarkerOrJingle('Intro Seven', 'Radio', ['type' => 'jingle'])
        );
        $this->assertTrue(
            $this->service->isTechnicalMarkerOrJingle('Barrido', 'Radio', ['type' => 'sweeper'])
        );

        // Evidencia técnica de rutas absolutas
        $this->assertTrue(
            $this->service->isTechnicalMarkerOrJingle('C:\\RadioBOSS\\jingle1.mp3', '')
        );
        $this->assertTrue(
            $this->service->isTechnicalMarkerOrJingle('/var/audio/station_id.mp3', '')
        );

        // Archivos sueltos como título sin metadata
        $this->assertTrue(
            $this->service->isTechnicalMarkerOrJingle('sweep_station.mp3', '')
        );

        // Identificador literal de software
        $this->assertTrue(
            $this->service->isTechnicalMarkerOrJingle('RadioBOSS', 'RadioBOSS')
        );
        $this->assertTrue(
            $this->service->isTechnicalMarkerOrJingle('Station ID - Seven Rock Radio', '')
        );
    }

    /**
     * Regla 4: Deduplicación por identificador e inicio real sin descartar repeticiones auténticas < 5m.
     */
    public function test_repeticion_autentica_cancion_corta_dentro_de_5_minutos_se_conserva(): void
    {
        // Canción punk de 72 segundos:
        // Pase 1: hace 85 segundos
        // Pase 2: ahora (hace 0 segundos)
        // Ambas ocurrieron dentro de los 5 minutos, pero la separación (85s) supera su duración (72s).
        $now = Carbon::now('America/Caracas');

        PlayHistory::query()->create([
            'id' => 9101,
            'title' => 'Blitzkrieg Bop',
            'artist' => 'Ramones',
            'duration_seconds' => 72,
            'played_at' => $now,
            'source' => 'radioboss',
        ]);

        PlayHistory::query()->create([
            'id' => 9102,
            'title' => 'Blitzkrieg Bop',
            'artist' => 'Ramones',
            'duration_seconds' => 72,
            'played_at' => $now->copy()->subSeconds(85),
            'source' => 'radioboss',
        ]);

        $history = $this->service->latestHistory(10);

        $this->assertCount(2, $history, 'La repetición legítima tras finalizar la duración debe conservarse');
        $this->assertSame('Blitzkrieg Bop', $history[0]['title']);
        $this->assertSame('Blitzkrieg Bop', $history[1]['title']);
    }

    public function test_sondeo_redundante_inmediato_se_deduplica(): void
    {
        $now = Carbon::now('America/Caracas');

        PlayHistory::query()->create([
            'id' => 9201,
            'title' => 'The Trooper',
            'artist' => 'Iron Maiden',
            'duration_seconds' => 252,
            'played_at' => $now,
            'source' => 'radioboss',
        ]);

        // Duplicado de sondeo redundante a los 10 segundos
        PlayHistory::query()->create([
            'id' => 9202,
            'title' => 'The Trooper',
            'artist' => 'Iron Maiden',
            'duration_seconds' => 252,
            'played_at' => $now->copy()->subSeconds(10),
            'source' => 'radioboss',
        ]);

        $history = $this->service->latestHistory(10);

        $this->assertCount(1, $history, 'El sondeo continuo dentro de la misma canción debe deduplicarse a 1 sola entrada');
    }
}

