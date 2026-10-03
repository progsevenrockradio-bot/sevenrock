<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MasterProgram;
use App\Support\RadioPlayerStateStore;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlayerProgramInfoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        app(RadioPlayerStateStore::class)->forget();
    }

    protected function tearDown(): void
    {
        app(RadioPlayerStateStore::class)->forget();
        parent::tearDown();
    }

    public function test_player_status_returns_program_info_when_master_program_is_active_by_schedule(): void
    {
        app(RadioPlayerStateStore::class)->forget();

        MasterProgram::query()->create([
            'nombre' => 'ROCK AL PALO',
            'conductor' => 'Charly Garcia',
            'genero' => 'Rock',
            'dia_transmision' => 'MARTES',
            'hora_transmision' => '15:05',
            'duracion_minutos' => 60,
            'timezone' => 'America/Caracas',
            'activo' => true,
            'caratula_url' => 'https://example.com/cover.jpg',
            'descripcion' => 'El mejor rock nacional e internacional',
        ]);

        // a) Simular martes 15:30 America/Caracas (dentro del bloque MARTES 15:05 con duración 60 min)
        $tuesdayInsideWindow = Carbon::parse('2026-10-06 15:30:00', 'America/Caracas');
        Carbon::setTestNow($tuesdayInsideWindow);

        $response = $this->getJson('/api/player/status');

        $response->assertOk()
            ->assertJsonPath('data.es_bloque_programa', true);

        $data = $response->json('data');
        $this->assertNotEmpty($data['program_name']);
        $this->assertNotNull($data['program']);
        $this->assertNotEmpty($data['program']['name']);
        $this->assertSame('ROCK AL PALO', $data['program']['name']);

        // b) Fuera de ese horario: ej. martes 17:00 America/Caracas
        $tuesdayOutsideWindow = Carbon::parse('2026-10-06 17:00:00', 'America/Caracas');
        Carbon::setTestNow($tuesdayOutsideWindow);
        app(RadioPlayerStateStore::class)->forget();

        $responseOff = $this->getJson('/api/player/status');

        $responseOff->assertOk()
            ->assertJsonPath('data.es_bloque_programa', false)
            ->assertJsonPath('data.program', null);

        Carbon::setTestNow();
    }

    public function test_player_status_returns_program_info_when_metadata_matches_outside_schedule(): void
    {
        app(RadioPlayerStateStore::class)->forget();

        MasterProgram::query()->create([
            'nombre' => 'ROCK AL PALO',
            'conductor' => 'Charly Garcia',
            'genero' => 'Rock',
            'dia_transmision' => 'MARTES',
            'hora_transmision' => '15:05',
            'duracion_minutos' => 60,
            'timezone' => 'America/Caracas',
            'activo' => true,
            'caratula_url' => 'https://example.com/cover.jpg',
            'descripcion' => 'El mejor rock nacional e internacional',
        ]);

        // Simular un sábado (fuera del horario del martes 15:05)
        $saturday = Carbon::parse('2026-10-10 12:00:00', 'America/Caracas');
        Carbon::setTestNow($saturday);

        // Escribir en RadioPlayerStateStore título con prefijo numérico y artista de la radio
        app(RadioPlayerStateStore::class)->write([
            'title' => '06.12.- ROCK AL PALO',
            'artist' => 'Seven Rock Radio',
        ]);

        $response = $this->getJson('/api/player/status');

        $response->assertOk()
            ->assertJsonPath('data.es_bloque_programa', true)
            ->assertJsonPath('data.program_name', 'ROCK AL PALO');

        $data = $response->json('data');
        $this->assertNotNull($data['program']);
        $this->assertNotEmpty($data['program']['name']);
        $this->assertSame('ROCK AL PALO', $data['program']['name']);

        // Limpiar el estado y probar fuera de todo (sin metadatos de programa y sin horario)
        app(RadioPlayerStateStore::class)->forget();

        $responseOff = $this->getJson('/api/player/status');

        $responseOff->assertOk()
            ->assertJsonPath('data.es_bloque_programa', false)
            ->assertJsonPath('data.program', null);

        Carbon::setTestNow();
    }
}
