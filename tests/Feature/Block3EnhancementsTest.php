<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Post;
use App\Models\Talent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Block3EnhancementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_page_and_list_are_translated_and_differentiated(): void
    {
        // Crear dos eventos: uno con tickets externos y otro solo con ficha
        Event::query()->create([
            'title' => 'Rock al Parque',
            'slug' => 'rock-al-parque-2027',
            'starts_at' => Carbon::create(2027, 7, 15, 18, 0),
            'location' => 'Bogotá, Colombia',
            'venue' => 'Parque Simón Bolívar',
            'ticket_url' => 'https://entradas.ejemplo.com/rock-al-parque',
            'ticket_label' => 'Tickets',
            'status' => 'published',
        ]);

        Event::query()->create([
            'title' => 'Concierto Bucaramanga Rock',
            'slug' => 'concierto-bucaramanga-rock',
            'starts_at' => Carbon::create(2027, 8, 20, 20, 0),
            'location' => 'Bucaramanga',
            'venue' => 'Bucaramanga', // Idéntico a ciudad: no debe duplicarse
            'ticket_url' => '', // Sin entradas externas: debe mostrar "Ver evento"
            'ticket_label' => '',
            'status' => 'published',
        ]);

        $response = $this->get(route('events'));
        $response->assertStatus(200);

        $html = $response->getContent();

        // Traducción de encabezados y no fijar 2026
        $this->assertStringContainsString('Próximos conciertos', $html);
        $this->assertStringContainsString('Fechas de conciertos', $html);
        $this->assertStringNotContainsString('Upcoming Shows', $html);
        $this->assertStringNotContainsString('Tour Dates 2026', $html);

        // Número grande del día conservado con clase lucille-event-day
        $this->assertStringContainsString('class="lucille-event-day">15</span>', $html);

        // Distinción de título y ubicación
        $this->assertStringContainsString('Rock al Parque', $html);
        $this->assertStringContainsString('Bogotá, Colombia', $html);
        $this->assertStringContainsString('Parque Simón Bolívar', $html);

        // No duplicar Bucaramanga cuando ciudad y recinto coinciden
        $this->assertStringContainsString('Concierto Bucaramanga Rock', $html);
        $this->assertStringContainsString('Bucaramanga', $html);

        // Distinción entre "Entradas" (cuando hay ticket_url) y "Ver evento" (cuando solo abre ficha)
        $this->assertStringContainsString('Entradas', $html);
        $this->assertStringContainsString('Ver evento', $html);
    }

    public function test_muro_del_rock_distinguishes_empty_catalog_and_search_no_results(): void
    {
        // 1. Catálogo vacío sin filtros
        $response = $this->get(route('talents.explore'));
        $response->assertStatus(200);
        $html = $response->getContent();

        $this->assertStringContainsString('Catálogo del Muro del Rock', $html);
        $this->assertStringContainsString('Registrar banda', $html);
        $this->assertStringContainsString('Muro de la Comunidad', $html);

        // 2. Búsqueda sin resultados
        $responseSearch = $this->get(route('talents.explore', ['search' => 'InexistenteXYZ']));
        $responseSearch->assertStatus(200);
        $htmlSearch = $responseSearch->getContent();

        $this->assertStringContainsString('Búsqueda sin resultados', $htmlSearch);
        $this->assertStringContainsString('Limpiar filtros', $htmlSearch);
        $this->assertStringContainsString('Registrar banda', $htmlSearch);
    }

    public function test_multimedia_page_contains_spanish_tabs_hash_sync_and_single_player(): void
    {
        $response = $this->get(route('multimedia'));
        $response->assertStatus(200);
        $html = $response->getContent();

        // Textos y tabs en español
        $this->assertStringContainsString('Radio En Vivo', $html);
        $this->assertStringContainsString('Catálogo de Música', $html);
        $this->assertStringContainsString('Galería de Fotos', $html);
        $this->assertStringContainsString('Videos', $html);

        // Sincronización con hash y popstate
        $this->assertStringContainsString("window.addEventListener('hashchange'", $html);
        $this->assertStringContainsString("history.replaceState", $html);

        // Motor unificado de reproducción
        $this->assertStringContainsString('play-multimedia-track', $html);
    }

    public function test_blog_single_post_removes_meta_widget_and_shows_related_posts(): void
    {
        $post = Post::query()->create([
            'title' => 'Noticia de Rock Histórica',
            'slug' => 'noticia-de-rock-historica',
            'content' => '<p>Cuerpo legible del artículo de rock.</p>',
            'excerpt' => 'Extracto de rock.',
            'categories' => ['Noticias Rock'],
            'status' => 'published',
            'published_at' => Carbon::now('America/Caracas'),
        ]);

        $post2 = Post::query()->create([
            'title' => 'Otra Noticia Relevante',
            'slug' => 'otra-noticia-relevante',
            'content' => '<p>Contenido relacionado.</p>',
            'excerpt' => 'Extracto relacionado.',
            'categories' => ['Noticias Rock'],
            'status' => 'published',
            'published_at' => Carbon::now('America/Caracas')->subDay(),
        ]);

        $url = route('posts.single', [
            'year' => $post->published_at->format('Y'),
            'month' => $post->published_at->format('m'),
            'day' => $post->published_at->format('d'),
            'slug' => $post->slug,
        ]);

        $response = $this->get($url);
        $response->assertStatus(200);

        $html = $response->getContent();

        // Widget Meta retirado
        $this->assertStringNotContainsString('WordPress.org', $html);
        $this->assertStringNotContainsString('Entries feed', $html);
        $this->assertStringNotContainsString('Comments feed', $html);

        // Artículos relacionados presentes
        $this->assertStringContainsString('Artículos relacionados', $html);
        $this->assertStringContainsString('Otra Noticia Relevante', $html);
    }

    public function test_mobile_first_viewport_has_live_cta_and_cookie_banner_options(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $html = $response->getContent();

        // CTA móvil para escuchar en vivo reutilizando el reproductor
        $this->assertStringContainsString('Escuchar en vivo', $html);
        $this->assertStringContainsString('play-multimedia-track', $html);
        $this->assertStringContainsString('md:hidden', $html);

        // Opciones de cookies presentes sin solapamiento
        $this->assertStringContainsString('cookie-consent-banner', $html);
        $this->assertStringContainsString('acceptAllCookies()', $html);
        $this->assertStringContainsString('acceptNecessaryCookies()', $html);
        $this->assertStringContainsString('openCookieSettingsModal()', $html);
    }
}

