<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Jobs\Concerns\InteractsWithPodcastUploadPipeline;
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
    use InteractsWithPodcastUploadPipeline;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 60];

    public function __construct(
        public int $radioProgramId,
        public string $localPath,
    ) {
    }

    public function handle(ArchiveOrgPodcastService $archiveOrgPodcastService): void
    {
        $radioProgram = RadioProgram::query()->with('masterProgram')->findOrFail($this->radioProgramId);

        try {
            if (! $radioProgram->sync_archive_org) {
                RadioProgram::withoutEvents(fn (): bool => (bool) $radioProgram->update([
                    'archive_org_status' => 'skipped',
                    'archive_org_verified_at' => null,
                    'archive_org_last_error' => null,
                    'archive_org_metadata' => array_merge((array) ($radioProgram->archive_org_metadata ?? []), [
                        'status' => 'skipped',
                        'local_path' => $this->localPath,
                        'reason' => 'La sincronización con Archive.org está desactivada.',
                    ]),
                    'status_message' => 'Archive.org desactivado para este episodio.',
                ]));

                $this->dispatchDeliveryNotification($radioProgram->id);

                return;
            }

            if (! $archiveOrgPodcastService->canSync()) {
                RadioProgram::withoutEvents(fn (): bool => (bool) $radioProgram->update([
                    'archive_org_status' => 'skipped',
                    'archive_org_verified_at' => null,
                    'archive_org_last_error' => null,
                    'archive_org_metadata' => array_merge((array) ($radioProgram->archive_org_metadata ?? []), [
                        'status' => 'skipped',
                        'local_path' => $this->localPath,
                        'reason' => 'Faltan credenciales de Archive.org.',
                    ]),
                    'status_message' => 'Archive.org omitido por credenciales incompletas.',
                ]));

                $this->dispatchDeliveryNotification($radioProgram->id);

                return;
            }

            $archiveResult = $archiveOrgPodcastService->syncEpisode($radioProgram);
            $archiveVerification = (array) ($archiveResult['verification'] ?? []);

            RadioProgram::withoutEvents(fn (): bool => (bool) $radioProgram->update([
                'archive_org_status' => 'synced',
                'archive_org_remote_path' => $archiveResult['remote_path'] ?? $radioProgram->archive_org_remote_path,
                'archive_org_uploaded_at' => now(),
                'archive_org_verified_at' => now(),
                'archive_org_last_error' => null,
                'archive_org_metadata' => array_merge((array) ($radioProgram->archive_org_metadata ?? []), [
                    'status' => 'synced',
                    'synced_at' => now()->toIso8601String(),
                    'remote_path' => $archiveResult['remote_path'] ?? null,
                    'verification' => $archiveVerification,
                ]),
                'status_message' => 'Archive.org sincronizado correctamente.',
            ]));

            $this->dispatchDeliveryNotification($radioProgram->id);
        } catch (Throwable $exception) {
            RadioProgram::withoutEvents(fn (): bool => (bool) $radioProgram->update([
                'archive_org_status' => 'error',
                'archive_org_verified_at' => null,
                'archive_org_last_error' => $exception->getMessage(),
                'archive_org_metadata' => array_merge((array) ($radioProgram->archive_org_metadata ?? []), [
                    'status' => 'error',
                    'local_path' => $this->localPath,
                    'last_error' => $exception->getMessage(),
                ]),
                'status_message' => 'Archive.org falló al sincronizar.',
            ]));

            Log::warning('UploadArchiveOrgJob: fallo la subida a Archive.org', [
                'program_id' => $radioProgram->id,
                'exception' => $exception->getMessage(),
            ]);

            $this->dispatchDeliveryNotification($radioProgram->id);

            throw $exception;
        }
    }
}
