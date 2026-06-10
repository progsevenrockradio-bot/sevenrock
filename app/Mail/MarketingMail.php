<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MarketingMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public readonly string $templateName,
        public readonly string $subjectLine,
        public readonly string $bodyContent,
        public readonly ?string $buttonText,
        public readonly ?string $buttonUrl,
        public readonly string $senderEmail,
        public readonly string $senderName,
        public readonly ?string $contactName = null
    ) {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->senderEmail, $this->senderName),
            subject: $this->subjectLine,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Limpiar saltos de línea para Blade
        $formattedContent = nl2br(e($this->bodyContent));

        return new Content(
            markdown: 'emails.marketing.' . $this->templateName,
            with: [
                'bodyContent' => $formattedContent,
                'buttonText' => $this->buttonText,
                'buttonUrl' => $this->buttonUrl,
                'contactName' => $this->contactName,
            ],
        );
    }
}
