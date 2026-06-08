<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_loads_without_legacy_table_errors(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Podcast uploads');
        $response->assertSee('Band profiles');
        $response->assertSee('Songs');
    }

    public function test_admin_settings_requires_password_confirmation(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'password' => bcrypt('secret-pass'),
        ]);

        // Creamos el rol necesario para el entorno de pruebas
        \Spatie\Permission\Models\Role::create(['name' => 'Super Admin']);

        // Asignamos el rol Super Admin para que pase el middleware de rol antes de password.confirm
        $admin->assignRole('Super Admin');

        // Intentar acceder a la configuración del sistema. Debe redirigir a la confirmación de contraseña.
        $response = $this->actingAs($admin)->get(route('admin.settings.edit'));
        $response->assertRedirect(route('password.confirm'));

        // Cargar la vista de confirmación. Debe retornar 200 OK.
        $response = $this->actingAs($admin)->get(route('password.confirm'));
        $response->assertOk();

        // Enviar contraseña incorrecta. Debe retornar errores de sesión.
        $response = $this->actingAs($admin)->post(route('password.confirm'), [
            'password' => 'wrong-pass',
        ]);
        $response->assertSessionHasErrors('password');

        // Enviar contraseña correcta. Debe procesarse sin errores y redirigir.
        $response = $this->actingAs($admin)->post(route('password.confirm'), [
            'password' => 'secret-pass',
        ]);
        $response->assertRedirect();
    }
}
