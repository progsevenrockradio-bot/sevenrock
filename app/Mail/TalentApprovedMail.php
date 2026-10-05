<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Talent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class TalentApprovedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly Talent $talent)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Tu perfil en Seven Rock Radio ha sido aprobado!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.talents.approved',
            with: [
                'talent'       => $this->talent,
                'dashboardUrl' => route('talents.dashboard'),
                'endDate'      => optional($this->talent->activeSubscription())->end_date,
                'graceDays'    => \App\Models\Talent::GRACE_DAYS,
            ],
        );
    }
}
