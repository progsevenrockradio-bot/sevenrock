<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\NewRelease;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class NewReleasePublishedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public readonly NewRelease $newRelease,
        public readonly ?string $senderEmail = null
    ) {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->senderEmail ?: config('mail.from.address'),
            subject: '¡Nuevo lanzamiento publicado! - Seven Rock Radio',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.new-release-published',
            with: [
                'newRelease' => $this->newRelease,
                'newReleaseUrl' => $this->newRelease->url,
            ],
        );
    }
}
