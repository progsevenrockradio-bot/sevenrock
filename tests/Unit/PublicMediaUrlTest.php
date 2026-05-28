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
}
