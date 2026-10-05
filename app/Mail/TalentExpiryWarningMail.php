<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Talent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class TalentExpiryWarningMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Talent $talent,
        public readonly int $daysLeft,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tu espacio en Seven Rock Radio vence en {$this->daysLeft} días",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.talents.expiry_warning',
            with: [
                'talent'      => $this->talent,
                'daysLeft'    => $this->daysLeft,
                'graceDays'   => Talent::GRACE_DAYS,
                'renewalUrl'  => route('talents.subscriptions.plans'),
                'dashboardUrl' => route('talents.dashboard'),
            ],
        );
    }
}
