<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class AdminPodcastChunkUploadController extends Controller
{
    public function uploadChunk(Request $request): JsonResponse
    {
        $request->validate([
            'upload_id' => ['required', 'string', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'chunk_index' => ['required', 'integer', 'min:0'],
            'total_chunks' => ['required', 'integer', 'min:1'],
            'file' => ['required', 'file', 'max:51200'], // 50 MB max per chunk
        ]);

        $uploadId = (string) $request->input('upload_id');
        $chunkIndex = (int) $request->input('chunk_index');

        $chunkDir = storage_path("app/podcast-chunks/{$uploadId}");
        File::ensureDirectoryExists($chunkDir);

        $chunkFile = $request->file('file');
        if ($chunkFile === null || ! $chunkFile->isValid()) {
            return response()->json([
                'error' => 'No se pudo recibir el fragmento.',
            ], 400);
        }

        $chunkFile->move($chunkDir, "chunk_{$chunkIndex}.part");

        return response()->json([
            'status' => 'chunk_uploaded',
            'upload_id' => $uploadId,
            'chunk_index' => $chunkIndex,
        ]);
    }

    public function completeChunk(Request $request): JsonResponse
    {
        $request->validate([
            'upload_id' => ['required', 'string', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'total_chunks' => ['required', 'integer', 'min:1'],
            'filename' => ['required', 'string'],
        ]);

        $uploadId = (string) $request->input('upload_id');
        $totalChunks = (int) $request->input('total_chunks');
        $filename = (string) $request->input('filename');

        $chunkDir = storage_path("app/podcast-chunks/{$uploadId}");

        if (! File::isDirectory($chunkDir)) {
            return response()->json([
                'error' => 'No se encontraron las partes de la subida.',
            ], 422);
        }

        for ($i = 0; $i < $totalChunks; $i++) {
            $partPath = "{$chunkDir}/chunk_{$i}.part";
            if (! file_exists($partPath)) {
                return response()->json([
                    'error' => "Falta la parte {$i} de {$totalChunks}.",
                ], 422);
            }
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension !== 'mp3') {
            $extension = 'mp3';
        }

        $safeName = Str::slug(pathinfo($filename, PATHINFO_FILENAME));
        if ($safeName === '') {
            $safeName = 'episode';
        }

        $uniqueName = sprintf('%s-%s-%s.%s', now()->format('Y-m-d'), $safeName, Str::lower(Str::uuid()->toString()), $extension);
        $finalRelPath = "podcast-inbox/{$uniqueName}";

        $finalDisk = Storage::disk('public');
        $finalDisk->makeDirectory('podcast-inbox');
        $finalFullPath = $finalDisk->path($finalRelPath);

        $outHandle = fopen($finalFullPath, 'wb');
        if ($outHandle === false) {
            return response()->json([
                'error' => 'No se pudo crear el archivo final en el servidor.',
            ], 500);
        }

        for ($i = 0; $i < $totalChunks; $i++) {
            $partPath = "{$chunkDir}/chunk_{$i}.part";
            $inHandle = fopen($partPath, 'rb');
            if ($inHandle !== false) {
                stream_copy_to_stream($inHandle, $outHandle);
                fclose($inHandle);
            }
            @unlink($partPath);
        }

        fclose($outHandle);
        @rmdir($chunkDir);

        return response()->json([
            'status' => 'completed',
            'chunked_key' => $finalRelPath,
        ]);
    }
}
