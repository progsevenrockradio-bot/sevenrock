<?php

declare(strict_types=1);

namespace Tests\Feature\Radio;

use App\Models\MasterProgram;
use App\Models\MasterProgramEmision;
use App\Models\RadioProgram;
use App\Models\Song;
use App\Services\RadioPlayerService;
use App\Support\RadioPlayerStateStore;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Escenario local ejecutable que valida el flujo y los contratos de datos
 * exactamente como los consume player.js desde las rutas de la API:
 *
 * 1. Canción activa ordinaria (canción real, lyrics disponibles, sin anticipo)
 * 2. Anticipo de programa (5 minutos antes): track activo sigue siendo canción, pero con es_programa_anticipo = true
 *    y banner de aviso en la pestaña de programa. Próxima canción disponible vs ausente.
 * 3. Programa en emisión: es_bloque_programa = true, sin canción real, metadatos de emisión y máster.
 * 4. Vuelta a canción tras el final del programa (determinado por hora de inicio + duración calculada sin margen posterior).
 * 5. Manejo de respuestas desordenadas en player.js (reversión evitada por timestamps).
 */
class RadioPlayerFrontendScenarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'player.radioboss.api_url' => '',
            'player.radioboss.metadata_txt_url' => '',
            'player.streams.direct' => 'https://stream.sevenrockradio.com/live',
            'player.streams.alt_direct' => '',
        ]);

        app(RadioPlayerStateStore::class)->forget();
    }

    protected function tearDown(): void
    {
        app(RadioPlayerStateStore::class)->forget();
        parent::tearDown();
    }

    public function test_player_status_endpoint_full_lifecycle_scenario(): void
    {
        $timezone = 'America/Caracas';

        // Configuración de MasterProgram y Emisión calculada
        // Emisión programada para el miércoles de 18:00 a 19:00 (duración 60 minutos)
        $master = MasterProgram::create([
            'nombre' => 'La Hora del Metal',
            'conductor' => 'Carlos Metalhead',
            'dia_transmision' => 'MIERCOLES',
            'hora_transmision' => '18:00:00',
            'duracion_minutos' => 60,
            'genero' => 'Heavy Metal',
            'pais' => 'España',
            'timezone' => $timezone,
            'activo' => true,
            'caratula_url' => 'https://media.sevenrockradio.com/covers/metal-show.jpg',
            'descripcion' => 'Lo mejor del heavy metal nacional e internacional.',
        ]);

        $baseDate = Carbon::parse('2026-10-14 00:00:00', $timezone); // Miércoles

        // Paso 1: 17:40:00 - Canción ordinaria (fuera del margen de anticipo de 5 minutos)
        Carbon::setTestNow($baseDate->copy()->setTime(17, 40, 0));

        app(RadioPlayerStateStore::class)->write([
            'title' => 'The Trooper',
            'artist' => 'Iron Maiden',
            'album' => 'Piece of Mind',
            'started_at' => Carbon::now()->toIso8601String(),
            'duration_seconds' => 252,
            'meta_source' => 'radioboss',
            'next_track' => [
                'title' => 'Painkiller',
                'artist' => 'Judas Priest',
                'album' => 'Painkiller',
            ],
        ]);

        $response1 = $this->getJson(route('api.player.status'));
        $response1->assertOk();
        $data1 = $response1->json('data');

        $this->assertSame('The Trooper', $data1['track']['title']);
        $this->assertSame('Iron Maiden', $data1['track']['artist']);
        $this->assertFalse($data1['track']['es_bloque_programa'] ?? false);
        $this->assertFalse($data1['track']['es_programa_anticipo'] ?? false);
        // Próxima canción disponible
        $this->assertNotNull($data1['next_track'] ?? null);
        $this->assertSame('Painkiller', $data1['next_track']['title']);
        $this->assertSame('Judas Priest', $data1['next_track']['artist']);

        // Paso 2: 17:56:00 - Ventana de Anticipo (4 minutos antes del inicio de 18:00)
        // La canción sigue sonando pero se activa el flag de aviso anticipado para player.js
        Carbon::setTestNow($baseDate->copy()->setTime(17, 56, 0));

        $response2 = $this->getJson(route('api.player.status'));
        $response2->assertOk();
        $data2 = $response2->json('data');

        $this->assertSame('The Trooper', $data2['track']['title']);
        $this->assertSame('Iron Maiden', $data2['track']['artist']);
        $this->assertTrue($data2['track']['es_programa_anticipo'] ?? false);
        $this->assertFalse($data2['track']['es_bloque_programa'] ?? false);
        // Información del programa próximo disponible para el aviso
        $this->assertNotNull($data2['program'] ?? null);
        $this->assertSame('La Hora del Metal', $data2['program']['nombre'] ?? $data2['program']['name'] ?? $data2['track']['program_name']);

        // Verificación de la API de información del programa (modal programInfo con país)
        $programInfoResponse = $this->getJson(route('api.player.program-info', ['program_id' => $master->id]));
        $programInfoResponse->assertOk();
        $this->assertSame('España', $programInfoResponse->json('data.country'));
        $this->assertSame('Carlos Metalhead', $programInfoResponse->json('data.host'));

        // Paso 3: 18:05:00 - Programa en Emisión (inicia el bloque)
        // Se emite el programa: ahora es_bloque_programa = true y no hay canción ordinaria
        Carbon::setTestNow($baseDate->copy()->setTime(18, 5, 0));

        app(RadioPlayerStateStore::class)->write([
            'title' => 'La Hora del Metal - Ep. 12',
            'artist' => 'Carlos Metalhead',
            'album' => 'La Hora del Metal',
            'started_at' => Carbon::now()->toIso8601String(),
            'duration_seconds' => 3600,
            'meta_source' => 'radioboss',
            'next_track' => null, // Próxima canción ausente durante el programa
        ]);

        $response3 = $this->getJson(route('api.player.status'));
        $response3->assertOk();
        $data3 = $response3->json('data');

        $this->assertTrue($data3['track']['es_bloque_programa'] ?? $data3['es_bloque_programa'] ?? false);
        $this->assertFalse($data3['track']['es_programa_anticipo'] ?? false);
        $this->assertSame('La Hora del Metal', $data3['program']['nombre'] ?? $data3['program']['name'] ?? $data3['track']['program_name']);
        // Próxima canción ausente
        $this->assertNull($data3['next_track'] ?? null);

        // Paso 4: 19:01:00 - Fin del programa según su duración calculada (60 min)
        // Exactamente al cumplirse la hora calculada, sin margen posterior, vuelve a ser canción
        Carbon::setTestNow($baseDate->copy()->setTime(19, 1, 0));

        app(RadioPlayerStateStore::class)->write([
            'title' => 'Holy Diver',
            'artist' => 'Dio',
            'album' => 'Holy Diver',
            'started_at' => Carbon::now()->toIso8601String(),
            'duration_seconds' => 340,
            'meta_source' => 'radioboss',
            'next_track' => [
                'title' => 'Rainbow in the Dark',
                'artist' => 'Dio',
            ],
        ]);

        $response4 = $this->getJson(route('api.player.status'));
        $response4->assertOk();
        $data4 = $response4->json('data');

        $this->assertSame('Holy Diver', $data4['track']['title']);
        $this->assertSame('Dio', $data4['track']['artist']);
        $this->assertFalse($data4['track']['es_bloque_programa'] ?? false);
        $this->assertFalse($data4['track']['es_programa_anticipo'] ?? false);
        $this->assertSame('Rainbow in the Dark', $data4['next_track']['title'] ?? '');

        // Paso 5: Contrato de timestamps para respuestas desordenadas en player.js
        // player.js compara new Date(payload.data.updated_at || payload.data.meta_updated_at).getTime()
        // con lastAppliedStatusTimestamp para descartar respuestas que lleguen tarde.
        $this->assertNotNull($data4['meta_updated_at'] ?? $data4['updated_at'] ?? null);
        $newerTimestamp = Carbon::parse($data4['meta_updated_at'] ?? $data4['updated_at'])->getTimestampMs();
        $olderTimestamp = Carbon::parse($data1['meta_updated_at'] ?? $data1['updated_at'])->getTimestampMs();

        $this->assertGreaterThan($olderTimestamp, $newerTimestamp);
    }
}
