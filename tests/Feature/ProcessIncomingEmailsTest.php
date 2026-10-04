<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\ThemeSetting;
use App\Models\NewRelease;
use App\Services\GeminiContentParser;
use App\Services\FileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Query\WhereQuery;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Address;

class ProcessIncomingEmailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_extracts_and_uploads_embedded_html_images_when_no_attachments_exist(): void
    {
        // 1. Setup Theme Settings
        $settings = ThemeSetting::current();
        $settings->fill([
            'email_processing_enabled' => true,
            'gemini_api_key' => 'fake-gemini-key',
            'email_auto_publish' => true,
            'email_min_importance' => 1,
            'email_whitelist_senders' => 'mailchimpapp.com',
        ]);
        $settings->save();

        config([
            'services.imap.password' => 'fake-imap-password',
            'services.imap.username' => 'test@example.com',
        ]);

        // Fake Http requests for the image download with a valid image string
        $fakeImgUrl = 'https://example.com/band-photo.jpg';
        
        $img = imagecreatetruecolor(400, 300);
        ob_start();
        imagejpeg($img);
        $validImageData = ob_get_clean();
        // Pad to >8KB to pass the size check (JPEG ignores trailing garbage)
        $validImageData = str_pad($validImageData, 20000, 'A');
        
        Http::fake([
            $fakeImgUrl => Http::response($validImageData, 200),
        ]);

        // 2. Mock PHPIMAP using Mockery
        $mockClientManager = \Mockery::mock(ClientManager::class);
        $mockClient = \Mockery::mock(Client::class);
        $mockFolder = \Mockery::mock(Folder::class);
        $mockQuery = \Mockery::mock(WhereQuery::class);
        $mockMessage = \Mockery::mock(Message::class);
        $mockAddress = \Mockery::mock(Address::class);

        // Address mock properties
        $mockAddress->mail = 'grandsoundsofficial@mailchimpapp.com';

        // Message mock returns
        $mockMessage->shouldReceive('getMessageId')->andReturn('msg-unique-123');
        $mockMessage->shouldReceive('getSubject')->andReturn('Season of Melancholy Announce Album');
        $mockMessage->shouldReceive('getFrom')->andReturn(new \Webklex\PHPIMAP\Support\PaginatedCollection([$mockAddress]));
        $mockMessage->shouldReceive('getAttachments')->andReturn(new \Webklex\PHPIMAP\Support\AttachmentCollection([])); // No attachments
        
        $htmlBody = '<html><body><h1>Announcing Album</h1><img src="' . $fakeImgUrl . '"></body></html>';
        $mockMessage->shouldReceive('getHTMLBody')->andReturn($htmlBody);
        $mockMessage->shouldReceive('getTextBody')->andReturn('Announcing Album');
        $mockMessage->shouldReceive('setFlag')->with('SEEN')->andReturn(true);

        // Flow mocks
        $mockQuery->shouldReceive('unseen')->andReturn($mockQuery);
        $mockQuery->shouldReceive('get')->andReturn(new \Webklex\PHPIMAP\Support\MessageCollection([$mockMessage]));
        $mockFolder->shouldReceive('query')->andReturn($mockQuery);
        $mockClient->shouldReceive('getFolder')->with('INBOX')->andReturn($mockFolder);
        $mockClient->shouldReceive('connect')->andReturn($mockClient);
        $mockClientManager->shouldReceive('make')->andReturn($mockClient);

        $this->app->instance(ClientManager::class, $mockClientManager);

        // 3. Mock GeminiContentParser
        $mockGemini = $this->createMock(GeminiContentParser::class);
        $mockGemini->method('parse')->willReturn([
            'type' => 'release',
            'title' => 'Season of Melancholy Announce Album',
            'artist_name' => 'Season of Melancholy',
            'content' => 'This is the description.',
            'importance' => 4,
            'youtube_url' => 'https://youtube.com/watch?v=123',
            'spotify_url' => 'https://spotify.com/track/123',
        ]);
        $this->app->instance(GeminiContentParser::class, $mockGemini);

        // 4. Mock FileUploadService
        $mockUpload = $this->createMock(FileUploadService::class);
        $mockUpload->method('uploadRaw')->willReturn([
            'disk' => 'backblaze',
            'key' => 'catalog/releases/covers/fake-uuid.jpg',
            'url' => 'https://media.sevenrockradio.com/catalog/releases/covers/fake-uuid.jpg',
        ]);
        $mockUpload->method('isB2Configured')->willReturn(true);
        $this->app->instance(FileUploadService::class, $mockUpload);

        // 5. Run Command
        $exitCode = \Illuminate\Support\Facades\Artisan::call('emails:process');
        if ($exitCode !== 0) {
            $output = \Illuminate\Support\Facades\Artisan::output();
            fwrite(STDERR, "\nCommand Output:\n" . $output . "\n");
        }
        $this->assertSame(0, $exitCode);

        // 6. Verify Database
        $this->assertDatabaseHas('new_releases', [
            'title' => 'Season of Melancholy Announce Album',
            'artist_name' => 'Season of Melancholy',
            'cover_image' => 'https://media.sevenrockradio.com/catalog/releases/covers/fake-uuid.jpg',
        ]);
    }

    public function test_dark_vader_noticia_rock_with_full_releases_limit()
    {
        // 1. Setup Theme Settings
        $settings = ThemeSetting::current();
        $settings->fill([
            'email_processing_enabled' => true,
            'gemini_api_key' => 'fake-gemini-key',
            'email_auto_publish' => true,
            'email_daily_releases_limit' => 3,
        ]);
        $settings->save();

        config([
            'services.imap.password' => 'fake-imap-password',
            'services.imap.username' => 'test@example.com',
        ]);

        // Fill limits
        for ($i = 0; $i < 3; $i++) {
            NewRelease::create([
                'title' => "Release $i",
                'slug' => "release-$i",
                'artist_name' => "Artist $i",
                'is_active' => true,
            ]);
        }

        // 2. Mock PHPIMAP
        $mockClientManager = \Mockery::mock(ClientManager::class);
        $mockClient = \Mockery::mock(\Webklex\PHPIMAP\Client::class);
        $mockFolder = \Mockery::mock(\Webklex\PHPIMAP\Folder::class);
        $mockQuery = \Mockery::mock(\Webklex\PHPIMAP\Query\WhereQuery::class);
        $mockMessage = \Mockery::mock(\Webklex\PHPIMAP\Message::class);
        $mockAddress = \Mockery::mock(\Webklex\PHPIMAP\Address::class);

        $mockAddress->mail = 'dark.vader.agent@gmail.com';

        $mockMessage->shouldReceive('getMessageId')->andReturn('msg-news-123');
        $mockMessage->shouldReceive('getSubject')->andReturn('Nueva noticia importante');
        $mockMessage->shouldReceive('getFrom')->andReturn(new \Webklex\PHPIMAP\Support\PaginatedCollection([$mockAddress]));
        $mockMessage->shouldReceive('getAttachments')->andReturn(new \Webklex\PHPIMAP\Support\AttachmentCollection([]));
        $mockMessage->shouldReceive('getHTMLBody')->andReturn('Contenido de la noticia');
        $mockMessage->shouldReceive('getTextBody')->andReturn('Contenido de la noticia');
        $mockMessage->shouldReceive('setFlag')->with('SEEN')->andReturn(true);

        $mockQuery->shouldReceive('unseen')->andReturn($mockQuery);
        $mockQuery->shouldReceive('get')->andReturn(new \Webklex\PHPIMAP\Support\MessageCollection([$mockMessage]));
        $mockFolder->shouldReceive('query')->andReturn($mockQuery);
        $mockClient->shouldReceive('getFolder')->with('INBOX')->andReturn($mockFolder);
        $mockClient->shouldReceive('connect')->andReturn($mockClient);
        $mockClientManager->shouldReceive('make')->andReturn($mockClient);

        $this->app->instance(ClientManager::class, $mockClientManager);

        $mockGemini = $this->createMock(GeminiContentParser::class);
        $mockGemini->method('parse')->willReturn([
            'type' => 'release', // The AI mistakenly classifies as release
            'title' => 'Nueva noticia importante',
            'content' => 'Contenido de la noticia',
            'importance' => 5,
        ]);
        $this->app->instance(GeminiContentParser::class, $mockGemini);

        $mockUpload = $this->createMock(FileUploadService::class);
        $mockUpload->method('uploadRaw')->willReturn(['url' => 'fake-url.jpg']);
        $this->app->instance(FileUploadService::class, $mockUpload);

        \Illuminate\Support\Facades\Artisan::call('emails:process');

        $this->assertDatabaseHas('posts', [
            'title' => 'Nueva noticia importante',
            'author_email' => 'dark.vader.agent@gmail.com',
        ]);
        $this->assertDatabaseHas('processed_emails', [
            'message_id' => 'msg-news-123',
            'status' => 'processed',
        ]);
    }

    public function test_dark_vader_efemerides_with_full_releases_limit()
    {
        $settings = ThemeSetting::current();
        $settings->fill([
            'email_processing_enabled' => true,
            'email_auto_publish' => true,
            'email_daily_releases_limit' => 3,
        ]);
        $settings->save();

        config([
            'services.imap.password' => 'fake-imap-password',
            'services.imap.username' => 'test@example.com',
        ]);

        for ($i = 0; $i < 3; $i++) {
            NewRelease::create(['title' => "Release $i", 'slug' => "release-$i", 'artist_name' => "Artist $i", 'is_active' => true]);
        }

        $mockClientManager = \Mockery::mock(ClientManager::class);
        $mockClient = \Mockery::mock(\Webklex\PHPIMAP\Client::class);
        $mockFolder = \Mockery::mock(\Webklex\PHPIMAP\Folder::class);
        $mockQuery = \Mockery::mock(\Webklex\PHPIMAP\Query\WhereQuery::class);
        $mockMessage = \Mockery::mock(\Webklex\PHPIMAP\Message::class);
        $mockAddress = \Mockery::mock(\Webklex\PHPIMAP\Address::class);

        $mockAddress->mail = 'dark.vader.agent@gmail.com';
        $mockMessage->shouldReceive('getMessageId')->andReturn('msg-efem-123');
        $mockMessage->shouldReceive('getSubject')->andReturn('Hoy en el Rock - Especial');
        $mockMessage->shouldReceive('getFrom')->andReturn(new \Webklex\PHPIMAP\Support\PaginatedCollection([$mockAddress]));
        $mockMessage->shouldReceive('getAttachments')->andReturn(new \Webklex\PHPIMAP\Support\AttachmentCollection([]));
        $mockMessage->shouldReceive('getHTMLBody')->andReturn("1. Evento 1 \n 2. Evento 2");
        $mockMessage->shouldReceive('getTextBody')->andReturn("1. Evento 1 \n 2. Evento 2");
        $mockMessage->shouldReceive('setFlag')->with('SEEN')->andReturn(true);

        $mockQuery->shouldReceive('unseen')->andReturn($mockQuery);
        $mockQuery->shouldReceive('get')->andReturn(new \Webklex\PHPIMAP\Support\MessageCollection([$mockMessage]));
        $mockFolder->shouldReceive('query')->andReturn($mockQuery);
        $mockClient->shouldReceive('getFolder')->with('INBOX')->andReturn($mockFolder);
        $mockClient->shouldReceive('connect')->andReturn($mockClient);
        $mockClientManager->shouldReceive('make')->andReturn($mockClient);
        $this->app->instance(ClientManager::class, $mockClientManager);

        $mockGemini = $this->createMock(GeminiContentParser::class);
        $this->app->instance(GeminiContentParser::class, $mockGemini);

        $mockUpload = $this->createMock(FileUploadService::class);
        $mockUpload->method('uploadRaw')->willReturn(['url' => 'fake-url.jpg']);
        $this->app->instance(FileUploadService::class, $mockUpload);

        \Illuminate\Support\Facades\Artisan::call('emails:process');

        $this->assertDatabaseHas('posts', ['title' => 'Evento 1']);
        $this->assertDatabaseHas('posts', ['title' => 'Evento 2']);
    }

    public function test_promo_label_with_full_releases_bounces_and_not_marked_seen()
    {
        $settings = ThemeSetting::current();
        $settings->fill([
            'email_processing_enabled' => true,
            'gemini_api_key' => 'fake-gemini-key',
            'email_auto_publish' => true,
            'email_daily_releases_limit' => 3,
            'email_whitelist_senders' => 'promo@label.com',
        ]);
        $settings->save();

        config([
            'services.imap.password' => 'fake-imap-password',
            'services.imap.username' => 'test@example.com',
        ]);

        for ($i = 0; $i < 3; $i++) {
            NewRelease::create(['title' => "Release $i", 'slug' => "release-$i", 'artist_name' => "Artist $i", 'is_active' => true]);
        }

        $mockClientManager = \Mockery::mock(ClientManager::class);
        $mockClient = \Mockery::mock(\Webklex\PHPIMAP\Client::class);
        $mockFolder = \Mockery::mock(\Webklex\PHPIMAP\Folder::class);
        $mockQuery = \Mockery::mock(\Webklex\PHPIMAP\Query\WhereQuery::class);
        $mockMessage = \Mockery::mock(\Webklex\PHPIMAP\Message::class);
        $mockAddress = \Mockery::mock(\Webklex\PHPIMAP\Address::class);

        $mockAddress->mail = 'promo@label.com';
        $mockMessage->shouldReceive('getMessageId')->andReturn('msg-promo-123');
        $mockMessage->shouldReceive('getSubject')->andReturn('New Album Promo');
        $mockMessage->shouldReceive('getFrom')->andReturn(new \Webklex\PHPIMAP\Support\PaginatedCollection([$mockAddress]));
        $mockMessage->shouldReceive('getAttachments')->andReturn(new \Webklex\PHPIMAP\Support\AttachmentCollection([]));
        $mockMessage->shouldReceive('getHTMLBody')->andReturn("Promo content");
        $mockMessage->shouldReceive('getTextBody')->andReturn("Promo content");
        
        $mockMessage->shouldReceive('setFlag')->with('SEEN')->times(0);

        $mockQuery->shouldReceive('unseen')->andReturn($mockQuery);
        $mockQuery->shouldReceive('get')->andReturn(new \Webklex\PHPIMAP\Support\MessageCollection([$mockMessage]));
        $mockFolder->shouldReceive('query')->andReturn($mockQuery);
        $mockClient->shouldReceive('getFolder')->with('INBOX')->andReturn($mockFolder);
        $mockClient->shouldReceive('connect')->andReturn($mockClient);
        $mockClientManager->shouldReceive('make')->andReturn($mockClient);
        $this->app->instance(ClientManager::class, $mockClientManager);

        $mockGemini = $this->createMock(GeminiContentParser::class);
        $mockGemini->method('parse')->willReturn([
            'type' => 'release',
            'title' => 'New Album',
            'artist_name' => 'The Band',
            'content' => 'Promo content',
            'importance' => 5,
        ]);
        $this->app->instance(GeminiContentParser::class, $mockGemini);

        \Illuminate\Support\Facades\Artisan::call('emails:process');

        $this->assertDatabaseMissing('new_releases', ['title' => 'New Album']);
        $this->assertDatabaseMissing('processed_emails', ['message_id' => 'msg-promo-123']);
    }
}
