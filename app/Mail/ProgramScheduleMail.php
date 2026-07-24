<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProgramScheduleMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $customMessage,
        public readonly array $groupedPrograms,
        public readonly string $pdfAttachment
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.program-schedule',
            with: [
                'customMessage' => $this->customMessage,
                'groupedPrograms' => $this->groupedPrograms,
            ]
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn() => $this->pdfAttachment, 'Horarios_Programas_Seven_Rock_Radio.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
