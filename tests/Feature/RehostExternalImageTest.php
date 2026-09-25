<?php

namespace Tests\Feature;

use App\Services\PostImageResolver;
use App\Models\ThemeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Webklex\PHPIMAP\Message;
use Mockery;

class RehostExternalImageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $mockSettings = Mockery::mock(ThemeSetting::class);
        $mockSettings->shouldReceive('getAttribute')->with('email_default_cover_path')->andReturn('assets/lucille/album3.jpg');
        $this->app->instance(ThemeSetting::class, $mockSettings);
        
        $mockFileUpload = Mockery::mock(\App\Services\FileUploadService::class);
        $mockFileUpload->shouldReceive('uploadRaw')->andReturnUsing(function ($content, $path, $disk) {
            Storage::disk($disk)->put($path, $content);
            return ['url' => "https://media.sevenrockradio.com/{$path}"];
        });
        $this->app->instance(\App\Services\FileUploadService::class, $mockFileUpload);

        Storage::fake('r2');
        config(['app.url' => 'https://sevenrockradio.com']);
        config(['filesystems.default' => 'r2']);
        config(['filesystems.disks.r2.url' => 'https://media.sevenrockradio.com']);
    }

    public function test_rehosts_external_image_and_returns_media_domain()
    {
        // Fake de un pixel de 300x300 válido
        $validImage = base64_decode('iVBORw0KGgoAAAANSUhEUgAAASwAAAEsAQMAAABDsxw2AAAAA1BMVEUAAACnej3aAAAAAXRSTlMAQObYZgAAACNJREFUaN7twTEBAAAAwiD7p7bGDmAAAAAAAAAAAAAAAAAAAF4MIAABbW9mOAAAAABJRU5ErkJggg==');
        // Aumentamos el tamaño a > 8KB concatenando bytes nulos para simular un archivo más grande que pase el filtro de tamaño
        $validImage .= str_repeat("\0", 8192);

        Http::fake([
            'assets.blabbermouth.net/*' => Http::response($validImage, 200, ['Content-Type' => 'image/png']),
        ]);

        $resolver = app(PostImageResolver::class);
        $reflection = new \ReflectionClass($resolver);
        $method = $reflection->getMethod('rehostExternalImage');
        $method->setAccessible(true);

        $url = 'https://assets.blabbermouth.net/media/image.jpg';
        $rehostedUrl = $method->invoke($resolver, $url, 'test');

        $this->assertNotNull($rehostedUrl);
        $this->assertStringStartsWith('https://media.sevenrockradio.com/posts/covers/', $rehostedUrl);
        
        $files = Storage::disk('r2')->allFiles('posts/covers');
        $this->assertCount(1, $files);
    }

    public function test_discards_small_tracking_pixel()
    {
        // Píxel de 1x1 muy pequeño
        $smallPixel = base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
        
        Http::fake([
            'assets.blabbermouth.net/pixel.gif' => Http::response($smallPixel, 200, ['Content-Type' => 'image/gif']),
        ]);

        $resolver = app(PostImageResolver::class);
        $reflection = new \ReflectionClass($resolver);
        $method = $reflection->getMethod('rehostExternalImage');
        $method->setAccessible(true);

        $url = 'https://assets.blabbermouth.net/pixel.gif';
        $rehostedUrl = $method->invoke($resolver, $url, 'test');

        $this->assertNull($rehostedUrl);
        $files = Storage::disk('r2')->allFiles('posts/covers');
        $this->assertCount(0, $files);
    }

    public function test_does_not_rehost_already_media_domain_url()
    {
        Http::fake();

        $resolver = app(PostImageResolver::class);
        $reflection = new \ReflectionClass($resolver);
        $method = $reflection->getMethod('rehostExternalImage');
        $method->setAccessible(true);

        $url = 'https://media.sevenrockradio.com/test/already.jpg';
        $rehostedUrl = $method->invoke($resolver, $url, 'test');

        $this->assertEquals($url, $rehostedUrl);
        Http::assertNothingSent();
    }

    public function test_returns_null_if_upload_fails()
    {
        $validImage = base64_decode('iVBORw0KGgoAAAANSUhEUgAAASwAAAEsAQMAAABDsxw2AAAAA1BMVEUAAACnej3aAAAAAXRSTlMAQObYZgAAACNJREFUaN7twTEBAAAAwiD7p7bGDmAAAAAAAAAAAAAAAAAAAF4MIAABbW9mOAAAAABJRU5ErkJggg==');
        $validImage .= str_repeat("\0", 8192);

        Http::fake([
            'assets.blabbermouth.net/*' => Http::response($validImage, 200, ['Content-Type' => 'image/png']),
        ]);

        $mockSettings = Mockery::mock(ThemeSetting::class);
        $mockSettings->shouldReceive('getAttribute')->with('email_default_cover_path')->andReturn('assets/lucille/album3.jpg');
        $this->app->instance(ThemeSetting::class, $mockSettings);

        $mockFileUpload = Mockery::mock(\App\Services\FileUploadService::class);
        $mockFileUpload->shouldReceive('uploadRaw')->andReturn(['url' => '']); // Force fail
        $this->app->instance(\App\Services\FileUploadService::class, $mockFileUpload);

        $resolver = app(PostImageResolver::class);
        $reflection = new \ReflectionClass($resolver);
        $method = $reflection->getMethod('rehostExternalImage');
        $method->setAccessible(true);

        $url = 'https://assets.blabbermouth.net/media/image.jpg';
        $rehostedUrl = $method->invoke($resolver, $url, 'test');

        $this->assertNull($rehostedUrl);
    }
}
