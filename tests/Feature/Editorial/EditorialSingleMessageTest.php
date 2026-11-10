<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Models\EmailPostSanitizedMeta;
use App\Models\Post;
use App\Models\ThemeSetting;
use App\Services\EditorialBatchProcessor;
use App\Services\EmailSecurityVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

class EditorialSingleMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = ThemeSetting::current();
        $settings->fill([
            'email_processing_enabled' => false, // Deshabilitado en general
            'email_auto_publish'       => false,
        ]);
        $settings->save();

        config([
            'services.imap.password' => 'test-imap-pass',
            'services.imap.username' => 'sevenrockradio@gmail.com',
        ]);
    }

    public function test_target_message_id_processes_exclusively_targeted_email_even_when_general_polling_is_disabled(): void
    {
        $batchFile = base_path('storage/editorial_batches/DV-20261110-01/DV-20261110-01_REVISADO.md');
        $batchContent = file_get_contents($batchFile);

        // Pre-crear los 5 posts como publicados
        $batchProcessor = app(EditorialBatchProcessor::class);
        $manifestPath = base_path('storage/editorial_batches/DV-20261110-01/images_installed.json');
        $manifest = json_decode(file_get_contents($manifestPath), true);
        $initialImport = $batchProcessor->process($batchContent, $manifest, 'initial-cli');
        $this->assertTrue($initialImport['success']);

        // Marcar como publicados
        Post::query()->update(['status' => 'published', 'is_published' => 1]);

        // Mock IMAP
        $mockClientManager = \Mockery::mock(ClientManager::class);
        $mockClient = \Mockery::mock(Client::class);
        $mockFolder = \Mockery::mock(Folder::class);
        $mockQuery = \Mockery::mock(WhereQuery::class);

        // Mensaje objetivo
        $mockMsgTarget = \Mockery::mock(Message::class);
        $mockAddressDV = \Mockery::mock(Address::class);
        $mockAddressDV->mail = 'dark.vader.agent@gmail.com';

        $mockHeaderTarget = \Mockery::mock(\Webklex\PHPIMAP\Header::class);
        $mockHeaderTarget->raw = "Authentication-Results: mx.google.com; dkim=pass header.i=@gmail.com; spf=pass\r\nFrom: dark.vader.agent@gmail.com";
        $mockMsgTarget->shouldReceive('getHeader')->andReturn($mockHeaderTarget);

        $mockMsgTarget->shouldReceive('getMessageId')->andReturn('<179434419672.847527.16732409867263122347@gmail.com>');
        $mockMsgTarget->shouldReceive('getSubject')->andReturn('[PRUEBA][DARTH-VADER][DV-1][2026-11-10][DV-20261110-01]');
        $mockMsgTarget->shouldReceive('getFrom')->andReturn(new PaginatedCollection([$mockAddressDV]));
        $mockMsgTarget->shouldReceive('getAttachments')->andReturn(new AttachmentCollection([]));
        $mockMsgTarget->shouldReceive('getHTMLBody')->andReturn('');
        $mockMsgTarget->shouldReceive('getTextBody')->andReturn($batchContent);
        $mockMsgTarget->shouldReceive('setFlag')->with('SEEN')->andReturn(true);

        // Mensaje espurio que no debe procesarse
        $mockMsgSpurious = \Mockery::mock(Message::class);
        $mockAddressOther = \Mockery::mock(Address::class);
        $mockAddressOther->mail = 'promo@recordlabel.com';
        $mockMsgSpurious->shouldReceive('getMessageId')->andReturn('<spurious-promo-123@recordlabel.com>');
        $mockMsgSpurious->shouldReceive('getSubject')->andReturn('Promo release');

        $mockQuery->shouldReceive('from')->with('dark.vader.agent@gmail.com')->andReturn($mockQuery);
        $mockQuery->shouldReceive('setFetchOrder')->with('desc')->andReturn($mockQuery);
        $mockQuery->shouldReceive('limit')->with(10)->andReturn($mockQuery);
        $mockQuery->shouldReceive('get')->andReturn(new MessageCollection([$mockMsgTarget, $mockMsgSpurious]));

        $mockFolder->shouldReceive('query')->andReturn($mockQuery);
        $mockClient->shouldReceive('getFolder')->with('INBOX')->andReturn($mockFolder);
        $mockClient->shouldReceive('connect')->andReturn($mockClient);
        $mockClientManager->shouldReceive('make')->andReturn($mockClient);

        $this->app->instance(ClientManager::class, $mockClientManager);

        $mockVerifier = \Mockery::mock(EmailSecurityVerifier::class);
        $mockVerifier->shouldReceive('isDarkVaderLegitimate')->andReturn(true);
        $this->app->instance(EmailSecurityVerifier::class, $mockVerifier);

        // Ejecutar con --message-id
        $exitCode = \Illuminate\Support\Facades\Artisan::call('emails:process', [
            '--message-id' => '179434419672.847527.16732409867263122347@gmail.com',
        ]);

        $this->assertSame(0, $exitCode);

        // Verificar resultados: 0 artículos nuevos, 0 modificaciones en publicados
        $this->assertSame(5, Post::count());
        $this->assertSame(5, Post::where('status', 'published')->where('is_published', 1)->count());

        $this->assertDatabaseHas('processed_emails', [
            'message_id' => '<179434419672.847527.16732409867263122347@gmail.com>',
            'status'     => 'processed',
        ]);
        $this->assertDatabaseMissing('processed_emails', [
            'message_id' => '<spurious-promo-123@recordlabel.com>',
        ]);
    }

    public function test_only_dv1_option_ignores_promotional_emails_and_audios(): void
    {
        $settings = ThemeSetting::current();
        $settings->fill(['email_processing_enabled' => true]);
        $settings->save();

        $mockClientManager = \Mockery::mock(ClientManager::class);
        $mockClient = \Mockery::mock(Client::class);
        $mockFolder = \Mockery::mock(Folder::class);
        $mockQuery = \Mockery::mock(WhereQuery::class);

        // Mensaje no DV-1 (ej. nota de prensa de sello discográfico)
        $mockMsg = \Mockery::mock(Message::class);
        $mockAddress = \Mockery::mock(Address::class);
        $mockAddress->mail = 'promo@label.com';

        $mockMsg->shouldReceive('getMessageId')->andReturn('<promo-999@label.com>');
        $mockMsg->shouldReceive('getSubject')->andReturn('Boletin de novedades de sello');
        $mockMsg->shouldReceive('getFrom')->andReturn(new PaginatedCollection([$mockAddress]));
        $mockMsg->shouldReceive('getAttachments')->andReturn(new AttachmentCollection([]));
        $mockMsg->shouldReceive('getHTMLBody')->andReturn('<p>Nuevo single de banda X</p>');
        $mockMsg->shouldReceive('getTextBody')->andReturn('Nuevo single de banda X');
        $mockMsg->shouldReceive('setFlag')->with('SEEN')->andReturn(true);

        $mockQuery->shouldReceive('unseen')->andReturn($mockQuery);
        $mockQuery->shouldReceive('get')->andReturn(new MessageCollection([$mockMsg]));

        $mockFolder->shouldReceive('query')->andReturn($mockQuery);
        $mockClient->shouldReceive('getFolder')->with('INBOX')->andReturn($mockFolder);
        $mockClient->shouldReceive('connect')->andReturn($mockClient);
        $mockClientManager->shouldReceive('make')->andReturn($mockClient);

        $this->app->instance(ClientManager::class, $mockClientManager);

        $exitCode = \Illuminate\Support\Facades\Artisan::call('emails:process', [
            '--only-dv1' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(0, Post::count());
    }
}

