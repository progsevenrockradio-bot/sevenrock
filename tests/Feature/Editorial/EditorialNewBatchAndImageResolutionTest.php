<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Models\EmailPostSanitizedMeta;
use App\Models\Post;
use App\Models\PostTaxonomy;
use App\Models\ThemeSetting;
use App\Services\EditorialBatchProcessor;
use App\Services\EditorialImageService;
use App\Services\EmailSecurityVerifier;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

class EditorialNewBatchAndImageResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = ThemeSetting::current();
        $settings->fill([
            'email_processing_enabled' => true,
            'email_auto_publish'       => false,
        ]);
        $settings->save();

        config([
            'services.imap.password' => 'secret-test',
            'services.imap.username' => 'sevenrockradio@gmail.com',
        ]);

        $mockVerifier = \Mockery::mock(EmailSecurityVerifier::class);
        $mockVerifier->shouldReceive('isDarkVaderLegitimate')->andReturn(true);
        $mockVerifier->shouldReceive('verify')->andReturn(['is_spoofing' => false, 'spf' => 'pass', 'dkim' => 'pass', 'dmarc' => 'pass']);
        $this->app->instance(EmailSecurityVerifier::class, $mockVerifier);

        PostTaxonomy::firstOrCreate(['name' => 'Noticias Rock', 'type' => 'category'], ['slug' => 'noticias-rock']);
        PostTaxonomy::firstOrCreate(['name' => 'Hoy en el Rock', 'type' => 'category'], ['slug' => 'hoy-en-el-rock']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_editorial_image_service_blocks_ssrf_and_private_ips(): void
    {
        $service = app(EditorialImageService::class);

        $res1 = $service->validateSafeRemoteUrl('http://127.0.0.1/malicious.jpg');
        $this->assertFalse($res1['valid']);
        $this->assertStringContainsString('loopback', $res1['error']);

        $res2 = $service->validateSafeRemoteUrl('http://localhost:8000/test.jpg');
        $this->assertFalse($res2['valid']);
        $this->assertStringContainsString('loopback', $res2['error']);

        $res3 = $service->validateSafeRemoteUrl('ftp://example.com/file.jpg');
        $this->assertFalse($res3['valid']);
        $this->assertStringContainsString('Esquema no permitido', $res3['error']);

        $res4 = $service->validateSafeRemoteUrl('https://upload.wikimedia.org/wikipedia/commons/test.jpg');
        $this->assertTrue($res4['valid']);
    }

    public function test_editorial_image_service_downloads_and_optimizes_image_with_gd(): void
    {
        $service = app(EditorialImageService::class);

        // Generar imagen JPEG válida de prueba (>4KB)
        $img = imagecreatetruecolor(1600, 900);
        $bgColor = imagecolorallocate($img, 195, 39, 32);
        imagefilledrectangle($img, 0, 0, 1600, 900, $bgColor);
        ob_start();
        imagejpeg($img, null, 90);
        $imgData = ob_get_clean();
        imagedestroy($img);

        // Simular respuesta HTTP para la descarga
        Http::fake([
            'https://upload.wikimedia.org/wikipedia/commons/test-rock-photo.jpg' => Http::response($imgData, 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $result = $service->resolveAndStore(
            'https://upload.wikimedia.org/wikipedia/commons/test-rock-photo.jpg',
            'DV-TEST-E01',
            'test-band-announcement',
            'Foto: Fotógrafo Rock / Commons (CC BY 4.0)'
        );

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['path']);
        $this->assertStringStartsWith('storage/posts/covers/post-dv-e01-test-band-announcement', $result['path']);
        $this->assertSame('Foto: Fotógrafo Rock / Commons (CC BY 4.0)', $result['credit']);

        // Verificar existencia en disco y dimensiones escaladas (max 1200px)
        $storagePath = storage_path('app/public/' . str_replace('storage/', '', $result['path']));
        $this->assertFileExists($storagePath);
        $dims = getimagesize($storagePath);
        $this->assertLessThanOrEqual(1200, $dims[0]);
    }

    public function test_editorial_image_service_resolves_wikimedia_commons_api_file_page(): void
    {
        $service = app(EditorialImageService::class);

        $img = imagecreatetruecolor(800, 600);
        $bgColor = imagecolorallocate($img, 120, 40, 40);
        imagefilledrectangle($img, 0, 0, 800, 600, $bgColor);
        for ($i = 0; $i < 100; $i++) {
            $col = imagecolorallocate($img, ($i * 5) % 255, ($i * 9) % 255, 200);
            imageline($img, 0, $i * 6, 800, 600 - ($i * 6), $col);
        }
        ob_start();
        imagejpeg($img, null, 90);
        $imgData = ob_get_clean();
        imagedestroy($img);

        Http::fake([
            'https://commons.wikimedia.org/w/api.php*' => Http::response([
                'query' => [
                    'pages' => [
                        '12345' => [
                            'pageid' => 12345,
                            'title'  => 'File:Ghost_Live_2025.jpg',
                            'imageinfo' => [
                                [
                                    'url'  => 'https://upload.wikimedia.org/wikipedia/commons/direct-ghost-live-2025.jpg',
                                    'size' => strlen($imgData),
                                    'extmetadata' => [
                                        'Artist' => ['value' => '<a href="#">Jane Rock Photo</a>'],
                                        'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
            'https://upload.wikimedia.org/wikipedia/commons/direct-ghost-live-2025.jpg' => Http::response($imgData, 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $wikiPageUrl = 'https://commons.wikimedia.org/wiki/File:Ghost_Live_2025.jpg';
        $this->assertTrue($service->isWikimediaPageUrl($wikiPageUrl));

        $result = $service->resolveAndStore($wikiPageUrl, 'DV-WIKI-E01', 'ghost-live-tour-2025', null);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['path']);
        $this->assertSame('Foto: Jane Rock Photo / Wikimedia Commons (CC BY-SA 4.0)', $result['credit']);
        $this->assertStringStartsWith('storage/posts/covers/post-dv-e01-ghost-live-tour-2025', $result['path']);
    }

    public function test_controlled_clock_schedule_arrival_at_0307_and_processing_at_0315_with_idempotency(): void
    {
        // 1. Simular reloj a las 03:00 Europe/Madrid
        Carbon::setTestNow(Carbon::parse('2026-11-11 03:00:00', 'Europe/Madrid'));

        $messagesToReturn = [];

        $mockClientManager = \Mockery::mock(ClientManager::class);
        $mockClient = \Mockery::mock(Client::class);
        $mockFolder = \Mockery::mock(Folder::class);
        $mockQuery = \Mockery::mock(WhereQuery::class);

        $mockQuery->shouldReceive('unseen')->andReturn($mockQuery);
        $mockQuery->shouldReceive('get')->andReturnUsing(function () use (&$messagesToReturn) {
            return new MessageCollection($messagesToReturn);
        });
        $mockFolder->shouldReceive('query')->andReturn($mockQuery);
        $mockClient->shouldReceive('getFolder')->with('INBOX')->andReturn($mockFolder);
        $mockClient->shouldReceive('connect')->andReturn($mockClient);
        $mockClientManager->shouldReceive('make')->andReturn($mockClient);

        $this->app->instance(ClientManager::class, $mockClientManager);

        $exitCode0300 = \Illuminate\Support\Facades\Artisan::call('emails:process', ['--only-dv1' => true]);
        $this->assertSame(0, $exitCode0300);
        $this->assertSame(0, Post::count());

        // 2. A las 03:07 llega el correo del nuevo lote DV-20261111-01
        // Un elemento tiene imagen válida remota, otro tiene imagen que falla (404)
        $newBatchPayload = <<<'MARKDOWN'
FORMATO: DV-1
LOTE_ID: DV-20261111-01
FECHA_EDITORIAL: 2026-11-11
ZONA_HORARIA: Europe/Madrid
TOTAL_ELEMENTOS: 2
MODO: BORRADOR

=== INICIO ELEMENTO ===
ID: DV-20261111-01-E01
TIPO: NOTICIA
ARTISTA_BANDA: Ghost
TITULO: Ghost confirma grabaciones de su nuevo album de estudio para 2027
FECHA_EDITORIAL: 2026-11-11
TEXTO:
La banda sueca Ghost ha confirmado oficialmente que entrara al estudio de grabacion a principios del proximo ano para dar forma a su sexto larga duracion.

Tobias Forge explico que el concepto musical explorara terrenos mas oscuros con una produccion sinfonica de gran envergadura.
FUENTES:
- https://blabbermouth.net/news/ghost-new-album-2027
IMAGEN_URL: https://upload.wikimedia.org/wikipedia/commons/ghost-photo.jpg
IMAGEN_CREDITO: Foto: Rock Magazine / Wikimedia Commons (CC BY-SA 4.0)
IMAGEN_ALT: Tobias Forge de Ghost en directo
REVISION_REQUERIDA: NO
=== FIN ELEMENTO ===

=== INICIO ELEMENTO ===
ID: DV-20261111-01-E02
TIPO: EFEMERIDE
ARTISTA_BANDA: AC/DC
TITULO: Nace Angus Young en Glasgow y redefine el sonido de la guitarra rock
FECHA_EDITORIAL: 2026-11-11
TEXTO:
Un dia como hoy en Glasgow nace Angus Young, legendario guitarrista lider y cofundador de AC/DC.

Su estilo inconfundible con la Gibson SG y su uniforme colegial se convirtieron en un icono eterno del rock and roll.
FUENTES:
- https://es.wikipedia.org/wiki/Angus_Young
IMAGEN_URL: https://example.com/broken-image-404.jpg
IMAGEN_CREDITO: Foto no disponible
IMAGEN_ALT: Angus Young con su guitarra
REVISION_REQUERIDA: NO
=== FIN ELEMENTO ===
MARKDOWN;

        // Mock Http para descargas
        $img = imagecreatetruecolor(1000, 800);
        $bgColor = imagecolorallocate($img, 100, 100, 100);
        imagefilledrectangle($img, 0, 0, 1000, 800, $bgColor);
        for ($i = 0; $i < 150; $i++) {
            $col = imagecolorallocate($img, ($i * 7) % 255, ($i * 13) % 255, ($i * 19) % 255);
            imageline($img, 0, $i * 5, 1000, 800 - ($i * 5), $col);
        }
        ob_start();
        imagejpeg($img, null, 95);
        $validJpgData = ob_get_clean();
        imagedestroy($img);

        Http::fake([
            'https://upload.wikimedia.org/wikipedia/commons/ghost-photo.jpg' => Http::response($validJpgData, 200, ['Content-Type' => 'image/jpeg']),
            'https://example.com/broken-image-404.jpg' => Http::response('Not Found', 404),
        ]);

        // 3. Simular reloj a las 03:15 Europe/Madrid (segunda consulta periódica)
        Carbon::setTestNow(Carbon::parse('2026-11-11 03:15:00', 'Europe/Madrid'));

        $mockMsgNew = \Mockery::mock(Message::class);
        $mockAddressDV = \Mockery::mock(Address::class);
        $mockAddressDV->mail = 'dark.vader.agent@gmail.com';

        $mockHeaderNew = \Mockery::mock(\Webklex\PHPIMAP\Header::class);
        $mockHeaderNew->raw = "Authentication-Results: mx.google.com; dkim=pass header.i=@gmail.com; spf=pass\r\nFrom: dark.vader.agent@gmail.com";
        $mockMsgNew->shouldReceive('getHeader')->andReturn($mockHeaderNew);
        $mockMsgNew->shouldReceive('getMessageId')->andReturn('<dv-batch-20261111-01@gmail.com>');
        $mockMsgNew->shouldReceive('getSubject')->andReturn('[DARTH-VADER][DV-1][2026-11-11][DV-20261111-01]');
        $mockMsgNew->shouldReceive('getFrom')->andReturn(new PaginatedCollection([$mockAddressDV]));
        $mockMsgNew->shouldReceive('getAttachments')->andReturn(new AttachmentCollection([]));
        $mockMsgNew->shouldReceive('getHTMLBody')->andReturn('');
        $mockMsgNew->shouldReceive('getTextBody')->andReturn($newBatchPayload);
        $mockMsgNew->shouldReceive('setFlag')->with('SEEN')->andReturn(true);

        $messagesToReturn = [$mockMsgNew];

        $exitCode0315 = \Illuminate\Support\Facades\Artisan::call('emails:process', ['--only-dv1' => true]);
        $this->assertSame(0, $exitCode0315);

        // Verificaciones del lote nuevo:
        // Se crearon exactamente 2 borradores (cero publicados)
        $this->assertSame(2, Post::count());
        $this->assertSame(2, Post::where('status', 'draft')->where('is_published', 0)->count());

        // Elemento 1 (Ghost): imagen descargada y optimizada con éxito
        $ghostPost = Post::where('slug', 'ghost-confirma-grabaciones-de-su-nuevo-album-de-estudio-para-2027')->first();
        $this->assertNotNull($ghostPost, 'ghostPost no fue encontrado por slug');
        $ghostImagePath = $ghostPost->featured_image ?: $ghostPost->featured_image_path;
        $this->assertNotNull($ghostImagePath, 'featured_image de ghostPost es null');
        $this->assertStringStartsWith('storage/posts/covers/post-dv-e01-ghost', $ghostImagePath);
        $this->assertSame('draft', $ghostPost->status);
        $this->assertFalse((bool) $ghostPost->is_published);

        // Elemento 2 (Angus Young): fallo 404 de imagen no bloquea el lote; se crea como borrador con pendiente visible
        $angusPost = Post::where('slug', 'nace-angus-young-en-glasgow-y-redefine-el-sonido-de-la-guitarra-rock')->first();
        $this->assertNotNull($angusPost, 'angusPost no fue encontrado por slug');
        $angusImagePath = $angusPost->featured_image ?: $angusPost->featured_image_path;
        $this->assertNull($angusImagePath);
        $this->assertSame('draft', $angusPost->status);
        $this->assertFalse((bool) $angusPost->is_published);

        $angusMeta = EmailPostSanitizedMeta::where('post_id', $angusPost->id)->first();
        $this->assertNotNull($angusMeta, 'angusMeta no fue encontrado');
        $this->assertSame('needs_review', $angusMeta->security_status);
        $this->assertStringContainsString('IMAGEN_PENDIENTE', (string) $angusMeta->editorial_notes);
        $this->assertStringContainsString('HTTP 404', (string) $angusMeta->editorial_notes);
        $this->assertStringContainsString('IMAGEN_PENDIENTE', (string) $angusMeta->incident_reason);

        // 4. Simular reloj a las 03:30 Europe/Madrid (siguiente consulta periódica)
        // El correo ya fue procesado y además llega un correo promocional espurio
        Carbon::setTestNow(Carbon::parse('2026-11-11 03:30:00', 'Europe/Madrid'));

        $mockMsgPromo = \Mockery::mock(Message::class);
        $mockAddrPromo = \Mockery::mock(Address::class);
        $mockAddrPromo->mail = 'recordlabel@promo.com';
        $mockHeaderPromo = \Mockery::mock(\Webklex\PHPIMAP\Header::class);
        $mockHeaderPromo->raw = "From: recordlabel@promo.com";
        $mockMsgPromo->shouldReceive('getHeader')->andReturn($mockHeaderPromo);
        $mockMsgPromo->shouldReceive('getMessageId')->andReturn('<promo-999@promo.com>');
        $mockMsgPromo->shouldReceive('getSubject')->andReturn('Boletin discografico ajeno');
        $mockMsgPromo->shouldReceive('getFrom')->andReturn(new PaginatedCollection([$mockAddrPromo]));
        $mockMsgPromo->shouldReceive('getAttachments')->andReturn(new AttachmentCollection([]));
        $mockMsgPromo->shouldReceive('getHTMLBody')->andReturn('<p>Nuevo disco...</p>');
        $mockMsgPromo->shouldReceive('getTextBody')->andReturn('Nuevo disco...');
        $mockMsgPromo->shouldReceive('setFlag')->with('SEEN')->andReturn(true);

        // Simulamos que el buzón tiene el correo ya procesado de DV y el promocional
        $messagesToReturn = [$mockMsgNew, $mockMsgPromo];

        $exitCode0330 = \Illuminate\Support\Facades\Artisan::call('emails:process', ['--only-dv1' => true]);
        $this->assertSame(0, $exitCode0330);

        // Total de posts permanece exactamente en 2: cero duplicados, cero publicaciones no autorizadas
        $this->assertSame(2, Post::count());
        $this->assertDatabaseMissing('processed_emails', ['message_id' => '<promo-999@promo.com>']);
    }
}
