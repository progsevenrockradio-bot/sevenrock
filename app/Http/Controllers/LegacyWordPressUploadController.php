<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\PublicMediaUrl;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

final class LegacyWordPressUploadController extends Controller
{
    public function show(string $path): Response
    {
        $relative = ltrim(str_replace('\\', '/', $path), '/');
        $candidate = PublicMediaUrl::resolveLegacyWordPressUploadFilesystemPath($relative);

        abort_if($candidate === null || ! File::exists($candidate), 404);

        return response()->file($candidate, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
