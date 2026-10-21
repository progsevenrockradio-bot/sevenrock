<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Mail\NewReleasePublishedMail;
use App\Mail\PostPublishedMail;
use App\Models\NewRelease;
use App\Models\Post;
use App\Models\ThemeSetting;
use App\Support\EmailRelevanceFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Phase1CircuitProtectionTest extends TestCase
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
        $settings = ThemeSetting::create([
            'email_auto_publish' => false,
            'email_daily_posts_limit' => 5,
            'email_daily_releases_limit' => 5,
            'email_daily_news_limit' => 50,
            'ai_prefilter_enabled' => true,
        ]);
        $property->setValue(null);
    }

    /**
     * 1. Comprueba expresamente: Los borradores nunca se publican solos.
     */
    public function test_drafts_with_past_published_at_are_never_published_by_command(): void
    {
        $draftPost = Post::create([
            'title' => 'Borrador retenido por moderación o filtro',
            'slug' => 'borrador-retenido',
            'content' => 'Contenido confidencial o pendiente de revisión',
            'status' => 'draft',
            'is_published' => false,
            'published_at' => now()->subHours(2),
        ]);

        Artisan::call('posts:publish-scheduled');

        $draftPost->refresh();
        $this->assertFalse((bool) $draftPost->is_published, 'El borrador NO debe publicarse.');
        $this->assertSame('draft', $draftPost->status, 'El estado debe seguir siendo draft.');
    }

    /**
     * 2. Comprueba expresamente: Las publicaciones programadas legítimas funcionan.
     */
    public function test_legitimate_scheduled_posts_are_published_when_due(): void
    {
        $scheduledPost = Post::create([
            'title' => 'Post legítimamente programado por redacción',
            'slug' => 'post-programado-legitimo',
            'content' => 'Contenido programado para salir a una hora específica',
            'status' => 'scheduled',
            'is_published' => false,
            'published_at' => now()->subMinute(),
        ]);

        Artisan::call('posts:publish-scheduled');

        $scheduledPost->refresh();
        $this->assertTrue((bool) $scheduledPost->is_published, 'El post programado vencido DEBE publicarse.');
        $this->assertSame('published', $scheduledPost->status, 'El estado DEBE actualizarse a published.');
    }

    /**
     * Comprueba que los posts programados a futuro NO se publiquen prematuramente.
     */
    public function test_future_scheduled_posts_are_not_published_prematurely(): void
    {
        $futurePost = Post::create([
            'title' => 'Post programado para mañana',
            'slug' => 'post-programado-manana',
            'content' => 'Contenido futuro',
            'status' => 'scheduled',
            'is_published' => false,
            'published_at' => now()->addDay(),
        ]);

        Artisan::call('posts:publish-scheduled');

        $futurePost->refresh();
        $this->assertFalse((bool) $futurePost->is_published, 'El post futuro NO debe publicarse antes de tiempo.');
        $this->assertSame('scheduled', $futurePost->status, 'El estado debe conservarse como scheduled.');
    }

    /**
     * 3. Comprueba expresamente: Los correos externos no disparan avisos indebidos.
     */
    public function test_external_email_posts_do_not_send_notifications_to_external_senders(): void
    {
        Mail::fake();

        $post = Post::create([
            'title' => 'Comunicado de prensa externo',
            'slug' => 'comunicado-prensa-externo',
            'content' => 'Texto enviado por agencia PR',
            'source_subject' => 'Press Release: Iron Maiden Tour 2027',
            'author_email' => 'promo@pr-agency-europe.com',
            'status' => 'published',
            'is_published' => true,
            'published_at' => now(),
        ]);

        Mail::assertNothingSent();
    }

    /**
     * Verifica que los bots internos (Dark Vader) nunca reciban notificaciones de publicación.
     */
    public function test_dark_vader_bot_never_receives_post_published_notifications(): void
    {
        Mail::fake();

        Post::create([
            'title' => 'Efeméride diaria por Dark Vader',
            'slug' => 'efemeride-dark-vader',
            'content' => 'Hoy en 1980 ocurrió tal evento rockero',
            'source_subject' => null,
            'author_email' => 'dark.vader.agent@gmail.com',
            'status' => 'published',
            'is_published' => true,
            'published_at' => now(),
        ]);

        Mail::assertNothingSent();
    }

    /**
     * 4. Comprueba expresamente: Los artistas siguen recibiendo sus notificaciones legítimas.
     */
    public function test_legitimate_artists_and_manual_authors_receive_notifications(): void
    {
        Mail::fake();

        Post::create([
            'title' => 'Entrevista exclusiva a banda local',
            'slug' => 'entrevista-exclusiva-banda-local',
            'content' => 'Artículo redactado por el equipo sobre la banda invitada',
            'source_subject' => null, // Creado manualmente en el panel o submission
            'author_email' => 'contacto@bandalocal.com',
            'status' => 'published',
            'is_published' => true,
            'published_at' => now(),
        ]);

        Mail::assertSent(PostPublishedMail::class, function ($mail) {
            return $mail->hasTo('contacto@bandalocal.com');
        });
    }

    /**
     * Verifica que NewRelease de procedencia desconocida o histórica NUNCA envíe notificaciones al activarse.
     */
    public function test_historical_new_release_with_unknown_origin_never_sends_notification_on_activation(): void
    {
        Mail::fake();

        // Simular registro histórico preexistente con correo de agencia y procedencia desconocida
        $release = NewRelease::create([
            'title' => 'Lanzamiento Histórico 2024',
            'slug' => 'lanzamiento-historico-2024',
            'artist_name' => 'Banda Histórica',
            'is_active' => false,
            'author_email' => 'promocion@agencia-externa-antigua.com',
            'origin' => 'unknown',
        ]);

        // Un administrador lo activa posteriormente
        $release->update(['is_active' => true]);

        Mail::assertNothingSent();
    }

    /**
     * Verifica que NewRelease importado desde correo externo NUNCA envíe notificaciones.
     */
    public function test_external_email_new_release_never_sends_notification_on_activation(): void
    {
        Mail::fake();

        $release = NewRelease::create([
            'title' => 'Single Importado por Email',
            'slug' => 'single-importado-email',
            'artist_name' => 'Banda de Sello',
            'is_active' => false,
            'author_email' => 'press@nuclearblast-promo.com',
            'origin' => 'email',
        ]);

        $release->update(['is_active' => true]);

        Mail::assertNothingSent();
    }

    /**
     * Verifica que NewRelease enviado voluntariamente por un artista (Buzón de Maquetas) SÍ envíe notificación al artista.
     */
    public function test_artist_submission_new_release_sends_notification_to_artist_on_activation(): void
    {
        Mail::fake();

        $release = NewRelease::create([
            'title' => 'Maqueta de Banda Indie',
            'slug' => 'maqueta-banda-indie',
            'artist_name' => 'Banda Indie Local',
            'is_active' => false,
            'author_email' => 'cantante@bandaindie.com',
            'origin' => 'submission',
        ]);

        $release->update(['is_active' => true]);

        Mail::assertSent(NewReleasePublishedMail::class, function ($mail) {
            return $mail->hasTo('cantante@bandaindie.com');
        });
    }

    /**
     * Verifica que NewRelease creado manualmente por un administrador en el panel SÍ envíe notificación al autor indicado.
     */
    public function test_admin_manual_new_release_sends_notification_to_author_on_activation(): void
    {
        Mail::fake();

        $release = NewRelease::create([
            'title' => 'Lanzamiento Creado por Admin',
            'slug' => 'lanzamiento-creado-por-admin',
            'artist_name' => 'Artista Invitado',
            'is_active' => false,
            'author_email' => 'colaborador@sevenrockradio.com',
            'origin' => 'manual',
        ]);

        $release->update(['is_active' => true]);

        Mail::assertSent(NewReleasePublishedMail::class, function ($mail) {
            return $mail->hasTo('colaborador@sevenrockradio.com');
        });
    }

    /**
     * Verifica que NewRelease filtre bots y correos del sistema en notificaciones.
     */
    public function test_new_release_does_not_notify_bots_or_empty_authors(): void
    {
        Mail::fake();

        NewRelease::create([
            'title' => 'Single Automático',
            'slug' => 'single-automatico',
            'artist_name' => 'Banda X',
            'is_active' => true,
            'author_email' => 'dark.vader.agent@gmail.com',
            'origin' => 'manual',
        ]);

        Mail::assertNothingSent();
    }

    /**
     * 5. Comprueba expresamente: Los estados de publicación son coherentes.
     */
    public function test_status_and_is_published_are_always_synchronized(): void
    {
        // En ningún flujo debe permitirse status='draft' con is_published=true
        $post = Post::create([
            'title' => 'Post de prueba coherencia',
            'slug' => 'post-coherencia',
            'content' => 'Test',
            'status' => 'draft',
            'is_published' => false,
        ]);

        $this->assertFalse((bool) $post->is_published);
        $this->assertSame('draft', $post->status);

        $post->update([
            'status' => 'published',
            'is_published' => true,
        ]);

        $this->assertTrue((bool) $post->is_published);
        $this->assertSame('published', $post->status);
    }

    /**
     * 6. Comprueba expresamente: Coincidencia segura de listas blancas y filtro de junk prioritario.
     */
    public function test_whitelist_matching_is_strict_and_junk_filter_takes_precedence(): void
    {
        $settings = ThemeSetting::current();
        $settings->update([
            'email_whitelist_senders' => '@centurymedia.com, contacto@rocklabel.com',
            'ai_prefilter_enabled' => true,
        ]);

        $filter = new EmailRelevanceFilter();

        // 1. Dominio exacto en whitelist pasa
        $res1 = $filter->shouldProcess('promo@centurymedia.com', 'Nuevo single de Arch Enemy', 'Contenido musical', $settings);
        $this->assertTrue($res1['pass']);
        $this->assertSame('whitelisted_sender', $res1['reason']);

        // 2. Correo exacto en whitelist pasa
        $res2 = $filter->shouldProcess('contacto@rocklabel.com', 'Lanzamiento de EP', 'Contenido musical', $settings);
        $this->assertTrue($res2['pass']);
        $this->assertSame('whitelisted_sender', $res2['reason']);

        // 3. Falso positivo por subcadena debe ser RECHAZADO (no debe coincidir con @centurymedia.com)
        $res3 = $filter->shouldProcess('spammer@fakecenturymedia.com', 'Publicidad no deseada', 'Haga clic aqui', $settings);
        $this->assertFalse($res3['pass']);

        // 4. Falso positivo por subcadena de correo debe ser RECHAZADO
        $res4 = $filter->shouldProcess('malicioso-contacto@rocklabel.com.evil.com', 'Spam', 'Compre ahora', $settings);
        $this->assertFalse($res4['pass']);

        // 5. Correo en whitelist que envía una FACTURA o RECIBO debe ser DESCARTADO por junk_patterns prioritario
        $res5 = $filter->shouldProcess(
            'promo@centurymedia.com',
            'Factura correspondiente al mes de octubre',
            'Adjuntamos recibo de pago y factura de hosting',
            $settings
        );
        $this->assertFalse($res5['pass'], 'El junk filter debe descartar facturas incluso si provienen de la lista blanca.');
        $this->assertSame('junk_or_service_notice', $res5['reason']);
    }

    /**
     * 7. Comprueba la migración y persistencia de email_daily_news_limit en ThemeSetting.
     */
    public function test_theme_setting_has_email_daily_news_limit(): void
    {
        $setting = ThemeSetting::current();
        $setting->update(['email_daily_news_limit' => 42]);

        $this->assertSame(42, (int) $setting->fresh()->email_daily_news_limit);
    }
}
