<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\SitemapController;
use App\Models\Event;
use App\Models\MasterProgram;
use App\Models\Post;
use App\Models\RadioProgram;
use App\Models\Talent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SeoDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget(SitemapController::CACHE_KEY);
    }

    public function test_sitemap_returns_valid_xml_with_canonical_routes_and_no_static_masking(): void
    {
        // Verificar que no existe un archivo estático en public/ que eclipse la ruta dinámica
        $this->assertFileDoesNotExist(public_path('sitemap.xml'));

        // 1. Post publicado válido
        $publishedPost = Post::create([
            'title' => 'Noticia Canónica Rock',
            'slug' => 'noticia-canonica-rock',
            'content' => 'Contenido de prueba rock',
            'published_at' => now()->subDay(),
            'is_published' => true,
        ]);

        // 2. Evento publicado
        $event = Event::create([
            'title' => 'Concierto Metálico 2027',
            'slug' => 'concierto-metalico-2027',
            'location' => 'Bogotá',
            'venue' => 'Movistar Arena',
            'starts_at' => now()->addMonths(3),
            'is_published' => true,
        ]);

        // 3. Programa activo
        $master = MasterProgram::create([
            'nombre' => 'La Hora del Metal',
            'archive_identifier' => 'la-hora-del-metal',
            'conductor' => 'Rock Conductor',
            'dia_transmision' => 'SABADO',
            'hora_transmision' => '20:00',
            'pais' => 'Colombia',
            'genero' => 'Rock / Metal',
            'activo' => true,
        ]);

        // 4. Episodio de podcast aprobado, procesado y no futuro
        $episode = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'La Hora del Metal #15',
            'conductor' => 'Rock Conductor',
            'numero_episodio' => 15,
            'live_title' => 'Especial Megadeth',
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'archivo_mp3' => 'episodes/metal-15.mp3',
            'fecha_emision' => now()->subDays(2),
        ]);

        // 5. Talento con perfil público activo
        $talent = Talent::create([
            'band_name' => 'Koyi K Utho',
            'email' => 'koyi@test.com',
            'password' => bcrypt('secret'),
            'is_hidden' => false,
            'subscription_status' => 'active',
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');

        $content = $response->getContent();

        // Páginas estáticas canónicas presentes
        $this->assertStringContainsString('/muro-del-rock</loc>', $content);
        $this->assertStringContainsString('/blog</loc>', $content);
        $this->assertStringContainsString('/events</loc>', $content);
        $this->assertStringContainsString('/programas</loc>', $content);
        $this->assertStringContainsString('/multimedia</loc>', $content);

        // Exclusión estricta de /talentos (es redirección 301)
        $this->assertStringNotContainsString('/talentos</loc>', $content);

        // Inclusión de entidades públicas
        $this->assertStringContainsString('noticia-canonica-rock</loc>', $content);
        $this->assertStringContainsString('/js_events/concierto-metalico-2027</loc>', $content);
        $this->assertStringContainsString('/programas/la-hora-del-metal</loc>', $content);
        $this->assertStringContainsString('/podcasts/la-hora-del-metal/15</loc>', $content);
        $this->assertStringContainsString('/muro-del-rock/Koyi%20K%20Utho</loc>', $content);

        // Formato ISO en marcas lastmod
        $this->assertMatchesRegularExpression('/<lastmod>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $content);
    }

    public function test_strict_visibility_criteria_excludes_future_draft_and_processing_content(): void
    {
        // 1. Post futuro (programado): NO debe aparecer
        $futurePost = Post::create([
            'title' => 'Noticia Futura No Indexable',
            'slug' => 'noticia-futura-no-indexable',
            'content' => 'Futuro',
            'published_at' => now()->addDays(5),
            'is_published' => true,
        ]);

        // 2. Post borrador: NO debe aparecer
        $draftPost = Post::create([
            'title' => 'Borrador Confidencial',
            'slug' => 'borrador-confidencial',
            'content' => 'Borrador',
            'published_at' => now()->subDay(),
            'is_published' => false,
        ]);

        // 3. Efeméride "Hoy en el Rock": NO debe aparecer en sitemap
        $efemPost = Post::create([
            'title' => 'Efeméride 31 Octubre',
            'slug' => 'efemeride-31-octubre',
            'content' => 'Efeméride',
            'categories' => ['Hoy en el Rock'],
            'published_at' => now()->subDay(),
            'is_published' => true,
        ]);

        // 4. Programa inactivo: NO debe aparecer
        $inactiveMaster = MasterProgram::create([
            'nombre' => 'Programa Inactivo',
            'archive_identifier' => 'programa-inactivo',
            'conductor' => 'Nadie',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '10:00',
            'pais' => 'Colombia',
            'genero' => 'Rock',
            'activo' => false,
        ]);

        // 5. Episodio en programa inactivo: NO debe aparecer
        $episodeInactive = RadioProgram::create([
            'master_program_id' => $inactiveMaster->id,
            'titulo_programa' => 'Episodio Inactivo',
            'conductor' => 'Nadie',
            'numero_episodio' => 1,
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'archivo_mp3' => 'audio.mp3',
        ]);

        // 6. Episodio pendiente de procesamiento de audio: NO debe aparecer
        $activeMaster = MasterProgram::create([
            'nombre' => 'Programa Activo Test',
            'archive_identifier' => 'programa-activo-test',
            'conductor' => 'Locutor',
            'dia_transmision' => 'MARTES',
            'hora_transmision' => '18:00',
            'pais' => 'Colombia',
            'genero' => 'Rock',
            'activo' => true,
        ]);

        $episodeProcessing = RadioProgram::create([
            'master_program_id' => $activeMaster->id,
            'titulo_programa' => 'Episodio Procesando',
            'conductor' => 'Locutor',
            'numero_episodio' => 2,
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'archivo_mp3' => 'audio.mp3',
            'processing_started_at' => now()->subMinutes(10),
            'processing_finished_at' => null, // Pendiente de procesamiento
        ]);

        // 7. Episodio con fecha de emisión futura: NO debe aparecer
        $episodeFuture = RadioProgram::create([
            'master_program_id' => $activeMaster->id,
            'titulo_programa' => 'Episodio Futuro',
            'conductor' => 'Locutor',
            'numero_episodio' => 3,
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'archivo_mp3' => 'audio.mp3',
            'fecha_emision' => now()->addDays(3),
        ]);

        // 8. Talento oculto o suscripción cancelada: NO debe aparecer
        $hiddenTalent = Talent::create([
            'band_name' => 'Banda Oculta',
            'email' => 'oculta@test.com',
            'password' => bcrypt('secret'),
            'is_hidden' => true,
            'subscription_status' => 'active',
        ]);

        $cancelledTalent = Talent::create([
            'band_name' => 'Banda Cancelada',
            'email' => 'cancelada@test.com',
            'password' => bcrypt('secret'),
            'is_hidden' => false,
            'subscription_status' => 'cancelled',
        ]);

        $response = $this->get('/sitemap.xml');
        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringNotContainsString('noticia-futura-no-indexable', $content);
        $this->assertStringNotContainsString('borrador-confidencial', $content);
        $this->assertStringNotContainsString('efemeride-31-octubre', $content);
        $this->assertStringNotContainsString('programa-inactivo', $content);
        $this->assertStringNotContainsString('/podcasts/programa-inactivo/1', $content);
        $this->assertStringNotContainsString('/podcasts/programa-activo-test/2', $content);
        $this->assertStringNotContainsString('/podcasts/programa-activo-test/3', $content);
        $this->assertStringNotContainsString('Banda%20Oculta', $content);
        $this->assertStringNotContainsString('Banda%20Cancelada', $content);
    }

    public function test_automatic_incorporation_and_removal_without_manual_regeneration(): void
    {
        // 1. Estado inicial: sitemap en caché sin la nueva noticia
        $initialRes = $this->get('/sitemap.xml');
        $initialRes->assertStatus(200);
        $this->assertStringNotContainsString('noticia-dinamica-auto', $initialRes->getContent());

        // 2. Se publica una nueva noticia: la invalidación automática en AppServiceProvider vacía la caché
        $post = Post::create([
            'title' => 'Noticia Dinámica Auto',
            'slug' => 'noticia-dinamica-auto',
            'content' => 'Contenido automático',
            'published_at' => now()->subMinute(),
            'is_published' => true,
        ]);

        // 3. La siguiente petición HTTP a /sitemap.xml incorpora la noticia de inmediato
        $afterPublishRes = $this->get('/sitemap.xml');
        $afterPublishRes->assertStatus(200);
        $this->assertStringContainsString('noticia-dinamica-auto</loc>', $afterPublishRes->getContent());

        // 4. Se retira la visibilidad de la noticia (is_published = false)
        $post->update(['is_published' => false]);

        // 5. La siguiente petición HTTP a /sitemap.xml ya NO contiene la noticia, sin comandos manuales
        $afterUnpublishRes = $this->get('/sitemap.xml');
        $afterUnpublishRes->assertStatus(200);
        $this->assertStringNotContainsString('noticia-dinamica-auto</loc>', $afterUnpublishRes->getContent());
    }

    public function test_no_duplicate_urls_and_canonical_consistency(): void
    {
        $post = Post::create([
            'title' => 'Verificación Canónica',
            'slug' => 'verificacion-canonica',
            'content' => 'Texto',
            'published_at' => now()->subDay(),
            'is_published' => true,
        ]);

        $response = $this->get('/sitemap.xml');
        $response->assertStatus(200);
        $xml = $response->getContent();

        // Extraer todas las URLs
        preg_match_all('/<loc>(.*?)<\/loc>/', $xml, $matches);
        $urls = $matches[1] ?? [];

        $this->assertNotEmpty($urls);
        $this->assertSame(count($urls), count(array_unique($urls)), 'El sitemap contiene URLs duplicadas.');

        // Verificar coincidencia con el tag canonical del post
        $postPub = $post->published_at;
        $postUrl = url($postPub->format('Y') . '/' . $postPub->format('m') . '/' . $postPub->format('d') . '/' . $post->slug);

        $this->assertContains($postUrl, $urls);

        $postRes = $this->get($postUrl);
        $postRes->assertStatus(200);
        $postRes->assertSee('<link rel="canonical" href="' . $postUrl . '">', false);
    }

    public function test_robots_txt_instructs_crawlers_and_allows_render_assets(): void
    {
        $robotsPath = public_path('robots.txt');
        $this->assertFileExists($robotsPath);

        $content = file_get_contents($robotsPath);

        $this->assertStringContainsString('Sitemap: https://sevenrockradio.com/sitemap.xml', $content);
        $this->assertStringContainsString('Allow: /build/', $content);
        $this->assertStringContainsString('Allow: /assets/', $content);
        $this->assertStringContainsString('Disallow: /admin', $content);
        $this->assertStringContainsString('Disallow: /conductor', $content);
        $this->assertStringContainsString('Disallow: /talentos/dashboard', $content);
    }

    public function test_http_status_codes_and_redirects(): void
    {
        // 1. Recurso inexistente devuelve 404 real
        $this->get('/js_events/evento-que-no-existe-xyz')->assertStatus(404);
        $this->get('/2026/10/31/noticia-fantasma-inexistente')->assertStatus(404);
        $this->get('/podcasts/programa-inexistente/999')->assertStatus(404);
        $this->get('/programas/programa-inexistente-12345678')->assertStatus(404);

        // 2. /talentos y /talentos/{band} redirigen con 301 limpio a /muro-del-rock
        $redirectRes = $this->get('/talentos');
        $redirectRes->assertStatus(301);
        $redirectRes->assertRedirect('/muro-del-rock');

        $redirectBand = $this->get('/talentos/Kraken');
        $redirectBand->assertStatus(301);
        $redirectBand->assertRedirect('/muro-del-rock/Kraken');
    }
}
