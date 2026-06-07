<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PodcastProcessed;
use App\Jobs\UploadArchiveOrgJob;
use App\Jobs\UploadRadiobossJob;
use App\Models\RadioProgram;
use App\Services\PodcastPipelineAuditService;
use Illuminate\Support\Facades\Bus;

final class DispatchPodcastDistributionJobs
{
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

        $this->auditService->record($program, 'PROCESSING_COMPLETED', 'El MP3 fue procesado y quedó listo para distribución.');

        RadioProgram::withoutEvents(function () use ($program): void {
            $program->forceFill([
                'processing_finished_at' => now(),
                'radioboss_status' => 'radioboss_pending',
                'archive_org_status' => 'archive_pending',
                'delivery_status' => 'delivery_pending',
                'status_message' => 'Procesamiento finalizado. Iniciando distribución independiente.',
            ])->saveQuietly();
        });

        Bus::dispatch(new UploadRadiobossJob($program->id));
        Bus::dispatch(new UploadArchiveOrgJob($program->id));
    }
}
