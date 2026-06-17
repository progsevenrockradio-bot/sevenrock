<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MasterProgram;
use App\Models\RadioProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ReconcilePodcastPipelineCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciler_ignores_legacy_completed_and_draft_programs(): void
    {
        $master = MasterProgram::query()->create([
            'nombre' => 'Test Show',
            'conductor' => 'DJ Test',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '20:00',
            'timezone' => 'America/Caracas',
            'duracion_minutos' => 120,
            'genero' => 'ROCK',
            'activo' => true,
        ]);

        // 1. Legacy program (processing_started_at is null, updated_at is old)
        $legacy = RadioProgram::query()->create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Legacy Ep',
            'numero_episodio' => 1,
            'fecha_emision' => '2026-05-01',
            'archivo_mp3' => 'programas_procesados/legacy.mp3',
            'conductor' => $master->conductor,
            'processing_started_at' => null,
            'processing_finished_at' => null,
            'delivery_status' => 'delivery_verified',
        ]);
        RadioProgram::query()->where('id', $legacy->id)->update(['updated_at' => now()->subDays(10)]);

        // 2. Draft program (delivery_status is 'skipped', processing_started_at is null)
        $draft = RadioProgram::query()->create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Draft Ep',
            'numero_episodio' => 2,
            'fecha_emision' => '2026-06-15',
            'archivo_mp3' => 'podcast-inbox/draft.mp3',
            'conductor' => $master->conductor,
            'processing_started_at' => null,
            'processing_finished_at' => null,
            'delivery_status' => 'skipped',
        ]);
        RadioProgram::query()->where('id', $draft->id)->update(['updated_at' => now()->subDays(1)]);

        // 3. Completed program (delivery_status is 'delivery_verified', processing_started_at/finished_at is not null)
        $completed = RadioProgram::query()->create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Completed Ep',
            'numero_episodio' => 3,
            'fecha_emision' => '2026-06-16',
            'archivo_mp3' => 'programas_procesados/completed.mp3',
            'conductor' => $master->conductor,
            'processing_started_at' => now()->subDays(1),
            'processing_finished_at' => now()->subDays(1),
            'delivery_status' => 'delivery_verified',
        ]);
        RadioProgram::query()->where('id', $completed->id)->update(['updated_at' => now()->subDays(1)]);

        // 4. Stuck program (processing_started_at is set, processing_finished_at is null, updated_at is older than stale threshold)
        $stuck = RadioProgram::query()->create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Stuck Ep',
            'numero_episodio' => 4,
            'fecha_emision' => '2026-06-17',
            'archivo_mp3' => 'podcast-inbox/stuck.mp3',
            'conductor' => $master->conductor,
            'processing_started_at' => now()->subMinutes(150),
            'processing_finished_at' => null,
            'delivery_status' => 'delivery_pending',
        ]);
        RadioProgram::query()->where('id', $stuck->id)->update(['updated_at' => now()->subMinutes(150)]);

        $exitCode = Artisan::call('podcast:reconcile-pipeline', [
            '--limit' => 50,
            '--stale-minutes' => 120,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);

        // Stuck Ep should be reported as still in local processing since processing_finished_at is null
        $this->assertStringContainsString('Episodio #' . $stuck->id . ' sigue en procesamiento local.', $output);

        // Legacy, Draft, and Completed should NOT be reported or warned
        $this->assertStringNotContainsString('Episodio #' . $legacy->id, $output);
        $this->assertStringNotContainsString('Episodio #' . $draft->id, $output);
        $this->assertStringNotContainsString('Episodio #' . $completed->id, $output);
    }
}
