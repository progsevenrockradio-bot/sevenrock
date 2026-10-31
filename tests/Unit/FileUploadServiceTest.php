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
            'filesystems.disks.r2.key' => '',
            'filesystems.disks.r2.secret' => '',
            'filesystems.disks.r2.bucket' => '',
            'filesystems.disks.r2.url' => '',
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
            'filesystems.disks.r2.key' => '',
            'filesystems.disks.r2.secret' => '',
            'filesystems.disks.r2.bucket' => '',
            'filesystems.disks.r2.url' => '',
            'filesystems.disks.backblaze.enabled' => true,
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

    public function test_it_uses_r2_for_upload_read_and_url_when_configured(): void
    {
        Storage::fake('r2');
        config([
            'filesystems.disks.r2.key' => 'r2-key',
            'filesystems.disks.r2.secret' => 'r2-secret',
            'filesystems.disks.r2.bucket' => 'sevenrock-media',
            'filesystems.disks.r2.url' => 'https://media.sevenrockradio.com',
        ]);

        $service = app(FileUploadService::class);

        // 1. disk() contract returns R2
        $disk = $service->disk('r2');
        $this->assertInstanceOf(\Illuminate\Filesystem\FilesystemAdapter::class, $disk);

        // 2. Upload with implicit cloud selection (R2 configured)
        $file = UploadedFile::fake()->create('podcast-sample.mp3', 25, 'audio/mpeg');
        $uploadResult = $service->upload($file, 'podcasts');

        $this->assertSame('r2', $uploadResult['disk']);
        $this->assertNotEmpty($uploadResult['key']);
        $this->assertStringStartsWith('https://media.sevenrockradio.com/podcasts/', $uploadResult['url']);
        Storage::disk('r2')->assertExists($uploadResult['key']);

        // 3. UploadRaw with explicit R2
        $rawResult = $service->uploadRaw('raw binary mp3 content', 'podcasts/episode-raw.mp3', 'r2');
        $this->assertSame('r2', $rawResult['disk']);
        $this->assertSame('podcasts/episode-raw.mp3', $rawResult['key']);
        $this->assertSame('https://media.sevenrockradio.com/podcasts/episode-raw.mp3', $rawResult['url']);

        // 4. Read from R2
        $readContent = $service->read('podcasts/episode-raw.mp3', 'r2');
        $this->assertSame('raw binary mp3 content', $readContent);

        // 5. detectDisk identifies R2 when file exists on R2
        $detectedDisk = $service->detectDisk('podcasts/episode-raw.mp3');
        $this->assertSame('r2', $detectedDisk);
    }

    public function test_cloud_upload_does_not_fall_back_silently_to_public_on_remote_failure(): void
    {
        Storage::fake('public');
        config([
            'filesystems.disks.r2.key' => 'r2-key',
            'filesystems.disks.r2.secret' => 'r2-secret',
            'filesystems.disks.r2.bucket' => 'sevenrock-media',
            'filesystems.disks.r2.url' => 'https://media.sevenrockradio.com',
        ]);

        // Mock Storage R2 put failure
        Storage::shouldReceive('disk')->with('r2')->andThrow(new \RuntimeException('Connection timeout to Cloudflare'));
        Storage::shouldReceive('disk')->with('public')->andReturn(Storage::disk('public'));

        $service = app(FileUploadService::class);
        $file = UploadedFile::fake()->create('podcast.mp3', 10, 'audio/mpeg');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No se pudo subir el archivo.');

        // Must fail closed without saving permanently to public
        $service->upload($file, 'podcasts', 'r2');
    }

    public function test_disk_throws_exception_on_explicit_r2_initialization_failure(): void
    {
        config([
            'filesystems.disks.r2.key' => 'r2-key',
            'filesystems.disks.r2.secret' => 'r2-secret',
            'filesystems.disks.r2.bucket' => 'sevenrock-media',
            'filesystems.disks.r2.url' => 'https://media.sevenrockradio.com',
        ]);

        Storage::shouldReceive('disk')->with('r2')->andThrow(new \RuntimeException('Driver creation error for R2'));

        $service = app(FileUploadService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No se pudo inicializar el disco R2');

        $service->disk('r2');
    }

    public function test_disk_throws_exception_on_automatic_r2_initialization_failure(): void
    {
        config([
            'filesystems.disks.r2.key' => 'r2-key',
            'filesystems.disks.r2.secret' => 'r2-secret',
            'filesystems.disks.r2.bucket' => 'sevenrock-media',
            'filesystems.disks.r2.url' => 'https://media.sevenrockradio.com',
        ]);

        Storage::shouldReceive('disk')->with('r2')->andThrow(new \RuntimeException('Automatic resolution R2 failure'));

        $service = app(FileUploadService::class);

        // When preferred is null, R2 is automatically selected because it is configured.
        // It must NOT fall through to Storage::disk('public').
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No se pudo inicializar el disco R2');

        $service->disk(null);
    }
}
