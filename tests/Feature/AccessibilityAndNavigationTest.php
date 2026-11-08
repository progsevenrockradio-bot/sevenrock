<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Talent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccessibilityAndNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_skip_to_content_link_is_present_and_targets_site_main(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        $html = $response->getContent();
        $this->assertStringContainsString('href="#site-main"', $html);
        $this->assertStringContainsString('Saltar al contenido', $html);
        $this->assertStringContainsString('id="site-main"', $html);
    }

    public function test_muro_del_rock_has_h1_and_search_label(): void
    {
        $user = \App\Models\User::factory()->create();

        Talent::query()->create([
            'user_id' => $user->id,
            'band_name' => 'Banda Accesible Test',
            'email' => 'banda@test.com',
            'password' => Hash::make('secret123'),
            'plan' => 'free',
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        $response = $this->get(route('talents.explore'));
        $response->assertStatus(200);

        $html = $response->getContent();
        // H1 textual
        $this->assertMatchesRegularExpression('/<h1[^>]*>[\s\S]*?Muro[\s\S]*?Rock[\s\S]*?<\/h1>/i', $html);
        // Label for search input
        $this->assertStringContainsString('for="muro-search-input"', $html);
        $this->assertStringContainsString('id="muro-search-input"', $html);
        // Card title uses H3
        $this->assertStringContainsString('Banda Accesible Test', $html);
        $this->assertMatchesRegularExpression('/<h3[^>]*font-display[^>]*>[\s\S]*?Banda Accesible Test/i', $html);
    }

    public function test_hero_rocks_has_touch_friendly_indicators_and_pause_control(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        $html = $response->getContent();
        // Touch hit area for indicators (Lucille visual harmonization)
        $this->assertStringContainsString('aria-label="Ir a la diapositiva', $html);
        $this->assertStringContainsString('min-w-[28px] min-h-[28px]', $html);
        // Play / pause toggle button and states
        $this->assertStringContainsString('Pausar pase de diapositivas', $html);
        $this->assertStringContainsString('syncAutoPlay', $html);
        $this->assertStringContainsString('reducedMotion', $html);
        $this->assertStringContainsString('userPaused', $html);
        // Synchronized aria-hidden and inert on slides
        $this->assertStringContainsString(':aria-hidden="(active !==', $html);
        $this->assertStringContainsString(':inert="active !==', $html);
        // Readable red schedule text
        $this->assertStringContainsString('--lucille-accent-readable', $html);
    }

    public function test_tickers_render_single_accessible_track_and_hidden_decorative_track(): void
    {
        // Crear efeméride para hoy
        Post::query()->create([
            'title' => 'Freddie Mercury en Wembley',
            'slug' => 'freddie-mercury-en-wembley-1986',
            'content' => '<p>Gran concierto histórico de Queen en Londres.</p>',
            'excerpt' => 'Gran concierto histórico de Queen.',
            'categories' => ['Hoy en el Rock'],
            'status' => 'published',
            'published_at' => Carbon::now('America/Caracas'),
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);

        $html = $response->getContent();

        // Efemérides ticker: single interactive buttons track and cloned aria-hidden track
        $this->assertStringContainsString('id="efemerides-ticker"', $html);
        $this->assertStringContainsString('id="efem-ticker-toggle"', $html);
        $this->assertStringContainsString('Pausar movimiento de efemérides', $html);
        $this->assertStringContainsString('class="pointer-events-none flex items-center gap-0 shrink-0 efem-track-clone" aria-hidden="true"', $html);

        // Reduced motion ticker accessibility (scrollable instead of cut-off)
        $this->assertStringContainsString('#efemerides-ticker {', $html);
        $this->assertStringContainsString('overflow-x: auto !important', $html);
        $this->assertStringContainsString('.efem-track-clone {', $html);
        $this->assertStringContainsString('display: none !important', $html);

        // Modals dialog role, aria-modal and focus trap / background inert
        $this->assertStringContainsString('id="efem-modal"', $html);
        $this->assertStringContainsString('id="affiliate-modal"', $html);
        $mainStart = strpos($html, 'id="site-main"');
        $mainEnd = strpos($html, '</main>');
        $footerEnd = strpos($html, '</footer>');
        $modalPos = strpos($html, 'id="efem-modal"');
        $affiliatePos = strpos($html, 'id="affiliate-modal"');

        // Both modals must be outside #site-main and outside <footer> (no inert ancestors)
        $this->assertGreaterThan($mainEnd, $modalPos, 'efem-modal must be rendered outside #site-main');
        $this->assertGreaterThan($footerEnd, $affiliatePos, 'affiliate-modal must be rendered outside <footer>');
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('setBackgroundInert(true)', $html);
        $this->assertStringContainsString('el !== modal', $html);
    }

    public function test_search_and_navigation_contain_accessible_names_and_attributes(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        $html = $response->getContent();

        // Search trigger and search input label
        $this->assertStringContainsString('aria-label="Buscar en el sitio"', $html);
        $this->assertStringContainsString('id="site-nav-search-input"', $html);
        $this->assertStringContainsString('for="site-nav-search-input"', $html);

        // Mobile menu trigger
        $this->assertStringContainsString('aria-label="Menú principal de navegación"', $html);
    }
}
