<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class FileUploadService
{
    /**
     * @return array{disk:string,key:string,url:string}
     */
    public function upload(UploadedFile $file, string $path, ?string $disk = null): array
    {
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
            try {
                return Storage::disk('backblaze')->url($key);
            } catch (Throwable) {
                // Fall through to public storage.
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

        foreach ($disks as $candidate) {
            try {
                if ($file instanceof UploadedFile) {
                    $storedPath = Storage::disk($candidate)->putFile($path, $file);
                    $storedPath = is_string($storedPath) ? trim(str_replace('\\', '/', $storedPath), '/') : '';
                } else {
                    $stored = Storage::disk($candidate)->put($path, $contents ?? '');
                    $storedPath = $stored ? $path : '';
                }

                if ($storedPath === '') {
                    continue;
                }

                return [
                    'disk' => $candidate,
                    'key' => $storedPath,
                    'url' => $this->url($storedPath, $candidate),
                ];
            } catch (Throwable) {
                continue;
            }
        }

        return null;
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
