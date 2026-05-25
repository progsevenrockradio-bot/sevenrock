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
        public readonly string ,
        public readonly string ,
        public readonly string ,
        public readonly string ,
        public readonly string ,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.from.address', 'prog.sevenrockradio@gmail.com'),
                ->senderName,
            ),
            replyTo: [
                new Address(->senderEmail, ->senderName),
            ],
            subject: 'Nuevo mensaje desde ' . ->source . ' - Seven Rock Radio',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contact',
            with: [
                'senderName' => ->senderName,
                'senderEmail' => ->senderEmail,
                'senderPhone' => ->senderPhone,
                'messageBody' => ->messageBody,
                'source' => ->source,
            ],
        );
    }
}
