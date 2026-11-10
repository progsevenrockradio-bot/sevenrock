<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Models\EmailPostSanitizedMeta;
use App\Models\Post;
use App\Models\User;
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
    }

    /**
     * 1. Reproduce el rechazo mediante la acción «Publicar» del panel cuando existen
     * fuentes externas sin aprobación previa y la imagen no está normalizada.
     */
    public function test_reproduces_panel_rejection_for_unverified_sources_and_preserves_draft(): void
    {
        $this->actingAs($this->adminUser);

        $post = Post::create([
            'title'          => 'Nace Greg Lake, fundador de King Crimson y Emerson, Lake & Palmer',
            'slug'           => 'nace-greg-lake-fundador-de-king-crimson',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Nace Greg Lake',
            'featured_image' => 'storage/posts/covers/post-dv-e11-greg-lake-2013-elfo.jpg',
            'content'        => '<p>Texto de efeméride.</p>' .
                '<div class="editorial-sources">' .
                '<a href="https://en.wikipedia.org/wiki/Greg_Lake">Wikipedia</a>' .
                '<a href="https://mariskalrock.com/actualidad/efemerides-greg-lake/">MariskalRock</a>' .
                '</div>',
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'         => $post->id,
            'security_status' => 'sanitized',
            'sources'         => json_encode([
                'https://en.wikipedia.org/wiki/Greg_Lake',
                'https://mariskalrock.com/actualidad/efemerides-greg-lake/',
            ]),
            'approved_urls'   => null, // Sin aprobar
        ]);

        // Intentar publicar mediante la acción del panel sin registrar aprobación de URLs
        $response = $this->postJson(route('admin.editorial.approve', $post));

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'code'    => 'safety_violation',
        ]);

        // Comprobar que el rechazo identifica las fuentes no aprobadas
        $responseData = $response->json();
        $this->assertStringContainsString('unverified_external_web', json_encode($responseData['violations']));

        // REGLA CRÍTICA: El rechazo debe conservar el artículo intacto como borrador
        $post->refresh();
        $this->assertSame('draft', $post->status);
        $this->assertFalse((bool) $post->is_published);
        $this->assertNull($post->published_at);
    }

    /**
     * 2. Recorrido exitoso: Fuentes concretas aprobadas e imagen local válida.
     */
    public function test_panel_publishing_succeeds_with_approved_sources_and_valid_local_image(): void
    {
        $this->actingAs($this->adminUser);

        $post = Post::create([
            'title'          => 'Nace Greg Lake, fundador de King Crimson y Emerson, Lake & Palmer',
            'slug'           => 'nace-greg-lake-fundador-de-king-crimson',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Nace Greg Lake',
            'featured_image' => 'storage/posts/covers/post-dv-e11-greg-lake-2013-elfo.jpg',
            'content'        => '<p>Texto de efeméride.</p>' .
                '<div class="editorial-sources">' .
                '<a href="https://en.wikipedia.org/wiki/Greg_Lake">Wikipedia</a>' .
                '<a href="https://mariskalrock.com/actualidad/efemerides-greg-lake/">MariskalRock</a>' .
                '</div>',
        ]);

        $meta = EmailPostSanitizedMeta::create([
            'post_id'         => $post->id,
            'security_status' => 'sanitized',
            'sources'         => json_encode([
                'https://en.wikipedia.org/wiki/Greg_Lake',
                'https://mariskalrock.com/actualidad/efemerides-greg-lake/',
            ]),
            'approved_urls'   => [
                'https://en.wikipedia.org/wiki/Greg_Lake',
                'https://mariskalrock.com/actualidad/efemerides-greg-lake/',
            ],
        ]);

        // Publicar desde el panel
        $response = $this->postJson(route('admin.editorial.approve', $post));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'post_id' => $post->id,
        ]);

        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertTrue((bool) $post->is_published);
        $this->assertNotNull($post->published_at);

        $meta->refresh();
        $this->assertSame('passed', $meta->security_status);
    }

    /**
     * 3. Recorrido: Publicación con aprobación explícita enviada en la misma acción «Publicar».
     */
    public function test_panel_publishing_with_approve_verified_sources_flag(): void
    {
        $this->actingAs($this->adminUser);

        $post = Post::create([
            'title'          => 'Fall Out Boy anuncia gira mundial',
            'slug'           => 'fall-out-boy-anuncia-gira-mundial',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Fall Out Boy',
            'featured_image' => 'storage/posts/covers/post-dv-e03-fall-out-boy-monumentour.jpg',
            'content'        => '<p>Contenido Fall Out Boy.</p>' .
                '<div class="editorial-sources">' .
                '<a href="https://nextmosh.com/fall-out-boy-2027/">NextMosh</a>' .
                '<a href="https://loudwire.com/fall-out-boy-2027/">Loudwire</a>' .
                '</div>',
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'         => $post->id,
            'security_status' => 'sanitized',
            'sources'         => json_encode([
                'https://nextmosh.com/fall-out-boy-2027/',
                'https://loudwire.com/fall-out-boy-2027/',
            ]),
            'approved_urls'   => null,
        ]);

        // Publicar con la bandera que envía el formulario del panel
        $response = $this->postJson(route('admin.editorial.approve', $post), [
            'approve_verified_sources' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertTrue((bool) $post->is_published);

        $meta = EmailPostSanitizedMeta::where('post_id', $post->id)->first();
        $this->assertContains('https://nextmosh.com/fall-out-boy-2027/', $meta->approved_urls);
        $this->assertContains('https://loudwire.com/fall-out-boy-2027/', $meta->approved_urls);
    }

    /**
     * 4. Fuente externa no aprobada es rechazada y el artículo se conserva como borrador.
     */
    public function test_unapproved_external_source_is_rejected_and_remains_draft(): void
    {
        $this->actingAs($this->adminUser);

        $post = Post::create([
            'title'          => 'Kerry King nuevo single',
            'slug'           => 'kerry-king-nuevo-single',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Kerry King',
            'featured_image' => 'storage/posts/covers/post-dv-e04-kerry-king-2006.jpg',
            'content'        => '<p>Texto.</p>' .
                '<a href="https://blabbermouth.net/news/kerry-king">Blabbermouth</a>' .
                '<a href="https://sitio-filtracion-no-aprobado.xyz/leak">Filtración No Autorizada</a>',
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'         => $post->id,
            'security_status' => 'sanitized',
            'approved_urls'   => [
                'https://blabbermouth.net/news/kerry-king', // Solo Blabbermouth está aprobada
            ],
        ]);

        $response = $this->postJson(route('admin.editorial.approve', $post));

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'code'    => 'safety_violation',
        ]);

        $violations = json_encode($response->json('violations'));
        $this->assertStringContainsString('sitio-filtracion-no-aprobado.xyz', $violations);

        $post->refresh();
        $this->assertSame('draft', $post->status);
        $this->assertFalse((bool) $post->is_published);
    }

    /**
     * 5. Enlace con credenciales en URL es rechazado y conserva el artículo como borrador.
     */
    public function test_rejects_link_with_embedded_credentials_even_if_approval_attempted(): void
    {
        $this->actingAs($this->adminUser);

        $post = Post::create([
            'title'          => 'Artículo con credenciales maliciosas',
            'slug'           => 'articulo-credenciales',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Test',
            'featured_image' => 'storage/posts/covers/post-dv-e01-powerwolf.jpg',
            'content'        => '<p>Texto.</p>' .
                '<a href="https://editor:password123@prensa.label.com/recursos">Enlace Prensa</a>',
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'         => $post->id,
            'security_status' => 'sanitized',
            'approved_urls'   => [
                'https://editor:password123@prensa.label.com/recursos', // Intento de aprobar URL con contraseña
            ],
        ]);

        $response = $this->postJson(route('admin.editorial.approve', $post));

        $response->assertStatus(422);
        $response->assertJson(['success' => false, 'code' => 'safety_violation']);

        // Comprobar que no se expone la contraseña en texto plano
        $jsonStr = json_encode($response->json());
        $this->assertStringNotContainsString('password123', $jsonStr);
        $this->assertStringContainsString('credenciales', $jsonStr);

        $post->refresh();
        $this->assertSame('draft', $post->status);
        $this->assertFalse((bool) $post->is_published);
    }

    /**
     * 6. Rechazo de enlace restringido / EPK privado / Cloud Storage privado.
     */
    public function test_rejects_private_cloud_and_epk_links(): void
    {
        $this->actingAs($this->adminUser);

        $post = Post::create([
            'title'          => 'Artículo con EPK cerrado',
            'slug'           => 'articulo-epk',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] EPK',
            'featured_image' => 'storage/posts/covers/post-dv-e01-powerwolf.jpg',
            'content'        => '<p>Texto.</p>' .
                '<a href="https://haulix.com/promo/exclusive-stream">Haulix Promo</a>',
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'         => $post->id,
            'security_status' => 'sanitized',
            'approved_urls'   => ['https://haulix.com/promo/exclusive-stream'],
        ]);

        $response = $this->postJson(route('admin.editorial.approve', $post));

        $response->assertStatus(422);
        $post->refresh();
        $this->assertSame('draft', $post->status);
        $this->assertFalse((bool) $post->is_published);
    }

    /**
     * 7. Rechazo de imágenes peligrosas: Path Traversal, SSRF, esquemas maliciosos.
     */
    public function test_rejects_dangerous_image_paths_and_preserves_draft(): void
    {
        $this->actingAs($this->adminUser);

        $dangerousCases = [
            'path_traversal'         => 'storage/posts/covers/../../../../etc/passwd',
            'path_traversal_encoded' => 'storage/posts/covers/%2e%2e/%2e%2e/env',
            'cloud_metadata_ssrf'    => 'http://169.254.169.254/latest/meta-data/credentials',
            'localhost_ssrf'         => 'http://127.0.0.1:8000/private-data.jpg',
            'private_subnet_ssrf'    => 'http://192.168.1.100/admin.png',
            'credentials_in_url'     => 'http://admin:secretpass@evil.com/cover.jpg',
            'file_scheme'            => 'file:///etc/shadow',
            'data_scheme'            => 'data:image/svg+xml;base64,PHN2Zz4=',
            'outside_authorized_dir' => 'storage/framework/cache/session.php',
            'invalid_extension'      => 'storage/posts/covers/exploit.exe',
        ];

        foreach ($dangerousCases as $label => $dangerousPath) {
            $post = Post::create([
                'title'          => "Artículo imagen {$label}",
                'slug'           => "articulo-imagen-{$label}",
                'status'         => 'draft',
                'is_published'   => false,
                'source_subject' => '[DV-1] Imagen Test',
                'featured_image' => $dangerousPath,
                'content'        => '<p>Texto sin enlaces externos.</p>',
            ]);

            EmailPostSanitizedMeta::create([
                'post_id'         => $post->id,
                'security_status' => 'sanitized',
            ]);

            $response = $this->postJson(route('admin.editorial.approve', $post));

            $this->assertSame(
                422,
                $response->status(),
                "La ruta peligrosa '{$dangerousPath}' ({$label}) debió ser rechazada con 422 pero devolvió {$response->status()}"
            );
            $response->assertJson([
                'success' => false,
                'code'    => 'safety_violation',
            ]);

            $post->refresh();
            $this->assertSame('draft', $post->status);
            $this->assertFalse((bool) $post->is_published);
        }
    }

    /**
     * 8. Endpoint de aprobación de URLs concretas: registra y audita.
     */
    public function test_endpoint_approve_urls_registers_and_audits(): void
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
        $this->assertContains('https://blabbermouth.net/news/deep-purples-ian-gillan', $meta->approved_urls);
        $this->assertContains('https://www.loudersound.com/bands-artists/ian-gillan', $meta->approved_urls);

        // Intento de registrar URL peligrosa
        $badResponse = $this->postJson(route('admin.editorial.approve-urls', $post), [
            'urls' => ['http://127.0.0.1/admin-secrets'],
        ]);

        $badResponse->assertStatus(422);
    }

    /**
     * 9. Simulación exhaustiva de las cinco piezas del lote DV-20261110-01 (E01, E02, E03, E04, E11):
     * Comprueba que las 4 noticias y 1 efeméride son completamente publicables con sus fuentes e imágenes reales.
     */
    public function test_simulation_of_all_five_batch_pieces_pass_validation_with_approved_sources(): void
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

            EmailPostSanitizedMeta::create([
                'post_id'         => $post->id,
                'batch_id'        => 'DV-20261110-01',
                'element_id'      => "DV-20261110-01-{$code}",
                'security_status' => 'sanitized',
                'sources'         => json_encode($data['sources']),
                'image_credit'    => $data['credit'],
                'approved_urls'   => $data['sources'], // Aprobadas
            ]);

            // Comprobar con el validador directamente
            $safety = $this->validator->validatePost($post);
            $this->assertTrue(
                $safety['is_safe'],
                "La pieza {$code} ({$data['title']}) falló en la validación de seguridad: " . implode('; ', $safety['violations'])
            );
            $this->assertEmpty($safety['violations']);

            // Comprobar publicación mediante el endpoint del panel
            $resp = $this->postJson(route('admin.editorial.approve', $post));
            $resp->assertStatus(200);

            $post->refresh();
            $this->assertSame('published', $post->status);
            $this->assertTrue((bool) $post->is_published);
        }
    }
}
