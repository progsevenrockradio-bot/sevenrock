<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\TalentSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class SubscriptionRenewalMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly TalentSubscription $subscription)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu suscripción está por vencer',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.talents.renewal',
            with: [
                'subscription' => $this->subscription,
                'talent' => $this->subscription->talent,
                'renewUrl' => route('talents.subscriptions.plans'),
            ],
        );
    }
}
