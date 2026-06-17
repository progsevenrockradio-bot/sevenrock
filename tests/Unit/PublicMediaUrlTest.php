<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PublicMediaUrl;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicMediaUrlTest extends TestCase
{
    public function test_it_resolves_an_explicit_disk_and_key_payload(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('catalog/posts/example.jpg', 'binary');

        $resolved = PublicMediaUrl::normalize([
            'disk' => 'public',
            'key' => 'catalog/posts/example.jpg',
        ]);

        $this->assertSame(Storage::disk('public')->url('catalog/posts/example.jpg'), $resolved);
    }

    public function test_it_rewrites_raw_backblaze_urls_to_custom_cloudflare_proxy(): void
    {
        config([
            'filesystems.disks.backblaze.url' => 'https://media.sevenrockradio.com/file/other-bucket',
            'filesystems.disks.backblaze.custom_url_resolves' => true,
        ]);

        $rawUrl = 'https://f003.backblazeb2.com/file/other-bucket/theme/logo.png';
        $expectedUrl = 'https://media.sevenrockradio.com/file/other-bucket/theme/logo.png';

        $resolved = PublicMediaUrl::normalize($rawUrl);

        $this->assertSame($expectedUrl, $resolved);
    }

    public function test_it_does_not_rewrite_if_custom_url_does_not_resolve(): void
    {
        config([
            'filesystems.disks.backblaze.url' => 'https://media.sevenrockradio.com/file/other-bucket',
            'filesystems.disks.backblaze.custom_url_resolves' => false,
        ]);

        $rawUrl = 'https://f003.backblazeb2.com/file/other-bucket/theme/logo.png';
        $expectedUrl = 'https://f003.backblazeb2.com/file/other-bucket/theme/logo.png';

        $resolved = PublicMediaUrl::normalize($rawUrl);

        $this->assertSame($expectedUrl, $resolved);
    }

    public function test_it_unconditionally_rewrites_legacy_b2_bucket_urls_to_r2_url(): void
    {
        config([
            'filesystems.disks.r2.url' => 'https://media.sevenrockradio.com',
        ]);

        $rawUrl = 'https://f003.backblazeb2.com/file/7RR-DATOS/theme/logo.png';
        $expectedUrl = 'https://media.sevenrockradio.com/theme/logo.png';

        $resolved = PublicMediaUrl::normalize($rawUrl);

        $this->assertSame($expectedUrl, $resolved);
    }
}

