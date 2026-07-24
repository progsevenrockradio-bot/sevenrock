<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class DirectAdminEmail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $bodyHtml,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.direct',
            with: [
                'subjectLine' => $this->subjectLine,
                'bodyHtml' => $this->bodyHtml,
                'websiteUrl' => 'https://sevenrockradio.com',
            ],
        );
    }
}
