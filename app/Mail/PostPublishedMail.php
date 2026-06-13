<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class PostPublishedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public readonly Post $post,
        public readonly ?string $senderEmail = null
    ) {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $settings = \App\Models\ThemeSetting::current();
        $ui = $settings->uiTexts();
        $subject = $ui['email_title_post_published'] ?? '¡Tu contenido ya ha sido publicado! - Seven Rock Radio';

        return new Envelope(
            from: $this->senderEmail ?: config('mail.from.address'),
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.post-published',
            with: [
                'post' => $this->post,
                'postUrl' => $this->post->url,
            ],
        );
    }
}
