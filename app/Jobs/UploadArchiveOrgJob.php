<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\ArchiveOrgPodcastServiceContract;
use App\Events\PodcastArchiveUploaded;
use App\Events\PodcastDeliveryVerified;
use App\Events\PodcastUploadFailed;
use App\Models\RadioProgram;
use App\Services\PodcastPipelineAuditService;
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

    public function handle(ArchiveOrgPodcastServiceContract $archiveOrgPodcastService): void
    {
        $radioProgram = RadioProgram::query()->with('masterProgram')->find($this->radioProgramId);
        if (! $radioProgram instanceof RadioProgram) {
            return;
        }

        $audit = app(PodcastPipelineAuditService::class);
        $attempt = method_exists($this, 'attempts') ? $this->attempts() : null;
        $startedAt = microtime(true);

        if (! $radioProgram->sync_archive_org) {
            $this->markSkipped($radioProgram, 'La sincronización con Archive.org está desactivada.');

            $audit->record($radioProgram, 'ARCHIVE_SKIPPED', 'Se omitió la subida a Archive.org.', [
                'program_id' => $radioProgram->id,
                'attempt' => $attempt,
                'reason' => 'sync_disabled',
            ]);

            return;
        }

        if (! $archiveOrgPodcastService->canSync()) {
            $this->markSkipped($radioProgram, 'Faltan credenciales de Archive.org.');

            $audit->record($radioProgram, 'ARCHIVE_SKIPPED', 'Se omitió la subida a Archive.org por credenciales faltantes.', [
                'program_id' => $radioProgram->id,
                'attempt' => $attempt,
                'reason' => 'missing_credentials',
            ]);

            return;
        }

        RadioProgram::withoutEvents(function () use ($radioProgram): void {
            $radioProgram->forceFill([
                'archive_started_at' => $radioProgram->archive_started_at ?? now(),
                'archive_org_status' => 'archive_pending',
                'status_message' => 'Subiendo a Archive.org.',
            ])->saveQuietly();
        });

        $audit->record($radioProgram, 'ARCHIVE_UPLOAD_STARTED', 'Inició la subida a Archive.org.', [
            'program_id' => $radioProgram->id,
            'attempt' => $attempt,
        ]);

        try {
            $archiveResult = $archiveOrgPodcastService->syncEpisode($radioProgram);
            $pendingIndexing = (bool) ($archiveResult['pending_indexing'] ?? false);
            $archiveStatus = $pendingIndexing ? 'archive_pending_indexing' : 'archive_verified';
            $deliveryStatus = $this->resolveDeliveryStatus($radioProgram, $archiveStatus);

            RadioProgram::withoutEvents(function () use ($radioProgram, $archiveResult, $archiveStatus, $deliveryStatus, $pendingIndexing): void {
                $radioProgram->forceFill([
                    'archive_org_status' => $archiveStatus,
                    'archive_org_remote_path' => $archiveResult['remote_path'] ?? $radioProgram->archive_org_remote_path,
                    'archive_org_uploaded_at' => $radioProgram->archive_org_uploaded_at ?? now(),
                    'archive_org_verified_at' => $pendingIndexing ? null : now(),
                    'archive_finished_at' => now(),
                    'archive_org_last_error' => null,
                    'archive_org_metadata' => array_merge((array) ($radioProgram->archive_org_metadata ?? []), [
                        'status' => $archiveStatus,
                        'synced_at' => $pendingIndexing ? null : now()->toIso8601String(),
                        'remote_path' => $archiveResult['remote_path'] ?? null,
                        'verification' => (array) ($archiveResult['verification'] ?? []),
                        'pending_indexing' => $pendingIndexing,
                    ]),
                    'delivery_status' => $deliveryStatus,
                    'delivery_verified_at' => $deliveryStatus === 'delivery_verified' ? now() : null,
                    'delivery_last_error' => null,
                    'delivery_metadata' => array_merge((array) ($radioProgram->delivery_metadata ?? []), [
                        'status' => $deliveryStatus,
                        'updated_at' => now()->toIso8601String(),
                        'radioboss_status' => $radioProgram->radioboss_status ?? null,
                        'archive_org_status' => $archiveStatus,
                        'radioboss_verified' => (bool) $radioProgram->enviado_radioboss,
                        'archive_verified' => $archiveStatus === 'archive_verified',
                        'archive_pending_indexing' => $pendingIndexing,
                        'archive_item_url' => $archiveResult['verification']['item_url'] ?? null,
                        'preserve_local_copy' => (bool) data_get($radioProgram->delivery_metadata, 'preserve_local_copy', false),
                    ]),
                    'status_message' => $pendingIndexing
                        ? 'Archive.org subido, en espera de indexación.'
                        : 'Archive.org sincronizado correctamente.',
                ])->saveQuietly();
            });

            $audit->record($radioProgram, 'ARCHIVE_UPLOAD_COMPLETED', 'La subida a Archive.org finalizó correctamente.', [
                'program_id' => $radioProgram->id,
                'attempt' => $attempt,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'pending_indexing' => $pendingIndexing,
            ]);

            if ($pendingIndexing) {
                $audit->record($radioProgram, 'ARCHIVE_PENDING_INDEXING', 'Archive.org quedó pendiente de indexación.', [
                    'program_id' => $radioProgram->id,
                    'attempt' => $attempt,
                ]);
            } else {
                $audit->record($radioProgram, 'ARCHIVE_VERIFIED', 'Archive.org quedó verificado.', [
                    'program_id' => $radioProgram->id,
                    'attempt' => $attempt,
                ]);
            }

            PodcastArchiveUploaded::dispatch($radioProgram->id);

            if (! $pendingIndexing && ($radioProgram->radioboss_status ?? null) === 'radioboss_verified') {
                PodcastDeliveryVerified::dispatch($radioProgram->id);
            }
        } catch (Throwable $exception) {
            $this->markFailure($radioProgram, $exception->getMessage());

            $audit->record($radioProgram, 'ERROR', 'Falló la subida a Archive.org.', [
                'program_id' => $radioProgram->id,
                'attempt' => $attempt,
                'exception' => $exception->getMessage(),
                'stage' => 'archive_org',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            PodcastUploadFailed::dispatch($radioProgram->id, 'archive_org', $exception->getMessage(), [
                'attempt' => $attempt,
            ]);

            return;
        }
    }

    private function markSkipped(RadioProgram $radioProgram, string $reason): void
    {
        RadioProgram::withoutEvents(function () use ($radioProgram, $reason): void {
            $radioProgram->forceFill([
                'archive_started_at' => $radioProgram->archive_started_at ?? now(),
                'archive_finished_at' => now(),
                'archive_org_status' => 'archive_skipped',
                'archive_org_verified_at' => null,
                'archive_org_last_error' => null,
                'archive_org_metadata' => array_merge((array) ($radioProgram->archive_org_metadata ?? []), [
                    'status' => 'archive_skipped',
                    'local_path' => (string) $radioProgram->archivo_mp3,
                    'reason' => $reason,
                ]),
                'status_message' => 'Archive.org omitido.',
                'delivery_status' => $this->resolveSkippedDeliveryStatus($radioProgram),
                'delivery_verified_at' => null,
                'delivery_last_error' => null,
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
                'archive_started_at' => $radioProgram->archive_started_at ?? now(),
                'archive_finished_at' => now(),
                'archive_org_status' => 'archive_error',
                'archive_org_verified_at' => null,
                'archive_org_last_error' => $message,
                'archive_org_metadata' => array_merge((array) ($radioProgram->archive_org_metadata ?? []), [
                    'status' => 'archive_error',
                    'local_path' => (string) $radioProgram->archivo_mp3,
                    'last_error' => $message,
                ]),
                'status_message' => 'Archive.org falló al sincronizar.',
                'delivery_status' => $this->resolveFailureDeliveryStatus($radioProgram),
                'delivery_verified_at' => null,
                'delivery_last_error' => $message,
            ])->saveQuietly();
        });
    }

    private function resolveDeliveryStatus(RadioProgram $radioProgram, string $archiveStatus): string
    {
        return $radioProgram->radioboss_status === 'radioboss_verified' && $archiveStatus === 'archive_verified'
            ? 'delivery_verified'
            : 'delivery_partial';
    }

    private function resolveSkippedDeliveryStatus(RadioProgram $radioProgram): string
    {
        return $radioProgram->radioboss_status === 'radioboss_verified'
            ? 'delivery_partial'
            : 'delivery_pending';
    }

    private function resolveFailureDeliveryStatus(RadioProgram $radioProgram): string
    {
        return $radioProgram->radioboss_status === 'radioboss_verified'
            ? 'delivery_partial'
            : 'delivery_failed';
    }
}
