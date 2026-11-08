<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\NewRelease;
use App\Models\SubmissionMuroFollowup;
use App\Models\ThemeSetting;
use App\Models\TrackSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class SubmissionMuroInvitationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly SubmissionMuroFollowup $followup,
        public readonly TrackSubmission $submission,
        public readonly NewRelease $release,
        public readonly string $ctaUrl,
        public readonly string $unsubscribeUrl,
    ) {
    }

    /**
     * Resuelve el buzón de respuesta configurado en el sistema sin modificarlo.
     */
    public static function configuredReplyToAddress(): string
    {
        try {
            $settings = ThemeSetting::current();
            $candidate = trim((string) (
                $settings->notification_reply_to_email
                ?: $settings->notification_email
                ?: $settings->contact_email
                ?: config('mail.from.address', 'prog.sevenrockradio@gmail.com')
            ));

            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL) !== false) {
                return $candidate;
            }
        } catch (\Throwable) {
            // Fallback seguro a la configuración de mail.php
        }

        return (string) config('mail.from.address', 'prog.sevenrockradio@gmail.com');
    }

    public function envelope(): Envelope
    {
        $replyTo = self::configuredReplyToAddress();

        return new Envelope(
            replyTo: [new Address($replyTo, 'Seven Rock Radio')],
            subject: "{$this->followup->band_name}, conoce el Muro del Rock de Seven Rock Radio",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.submissions.muro_invitation',
            with: [
                'bandName' => $this->followup->band_name,
                'songTitle' => $this->submission->song_title,
                'releaseUrl' => $this->release->url,
                'ctaUrl' => $this->ctaUrl,
                'unsubscribeUrl' => $this->unsubscribeUrl,
                'basicPriceLabel' => (string) config('muro_outreach.basic_plan_price_label', '3,50 €/mes'),
                'signature' => (string) config('muro_outreach.signature', 'Equipo de Seven Rock Radio'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

