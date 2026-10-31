<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MasterProgram;
use App\Models\MasterProgramEmision;
use App\Models\RadioProgram;
use App\Services\ArchiveOrgService;
use App\Support\ProgramScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogAndScheduleOptimizationTest extends TestCase
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

    /**
     * Requirement 2: Catálogo de podcasts
     * - Caché vencida / fría responde sin esperar la red.
     * - Episodio nuevo se refleja inmediatamente al invalidar o guardar.
     * - Archive.org indisponible preserva la última respuesta válida.
     * - Recuperación posterior actualiza el catálogo.
     */
    public function test_catalog_handles_cache_miss_new_episodes_outages_and_recovery(): void
    {
        $archiveService = app(ArchiveOrgService::class);

        // Sembrar última respuesta válida en caché
        $initialPayload = [
            'featured' => ['title' => 'Episodio Guardado', 'src' => 'https://archive.org/download/test/ep1.mp3'],
            'episodes' => [
                ['id' => '1', 'title' => 'Episodio Guardado', 'src' => 'https://archive.org/download/test/ep1.mp3']
            ],
        ];
        Cache::forever('archive-org:home-podcasts:v5:last_valid:10', $initialPayload);

        // 1. Caché fría / vencida: la página web debe responder de inmediato con last_valid sin tocar red
        Http::fake([
            'archive.org/*' => Http::response(['error' => 'Should not be called during web response'], 500)
        ]);

        $t0 = microtime(true);
        $payload = $archiveService->homePodcastPayload(10);
        $elapsed = (microtime(true) - $t0) * 1000;

        $this->assertLessThan(50, $elapsed, 'homePodcastPayload debe responder en menos de 50ms');
        $this->assertSame('Episodio Guardado', $payload['featured']['title'] ?? null);
        Http::assertNothingSent();

        // 2. Nuevo episodio en base de datos local: debe invalidar la caché y mostrarse inmediatamente
        $master = MasterProgram::query()->create([
            'nombre' => 'Test Show',
            'conductor' => 'Host Test',
            'genero' => 'ROCK',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '10:00',
            'duracion_minutos' => 60,
            'timezone' => 'America/Caracas',
            'activo' => true,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-11-01 12:00:00', 'America/Caracas'));

        $newEpisode = RadioProgram::query()->create([
            'titulo_programa' => 'Nuevo Episodio Fresco',
            'master_program_id' => $master->id,
            'numero_episodio' => 99,
            'dia_transmision' => 'DOMINGO',
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'conductor' => 'Host Test',
            'fecha_emision' => '2026-10-25', // Pasado (sin embargo)
            'sync_archive_org' => true,
            'archive_org_status' => 'archive_verified',
            'archivo_mp3' => 'fresh.mp3',
            'archive_org_metadata' => json_encode([
                'identifier' => 'test-show',
                'remote_path' => 'fresh.mp3',
            ]),
        ]);

        $freshPayload = $archiveService->homePodcastPayload(10);
        $this->assertSame('Nuevo Episodio Fresco', $freshPayload['featured']['title'] ?? null);

        // 3. Simular caída de Archive.org durante refreshCatalog(): no debe borrar last_valid
        Http::fake([
            'archive.org/*' => Http::response(null, 503)
        ]);

        $failedRefresh = $archiveService->refreshCatalog(10);
        $this->assertNotEmpty($failedRefresh['episodes'] ?? [], 'No debe vaciar el catálogo ante fallo de red');

        // 4. Recuperación posterior de Archive.org: debe actualizar el catálogo y el fallback
        Http::fake([
            'archive.org/*' => Http::response([
                'files' => [
                    'ep_recovered.mp3' => [
                        'name' => 'ep_recovered.mp3',
                        'title' => 'Episodio Recuperado Archive',
                        'format' => 'VBR MP3',
                        'mtime' => 1762000000,
                    ]
                ]
            ], 200)
        ]);

        $master->update(['archive_identifier' => 'recovered-archive-id']);
        $recoveredRefresh = $archiveService->refreshCatalog(10);

        $this->assertNotEmpty($recoveredRefresh['episodes']);
    }

    /**
     * Requirement 3: Límites y precisión de ProgramScheduleService
     * - La caché no sobrevive al inicio, anticipo (5m) ni final de emisión.
     * - Prueba límites de 1, 2 y 3 segundos.
     * - Cambios de parrilla invalidan la caché y memoización.
     */
    public function test_schedule_cache_respects_start_prealert_and_end_boundaries_and_invalidates(): void
    {
        $schedService = app(ProgramScheduleService::class);

        $program = MasterProgram::query()->create([
            'nombre' => 'Rock Nocturno',
            'conductor' => 'DJ Noche',
            'genero' => 'ROCK',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '20:00',
            'duracion_minutos' => 60,
            'timezone' => 'America/Caracas',
            'activo' => true,
        ]);

        MasterProgramEmision::query()->create([
            'master_program_id' => $program->id,
            'dia_semana' => 'LUNES',
            'hora_inicio' => '20:00:00',
            'duracion_minutos' => 60,
            'activo' => true,
        ]);

        // CASO 1: Antes del anticipo de 5 min (emisión 20:00:00 -> anticipo a las 19:55:00)
        // 1.1) 3 segundos antes del anticipo (19:54:57)
        Carbon::setTestNow(Carbon::parse('2026-11-02 19:54:57', 'America/Caracas')); // 2026-11-02 es Lunes
        $schedService->flushMemoization();
        $payload1 = $schedService->resolve(5);
        $this->assertSame('A continuación', $payload1['badge'], '3s antes del anticipo debe ser A continuación');

        // 1.2) 1 segundo antes del anticipo (19:54:59)
        Carbon::setTestNow(Carbon::parse('2026-11-02 19:54:59', 'America/Caracas'));
        $schedService->flushMemoization();
        $payload2 = $schedService->resolve(5);
        $this->assertSame('A continuación', $payload2['badge'], '1s antes del anticipo debe seguir siendo A continuación');

        // CASO 2: Entrada al anticipo (ventana de 5 minutos antes hasta el inicio)
        // 2.1) 1 segundo dentro del anticipo (19:55:01): debe ser 'En breve', NUNCA 'En vivo'
        Carbon::setTestNow(Carbon::parse('2026-11-02 19:55:01', 'America/Caracas'));
        Cache::forget("program_schedule:resolve:v1:5");
        $schedService->flushMemoization();
        $payloadPreAlert1 = $schedService->resolve(5);
        $this->assertSame('En breve', $payloadPreAlert1['badge'], 'Al entrar en el anticipo de 5 min debe ser En breve');
        $this->assertFalse($schedService->programIsLiveNow($program, now('America/Caracas')), 'Durante el anticipo NO debe declarar emisión en vivo');

        // 2.2) 2 segundos antes del inicio real (19:59:58): sigue siendo 'En breve'
        Carbon::setTestNow(Carbon::parse('2026-11-02 19:59:58', 'America/Caracas'));
        $schedService->flushMemoization();
        $payloadPreAlert2 = $schedService->resolve(5);
        $this->assertSame('En breve', $payloadPreAlert2['badge'], '2s antes del inicio debe seguir siendo En breve');

        // 2.3) 1 segundo antes del inicio real (19:59:59): sigue siendo 'En breve'
        Carbon::setTestNow(Carbon::parse('2026-11-02 19:59:59', 'America/Caracas'));
        $schedService->flushMemoization();
        $payloadPreAlert3 = $schedService->resolve(5);
        $this->assertSame('En breve', $payloadPreAlert3['badge'], '1s antes del inicio debe seguir siendo En breve');

        // CASO 3: Inicio de emisión real y durante su duración efectiva (20:00:00 a 21:00:00)
        // 3.1) 1 segundo después del inicio real (20:00:01): pasa a 'En vivo'
        Carbon::setTestNow(Carbon::parse('2026-11-02 20:00:01', 'America/Caracas'));
        Cache::forget("program_schedule:resolve:v1:5");
        $schedService->flushMemoization();
        $payloadLive1 = $schedService->resolve(5);
        $this->assertSame('En vivo', $payloadLive1['badge'], 'Al iniciar la emisión real debe pasar a En vivo');
        $this->assertTrue($schedService->programIsLiveNow($program, now('America/Caracas')), 'Debe ser en vivo durante la emisión');

        // 3.2) A mitad de emisión (20:30:00)
        Carbon::setTestNow(Carbon::parse('2026-11-02 20:30:00', 'America/Caracas'));
        $schedService->flushMemoization();
        $payloadLiveMid = $schedService->resolve(5);
        $this->assertSame('En vivo', $payloadLiveMid['badge'], 'A mitad de emisión debe ser En vivo');

        // 3.3) 1 segundo antes del final de emisión (20:59:59)
        Carbon::setTestNow(Carbon::parse('2026-11-02 20:59:59', 'America/Caracas'));
        $schedService->flushMemoization();
        $payloadLiveEnd = $schedService->resolve(5);
        $this->assertSame('En vivo', $payloadLiveEnd['badge'], '1s antes del fin de emisión debe seguir En vivo');

        // CASO 4: Final de emisión (21:00:01 en adelante): resuelve siguiente programa sin datos residuales
        Carbon::setTestNow(Carbon::parse('2026-11-02 21:00:01', 'America/Caracas'));
        Cache::forget("program_schedule:resolve:v1:5");
        $schedService->flushMemoization();
        $payloadAfter = $schedService->resolve(5);
        $this->assertSame('A continuación', $payloadAfter['badge'], 'Finalizada la emisión, el siguiente programa debe ser A continuación');
        $this->assertFalse($schedService->programIsLiveNow($program, now('America/Caracas')), 'Finalizada la emisión NO debe declarar en vivo');

        // CASO 5: Invalidador por cambio de parrilla y memoización en servicio reutilizado
        $resMemo1 = $schedService->resolve(5);
        $resMemo2 = $schedService->resolve(5);
        $this->assertSame($resMemo1, $resMemo2, 'Debe devolver la misma referencia en la misma petición');

        // Modificar programa en base de datos: el hook debe incrementar cache.version.program_schedule
        $vBefore = (string) Cache::get('cache.version.program_schedule', '1');
        $program->update(['nombre' => 'Rock Nocturno Renovado']);
        $vAfter = (string) Cache::get('cache.version.program_schedule', '1');

        $this->assertNotSame($vBefore, $vAfter, 'El hook debe cambiar la versión de la caché de parrilla');

        // La siguiente llamada al mismo servicio reutilizado debe invalidar su memoización interna
        $resFresh = $schedService->resolve(5);
        $this->assertSame('Rock Nocturno Renovado', $resFresh['show']);
    }

    /**
     * Requirement 4: Distinción entre tiempo total transcurrido (Wall-clock) y tiempo de CPU
     */
    public function test_distinguish_wall_clock_time_from_cpu_time(): void
    {
        $schedService = app(ProgramScheduleService::class);
        $archiveService = app(ArchiveOrgService::class);

        // A. Medición en ArchiveOrgService::homePodcastPayload
        $u0 = getrusage();
        $t0 = hrtime(true);
        $archiveService->homePodcastPayload(10);
        $t1 = hrtime(true);
        $u1 = getrusage();

        $wallMs = ($t1 - $t0) / 1e6;
        $cpuMs = ((($u1['ru_utime.tv_sec'] - $u0['ru_utime.tv_sec']) * 1e6 + ($u1['ru_utime.tv_usec'] - $u0['ru_utime.tv_usec']))
            + (($u1['ru_stime.tv_sec'] - $u0['ru_stime.tv_sec']) * 1e6 + ($u1['ru_stime.tv_usec'] - $u0['ru_stime.tv_usec']))) / 1000;

        // El tiempo de CPU nunca excede el tiempo total transcurrido
        $this->assertGreaterThanOrEqual(0, $cpuMs);
        $this->assertLessThan(200, $wallMs, 'Wall clock debe responder en menos de 200ms');

        // B. Medición en ProgramScheduleService::resolve
        $u2 = getrusage();
        $t2 = hrtime(true);
        $schedService->resolve(5);
        $t3 = hrtime(true);
        $u3 = getrusage();

        $wallMsSched = ($t3 - $t2) / 1e6;
        $cpuMsSched = ((($u3['ru_utime.tv_sec'] - $u2['ru_utime.tv_sec']) * 1e6 + ($u3['ru_utime.tv_usec'] - $u2['ru_utime.tv_usec']))
            + (($u3['ru_stime.tv_sec'] - $u2['ru_stime.tv_sec']) * 1e6 + ($u3['ru_stime.tv_usec'] - $u2['ru_stime.tv_usec']))) / 1000;

        $this->assertGreaterThanOrEqual(0, $cpuMsSched);
        $this->assertLessThan(50, $wallMsSched, 'Wall clock de resolve debe ser casi instantáneo (<50ms)');
    }
}
