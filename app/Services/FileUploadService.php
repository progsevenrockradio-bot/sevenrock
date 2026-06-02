<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use Zaxbux\BackblazeB2\Client;
use Zaxbux\BackblazeB2\Object\File\FileUploadMetadata;

class FileUploadService
{
    private const BACKBLAZE_LARGE_FILE_THRESHOLD_BYTES = 5242880;

    /**
     * @return array{disk:string,key:string,url:string}
     */
    public function upload(UploadedFile $file, string $path, ?string $disk = null): array
    {
        $fileSize = (int) ($file->getSize() ?? 0);

        if ($fileSize > self::BACKBLAZE_LARGE_FILE_THRESHOLD_BYTES && $this->isB2Configured()) {
            Log::info('FileUploadService: Large file detected, using stream-based upload', [
                'path' => $path,
                'size' => $fileSize,
                'threshold' => self::BACKBLAZE_LARGE_FILE_THRESHOLD_BYTES,
            ]);

            try {
                $result = $this->uploadLargeToBackblazeFromFile($file, $path);
                if ($result !== null) {
                    return $result;
                }
            } catch (Throwable $exception) {
                Log::warning('FileUploadService: Large file B2 upload failed, falling back to public disk.', [
                    'path' => $path,
                    'error' => $exception->getMessage(),
                ]);
                // Force public disk — don't retry B2
                return $this->attemptUpload($file, null, $path, 'public') ?? throw new RuntimeException('No se pudo subir el archivo.');
            }
        }

        $contents = @file_get_contents((string) $file->getRealPath());
        if (! is_string($contents) || $contents === '') {
            $contents = null;
        }

        $result = $this->attemptUpload($file, $contents, $path, $disk);
        if ($result !== null) {
            return $result;
        }

        throw new RuntimeException('No se pudo subir el archivo.');
    }

    /**
     * @return array{disk:string,key:string,url:string}
     */
    public function uploadRaw(string $contents, string $path, ?string $disk = null): array
    {
        $result = $this->attemptUpload(null, $contents, $path, $disk);
        if ($result !== null) {
            return $result;
        }

        throw new RuntimeException('No se pudo guardar el contenido proporcionado.');
    }

    public function delete(string $key, string $disk = 'backblaze'): bool
    {
        $key = $this->normalizeKey($key);
        if ($key === '') {
            return false;
        }

        $candidates = $this->candidateDisks($disk);
        foreach ($candidates as $candidate) {
            try {
                if (Storage::disk($candidate)->delete($key)) {
                    return true;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return false;
    }

    public function url(string $key, string $disk = 'backblaze'): string
    {
        $key = $this->normalizeKey($key);
        if ($key === '') {
            return '';
        }

        $disk = $this->normalizeDisk($disk);

        if ($disk === 'backblaze' && $this->isB2Configured()) {
            $b2Url = config('filesystems.disks.backblaze.url');
            if ($b2Url) {
                return rtrim($b2Url, '/') . '/' . ltrim($key, '/');
            }
        }

        try {
            return Storage::disk('public')->url($key);
        } catch (Throwable) {
            return asset($key);
        }
    }

    public function isB2Configured(): bool
    {
        return trim((string) config('filesystems.disks.backblaze.account_id', '')) !== ''
            && trim((string) config('filesystems.disks.backblaze.application_key', '')) !== ''
            && trim((string) config('filesystems.disks.backblaze.bucket_id', '')) !== ''
            && trim((string) config('filesystems.disks.backblaze.bucket_name', '')) !== ''
            && trim((string) config('filesystems.disks.backblaze.url', '')) !== '';
    }

    public function disk(string $preferred = 'backblaze'): FilesystemAdapter
    {
        $preferred = $this->normalizeDisk($preferred);

        if ($preferred === 'backblaze' && $this->isB2Configured()) {
            try {
                return Storage::disk('backblaze');
            } catch (Throwable) {
                // Fall through to public.
            }
        }

        return Storage::disk('public');
    }

    public function detectDisk(string $key, ?string $preferred = null): string
    {
        $key = $this->normalizeKey($key);
        if ($key === '') {
            return $this->normalizeDisk($preferred ?? 'public');
        }

        $candidates = [];

        if ($preferred !== null && trim($preferred) !== '') {
            $candidates[] = $this->normalizeDisk($preferred);
        }

        $candidates[] = 'public';

        if ($this->isB2Configured()) {
            $candidates[] = 'backblaze';
        }

        foreach (array_values(array_unique($candidates)) as $candidate) {
            try {
                if (Storage::disk($candidate)->exists($key)) {
                    return $candidate;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $preferred !== null ? $this->normalizeDisk($preferred) : ($this->isB2Configured() ? 'backblaze' : 'public');
    }

    public function localPath(string $key, ?string $disk = null): ?string
    {
        $key = $this->normalizeKey($key);
        if ($key === '') {
            return null;
        }

        $resolvedDisk = $this->detectDisk($key, $disk);

        if ($resolvedDisk === 'public') {
            $path = Storage::disk('public')->path($key);
            return is_file($path) ? $path : null;
        }

        if ($resolvedDisk !== 'backblaze' || ! $this->isB2Configured()) {
            return null;
        }

        try {
            $stream = Storage::disk('backblaze')->readStream($key);
            if (! is_resource($stream)) {
                return null;
            }

            $directory = storage_path('app/tmp/backblaze');
            File::ensureDirectoryExists($directory);

            $tmpPath = $directory . DIRECTORY_SEPARATOR . Str::uuid()->toString() . '-' . basename($key);
            $handle = fopen($tmpPath, 'w+b');
            if ($handle === false) {
                fclose($stream);

                return null;
            }

            try {
                if (stream_copy_to_stream($stream, $handle) === false) {
                    fclose($handle);
                    @unlink($tmpPath);

                    return null;
                }
            } finally {
                fclose($handle);
                fclose($stream);
            }

            return $tmpPath;
        } catch (Throwable) {
            return null;
        }
    }

    public function read(string $key, ?string $disk = null): ?string
    {
        $key = $this->normalizeKey($key);
        if ($key === '') {
            return null;
        }

        $resolvedDisk = $this->detectDisk($key, $disk);

        try {
            if ($resolvedDisk === 'public') {
                $path = Storage::disk('public')->path($key);

                return is_file($path) ? (string) file_get_contents($path) : null;
            }

            if ($resolvedDisk === 'backblaze' && $this->isB2Configured()) {
                $contents = Storage::disk('backblaze')->get($key);

                return is_string($contents) ? $contents : null;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @return array{disk:string,key:string,url:string}|null
     */
    private function attemptUpload(?UploadedFile $file, ?string $contents, string $path, ?string $disk): ?array
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '') {
            return null;
        }

        $disks = $this->candidateDisks($disk);
        $contentSize = is_string($contents) ? strlen($contents) : null;

        foreach ($disks as $candidate) {
            try {
                if ($candidate === 'backblaze' && $this->isB2Configured()) {
                    $fileSize = $file instanceof UploadedFile ? (int) ($file->getSize() ?? 0) : ($contentSize ?? 0);

                    if ($fileSize > self::BACKBLAZE_LARGE_FILE_THRESHOLD_BYTES) {
                        Log::info('FileUploadService: Large file upload to B2', [
                            'path' => $path,
                            'disk' => $candidate,
                            'size' => $fileSize,
                        ]);

                        $result = $file instanceof UploadedFile
                            ? $this->uploadLargeToBackblazeFromFile($file, $path)
                            : $this->uploadLargeToBackblazeFromContents((string) $contents, $path);

                        Log::info('FileUploadService: Large file upload to B2 completed', [
                            'path' => $path,
                            'disk' => $candidate,
                            'size' => $fileSize,
                            'key' => $result['key'],
                        ]);

                        return $result;
                    }
                }

                if ($file instanceof UploadedFile) {
                    $storedPath = trim($path . '/' . $file->hashName(), '/');
                    $fileContents = $contents;

                    if (! is_string($fileContents)) {
                        $fileContents = @file_get_contents((string) $file->getRealPath());
                    }

                    if (! is_string($fileContents)) {
                        $storedPath = '';
                    } else {
                        $stored = $this->storeContentsOnDisk($candidate, $storedPath, $fileContents);
                        $storedPath = $stored ? trim(str_replace('\\', '/', $storedPath), '/') : '';
                    }
                } else {
                    $storedPath = $path;
                    $stored = $this->storeContentsOnDisk($candidate, $storedPath, $contents ?? '');
                    $storedPath = $stored ? $storedPath : '';
                }

                if ($storedPath === '') {
                    continue;
                }

                return [
                    'disk' => $candidate,
                    'key' => $storedPath,
                    'url' => $this->url($storedPath, $candidate),
                ];
            } catch (Throwable $exception) {
                Log::warning('FileUploadService: upload attempt failed', [
                    'path' => $path,
                    'disk' => $candidate,
                    'content_size' => $contentSize,
                    'file_size' => $file instanceof UploadedFile ? $this->uploadedFileSize($file) : null,
                    'exception_class' => get_class($exception),
                    'exception' => $exception->getMessage(),
                ]);

                continue;
            }
        }

        return null;
    }

    private function uploadLargeToBackblazeFromFile(UploadedFile $file, string $path): array
    {
        $realPath = (string) $file->getRealPath();
        if ($realPath === '' || ! is_file($realPath) || ! is_readable($realPath)) {
            throw new RuntimeException("No se pudo leer el archivo local para Backblaze: {$path}");
        }

        $stream = fopen($realPath, 'rb');
        if ($stream === false) {
            throw new RuntimeException("No se pudo abrir el archivo local para Backblaze: {$path}");
        }

        try {
            return $this->uploadLargeToBackblazeFromStream($stream, $path, $this->uploadedFileSize($file));
        } finally {
            fclose($stream);
        }
    }

    private function uploadLargeToBackblazeFromContents(string $contents, string $path): array
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('No se pudo preparar el contenido para la subida a Backblaze.');
        }

        try {
            if (fwrite($stream, $contents) === false) {
                throw new RuntimeException('No se pudo escribir el contenido temporal para la subida a Backblaze.');
            }

            rewind($stream);

            return $this->uploadLargeToBackblazeFromStream($stream, $path, strlen($contents));
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param resource $stream
     * @return array{disk:string,key:string,url:string}
     */
    private function uploadLargeToBackblazeFromStream($stream, string $path, int $contentSize): array
    {
        if (! is_resource($stream)) {
            throw new RuntimeException('No se pudo preparar el stream para la subida a Backblaze.');
        }

        if (! $this->isB2Configured()) {
            throw new RuntimeException('Backblaze B2 no está configurado.');
        }

        $bucketId = trim((string) config('filesystems.disks.backblaze.bucket_id', ''));
        if ($bucketId === '') {
            throw new RuntimeException('Backblaze B2 no tiene bucket_id configurado.');
        }

        $client = $this->createBackblazeClient();
        $client->refreshAccountAuthorization();
        $authorization = $client->accountAuthorization();

        if ($authorization === null) {
            throw new RuntimeException('Backblaze B2 no devolvió autorización para la subida grande.');
        }

        $absoluteMinimumPartSize = max(1, (int) ($authorization->absoluteMinimumPartSize() ?? self::BACKBLAZE_LARGE_FILE_THRESHOLD_BYTES));
        $recommendedPartSize = max($absoluteMinimumPartSize, (int) ($authorization->recommendedPartSize() ?? $absoluteMinimumPartSize));
        $maxRecommendedPartSize = (int) max($recommendedPartSize, (int) ceil($contentSize / 10000));
        $partSize = max($absoluteMinimumPartSize, $maxRecommendedPartSize);

        rewind($stream);

        $file = $client->startLargeFile($path, $bucketId, \Zaxbux\BackblazeB2\Object\File::CONTENT_TYPE_AUTO);
        $uploadPartUrl = $client->getUploadPartUrl((string) $file->id());
        $partHashes = [];
        $partNumber = 1;
        $bytesUploaded = 0;

        while (! feof($stream)) {
            $chunk = fread($stream, $partSize);
            if ($chunk === false || $chunk === '') {
                break;
            }

            $metadata = FileUploadMetadata::fromResource($chunk);
            $client->uploadPart($chunk, (string) $file->id(), $partNumber, null, $uploadPartUrl, $metadata);

            $partHashes[] = $metadata->sha1();
            $bytesUploaded += $metadata->length();
            $partNumber++;
        }

        $finishedFile = $client->finishLargeFile((string) $file->id(), $partHashes);
        $storedPath = trim(str_replace('\\', '/', (string) ($finishedFile->name() ?? $path)), '/');
        $url = $this->url($storedPath, 'backblaze');

        Log::info('FileUploadService: Backblaze large upload finalized', [
            'path' => $path,
            'stored_path' => $storedPath,
            'size' => $contentSize,
            'uploaded_size' => $bytesUploaded,
            'part_size' => $partSize,
            'parts' => count($partHashes),
            'file_id' => $finishedFile->id(),
        ]);

        return [
            'disk' => 'backblaze',
            'key' => $storedPath,
            'url' => $url,
        ];
    }

    private function createBackblazeClient(): Client
    {
        if (! $this->isB2Configured()) {
            throw new RuntimeException('Backblaze B2 no está configurado.');
        }

        return new Client([
            'applicationKeyId' => (string) config('filesystems.disks.backblaze.account_id', ''),
            'applicationKey' => (string) config('filesystems.disks.backblaze.application_key', ''),
        ]);
    }

    private function storeContentsOnDisk(string $disk, string $path, string $contents): bool
    {
        try {
            if (Storage::disk($disk)->put($path, $contents)) {
                return true;
            }
        } catch (Throwable) {
            // Fall through to the filesystem fallback below.
        }

        try {
            $absolutePath = Storage::disk($disk)->path($path);
            File::ensureDirectoryExists(dirname($absolutePath));

            return file_put_contents($absolutePath, $contents) !== false;
        } catch (Throwable) {
            return false;
        }
    }

    private function uploadedFileSize(UploadedFile $file): int
    {
        $size = $file->getSize();
        if (is_int($size) && $size > 0) {
            return $size;
        }

        $realPath = (string) $file->getRealPath();
        if ($realPath !== '' && is_file($realPath)) {
            return (int) filesize($realPath);
        }

        return 0;
    }

    /**
     * @return array<int, string>
     */
    private function candidateDisks(?string $preferred): array
    {
        $candidates = [];

        if ($preferred !== null && trim($preferred) !== '') {
            $candidates[] = $this->normalizeDisk($preferred);
        } elseif ($this->isB2Configured()) {
            $candidates[] = 'backblaze';
        }

        $candidates[] = 'public';

        return array_values(array_unique(array_filter($candidates)));
    }

    private function normalizeDisk(?string $disk): string
    {
        $disk = strtolower(trim((string) $disk));

        return match ($disk) {
            'backblaze-b2' => 'backblaze',
            'local' => 'public',
            default => $disk !== '' ? $disk : 'public',
        };
    }

    private function normalizeKey(string $path): string
    {
        return trim(str_replace('\\', '/', $path), '/');
    }
}
