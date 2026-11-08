<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsAndConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_gtm_and_ga_are_not_loaded_statically_without_consent(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        $html = $response->getContent();

        // 1. DataLayer inicializado para recibir eventos controlados
        $this->assertStringContainsString('window.dataLayer = window.dataLayer || [];', $html);

        // 2. Ningun script estatico de GTM o GA4 inyectado en el HTML servido al primer visitante
        $this->assertStringNotContainsString('<script src="https://www.googletagmanager.com/gtm.js', $html);
        $this->assertStringNotContainsString('googletagmanager.com/gtag/js', $html);

        // 3. Ningun iframe de noscript que genere pings sin eleccion
        $this->assertStringNotContainsString('<iframe src="https://www.googletagmanager.com/ns.html', $html);
    }

    public function test_cookie_banner_and_settings_modal_are_accessible(): void
    {
        $response = $this->get('/');
        $html = $response->getContent();

        // Controles de consentimiento
        $this->assertStringContainsString('acceptAllCookies()', $html);
        $this->assertStringContainsString('acceptNecessaryCookies()', $html);
        $this->assertStringContainsString('openCookieSettingsModal()', $html);

        // Reabrir / revocar en footer
        $this->assertStringContainsString('Preferencias de cookies', $html);
        $this->assertStringContainsString('openCookieSettings()', $html);

        // Switches
        $this->assertStringContainsString('cookie-opt-analytics', $html);
        $this->assertStringContainsString('cookie-opt-personalization', $html);
        $this->assertStringContainsString('saveCookiePreferences()', $html);
    }

    public function test_contact_success_event_emitted_only_on_server_success_session(): void
    {
        // 1. Carga normal sin exito
        $normalResponse = $this->get('/contact');
        $normalResponse->assertStatus(200);
        $this->assertStringNotContainsString('contact_success', $normalResponse->getContent());

        // 2. Carga con flash session 'success'
        $successResponse = $this->withSession(['success' => '¡Mensaje enviado con éxito!'])->get('/contact');
        $successResponse->assertStatus(200);
        $this->assertStringContainsString('contact_success', $successResponse->getContent());
        $this->assertStringContainsString('SevenRockAnalytics.track', $successResponse->getContent());
    }

    public function test_search_no_results_event_emits_only_section_and_query_length(): void
    {
        $response = $this->get('/search?q=TerminoInexistenteXYZ123456');
        $response->assertStatus(200);

        $html = $response->getContent();
        $this->assertStringContainsString('search_no_results', $html);
        $this->assertStringContainsString('query_length:', $html);
        $this->assertStringContainsString('section:', $html);
        // search_term no debe ser transmitido a la llamada de track()
        $this->assertStringNotContainsString("search_term:", $html);
    }
}
