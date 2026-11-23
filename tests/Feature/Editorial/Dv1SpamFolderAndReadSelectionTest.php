<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Models\EmailPostSanitizedMeta;
use App\Models\Post;
use App\Models\ThemeSetting;
use App\Services\EmailSecurityVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Header;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Query\WhereQuery;
use Webklex\PHPIMAP\Support\AttachmentCollection;
use Webklex\PHPIMAP\Support\FlagCollection;
use Webklex\PHPIMAP\Support\MessageCollection;
use Webklex\PHPIMAP\Support\PaginatedCollection;

class Dv1SpamFolderAndReadSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = ThemeSetting::current();
        $settings->fill([
            'email_processing_enabled' => true,
            'email_auto_publish'       => true, // En producción está en true
        ]);
        $settings->save();

        config([
            'services.imap.password' => 'test-imap-pass',
            'services.imap.username' => 'sevenrockradio@gmail.com',
        ]);
    }

    public function test_dv1_emails_in_spam_folder_or_already_marked_seen_are_selected_and_processed_as_drafts(): void
    {
        $batchPayload = <<<'MARKDOWN'
FORMATO: DV-1
LOTE_ID: DV-20261113-01
FECHA_EDITORIAL: 2026-11-13
ZONA_HORARIA: Europe/Madrid
TOTAL_ELEMENTOS: 1
MODO: BORRADOR

=== INICIO ELEMENTO ===
ID: DV-20261113-E01
TIPO: NOTICIA
TITULO: Iron Maiden confirma nuevas fechas exclusivas para su gira europea 2027
ARTISTA_BANDA: Iron Maiden
FECHA_EVENTO: 2026-11-13
TEXTO:
La legendaria agrupacion britanica Iron Maiden ha confirmado de forma oficial nuevas presentaciones internacionales dentro del circuito de estadios europeos para el proximo periodo estival de 2027.
FUENTES:
- https://ironmaiden.com/news/2026/european-tour-announcement
IMAGEN_URL: NINGUNA
IMAGEN_CREDITO: none
=== FIN ELEMENTO ===
MARKDOWN;

        // Mock IMAP
        $mockClientManager = \Mockery::mock(ClientManager::class);
        $mockClient = \Mockery::mock(Client::class);
        $mockInboxFolder = \Mockery::mock(Folder::class);
        $mockSpamFolder = \Mockery::mock(Folder::class);
        $mockInboxQuery = \Mockery::mock(WhereQuery::class);
        $mockSpamQuery = \Mockery::mock(WhereQuery::class);

        $mockInboxFolder->path = 'INBOX';
        $mockSpamFolder->path = '[Gmail]/Spam';

        // 1. Mensaje en INBOX: correo promocional no DV-1 (debe ignorarse)
        $mockMsgPromo = \Mockery::mock(Message::class);
        $mockAddressPromo = \Mockery::mock(Address::class);
        $mockAddressPromo->mail = 'promo@recordlabel.com';
        $mockMsgPromo->shouldReceive('getMessageId')->andReturn('<promo-release-001@recordlabel.com>');
        $mockMsgPromo->shouldReceive('getSubject')->andReturn('Nueva maqueta de banda local');
        $mockMsgPromo->shouldReceive('getFrom')->andReturn(new PaginatedCollection([$mockAddressPromo]));
        $mockMsgPromo->shouldReceive('getAttachments')->andReturn(new AttachmentCollection([]));
        $mockMsgPromo->shouldReceive('getHTMLBody')->andReturn('<p>Escucha nuestro nuevo single</p>');
        $mockMsgPromo->shouldReceive('getTextBody')->andReturn('Escucha nuestro nuevo single');
        $mockMsgPromo->shouldReceive('setFlag')->with('SEEN')->andReturn(true);

        // 2. Mensaje en Spam: auténtico DV-1 con flag SEEN (ya leído)
        $mockMsgDv1 = \Mockery::mock(Message::class);
        $mockAddressDV = \Mockery::mock(Address::class);
        $mockAddressDV->mail = 'dark.vader.agent@gmail.com';

        $mockHeaderDv1 = \Mockery::mock(Header::class);
        $mockHeaderDv1->raw = "Authentication-Results: mx.google.com; dkim=pass header.i=@gmail.com; spf=pass\r\nFrom: dark.vader.agent@gmail.com";
        $mockMsgDv1->shouldReceive('getHeader')->andReturn($mockHeaderDv1);
        $mockMsgDv1->shouldReceive('getMessageId')->andReturn('<dv-msg-20261113-01@gmail.com>');
        $mockMsgDv1->shouldReceive('getSubject')->andReturn('[DARTH-VADER][DV-1][2026-11-13][DV-20261113-01]');
        $mockMsgDv1->shouldReceive('getFrom')->andReturn(new PaginatedCollection([$mockAddressDV]));
        $mockMsgDv1->shouldReceive('getAttachments')->andReturn(new AttachmentCollection([]));
        $mockMsgDv1->shouldReceive('getHTMLBody')->andReturn('');
        $mockMsgDv1->shouldReceive('getTextBody')->andReturn($batchPayload);
        $mockMsgDv1->shouldReceive('getFlags')->andReturn(new FlagCollection(['\\Seen']));
        $mockMsgDv1->shouldReceive('setFlag')->with('SEEN')->andReturn(true);

        // Queries
        $mockInboxQuery->shouldReceive('from')->with('dark.vader.agent@gmail.com')->andReturn($mockInboxQuery);
        $mockInboxQuery->shouldReceive('since')->andReturn($mockInboxQuery);
        $mockInboxQuery->shouldReceive('setFetchOrder')->with('desc')->andReturn($mockInboxQuery);
        $mockInboxQuery->shouldReceive('get')->andReturn(new MessageCollection([]));
        $mockInboxFolder->shouldReceive('query')->andReturn($mockInboxQuery);

        $mockSpamQuery->shouldReceive('from')->with('dark.vader.agent@gmail.com')->andReturn($mockSpamQuery);
        $mockSpamQuery->shouldReceive('since')->andReturn($mockSpamQuery);
        $mockSpamQuery->shouldReceive('setFetchOrder')->with('desc')->andReturn($mockSpamQuery);
        $mockSpamQuery->shouldReceive('get')->andReturn(new MessageCollection([$mockMsgDv1]));
        $mockSpamFolder->shouldReceive('query')->andReturn($mockSpamQuery);

        $mockClient->shouldReceive('connect')->andReturn($mockClient);
        $mockClient->shouldReceive('getFolder')->with('INBOX')->andReturn($mockInboxFolder);
        $mockClient->shouldReceive('getFolder')->with('[Gmail]/Spam')->andReturn($mockSpamFolder);
        $mockClient->shouldReceive('getFolder')->with('Spam')->andReturn(null);
        $mockClient->shouldReceive('getFolder')->with('Junk')->andReturn(null);
        $mockClientManager->shouldReceive('make')->andReturn($mockClient);

        $this->app->instance(ClientManager::class, $mockClientManager);

        $mockVerifier = \Mockery::mock(EmailSecurityVerifier::class);
        $mockVerifier->shouldReceive('isDarkVaderLegitimate')->andReturn(true);
        $this->app->instance(EmailSecurityVerifier::class, $mockVerifier);

        // Ejecución con --only-dv1 y --as-draft
        $exitCode = \Illuminate\Support\Facades\Artisan::call('emails:process', [
            '--only-dv1' => true,
            '--as-draft' => true,
        ]);

        $this->assertSame(0, $exitCode);

        // 1. Correo promocional no procesado
        $this->assertDatabaseMissing('processed_emails', [
            'message_id' => '<promo-release-001@recordlabel.com>',
        ]);

        // 2. Correo DV-1 en Spam procesado con éxito
        $this->assertDatabaseHas('processed_emails', [
            'message_id' => '<dv-msg-20261113-01@gmail.com>',
            'status'     => 'processed',
        ]);

        // 3. Post creado exclusivamente como BORRADOR (a pesar de email_auto_publish=true)
        $this->assertSame(1, Post::count());
        $post = Post::first();
        $this->assertNotNull($post);
        $this->assertSame('draft', $post->status);
        $this->assertSame(0, (int) $post->is_published);
        $this->assertNull($post->published_at);
        $this->assertSame('Iron Maiden confirma nuevas fechas exclusivas para su gira europea 2027', $post->title);

        // 4. Metadatos del lote conservados íntegramente
        $meta = EmailPostSanitizedMeta::where('batch_id', 'DV-20261113-01')->first();
        $this->assertNotNull($meta);
        $this->assertSame('DV-20261113-E01', $meta->element_id);
        $this->assertSame('<dv-msg-20261113-01@gmail.com>', $meta->source_message_id);
    }

    public function test_dv1_reprocessing_same_email_is_idempotent_and_preserves_drafts_without_duplication(): void
    {
        // 1. Pre-crear el registro previo en processed_emails y post existente
        $existingPost = Post::create([
            'title'       => 'Noticia previa DV-1',
            'slug'        => 'noticia-previa-dv-1',
            'content'     => '<p>Contenido existente</p>',
            'excerpt'     => 'Contenido existente',
            'status'      => 'draft',
            'is_published'=> 0,
            'author'      => 'Seven Rock Radio',
            'source_name' => 'Darth Vader / Agencia Editorial',
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'           => $existingPost->id,
            'source_message_id' => '<dv-msg-idempotent-01@gmail.com>',
            'batch_id'          => 'DV-20261113-01',
            'element_id'        => 'DV-20261113-E01',
            'payload_hash'      => 'hash-idempotent-001',
            'security_status'   => 'passed',
        ]);

        \Illuminate\Support\Facades\DB::table('processed_emails')->insert([
            'message_id' => '<dv-msg-idempotent-01@gmail.com>',
            'subject'    => '[DARTH-VADER][DV-1][2026-11-13][DV-20261113-01]',
            'status'     => 'processed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mock IMAP devolviendo el mismo mensaje ya procesado
        $mockClientManager = \Mockery::mock(ClientManager::class);
        $mockClient = \Mockery::mock(Client::class);
        $mockFolder = \Mockery::mock(Folder::class);
        $mockQuery = \Mockery::mock(WhereQuery::class);

        $mockFolder->path = 'INBOX';

        $mockMsg = \Mockery::mock(Message::class);
        $mockAddressDV = \Mockery::mock(Address::class);
        $mockAddressDV->mail = 'dark.vader.agent@gmail.com';

        $mockMsg->shouldReceive('getMessageId')->andReturn('<dv-msg-idempotent-01@gmail.com>');
        $mockMsg->shouldReceive('getSubject')->andReturn('[DARTH-VADER][DV-1][2026-11-13][DV-20261113-01]');
        $mockMsg->shouldReceive('getFrom')->andReturn(new PaginatedCollection([$mockAddressDV]));
        $mockMsg->shouldReceive('setFlag')->with('SEEN')->andReturn(true);

        $mockQuery->shouldReceive('from')->with('dark.vader.agent@gmail.com')->andReturn($mockQuery);
        $mockQuery->shouldReceive('since')->andReturn($mockQuery);
        $mockQuery->shouldReceive('setFetchOrder')->with('desc')->andReturn($mockQuery);
        $mockQuery->shouldReceive('get')->andReturn(new MessageCollection([$mockMsg]));
        $mockFolder->shouldReceive('query')->andReturn($mockQuery);

        $mockClient->shouldReceive('connect')->andReturn($mockClient);
        $mockClient->shouldReceive('getFolder')->with('INBOX')->andReturn($mockFolder);
        $mockClient->shouldReceive('getFolder')->with('[Gmail]/Spam')->andReturn(null);
        $mockClient->shouldReceive('getFolder')->with('Spam')->andReturn(null);
        $mockClient->shouldReceive('getFolder')->with('Junk')->andReturn(null);
        $mockClientManager->shouldReceive('make')->andReturn($mockClient);

        $this->app->instance(ClientManager::class, $mockClientManager);

        // Re-ejecutar emails:process
        $exitCode = \Illuminate\Support\Facades\Artisan::call('emails:process', [
            '--only-dv1' => true,
            '--as-draft' => true,
        ]);

        $this->assertSame(0, $exitCode);

        // Aserciones de idempotencia:
        // No se crearon nuevos posts, se mantuvo intacto el post existente
        $this->assertSame(1, Post::count());
        $this->assertSame('draft', Post::first()->status);
        $this->assertSame(1, EmailPostSanitizedMeta::count());
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('processed_emails')->count());
    }
}
