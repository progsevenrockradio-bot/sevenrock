<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use App\Models\ThemeSetting;
use App\Models\AiUsageDaily;
use App\Services\AiParserManager;
use App\Services\FileUploadService;
use App\Services\GeminiContentParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Query\WhereQuery;
use Webklex\PHPIMAP\Support\AttachmentCollection;
use Webklex\PHPIMAP\Support\MessageCollection;
use Webklex\PHPIMAP\Support\PaginatedCollection;

class Prompt31DirectNewsAndAiLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.imap.password' => 'fake-password',
            'services.imap.username' => 'sevenrockradio@gmail.com',
            'services.archive_org.access_key' => 'fake-access',
            'services.archive_org.secret_key' => 'fake-secret',
        ]);

        $settings = ThemeSetting::current();
        $settings->fill([
            'email_processing_enabled' => true,
            'email_auto_publish' => true,
            'ai_daily_max_calls' => 30,
            'ai_prefilter_enabled' => true,
            'email_daily_news_limit' => 50,
            'email_daily_posts_limit' => 50,
            'email_daily_releases_limit' => 50,
        ]);
        $settings->save();

        \Illuminate\Support\Facades\Cache::flush();
    }

    protected function fakeImapSingleMessage(string $sender, string $subject, string $body, string $msgId = 'msg-101'): void
    {
        $mockClientManager = \Mockery::mock(ClientManager::class);
        $mockClient = \Mockery::mock(Client::class);
        $mockFolder = \Mockery::mock(Folder::class);
        $mockQuery = \Mockery::mock(WhereQuery::class);
        $mockMessage = \Mockery::mock(Message::class);
        $mockAddress = \Mockery::mock(Address::class);

        $mockAddress->mail = $sender;

        $mockMessage->shouldReceive('getMessageId')->andReturn($msgId);
        $mockMessage->shouldReceive('getSubject')->andReturn($subject);
        $mockMessage->shouldReceive('getFrom')->andReturn(new PaginatedCollection([$mockAddress]));
        $mockMessage->shouldReceive('getAttachments')->andReturn(new AttachmentCollection([]));
        $mockMessage->shouldReceive('getHTMLBody')->andReturn($body);
        $mockMessage->shouldReceive('getTextBody')->andReturn(strip_tags($body));
        $mockMessage->shouldReceive('setFlag')->with('SEEN')->andReturn(true);

        $mockQuery->shouldReceive('unseen')->andReturn($mockQuery);
        $mockQuery->shouldReceive('get')->andReturn(new MessageCollection([$mockMessage]));
        $mockFolder->shouldReceive('query')->andReturn($mockQuery);
        $mockClient->shouldReceive('getFolder')->with('INBOX')->andReturn($mockFolder);
        $mockClient->shouldReceive('connect')->andReturn($mockClient);
        $mockClientManager->shouldReceive('make')->andReturn($mockClient);

        $this->app->instance(ClientManager::class, $mockClientManager);
    }

    /**
     * TEST 1: Correo del agente con una noticia -> se publica y NO se llama a la IA.
     */
    public function test_agent_news_is_published_directly_without_any_ai_calls(): void
    {
        // El mock de AiParserManager y GeminiContentParser deben fallar si se les llama
        $aiMock = \Mockery::mock(AiParserManager::class);
        $aiMock->shouldReceive('hasAnyProvider')->andReturn(true);
        $aiMock->shouldReceive('parse')->never();
        $this->app->instance(AiParserManager::class, $aiMock);

        $geminiMock = \Mockery::mock(GeminiContentParser::class);
        $geminiMock->shouldReceive('parse')->never();
        $this->app->instance(GeminiContentParser::class, $geminiMock);

        $sourceUrl = 'https://rocknews.com/metallica-tour-2027';
        $ogImageUrl = 'https://rocknews.com/covers/metallica.jpg';

        $img = imagecreatetruecolor(500, 400);
        ob_start();
        imagejpeg($img);
        $fakeImgData = str_pad(ob_get_clean(), 15000, 'A');

        Http::fake([
            $sourceUrl => Http::response('<html><head><meta property="og:image" content="' . $ogImageUrl . '"></head></html>', 200),
            $ogImageUrl => Http::response($fakeImgData, 200),
        ]);

        $mockUpload = \Mockery::mock(FileUploadService::class);
        $mockUpload->shouldReceive('uploadRaw')->andReturn([
            'disk' => 'r2',
            'key' => 'posts/covers/metallica-r2.jpg',
            'url' => 'https://media.sevenrockradio.com/posts/covers/metallica-r2.jpg',
        ]);
        $mockUpload->shouldReceive('isB2Configured')->andReturn(true);
        $this->app->instance(FileUploadService::class, $mockUpload);

        $body = "<p>Metallica ha anunciado nuevas fechas para su gira europea en 2027.</p><p>FUENTE: {$sourceUrl}</p>";

        $this->fakeImapSingleMessage('dark.vader.agent@gmail.com', 'Noticia: Metallica anuncia fechas 2027', $body, 'msg-dark-vader-1');

        $exitCode = Artisan::call('emails:process');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('[DARK VADER] Noticia publicada directamente (sin IA)', $output);

        $post = Post::where('title', 'Metallica anuncia fechas 2027')->first();
        $this->assertNotNull($post);
        $this->assertSame('published', $post->status);
        $this->assertTrue((bool) $post->is_published);
        $this->assertSame('https://media.sevenrockradio.com/posts/covers/metallica-r2.jpg', $post->featured_image);
        $this->assertSame($sourceUrl, $post->source_url);
        $rawContent = is_array($post->content) ? json_encode($post->content) : (string) $post->content;
        $this->assertStringNotContainsString('FUENTE:', $rawContent);
    }

    /**
     * TEST 2: Correo del agente con efemérides -> sigue publicándose directo y sin imagen destacada.
     */
    public function test_agent_efemerides_published_directly_without_featured_image(): void
    {
        $aiMock = \Mockery::mock(AiParserManager::class);
        $aiMock->shouldReceive('hasAnyProvider')->andReturn(true);
        $aiMock->shouldReceive('parse')->never();
        $this->app->instance(AiParserManager::class, $aiMock);

        $body = "1. 🎸 1970 - Black Sabbath lanza su segundo álbum Paranoid en Reino Unido.\n2. 🎤 1948 - Nace Robert Plant, cantante de Led Zeppelin.";

        $this->fakeImapSingleMessage('dark.vader.agent@gmail.com', 'Efemérides del Rock - 18 de Septiembre', $body, 'msg-efem-1');

        $exitCode = Artisan::call('emails:process');
        $this->assertSame(0, $exitCode);

        $posts = Post::whereJsonContains('categories', 'Hoy en el Rock')->get();
        $this->assertGreaterThanOrEqual(1, $posts->count());

        foreach ($posts as $p) {
            $this->assertNull($p->featured_image, "Las efemérides no deben tener imagen destacada");
        }
    }

    /**
     * TEST 3: Correo externo con señales musicales -> pasa el pre-filtro y se procesa con IA.
     */
    public function test_external_musical_release_passes_filter_and_calls_ai(): void
    {
        $aiMock = \Mockery::mock(AiParserManager::class);
        $aiMock->shouldReceive('hasAnyProvider')->andReturn(true);
        $aiMock->shouldReceive('parse')->once()->andReturn([
            'type' => 'post',
            'title' => 'Judas Priest estrena nuevo videoclip oficial',
            'content' => '<p>La banda británica ha lanzado su nuevo single con videoclip.</p>',
            'categories' => ['Heavy Metal'],
            'importance' => 4,
        ]);
        $this->app->instance(AiParserManager::class, $aiMock);

        $body = "<p>Judas Priest estrena hoy su nuevo single y videoclip para el tema Panic Attack.</p>";

        $this->fakeImapSingleMessage('promo@centurymedia.com', 'Judas Priest estrena nuevo videoclip', $body, 'msg-ext-promo-1');

        $exitCode = Artisan::call('emails:process');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Consultando a la IA', $output);

        $post = Post::where('title', 'Judas Priest estrena nuevo videoclip oficial')->first();
        $this->assertNotNull($post);
    }

    /**
     * TEST 4: Correo externo tipo factura / newsletter / cobro -> es descartado sin llamar a la IA y sin crear post.
     */
    public function test_external_invoice_or_spam_rejected_by_prefilter_with_zero_ai_calls(): void
    {
        $aiMock = \Mockery::mock(AiParserManager::class);
        $aiMock->shouldReceive('hasAnyProvider')->andReturn(true);
        $aiMock->shouldReceive('parse')->never();
        $this->app->instance(AiParserManager::class, $aiMock);

        $body = "<p>Estimado cliente: Adjuntamos su factura mensual correspondiente al servicio de hosting web. Importe total: 45,00 EUR.</p>";

        $this->fakeImapSingleMessage('billing@hostinger.com', 'Factura de su suscripción del mes de Octubre', $body, 'msg-invoice-1');

        $exitCode = Artisan::call('emails:process');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('[FILTRO DETERMINISTA] Correo externo omitido por falta de relevancia', $output);

        $this->assertDatabaseHas('processed_emails', [
            'message_id' => 'msg-invoice-1',
            'status' => 'skipped_no_relevance',
        ]);

        $this->assertDatabaseMissing('posts', [
            'title' => 'Factura de su suscripción del mes de Octubre',
        ]);
    }

    /**
     * TEST 5: Tope diario de 30 llamadas alcanzado -> la llamada 31 no gasta IA, se crea en draft y avisa una vez al admin.
     */
    public function test_daily_ai_limit_reached_creates_draft_without_ai_call_and_alerts_admin(): void
    {
        Mail::fake();

        // Registrar 30 llamadas hoy para simular que el tope se alcanzó
        for ($i = 0; $i < 30; $i++) {
            AiUsageDaily::recordCall('gemini');
        }

        $aiMock = \Mockery::mock(AiParserManager::class);
        $aiMock->shouldReceive('hasAnyProvider')->andReturn(true);
        $aiMock->shouldReceive('parse')->never();
        $this->app->instance(AiParserManager::class, $aiMock);

        $body = "<p>Gira europea de Opeth confirmada para el mes de noviembre.</p>";

        $this->fakeImapSingleMessage('info@nuclearblast.de', 'Gira de Opeth por Europa', $body, 'msg-limit-test-1');

        $exitCode = Artisan::call('emails:process');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('[TOPE IA] Límite diario de llamadas alcanzado', $output);

        // Se debió crear como borrador (draft), nunca published
        $post = Post::where('source_subject', 'Gira de Opeth por Europa')->first();
        $this->assertNotNull($post);
        $this->assertSame('draft', $post->status);
        $this->assertFalse((bool) $post->is_published);

        // Alerta al administrador enviada y cacheada para evitar saturación
        $this->assertStringContainsString('Alerta de administrador enviada', $output);
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has('admin_alert_sent_ai_daily_limit_reached_' . date('Y-m-d')));
    }

    /**
     * TEST 6: Error 429 de la IA -> se detecta como recuperable y se despacha Job con backoff.
     */
    public function test_recoverable_429_dispatches_retry_job_with_backoff(): void
    {
        Queue::fake();

        $aiMock = \Mockery::mock(AiParserManager::class);
        $aiMock->lastError = 'Google Generative AI error: 429 Resource has been exhausted (rate limit exceeded)';
        $aiMock->shouldReceive('hasAnyProvider')->andReturn(true);
        $aiMock->shouldReceive('parse')->once()->andReturn(null);
        $aiMock->shouldReceive('isRecoverableError')->andReturn(true);
        $this->app->instance(AiParserManager::class, $aiMock);

        $body = "<p>Nuevo single de Iron Maiden disponible en plataformas digitales.</p>";

        $this->fakeImapSingleMessage('press@parlophone.com', 'Nuevo single de Iron Maiden', $body, 'msg-429-test-1');

        $exitCode = Artisan::call('emails:process');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('[IA 429/TEMPORAL] Error recuperable detectado', $output);

        Queue::assertPushed(\App\Jobs\ProcessExternalEmailWithAiJob::class, function ($job) {
            $this->assertSame([300, 900], $job->backoff);
            $this->assertSame(3, $job->tries);
            return $job->emailData['subject'] === 'Nuevo single de Iron Maiden';
        });

        $this->assertDatabaseHas('processed_emails', [
            'message_id' => 'msg-429-test-1',
            'status' => 'pending_retry',
        ]);
    }

    /**
     * TEST 7: Comando artisan ai:usage muestra llamadas, descartes y ahorro estimado.
     */
    public function test_ai_usage_artisan_command_renders_metrics(): void
    {
        AiUsageDaily::recordCall('gemini');
        AiUsageDaily::recordCall('openrouter');
        AiUsageDaily::recordFiltered();
        AiUsageDaily::recordFiltered();

        $exitCode = Artisan::call('ai:usage');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('REPORTE DE USO DE IA', $output);
        $this->assertStringContainsString('Llamadas a la IA', $output);
        $this->assertStringContainsString('Correos descartados por pre-filtro', $output);
        $this->assertStringContainsString('Estimación de gasto evitado', $output);
    }
}
