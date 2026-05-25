<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Talent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class NewInteractionMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Talent $talent,
        public readonly string $interactionType,
        public readonly string $visitorIp,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Nueva interacción en tu perfil!',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.talents.interaction',
            with: [
                'talent' => $this->talent,
                'interactionType' => $this->interactionType,
                'visitorIp' => $this->visitorIp,
                'profileUrl' => route('talents.show', ['bandName' => $this->talent->band_name]),
            ],
        );
    }
}
