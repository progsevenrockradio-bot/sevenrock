<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PodcastArchiveUploaded;
use App\Mail\PodcastArchiveUploadedMail;
use App\Models\RadioProgram;
use App\Services\PodcastPipelineAuditService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class SendPodcastArchiveNotification
{
    public function __construct(
        private readonly PodcastPipelineAuditService $auditService,
    ) {
    }

    public function handle(PodcastArchiveUploaded $event): void
    {
        $program = RadioProgram::query()->with('masterProgram')->find($event->radioProgramId);
        if (! $program instanceof RadioProgram) {
            return;
        }

        if ($program->archive_notification_sent_at !== null) {
            return;
        }

        $mailer = $this->resolveMailer();
        $recipients = $this->resolveRecipients($program);
        $to = $recipients[0];
        $cc = $recipients[1] ?? null;

        try {
            $message = Mail::mailer($mailer)->to($to);
            if (is_string($cc) && $cc !== '' && $cc !== $to) {
                $message->cc($cc);
            }

            $message->send(new PodcastArchiveUploadedMail($program));

            RadioProgram::withoutEvents(function () use ($program): void {
                $program->forceFill([
                    'archive_notification_sent_at' => now(),
                ])->saveQuietly();
            });

            $this->auditService->record($program, 'NOTIFICATION_SENT', 'Correo de confirmación de Archive.org enviado.', [
                'channel' => 'archive',
                'mailer' => $mailer,
            ]);
        } catch (\Throwable $exception) {
            Log::error('SendPodcastArchiveNotification: fallo enviando el correo de Archive.org.', [
                'program_id' => $program->id,
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveRecipients(RadioProgram $program): array
    {
        $primary = trim((string) ($program->email_notificacion ?: $program->masterProgram?->email_notificacion ?: config('mail.from.address', 'prog.sevenrockradio@gmail.com')));
        $copy = trim((string) ($program->masterProgram?->email_copia_notificacion ?? ''));

        return [
            $primary !== '' ? $primary : 'prog.sevenrockradio@gmail.com',
            $copy !== '' ? $copy : $primary,
        ];
    }

    private function resolveMailer(): string
    {
        $mailer = trim((string) config('services.notifications.mailer', ''));

        return $mailer !== '' ? $mailer : (string) config('mail.default', 'log');
    }
}
