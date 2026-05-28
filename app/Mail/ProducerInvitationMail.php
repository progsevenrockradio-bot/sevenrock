<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\MasterProgram;
use App\Models\OutreachTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class ProducerInvitationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly MasterProgram $program,
        public readonly OutreachTemplate $template,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->template->renderSubject($this->program),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.producer-invitation',
            with: [
                'program' => $this->program,
                'subjectLine' => $this->template->renderSubject($this->program),
                'bodyHtml' => $this->template->renderBody($this->program),
            ],
        );
    }
}
