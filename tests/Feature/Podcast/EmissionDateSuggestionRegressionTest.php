<?php

declare(strict_types=1);

namespace Tests\Feature\Podcast;

use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class EmissionDateSuggestionRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function createPolucion(): MasterProgram
    {
        $master = MasterProgram::query()->create([
            'nombre' => 'POLUCION',
            'conductor' => 'Host Polucion',
            'genero' => 'Rock',
            'pais' => 'Venezuela',
            'dia_transmision' => 'JUEVES',
            'hora_transmision' => '18:00:00',
            'timezone' => 'America/Caracas',
            'duracion_minutos' => 120,
            'activo' => true,
        ]);

        $master->emisiones()->create([
            'tipo' => 'normal',
            'dia_semana' => 'JUEVES',
            'hora_inicio' => '18:00:00',
            'duracion_minutos' => 120,
            'activo' => true,
        ]);

        return $master->fresh('emisiones');
    }

    public function test_monday_upload_for_thursday_program_proposes_thursday_and_not_upload_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-23 19:57:34', 'America/Caracas'));

        try {
            $polucion = $this->createPolucion();
            $next = $polucion->calculateNextEmission();

            $this->assertSame('ok', $next['status']);
            $this->assertSame('2026-11-26', $next['date']);
            $this->assertFalse($next['is_today']);
            $this->assertFalse($next['is_live_now']);

            // Verificar que el formulario renderiza 2026-11-26 para Polución y no inicializa con 2026-11-23
            $admin = User::factory()->create(['is_admin' => true]);
            $this->actingAs($admin)
                ->get(route('admin.podcast-uploads.index'))
                ->assertOk()
                ->assertSee('data-next-emission-date="2026-11-26"', false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_same_day_before_during_and_after_broadcast(): void
    {
        $polucion = $this->createPolucion();

        // 1. Mismo jueves ANTES de las 18:00 (14:00 Caracas) -> propone hoy 26/11
        $before = $polucion->calculateNextEmission(Carbon::parse('2026-11-26 14:00:00', 'America/Caracas'));
        $this->assertSame('ok', $before['status']);
        $this->assertSame('2026-11-26', $before['date']);
        $this->assertTrue($before['is_today']);
        $this->assertFalse($before['is_live_now']);

        // 2. Durante la emisión en curso (18:30 Caracas) -> respeta protección en vivo y propone siguiente jueves 03/12
        $during = $polucion->calculateNextEmission(Carbon::parse('2026-11-26 18:30:00', 'America/Caracas'));
        $this->assertSame('ok', $during['status']);
        $this->assertSame('2026-12-03', $during['date']);
        $this->assertFalse($during['is_today']);
        $this->assertTrue($during['is_live_now']);

        // 3. Después de terminar la emisión (20:05 Caracas) -> propone siguiente jueves 03/12
        $after = $polucion->calculateNextEmission(Carbon::parse('2026-11-26 20:05:00', 'America/Caracas'));
        $this->assertSame('ok', $after['status']);
        $this->assertSame('2026-12-03', $after['date']);
        $this->assertFalse($after['is_today']);
        $this->assertFalse($after['is_live_now']);
    }

    public function test_week_boundary_and_program_timezone_handling(): void
    {
        // Programa de LUNES a las 10:00 en Europe/Madrid
        $madridProg = MasterProgram::query()->create([
            'nombre' => 'Rock Madrid',
            'conductor' => 'Host Madrid',
            'genero' => 'Rock',
            'pais' => 'España',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '10:00:00',
            'timezone' => 'Europe/Madrid',
            'duracion_minutos' => 60,
            'activo' => true,
        ]);

        // Domingo 29/11/2026 21:00 America/Caracas == Lunes 30/11/2026 02:00 Europe/Madrid
        // En la zona horaria del programa (Madrid) YA es lunes antes de las 10:00 -> propone 2026-11-30 (hoy en Madrid)
        $ref = Carbon::parse('2026-11-29 21:00:00', 'America/Caracas');
        $next = $madridProg->calculateNextEmission($ref);

        $this->assertSame('ok', $next['status']);
        $this->assertSame('2026-11-30', $next['date']);
        $this->assertTrue($next['is_today']);
        $this->assertSame('Europe/Madrid', $next['timezone']);

        // Programa sin hora ni emisión configurada -> devuelve incomplete_config (NO sustituye por hoy)
        $incomplete = MasterProgram::query()->create([
            'nombre' => 'Sin Horario',
            'conductor' => 'Host',
            'genero' => 'Rock',
            'pais' => 'España',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => null,
            'timezone' => 'Europe/Madrid',
            'duracion_minutos' => 60,
            'activo' => true,
        ]);

        $incRes = $incomplete->calculateNextEmission($ref);
        $this->assertSame('incomplete_config', $incRes['status']);
        $this->assertNull($incRes['date']);
        $this->assertNotEmpty($incRes['missing_fields']);
    }

    public function test_manual_date_is_preserved_on_save_and_missing_date_uses_next_emission(): void
    {
        Storage::fake('public');
        Carbon::setTestNow(Carbon::parse('2026-11-23 19:57:34', 'America/Caracas'));

        try {
            $admin = User::factory()->create(['is_admin' => true]);
            $polucion = $this->createPolucion();

            // 1. Fecha elegida manualmente por el usuario (2026-12-10) se conserva al guardar
            $file1 = UploadedFile::fake()->create('ep55.mp3', 100, 'audio/mpeg');
            $this->actingAs($admin)
                ->post(route('admin.podcast-uploads.store'), [
                    'master_program_id' => $polucion->id,
                    'numero_episodio' => 55,
                    'live_title' => 'Polucion Manual Date',
                    'fecha_emision' => '2026-12-10',
                    'fecha_emision_manual' => '1',
                    'pipeline_action' => 'save',
                    'archivo_mp3' => $file1,
                ])
                ->assertRedirect();

            $ep55 = RadioProgram::query()->where('master_program_id', $polucion->id)->where('numero_episodio', 55)->firstOrFail();
            $this->assertSame('2026-12-10', $ep55->fecha_emision->toDateString());
            // La fecha de subida (created_at) sigue siendo el lunes 23/11 separada de fecha_emision
            $this->assertSame('2026-11-23', $ep55->created_at->copy()->setTimezone('America/Caracas')->toDateString());

            // 2. Si fecha_emision se omite en el POST, el servidor calcula la próxima emisión (2026-11-26), NO hoy (2026-11-23)
            $file2 = UploadedFile::fake()->create('ep56.mp3', 100, 'audio/mpeg');
            $this->actingAs($admin)
                ->post(route('admin.podcast-uploads.store'), [
                    'master_program_id' => $polucion->id,
                    'numero_episodio' => 56,
                    'live_title' => 'Polucion Auto Date',
                    'fecha_emision' => '',
                    'pipeline_action' => 'save',
                    'archivo_mp3' => $file2,
                ])
                ->assertRedirect();

            $ep56 = RadioProgram::query()->where('master_program_id', $polucion->id)->where('numero_episodio', 56)->firstOrFail();
            $this->assertSame('2026-11-26', $ep56->fecha_emision->toDateString());
        } finally {
            Carbon::setTestNow();
        }
    }
}

