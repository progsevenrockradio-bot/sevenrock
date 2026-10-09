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
use Mockery;

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

        Storage::fake('r2');
        config(['filesystems.default' => 'r2']);
        config(['filesystems.disks.r2.url' => 'https://media.sevenrockradio.com']);

        $mockFileUpload = Mockery::mock(\App\Services\FileUploadService::class);
        $mockFileUpload->shouldReceive('uploadRaw')->andReturnUsing(function ($content, $path, $disk = 'r2') {
            Storage::disk('r2')->put($path, $content);
            return ['url' => "https://media.sevenrockradio.com/{$path}"];
        });
        $this->app->instance(\App\Services\FileUploadService::class, $mockFileUpload);
    }

    public function test_resolves_from_source_url_og_image_and_rehosts()
    {
        $validImage = base64_decode('iVBORw0KGgoAAAANSUhEUgAAASwAAAEsAQMAAABDsxw2AAAAA1BMVEUAAACnej3aAAAAAXRSTlMAQObYZgAAACNJREFUaN7twTEBAAAAwiD7p7bGDmAAAAAAAAAAAAAAAAAAAF4MIAABbW9mOAAAAABJRU5ErkJggg==');
        $validImage .= str_repeat("\0", 8192);

        Http::fake([
            'https://example.com/article' => Http::response(
                '<html><head><meta property="og:image" content="https://example.com/og-image.jpg"></head><body></body></html>'
            ),
            'https://example.com/og-image.jpg' => Http::response($validImage, 200, ['Content-Type' => 'image/png']),
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

        $this->assertNotNull($result['url']);
        $this->assertStringStartsWith('https://media.sevenrockradio.com/posts/covers/', $result['url']);
        $this->assertEquals('source_url', $result['source']);
        $this->assertEquals('https://example.com/article', $result['article_url']);
    }

    public function test_resolves_from_rss_feed_and_rehosts()
    {
        $validImage = base64_decode('iVBORw0KGgoAAAANSUhEUgAAASwAAAEsAQMAAABDsxw2AAAAA1BMVEUAAACnej3aAAAAAXRSTlMAQObYZgAAACNJREFUaN7twTEBAAAAwiD7p7bGDmAAAAAAAAAAAAAAAAAAAF4MIAABbW9mOAAAAABJRU5ErkJggg==');
        $validImage .= str_repeat("\0", 8192);

        Http::fake([
            'https://example.com/feed' => Http::response(
                '<?xml version="1.0"?><rss><channel><item><title>Test Article Title</title><link>https://example.com/test-article</link><description>&lt;img src="https://example.com/rss-image.jpg"&gt;</description></item></channel></rss>'
            ),
            'https://example.com/rss-image.jpg' => Http::response($validImage, 200, ['Content-Type' => 'image/png']),
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

        $this->assertNotNull($result['url']);
        $this->assertStringStartsWith('https://media.sevenrockradio.com/posts/covers/', $result['url']);
        $this->assertEquals('rss', $result['source']);
        $this->assertEquals('example.com', $result['credit']);
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
            'artist_name' => 'Test Band'
        ]);

        $this->assertStringContainsString('catalog/artists/test-band.jpg', $result['url']);
        $this->assertEquals('artist_catalog', $result['source']);
    }

    public function test_resolves_to_null_if_nothing_found_never_generic_fake()
    {
        $resolver = app(PostImageResolver::class);
        $message = $this->createMock(Message::class);
        $message->method('getAttachments')->willReturn(\Webklex\PHPIMAP\Support\AttachmentCollection::make([]));

        $result = $resolver->resolveForPost([
            'message' => $message,
            'body' => 'Some text without source',
            'subject' => 'Test',
            'clean_title' => 'Test',
            'is_dark_vader' => false,
            'artist_name' => null
        ]);

        $this->assertNull($result['url']);
    }

    public function test_post_without_image_returns_null_not_generic_theme_album()
    {
        $post = new \App\Models\Post();
        $post->featured_image_path = null;
        $post->featured_image = null;

        $this->assertNull($post->featured_image);
        $this->assertNull($post->featured_image_url);
    }

    public function test_attachment_accepts_valid_cover_and_discards_tracking_pixel()
    {
        $validJpg = imagecreatetruecolor(600, 600);
        ob_start(); imagejpeg($validJpg); $validJpgContent = str_pad(ob_get_clean(), 25000, '0'); imagedestroy($validJpg);
        
        $pixelPng = imagecreatetruecolor(1, 1);
        ob_start(); imagepng($pixelPng); $pixelContent = str_pad(ob_get_clean(), 1024, '0'); imagedestroy($pixelPng);

        $att1 = Mockery::mock(\Webklex\PHPIMAP\Attachment::class);
        $att1->shouldReceive('getName')->andReturn('cover.jpg');
        $att1->shouldReceive('getContent')->andReturn($validJpgContent);

        $att2 = Mockery::mock(\Webklex\PHPIMAP\Attachment::class);
        $att2->shouldReceive('getName')->andReturn('pixel.png');
        $att2->shouldReceive('getContent')->andReturn($pixelContent);

        $message = Mockery::mock(\Webklex\PHPIMAP\Message::class);
        $message->shouldReceive('getAttachments')->andReturn(new \Webklex\PHPIMAP\Support\AttachmentCollection([$att2, $att1]));

        $result = app(\App\Services\PostImageResolver::class)->resolveForPost([
            'message' => $message,
            'body' => '',
            'subject' => 'Test',
            'clean_title' => 'Test',
            'is_dark_vader' => false,
        ]);

        $this->assertNotNull($result['url']);
        $this->assertStringContainsString('.jpg', $result['url']);
        $this->assertEquals('attachment', $result['source']);
    }

    public function test_attachment_prefers_square_over_heavy()
    {
        $squarePng = imagecreatetruecolor(400, 400);
        ob_start(); imagepng($squarePng); $squareContent = str_pad(ob_get_clean(), 8000, '0'); imagedestroy($squarePng);

        $rectJpg = imagecreatetruecolor(600, 400);
        ob_start(); imagejpeg($rectJpg); $rectContent = str_pad(ob_get_clean(), 30000, '0'); imagedestroy($rectJpg);

        $att1 = Mockery::mock(\Webklex\PHPIMAP\Attachment::class);
        $att1->shouldReceive('getName')->andReturn('square.png');
        $att1->shouldReceive('getContent')->andReturn($squareContent);

        $att2 = Mockery::mock(\Webklex\PHPIMAP\Attachment::class);
        $att2->shouldReceive('getName')->andReturn('rect.jpg');
        $att2->shouldReceive('getContent')->andReturn($rectContent);

        $message = Mockery::mock(\Webklex\PHPIMAP\Message::class);
        $message->shouldReceive('getAttachments')->andReturn(new \Webklex\PHPIMAP\Support\AttachmentCollection([$att2, $att1]));

        $result = app(\App\Services\PostImageResolver::class)->resolveForPost([
            'message' => $message,
            'body' => '',
            'subject' => 'Test',
            'clean_title' => 'Test',
            'is_dark_vader' => false,
        ]);

        $this->assertNotNull($result['url']);
        $this->assertStringContainsString('.png', $result['url']);
        $this->assertEquals('attachment', $result['source']);
    }
}
