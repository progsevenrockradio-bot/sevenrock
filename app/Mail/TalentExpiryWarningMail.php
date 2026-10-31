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
        public readonly string $stage = '7_days_before',
        public readonly ?\Carbon\CarbonInterface $endDate = null,
        public readonly ?\Carbon\CarbonInterface $purgeDate = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->stage) {
            '1_day_before' => '¡Último día! Tu espacio en Seven Rock Radio vence mañana',
            '3_days_before_purge' => 'AVISO FINAL: Tu material en Seven Rock Radio será eliminado en 3 días',
            default => "Tu espacio en Seven Rock Radio vence en {$this->daysLeft} días",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $resolvedEndDate = $this->endDate ?? today()->addDays($this->daysLeft);
        $resolvedPurgeDate = $this->purgeDate ?? $resolvedEndDate->copy()->addDays(Talent::GRACE_DAYS);
        $renewalUrl = route('talents.login', ['redirect_to' => route('talents.subscriptions.plans')]);

        return new Content(
            view: 'emails.talents.expiry_warning',
            with: [
                'talent'       => $this->talent,
                'stage'        => $this->stage,
                'daysLeft'     => $this->daysLeft,
                'graceDays'    => Talent::GRACE_DAYS,
                'endDate'      => $resolvedEndDate,
                'purgeDate'    => $resolvedPurgeDate,
                'renewalUrl'   => $renewalUrl,
                'dashboardUrl' => route('talents.dashboard'),
            ],
        );
    }
}
