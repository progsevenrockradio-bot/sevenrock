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
        $this->assertSame('On air', $payload['badge']);
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
            'archive_org_status' => 'synced',
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
}
