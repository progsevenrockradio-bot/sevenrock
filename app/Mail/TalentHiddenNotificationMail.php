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

    public function __construct(
        public Talent $talent,
        public ?\Carbon\CarbonInterface $expiredDate = null,
        public ?\Carbon\CarbonInterface $purgeDate = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu perfil en Seven Rock Radio está oculto: periodo de gracia iniciado'
        );
    }

    public function content(): Content
    {
        $resolvedExpiredDate = $this->expiredDate ?? today();
        $resolvedPurgeDate = $this->purgeDate ?? ($this->talent->expires_grace_at ?? today()->addDays(Talent::GRACE_DAYS));
        $renewalUrl = route('talents.login', ['redirect_to' => route('talents.subscriptions.plans')]);

        return new Content(
            view: 'emails.talents.hidden_notice',
            with: [
                'talent' => $this->talent,
                'graceDays' => Talent::GRACE_DAYS,
                'expiredDate' => $resolvedExpiredDate,
                'purgeDate' => $resolvedPurgeDate,
                'renewalUrl' => $renewalUrl,
                'dashboardUrl' => route('talents.dashboard'),
            ]
        );
    }
}
