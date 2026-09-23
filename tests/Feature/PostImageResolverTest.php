<?php

namespace Tests\Feature;

use App\Models\RadioArtist;
use App\Models\ThemeSetting;
use App\Services\PostImageResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Webklex\PHPIMAP\Message;

class PostImageResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $settings = ThemeSetting::current();
        $settings->email_default_cover_path = 'assets/lucille/default-test.jpg';
        $settings->press_feeds_extra = "example.com=https://example.com/feed";
        $settings->save();
        
        $reflection = new \ReflectionClass(ThemeSetting::class);
        $property = $reflection->getProperty('currentSettings');
        $property->setAccessible(true);
        $property->setValue(null);

        Storage::fake('public');
    }

    public function test_resolves_from_source_url_og_image()
    {
        Http::fake([
            'https://example.com/article' => Http::response(
                '<html><head><meta property="og:image" content="https://example.com/og-image.jpg"></head><body></body></html>'
            ),
        ]);

        $resolver = app(PostImageResolver::class);
        $message = $this->createMock(Message::class);
        $message->method('getAttachments')->willReturn(\Webklex\PHPIMAP\Support\AttachmentCollection::make([]));

        $result = $resolver->resolveForPost([
            'message' => $message,
            'body' => 'FUENTE: https://example.com/article',
            'subject' => 'Test',
            'clean_title' => 'Test',
            'is_dark_vader' => false,
            'artist_name' => null
        ]);

        $this->assertEquals('https://example.com/og-image.jpg', $result['url']);
        $this->assertEquals('source_url', $result['source']);
        $this->assertEquals('https://example.com/article', $result['article_url']);
    }

    public function test_resolves_from_rss_feed()
    {
        Http::fake([
            'https://example.com/feed' => Http::response(
                '<?xml version="1.0"?><rss><channel><item><title>Test Article Title</title><link>https://example.com/test-article</link><description>&lt;img src="https://example.com/rss-image.jpg"&gt;</description></item></channel></rss>'
            ),
        ]);

        $resolver = app(PostImageResolver::class);
        $message = $this->createMock(Message::class);
        $message->method('getAttachments')->willReturn(\Webklex\PHPIMAP\Support\AttachmentCollection::make([]));

        $result = $resolver->resolveForPost([
            'message' => $message,
            'body' => 'Creditos: example.com',
            'subject' => 'Test Article',
            'clean_title' => 'Test Article Title',
            'is_dark_vader' => false,
            'artist_name' => null
        ]);

        $this->assertEquals('https://example.com/rss-image.jpg', $result['url']);
        $this->assertEquals('rss', $result['source']);
        $this->assertEquals('example.com', $result['credit']);
        $this->assertEquals('https://example.com/test-article', $result['article_url']);
    }

    public function test_resolves_from_artist_catalog()
    {
        RadioArtist::create([
            'name' => 'The Test Band',
            'slug' => 'test-band',
            'image_path' => 'catalog/artists/test-band.jpg'
        ]);

        $resolver = app(PostImageResolver::class);
        $message = $this->createMock(Message::class);
        $message->method('getAttachments')->willReturn(\Webklex\PHPIMAP\Support\AttachmentCollection::make([]));

        $result = $resolver->resolveForPost([
            'message' => $message,
            'body' => 'Some text',
            'subject' => 'Test',
            'clean_title' => 'Test',
            'is_dark_vader' => false,
            'artist_name' => 'Test Band' // Testing normalization
        ]);

        $this->assertStringContainsString('catalog/artists/test-band.jpg', $result['url']);
        $this->assertEquals('artist_catalog', $result['source']);
    }

    public function test_resolves_to_default_if_nothing_found()
    {
        $resolver = app(PostImageResolver::class);
        $message = $this->createMock(Message::class);
        $message->method('getAttachments')->willReturn(\Webklex\PHPIMAP\Support\AttachmentCollection::make([]));

        $result = $resolver->resolveForPost([
            'message' => $message,
            'body' => 'Some text',
            'subject' => 'Test',
            'clean_title' => 'Test',
            'is_dark_vader' => false,
            'artist_name' => null
        ]);

        $this->assertStringContainsString('default-test.jpg', $result['url']);
        $this->assertEquals('default', $result['source']);
    }
}
