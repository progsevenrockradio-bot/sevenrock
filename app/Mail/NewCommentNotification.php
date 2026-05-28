<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class NewCommentNotification extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Comment $comment,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nuevo comentario en Seven Rock Radio - ' . ($this->comment->author_name ?: 'Anónimo'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.new-comment',
            with: [
                'comment' => $this->comment,
            ],
        );
    }
}
