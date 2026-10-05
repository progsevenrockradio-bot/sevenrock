<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Talent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TalentHiddenNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Talent $talent)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu perfil en Seven Rock Radio está oculto: renuévalo ahora'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.talents.hidden_notice',
            with: [
                'talent' => $this->talent,
                'graceDays' => Talent::GRACE_DAYS,
                'renewalUrl' => route('talents.subscriptions.plans'),
            ]
        );
    }
}
