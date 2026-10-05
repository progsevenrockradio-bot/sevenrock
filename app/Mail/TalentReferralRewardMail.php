<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Talent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class TalentReferralRewardMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Talent $referrer,
        public readonly string $rewardLabel,
        public readonly int $bonusDays,
        public readonly int $activeReferrals,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Has ganado una recompensa por referir bandas a Seven Rock Radio!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.talents.referral_reward',
            with: [
                'referrer'       => $this->referrer,
                'rewardLabel'    => $this->rewardLabel,
                'bonusDays'      => $this->bonusDays,
                'activeReferrals' => $this->activeReferrals,
                'dashboardUrl'   => route('talents.dashboard'),
            ],
        );
    }
}
