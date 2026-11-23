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
            ->whereNotNull('processing_started_at')
            ->whereNotIn('delivery_status', ['delivery_verified', 'skipped'])
            ->where('updated_at', '<=', $threshold)
            ->where(function ($query): void {
                $query->whereNull('processing_finished_at')
                    ->orWhereIn('radioboss_status', ['radioboss_error', 'radioboss_pending'])
                    ->orWhereIn('archive_org_status', ['archive_error', 'archive_pending', 'archive_pending_indexing'])
                    ->orWhere('delivery_status', 'delivery_partial')
                    ->orWhereNull('radioboss_notification_sent_at')
                    ->orWhereNull('archive_notification_sent_at');
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

        /** @var RadioProgram $program */
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

            if (! $program->hasPreparedBroadcastAudio()) {
                $reported++;

                Log::warning('Podcast pipeline reconciliation skipped distribution because broadcast audio is not processed.', [
                    'program_id' => $program->id,
                    'archivo_mp3' => $program->archivo_mp3,
                    'archivo_mp3_disk' => $program->archivo_mp3_disk,
                    'delivery_status' => $program->delivery_status,
                ]);

                $this->warn(sprintf(
                    'Episodio #%d no tiene un MP3 procesado definitivo (%s); se omite distribución.',
                    $program->id,
                    (string) $program->archivo_mp3
                ));

                continue;
            }

            // Comprobar si el episodio ha sido superado por una emisión posterior verificada
            $supersededBy = null;
            if ($program->master_program_id) {
                $supersededBy = RadioProgram::query()
                    ->where('master_program_id', $program->master_program_id)
                    ->where('id', '!=', $program->id)
                    ->where(function ($q): void {
                        $q->where('radioboss_status', 'radioboss_verified')
                            ->orWhere('delivery_status', 'delivery_verified')
                            ->orWhere('enviado_radioboss', true);
                    })
                    ->where(function ($q) use ($program): void {
                        if ($program->fecha_emision) {
                            $q->where('fecha_emision', '>', $program->fecha_emision);
                        } elseif ($program->numero_episodio) {
                            $q->where('numero_episodio', '>', $program->numero_episodio);
                        }
                    })
                    ->orderByDesc('fecha_emision')
                    ->orderByDesc('numero_episodio')
                    ->first();
            }

            if ($supersededBy !== null) {
                // Episodio superado: no sustituir en emisión activa, registrar trazabilidad
                if ($program->radioboss_status !== 'radioboss_verified') {
                    $meta = (array) ($program->delivery_metadata ?? []);
                    $meta['superseded_by_id'] = $supersededBy->id;
                    $meta['superseded_at'] = now()->toIso8601String();

                    RadioProgram::withoutEvents(function () use ($program, $meta, $supersededBy): void {
                        $program->forceFill([
                            'radioboss_status' => 'radioboss_verified',
                            'status_message' => sprintf('Episodio superado por #%d; conservado en respaldo.', $supersededBy->id),
                            'delivery_metadata' => $meta,
                        ])->saveQuietly();
                    });

                    $auditService->record($program, 'RADIOBOSS_SUPERSEDED', sprintf(
                        'Episodio superado por #%d; no se envía a emisión activa.',
                        $supersededBy->id
                    ));

                    $actions[] = 'superado (conservado en respaldo)';
                }
            } elseif ($program->isEditorialApproved() && $program->radioboss_status !== 'radioboss_verified' && $program->radioboss_status !== 'archive_skipped') {
                UploadRadiobossJob::dispatch($program->id);
                $actions[] = 'RadioBOSS';
            } elseif ($program->radioboss_status === 'radioboss_verified' && $program->radioboss_notification_sent_at === null) {
                PodcastRadiobossUploaded::dispatch($program->id);
                $actions[] = 'correo RadioBOSS';
            }

            if (($program->masterProgram->sync_archive_org ?? true) && $program->archive_org_status !== 'archive_verified' && $program->archive_org_status !== 'archive_skipped') {
                UploadArchiveOrgJob::dispatch($program->id);
                $actions[] = 'Archive.org';
            } elseif (in_array($program->archive_org_status, ['archive_verified', 'archive_pending_indexing'], true) && $program->archive_notification_sent_at === null) {
                PodcastArchiveUploaded::dispatch($program->id);
                $actions[] = 'correo Archive.org';
            }

            if (
                $program->radioboss_status === 'radioboss_verified'
                && in_array($program->archive_org_status, ['archive_verified', 'archive_pending_indexing', 'archive_skipped'], true)
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
