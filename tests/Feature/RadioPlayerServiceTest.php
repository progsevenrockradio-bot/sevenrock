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

    public function test_coincidencia_por_nombre_de_fichero_fuera_de_horario()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 10:00:00', 'America/Caracas')); // Outside schedule

        $master = MasterProgram::create([
            'nombre' => 'Polución',
            'conductor' => 'Yury Fernández',
            'dia_transmision' => 'JUEVES',
            'hora_transmision' => '18:00:00',
            'duracion_minutos' => 60,
            'timezone' => 'America/Caracas',
            'activo' => true,
            'genero' => 'Rock',
        ]);

        $episode = \App\Models\RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Polución',
            'live_title' => 'POLUCIÓN PODCAST 52',
            'conductor' => 'Yury Fernández',
            'numero_episodio' => 52,
            'fecha_emision' => '2026-10-15',
            'duration_seconds' => 3600,
        ]);

        // State sends generic title or empty title, but brings file name
        app(\App\Support\RadioPlayerStateStore::class)->write([
            'title' => 'Transmisión oficial',
            'artist' => 'Seven Rock Radio',
            'file' => '\\04.- JUEVES\\18.- POLUCION\\052.- POLUCION 15-10-2026.mp3',
        ]);

        $service = app(RadioPlayerService::class);
        $state = $service->resolve();

        $this->assertTrue($state['es_bloque_programa']);
        $this->assertEquals('Polución', $state['program_name']);
        $this->assertEquals('POLUCIÓN PODCAST 52', $state['track']['title']);
        $this->assertEquals('Polución', $state['track']['artist']);
        $this->assertEquals('fichero', $service->getLastDiagnosis()['match_via']);
    }

    public function test_coincidencia_por_caratula()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 10:00:00', 'America/Caracas')); // Outside schedule

        $master = MasterProgram::create([
            'nombre' => 'Metalurgia',
            'conductor' => 'Tester',
            'caratula_url' => 'uploads/programs/metalurgia_cover.jpg',
            'dia_transmision' => 'VIERNES',
            'hora_transmision' => '20:00:00',
            'duracion_minutos' => 60,
            'timezone' => 'America/Caracas',
            'activo' => true,
            'genero' => 'Metal',
        ]);

        // Stream arrives with no file and generic title, but has the program cover URL
        app(\App\Support\RadioPlayerStateStore::class)->write([
            'title' => 'Transmisión oficial',
            'artist' => 'Seven Rock Radio',
            'cover' => 'https://sevenrockradio.com/uploads/programs/metalurgia_cover.jpg',
        ]);

        $service = app(RadioPlayerService::class);
        $state = $service->resolve();

        $this->assertTrue($state['es_bloque_programa']);
        $this->assertEquals('Metalurgia', $state['program_name']);
        $this->assertEquals('caratula', $service->getLastDiagnosis()['match_via']);
    }

    public function test_musica_normal_mantiene_datos_reales()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 10:00:00', 'America/Caracas'));

        // Normal song playing
        app(\App\Support\RadioPlayerStateStore::class)->write([
            'title' => 'Enter Sandman',
            'artist' => 'Metallica',
            'album' => 'Metallica',
            'cover' => 'https://example.com/metallica.jpg',
        ]);

        $service = app(RadioPlayerService::class);
        $state = $service->resolve();

        $this->assertFalse($state['es_bloque_programa']);
        $this->assertEquals('Enter Sandman', $state['track']['title']);
        $this->assertEquals('Metallica', $state['track']['artist']);
    }

    public function test_horario_con_margen_30_minutos()
    {
        // Scheduled at 15:00 on Tuesday. Test at 14:40 (20 minutes before, inside 30m margin)
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:40:00', 'America/Caracas'));

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

        \App\Models\RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Rock al Palo',
            'live_title' => 'Rock al Palo Ep 10',
            'conductor' => 'Tester',
            'fecha_emision' => '2026-10-06',
            'hora_inicio' => '15:00:00',
            'duration_seconds' => 3600,
        ]);

        app(\App\Support\RadioPlayerStateStore::class)->forget();

        $service = app(RadioPlayerService::class);
        $state = $service->resolve();

        $this->assertTrue($state['es_bloque_programa']);
        $this->assertEquals('Rock al Palo', $state['program_name']);
        $this->assertEquals('horario', $service->getLastDiagnosis()['match_via']);
    }
}
