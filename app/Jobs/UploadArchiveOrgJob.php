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

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 60];

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

            $archiveResult = $archiveOrgPodcastService->syncEpisode($radioProgram);
            $archiveVerification = (array) ($archiveResult['verification'] ?? []);
            $archiveStatus = (string) ($archiveResult['status'] ?? ($archiveVerification['pending_indexing'] ?? false ? 'pending' : 'synced'));
            $archivePendingIndexing = (bool) ($archiveResult['pending_indexing'] ?? ($archiveVerification['pending_indexing'] ?? false));

            RadioProgram::withoutEvents(function () use ($radioProgram, $archiveResult, $archiveVerification): void {
                $radioProgram->forceFill([
                    'archive_org_status' => (string) ($archiveResult['status'] ?? 'synced'),
                    'archive_org_remote_path' => $archiveResult['remote_path'] ?? $radioProgram->archive_org_remote_path,
                    'archive_org_uploaded_at' => now(),
                    'archive_org_verified_at' => (string) ($archiveResult['status'] ?? 'synced') === 'synced' ? now() : null,
                    'archive_org_last_error' => null,
                    'archive_org_metadata' => array_merge((array) ($radioProgram->archive_org_metadata ?? []), [
                        'status' => (string) ($archiveResult['status'] ?? 'synced'),
                        'synced_at' => (string) ($archiveResult['status'] ?? 'synced') === 'synced' ? now()->toIso8601String() : null,
                        'remote_path' => $archiveResult['remote_path'] ?? null,
                        'verification' => $archiveVerification,
                        'pending_indexing' => $archivePendingIndexing,
                    ]),
                    'status_message' => (string) ($archiveResult['status'] ?? 'synced') === 'pending'
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
