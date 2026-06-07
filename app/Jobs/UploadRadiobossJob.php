<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\PodcastDeliveryVerified;
use App\Events\PodcastRadiobossUploaded;
use App\Events\PodcastUploadFailed;
use App\Models\RadioProgram;
use App\Services\PodcastPipelineAuditService;
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

        $audit = app(PodcastPipelineAuditService::class);
        $attempt = method_exists($this, 'attempts') ? $this->attempts() : null;
        $startedAt = microtime(true);

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

        RadioProgram::withoutEvents(function () use ($radioProgram): void {
            $radioProgram->forceFill([
                'radioboss_started_at' => $radioProgram->radioboss_started_at ?? now(),
                'radioboss_status' => 'radioboss_pending',
                'delivery_status' => 'delivery_partial',
                'status_message' => 'Subiendo a RadioBOSS.',
            ])->saveQuietly();
        });

        $audit->record($radioProgram, 'RADIOBOSS_UPLOAD_STARTED', 'Inició la subida a RadioBOSS.', [
            'program_id' => $radioProgram->id,
            'attempt' => $attempt,
            'folder' => $folder,
            'remote_path' => $remotePath,
        ]);

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
                    'radioboss_status' => 'radioboss_verified',
                    'radioboss_verified_at' => now(),
                    'radioboss_finished_at' => now(),
                    'radioboss_last_error' => null,
                    'radioboss_metadata' => array_merge((array) ($radioProgram->radioboss_metadata ?? []), [
                        'status' => 'radioboss_verified',
                        'remote_path' => $remotePath,
                        'local_path' => (string) $radioProgram->archivo_mp3,
                        'transfer_method' => 'writeStream_or_put',
                        'verified_at' => now()->toIso8601String(),
                    ]),
                    'status_message' => 'RadioBOSS verificado. Archive.org pendiente.',
                ])->saveQuietly();
            });

            $audit->record($radioProgram, 'RADIOBOSS_UPLOAD_COMPLETED', 'La subida a RadioBOSS finalizó correctamente.', [
                'program_id' => $radioProgram->id,
                'attempt' => $attempt,
                'folder' => $folder,
                'remote_path' => $remotePath,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            $audit->record($radioProgram, 'RADIOBOSS_VERIFIED', 'RadioBOSS quedó verificado.', [
                'program_id' => $radioProgram->id,
                'attempt' => $attempt,
            ]);

            PodcastRadiobossUploaded::dispatch($radioProgram->id);

            if (($radioProgram->archive_org_status ?? null) === 'archive_verified') {
                PodcastDeliveryVerified::dispatch($radioProgram->id);
            }
        } catch (Throwable $exception) {
            $this->markFailure($radioProgram, $exception->getMessage(), $remotePath ?? null);
            $audit->record($radioProgram, 'ERROR', 'Falló la subida a RadioBOSS.', [
                'program_id' => $radioProgram->id,
                'attempt' => $attempt,
                'exception' => $exception->getMessage(),
                'remote_path' => $remotePath,
                'stage' => 'radioboss',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            PodcastUploadFailed::dispatch($radioProgram->id, 'radioboss', $exception->getMessage(), [
                'remote_path' => $remotePath,
                'attempt' => $attempt,
            ]);

            return;
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
                'radioboss_status' => 'radioboss_error',
                'radioboss_verified_at' => null,
                'radioboss_finished_at' => now(),
                'radioboss_last_error' => $message,
                'radioboss_metadata' => array_merge((array) ($radioProgram->radioboss_metadata ?? []), [
                    'status' => 'radioboss_error',
                    'remote_path' => $remotePath,
                    'local_path' => (string) $radioProgram->archivo_mp3,
                    'last_error' => $message,
                ]),
                'delivery_status' => 'delivery_partial',
                'delivery_last_error' => $message,
                'status_message' => 'RadioBOSS no pudo completarse.',
            ])->saveQuietly();
        });
    }
}
