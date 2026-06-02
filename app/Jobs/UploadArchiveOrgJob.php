<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\RadioProgram;
use App\Services\ArchiveOrgPodcastService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class UploadArchiveOrgJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [120, 300, 600, 1800];

    public function __construct(
        public int $radioProgramId,
    ) {
    }

    public function handle(ArchiveOrgPodcastService $archiveOrgPodcastService): void
    {
        $radioProgram = RadioProgram::query()->with('masterProgram')->find($this->radioProgramId);
        if (! $radioProgram instanceof RadioProgram) {
            return;
        }

        try {
            if (! $radioProgram->sync_archive_org) {
                $this->markSkipped($radioProgram, 'La sincronización con Archive.org está desactivada.');

                return;
            }

            if (! $archiveOrgPodcastService->canSync()) {
                $this->markSkipped($radioProgram, 'Faltan credenciales de Archive.org.');

                return;
            }

            $isRetry = $radioProgram->archive_org_remote_path !== null;

            // Paso 1: Subir el archivo (solo primera vez)
            if (! $isRetry) {
                $archiveResult = $archiveOrgPodcastService->syncEpisode($radioProgram);
            } else {
                $archiveResult = [
                    'status' => $radioProgram->archive_org_status ?? 'pending',
                    'remote_path' => $radioProgram->archive_org_remote_path,
                    'verification' => (array) (data_get($radioProgram->archive_org_metadata, 'verification', [])),
                    'pending_indexing' => true,
                ];
            }

            // Paso 2: Aplicar metadata via PATCH (con reintento automático si falla)
            try {
                $archiveOrgPodcastService->patchFileMetadata($radioProgram);
                // PATCH exitoso
                $archiveResult['status'] = 'synced';
                $archiveResult['pending_indexing'] = false;
            } catch (Throwable $patchException) {
                Log::warning('UploadArchiveOrgJob: metadata PATCH falló, reintentando en 3 minutos.', [
                    'program_id' => $radioProgram->id,
                    'message' => $patchException->getMessage(),
                ]);

                RadioProgram::withoutEvents(function () use ($radioProgram): void {
                    $radioProgram->forceFill([
                        'archive_org_status' => 'pending',
                        'status_message' => 'Archive.org: metadata pendiente de indexación, reintentando...',
                    ])->saveQuietly();
                });

                $this->release(180); // Reintentar en 3 minutos

                return;
            }

            // Paso 3: Actualizar registro (éxito)
            $archiveVerification = (array) ($archiveResult['verification'] ?? []);
            $archiveStatus = (string) ($archiveResult['status'] ?? 'synced');
            $archivePendingIndexing = (bool) ($archiveResult['pending_indexing'] ?? false);

            RadioProgram::withoutEvents(function () use ($radioProgram, $archiveResult, $archiveVerification, $archivePendingIndexing, $archiveStatus): void {
                $radioProgram->forceFill([
                    'archive_org_status' => $archiveStatus,
                    'archive_org_remote_path' => $archiveResult['remote_path'] ?? $radioProgram->archive_org_remote_path,
                    'archive_org_uploaded_at' => $radioProgram->archive_org_uploaded_at ?? now(),
                    'archive_org_verified_at' => $archiveStatus === 'synced' ? now() : null,
                    'archive_org_last_error' => null,
                    'archive_org_metadata' => array_merge((array) ($radioProgram->archive_org_metadata ?? []), [
                        'status' => $archiveStatus,
                        'synced_at' => $archiveStatus === 'synced' ? now()->toIso8601String() : null,
                        'remote_path' => $archiveResult['remote_path'] ?? null,
                        'verification' => $archiveVerification,
                        'pending_indexing' => $archivePendingIndexing,
                    ]),
                    'status_message' => $archiveStatus === 'pending'
                        ? 'Archive.org subido, en espera de indexación.'
                        : 'Archive.org sincronizado correctamente.',
                ])->saveQuietly();
            });
        } catch (Throwable $exception) {
            $this->markFailure($radioProgram, $exception->getMessage());
        }
    }

    private function markSkipped(RadioProgram $radioProgram, string $reason): void
    {
        RadioProgram::withoutEvents(function () use ($radioProgram, $reason): void {
            $radioProgram->forceFill([
                'archive_org_status' => 'skipped',
                'archive_org_verified_at' => null,
                'archive_org_last_error' => null,
                'archive_org_metadata' => array_merge((array) ($radioProgram->archive_org_metadata ?? []), [
                    'status' => 'skipped',
                    'local_path' => (string) $radioProgram->archivo_mp3,
                    'reason' => $reason,
                ]),
                'status_message' => 'Archive.org omitido.',
            ])->saveQuietly();
        });
    }

    private function markFailure(RadioProgram $radioProgram, string $message): void
    {
        Log::warning('UploadArchiveOrgJob: fallo la subida a Archive.org', [
            'program_id' => $radioProgram->id,
            'exception' => $message,
        ]);

        RadioProgram::withoutEvents(function () use ($radioProgram, $message): void {
            $radioProgram->forceFill([
                'archive_org_status' => 'error',
                'archive_org_verified_at' => null,
                'archive_org_last_error' => $message,
                'archive_org_metadata' => array_merge((array) ($radioProgram->archive_org_metadata ?? []), [
                    'status' => 'error',
                    'local_path' => (string) $radioProgram->archivo_mp3,
                    'last_error' => $message,
                ]),
                'status_message' => 'Archive.org falló al sincronizar.',
            ])->saveQuietly();
        });
    }
}
