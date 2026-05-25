<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\TalentMedia;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class ContentApprovedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly TalentMedia $media)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Contenido moderado',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.talents.content-approved',
            with: [
                'media' => $this->media,
                'talent' => $this->media->talent,
            ],
        );
    }
}
