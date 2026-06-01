<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\PodcastUploadedMail;
use App\Models\RadioProgram;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyPodcastReadyJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $radioProgramId,
    ) {
    }

    public function handle(): void
    {
        $radioProgram = RadioProgram::query()->with('masterProgram')->find($this->radioProgramId);
        if (! $radioProgram instanceof RadioProgram) {
            return;
        }

        $radiobossStatus = (string) ($radioProgram->radioboss_status ?? '');
        $archiveStatus = (string) ($radioProgram->archive_org_status ?? '');

        $radiobossVerified = $radiobossStatus === 'verified';
        $archiveVerified = $archiveStatus === 'synced';

        $deliveryStatus = match (true) {
            $radiobossVerified && $archiveVerified => 'verified',
            $radiobossVerified || $archiveVerified => 'partial',
            default => 'failed',
        };

        $failureReason = $this->resolveFailureReason($radioProgram);
        $localPath = (string) $radioProgram->archivo_mp3;
        $remotePath = (string) ($radioProgram->radioboss_metadata['remote_path'] ?? $radioProgram->archive_org_remote_path ?? '');

        $to = $this->resolveRecipient($radioProgram);
        $cc = $this->resolveCopyRecipient($radioProgram);

        try {
            $message = Mail::to($to);
            if ($cc !== null && $cc !== '' && $cc !== $to) {
                $message->cc($cc);
            }

            $message->send(new PodcastUploadedMail(
                episode: $radioProgram,
                localPath: $localPath,
                remotePath: $remotePath,
                radiobossVerified: $radiobossVerified,
                archiveVerified: $archiveVerified,
                deliveryStatus: $deliveryStatus,
            ));

            RadioProgram::withoutEvents(function () use ($radioProgram, $deliveryStatus, $radiobossStatus, $archiveStatus, $radiobossVerified, $archiveVerified): void {
                $radioProgram->forceFill([
                    'delivery_status' => $deliveryStatus,
                    'delivery_verified_at' => now(),
                    'delivery_last_error' => null,
                    'delivery_metadata' => array_merge((array) ($radioProgram->delivery_metadata ?? []), [
                        'status' => $deliveryStatus,
                        'verified' => $deliveryStatus === 'verified',
                        'updated_at' => now()->toIso8601String(),
                        'radioboss_status' => $radiobossStatus,
                        'archive_org_status' => $archiveStatus,
                        'radioboss_verified' => $radiobossVerified,
                        'archive_verified' => $archiveVerified,
                        'preserve_local_copy' => (bool) data_get($radioProgram->delivery_metadata, 'preserve_local_copy', false),
                    ]),
                    'status_message' => $deliveryStatus === 'verified'
                        ? 'Procesamiento finalizado correctamente.'
                        : 'Procesamiento finalizado con incidencias.',
                ])->saveQuietly();
            });

            if (! (bool) data_get($radioProgram->delivery_metadata, 'preserve_local_copy', false)) {
                $this->cleanupLocalCopy($radioProgram);
            }
        } catch (Throwable $exception) {
            RadioProgram::withoutEvents(function () use ($radioProgram, $exception): void {
                $radioProgram->forceFill([
                    'delivery_status' => 'failed',
                    'delivery_verified_at' => null,
                    'delivery_last_error' => $exception->getMessage(),
                    'delivery_metadata' => array_merge((array) ($radioProgram->delivery_metadata ?? []), [
                        'status' => 'failed',
                        'last_error' => $exception->getMessage(),
                    ]),
                    'status_message' => 'La notificación por correo falló.',
                ])->saveQuietly();
            });

            Log::error('NotifyPodcastReadyJob: fallo enviando el correo de notificación', [
                'program_id' => $radioProgram->id,
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }

    private function resolveRecipient(RadioProgram $radioProgram): string
    {
        $recipient = trim((string) ($radioProgram->email_notificacion ?: $radioProgram->masterProgram?->email_notificacion ?: config('mail.from.address', 'prog.sevenrockradio@gmail.com')));

        return $recipient !== '' ? $recipient : 'prog.sevenrockradio@gmail.com';
    }

    private function resolveCopyRecipient(RadioProgram $radioProgram): ?string
    {
        $copy = trim((string) ($radioProgram->masterProgram?->email_copia_notificacion ?? ''));

        return $copy !== '' ? $copy : null;
    }

    private function resolveFailureReason(RadioProgram $radioProgram): ?string
    {
        $reasons = array_filter([
            trim((string) ($radioProgram->radioboss_last_error ?? '')),
            trim((string) ($radioProgram->archive_org_last_error ?? '')),
            trim((string) ($radioProgram->delivery_last_error ?? '')),
        ]);

        return $reasons !== [] ? (string) reset($reasons) : null;
    }

    private function cleanupLocalCopy(RadioProgram $radioProgram): void
    {
        // Conservamos la copia local por defecto para permitir descarga,
        // reintentos manuales y auditoría. La limpieza automática se reserva
        // para tareas operativas específicas fuera de este job.
    }
}
