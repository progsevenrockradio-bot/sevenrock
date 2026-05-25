<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\TalentSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class SubscriptionExpiredMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly TalentSubscription $subscription)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu suscripción ha expirado',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.talents.expired',
            with: [
                'subscription' => $this->subscription,
                'talent' => $this->subscription->talent,
                'renewUrl' => route('talents.subscriptions.plans'),
            ],
        );
    }
}
