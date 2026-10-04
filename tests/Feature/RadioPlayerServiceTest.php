<?php

namespace Tests\Feature;

use App\Models\MasterProgram;
use App\Models\MasterProgramEmision;
use App\Services\RadioPlayerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RadioPlayerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rock_al_palo_bloque_programa_true()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 15:30:00', 'America/Caracas')); // A Tuesday

        $master = MasterProgram::create([
            'nombre' => 'Rock al Palo',
            'conductor' => 'Tester',
            'dia_transmision' => 'MARTES',
            'hora_transmision' => '15:00:00',
            'duracion_minutos' => 60,
            'timezone' => 'America/Caracas',
            'activo' => true,
            'genero' => 'Rock',
        ]);

        $service = app(RadioPlayerService::class);
        $state = $service->resolve();

        $this->assertTrue($state['es_bloque_programa']);
    }

    public function test_alto_voltaje_bug_fix()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 19:30:00', 'America/Caracas')); // A Saturday

        $master = MasterProgram::create([
            'nombre' => 'Alto Voltaje',
            'conductor' => 'Tester',
            'dia_transmision' => 'SABADO',
            'hora_transmision' => '19:00:00',
            'duracion_minutos' => 120,
            'timezone' => 'America/Caracas',
            'activo' => true,
            'genero' => 'Rock',
        ]);

        $service = app(RadioPlayerService::class);
        $state = $service->resolve();

        $this->assertTrue($state['es_bloque_programa']);
    }

    public function test_emision_sabado_13_activa()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 13:30:00', 'America/Caracas')); // A Saturday

        $master = MasterProgram::create([
            'nombre' => 'Rock al Palo',
            'conductor' => 'Tester',
            'dia_transmision' => 'MARTES',
            'hora_transmision' => '15:00:00',
            'duracion_minutos' => 60,
            'timezone' => 'America/Caracas',
            'activo' => true,
            'genero' => 'Rock',
        ]);

        MasterProgramEmision::create([
            'master_program_id' => $master->id,
            'tipo' => 'en_vivo',
            'dia_semana' => 'SABADO',
            'hora_inicio' => '13:00:00',
            'duracion_minutos' => 60,
            'activo' => true,
        ]);

        $service = app(RadioPlayerService::class);
        $state = $service->resolve();

        $this->assertTrue($state['es_bloque_programa']);
    }

    public function test_fuera_de_horario_y_sin_metadatos()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 10:00:00', 'America/Caracas')); // A Saturday, 10 AM

        $master = MasterProgram::create([
            'nombre' => 'Rock al Palo',
            'conductor' => 'Tester',
            'dia_transmision' => 'MARTES',
            'hora_transmision' => '15:00:00',
            'duracion_minutos' => 60,
            'timezone' => 'America/Caracas',
            'activo' => true,
            'genero' => 'Rock',
        ]);

        $service = app(RadioPlayerService::class);
        $state = $service->resolve();

        $this->assertFalse($state['es_bloque_programa']);
        $this->assertNull($state['program_name']);
    }

    public function test_metadatos_title_rock_al_palo_in_sabado()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 10:00:00', 'America/Caracas')); // A Saturday, 10 AM, outside schedule

        $master = MasterProgram::create([
            'nombre' => 'Rock al Palo',
            'conductor' => 'Tester',
            'dia_transmision' => 'MARTES',
            'hora_transmision' => '15:00:00',
            'duracion_minutos' => 60,
            'timezone' => 'America/Caracas',
            'activo' => true,
            'genero' => 'Rock',
        ]);

        // Mock state to return title "06.12.- ROCK AL PALO"
        app(\App\Support\RadioPlayerStateStore::class)->write([
            'title' => '06.12.- ROCK AL PALO',
            'artist' => 'Artist',
        ]);

        $service = app(RadioPlayerService::class);
        $state = $service->resolve();

        $this->assertTrue($state['es_bloque_programa']);
        $this->assertEquals('Rock al Palo', $state['program_name']);
    }
}
