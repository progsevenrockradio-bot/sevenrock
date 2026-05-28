<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileUploadServiceTest extends TestCase
{
    public function test_it_falls_back_to_public_when_backblaze_is_not_configured(): void
    {
        Storage::fake('public');
        config([
            'filesystems.disks.backblaze.account_id' => '',
            'filesystems.disks.backblaze.application_key' => '',
            'filesystems.disks.backblaze.bucket_id' => '',
            'filesystems.disks.backblaze.bucket_name' => '',
            'filesystems.disks.backblaze.url' => '',
        ]);

        $file = UploadedFile::fake()->create('cover.jpg', 12, 'image/jpeg');
        $result = app(FileUploadService::class)->upload($file, 'catalog/posts');

        $this->assertSame('public', $result['disk']);
        $this->assertNotEmpty($result['key']);
        Storage::disk('public')->assertExists($result['key']);
    }

    public function test_it_uses_backblaze_when_it_is_configured(): void
    {
        Storage::fake('backblaze');
        config([
            'filesystems.disks.backblaze.account_id' => 'key-id',
            'filesystems.disks.backblaze.application_key' => 'app-key',
            'filesystems.disks.backblaze.bucket_id' => 'bucket-id',
            'filesystems.disks.backblaze.bucket_name' => 'sevenrock-radio',
            'filesystems.disks.backblaze.url' => 'https://s3.us-east-005.backblazeb2.com/sevenrock-radio',
        ]);

        $file = UploadedFile::fake()->create('cover.jpg', 12, 'image/jpeg');
        $result = app(FileUploadService::class)->upload($file, 'catalog/posts');

        $this->assertSame('backblaze', $result['disk']);
        $this->assertNotEmpty($result['key']);
        Storage::disk('backblaze')->assertExists($result['key']);
    }
}
