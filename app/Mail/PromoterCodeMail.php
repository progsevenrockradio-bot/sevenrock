<?php

namespace App\Mail;

use App\Models\PromoterCode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PromoterCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PromoterCode $promoter)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu código de promotor para Seven Rock Radio',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.talents.promoter_code',
        );
    }
}
