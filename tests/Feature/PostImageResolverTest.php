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
        
        \Illuminate\Support\Facades\DB::table('theme_settings')->insert([
            'email_default_cover_path' => 'assets/lucille/default-test.jpg',
            'press_feeds_extra' => "example.com=https://example.com/feed",
            'site_name' => 'Test',
        ]);
        
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
    public function test_post_featured_image_fallback()
    {
        $post = new \App\Models\Post();
        $post->featured_image_path = null;
        $post->featured_image = null;

        $resolved = $post->featured_image;

        $this->assertNotNull($resolved);
        $this->assertIsString($resolved);
        $this->assertTrue(str_contains($resolved, 'default-test.jpg') || str_contains($resolved, 'album3.jpg'));
    }

    public function test_attachment_accepts_25kb_valid_cover_and_discards_tracking_pixel()
    {
        // 1. JPEG 600x600 y 25 KB
        $validJpg = imagecreatetruecolor(600, 600);
        ob_start(); imagejpeg($validJpg); $validJpgContent = str_pad(ob_get_clean(), 25000, '0'); imagedestroy($validJpg);
        
        // 2. PNG 1x1 de 1 KB (tracking)
        $pixelPng = imagecreatetruecolor(1, 1);
        ob_start(); imagepng($pixelPng); $pixelContent = str_pad(ob_get_clean(), 1024, '0'); imagedestroy($pixelPng);

        $att1 = $this->createMock(\Webklex\PHPIMAP\Attachment::class);
        $att1->method('getName')->willReturn('cover.jpg');
        $att1->method('getContent')->willReturn($validJpgContent);

        $att2 = $this->createMock(\Webklex\PHPIMAP\Attachment::class);
        $att2->method('getName')->willReturn('pixel.png');
        $att2->method('getContent')->willReturn($pixelContent);

        $message = $this->createMock(\Webklex\PHPIMAP\Message::class);
        $message->method('getAttachments')->willReturn(\Webklex\PHPIMAP\Support\AttachmentCollection::make([$att2, $att1]));

        $result = app(\App\Services\PostImageResolver::class)->resolveForPost([
            'message' => $message,
            'body' => '',
            'sender' => 'test@test.com',
            'isDarkVader' => false,
        ]);

        $this->assertNotNull($result);
        $this->assertStringContainsString('.jpg', $result);
    }

    public function test_attachment_prefers_square_over_heavy()
    {
        // 3. PNG 400x400 y 8 KB (mas cuadrada)
        $squarePng = imagecreatetruecolor(400, 400);
        ob_start(); imagepng($squarePng); $squareContent = str_pad(ob_get_clean(), 8000, '0'); imagedestroy($squarePng);

        // 4. JPEG 600x400 y 30 KB (mas pesada pero no cuadrada)
        $rectJpg = imagecreatetruecolor(600, 400);
        ob_start(); imagejpeg($rectJpg); $rectContent = str_pad(ob_get_clean(), 30000, '0'); imagedestroy($rectJpg);

        $att1 = $this->createMock(\Webklex\PHPIMAP\Attachment::class);
        $att1->method('getName')->willReturn('square.png');
        $att1->method('getContent')->willReturn($squareContent);

        $att2 = $this->createMock(\Webklex\PHPIMAP\Attachment::class);
        $att2->method('getName')->willReturn('rect.jpg');
        $att2->method('getContent')->willReturn($rectContent);

        $message = $this->createMock(\Webklex\PHPIMAP\Message::class);
        $message->method('getAttachments')->willReturn(\Webklex\PHPIMAP\Support\AttachmentCollection::make([$att2, $att1]));

        $result = app(\App\Services\PostImageResolver::class)->resolveForPost([
            'message' => $message,
            'body' => '',
            'sender' => 'test@test.com',
            'isDarkVader' => false,
        ]);

        $this->assertNotNull($result);
        $this->assertStringContainsString('.png', $result);
    }

    public function test_attachment_accepts_heavy_legacy_cover()
    {
        // 4. JPEG 1200x1200 y 300 KB
        $heavyJpg = imagecreatetruecolor(1200, 1200);
        ob_start(); imagejpeg($heavyJpg); $heavyContent = str_pad(ob_get_clean(), 300000, '0'); imagedestroy($heavyJpg);

        $att = $this->createMock(\Webklex\PHPIMAP\Attachment::class);
        $att->method('getName')->willReturn('heavy.jpg');
        $att->method('getContent')->willReturn($heavyContent);

        $message = $this->createMock(\Webklex\PHPIMAP\Message::class);
        $message->method('getAttachments')->willReturn(\Webklex\PHPIMAP\Support\AttachmentCollection::make([$att]));

        $result = app(\App\Services\PostImageResolver::class)->resolveForPost([
            'message' => $message,
            'body' => '',
            'sender' => 'test@test.com',
            'isDarkVader' => false,
        ]);

        $this->assertNotNull($result);
        $this->assertStringContainsString('.jpg', $result);
    }
}
