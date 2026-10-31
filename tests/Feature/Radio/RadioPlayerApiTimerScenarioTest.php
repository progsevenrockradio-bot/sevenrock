<?php

declare(strict_types=1);

namespace Tests\Feature\Radio;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validación de Contratos de API para Temporizador del Reproductor (Pruebas Simuladas de Backend)
 * 
 * NOTA: Esta suite valida que los endpoints de la API expongan las duraciones, tiempos
 * transcurridos y banderas exactamente como los requiere el frontend. No evalúa la ejecución
 * del motor de eventos JavaScript ni la interacción con el widget externo de RadioBOSS en el DOM.
 */
class RadioPlayerApiTimerScenarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::forget('dev_player_simulated_status');
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\Cache::forget('dev_player_simulated_status');
        parent::tearDown();
    }

    public function test_api_scenario_live_song_supplies_duration_and_elapsed_payload(): void
    {
        $response = $this->postJson(route('dev.player-scenario.set'), [
            'scenario' => 'song',
            'has_next_track' => true,
        ]);
        $response->assertOk();

        $statusResponse = $this->getJson(route('api.player.status'));
        $statusResponse->assertOk();
        $data = $statusResponse->json('data');

        $this->assertTrue($data['track']['is_live']);
        $this->assertSame(252, $data['track']['duration_seconds']);
        $this->assertSame(45, $data['track']['elapsed_seconds']);
        $this->assertSame('The Trooper', $data['track']['title']);
        $this->assertSame('Iron Maiden', $data['track']['artist']);
        $this->assertFalse($data['track']['es_bloque_programa']);
    }

    public function test_api_scenario_track_change_generates_distinct_signature_and_times(): void
    {
        // 1. Pista A (The Trooper: 252s)
        $this->postJson(route('dev.player-scenario.set'), ['scenario' => 'song'])->assertOk();
        $status1 = $this->getJson(route('api.player.status'))->json('data');
        $this->assertSame(252, $status1['track']['duration_seconds']);

        // 2. Pista B (Holy Diver: 340s)
        $this->postJson(route('dev.player-scenario.set'), ['scenario' => 'program_ended'])->assertOk();
        $status2 = $this->getJson(route('api.player.status'))->json('data');

        $this->assertSame('Holy Diver', $status2['track']['title']);
        $this->assertSame(340, $status2['track']['duration_seconds']);
        $this->assertSame(20, $status2['track']['elapsed_seconds']);
        $this->assertNotSame($status1['track']['signature'], $status2['track']['signature']);
    }

    public function test_api_scenario_program_block_duration_is_independent_of_previous_song(): void
    {
        // Canción previa: 252s
        $this->postJson(route('dev.player-scenario.set'), ['scenario' => 'song'])->assertOk();
        $songStatus = $this->getJson(route('api.player.status'))->json('data');
        $this->assertSame(252, $songStatus['track']['duration_seconds']);

        // Bloque de programa: 3600s
        $this->postJson(route('dev.player-scenario.set'), ['scenario' => 'program_specific'])->assertOk();
        $programStatus = $this->getJson(route('api.player.status'))->json('data');
        $this->assertTrue($programStatus['track']['es_bloque_programa']);
        $this->assertSame(3600, $programStatus['track']['duration_seconds']);
        $this->assertSame(320, $programStatus['track']['elapsed_seconds']);
        $this->assertNotEquals(252, $programStatus['track']['duration_seconds']);

        // Retorno a canción: 340s
        $this->postJson(route('dev.player-scenario.set'), ['scenario' => 'program_ended'])->assertOk();
        $returnSongStatus = $this->getJson(route('api.player.status'))->json('data');
        $this->assertFalse($returnSongStatus['track']['es_bloque_programa']);
        $this->assertSame(340, $returnSongStatus['track']['duration_seconds']);
        $this->assertNotEquals(3600, $returnSongStatus['track']['duration_seconds']);
    }

    public function test_api_scenario_missing_times_provides_zero_duration_and_elapsed(): void
    {
        $this->postJson(route('dev.player-scenario.set'), ['scenario' => 'missing_time'])->assertOk();
        $status = $this->getJson(route('api.player.status'))->json('data');

        $this->assertTrue($status['track']['is_live']);
        $this->assertSame(0, $status['track']['duration_seconds']);
        $this->assertSame(0, $status['track']['elapsed_seconds']);
        $this->assertSame('Transmisión Oficial Seven Rock Radio', $status['track']['title']);
    }

    public function test_api_scenario_podcast_archive_sets_non_live_and_audio_url(): void
    {
        $this->postJson(route('dev.player-scenario.set'), ['scenario' => 'podcast'])->assertOk();
        $status = $this->getJson(route('api.player.status'))->json('data');

        $this->assertFalse($status['track']['is_live']);
        $this->assertNotEmpty($status['track']['audio_url']);
        $this->assertStringContainsString('archive.org', $status['track']['audio_url']);
        $this->assertSame(3240, $status['track']['duration_seconds']);
        $this->assertSame(315, $status['track']['elapsed_seconds']);
    }

    public function test_api_scenario_similar_duration_tracks_have_distinct_identities(): void
    {
        // Pista 1 (The Trooper: 252s)
        $this->postJson(route('dev.player-scenario.set'), ['scenario' => 'song'])->assertOk();
        $statusA = $this->getJson(route('api.player.status'))->json('data');

        // Pista 2 (Rainbow in the Dark: 255s - diferencia de solo 3s)
        $this->postJson(route('dev.player-scenario.set'), ['scenario' => 'delayed_widget'])->assertOk();
        $statusB = $this->getJson(route('api.player.status'))->json('data');

        $this->assertSame('The Trooper', $statusA['track']['title']);
        $this->assertSame('Rainbow in the Dark', $statusB['track']['title']);
        $this->assertSame(252, $statusA['track']['duration_seconds']);
        $this->assertSame(255, $statusB['track']['duration_seconds']);
        $this->assertLessThanOrEqual(5, abs($statusA['track']['duration_seconds'] - $statusB['track']['duration_seconds']));
        $this->assertNotSame($statusA['track']['signature'], $statusB['track']['signature']);
    }

    public function test_api_scenario_data_loss_on_same_track_reports_zero_times(): void
    {
        $this->postJson(route('dev.player-scenario.set'), ['scenario' => 'data_loss_same_track'])->assertOk();
        $status = $this->getJson(route('api.player.status'))->json('data');

        $this->assertSame('The Trooper', $status['track']['title']);
        $this->assertSame(0, $status['track']['duration_seconds']);
        $this->assertSame(0, $status['track']['elapsed_seconds']);
    }
}

