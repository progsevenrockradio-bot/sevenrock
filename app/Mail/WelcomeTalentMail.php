<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Talent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class WelcomeTalentMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly Talent $talent)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Bienvenido a Seven Rock Radio Talentos!',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.talents.welcome',
            with: [
                'talent' => $this->talent,
                'dashboardUrl' => route('talents.dashboard'),
                'plansUrl' => route('talents.subscriptions.plans'),
            ],
        );
    }
}
