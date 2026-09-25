<?php

namespace App\Mail;

use App\Models\ModerationItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;
use App\Models\ThemeSetting;

class ModerationNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ModerationItem $item)
    {
    }

    public function envelope(): Envelope
    {
        $settings = ThemeSetting::current();
        
        $fromEmail = $settings?->notification_from_email;
        $fromAddress = $fromEmail ?: config('mail.from.address');
        
        $typeLabel = \App\Support\ModerationType::tryFrom($this->item->type)?->label() ?? $this->item->type;
        $title = str($this->item->title)->limit(60);

        return new Envelope(
            from: new Address($fromAddress, config('app.name')),
            subject: "[7RR] Pendiente: {$typeLabel} — {$title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.moderation.notification',
            text: 'emails.moderation.notification-text',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
