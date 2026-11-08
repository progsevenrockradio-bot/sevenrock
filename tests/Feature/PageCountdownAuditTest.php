<?php

namespace Tests\Feature;

use App\Models\PageCountdown;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageCountdownAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_visitor_is_blocked_when_countdown_is_active_and_future(): void
    {
        PageCountdown::create([
            'route_path' => 'events',
            'title' => 'Próximamente Eventos',
            'description' => 'Estamos actualizando la cartelera.',
            'active_at' => now()->addDays(3),
            'is_enabled' => true,
        ]);

        $response = $this->get('/events');
        $response->assertStatus(200);
        $response->assertSee('Próximamente Eventos');
        $response->assertSee('Estamos actualizando la cartelera.');
    }

    public function test_admin_bypasses_countdown(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        PageCountdown::create([
            'route_path' => 'events',
            'title' => 'Próximamente Eventos',
            'active_at' => now()->addDays(3),
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($admin, 'web')->get('/events');
        $response->assertStatus(200);
        $response->assertDontSee('Próximamente Eventos');
    }

    public function test_countdown_does_not_block_when_active_at_is_in_past(): void
    {
        PageCountdown::create([
            'route_path' => 'events',
            'title' => 'Evento Expirado',
            'active_at' => now()->subDay(),
            'is_enabled' => true,
        ]);

        $response = $this->get('/events');
        $response->assertStatus(200);
        $response->assertDontSee('Evento Expirado');
    }

    public function test_countdown_does_not_block_when_active_at_is_null(): void
    {
        // Vulnerabilidad operativa común: el administrador crea la cuenta atrás sin fecha ("activo indefinidamente")
        PageCountdown::create([
            'route_path' => 'events',
            'title' => 'Mantenimiento Indefinido',
            'active_at' => null,
            'is_enabled' => true,
        ]);

        $response = $this->get('/events');
        $response->assertStatus(200);
        // Debe fallar el bloqueo porque el código exige $countdown->active_at && $countdown->active_at->isFuture()
        $response->assertDontSee('Mantenimiento Indefinido');
    }

    public function test_countdown_matching_variations(): void
    {
        // Caso: se guardó con slash inicial '/muro-del-rock'
        PageCountdown::create([
            'route_path' => '/muro-del-rock',
            'title' => 'Muro en Espera',
            'active_at' => now()->addDays(2),
            'is_enabled' => true,
        ]);

        $response = $this->get('/muro-del-rock');
        $response->assertStatus(200);
        $response->assertSee('Muro en Espera');
    }

    public function test_countdown_with_query_string(): void
    {
        PageCountdown::create([
            'route_path' => 'events',
            'title' => 'Eventos Filtrados Bloqueados',
            'active_at' => now()->addDays(2),
            'is_enabled' => true,
        ]);

        $response = $this->get('/events?genre=rock&page=2');
        $response->assertStatus(200);
        $response->assertSee('Eventos Filtrados Bloqueados');
    }

    public function test_countdown_disabled_does_not_block(): void
    {
        PageCountdown::create([
            'route_path' => 'events',
            'title' => 'Desactivado',
            'active_at' => now()->addDays(2),
            'is_enabled' => false,
        ]);

        $response = $this->get('/events');
        $response->assertStatus(200);
        $response->assertDontSee('Desactivado');
    }

    public function test_countdown_root_path_with_slash(): void
    {
        PageCountdown::create([
            'route_path' => '/',
            'title' => 'Portada en Espera',
            'active_at' => now()->addDays(2),
            'is_enabled' => true,
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Portada en Espera');
    }

    public function test_countdown_root_path_with_empty_string(): void
    {
        PageCountdown::create([
            'route_path' => '',
            'title' => 'Portada Vacia en Espera',
            'active_at' => now()->addDays(2),
            'is_enabled' => true,
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Portada Vacia en Espera');
    }
}
