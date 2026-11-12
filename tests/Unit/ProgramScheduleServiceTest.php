<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Support\ProgramScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

class ProgramScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::flush();

        parent::tearDown();
    }

    public function test_home_uses_the_actual_repeat_slot_and_keeps_upcoming_programs_in_time_order(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 18:00', 'America/Caracas'));
        $program = MasterProgram::query()->create([
            'nombre' => 'Uno por semana', 'conductor' => 'Lucho',
            'dia_transmision' => 'VIERNES', 'hora_transmision' => '17:05',
            'timezone' => 'America/Caracas', 'duracion_minutos' => 120,
            'genero' => 'ROCK', 'activo' => true,
        ]);
        $program->emisiones()->createMany([
            ['dia_semana' => 'VIERNES', 'hora_inicio' => '17:05', 'tipo' => 'en_vivo', 'activo' => true, 'enlace' => 'https://example.com/live'],
            ['dia_semana' => 'MARTES', 'hora_inicio' => '17:05', 'tipo' => 'retransmision', 'activo' => true],
        ]);
        foreach ([
            ['Siguiente martes', 'MARTES', '20:00'],
            ['Programa miércoles', 'MIERCOLES', '19:00'],
            ['Programa viernes', 'VIERNES', '19:05'],
            ['Programa sábado', 'SABADO', '19:00'],
        ] as [$name, $day, $time]) {
            MasterProgram::query()->create([
                'nombre' => $name, 'conductor' => 'DJ', 'dia_transmision' => $day, 'hora_transmision' => $time,
                'timezone' => 'America/Caracas', 'duracion_minutos' => 60, 'genero' => 'ROCK', 'activo' => true,
            ]);
        }

        $service = app(ProgramScheduleService::class);
        $payload = $service->resolve(fresh: true);
        $this->assertSame('MARTES · 10/11 · 17:05', $payload['schedule']);
        $this->assertSame('retransmision', $payload['tipo_emision']);
        $this->assertSame('En emisión', $payload['badge']);
        $this->assertNull($payload['enlace_en_vivo']);
        $this->assertSame(
            ['Siguiente martes', 'Programa miércoles', 'Programa viernes', 'Programa sábado'],
            array_column($payload['upcoming'], 'show')
        );

        Carbon::setTestNow(Carbon::parse('2026-11-10 16:00', 'America/Caracas'));
        $payload = $service->resolve(fresh: true);
        $this->assertSame('MARTES · 10/11 · 17:05', $payload['schedule']);
        $this->assertSame('A continuación', $payload['badge']);
        $this->assertSame('retransmision', $payload['tipo_emision']);

        Carbon::setTestNow(Carbon::parse('2026-11-13 18:00', 'America/Caracas'));
        $payload = $service->resolve(fresh: true);
        $this->assertSame('VIERNES · 13/11 · 17:05', $payload['schedule']);
        $this->assertSame('en_vivo', $payload['tipo_emision']);
        $this->assertSame('En vivo', $payload['badge']);
    }

    public function test_it_resolves_a_live_program_that_crosses_midnight_in_the_program_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 22:30:00', 'UTC'));

        MasterProgram::query()->create([
            'nombre' => 'Night Shift',
            'conductor' => 'DJ Zero',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '23:00',
            'timezone' => 'Europe/Madrid',
            'duracion_minutos' => 180,
            'genero' => 'ROCK',
            'activo' => true,
        ]);

        $payload = app(ProgramScheduleService::class)->resolve();

        $this->assertSame('Night Shift', $payload['show']);
        $this->assertSame('En vivo', $payload['badge']);
    }

    public function test_it_adjusts_the_next_start_when_an_episode_is_linked_to_the_program(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00', 'UTC'));

        $program = MasterProgram::query()->create([
            'nombre' => 'Late Session',
            'conductor' => 'DJ Zero',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '11:00',
            'timezone' => 'UTC',
            'duracion_minutos' => 90,
            'genero' => 'ROCK',
            'activo' => true,
        ]);

        RadioProgram::query()->create([
            'titulo_programa' => 'Late Session Episode',
            'master_program_id' => $program->id,
            'numero_episodio' => 12,
            'dia_transmision' => 'LUNES',
            'hora_inicio' => '11:30:00',
            'hora_fin' => '12:30:00',
            'conductor' => 'DJ Zero',
            'fecha_emision' => '2026-05-18',
            'genero_musical' => 'ROCK',
            'sync_archive_org' => true,
            'archive_org_status' => 'archive_verified',
            'archive_org_metadata' => json_encode([
                'identifier' => 'late-session-episode',
                'remote_path' => 'late-session-episode.mp3',
            ]),
        ]);

        $service = app(ProgramScheduleService::class);
        $method = new ReflectionMethod($service, 'nextProgramStart');
        $method->setAccessible(true);

        $nextStart = $method->invoke($service, $program, Carbon::parse('2026-05-18 10:00:00', 'UTC'));

        $this->assertInstanceOf(Carbon::class, $nextStart);
        $this->assertSame('2026-05-18 11:30:00', $nextStart->toDateTimeString());
    }

    public function test_cambio_de_dia_transitions_programs_across_midnight(): void
    {
        $progLunes = MasterProgram::query()->create([
            'nombre' => 'Rock de Lunes', 'conductor' => 'Host 1',
            'dia_transmision' => 'LUNES', 'hora_transmision' => '22:00',
            'timezone' => 'America/Caracas', 'duracion_minutos' => 110,
            'genero' => 'ROCK', 'activo' => true,
        ]);
        $progMartes = MasterProgram::query()->create([
            'nombre' => 'Metal de Martes', 'conductor' => 'Host 2',
            'dia_transmision' => 'MARTES', 'hora_transmision' => '10:00',
            'timezone' => 'America/Caracas', 'duracion_minutos' => 120,
            'genero' => 'METAL', 'activo' => true,
        ]);

        // Lunes 23:00 Caracas (en emisión de lunes)
        Carbon::setTestNow(Carbon::parse('2026-11-16 23:00:00', 'America/Caracas'));
        $service = app(ProgramScheduleService::class);
        $payloadLunes = $service->resolve(fresh: true);
        $this->assertSame('Rock de Lunes', $payloadLunes['show']);
        $this->assertSame('En vivo', $payloadLunes['badge']);
        $this->assertStringContainsString('LUNES', $payloadLunes['schedule']);

        // Martes 00:05 Caracas (después de terminar lunes, antes del inicio de martes)
        Carbon::setTestNow(Carbon::parse('2026-11-17 00:05:00', 'America/Caracas'));
        $payloadMartes = $service->resolve(fresh: true);
        $this->assertSame('Metal de Martes', $payloadMartes['show']);
        $this->assertSame('A continuación', $payloadMartes['badge']);
        $this->assertStringContainsString('MARTES', $payloadMartes['schedule']);
    }

    public function test_cruce_de_medianoche_keeps_program_live_and_active_in_caracas_timezone(): void
    {
        MasterProgram::query()->create([
            'nombre' => 'Medianoche Rock',
            'conductor' => 'DJ Midnight',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '23:00',
            'timezone' => 'America/Caracas',
            'duracion_minutos' => 180, // Termina a las 02:00 del martes
            'genero' => 'ROCK',
            'activo' => true,
        ]);

        // Martes a la 01:15 en Caracas (cruzó medianoche)
        Carbon::setTestNow(Carbon::parse('2026-11-17 01:15:00', 'America/Caracas'));
        $service = app(ProgramScheduleService::class);
        $payload = $service->resolve(fresh: true);

        $this->assertSame('Medianoche Rock', $payload['show']);
        $this->assertSame('En vivo', $payload['badge']);
        $this->assertStringContainsString('LUNES', $payload['schedule']);
    }

    public function test_ausencia_de_emision_actual_resolves_next_scheduled_program_or_fallback(): void
    {
        // 1. Con programas en parrilla pero ninguno en vivo
        MasterProgram::query()->create([
            'nombre' => 'Tarde Clásica',
            'conductor' => 'DJ Classic',
            'dia_transmision' => 'MIERCOLES',
            'hora_transmision' => '16:00',
            'timezone' => 'America/Caracas',
            'duracion_minutos' => 120,
            'genero' => 'ROCK',
            'activo' => true,
        ]);

        // Miércoles a las 04:00 de la madrugada (sin emisión en vivo)
        Carbon::setTestNow(Carbon::parse('2026-11-18 04:00:00', 'America/Caracas'));
        $service = app(ProgramScheduleService::class);
        $payload = $service->resolve(fresh: true);

        $this->assertSame('Tarde Clásica', $payload['show']);
        $this->assertSame('A continuación', $payload['badge']);
        $this->assertStringContainsString('MIERCOLES', $payload['schedule']);

        // 2. Sin ningún programa en base de datos -> Retorna fallback seguro
        MasterProgram::query()->delete();
        $payloadFallback = $service->resolve(fresh: true);

        $this->assertNotEmpty($payloadFallback['title']);
        $this->assertSame('America/Caracas', $payloadFallback['timezone']);
        $this->assertSame('A continuación', $payloadFallback['badge']);
    }

    public function test_actualizacion_de_caches_and_version_invalidation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 10:00:00', 'America/Caracas'));

        $program = MasterProgram::query()->create([
            'nombre' => 'Versión Inicial',
            'conductor' => 'DJ One',
            'dia_transmision' => 'MIERCOLES',
            'hora_transmision' => '11:00',
            'timezone' => 'America/Caracas',
            'duracion_minutos' => 60,
            'genero' => 'ROCK',
            'activo' => true,
        ]);

        $service = app(ProgramScheduleService::class);
        $firstPayload = $service->resolve();
        $this->assertSame('Versión Inicial', $firstPayload['show']);

        // Comprobar que una segunda llamada usa la caché
        $cachedPayload = $service->resolve();
        $this->assertSame('Versión Inicial', $cachedPayload['show']);

        // Actualizar el programa debe invalidar la versión de caché
        $program->update(['nombre' => 'Versión Actualizada']);

        // Nueva instancia o llamada detecta la nueva versión y devuelve el valor actualizado
        $newService = app(ProgramScheduleService::class);
        $updatedPayload = $newService->resolve();
        $this->assertSame('Versión Actualizada', $updatedPayload['show']);
    }
}
