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

        // Fake Http requests for the image download
        $fakeImgUrl = 'https://example.com/band-photo.jpg';
        Http::fake([
            $fakeImgUrl => Http::response(str_repeat('A', 50000), 200), // 50 KB image
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
            'url' => 'https://f003.backblazeb2.com/file/7RR-DATOS/catalog/releases/covers/fake-uuid.jpg',
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
            'cover_image' => 'https://f003.backblazeb2.com/file/7RR-DATOS/catalog/releases/covers/fake-uuid.jpg',
        ]);
    }
}
