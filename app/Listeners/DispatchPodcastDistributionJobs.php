<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PodcastProcessed;
use App\Jobs\UploadArchiveOrgJob;
use App\Jobs\UploadRadiobossJob;
use App\Models\RadioProgram;
use App\Services\PodcastPipelineAuditService;
use Illuminate\Contracts\Events\ShouldBeDiscovered;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

final class DispatchPodcastDistributionJobs implements ShouldBeDiscovered
{
    public static function shouldBeDiscovered(): bool
    {
        return false;
    }

    public function __construct(
        private readonly PodcastPipelineAuditService $auditService,
    ) {
    }

    public function handle(PodcastProcessed $event): void
    {
        $program = RadioProgram::query()->with('masterProgram')->find($event->radioProgramId);
        if (! $program instanceof RadioProgram) {
            return;
        }

        $storedPath = ltrim(str_replace('\\', '/', trim((string) ($program->archivo_mp3 ?? ''))), '/');
        $disk = trim((string) ($program->archivo_mp3_disk ?: 'r2'));

        if (
            $storedPath === ''
            || ! RadioProgram::isProcessedStoragePath($storedPath)
            || ! $program->hasPreparedBroadcastAudio()
            || ! Storage::disk($disk)->exists($storedPath)
        ) {
            $errorMessage = sprintf(
                "No se puede iniciar la distribución: el archivo MP3 ('%s') no es una versión procesada definitiva verificada.",
                $storedPath !== '' ? $storedPath : 'vacío'
            );

            RadioProgram::withoutEvents(function () use ($program, $errorMessage): void {
                $metadata = (array) ($program->delivery_metadata ?? []);
                unset($metadata['requested_distribution_target']);

                $program->forceFill([
                    'enviado_radioboss' => false,
                    'radioboss_status' => $program->radioboss_status === 'radioboss_verified' ? 'radioboss_verified' : 'skipped',
                    'delivery_status' => 'delivery_failed',
                    'delivery_last_error' => $errorMessage,
                    'status_message' => RadioProgram::sanitizeStatusMessage($errorMessage),
                    'delivery_metadata' => $metadata,
                ])->saveQuietly();
            });

            $this->auditService->record($program, 'ERROR', $errorMessage, [
                'stage' => 'distribution_dispatch_guard',
                'archivo_mp3' => $storedPath,
                'archivo_mp3_disk' => $disk,
            ]);

            return;
        }

        $metadata = (array) ($program->delivery_metadata ?? []);
        $requestedTarget = (string) ($metadata['requested_distribution_target'] ?? 'all');
        unset($metadata['requested_distribution_target']);

        $alreadyVerifiedRadioboss = $program->radioboss_status === 'radioboss_verified' && (bool) $program->enviado_radioboss;
        $alreadyVerifiedArchive = in_array($program->archive_org_status, ['archive_verified', 'archive_pending_indexing'], true);

        $dispatchRadioboss = in_array($requestedTarget, ['all', 'radioboss', ''], true) && ! $alreadyVerifiedRadioboss;
        $dispatchArchive = in_array($requestedTarget, ['all', 'archive', ''], true) && ! $alreadyVerifiedArchive;

        if (! $dispatchRadioboss && ! $dispatchArchive) {
            // Asegurar que el metadato transitorio quede limpio incluso si no se disparan tareas
            if (array_key_exists('requested_distribution_target', (array) ($program->delivery_metadata ?? []))) {
                RadioProgram::withoutEvents(function () use ($program, $metadata): void {
                    $program->forceFill(['delivery_metadata' => $metadata])->saveQuietly();
                });
            }

            return;
        }

        $this->auditService->record($program, 'PROCESSING_COMPLETED', 'El MP3 fue procesado y quedó listo para distribución.');

        RadioProgram::withoutEvents(function () use ($program, $metadata, $dispatchRadioboss, $dispatchArchive): void {
            $updates = [
                'processing_finished_at' => $program->processing_finished_at ?? now(),
                'delivery_status' => 'delivery_pending',
                'status_message' => 'Procesamiento finalizado. Iniciando distribución independiente.',
                'delivery_metadata' => $metadata,
            ];

            if ($dispatchRadioboss) {
                $updates['radioboss_status'] = 'radioboss_pending';
            }
            if ($dispatchArchive) {
                $updates['archive_org_status'] = 'archive_pending';
            }

            $program->forceFill($updates)->saveQuietly();
        });

        if ($dispatchRadioboss) {
            Bus::dispatch(new UploadRadiobossJob($program->id));
        }

        if ($dispatchArchive) {
            Bus::dispatch(new UploadArchiveOrgJob($program->id));
        }
    }
}
