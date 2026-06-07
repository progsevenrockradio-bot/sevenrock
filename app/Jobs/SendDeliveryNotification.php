<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Jobs\Concerns\InteractsWithPodcastUploadPipeline;
use App\Mail\PodcastUploadedMail;
use App\Models\RadioProgram;
use App\Services\FileUploadService;
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

            $radiobossReady = in_array($radiobossStatus, ['radioboss_verified', 'radioboss_error', 'skipped'], true);
            $archiveReady = in_array($archiveStatus, ['archive_verified', 'archive_pending_indexing', 'archive_error', 'archive_skipped', 'skipped'], true);

            if (! $radiobossReady || ! $archiveReady) {
                return;
            }

            if ($radioProgram->delivery_verified_at !== null) {
                return;
            }

            $radiobossVerified = $radiobossStatus === 'radioboss_verified';
            $archiveVerified = in_array($archiveStatus, ['archive_verified', 'archive_pending_indexing'], true);
            $deliveryStatus = $radiobossVerified && $archiveVerified
                ? 'delivery_verified'
                : ($radiobossVerified || $archiveVerified ? 'delivery_partial' : 'delivery_failed');

            $localPath = (string) $radioProgram->archivo_mp3;
            $archiveIdentifier = (string) data_get($radioProgram->archive_org_metadata, 'identifier', '');
            $archiveItemUrl = $archiveVerified && $archiveIdentifier !== ''
                ? 'https://archive.org/details/' . rawurlencode($archiveIdentifier)
                : null;

            $recipients = $this->resolveNotificationRecipients($radioProgram->masterProgram);
            if ($deliveryStatus === 'delivery_failed') {
                $to = $this->resolveGlobalNotificationPrimaryRecipient();
                $cc = null;

                Log::warning('SendDeliveryNotification: entrega fallida — notificando solo al admin', [
                    'program_id' => $radioProgram->id,
                    'admin_email' => $to,
                ]);
            } else {
                $to = $recipients[0] ?? $this->resolveGlobalNotificationPrimaryRecipient();
                $cc = $recipients[1] ?? $this->resolveGlobalNotificationCopyRecipient();
            }
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
                    'verified' => $deliveryStatus === 'delivery_verified',
                        'updated_at' => now()->toIso8601String(),
                        'radioboss_status' => $radiobossStatus,
                        'archive_org_status' => $archiveStatus,
                        'radioboss_verified' => $radiobossVerified,
                        'archive_verified' => $archiveVerified,
                        'archive_item_url' => $archiveItemUrl,
                        'preserve_local_copy' => (bool) data_get($radioProgram->delivery_metadata, 'preserve_local_copy', false),
                    ]),
                    'status_message' => $deliveryStatus === 'delivery_verified'
                        ? 'Procesamiento finalizado correctamente.'
                        : 'Procesamiento finalizado con incidencias.',
                ]));

                if ($deliveryStatus === 'delivery_verified' && ! (bool) data_get($radioProgram->delivery_metadata, 'preserve_local_copy', false)) {
                    $disk = (string) $radioProgram->archivo_mp3_disk;

                    if ($localPath !== '') {
                        app(FileUploadService::class)->delete($localPath, $disk);
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
