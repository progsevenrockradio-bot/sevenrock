<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\PodcastArchiveUploaded;
use App\Events\PodcastDeliveryVerified;
use App\Events\PodcastRadiobossUploaded;
use App\Jobs\UploadArchiveOrgJob;
use App\Jobs\UploadRadiobossJob;
use App\Models\RadioProgram;
use App\Services\PodcastPipelineAuditService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('podcast:reconcile-pipeline {--limit=100 : Maximum number of episodes to inspect} {--stale-minutes=90 : Minutes before a pipeline is considered stale}')]
#[Description('Reconcile podcast uploads by re-dispatching missing or stale pipeline steps')]
final class ReconcilePodcastPipelineCommand extends Command
{
    public function handle(PodcastPipelineAuditService $auditService): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $staleMinutes = max(10, (int) $this->option('stale-minutes'));
        $threshold = now()->subMinutes($staleMinutes);

        $programs = RadioProgram::query()
            ->with('masterProgram')
            ->where(function ($query) use ($threshold): void {
                $query->whereNull('processing_finished_at')
                    ->orWhere('updated_at', '<=', $threshold)
                    ->orWhereIn('radioboss_status', ['radioboss_error', 'radioboss_pending'])
                    ->orWhereIn('archive_org_status', ['archive_error', 'archive_pending', 'archive_pending_indexing']);
            })
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        if ($programs->isEmpty()) {
            $this->info('No hay episodios que reconciliar.');

            return self::SUCCESS;
        }

        $requeued = 0;
        $reported = 0;

        foreach ($programs as $program) {
            $program->loadMissing('masterProgram');
            $actions = [];

            if ($program->processing_finished_at === null) {
                $reported++;

                Log::warning('Podcast pipeline reconciliation detected an incomplete processing stage.', [
                    'program_id' => $program->id,
                    'status_message' => $program->status_message,
                    'updated_at' => optional($program->updated_at)?->toIso8601String(),
                ]);

                $this->warn(sprintf(
                    'Episodio #%d sigue en procesamiento local.',
                    $program->id
                ));

                continue;
            }

            if ($program->radioboss_status !== 'radioboss_verified' && $program->radioboss_status !== 'archive_skipped') {
                UploadRadiobossJob::dispatch($program->id);
                $actions[] = 'RadioBOSS';
            } elseif ($program->radioboss_status === 'radioboss_verified' && $program->radioboss_notification_sent_at === null) {
                PodcastRadiobossUploaded::dispatch($program->id);
                $actions[] = 'correo RadioBOSS';
            }

            if ($program->sync_archive_org && $program->archive_org_status !== 'archive_verified' && $program->archive_org_status !== 'archive_skipped') {
                UploadArchiveOrgJob::dispatch($program->id);
                $actions[] = 'Archive.org';
            } elseif (in_array($program->archive_org_status, ['archive_verified', 'archive_pending_indexing'], true) && $program->archive_notification_sent_at === null) {
                PodcastArchiveUploaded::dispatch($program->id);
                $actions[] = 'correo Archive.org';
            }

            if (
                $program->radioboss_status === 'radioboss_verified'
                && in_array($program->archive_org_status, ['archive_verified', 'archive_pending_indexing'], true)
                && $program->delivery_status !== 'delivery_verified'
            ) {
                PodcastDeliveryVerified::dispatch($program->id);
                $actions[] = 'verificación final';
            }

            if ($actions === []) {
                continue;
            }

            $requeued++;

            $auditService->record($program, 'RECONCILE_REQUEUED', 'Se re-dispararon etapas faltantes del pipeline.', [
                'actions' => $actions,
                'stale_minutes' => $staleMinutes,
            ]);

            $this->line(sprintf(
                'Requeue #%d: %s',
                $program->id,
                implode(', ', $actions)
            ));
        }

        $this->info(sprintf(
            'Reconciliación completada. Recolocados: %d. Solo reportados: %d.',
            $requeued,
            $reported
        ));

        return self::SUCCESS;
    }
}
