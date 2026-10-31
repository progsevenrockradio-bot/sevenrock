<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterProgramCountryOriginTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_master_program_with_country(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $countries = [
            'España',
            'Argentina',
            'Chile',
            'Venezuela',
            'México',
        ];

        foreach ($countries as $index => $country) {
            $name = "Programa Internacional {$index}";
            $payload = [
                'nombre' => $name,
                'conductor' => "Conductor {$country}",
                'pais' => $country,
                'dia_transmision' => 'LUNES',
                'hora_transmision' => '10:00',
                'timezone' => 'America/Caracas',
                'duracion_minutos' => 60,
                'genero' => 'Rock',
                'activo' => 1,
            ];

            $this->actingAs($admin)
                ->post(route('admin.master-programs.store'), $payload)
                ->assertRedirect();

            $master = MasterProgram::query()->where('nombre', $name)->first();
            $this->assertNotNull($master);
            $this->assertSame($country, $master->pais);

            // Verificar formulario de edición
            $this->actingAs($admin)
                ->get(route('admin.master-programs.edit', $master))
                ->assertOk()
                ->assertSee('value="' . $country . '"', false);

            // Actualizar a otro país o limpiar
            $updatePayload = array_merge($payload, ['pais' => ($index === 0 ? '' : 'Uruguay')]);
            $this->actingAs($admin)
                ->put(route('admin.master-programs.update', $master), $updatePayload)
                ->assertRedirect();

            $master->refresh();
            $this->assertSame($index === 0 ? null : 'Uruguay', $master->pais);
        }
    }

    public function test_program_info_api_exposes_country_with_master_and_episode_precedence(): void
    {
        // 1. Programa máster con país
        $master = MasterProgram::query()->create([
            'nombre' => 'La Hora del Rock',
            'conductor' => 'Pedro Pérez',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '10:00',
            'duracion_minutos' => 60,
            'timezone' => 'America/Caracas',
            'genero' => 'Rock',
            'pais' => 'Argentina',
            'activo' => true,
        ]);

        // Consulta de API sin episodio específico: toma el país del máster
        $response = $this->getJson("/api/player/program-info?program_id={$master->id}");
        $response->assertOk()
            ->assertJsonPath('data.name', 'La Hora del Rock')
            ->assertJsonPath('data.country', 'Argentina');

        // 2. Episodio con país propio (prevalece la emisión concreta)
        $episode = RadioProgram::query()->create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'La Hora del Rock - Edición Chile',
            'conductor' => 'Pedro Pérez',
            'pais' => 'Chile',
            'fecha_emision' => now()->toDateString(),
        ]);

        $responseEpisode = $this->getJson("/api/player/program-info?program_id={$master->id}");
        $responseEpisode->assertOk()
            ->assertJsonPath('data.country', 'Chile');

        // 3. Cuando país está vacío en máster y episodio, no se inventa nada y data.country es null
        $master->update(['pais' => null]);
        $episode->update(['pais' => null]);

        $responseEmpty = $this->getJson("/api/player/program-info?program_id={$master->id}");
        $responseEmpty->assertOk()
            ->assertJsonPath('data.country', null);
    }
}

