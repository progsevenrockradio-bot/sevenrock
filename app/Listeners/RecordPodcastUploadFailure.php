<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PodcastUploadFailed;
use App\Models\RadioProgram;
use App\Services\PodcastPipelineAuditService;
use Illuminate\Support\Facades\Log;

final class RecordPodcastUploadFailure
{
    public function __construct(
        private readonly PodcastPipelineAuditService $auditService,
    ) {
    }

    public function handle(PodcastUploadFailed $event): void
    {
        $program = RadioProgram::query()->find($event->radioProgramId);
        if (! $program instanceof RadioProgram) {
            return;
        }

        $program->forceFill([
            'delivery_status' => $this->resolveDeliveryStatus($program, $event->stage),
            'delivery_last_error' => $event->message,
            'status_message' => $event->message,
        ])->saveQuietly();

        $this->auditService->record($program, 'ERROR', $event->message, [
            'stage' => $event->stage,
            'context' => $event->context,
        ]);

        Log::warning('Podcast upload failed.', [
            'program_id' => $program->id,
            'stage' => $event->stage,
            'message' => $event->message,
            'context' => $event->context,
        ]);
    }

    private function resolveDeliveryStatus(RadioProgram $program, string $stage): string
    {
        if ($stage === 'processing_local') {
            return 'delivery_failed';
        }

        if ($program->radioboss_status === 'radioboss_verified' || $program->archive_org_status === 'archive_verified') {
            return 'delivery_partial';
        }

        return 'delivery_failed';
    }
}
