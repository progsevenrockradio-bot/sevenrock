<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MasterProgram;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMasterProgramCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_delete_master_programs(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $payload = [
            'nombre' => 'Programa de prueba',
            'conductor' => 'John Doe',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '20:00',
            'timezone' => 'America/Caracas',
            'duracion_minutos' => 90,
            'genero' => 'Rock',
            'caratula_url' => 'https://example.com/cover.jpg',
            'descripcion' => 'Descripción de prueba',
            'live_title' => 'En vivo',
            'live_description' => 'Descripción en curso',
            'live_image_url' => 'https://example.com/live.jpg',
            'comentario_predeterminado' => 'Comentario',
            'ruta_ftp' => 'programas/prueba',
            'archive_identifier' => 'programa-prueba',
            'vistas_archive' => 10,
            'escuchas_locales' => 20,
            'vistas_totales' => 30,
            'activo' => 1,
        ];

        $this->actingAs($admin)
            ->post(route('admin.master-programs.store'), $payload)
            ->assertRedirect();

        $program = MasterProgram::query()->where('nombre', 'Programa de prueba')->first();
        $this->assertNotNull($program);
        $this->assertSame('programas/prueba', $program->ruta_ftp);
        $this->assertSame('programa-prueba', $program->archive_identifier);

        $this->actingAs($admin)
            ->get(route('admin.master-programs.index'))
            ->assertOk()
            ->assertSee('Programa de prueba')
            ->assertSee('https://example.com/live.jpg')
            ->assertDontSee('programas/prueba');

        $this->actingAs($admin)
            ->delete(route('admin.master-programs.destroy', $program))
            ->assertRedirect(route('admin.master-programs.index'));

        $this->assertDatabaseMissing('master_programs', ['id' => $program->id]);
    }

    public function test_master_program_index_orders_by_monday_first(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        MasterProgram::query()->create([
            'nombre' => 'Viernes Show',
            'conductor' => 'DJ Friday',
            'dia_transmision' => 'VIERNES',
            'hora_transmision' => '20:00:00',
            'timezone' => 'America/Caracas',
            'duracion_minutos' => 60,
            'genero' => 'Rock',
            'caratula_url' => null,
            'activo' => true,
        ]);

        MasterProgram::query()->create([
            'nombre' => 'Lunes Show',
            'conductor' => 'DJ Monday',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '08:00:00',
            'timezone' => 'America/Caracas',
            'duracion_minutos' => 60,
            'genero' => 'Rock',
            'caratula_url' => null,
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.master-programs.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['Lunes Show', 'Viernes Show']);
    }

    public function test_master_program_index_opens_current_day_tab_by_default(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 19, 12, 0, 0, 'America/Caracas'));

        try {
            $admin = User::factory()->create([
                'is_admin' => true,
            ]);

            MasterProgram::query()->create([
                'nombre' => 'Martes Show',
                'conductor' => 'DJ Tuesday',
                'dia_transmision' => 'MARTES',
                'hora_transmision' => '08:00:00',
                'timezone' => 'America/Caracas',
                'duracion_minutos' => 60,
                'genero' => 'Rock',
                'caratula_url' => null,
                'activo' => true,
            ]);

            MasterProgram::query()->create([
                'nombre' => 'Lunes Show',
                'conductor' => 'DJ Monday',
                'dia_transmision' => 'LUNES',
                'hora_transmision' => '08:00:00',
                'timezone' => 'America/Caracas',
                'duracion_minutos' => 60,
                'genero' => 'Rock',
                'caratula_url' => null,
                'activo' => true,
            ]);

            $response = $this->actingAs($admin)->get(route('admin.master-programs.index'));

            $response->assertOk();
            $response->assertSee('x-data=\'{"activeDay": "MARTES"}\'', false);
            $response->assertSee('data-day-tab', false);
        } finally {
            Carbon::setTestNow();
        }
    }
}
