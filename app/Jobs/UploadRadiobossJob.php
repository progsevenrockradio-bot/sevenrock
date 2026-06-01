<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\RadioProgram;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UploadRadiobossJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [2, 5, 10];

    public function __construct(
        public int $radioProgramId,
    ) {
    }

    public function handle(): void
    {
        $radioProgram = RadioProgram::query()->with('masterProgram')->find($this->radioProgramId);
        if (! $radioProgram instanceof RadioProgram) {
            return;
        }

        $sourcePath = trim((string) $radioProgram->archivo_mp3);
        if ($sourcePath === '') {
            $this->markFailure($radioProgram, 'No se pudo determinar el archivo procesado para RadioBOSS.');

            return;
        }

        $absolutePath = Storage::disk('public')->path($sourcePath);
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            $this->markFailure($radioProgram, "No se pudo leer el MP3 procesado: {$sourcePath}");

            return;
        }

        $ftpDisk = Storage::disk('radioboss');
        $folder = $this->resolveRemoteFolder($radioProgram);
        $remotePath = trim($folder, '/\\') . '/' . basename(str_replace('\\', '/', $sourcePath));
        $remotePath = trim(str_replace(['//', '\\'], ['/', '/'], $remotePath), '/');

        try {
            $ftpDisk->makeDirectory($folder);
            $this->clearRemoteFilesOnly($ftpDisk, $folder);

            $stream = fopen($absolutePath, 'rb');
            if (! is_resource($stream)) {
                $this->markFailure($radioProgram, "No se pudo abrir el MP3 para RadioBOSS: {$absolutePath}");

                return;
            }

            stream_set_chunk_size($stream, 5 * 1024 * 1024);

            $uploaded = false;

            try {
                $uploaded = (bool) $ftpDisk->writeStream($remotePath, $stream);

                if (! $uploaded) {
                    rewind($stream);
                    $uploaded = (bool) $ftpDisk->put($remotePath, stream_get_contents($stream) ?: '');
                }
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if (! $uploaded) {
                $this->markFailure($radioProgram, 'La subida FTP a RadioBOSS devolvió un resultado inválido.');

                return;
            }

            RadioProgram::withoutEvents(function () use ($radioProgram, $remotePath): void {
                $radioProgram->forceFill([
                    'enviado_radioboss' => true,
                    'radioboss_status' => 'verified',
                    'radioboss_verified_at' => now(),
                    'radioboss_last_error' => null,
                    'radioboss_metadata' => array_merge((array) ($radioProgram->radioboss_metadata ?? []), [
                        'status' => 'verified',
                        'remote_path' => $remotePath,
                        'local_path' => (string) $radioProgram->archivo_mp3,
                        'transfer_method' => 'writeStream_or_put',
                        'verified_at' => now()->toIso8601String(),
                    ]),
                    'status_message' => 'RadioBOSS verificado.',
                ])->saveQuietly();
            });
        } catch (Throwable $exception) {
            $this->markFailure($radioProgram, $exception->getMessage(), $remotePath ?? null);
        }
    }

    private function resolveRemoteFolder(RadioProgram $radioProgram): string
    {
        $folder = trim((string) ($radioProgram->ruta_ftp_radioboss ?: $radioProgram->masterProgram?->ruta_ftp ?: 'Programas'));
        $folder = str_replace(['..', '\\'], '', $folder);

        return trim($folder, '/\\') !== '' ? trim($folder, '/\\') : 'Programas';
    }

    /**
     * Borra solo archivos del folder remoto, sin eliminar la carpeta.
     */
    private function clearRemoteFilesOnly($disk, string $folder): void
    {
        try {
            foreach ((array) $disk->files($folder) as $remoteFile) {
                $remoteFile = trim((string) $remoteFile);
                if ($remoteFile === '') {
                    continue;
                }

                $disk->delete($remoteFile);
            }
        } catch (Throwable $exception) {
            Log::warning('UploadRadiobossJob: no se pudieron limpiar archivos remotos antiguos.', [
                'folder' => $folder,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function markFailure(RadioProgram $radioProgram, string $message, ?string $remotePath = null): void
    {
        Log::warning('UploadRadiobossJob: fallo la subida a RadioBOSS.', [
            'program_id' => $radioProgram->id,
            'remote_path' => $remotePath,
            'message' => $message,
        ]);

        RadioProgram::withoutEvents(function () use ($radioProgram, $message, $remotePath): void {
            $radioProgram->forceFill([
                'enviado_radioboss' => false,
                'radioboss_status' => 'error',
                'radioboss_verified_at' => null,
                'radioboss_last_error' => $message,
                'radioboss_metadata' => array_merge((array) ($radioProgram->radioboss_metadata ?? []), [
                    'status' => 'error',
                    'remote_path' => $remotePath,
                    'local_path' => (string) $radioProgram->archivo_mp3,
                    'last_error' => $message,
                ]),
                'status_message' => 'RadioBOSS no pudo completarse.',
            ])->saveQuietly();
        });
    }
}
