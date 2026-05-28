<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class OutreachMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $bodyHtml,
        public readonly string $campaignName,
        public readonly string $bandName,
        public readonly string $contactPerson = '',
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.outreach',
            with: [
                'subjectLine' => $this->subjectLine,
                'bodyHtml' => $this->bodyHtml,
                'campaignName' => $this->campaignName,
                'bandName' => $this->bandName,
                'contactPerson' => $this->contactPerson,
                'websiteUrl' => 'https://sevenrockradio.com',
                'registerUrl' => 'https://sevenrockradio.shop/talentos/register',
            ],
        );
    }
}
