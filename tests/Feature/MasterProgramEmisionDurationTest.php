<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MasterProgram;
use App\Models\MasterProgramEmision;
use App\Services\RadioPlayerService;
use App\Support\ProgramScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MasterProgramEmisionDurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'player.radioboss.api_url' => '',
            'player.radioboss.api_key' => '',
            'player.radioboss.metadata_txt_url' => '',
        ]);
    }

    /**
     * Test 1: Una emisión con duracion_segundos = 3600 (60 min)
     * -> la ventana informativa empieza 5 minutos antes (MARGIN_BEFORE_MINUTES = 5) y termina en la duración calculada (60 min).
     */
    public function test_emision_con_duracion_segundos_empieza_2_minutos_antes_y_dura_64_minutos(): void
    {
        $timezone = 'America/Caracas';

        $master = MasterProgram::create([
            'nombre'           => 'Panela Do Rock',
            'conductor'        => 'Host Test',
            'dia_transmision'  => 'MARTES',
            'hora_transmision' => '15:00:00',
            'duracion_minutos' => 120, // Master dice 120 min
            'timezone'         => $timezone,
            'activo'           => true,
            'genero'           => 'Rock',
        ]);

        $emision = MasterProgramEmision::create([
            'master_program_id' => $master->id,
            'tipo'              => 'retransmision',
            'dia_semana'        => 'MARTES',
            'hora_inicio'       => '15:00:00',
            'duracion_minutos'  => 120,
            'duracion_segundos' => 3600, // Duración real del archivo = 60 min
            'activo'            => true,
        ]);

        $service = app(RadioPlayerService::class);
        $scheduleService = app(ProgramScheduleService::class);

        // 14:54:00 (6 minutos antes, fuera del margen de 5 min) -> NO activa
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:54:00', $timezone));
        $this->assertFalse($service->isEmisionWindowActive(Carbon::now($timezone), $emision));

        // 14:55:00 (5 minutos antes: inicio de margen anticipado) -> ACTIVA
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:55:00', $timezone));
        $this->assertTrue($service->isEmisionWindowActive(Carbon::now($timezone), $emision));

        // 15:30:00 (en mitad del programa) -> ACTIVA
        Carbon::setTestNow(Carbon::parse('2026-10-06 15:30:00', $timezone));
        $this->assertTrue($service->isEmisionWindowActive(Carbon::now($timezone), $emision));
        $this->assertTrue($scheduleService->programIsLiveNow($master));

        // 16:00:00 (hora en que termina el archivo de 60m) -> ACTIVA
        Carbon::setTestNow(Carbon::parse('2026-10-06 16:00:00', $timezone));
        $this->assertTrue($service->isEmisionWindowActive(Carbon::now($timezone), $emision));

        // 16:01:00 (pasada la duración real del archivo) -> NO activa (no espera los 120 min del master)
        Carbon::setTestNow(Carbon::parse('2026-10-06 16:01:00', $timezone));
        $this->assertFalse($service->isEmisionWindowActive(Carbon::now($timezone), $emision));
    }

    /**
     * Test 2: Una emisión sin duracion_segundos -> la ventana usa duracion_minutos.
     */
    public function test_emision_sin_duracion_segundos_usa_duracion_minutos_original(): void
    {
        $timezone = 'America/Caracas';

        $master = MasterProgram::create([
            'nombre'           => 'Rock Clasico',
            'conductor'        => 'Host Test',
            'dia_transmision'  => 'MARTES',
            'hora_transmision' => '15:00:00',
            'duracion_minutos' => 60,
            'timezone'         => $timezone,
            'activo'           => true,
            'genero'           => 'Rock',
        ]);

        $emision = MasterProgramEmision::create([
            'master_program_id' => $master->id,
            'tipo'              => 'normal',
            'dia_semana'        => 'MARTES',
            'hora_inicio'       => '15:00:00',
            'duracion_minutos'  => 60,
            'duracion_segundos' => null, // Sin duración real
            'activo'            => true,
        ]);

        $service = app(RadioPlayerService::class);
        $scheduleService = app(ProgramScheduleService::class);

        // 14:54:00 (6 minutos antes) -> NO activa
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:54:00', $timezone));
        $this->assertFalse($service->isEmisionWindowActive(Carbon::now($timezone), $emision));

        // 14:55:00 (5 minutos antes) -> ACTIVA
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:55:00', $timezone));
        $this->assertTrue($service->isEmisionWindowActive(Carbon::now($timezone), $emision));

        // 15:00:00 -> ACTIVA
        Carbon::setTestNow(Carbon::parse('2026-10-06 15:00:00', $timezone));
        $this->assertTrue($service->isEmisionWindowActive(Carbon::now($timezone), $emision));
        $this->assertTrue($scheduleService->programIsLiveNow($master));

        // 16:00:00 -> ACTIVA (en el minuto 60)
        Carbon::setTestNow(Carbon::parse('2026-10-06 16:00:00', $timezone));
        $this->assertTrue($service->isEmisionWindowActive(Carbon::now($timezone), $emision));

        // 16:01:00 -> NO activa
        Carbon::setTestNow(Carbon::parse('2026-10-06 16:01:00', $timezone));
        $this->assertFalse($service->isEmisionWindowActive(Carbon::now($timezone), $emision));
    }

    /**
     * Test 3: Un programa EN VIVO no se ve afectado.
     */
    public function test_programa_en_vivo_no_se_ve_afectado(): void
    {
        $timezone = 'America/Caracas';

        $master = MasterProgram::create([
            'nombre'           => 'Concierto En Vivo',
            'conductor'        => 'Host Live',
            'dia_transmision'  => 'SABADO',
            'hora_transmision' => '20:00:00',
            'duracion_minutos' => 90,
            'timezone'         => $timezone,
            'activo'           => true,
            'genero'           => 'Rock',
        ]);

        $emision = MasterProgramEmision::create([
            'master_program_id' => $master->id,
            'tipo'              => 'en_vivo',
            'enlace'            => 'https://stream.example.com/live',
            'dia_semana'        => 'SABADO',
            'hora_inicio'       => '20:00:00',
            'duracion_minutos'  => 90,
            'duracion_segundos' => null, // Programa en vivo no tiene archivo grabado
            'activo'            => true,
        ]);

        $service = app(RadioPlayerService::class);
        $scheduleService = app(ProgramScheduleService::class);

        // 19:54:00 (6 minutos antes) -> NO activo
        Carbon::setTestNow(Carbon::parse('2026-10-10 19:54:00', $timezone));
        $this->assertFalse($service->isEmisionWindowActive(Carbon::now($timezone), $emision));

        // 19:55:00 (5 minutos antes) -> ACTIVO
        Carbon::setTestNow(Carbon::parse('2026-10-10 19:55:00', $timezone));
        $this->assertTrue($service->isEmisionWindowActive(Carbon::now($timezone), $emision));

        // 20:00:00 -> ACTIVO
        Carbon::setTestNow(Carbon::parse('2026-10-10 20:00:00', $timezone));
        $this->assertTrue($service->isEmisionWindowActive(Carbon::now($timezone), $emision));
        $this->assertTrue($scheduleService->programIsLiveNow($master));

        // 21:30:00 (exactamente 90 minutos después) -> ACTIVO
        Carbon::setTestNow(Carbon::parse('2026-10-10 21:30:00', $timezone));
        $this->assertTrue($service->isEmisionWindowActive(Carbon::now($timezone), $emision));

        // 21:31:00 -> NO activo
        Carbon::setTestNow(Carbon::parse('2026-10-10 21:31:00', $timezone));
        $this->assertFalse($service->isEmisionWindowActive(Carbon::now($timezone), $emision));
    }
}
