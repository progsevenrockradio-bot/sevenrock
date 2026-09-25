<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Models\Product;
use App\Models\TalentAlbum;
use App\Services\FileUploadService;
use App\Support\PublicMediaUrl;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
class BackblazeStandbyTest extends TestCase
{
    use DatabaseTransactions;
    private function disableBackblaze(): void
    {
        Config::set('filesystems.disks.backblaze.enabled', false);
        Config::set('filesystems.disks.backblaze.account_id', 'test_account');
        Config::set('filesystems.disks.backblaze.application_key', 'test_key');
        Config::set('filesystems.disks.backblaze.bucket_id', 'test_bucket_id');
        Config::set('filesystems.disks.backblaze.bucket_name', '7RR-DATOS');
        Config::set('filesystems.disks.backblaze.url', 'https://media.example.com');
    }
    private function enableBackblaze(): void
    {
        Config::set('filesystems.disks.backblaze.enabled', true);
        Config::set('filesystems.disks.backblaze.account_id', 'test_account');
        Config::set('filesystems.disks.backblaze.application_key', 'test_key');
        Config::set('filesystems.disks.backblaze.bucket_id', 'test_bucket_id');
        Config::set('filesystems.disks.backblaze.bucket_name', '7RR-DATOS');
        Config::set('filesystems.disks.backblaze.url', 'https://media.example.com');
    }
    private function fakeR2(): void
    {
        Storage::fake('r2');
        Config::set('filesystems.disks.r2.key', 'r2key');
        Config::set('filesystems.disks.r2.secret', 'r2secret');
        Config::set('filesystems.disks.r2.bucket', 'test-bucket');
        Config::set('filesystems.disks.r2.url', 'https://media.sevenrockradio.com');
        Config::set('filesystems.disks.r2.endpoint', 'https://r2.example.com');
    }
    public function test_is_b2_configured_returns_false_when_disabled(): void
    {
        $this->disableBackblaze();
        $this->assertFalse((new FileUploadService())->isB2Configured());
    }
    public function test_is_b2_configured_false_even_with_all_vars_filled_when_disabled(): void
    {
        $this->disableBackblaze();
        $this->assertFalse((new FileUploadService())->isB2Configured());
    }
    public function test_is_b2_configured_returns_true_when_enabled_with_all_vars(): void
    {
        $this->enableBackblaze();
        $this->assertTrue((new FileUploadService())->isB2Configured());
    }
    public function test_normalize_does_not_touch_backblaze_when_disabled(): void
    {
        $this->disableBackblaze();
        PublicMediaUrl::normalize('some/image/path.jpg');
        $this->assertTrue(true);
    }
    public function test_upload_raw_does_not_use_backblaze_when_disabled(): void
    {
        $this->disableBackblaze();
        $this->fakeR2();
        $result = (new FileUploadService())->uploadRaw('test-content', 'test/probe.txt');
        $this->assertContains($result['disk'], ['r2', 'public']);
        $this->assertNotEquals('backblaze', $result['disk']);
    }
    public function test_upload_raw_url_does_not_contain_backblaze_when_disabled(): void
    {
        $this->disableBackblaze();
        $this->fakeR2();
        $result = (new FileUploadService())->uploadRaw('test-content', 'test/probe-url.txt');
        $this->assertStringNotContainsStringIgnoringCase('backblaze', (string)($result['url'] ?? ''));
        $this->assertStringNotContainsStringIgnoringCase('backblazeb2.com', (string)($result['url'] ?? ''));
    }
    public function test_product_image_url_does_not_throw_with_backblaze_disabled(): void
    {
        $this->disableBackblaze();
        $product = new Product(['image' => 'some/image.jpg', 'talent_id' => 1]);
        $product->exists = true;
        $url = $product->image_url;
        $this->assertIsString($url);
        $this->assertStringNotContainsStringIgnoringCase('backblaze', $url);
    }
    public function test_product_image_url_absolute_does_not_throw_with_backblaze_disabled(): void
    {
        $this->disableBackblaze();
        $product = new Product(['image' => 'https://media.sevenrockradio.com/covers/album.jpg', 'talent_id' => 1]);
        $product->exists = true;
        $url = $product->image_url;
        $this->assertIsString($url);
        $this->assertStringContainsString('sevenrockradio.com', $url);
    }
    public function test_talent_album_cover_url_does_not_throw_with_backblaze_disabled(): void
    {
        $this->disableBackblaze();
        $album = new TalentAlbum(['cover_image' => 'albums/cover.jpg']);
        $album->exists = true;
        try {
            $url = $album->cover_url;
            $this->assertTrue(true);
        } catch (\Throwable $e) {
            $this->fail('cover_url lanzo excepcion inesperada: ' . $e->getMessage());
        }
    }
}
