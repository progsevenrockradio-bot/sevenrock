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

    public function test_editorial_image_service_blocks_literal_private_and_loopback_ips(): void
    {
        $service = app(EditorialImageService::class);

        // IPv4 loopback literal
        $resLoopback = $service->validateSafeRemoteUrl('http://127.0.0.1/malicious.jpg');
        $this->assertFalse($resLoopback['valid']);
        $this->assertStringContainsString('privada o reservada', $resLoopback['error']);

        // IPv4 privada 192.168.x literal
        $resPriv192 = $service->validateSafeRemoteUrl('http://192.168.1.50/pic.jpg');
        $this->assertFalse($resPriv192['valid']);
        $this->assertStringContainsString('privada o reservada', $resPriv192['error']);

        // IPv4 privada 10.x literal
        $resPriv10 = $service->validateSafeRemoteUrl('http://10.0.0.1/pic.jpg');
        $this->assertFalse($resPriv10['valid']);
        $this->assertStringContainsString('privada o reservada', $resPriv10['error']);

        // IPv4 link-local (cloud metadata)
        $resMetadata = $service->validateSafeRemoteUrl('http://169.254.169.254/latest/meta-data/');
        $this->assertFalse($resMetadata['valid']);
        $this->assertStringContainsString('privada o reservada', $resMetadata['error']);

        // IPv6 loopback literal [::1]
        $resIpv6Loopback = $service->validateSafeRemoteUrl('http://[::1]/pic.jpg');
        $this->assertFalse($resIpv6Loopback['valid']);
        $this->assertStringContainsString('privada o reservada', $resIpv6Loopback['error']);

        // IPv6 ULA literal [fc00::1]
        $resIpv6Ula = $service->validateSafeRemoteUrl('http://[fc00::1]/pic.jpg');
        $this->assertFalse($resIpv6Ula['valid']);
        $this->assertStringContainsString('privada o reservada', $resIpv6Ula['error']);

        // Credenciales incrustadas
        $resCreds = $service->validateSafeRemoteUrl('http://admin:secret@upload.wikimedia.org/pic.jpg');
        $this->assertFalse($resCreds['valid']);
        $this->assertStringContainsString('Credenciales incrustadas', $resCreds['error']);

        // Fallo de resolución DNS
        $resDnsFail = $service->validateSafeRemoteUrl('http://dominio-totalmente-inexistente-xyz987654321.org/pic.jpg');
        $this->assertFalse($resDnsFail['valid']);
        $this->assertStringContainsString('Fallo de resolución DNS', $resDnsFail['error']);

        // Esquema prohibido
        $resFtp = $service->validateSafeRemoteUrl('ftp://example.com/file.jpg');
        $this->assertFalse($resFtp['valid']);
        $this->assertStringContainsString('Esquema no permitido', $resFtp['error']);
    }

    public function test_editorial_image_service_blocks_redirection_to_internal_ip(): void
    {
        $service = app(EditorialImageService::class);

        // Simulamos que la URL pública inicial redirige con 302 a una IP privada interna
        Http::fake([
            'https://public-host.com/cover.jpg' => Http::response('', 302, [
                'Location' => 'http://127.0.0.1/secret-internal.jpg',
            ]),
        ]);

        $result = $service->downloadToTempFile('https://public-host.com/cover.jpg');

        $this->assertFalse($result['success']);
        $this->assertNull($result['temp_path']);
        $this->assertStringContainsString('Seguridad en salto HTTP (1)', $result['error']);
        $this->assertStringContainsString('privada o reservada rechazada', $result['error']);
    }

    public function test_editorial_image_service_aborts_excessive_download_streaming(): void
    {
        $service = app(EditorialImageService::class);

        // Simulamos un payload que excede el límite de 12 MB (12582912 bytes)
        $largePayload = str_repeat('A', 13 * 1024 * 1024); // 13 MB

        Http::fake([
            'https://upload.wikimedia.org/wikipedia/commons/huge-image.jpg' => Http::response($largePayload, 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $result = $service->downloadToTempFile('https://upload.wikimedia.org/wikipedia/commons/huge-image.jpg');

        $this->assertFalse($result['success']);
        $this->assertNull($result['temp_path']);
        $this->assertStringContainsString('Descarga abortada: tamaño excede el límite de 12 MB', $result['error']);
    }

    public function test_editorial_image_service_rejects_missing_or_incompatible_wikimedia_licenses(): void
    {
        $service = app(EditorialImageService::class);

        // 1. Licencia con restricción No Comercial (CC BY-NC 4.0)
        $metaNc = [
            'Artist' => ['value' => 'Photographer Name'],
            'LicenseShortName' => ['value' => 'CC BY-NC 4.0'],
            'LicenseUrl' => ['value' => 'https://creativecommons.org/licenses/by-nc/4.0'],
        ];
        $resNc = $service->validateCommonsLicense($metaNc, 'https://commons.wikimedia.org/wiki/File:nc.jpg');
        $this->assertFalse($resNc['valid']);
        $this->assertStringContainsString('No Comercial', $resNc['error']);

        // 2. Licencia con restricción Sin Derivadas (CC BY-ND 4.0)
        $metaNd = [
            'Artist' => ['value' => 'Photographer Name'],
            'LicenseShortName' => ['value' => 'CC BY-ND 4.0'],
            'LicenseUrl' => ['value' => 'https://creativecommons.org/licenses/by-nd/4.0'],
        ];
        $resNd = $service->validateCommonsLicense($metaNd, 'https://commons.wikimedia.org/wiki/File:nd.jpg');
        $this->assertFalse($resNd['valid']);
        $this->assertStringContainsString('Sin Derivadas', $resNd['error']);

        // 3. Licencia ausente
        $metaEmpty = [
            'Artist' => ['value' => 'Photographer Name'],
            'LicenseShortName' => ['value' => ''],
        ];
        $resEmpty = $service->validateCommonsLicense($metaEmpty, 'https://commons.wikimedia.org/wiki/File:empty.jpg');
        $this->assertFalse($resEmpty['valid']);
        $this->assertStringContainsString('Licencia ausente', $resEmpty['error']);

        // 4. Autor no acreditado con licencia CC
        $metaNoAuthor = [
            'Artist' => ['value' => 'Desconocido'],
            'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
            'LicenseUrl' => ['value' => 'https://creativecommons.org/licenses/by-sa/4.0'],
        ];
        $resNoAuthor = $service->validateCommonsLicense($metaNoAuthor, 'https://commons.wikimedia.org/wiki/File:no-author.jpg');
        $this->assertFalse($resNoAuthor['valid']);
        $this->assertStringContainsString('Autor no acreditado', $resNoAuthor['error']);

        // 5. Licencia compatible (CC BY-SA 4.0) con autor
        $metaValid = [
            'Artist' => ['value' => 'John Rock Photographer'],
            'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
            'LicenseUrl' => ['value' => 'https://creativecommons.org/licenses/by-sa/4.0'],
        ];
        $resValid = $service->validateCommonsLicense($metaValid, 'https://commons.wikimedia.org/wiki/File:valid.jpg', 'Crédito Adicional');
        $this->assertTrue($resValid['valid']);
        $this->assertStringContainsString('John Rock Photographer', $resValid['credit']);
        $this->assertStringContainsString('CC BY-SA 4.0', $resValid['credit']);
        $this->assertStringContainsString('Crédito declarado', $resValid['credit']);
    }

    public function test_reimporting_published_article_makes_zero_http_requests_and_zero_file_writes(): void
    {
        // 1. Crear un post y su meta en estado publicado
        $post = Post::create([
            'title'        => 'Articulo Ya Publicado',
            'slug'         => 'articulo-ya-publicado',
            'status'       => 'published',
            'is_published' => 1,
            'featured_image' => 'storage/posts/covers/original-photo.jpg',
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'      => $post->id,
            'batch_id'     => 'DV-20261110-01',
            'element_id'   => 'DV-20261110-01-E01',
            'payload_hash' => 'dummy-different-hash',
            'received_at'  => now(),
        ]);

        // Aseguramos que si se intentara cualquier petición HTTP, falle o se detecte
        Http::fake();

        $processor = app(EditorialBatchProcessor::class);

        $payload = <<<'MARKDOWN'
FORMATO: DV-1
LOTE_ID: DV-20261110-01
FECHA_EDITORIAL: 2026-11-10
ZONA_HORARIA: Europe/Madrid
TOTAL_ELEMENTOS: 1
MODO: BORRADOR

=== INICIO ELEMENTO ===
ID: DV-20261110-01-E01
TIPO: NOTICIA
ARTISTA_BANDA: Powerwolf
TITULO: Articulo Ya Publicado Con Cambio De Texto
FECHA_EDITORIAL: 2026-11-10
TEXTO:
Texto modificado que no debe alterar el articulo publicado.
FUENTES:
- https://example.com/source
IMAGEN_URL: https://upload.wikimedia.org/wikipedia/commons/new-photo-that-must-not-be-downloaded.jpg
IMAGEN_CREDITO: Foto de prueba
IMAGEN_ALT: Foto alt
REVISION_REQUERIDA: NO
=== FIN ELEMENTO ===
MARKDOWN;

        $result = $processor->process($payload, [], 'msg-test-published');

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['conflicts']);
        $this->assertSame('protected_published', $result['elements_detail']['DV-20261110-01-E01']['action']);

        // El artículo publicado permanece exactamente intacto
        $freshPost = $post->fresh();
        $this->assertSame('published', $freshPost->status);
        $this->assertTrue((bool) $freshPost->is_published);
        $this->assertSame('storage/posts/covers/original-photo.jpg', $freshPost->featured_image);

        // Verificación crítica: CERO peticiones HTTP realizadas
        Http::assertNothingSent();
    }

    public function test_two_batches_with_same_e01_and_title_do_not_overwrite_each_other_images(): void
    {
        $service = app(EditorialImageService::class);

        // Generar imagen JPEG válida de prueba (>4KB)
        $img = imagecreatetruecolor(800, 600);
        $bgColor = imagecolorallocate($img, 120, 40, 40);
        imagefilledrectangle($img, 0, 0, 800, 600, $bgColor);
        for ($i = 0; $i < 120; $i++) {
            $col = imagecolorallocate($img, ($i * 7) % 255, ($i * 11) % 255, 220);
            imageline($img, 0, $i * 5, 800, 600 - ($i * 5), $col);
        }
        ob_start();
        imagejpeg($img, null, 90);
        $imgData = ob_get_clean();
        imagedestroy($img);

        Http::fake([
            'https://upload.wikimedia.org/wikipedia/commons/ghost-photo-batch1.jpg' => Http::response($imgData, 200, ['Content-Type' => 'image/jpeg']),
            'https://upload.wikimedia.org/wikipedia/commons/ghost-photo-batch2.jpg' => Http::response($imgData, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        // Lote 1: DV-20261111-01, E01
        $res1 = $service->resolveAndStore(
            'https://upload.wikimedia.org/wikipedia/commons/ghost-photo-batch1.jpg',
            'DV-20261111-01',
            'DV-20261111-01-E01',
            'ghost-confirma-nuevo-album',
            'Foto Lote 1'
        );

        if (! $res1['success']) {
            $this->fail("RES1 ERROR: " . ($res1['error'] ?? 'NONE'));
        }

        // Lote 2: DV-20261112-01, E01 (Mismo ID corto de elemento y mismo slug)
        $res2 = $service->resolveAndStore(
            'https://upload.wikimedia.org/wikipedia/commons/ghost-photo-batch2.jpg',
            'DV-20261112-01',
            'DV-20261112-01-E01',
            'ghost-confirma-nuevo-album',
            'Foto Lote 2'
        );

        $this->assertTrue($res1['success']);
        $this->assertTrue($res2['success']);

        // Las rutas deben ser completamente distintas
        $this->assertNotSame($res1['path'], $res2['path']);
        $this->assertStringContainsString('dv-20261111-01', $res1['path']);
        $this->assertStringContainsString('dv-20261112-01', $res2['path']);

        // Ambos archivos deben existir simultáneamente en disco sin sobrescribirse
        $file1 = storage_path('app/public/' . str_replace('storage/', '', $res1['path']));
        $file2 = storage_path('app/public/' . str_replace('storage/', '', $res2['path']));

        $this->assertFileExists($file1);
        $this->assertFileExists($file2);
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
        $this->assertStringStartsWith('storage/posts/covers/post-dv-dv-20261111-01-dv-20261111-01-e01-ghost', $ghostImagePath);
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
