<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\MasterProgram;
use App\Models\MasterProgramEmision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MasterProgramIndexDayGroupingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin_test_' . uniqid() . '@example.com',
            'is_admin' => true,
        ]);
    }

    private function createProgram(array $attributes = []): MasterProgram
    {
        return MasterProgram::create(array_merge([
            'nombre' => 'Programa Test ' . uniqid(),
            'conductor' => 'Conductor Test',
            'genero' => 'Rock',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '12:00:00',
            'timezone' => 'America/Caracas',
            'duracion_minutos' => 60,
            'activo' => true,
        ], $attributes));
    }

    public function test_program_with_friday_base_and_tuesday_rebroadcast_appears_in_both_tabs(): void
    {
        // Caso representativo del programa ID 26: Viernes base 17:05 y Martes retransmisión 13:05
        $program = $this->createProgram([
            'nombre' => 'UNO POR SEMANA TEST',
            'program_code' => 'UNOPORSEM',
            'conductor' => 'Lucho Almirón',
            'dia_transmision' => 'VIERNES',
            'hora_transmision' => '17:05:00',
            'timezone' => 'America/Caracas',
            'duracion_minutos' => 65,
            'activo' => true,
        ]);

        $program->emisiones()->create([
            'tipo' => 'normal',
            'dia_semana' => 'VIERNES',
            'hora_inicio' => '17:05:00',
            'duracion_minutos' => 65,
            'activo' => true,
        ]);

        $program->emisiones()->create([
            'tipo' => 'retransmision',
            'dia_semana' => 'MARTES',
            'hora_inicio' => '13:05:00',
            'duracion_minutos' => 65,
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.master-programs.index'));

        $response->assertOk();

        /** @var \Illuminate\Support\Collection $programsByDay */
        $programsByDay = $response->viewData('programsByDay');

        $tuesdayPrograms = $programsByDay->get('MARTES');
        $fridayPrograms = $programsByDay->get('VIERNES');
        $mondayPrograms = $programsByDay->get('LUNES');

        $this->assertTrue($tuesdayPrograms->contains('id', $program->id), 'El programa debe aparecer en la pestaña de MARTES');
        $this->assertTrue($fridayPrograms->contains('id', $program->id), 'El programa debe aparecer en la pestaña de VIERNES');
        $this->assertFalse($mondayPrograms->contains('id', $program->id), 'El programa NO debe aparecer en la pestaña de LUNES');

        $response->assertSee('UNO POR SEMANA TEST');
    }

    public function test_programs_are_sorted_by_day_pass_time(): void
    {
        // En martes: crear dos programas
        // Prog A: pase a las 18:00
        $progA = $this->createProgram([
            'nombre' => 'Programa Tarde',
            'program_code' => 'TARDE',
            'dia_transmision' => 'MARTES',
            'hora_transmision' => '18:00:00',
            'activo' => true,
        ]);
        $progA->emisiones()->create([
            'tipo' => 'normal',
            'dia_semana' => 'MARTES',
            'hora_inicio' => '18:00:00',
            'activo' => true,
        ]);

        // Prog B: base viernes pero retransmisión martes a las 13:05
        $progB = $this->createProgram([
            'nombre' => 'Programa Mediodía',
            'program_code' => 'MEDIODIA',
            'dia_transmision' => 'VIERNES',
            'hora_transmision' => '20:00:00',
            'activo' => true,
        ]);
        $progB->emisiones()->create([
            'tipo' => 'retransmision',
            'dia_semana' => 'MARTES',
            'hora_inicio' => '13:05:00',
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.master-programs.index'));
        $response->assertOk();

        /** @var \Illuminate\Support\Collection $programsByDay */
        $programsByDay = $response->viewData('programsByDay');
        $tuesdayList = $programsByDay->get('MARTES');

        $this->assertCount(2, $tuesdayList);
        // Debe estar primero el de las 13:05 (Prog B) y luego el de las 18:00 (Prog A)
        $this->assertSame($progB->id, $tuesdayList[0]->id, 'A las 13:05 debe ordenarse antes de las 18:00');
        $this->assertSame($progA->id, $tuesdayList[1]->id);
    }

    public function test_multiple_passes_on_same_day_yields_single_entry_and_single_count(): void
    {
        // Un programa con 2 pases el mismo día (ej. en vivo a las 10:00 y repetición a las 22:00)
        $program = $this->createProgram([
            'nombre' => 'Doble Pase',
            'program_code' => 'DOBLE',
            'dia_transmision' => 'SABADO',
            'hora_transmision' => '10:00:00',
            'activo' => true,
        ]);

        $program->emisiones()->create([
            'tipo' => 'en_vivo',
            'dia_semana' => 'SABADO',
            'hora_inicio' => '10:00:00',
            'activo' => true,
        ]);

        $program->emisiones()->create([
            'tipo' => 'retransmision',
            'dia_semana' => 'SABADO',
            'hora_inicio' => '22:00:00',
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.master-programs.index'));
        $response->assertOk();

        /** @var \Illuminate\Support\Collection $programsByDay */
        $programsByDay = $response->viewData('programsByDay');
        $saturdayList = $programsByDay->get('SABADO');

        $this->assertCount(1, $saturdayList, 'No debe duplicar filas para múltiples emisiones en el mismo día');
        $this->assertSame($program->id, $saturdayList[0]->id);
    }

    public function test_historical_program_without_emisiones_falls_back_to_base_schedule(): void
    {
        $historical = $this->createProgram([
            'nombre' => 'Histórico Sin Emisiones',
            'program_code' => 'HISTORICO',
            'dia_transmision' => 'JUEVES',
            'hora_transmision' => '15:00:00',
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.master-programs.index'));
        $response->assertOk();

        /** @var \Illuminate\Support\Collection $programsByDay */
        $programsByDay = $response->viewData('programsByDay');
        $thursdayList = $programsByDay->get('JUEVES');

        $this->assertTrue($thursdayList->contains('id', $historical->id), 'Debe aparecer en su día base como fallback');
    }

    public function test_explicitly_deactivated_emission_does_not_fall_back_to_base_schedule(): void
    {
        // Un programa que tiene base VIERNES, pero su emisión del VIERNES fue desactivada explícitamente
        $program = $this->createProgram([
            'nombre' => 'Emisión Desactivada',
            'program_code' => 'DESACT',
            'dia_transmision' => 'VIERNES',
            'hora_transmision' => '17:00:00',
            'activo' => true,
        ]);

        $program->emisiones()->create([
            'tipo' => 'normal',
            'dia_semana' => 'VIERNES',
            'hora_inicio' => '17:00:00',
            'activo' => false, // Inactivo explícito
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.master-programs.index'));
        $response->assertOk();

        /** @var \Illuminate\Support\Collection $programsByDay */
        $programsByDay = $response->viewData('programsByDay');
        $fridayList = $programsByDay->get('VIERNES');

        $this->assertFalse(
            $fridayList->contains('id', $program->id),
            'No debe mostrarse en VIERNES porque su emisión está explícitamente desactivada y NO debe resucitar vía horario base'
        );
    }

    public function test_search_filter_preserves_multi_day_grouping(): void
    {
        $program = $this->createProgram([
            'nombre' => 'Búsqueda Específica Metal',
            'program_code' => 'BUSQMET',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '10:00:00',
            'activo' => true,
        ]);

        $program->emisiones()->create([
            'tipo' => 'normal',
            'dia_semana' => 'LUNES',
            'hora_inicio' => '10:00:00',
            'activo' => true,
        ]);

        $program->emisiones()->create([
            'tipo' => 'retransmision',
            'dia_semana' => 'DOMINGO',
            'hora_inicio' => '21:00:00',
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.master-programs.index', ['search' => 'Específica Metal']));
        $response->assertOk();

        /** @var \Illuminate\Support\Collection $programsByDay */
        $programsByDay = $response->viewData('programsByDay');

        $this->assertTrue($programsByDay->get('LUNES')->contains('id', $program->id));
        $this->assertTrue($programsByDay->get('DOMINGO')->contains('id', $program->id));
        $this->assertCount(0, $programsByDay->get('MARTES'));
    }
}
