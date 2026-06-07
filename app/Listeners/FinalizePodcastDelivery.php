<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PodcastDeliveryVerified;
use App\Models\RadioProgram;
use App\Services\PodcastPipelineAuditService;

final class FinalizePodcastDelivery
{
    public function __construct(
        private readonly PodcastPipelineAuditService $auditService,
    ) {
    }

    public function handle(PodcastDeliveryVerified $event): void
    {
        $program = RadioProgram::query()->find($event->radioProgramId);
        if (! $program instanceof RadioProgram) {
            return;
        }

        RadioProgram::withoutEvents(function () use ($program): void {
            $program->forceFill([
                'delivery_status' => 'delivery_verified',
                'delivery_verified_at' => now(),
                'status_message' => 'Procesamiento finalizado correctamente.',
            ])->saveQuietly();
        });

        $this->auditService->record($program, 'ARCHIVE_VERIFIED', 'El pipeline quedó totalmente verificado.', [
            'radioboss_status' => $program->radioboss_status,
            'archive_org_status' => $program->archive_org_status,
        ]);
    }
}
