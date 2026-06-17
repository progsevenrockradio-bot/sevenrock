<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class AdminPodcastPresignController extends Controller
{
    public function presign(Request $request): JsonResponse
    {
        $request->validate([
            'filename' => ['required', 'string'],
            'contentType' => ['required', 'string', 'in:audio/mpeg,audio/mp3'],
        ]);

        $filename = $request->input('filename');
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension !== 'mp3') {
            $extension = 'mp3';
        }

        // Generamos un identificador único para el archivo temporal en R2
        $uuid = Str::uuid()->toString();
        $safeName = Str::slug(pathinfo($filename, PATHINFO_FILENAME));
        $r2Key = "podcast-inbox/{$uuid}-{$safeName}.{$extension}";

        try {
            // Generar URL firmada válida por 60 minutos
            $url = Storage::disk('r2')->temporaryUploadUrl(
                $r2Key,
                now()->addMinutes(60)
            );

            return response()->json([
                'url' => $url,
                'method' => 'PUT',
                'headers' => [
                    'Content-Type' => $request->input('contentType'),
                ],
                'fields' => (object)[],
                'key' => $r2Key,
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'error' => 'No se pudo generar la URL de subida a R2: ' . $exception->getMessage(),
            ], 500);
        }
    }
}
