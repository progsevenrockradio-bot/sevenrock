<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class AdminPodcastPresignController extends Controller
{
    public function presign(Request $request): JsonResponse
    {
        $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'contentType' => ['nullable', 'string', 'max:100'],
            'attempt_id' => ['nullable', 'string', 'max:100'],
        ]);

        $attemptId = (string) $request->input('attempt_id', 'att_' . Str::random(12));
        $filename = (string) $request->input('filename');
        $rawContentType = strtolower(trim((string) $request->input('contentType', '')));

        // Aceptar variantes comunes de audio MP3 reportadas por navegadores y sistemas operativos
        $validAudioMimes = [
            'audio/mpeg',
            'audio/mp3',
            'audio/x-mp3',
            'audio/x-mpeg',
            'audio/mpg',
            'audio/mpeg3',
        ];

        $normalizedContentType = in_array($rawContentType, $validAudioMimes, true)
            ? 'audio/mpeg'
            : 'audio/mpeg';

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension !== 'mp3') {
            $extension = 'mp3';
        }

        // Generamos un identificador único para el archivo temporal en R2
        $uuid = Str::uuid()->toString();
        $safeName = Str::slug(pathinfo($filename, PATHINFO_FILENAME));
        if ($safeName === '') {
            $safeName = 'podcast';
        }
        $r2Key = "podcast-inbox/{$uuid}-{$safeName}.{$extension}";

        try {
            // Generar URL firmada válida por 60 minutos
            $presignedData = Storage::disk('r2')->temporaryUploadUrl(
                $r2Key,
                now()->addMinutes(60)
            );

            $urlStr = is_array($presignedData) ? ($presignedData['url'] ?? '') : (string) $presignedData;

            // Filtrar encabezados prohibidos: no enviar 'Host', 'Connection', etc. a navegadores
            $sanitizedHeaders = [];
            if (is_array($presignedData) && isset($presignedData['headers']) && is_array($presignedData['headers'])) {
                foreach ($presignedData['headers'] as $headerKey => $headerVal) {
                    $lowerKey = strtolower((string) $headerKey);
                    if (in_array($lowerKey, ['host', 'connection', 'content-length', 'date', 'user-agent'], true)) {
                        continue;
                    }
                    $sanitizedHeaders[$headerKey] = is_array($headerVal) ? implode(', ', $headerVal) : (string) $headerVal;
                }
            }

            // Asegurar que Content-Type normalizado esté definido para la subida
            $sanitizedHeaders['Content-Type'] = $normalizedContentType;

            Log::info('Podcast presign authorized', [
                'attempt_id' => $attemptId,
                'r2_key' => $r2Key,
                'content_type' => $normalizedContentType,
            ]);

            return response()->json([
                'url' => $urlStr,
                'method' => 'PUT',
                'headers' => $sanitizedHeaders,
                'fields' => (object)[],
                'key' => $r2Key,
                'attempt_id' => $attemptId,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Podcast presign failed', [
                'attempt_id' => $attemptId,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'error' => 'No se pudo generar la URL de subida a R2: ' . $exception->getMessage(),
            ], 500);
        }
    }
}
