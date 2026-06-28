<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;

class MissingPersonsExportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $pdfContent;
    public $senderEmail;

    /**
     * Create a new message instance.
     */
    public function __construct($pdfContent, $senderEmail = null)
    {
        $this->pdfContent = $pdfContent;
        $this->senderEmail = $senderEmail;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $envelope = new Envelope(
            subject: 'Exportación de Personas Desaparecidas - Seven Rock Radio',
        );

        if ($this->senderEmail) {
            $envelope->replyTo = [
                new Address($this->senderEmail, 'Moderador Seven Rock Radio')
            ];
        }

        return $envelope;
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Adjunto encontrarás el reporte en PDF de las personas desaparecidas actualmente registradas en el sistema.</p>',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, 'Personas-Desaparecidas.pdf')
                    ->withMime('application/pdf'),
        ];
    }
}
