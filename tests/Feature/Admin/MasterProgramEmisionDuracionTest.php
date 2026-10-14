<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\MasterProgram;
use App\Models\MasterProgramEmision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MasterProgramEmisionDuracionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardar_ficha_con_duracion_real_vacia_mantiene_duracion_segundos_existente(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $master = MasterProgram::create([
            'nombre'           => 'Metal Maniacs',
            'conductor'        => 'Host Metal',
            'dia_transmision'  => 'LUNES',
            'hora_transmision' => '16:00:00',
            'duracion_minutos' => 120,
            'timezone'         => 'America/Caracas',
            'activo'           => true,
            'genero'           => 'Metal',
        ]);

        $emision = MasterProgramEmision::create([
            'master_program_id' => $master->id,
            'tipo'              => 'retransmision',
            'dia_semana'        => 'LUNES',
            'hora_inicio'       => '16:00:00',
            'duracion_minutos'  => 120,
            'duracion_segundos' => 7345, // 2h 2m 25s
            'activo'            => true,
        ]);

        // Guardamos la ficha del programa enviando la emisión pero con duracion_real_min y sec vacíos (y sin duracion_segundos)
        $payload = [
            'nombre'           => 'Metal Maniacs',
            'conductor'        => 'Host Metal',
            'dia_transmision'  => 'LUNES',
            'hora_transmision' => '16:05:00', // Le cambiamos la hora
            'timezone'         => 'America/Caracas',
            'duracion_minutos' => 120,
            'genero'           => 'Metal',
            'activo'           => 1,
            'emisiones'        => [
                [
                    'id'                => $emision->id,
                    'tipo'              => 'retransmision',
                    'dia_semana'        => 'LUNES',
                    'hora_inicio'       => '16:05:00',
                    'duracion_minutos'  => 120,
                    'duracion_real_min' => '',
                    'duracion_real_sec' => '',
                    'activo'            => 1,
                ],
            ],
        ];

        $response = $this->actingAs($admin)
            ->put(route('admin.master-programs.update', $master), $payload);

        $response->assertRedirect(route('admin.master-programs.edit', $master));

        $emision->refresh();

        // Debe conservar los 7345 segundos originales
        $this->assertSame(7345, $emision->duracion_segundos);
        $this->assertSame('16:05:00', $emision->hora_inicio);
    }

    public function test_borrar_duracion_real_explicitamente_pone_duracion_segundos_a_null(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $master = MasterProgram::create([
            'nombre'           => 'Metal Maniacs',
            'conductor'        => 'Host Metal',
            'dia_transmision'  => 'LUNES',
            'hora_transmision' => '16:00:00',
            'duracion_minutos' => 120,
            'timezone'         => 'America/Caracas',
            'activo'           => true,
            'genero'           => 'Metal',
        ]);

        $emision = MasterProgramEmision::create([
            'master_program_id' => $master->id,
            'tipo'              => 'retransmision',
            'dia_semana'        => 'LUNES',
            'hora_inicio'       => '16:00:00',
            'duracion_minutos'  => 120,
            'duracion_segundos' => 7345,
            'activo'            => true,
        ]);

        $payload = [
            'nombre'           => 'Metal Maniacs',
            'conductor'        => 'Host Metal',
            'dia_transmision'  => 'LUNES',
            'hora_transmision' => '16:00:00',
            'timezone'         => 'America/Caracas',
            'duracion_minutos' => 120,
            'genero'           => 'Metal',
            'activo'           => 1,
            'emisiones'        => [
                [
                    'id'                   => $emision->id,
                    'tipo'                 => 'retransmision',
                    'dia_semana'           => 'LUNES',
                    'hora_inicio'          => '16:00:00',
                    'duracion_minutos'     => 120,
                    'duracion_real_min'    => '',
                    'duracion_real_sec'    => '',
                    'borrar_duracion_real' => '1',
                    'activo'               => 1,
                ],
            ],
        ];

        $response = $this->actingAs($admin)
            ->put(route('admin.master-programs.update', $master), $payload);

        $response->assertRedirect(route('admin.master-programs.edit', $master));

        $emision->refresh();
        $this->assertNull($emision->duracion_segundos);
    }

    public function test_comando_estampar_duraciones_recupera_duracion(): void
    {
        $master = MasterProgram::create([
            'nombre'           => 'Metal Maniacs',
            'conductor'        => 'Host Metal',
            'dia_transmision'  => 'LUNES',
            'hora_transmision' => '16:00:00',
            'duracion_minutos' => 120,
            'timezone'         => 'America/Caracas',
            'activo'           => true,
            'genero'           => 'Metal',
        ]);

        $emision = MasterProgramEmision::create([
            'master_program_id' => $master->id,
            'tipo'              => 'retransmision',
            'dia_semana'        => 'LUNES',
            'hora_inicio'       => '16:00:00',
            'duracion_minutos'  => 120,
            'duracion_segundos' => null, // Duración perdida
            'activo'            => true,
        ]);

        \App\Models\RadioProgram::create([
            'master_program_id' => $master->id,
            'numero_episodio'   => 12,
            'titulo_programa'   => 'Metal Maniacs #12',
            'conductor'         => 'Host Metal',
            'dia_transmision'   => 'LUNES',
            'hora_inicio'       => '16:00:00',
            'hora_fin'          => '18:00:00',
            'fecha_emision'     => '2026-10-12', // Lunes
            'duration_seconds'  => 5420,
        ]);

        $this->artisan('emisiones:estampar-duraciones')
            ->assertSuccessful()
            ->expectsOutputToContain('Total de emisiones actualizadas: 1');

        $emision->refresh();
        $this->assertSame(5420, $emision->duracion_segundos);
    }

    public function test_comando_auditar_horas_detecta_diferencia_de_12_horas(): void
    {
        $master = MasterProgram::create([
            'nombre'           => 'Metal Maniacs',
            'conductor'        => 'Host Metal',
            'dia_transmision'  => 'LUNES',
            'hora_transmision' => '16:05:00',
            'duracion_minutos' => 120,
            'timezone'         => 'America/Caracas',
            'activo'           => true,
            'genero'           => 'Metal',
        ]);

        // Emisión guardada a las 04:05 (12 h de desfase)
        MasterProgramEmision::create([
            'master_program_id' => $master->id,
            'tipo'              => 'retransmision',
            'dia_semana'        => 'LUNES',
            'hora_inicio'       => '04:05:00',
            'duracion_minutos'  => 120,
            'duracion_segundos' => 7200,
            'activo'            => true,
        ]);

        $exitCode = \Illuminate\Support\Facades\Artisan::call('emisiones:auditar-horas');
        $this->assertSame(0, $exitCode);

        $output = \Illuminate\Support\Facades\Artisan::output();
        $this->assertStringContainsString('Metal Maniacs', $output);
        $this->assertStringContainsString('Diferencia de 12h', $output);
    }
}

