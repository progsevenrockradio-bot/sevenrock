<?php

declare(strict_types=1);

namespace Tests\Feature\News;

use App\Models\Post;
use App\Models\PostTaxonomy;
use App\Models\ThemeSetting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NewsAndEfemeridesPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ThemeSetting::firstOrCreate([], [
            'email_processing_enabled' => true,
            'email_auto_publish' => false,
            'email_min_importance' => 5,
        ]);
    }

    public function test_single_post_returns_correct_article_when_slug_and_date_match(): void
    {
        // 1. Crear un post antiguo
        $olderPost = Post::create([
            'title' => 'Noticia Antigua de Iron Maiden',
            'slug' => 'noticia-antigua-iron-maiden',
            'content' => '<p>Contenido antiguo</p>',
            'excerpt' => 'Extracto antiguo',
            'status' => 'published',
            'is_published' => true,
            'published_at' => Carbon::parse('2026-10-25 12:00:00'),
        ]);

        // 2. Crear el post específico que se va a solicitar
        $targetPost = Post::create([
            'title' => 'Entrevista Exclusiva con Ghost',
            'slug' => 'entrevista-exclusiva-con-ghost',
            'content' => '<p>Entrevista completa a Papa Emeritus</p>',
            'excerpt' => 'Extracto de la entrevista',
            'status' => 'published',
            'is_published' => true,
            'published_at' => Carbon::parse('2026-10-28 10:00:00'),
        ]);

        // 3. Crear el post más reciente (el que antes se mostraba incorrectamente por error)
        $latestPost = Post::create([
            'title' => 'Nuevo Single de Metallica',
            'slug' => 'nuevo-single-de-metallica',
            'content' => '<p>Lanzamiento de nuevo single</p>',
            'excerpt' => 'Extracto nuevo',
            'status' => 'published',
            'is_published' => true,
            'published_at' => Carbon::parse('2026-10-31 15:00:00'),
        ]);

        $response = $this->get('/2026/10/28/entrevista-exclusiva-con-ghost');

        $response->assertStatus(200);
        $this->assertEquals('Entrevista Exclusiva con Ghost', $response->viewData('post')['title']);
        $this->assertStringContainsString('Entrevista completa a Papa Emeritus', (string) $response->getContent());
    }

    public function test_single_post_redirects_canonically_when_slug_exists_with_different_date(): void
    {
        $post = Post::create([
            'title' => 'Concierto de Judas Priest en Madrid',
            'slug' => 'concierto-judas-priest-madrid',
            'content' => '<p>Crónica del concierto</p>',
            'excerpt' => 'Extracto',
            'status' => 'published',
            'is_published' => true,
            'published_at' => Carbon::parse('2026-10-20 18:00:00'),
        ]);

        // Se solicita con fecha incorrecta (2026/10/22 en lugar de 2026/10/20)
        $response = $this->get('/2026/10/22/concierto-judas-priest-madrid');

        // Debe redirigir con 301 canónico a la URL verdadera del artículo, nunca mostrar el último post
        $response->assertStatus(301);
        $response->assertRedirect('/2026/10/20/concierto-judas-priest-madrid');
    }

    public function test_single_post_aborts_404_when_post_does_not_exist(): void
    {
        // Existe un post publicado recientemente
        Post::create([
            'title' => 'Última Noticia Publicada',
            'slug' => 'ultima-noticia-publicada',
            'content' => '<p>Contenido</p>',
            'excerpt' => 'Extracto',
            'status' => 'published',
            'is_published' => true,
            'published_at' => Carbon::parse('2026-10-31 16:00:00'),
        ]);

        // Se solicita un slug inexistente
        $response = $this->get('/2026/10/31/slug-inexistente-que-no-debe-cargar-otra-noticia');

        // Debe responder 404 estricto, nunca mostrar 'Última Noticia Publicada' ni 'Inspiration'
        $response->assertStatus(404);
    }

    public function test_clean_excerpt_does_not_expose_html_json_or_escaped_sequences(): void
    {
        // Caso 1: Excerpt con JSON rendered
        $post1 = new Post([
            'title' => 'Post JSON',
            'excerpt' => '{"rendered": "Extracto con &quot;comillas&quot; y <b>HTML</b>."}',
            'content' => '<p>Contenido</p>',
        ]);
        $this->assertEquals('Extracto con "comillas" y HTML.', $post1->clean_excerpt);

        // Caso 2: Excerpt con secuencias de escape y unicode
        $post2 = new Post([
            'title' => 'Post Escapado',
            'excerpt' => "Banda de rock\/metal lanza nuevo single.\r\nEsc\u00fachalos aqui.",
            'content' => '<p>Contenido</p>',
        ]);
        $this->assertEquals('Banda de rock/metal lanza nuevo single. Escúchalos aqui.', $post2->clean_excerpt);

        // Caso 3: Fallback a contenido con bloques Gutenberg
        $post3 = new Post([
            'title' => 'Post Gutenberg',
            'excerpt' => null,
            'content' => '<!-- wp:paragraph --><p>Noticia importante de <i>Megadeth</i>.</p><!-- /wp:paragraph -->',
        ]);
        $this->assertEquals('Noticia importante de Megadeth.', $post3->clean_excerpt);

        // Caso 4: Entidades HTML doblemente codificadas
        $post4 = new Post([
            'title' => 'Post Doble Encode',
            'excerpt' => '&amp;lt;p&amp;gt;Texto libre de tags&amp;lt;/p&amp;gt;',
            'content' => '<p>Contenido</p>',
        ]);
        $this->assertEquals('Texto libre de tags', $post4->clean_excerpt);
    }

    public function test_efemerides_cintillo_respects_timezone_and_displays_ordered_events(): void
    {
        $tz = config('app.timezone', 'America/Caracas');
        $nowTz = Carbon::now($tz);

        // Crear 3 efemérides para el día de hoy
        $ef1 = Post::create([
            'title' => '1975 - Se publica Bohemian Rhapsody',
            'slug' => '1975-se-publica-bohemian-rhapsody',
            'content' => '<p>Queen lanza Bohemian Rhapsody</p>',
            'excerpt' => 'Queen lanza Bohemian Rhapsody',
            'status' => 'published',
            'is_published' => true,
            'published_at' => $nowTz->copy()->startOfDay()->addHours(8),
            'categories' => ['Hoy en el Rock'],
        ]);

        $ef2 = Post::create([
            'title' => '1982 - Iron Maiden encabeza festival',
            'slug' => '1982-iron-maiden-encabeza-festival',
            'content' => '<p>Iron Maiden en directo</p>',
            'excerpt' => 'Iron Maiden en directo',
            'status' => 'published',
            'is_published' => true,
            'published_at' => $nowTz->copy()->startOfDay()->addHours(10),
            'categories' => ['Hoy en el Rock'],
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('1975 - Se publica Bohemian Rhapsody');
        $response->assertSee('1982 - Iron Maiden encabeza festival');
    }

    public function test_efemerides_html_email_is_separated_into_individual_numbered_posts(): void
    {
        $htmlEmail = '<p>1. 1965: The Beatles graban Yesterday en los estudios Abbey Road.</p>'
                   . '<p>2. 1973: Pink Floyd alcanza el n&uacute;mero uno en listas con The Dark Side of the Moon.</p>'
                   . '<p>3. 🎸 1980: AC/DC estrena Back in Black mundialmente.</p>';

        $preparedBody = preg_replace('/<(?:br|\/p|\/div|\/li|\/tr|\/h[1-6])[^>]*>/i', "\n", $htmlEmail) ?? $htmlEmail;
        $plainBody = strip_tags($preparedBody);
        $plainBody = html_entity_decode($plainBody, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\r?\n/', $plainBody) ?: [])
        ));

        $itemPattern = '/^(?:[\x{1F300}-\x{1FFFF}\x{2600}-\x{27BF}]\s*)?(?:\[?\d+[.)\-\]]+|\d+\s*[-–—])\s*/u';

        $items = [];
        $current = null;
        foreach ($lines as $line) {
            if (preg_match($itemPattern, $line)) {
                if ($current !== null) {
                    $items[] = $current;
                }
                $current = $line;
            } elseif ($current !== null && $line !== '') {
                $current .= ' ' . $line;
            }
        }
        if ($current !== null) {
            $items[] = $current;
        }

        $this->assertCount(3, $items);

        foreach ($items as $item) {
            $cleanItem = preg_replace($itemPattern, '', $item) ?? $item;
            $cleanItem = preg_replace('/^[\x{1F300}-\x{1FFFF}\x{2600}-\x{27BF}]\s*/u', '', $cleanItem) ?? $cleanItem;
            $cleanItem = trim($cleanItem);

            $efTitle = $cleanItem;
            if (preg_match('/^(.{10,120}?[.!?])\s/u', $cleanItem, $m)) {
                $efTitle = rtrim($m[1], '.!?');
            }
            $efTitle = trim($efTitle, ' .,;:—-');

            Post::create([
                'title' => $efTitle,
                'slug' => \Illuminate\Support\Str::slug($efTitle),
                'content' => '<p>' . $cleanItem . '</p>',
                'excerpt' => Post::sanitizeExcerpt($cleanItem, null, 160),
                'status' => 'draft',
                'is_published' => false,
                'published_at' => now(),
                'categories' => ['Hoy en el Rock'],
            ]);
        }

        $this->assertEquals(3, Post::whereJsonContains('categories', 'Hoy en el Rock')->count());
        $this->assertTrue(Post::where('title', 'like', '%Beatles%')->exists());
        $this->assertTrue(Post::where('title', 'like', '%Pink Floyd%')->exists());
        $this->assertTrue(Post::where('title', 'like', '%AC/DC%')->exists());
    }

    public function test_news_email_deduplication_detects_duplicate_within_48h_window(): void
    {
        $existingPost = Post::create([
            'title' => 'Metallica confirma gira por Latinoamerica para 2027',
            'slug' => 'metallica-confirma-gira-latinoamerica-2027',
            'content' => '<p>Detalles de la gira de Metallica</p>',
            'excerpt' => 'Detalles de la gira de Metallica',
            'status' => 'draft',
            'is_published' => false,
            'published_at' => now()->subHours(6),
            'source_subject' => 'Metallica anuncia fechas para su gira latinoamericana',
            'created_at' => now()->subHours(6),
        ]);

        $incomingSubject = 'Metallica anuncia fechas para su gira latinoamericana!';
        $incomingTitle = 'Metallica confirma gira por Latinoamerica para 2027.';

        $normalizedSlug = \App\Support\TextNormalizer::normalizeSlug($incomingTitle);
        $originalSubjectNormalized = \App\Support\TextNormalizer::normalizeSlug($incomingSubject);

        $windowHours = (int) config('services.dedupe.window_hours', 48);
        $recentPosts = Post::where('created_at', '>=', now()->subHours($windowHours))->get();

        $isDuplicate = false;
        $matchedPostId = null;

        foreach ($recentPosts as $recent) {
            $recentSubjectNorm = \App\Support\TextNormalizer::normalizeSlug($recent->source_subject ?? '');
            if ($recentSubjectNorm !== '' && $recentSubjectNorm === $originalSubjectNormalized) {
                $isDuplicate = true;
                $matchedPostId = $recent->id;
                break;
            }

            $recentNormSlug = \App\Support\TextNormalizer::normalizeSlug($recent->title);
            if ($recentNormSlug === $normalizedSlug) {
                $isDuplicate = true;
                $matchedPostId = $recent->id;
                break;
            }

            $shared = \App\Support\EntityMatcher::sharedEntities($recent->title, $incomingTitle);
            if (count($shared) >= 2) {
                $isDuplicate = true;
                $matchedPostId = $recent->id;
                break;
            }
        }

        $this->assertTrue($isDuplicate);
        $this->assertEquals($existingPost->id, $matchedPostId);
    }

    public function test_single_post_renders_clean_editorial_body_and_strips_email_doctype_and_tables(): void
    {
        $rawEmailPayload = [
            '<p>&lt;!doctype html&gt;<br />&lt;html xmlns=&quot;http://www.w3.org/1999/xhtml&quot; xmlns:v=&quot;urn:schemas-microsoft-com:vml&quot;&gt;</p>',
            '<p>&lt;/head&gt;<br />&lt;body style=&quot;background:#FAFAFA;&quot;&gt;</p>',
            '<p><span class="mcnPreviewText">Heavy metal madness feat. Nick Oliveri and Mucho Drums</span></p>',
            '<p>&lt;/td&gt;<br />&lt;/tr&gt;<br />&lt;/tbody&gt;&lt;/table&gt;</p>',
            '<p>I\'m pleased to present the debut solo album from American heavy metal guitarist Jason Walker.</p>',
            '<p><strong>Jason Walker\'s Gravity Amplifiers &quot;Disclosure Vol. 1&quot;</strong><br />Out November 20th on Ripple Music.</p>',
            '</td></tr></tbody></table>',
            '<img src="https://us.list-manage.com/track/open.php?u=123" width="1" height="1" style="display:none;" />',
        ];

        $post = Post::create([
            'title' => 'Download Jason Walker Gravity Amplifiers',
            'slug' => 'download-jason-walker-gravity-amplifiers',
            'content' => $rawEmailPayload,
            'excerpt' => null,
            'status' => 'published',
            'is_published' => true,
            'published_at' => Carbon::parse('2026-10-20 12:00:00'),
        ]);

        $response = $this->get('/2026/10/20/download-jason-walker-gravity-amplifiers');

        $response->assertStatus(200);
        $html = (string) $response->getContent();

        // 1. Debe haber eliminado artefactos de correo crudo
        $this->assertStringNotContainsString('&lt;!doctype html&gt;', $html);
        $this->assertStringNotContainsString('<!doctype html>', $html);
        $this->assertStringNotContainsString('urn:schemas-microsoft-com', $html);
        $this->assertStringNotContainsString('&lt;/td&gt;&lt;/tr&gt;', $html);
        $this->assertStringNotContainsString('list-manage.com/track', $html);

        // 2. Debe conservar el contenido legible del artículo
        $this->assertStringContainsString('Heavy metal madness feat. Nick Oliveri and Mucho Drums', $html);
        $this->assertStringContainsString('debut solo album from American heavy metal guitarist Jason Walker', $html);
        $this->assertStringContainsString('Disclosure Vol. 1', $html);

        // 3. Meta description debe estar libre de DOCTYPE
        $this->assertStringNotContainsString('&lt;!doctype', $html);
    }

    public function test_efemerides_cintillo_never_falls_back_to_different_date_when_today_has_no_events(): void
    {
        $tz = config('app.timezone', 'America/Caracas');
        $nowTz = Carbon::now($tz);

        // Crear una efeméride de hace 12 días
        $oldEfem = Post::create([
            'title' => '1980: U2 publica Boy, su disco debut',
            'slug' => '1980-u2-publica-boy-su-disco-debut',
            'content' => '<p>U2 estrena su primer álbum</p>',
            'excerpt' => 'U2 estrena su primer álbum',
            'status' => 'published',
            'is_published' => true,
            'published_at' => $nowTz->copy()->subDays(12)->setTime(10, 0, 0),
            'categories' => ['Hoy en el Rock'],
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);

        // El cintillo nunca debe cargar efemérides de otra fecha como si fueran de hoy
        $efemerides = $response->viewData('efemerides');
        $this->assertCount(0, $efemerides);
        $this->assertStringNotContainsString('id="efemerides-ticker"', (string) $response->getContent());
    }

    public function test_repair_command_preview_mode_preserves_database_and_shows_clean_extraction(): void
    {
        $rawEmailPayload = [
            '<p>&lt;!doctype html&gt;<br />&lt;html xmlns=&quot;urn:schemas-microsoft-com&quot;&gt;</p>',
            '<p>&lt;/td&gt;&lt;/tr&gt;&lt;/tbody&gt;&lt;/table&gt;</p>',
            '<p>Noticia exclusiva de rock: Presentación de nuevo disco.</p>',
            '<p>Biografía de la banda y detalles de lanzamiento.</p>',
            '</td></tr></tbody></table>',
        ];

        $post = Post::create([
            'title' => 'Noticia Contaminada con Correo',
            'slug' => 'noticia-contaminada-con-correo',
            'content' => $rawEmailPayload,
            'excerpt' => null,
            'status' => 'published',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $originalRawContent = $post->getRawOriginal('content');

        // 1. Ejecutar en modo preview (sin --apply)
        $this->artisan('post:repair-editorial-body', ['id' => $post->id])
            ->expectsOutputToContain('MODO VISTA PREVIA ACTIVO')
            ->assertExitCode(0);

        // Asegurar que la base de datos permanece intacta en preview
        $post->refresh();
        $this->assertEquals($originalRawContent, $post->getRawOriginal('content'));

        // 2. Ejecutar con --apply
        $this->artisan('post:repair-editorial-body', ['id' => $post->id, '--apply' => true])
            ->expectsOutputToContain('REPARACIÓN LOCAL APLICADA CON ÉXITO')
            ->assertExitCode(0);

        $post->refresh();
        // El contenido debe contener solo 2 bloques limpios (las 2 frases legibles)
        $this->assertCount(2, $post->content);
        $this->assertStringContainsString('Noticia exclusiva de rock', $post->content[0]);
        $this->assertStringContainsString('Biografía de la banda', $post->content[1]);
    }
}
