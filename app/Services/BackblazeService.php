<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;

class BackblazeService
{
    /**
     * @return array{key:string,url:string}
     */
    public function upload(UploadedFile $file, string $path): array
    {
        $result = app(FileUploadService::class)->upload($file, $path);

        return [
            'key' => $result['key'],
            'url' => $result['url'],
        ];
    }

    public function delete(string $key): bool
    {
        return app(FileUploadService::class)->delete($key);
    }
}
