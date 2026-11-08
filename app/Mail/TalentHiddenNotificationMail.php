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
        public ?\Carbon\CarbonInterface $recoveryEndDate = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = $this->talent->isPaidPlan()
            ? 'Tu perfil en Seven Rock Radio está pausado: período de recuperación iniciado'
            : 'Tu perfil en Seven Rock Radio está oculto: periodo de gracia iniciado';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $resolvedExpiredDate = $this->expiredDate ?? today();
        $resolvedPurgeDate = $this->purgeDate ?? ($this->talent->expires_grace_at ?? today()->addDays(Talent::GRACE_DAYS));
        $resolvedRecoveryEndDate = $this->recoveryEndDate ?? $this->talent->recoveryPeriodEndsAt() ?? $resolvedExpiredDate->copy()->addDays(Talent::PAID_RECOVERY_DAYS);
        $renewalUrl = route('talents.login', ['redirect_to' => route('talents.subscriptions.plans')]);

        return new Content(
            view: 'emails.talents.hidden_notice',
            with: [
                'talent' => $this->talent,
                'graceDays' => Talent::GRACE_DAYS,
                'recoveryDays' => Talent::PAID_RECOVERY_DAYS,
                'expiredDate' => $resolvedExpiredDate,
                'purgeDate' => $resolvedPurgeDate,
                'recoveryEndDate' => $resolvedRecoveryEndDate,
                'renewalUrl' => $renewalUrl,
                'dashboardUrl' => route('talents.dashboard'),
            ]
        );
    }
}
