<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Jobs\Concerns\InteractsWithPodcastUploadPipeline;
use App\Mail\PodcastUploadedMail;
use App\Models\RadioProgram;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendDeliveryNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use InteractsWithPodcastUploadPipeline;
    use Queueable;
    use SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    public function __construct(
        public int $radioProgramId,
    ) {
    }

    public function handle(): void
    {
        Cache::lock('podcast-delivery-notification:' . $this->radioProgramId, 30)->get(function (): void {
            $radioProgram = RadioProgram::query()->with('masterProgram')->find($this->radioProgramId);
            if (! $radioProgram instanceof RadioProgram) {
                return;
            }

            $radiobossStatus = (string) ($radioProgram->radioboss_status ?? '');
            $archiveStatus = (string) ($radioProgram->archive_org_status ?? '');

            $radiobossReady = in_array($radiobossStatus, ['verified', 'error', 'skipped'], true);
            $archiveReady = in_array($archiveStatus, ['synced', 'error', 'skipped'], true);

            if (! $radiobossReady || ! $archiveReady) {
                return;
            }

            if ($radioProgram->delivery_verified_at !== null) {
                return;
            }

            $radiobossVerified = $radiobossStatus === 'verified';
            $archiveVerified = $archiveStatus === 'synced';
            $deliveryStatus = $radiobossVerified && $archiveVerified
                ? 'verified'
                : ($radiobossVerified || $archiveVerified ? 'partial' : 'failed');

            $localPath = (string) $radioProgram->archivo_mp3;
            $archiveIdentifier = (string) data_get($radioProgram->archive_org_metadata, 'identifier', '');
            $archiveItemUrl = $archiveVerified && $archiveIdentifier !== ''
                ? 'https://archive.org/details/' . rawurlencode($archiveIdentifier)
                : null;

            $recipients = $this->resolveNotificationRecipients($radioProgram->masterProgram);
            $to = $recipients[0] ?? $this->resolveGlobalNotificationPrimaryRecipient();
            $cc = $recipients[1] ?? $this->resolveGlobalNotificationCopyRecipient();
            $mailer = $this->resolveNotificationMailer();

            try {
                $message = Mail::mailer($mailer)->to($to);
                if (is_string($cc) && $cc !== '' && $cc !== $to) {
                    $message->cc($cc);
                }

                $message->send(new PodcastUploadedMail(
                    episode: $radioProgram,
                    localPath: $localPath,
                    remotePath: (string) ($radioProgram->radioboss_metadata['remote_path'] ?? ''),
                    radiobossVerified: $radiobossVerified,
                    archiveVerified: $archiveVerified,
                    deliveryStatus: $deliveryStatus,
                ));

                RadioProgram::withoutEvents(fn (): bool => (bool) $radioProgram->update([
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
                        'archive_item_url' => $archiveItemUrl,
                        'preserve_local_copy' => (bool) data_get($radioProgram->delivery_metadata, 'preserve_local_copy', false),
                    ]),
                    'status_message' => $deliveryStatus === 'verified'
                        ? 'Procesamiento finalizado correctamente.'
                        : 'Procesamiento finalizado con incidencias.',
                ]));

                if ($deliveryStatus === 'verified' && ! (bool) data_get($radioProgram->delivery_metadata, 'preserve_local_copy', false)) {
                    if ($localPath !== '' && \Illuminate\Support\Facades\Storage::disk('public')->exists($localPath)) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($localPath);
                    }
                }
            } catch (Throwable $exception) {
                Log::error('SendDeliveryNotification: fallo enviando el correo de notificación', [
                    'program_id' => $radioProgram->id,
                    'exception' => $exception,
                ]);

                throw $exception;
            }
        });
    }
}
