<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\MasterProgram;
use App\Models\MasterProgramEmision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MasterProgramEmisionesPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'is_admin' => true,
        ]);
    }

    public function test_program_with_base_schedule_and_added_rebroadcast_saves_both_passes(): void
    {
        // Caso exacto de "UNO POR SEMANA": base Viernes 17:05, 65 min
        $program = MasterProgram::create([
            'nombre'           => 'UNO POR SEMANA .... LA LEYENDA CONTINUA',
            'conductor'        => 'LUCHO ALMIRON',
            'dia_transmision'  => 'VIERNES',
            'hora_transmision' => '17:05:00',
            'timezone'         => 'America/Caracas',
            'duracion_minutos' => 65,
            'genero'           => 'Rock',
            'activo'           => true,
        ]);

        // Simula la llamada syncEmisionNormal previa (el viernes ya existe como emisión normal ID 8)
        $program->syncEmisionNormal();
        $normal = $program->emisiones()->where('tipo', 'normal')->first();
        $this->assertNotNull($normal);
        $this->assertSame('VIERNES', $normal->dia_semana);
        $this->assertSame('17:05:00', $normal->hora_inicio);

        // El usuario edita el programa y añade el Martes 13:05 (retransmisión) manteniendo el Viernes 17:05 (normal)
        $payload = [
            'nombre'           => 'UNO POR SEMANA .... LA LEYENDA CONTINUA',
            'conductor'        => 'LUCHO ALMIRON',
            'dia_transmision'  => 'VIERNES',
            'hora_transmision' => '17:05:00',
            'timezone'         => 'America/Caracas',
            'duracion_minutos' => 65,
            'genero'           => 'Rock',
            'activo'           => 1,
            'emisiones'        => [
                [
                    'id'               => $normal->id,
                    'tipo'             => 'normal',
                    'etiqueta'         => 'Estreno Viernes',
                    'dia_semana'       => 'VIERNES',
                    'hora_inicio'      => '17:05',
                    'duracion_minutos' => 65,
                    'activo'           => 1,
                ],
                [
                    'id'               => 0,
                    'tipo'             => 'retransmision',
                    'etiqueta'         => 'Retransmisión Martes',
                    'dia_semana'       => 'MARTES',
                    'hora_inicio'      => '13:05',
                    'duracion_minutos' => 65,
                    'activo'           => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->put(route('admin.master-programs.update', $program), $payload);

        $response->assertRedirect(route('admin.master-programs.edit', $program));
        $response->assertSessionHas('status', 'Programa maestro actualizado.');

        $program->refresh();
        $this->assertCount(2, $program->emisiones);

        $viernesEmision = $program->emisiones->firstWhere('dia_semana', 'VIERNES');
        $this->assertNotNull($viernesEmision);
        $this->assertSame('normal', $viernesEmision->tipo);
        $this->assertSame('Estreno Viernes', $viernesEmision->etiqueta);
        $this->assertSame('17:05:00', $viernesEmision->hora_inicio);
        $this->assertTrue((bool) $viernesEmision->activo);

        $martesEmision = $program->emisiones->firstWhere('dia_semana', 'MARTES');
        $this->assertNotNull($martesEmision);
        $this->assertSame('retransmision', $martesEmision->tipo);
        $this->assertSame('Retransmisión Martes', $martesEmision->etiqueta);
        $this->assertSame('13:05:00', $martesEmision->hora_inicio);
        $this->assertTrue((bool) $martesEmision->activo);

        // Verificar que la vista de edición renderiza ambas emisiones correctamente
        $editResponse = $this->actingAs($this->admin)->get(route('admin.master-programs.edit', $program));
        $editResponse->assertOk();
        $editResponse->assertSee('Retransmisión Martes');
        $editResponse->assertSee('Estreno Viernes');
        $editResponse->assertSee('13:05');
        $editResponse->assertSee('17:05');
    }

    public function test_inactive_emissions_are_persisted_and_displayed_in_edit_view(): void
    {
        $program = MasterProgram::create([
            'nombre'           => 'Programa con Inactivos',
            'conductor'        => 'Locutor Test',
            'dia_transmision'  => 'JUEVES',
            'hora_transmision' => '21:00:00',
            'timezone'         => 'America/Caracas',
            'duracion_minutos' => 60,
            'genero'           => 'Rock',
            'activo'           => true,
        ]);

        $payload = [
            'nombre'           => 'Programa con Inactivos',
            'conductor'        => 'Locutor Test',
            'dia_transmision'  => 'JUEVES',
            'hora_transmision' => '21:00:00',
            'timezone'         => 'America/Caracas',
            'duracion_minutos' => 60,
            'genero'           => 'Rock',
            'activo'           => 1,
            'emisiones'        => [
                [
                    'id'               => 0,
                    'tipo'             => 'normal',
                    'etiqueta'         => 'Pase Principal Inactivo',
                    'dia_semana'       => 'JUEVES',
                    'hora_inicio'      => '21:00',
                    'duracion_minutos' => 60,
                    'activo'           => 0, // Inactivo intencional
                ],
                [
                    'id'               => 0,
                    'tipo'             => 'retransmision',
                    'etiqueta'         => 'Retransmisión Activa',
                    'dia_semana'       => 'DOMINGO',
                    'hora_inicio'      => '10:00',
                    'duracion_minutos' => 60,
                    'activo'           => 1,
                ],
            ],
        ];

        $this->actingAs($this->admin)
            ->put(route('admin.master-programs.update', $program), $payload)
            ->assertRedirect();

        $program->refresh();
        $this->assertCount(2, $program->emisiones);

        $inactiva = $program->emisiones->firstWhere('dia_semana', 'JUEVES');
        $this->assertNotNull($inactiva);
        $this->assertFalse((bool) $inactiva->activo);

        // Comprobar que en la vista de edición se cargan las inactivas para poder gestionarlas
        $editResponse = $this->actingAs($this->admin)->get(route('admin.master-programs.edit', $program));
        $editResponse->assertOk();
        $editResponse->assertSee('Pase Principal Inactivo');
        $editResponse->assertSee('Retransmisión Activa');
    }

    public function test_form_prepopulates_base_emission_when_master_program_has_no_emisiones_yet(): void
    {
        // Programa antiguo sin filas en master_program_emisiones
        $program = MasterProgram::create([
            'nombre'           => 'Programa Antiguo Sin Emisiones',
            'conductor'        => 'Locutor Antiguo',
            'dia_transmision'  => 'VIERNES',
            'hora_transmision' => '17:05:00',
            'timezone'         => 'America/Caracas',
            'duracion_minutos' => 65,
            'genero'           => 'Rock',
            'activo'           => true,
        ]);

        $this->assertSame(0, $program->emisiones()->count());

        $editResponse = $this->actingAs($this->admin)->get(route('admin.master-programs.edit', $program));
        $editResponse->assertOk();

        // En vez de estar vacío, el array de Alpine viene precargado con el pase base
        $editResponse->assertSee('&quot;dia_semana&quot;:&quot;VIERNES&quot;', false);
        $editResponse->assertSee('&quot;hora_inicio&quot;:&quot;17:05&quot;', false);
        $editResponse->assertSee('&quot;tipo&quot;:&quot;normal&quot;', false);
    }

    public function test_validation_error_preserves_user_added_emissions_via_old_input(): void
    {
        $program = MasterProgram::create([
            'nombre'           => 'Programa con Error',
            'conductor'        => 'Locutor',
            'dia_transmision'  => 'LUNES',
            'hora_transmision' => '10:00:00',
            'timezone'         => 'America/Caracas',
            'duracion_minutos' => 60,
            'genero'           => 'Rock',
            'activo'           => true,
        ]);

        // Enviamos el formulario con un campo requerido vacío (nombre = '') y una emisión nueva
        $payload = [
            'nombre'           => '', // Error de validación requerido
            'conductor'        => 'Locutor',
            'dia_transmision'  => 'LUNES',
            'hora_transmision' => '10:00:00',
            'timezone'         => 'America/Caracas',
            'duracion_minutos' => 60,
            'genero'           => 'Rock',
            'emisiones'        => [
                [
                    'id'               => 0,
                    'tipo'             => 'retransmision',
                    'etiqueta'         => 'Retransmisión no perdida',
                    'dia_semana'       => 'MARTES',
                    'hora_inicio'      => '14:00',
                    'duracion_minutos' => 60,
                    'activo'           => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->from(route('admin.master-programs.edit', $program))
            ->put(route('admin.master-programs.update', $program), $payload);

        $response->assertRedirect(route('admin.master-programs.edit', $program));
        $response->assertSessionHasErrors(['nombre']);

        // Siguiendo la redirección con los datos en sesión flash (old input)
        $followResponse = $this->actingAs($this->admin)
            ->get(route('admin.master-programs.edit', $program));

        $followResponse->assertOk();
        $followResponse->assertSee('Retransmisión no perdida');
        $followResponse->assertSee('MARTES');
        $followResponse->assertSee('14:00');
    }
}
