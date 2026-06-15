<?php

namespace App\Mail;

use App\Models\TrackSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrackSubmissionReceived extends Mailable
{
    use Queueable, SerializesModels;

    public TrackSubmission $submission;

    /**
     * Create a new message instance.
     */
    public function __construct(TrackSubmission $submission)
    {
        $this->submission = $submission;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Hemos recibido tu maqueta! - Seven Rock Radio',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.submissions.received',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
