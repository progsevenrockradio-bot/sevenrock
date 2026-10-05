<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Talent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TalentMaterialDeletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Talent $talent)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Aviso de eliminación de material promocional - Seven Rock Radio'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.talents.material_deleted',
            with: [
                'talent' => $this->talent,
                'plansUrl' => route('talents.subscriptions.plans'),
            ]
        );
    }
}
