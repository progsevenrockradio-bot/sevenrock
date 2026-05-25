<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BackblazeService
{
    /**
     * @return array{key:string,url:string}
     */
    public function upload(UploadedFile $file, string $path): array
    {
        $storedPath = Storage::disk('backblaze')->putFile($path, $file);
        $storedPath = is_string($storedPath) ? $storedPath : '';

        return [
            'key' => $storedPath,
            'url' => $storedPath !== '' ? Storage::disk('backblaze')->url($storedPath) : '',
        ];
    }

    public function delete(string $key): bool
    {
        return Storage::disk('backblaze')->delete($key);
    }
}
