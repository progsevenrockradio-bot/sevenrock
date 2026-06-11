<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class ContactMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $senderName,
        public readonly string $senderEmail,
        public readonly string $senderPhone,
        public readonly string $messageBody,
        public readonly string $source,
        public readonly ?string $bandName = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.from.address', 'prog.sevenrockradio@gmail.com'),
                $this->senderName,
            ),
            replyTo: [
                new Address($this->senderEmail, $this->senderName),
            ],
            subject: 'Nuevo mensaje desde ' . $this->source . ' - Seven Rock Radio',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contact',
            with: [
                'senderName' => $this->senderName,
                'senderEmail' => $this->senderEmail,
                'senderPhone' => $this->senderPhone,
                'messageBody' => $this->messageBody,
                'source' => $this->source,
                'bandName' => $this->bandName,
            ],
        );
    }
}
