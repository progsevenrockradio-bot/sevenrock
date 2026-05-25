<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * @deprecated Use PodcastUploadedMail instead.
 */
final class ProgramUploadedNotification extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $fileName,
        public readonly bool $uploadedToRadioboss = true,
        public readonly bool $archiveVerified = false,
        public readonly string $deliveryStatus = 'pending',
        public readonly array $deliveryMetadata = [],
        public readonly ?string $failureReason = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: match ($this->deliveryStatus) {
                'verified' => 'Seven Rock Radio: entrega verificada',
                'partial' => 'Seven Rock Radio: entrega parcialmente verificada',
                'failed' => 'Seven Rock Radio: entrega con errores',
                default => $this->uploadedToRadioboss
                    ? 'Seven Rock Radio: episodio subido y procesado'
                    : 'Seven Rock Radio: episodio procesado con envío pendiente',
            },
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.programs.uploaded',
        );
    }
}
