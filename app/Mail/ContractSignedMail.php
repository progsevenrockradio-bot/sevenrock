<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class ContractSignedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public readonly Contract $contract
    ) {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Contrato Firmado con Éxito - Seven Rock Radio',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contracts.signed',
            with: [
                'contract' => $this->contract,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->contract->pdf_path) {
            $path = storage_path('app/' . $this->contract->pdf_path);
            if (file_exists($path)) {
                return [
                    Attachment::fromPath($path)
                        ->as(str_replace(' ', '_', $this->contract->title) . '_firmado.pdf')
                        ->withMime('application/pdf'),
                ];
            }
        }
        return [];
    }
}
