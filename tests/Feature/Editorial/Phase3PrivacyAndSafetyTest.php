<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Models\EmailPostSanitizedMeta;
use App\Models\Post;
use App\Models\ThemeSetting;
use App\Services\EmailContentSanitizer;
use App\Services\EmailSafetyValidator;
use App\Services\LinkSecurityClassifier;
use App\Services\ReleaseScheduleManager;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class Phase3PrivacyAndSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $reflection = new \ReflectionClass(ThemeSetting::class);
        $property = $reflection->getProperty('currentSettings');
        $property->setAccessible(true);
        $property->setValue(null);

        ThemeSetting::query()->delete();
        ThemeSetting::create([
            'email_auto_publish' => false,
            'email_daily_posts_limit' => 5,
            'email_daily_releases_limit' => 5,
            'email_daily_news_limit' => 50,
            'ai_prefilter_enabled' => true,
        ]);
        $property->setValue(null);
    }

    /**
     * 1. Comunicado legítimo enviado mediante Mailchimp o Brevo (tracking eliminado, enlaces desenvueltos, no se descarta).
     */
    public function test_legitimate_mailing_platform_email_is_sanitized_and_unwrapped(): void
    {
        $sanitizer = new EmailContentSanitizer();
        $classifier = new LinkSecurityClassifier();

        $rawBody = '
            <h1>Bright Curse New Album Announced</h1>
            <p>Listen to the new single here: <a href="https://mailchimp.list-manage.com/track/click?u=123&id=456&url=https%3A%2F%2Fwww.youtube.com%2Fwatch%3Fv%3Dabcdefgh">Watch Video</a></p>
            <img src="https://mailchimp.list-manage.com/track/open.php?u=123&id=456" width="1" height="1" alt="" />
            <p>Update your preferences or unsubscribe from this list: <a href="https://mailchimp.list-manage.com/unsubscribe">Unsubscribe</a></p>
        ';

        $res = $sanitizer->sanitize($rawBody);

        // Tracking eliminado
        $this->assertStringNotContainsString('open.php', $res['clean_html']);
        $this->assertStringNotContainsString('width="1"', $res['clean_html']);
        $this->assertStringNotContainsString('Update your preferences', $res['clean_html']);
        $this->assertGreaterThan(0, $res['removed_trackers']);

        // Desenvolvimiento de URL estructural de Mailchimp a YouTube
        $wrappedUrl = 'https://mailchimp.list-manage.com/track/click?u=123&id=456&url=https%3A%2F%2Fwww.youtube.com%2Fwatch%3Fv%3Dabcdefgh';
        $unwrapped = $classifier->unwrapStructuralRedirect($wrappedUrl);
        $this->assertSame('https://www.youtube.com/watch?v=abcdefgh', $unwrapped);

        $classification = $classifier->classify($unwrapped);
        $this->assertSame('verified_public', $classification['classification']);
        $this->assertTrue($classification['is_allowed_in_post']);
    }

    /**
     * 2. Enlace privado de Iron Backstage (EPK / SoundCloud secret token detectado y prohibido).
     */
    public function test_private_epk_and_secret_token_links_are_restricted(): void
    {
        $classifier = new LinkSecurityClassifier();

        // SoundCloud con secret token (s-xxxxx)
        $scPrivate = 'https://soundcloud.com/ironbackstage/exclusive-stream/s-abc123xyz';
        $resSc = $classifier->classify($scPrivate);
        $this->assertSame('restricted_private', $resSc['classification']);
        $this->assertFalse($resSc['is_allowed_in_post']);
        $this->assertStringContainsString('***REDACTED***', $resSc['redacted_url']);

        // Plataforma Haulix EPK
        $haulixUrl = 'https://haulix.com/promo/iron-backstage-epk?token=press_pass_secret';
        $resHaulix = $classifier->classify($haulixUrl);
        $this->assertSame('restricted_private', $resHaulix['classification']);
        $this->assertFalse($resHaulix['is_allowed_in_post']);
        $this->assertSame('epk_promo_platform', $resHaulix['reason']);
        $this->assertStringContainsString('token=***REDACTED***', $resHaulix['redacted_url']);
    }

    /**
     * 3. URL con token o firma temporal.
     */
    public function test_url_with_sensitive_parameters_is_restricted(): void
    {
        $classifier = new LinkSecurityClassifier();

        $signedUrl = 'https://media.recordlabel.com/downloads/album.zip?signature=ab456&expires=1790000000&access_token=secretKey';
        $res = $classifier->classify($signedUrl);

        $this->assertSame('restricted_private', $res['classification']);
        $this->assertFalse($res['is_allowed_in_post']);
        $this->assertStringContainsString('***REDACTED***', $res['redacted_url']);
    }

    /**
     * 4. Enlace desconocido (bloqueado para publicación automática).
     */
    public function test_unknown_link_structure_is_not_allowed_for_auto_publishing(): void
    {
        $classifier = new LinkSecurityClassifier();

        $unknownLink = 'gopher://rare-protocol.net/resource';
        $res = $classifier->classify($unknownLink);

        $this->assertFalse($res['is_allowed_in_post']);
    }

    /**
     * 5. URL pública con restricciones editoriales (contexto de embargo).
     */
    public function test_public_url_in_embargoed_context_is_classified_as_under_embargo(): void
    {
        $classifier = new LinkSecurityClassifier();

        $youtubeUrl = 'https://www.youtube.com/watch?v=unlistedVideo123';
        $res = $classifier->classify($youtubeUrl, ['is_embargoed' => true]);

        $this->assertSame('under_embargo', $res['classification']);
        $this->assertFalse($res['is_allowed_in_post']);
    }

    /**
     * 6. Redirección sospechosa o hacia una dirección interna (SSRF check).
     */
    public function test_ssrf_and_internal_network_urls_are_flagged_suspicious(): void
    {
        $classifier = new LinkSecurityClassifier();

        // Localhost
        $this->assertSame('suspicious', $classifier->classify('http://127.0.0.1/admin')['classification']);
        $this->assertSame('suspicious', $classifier->classify('http://localhost:8000/info')['classification']);

        // Cloud metadata 169.254.169.254
        $this->assertSame('suspicious', $classifier->classify('http://169.254.169.254/latest/meta-data/')['classification']);

        // IP privada clase C
        $this->assertSame('suspicious', $classifier->classify('http://192.168.1.100/router')['classification']);

        // Esquema peligroso
        $this->assertSame('suspicious', $classifier->classify('javascript:alert(1)')['classification']);
    }

    /**
     * 7. HTML malicioso o con seguimiento purgado por DOMDocument.
     */
    public function test_malicious_and_tracking_html_elements_are_purged(): void
    {
        $sanitizer = new EmailContentSanitizer();

        $maliciousHtml = '
            <p>Noticia legítima de rock.</p>
            <script>alert("hacked");</script>
            <iframe src="http://evil.com/payload"></iframe>
            <p onclick="stealCookies()" style="display:none">Hidden payload</p>
            <a href="javascript:doEvil()">Click</a>
        ';

        $clean = $sanitizer->sanitize($maliciousHtml);

        $this->assertStringNotContainsString('<script', $clean['clean_html']);
        $this->assertStringNotContainsString('<iframe', $clean['clean_html']);
        $this->assertStringNotContainsString('onclick', $clean['clean_html']);
        $this->assertStringNotContainsString('display:none', $clean['clean_html']);
        $this->assertStringNotContainsString('javascript:', $clean['clean_html']);
        $this->assertStringContainsString('Noticia legítima de rock.', $clean['clean_html']);
    }

    /**
     * 8. Contraseña incluida en notas para periodistas enmascarada y separada a editorial_notes.
     */
    public function test_credentials_in_press_notes_are_redacted_and_isolated(): void
    {
        $sanitizer = new EmailContentSanitizer();

        $rawEmail = '
            <h1>Nuevo EP de Sasha And The Lunatics</h1>
            <p>La banda estrena single Dreams disponible en todas las plataformas.</p>
            <h3>Notes to editors:</h3>
            <p>Download link: https://haulix.com/promo/sasha</p>
            <p>Password: SecretRock2026</p>
            <p>Contact manager at: booking@sashaband.com</p>
        ';

        $res = $sanitizer->sanitize($rawEmail);

        // Notas para editores aisladas
        $this->assertNotNull($res['editorial_notes']);
        $this->assertStringContainsString('booking@sashaband.com', $res['editorial_notes']);

        // Contenido público no contiene las notas internas
        $this->assertStringNotContainsString('booking@sashaband.com', $res['clean_html']);

        // Contraseña enmascarada
        $this->assertStringNotContainsString('SecretRock2026', $res['clean_html']);
        $this->assertStringNotContainsString('SecretRock2026', $res['editorial_notes']);
        $this->assertStringContainsString('[PROTECTED_CREDENTIAL]', $res['editorial_notes']);
        $this->assertGreaterThan(0, $res['redacted_count']);
    }

    /**
     * 9. Embargo activo (post con embargo futuro no puede publicarse, is_safe = false).
     */
    public function test_active_embargo_blocks_post_safety_validation(): void
    {
        $validator = new EmailSafetyValidator();

        $post = Post::create([
            'title' => 'Comunicado de Nueva Gira de Iron Maiden',
            'slug' => 'comunicado-nueva-gira',
            'content' => '<p>Anuncio oficial de gira.</p>',
            'status' => 'draft',
            'is_published' => false,
        ]);

        // Registrar embargo activo hasta mañana
        EmailPostSanitizedMeta::create([
            'post_id'           => $post->id,
            'is_embargo_active' => true,
            'embargo_until'     => now()->addDay(),
            'sender_email'      => 'press@ironmaiden.com',
            'received_at'       => now(),
        ]);

        $res = $validator->validatePost($post);

        $this->assertFalse($res['is_safe']);
        $this->assertTrue($res['has_embargo']);
        $this->assertFalse($validator->isSafeToPublish($post));
    }

    /**
     * 10. Lanzamiento futuro sin embargo (musical_release_date en el futuro permite publicación del anuncio).
     */
    public function test_future_musical_release_date_does_not_block_announcement_publication(): void
    {
        $scheduleManager = new ReleaseScheduleManager();

        $subject = 'Warcry anuncia su nuevo disco para el 25 de noviembre';
        $body = 'El nuevo trabajo estará disponible a la venta el 25 de noviembre en todas las tiendas.';

        $res = $scheduleManager->evaluateSchedule($subject, $body, now());

        $this->assertFalse($res['is_embargo_active']);
        $this->assertNull($res['embargo_until']);
        $this->assertNotNull($res['musical_release_date']);
        $this->assertTrue($res['is_future_release']);
        // Publicación permitida inmediatamente
        $this->assertTrue($res['publish_permitted_at']->isToday() || $res['publish_permitted_at']->isPast());
    }

    /**
     * 11. Rectificación de Rupes Nigra (detecta "Corrección: Por favor ignoren el correo anterior").
     */
    public function test_rectification_email_is_detected_and_correlated(): void
    {
        // Post original previo
        $originalPost = Post::create([
            'title' => 'La Cartografía del Abismo Rupes Nigra Revela Axis Mundi',
            'slug' => 'rupes-nigra-axis-mundi',
            'content' => 'Contenido original con errata',
            'status' => 'draft',
            'is_published' => false,
            'author_email' => 'promo@rupesnigra.com',
        ]);

        $scheduleManager = new ReleaseScheduleManager();
        $rectSubject = 'Corrección: Por favor, ignoren el correo electrónico anterior: La Cartografía del Abismo Rupes Nigra';
        $rectBody = 'Adjuntamos la versión correcta del comunicado.';

        $rect = $scheduleManager->detectRectification($rectSubject, $rectBody, 'promo@rupesnigra.com');

        $this->assertNotNull($rect);
        $this->assertTrue($rect['is_rectification']);
        $this->assertSame($originalPost->id, $rect['target_post']->id);
    }

    /**
     * 12. Corrección de una noticia ya publicada genera incidencia y no despublica a ciegas.
     */
    public function test_correction_of_already_published_post_generates_incident_without_blind_deletion(): void
    {
        $publishedPost = Post::create([
            'title' => 'Noticia Ya Publicada de Festival',
            'slug' => 'noticia-ya-publicada-festival',
            'content' => 'Contenido público',
            'status' => 'published',
            'is_published' => true,
            'published_at' => now()->subHours(2),
            'author_email' => 'press@festival.com',
        ]);

        $scheduleManager = new ReleaseScheduleManager();
        $rect = $scheduleManager->detectRectification(
            'Fe de Erratas: Fechas de Festival',
            'Se corrigieron fechas del comunicado',
            'press@festival.com'
        );

        $this->assertNotNull($rect);
        $this->assertTrue($rect['is_rectification']);
        $this->assertSame($publishedPost->id, $rect['target_post']->id);

        // Comprobar expresamente que el post original NO fue borrado ni despublicado a ciegas
        $publishedPost->refresh();
        $this->assertTrue((bool) $publishedPost->is_published);
        $this->assertSame('published', $publishedPost->status);
    }

    /**
     * 13. Publicación bloqueada desde Job asíncrono si contiene enlaces restringidos.
     */
    public function test_async_job_post_is_screened_by_safety_validator(): void
    {
        $validator = new EmailSafetyValidator();

        $postWithPrivateLink = Post::create([
            'title' => 'Post Asíncrono con Enlace Haulix',
            'slug' => 'post-asincrono-haulix',
            'content' => '<p>Descarga el disco en <a href="https://haulix.com/promo/album">Haulix EPK</a></p>',
            'status' => 'draft',
            'is_published' => false,
        ]);

        $res = $validator->validatePost($postWithPrivateLink);
        $this->assertFalse($res['is_safe']);
        $this->assertStringContainsString('haulix.com', $res['violations'][0]);
    }

    /**
     * 14. Publicación bloqueada desde programador (PublishScheduledPosts bloquea posts con violación de seguridad).
     */
    public function test_publish_scheduled_posts_blocks_unsafe_posts_and_reverts_to_draft(): void
    {
        // Post programado cuyo tiempo venció pero contiene una contraseña expuesta en el texto
        $unsafePost = Post::create([
            'title' => 'Post Programado con Contraseña Filtrada',
            'slug' => 'post-programado-inseguro',
            'content' => '<p>Contenido programado. Password: InsecurePassword123</p>',
            'status' => 'scheduled',
            'is_published' => false,
            'published_at' => now()->subMinute(),
        ]);

        Artisan::call('posts:publish-scheduled');

        $unsafePost->refresh();
        // Debe haber sido bloqueado y revertido a draft:
        $this->assertFalse((bool) $unsafePost->is_published, 'El post inseguro NO debe publicarse.');
        $this->assertSame('draft', $unsafePost->status, 'El post debe ser revertido a draft.');
    }

    /**
     * 15. Aprobación editorial futura que reutilice EmailSafetyValidator arroja excepción en violación de seguridad.
     */
    public function test_future_editorial_approval_throws_exception_on_unsafe_post(): void
    {
        $validator = new EmailSafetyValidator();

        $unsafePost = Post::create([
            'title' => 'Post Inseguro para Bandeja Editorial',
            'slug' => 'post-inseguro-bandeja',
            'content' => '<p>Stream privado en <a href="https://soundcloud.com/band/album/s-secret123">SoundCloud</a></p>',
            'status' => 'draft',
            'is_published' => false,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Publicación bloqueada por seguridad editorial');

        $validator->assertSafeForPublishing($unsafePost);
    }

    /**
     * 16. Conservación de publicaciones manuales legítimas.
     */
    public function test_legitimate_manual_editorial_post_passes_safety_validator(): void
    {
        $validator = new EmailSafetyValidator();

        $manualPost = Post::create([
            'title' => 'Entrevista Exclusiva con Bruce Dickinson',
            'slug' => 'entrevista-bruce-dickinson',
            'content' => '<p>Excelente conversación sobre el nuevo disco.</p><p>Video oficial: <a href="https://www.youtube.com/watch?v=genuineVid">YouTube</a></p>',
            'excerpt' => 'Entrevista en profundidad realizada por el equipo de Seven Rock Radio.',
            'featured_image' => 'https://sevenrockradio.com/catalog/covers/bruce.jpg',
            'status' => 'draft',
            'is_published' => false,
        ]);

        $res = $validator->validatePost($manualPost);

        $this->assertTrue($res['is_safe']);
        $this->assertEmpty($res['violations']);
        $this->assertTrue($validator->isSafeToPublish($manualPost));
    }
}
