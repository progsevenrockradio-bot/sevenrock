<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class MediaKitMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $customSubject;
    public $customMessage;
    public $appTheme;
    public $recipientName;

    /**
     * Create a new message instance.
     */
    public function __construct(string $subject, ?string $customMessage, array $theme, ?string $recipientName = null)
    {
        $this->customSubject = $subject;
        $this->customMessage = $customMessage;
        $this->appTheme = $theme;
        $this->recipientName = $recipientName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->customSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $settings = \App\Models\ThemeSetting::current();
        $this->appTheme['social_links'] = $settings->resolvedLinks()['social_links'] ?? [];
        $this->appTheme['media']['logo_url'] = $settings->logo_url ?? asset('assets/lucille/logo.png');

        return new Content(
            view: 'emails.media-kit',
            with: [
                'customMessage' => $this->customMessage,
                'theme'         => $this->appTheme,
                'recipientName' => $this->recipientName,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $settings = \App\Models\ThemeSetting::current();
        $theme = $this->appTheme;
        $theme['social_links'] = $settings->resolvedLinks()['social_links'] ?? [];
        $theme['media']['logo_url'] = $settings->logo_url ?? asset('assets/lucille/logo.png');

        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadView('pdf.media-kit', [
            'theme' => $theme,
            'recipientName' => $this->recipientName,
        ]);

        return [
            Attachment::fromData(fn () => $pdf->output(), 'Seven_Rock_Radio_Media_Kit.pdf')
                ->withMime('application/pdf')
        ];
    }
}
