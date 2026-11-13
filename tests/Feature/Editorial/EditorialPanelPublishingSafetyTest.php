<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Models\EmailPostSanitizedMeta;
use App\Models\Post;
use App\Models\PostTaxonomy;
use App\Models\User;
use App\Services\EditorialBatchProcessor;
use App\Services\EmailSafetyValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EditorialPanelPublishingSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected EmailSafetyValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'email'    => 'admin@sevenrockradio.com',
            'is_admin' => true,
        ]);

        if (class_exists(Role::class)) {
            $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
            $this->adminUser->assignRole($role);
        }

        $this->validator = app(EmailSafetyValidator::class);

        $coversDir = storage_path('app/public/posts/covers');
        if (! file_exists($coversDir)) {
            @mkdir($coversDir, 0755, true);
        }
        foreach (['valid-cover.jpg', 'cover.jpg', 'valid-fob.jpg'] as $file) {
            $p = $coversDir . DIRECTORY_SEPARATOR . $file;
            if (! file_exists($p)) {
                @file_put_contents($p, 'fake-cover-bytes');
            }
        }
    }

    protected function tearDown(): void
    {
        $coversDir = storage_path('app/public/posts/covers');
        foreach (['valid-cover.jpg', 'cover.jpg', 'valid-fob.jpg'] as $file) {
            $p = $coversDir . DIRECTORY_SEPARATOR . $file;
            if (file_exists($p)) {
                @unlink($p);
            }
        }
        parent::tearDown();
    }

    /**
     * 1. Acredita que importar con REVISION_REQUERIDA: NO NO aprueba fuentes automáticamente.
     */
    public function test_import_with_revision_no_does_not_auto_approve_sources(): void
    {
        PostTaxonomy::firstOrCreate(['name' => 'Noticias Rock', 'slug' => 'noticias-rock', 'type' => 'category']);
        PostTaxonomy::firstOrCreate(['name' => 'Hoy en el Rock', 'slug' => 'hoy-en-el-rock', 'type' => 'category']);

        $processor = app(EditorialBatchProcessor::class);

        $payload = <<<MD
FORMATO: DV-1
LOTE_ID: BATCH-TEST-AUTONO
TOTAL_ELEMENTOS: 1

=== INICIO ELEMENTO ===
ID: BATCH-TEST-AUTONO-E03
TIPO: NOTICIA
FECHA_EDITORIAL: 2026-11-10
TITULO: Fall Out Boy anuncia nueva gira sin revision requerida
TEXTO:
Texto descriptivo de la gira mundial.
FUENTES:
- https://nextmosh.com/fall-out-boy/
IMAGEN_URL: storage/posts/covers/valid-fob.jpg
IMAGEN_CREDITO: Foto: Test / CC BY 2.0
REVISION_REQUERIDA: NO
=== FIN ELEMENTO ===
MD;

        $result = $processor->process($payload);
        $this->assertTrue($result['success'], 'El procesamiento del lote debió ser exitoso: ' . ($result['error'] ?? ''));
        $this->assertSame(1, $result['created']);

        $meta = EmailPostSanitizedMeta::where('element_id', 'BATCH-TEST-AUTONO-E03')->first();
        $this->assertNotNull($meta);

        // REGLA CRÍTICA: NO debe concederse a sí mismo aprobación editorial
        $this->assertNull($meta->approved_urls, 'Las fuentes NO deben ser aprobadas automáticamente por REVISION_REQUERIDA: NO.');
        $this->assertNull($meta->approved_at);
        $this->assertNull($meta->approved_by);

        // Intentar publicar sin aprobación explícita debe ser rechazado
        $post = Post::find($meta->post_id);
        $this->actingAs($this->adminUser);
        $response = $this->postJson(route('admin.editorial.approve', $post));

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'code'    => 'safety_violation',
        ]);
        $post->refresh();
        $this->assertSame('draft', $post->status);
    }

    /**
     * 2. Acredita que la acción explícita registra únicamente las URLs mostradas y autorizadas,
     * guardando usuario y fecha, y que si ya están aprobadas el botón normal «Publicar» conserva la publicación sin pedir otra aprobación.
     */
    public function test_explicit_action_approves_only_shown_and_authorized_urls_and_subsequent_publish_works(): void
    {
        $this->actingAs($this->adminUser);

        $post = Post::create([
            'title'          => 'Noticia con múltiples fuentes',
            'slug'           => 'noticia-multiples-fuentes',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Multiples Fuentes',
            'featured_image' => 'storage/posts/covers/valid-cover.jpg',
            'content'        => '<p>Texto.</p>' .
                '<a href="https://source-a.com/news">Fuente A</a>' .
                '<a href="https://source-b.com/news">Fuente B</a>',
        ]);

        $meta = EmailPostSanitizedMeta::create([
            'post_id'         => $post->id,
            'security_status' => 'sanitized',
            'sources'         => json_encode(['https://source-a.com/news', 'https://source-b.com/news']),
            'approved_urls'   => null,
        ]);

        // Intento 1: Si solo se autoriza Fuente A pero el contenido contiene también Fuente B no autorizada
        $partialResponse = $this->postJson(route('admin.editorial.approve', $post), [
            'approve_explicit_sources' => 1,
            'sources_to_approve'       => ['https://source-a.com/news'], // Solo A mostrada/aprobada
        ]);

        // Debe ser rechazado porque Fuente B sigue sin aprobar
        $partialResponse->assertStatus(422);
        $post->refresh();
        $this->assertSame('draft', $post->status);

        // Intento 2: Acción explícita «Aprobar estas fuentes y publicar» con todas las fuentes mostradas
        $fullResponse = $this->postJson(route('admin.editorial.approve', $post), [
            'approve_explicit_sources' => 1,
            'sources_to_approve'       => [
                'https://source-a.com/news',
                'https://source-b.com/news',
            ],
        ]);

        $fullResponse->assertStatus(200);
        $fullResponse->assertJson(['success' => true]);

        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertTrue((bool) $post->is_published);

        $meta->refresh();
        $this->assertCount(2, $meta->approved_urls);
        $this->assertSame($this->adminUser->id, $meta->approved_by);
        $this->assertNotNull($meta->approved_at);

        // Intento 3: Si las fuentes ya están aprobadas, una publicación normal (sin enviar bandera) funciona directamente
        $post->status = 'draft';
        $post->is_published = false;
        $post->save();

        $normalResponse = $this->postJson(route('admin.editorial.approve', $post));
        $normalResponse->assertStatus(200);
        $post->refresh();
        $this->assertSame('published', $post->status);
    }

    /**
     * 3. Acredita que en producción las direcciones internas (localhost, loopback, redes privadas)
     * son rechazadas antes de normalizarse y que solo se admiten rutas relativas y el dominio público configurado.
     */
    public function test_internal_addresses_localhost_loopback_private_are_blocked_in_production_before_normalization(): void
    {
        $this->actingAs($this->adminUser);
        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        ]);

        // Simular entorno de producción
        app()['env'] = 'production';
        config(['app.url' => 'https://sevenrockradio.com']);

        $internalCases = [
            'localhost_url'       => 'http://localhost/storage/posts/covers/photo.jpg',
            'localhost_port'      => 'http://localhost:8000/storage/posts/covers/photo.jpg',
            'loopback_ipv4'       => 'http://127.0.0.1/storage/posts/covers/photo.jpg',
            'loopback_ipv6'       => 'http://[::1]/storage/posts/covers/photo.jpg',
            'cloud_metadata_ssrf' => 'http://169.254.169.254/latest/meta-data',
            'private_lan_10'      => 'http://10.0.0.1/storage/posts/covers/photo.jpg',
            'private_lan_192'     => 'http://192.168.1.10/storage/posts/covers/photo.jpg',
            'internal_domain'     => 'http://server.local/storage/posts/covers/photo.jpg',
        ];

        try {
            foreach ($internalCases as $label => $internalPath) {
                $post = Post::create([
                    'title'          => "Test {$label}",
                    'slug'           => "test-{$label}",
                    'status'         => 'draft',
                    'is_published'   => false,
                    'source_subject' => '[DV-1] Test',
                    'featured_image' => $internalPath,
                    'content'        => '<p>Texto.</p>',
                ]);

                EmailPostSanitizedMeta::create([
                    'post_id'         => $post->id,
                    'security_status' => 'sanitized',
                ]);

                $validation = $this->validator->validateFeaturedImage($internalPath);
                $this->assertFalse(
                    $validation['is_safe'],
                    "La dirección interna '{$internalPath}' ({$label}) debió ser rechazada en producción antes de normalizar."
                );

                $postValidation = $this->validator->validatePost($post);
                $this->assertFalse(
                    $postValidation['is_safe'],
                    "El post con dirección interna '{$internalPath}' ({$label}) debió ser rechazado por validatePost en producción."
                );

                $response = $this->postJson(route('admin.editorial.approve', $post));
                $response->assertStatus(422);
                $response->assertJson([
                    'success' => false,
                    'code'    => 'safety_violation',
                ]);
            }

            // Acreditar que rutas relativas autorizadas y el dominio público configurado SÍ se admiten
            $allowedRelative = 'storage/posts/covers/post-dv-e11-greg-lake.jpg';
            $valRelative = $this->validator->validateFeaturedImage($allowedRelative);
            $this->assertTrue($valRelative['is_safe'], "La ruta relativa autorizada debió admitirse.");

            $allowedPublicUrl = 'https://sevenrockradio.com/storage/posts/covers/post-dv-e11-greg-lake.jpg';
            $valPublicUrl = $this->validator->validateFeaturedImage($allowedPublicUrl);
            $this->assertTrue($valPublicUrl['is_safe'], "La URL con el dominio público configurado debió admitirse.");
        } finally {
            app()['env'] = 'testing';
        }
    }

    /**
     * 4. Acredita que enlaces privados o con credenciales incrustadas siguen bloqueados.
     */
    public function test_links_with_credentials_or_private_services_remain_blocked_even_if_approval_attempted(): void
    {
        $this->actingAs($this->adminUser);

        $post = Post::create([
            'title'          => 'Test credenciales',
            'slug'           => 'test-credenciales',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Test',
            'featured_image' => 'storage/posts/covers/cover.jpg',
            'content'        => '<p>Texto.</p><a href="https://admin:secret123@source.com/report">Enlace Privado</a>',
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'         => $post->id,
            'security_status' => 'sanitized',
        ]);

        $response = $this->postJson(route('admin.editorial.approve', $post), [
            'approve_explicit_sources' => 1,
            'sources_to_approve'       => ['https://admin:secret123@source.com/report'],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false, 'code' => 'safety_violation']);

        $post->refresh();
        $this->assertSame('draft', $post->status);
    }

    /**
     * 5. Endpoint de aprobación de URLs concretas: registra autor, fecha y audita.
     */
    public function test_endpoint_approve_urls_registers_author_and_date(): void
    {
        $this->actingAs($this->adminUser);

        $post = Post::create([
            'title'          => 'Ian Gillan intervención',
            'slug'           => 'ian-gillan-intervencion',
            'status'         => 'draft',
            'source_subject' => '[DV-1] Ian Gillan',
            'content'        => '<p>Texto.</p>',
        ]);

        $meta = EmailPostSanitizedMeta::create([
            'post_id'         => $post->id,
            'security_status' => 'needs_review',
            'sources'         => json_encode([
                'https://blabbermouth.net/news/deep-purples-ian-gillan',
                'https://www.loudersound.com/bands-artists/ian-gillan',
            ]),
        ]);

        $response = $this->postJson(route('admin.editorial.approve-urls', $post), [
            'urls' => [
                'https://blabbermouth.net/news/deep-purples-ian-gillan',
                'https://www.loudersound.com/bands-artists/ian-gillan',
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $meta->refresh();
        $this->assertCount(2, $meta->approved_urls);
        $this->assertSame($this->adminUser->id, $meta->approved_by);
        $this->assertNotNull($meta->approved_at);
        $this->assertContains('https://blabbermouth.net/news/deep-purples-ian-gillan', $meta->approved_urls);
        $this->assertContains('https://www.loudersound.com/bands-artists/ian-gillan', $meta->approved_urls);
    }

    /**
     * 6. Simulación de las 5 piezas del lote DV-20261110-01 (E01, E02, E03, E04, E11):
     * Acredita que las imágenes legítimas y los 5 borradores superan el recorrido de publicación
     * tras la aprobación correspondiente mediante «Aprobar estas fuentes y publicar».
     */
    public function test_simulation_of_all_five_batch_pieces_publishable_after_explicit_approval(): void
    {
        $this->actingAs($this->adminUser);

        $pieces = [
            'E01' => [
                'title'   => 'Powerwolf anuncia nuevo disco y una gira europea para 2027 con Apocalyptica',
                'slug'    => 'powerwolf-anuncia-nuevo-disco-2027',
                'image'   => 'storage/posts/covers/post-dv-e01-powerwolf-colmar-2018.jpg',
                'credit'  => 'Foto: Tilly antoine / Wikimedia Commons (CC BY-SA 4.0)',
                'sources' => [
                    'https://mariskalrock.com/actualidad/powerwolf-anuncia-disco-y-la-gira-moral-and-madness-world-tour-con-su-produccion-mas-grande-hasta-la-fecha/',
                    'https://www.powermetal.de/news/news-POWERWOLF_geht_2027_auf_Moral__Madness-World-Tour,64189.html',
                ],
            ],
            'E02' => [
                'title'   => 'Ian Gillan se recupera de una intervención médica con éxito y Deep Purple mantiene aplazada su gira europea',
                'slug'    => 'ian-gillan-se-recupera-de-intervencion',
                'image'   => 'storage/posts/covers/post-dv-e02-ian-gillan-asier-arco.jpg',
                'credit'  => 'Foto: Asier Arco / Wikimedia Commons (CC BY 2.0)',
                'sources' => [
                    'https://blabbermouth.net/news/deep-purples-ian-gillan-undergoes-successful-procedure-i-have-to-rest-in-order-to-make-a-complete-recovery',
                    'https://www.loudersound.com/bands-artists/ian-gillan-recovering-from-successful-procedure-following-medical-emergency-that-led-deep-purple-to-postpone-uk-and-european-tour-dates',
                    'https://mariskalrock.com/actualidad/ian-gillan-deep-purple-se-somete-a-una-intervencion-medica-tengo-que-descansar-para-recuperarme-por-completo/',
                ],
            ],
            'E03' => [
                'title'   => 'Fall Out Boy anuncia una gira mundial para 2027 con parada en Madrid',
                'slug'    => 'fall-out-boy-anuncia-gira-mundial-2027',
                'image'   => 'storage/posts/covers/post-dv-e03-fall-out-boy-monumentour.jpg',
                'credit'  => 'Foto: Fernando Loz / Wikimedia Commons (CC BY-SA 2.0)',
                'sources' => [
                    'https://nextmosh.com/fall-out-boy-announce-2027-world-tour-release-new-single-aces/',
                    'https://news.livenationentertainment.com/news/fall-out-boy-announces-2027-twilight-of-the-punks-headline-world-tour-returns-with-highly-anticipated-new-single-aces',
                    'https://loudwire.com/fall-out-boy-2027-tour-aces-song/',
                ],
            ],
            'E04' => [
                'title'   => 'Kerry King publicará «My War», primer adelanto de su segundo disco en solitario',
                'slug'    => 'kerry-king-publicara-my-war-adelanto',
                'image'   => 'storage/posts/covers/post-dv-e04-kerry-king-2006.jpg',
                'credit'  => 'Foto: Suelen Pessoa / Wikimedia Commons (CC BY-SA 2.0)',
                'sources' => [
                    'https://blabbermouth.net/news/kerry-king-announces-my-war-first-single-from-upcoming-second-solo-album',
                    'https://www.theprp.com/2026/11/09/news/slayers-kerry-king-teases-his-upcoming-new-track-my-war/',
                    'https://metalinjection.net/new-music/kerry-king-teases-my-war-from-upcoming-sophomore-solo-album',
                ],
            ],
            'E11' => [
                'title'   => 'Nace Greg Lake, fundador de King Crimson y Emerson, Lake & Palmer',
                'slug'    => 'nace-greg-lake-fundador-king-crimson',
                'image'   => 'storage/posts/covers/post-dv-e11-greg-lake-2013-elfo.jpg',
                'credit'  => 'Foto: Manticore1962 / Wikimedia Commons (CC0 1.0 Universal / Dominio Público)',
                'sources' => [
                    'https://en.wikipedia.org/wiki/Greg_Lake',
                    'https://mariskalrock.com/actualidad/efemerides-de-la-semana-10-11-16-11-metallica-manowar-guns-n-roses-motorhead-megadeth-led-zeppelin-pearl-jam-ac-dc/',
                ],
            ],
        ];

        foreach ($pieces as $code => $data) {
            $content = "<p>Contenido del artículo {$code}.</p><div class=\"editorial-sources\">";
            foreach ($data['sources'] as $src) {
                $content .= "<a href=\"{$src}\">Fuente</a>";
            }
            $content .= '</div>';

            $post = Post::create([
                'title'          => $data['title'],
                'slug'           => $data['slug'],
                'status'         => 'draft',
                'is_published'   => false,
                'source_subject' => "[DV-1] {$data['title']}",
                'featured_image' => $data['image'],
                'image_source'   => $data['credit'],
                'content'        => $content,
            ]);

            $meta = EmailPostSanitizedMeta::create([
                'post_id'         => $post->id,
                'batch_id'        => 'DV-20261110-01',
                'element_id'      => "DV-20261110-01-{$code}",
                'security_status' => 'sanitized',
                'sources'         => json_encode($data['sources']),
                'image_credit'    => $data['credit'],
                'approved_urls'   => null, // Empieza sin aprobar
            ]);

            // Paso A: Intento de publicación directa sin autorizar fuentes -> bloqueado
            $failResp = $this->postJson(route('admin.editorial.approve', $post));
            $failResp->assertStatus(422);

            // Paso B: Publicación mediante la acción explícita «Aprobar estas fuentes y publicar»
            $resp = $this->postJson(route('admin.editorial.approve', $post), [
                'approve_explicit_sources' => 1,
                'sources_to_approve'       => $data['sources'],
            ]);
            $resp->assertStatus(200);

            $post->refresh();
            $this->assertSame('published', $post->status);
            $this->assertTrue((bool) $post->is_published);
            $this->assertNotNull($post->published_at);

            $meta->refresh();
            $this->assertCount(count($data['sources']), $meta->approved_urls);
            $this->assertSame($this->adminUser->id, $meta->approved_by);
            $this->assertNotNull($meta->approved_at);

            // Verificar con el validador directamente
            $safety = $this->validator->validatePost($post);
            $this->assertTrue(
                $safety['is_safe'],
                "La pieza {$code} ({$data['title']}) falló en la validación: " . implode('; ', $safety['violations'])
            );
        }
    }
}
