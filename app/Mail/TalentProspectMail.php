<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class TalentProspectMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $senderName,
        public readonly string $bandName,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Únete a Seven Rock Radio! - Información de Planes 🎸',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.talents.prospect-info',
            with: [
                'senderName' => $this->senderName,
                'bandName' => $this->bandName,
            ],
        );
    }
}
