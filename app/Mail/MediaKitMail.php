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

class MediaKitMail extends Mailable
{
    use SerializesModels;

    public $customSubject;
    public $customMessage;
    public $appTheme;
    public $recipientName;
    public $options;

    /**
     * Create a new message instance.
     */
    public function __construct(string $subject, ?string $customMessage, array $theme, ?string $recipientName = null, array $options = [])
    {
        $this->customSubject = $subject;
        $this->customMessage = $customMessage;
        $this->appTheme = $theme;
        $this->recipientName = $recipientName;
        $this->options = $options;
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
    
    private function getFilteredTheme(): array
    {
        $settings = \App\Models\ThemeSetting::current();
        $theme = $this->appTheme;
        
        // Filter logo
        $includeLogo = $this->options['include_logo'] ?? true;
        $theme['media']['logo_url'] = $includeLogo ? ($settings->logo_url ?? asset('assets/lucille/logo.png')) : null;
        
        // Filter social links
        $allSocials = $settings->resolvedLinks()['social_links'] ?? [];
        $selectedSocials = $this->options['socials'] ?? [];
        
        if (empty($selectedSocials)) {
            // If no socials selected (e.g. form didn't send any), we should probably include none. 
            // Wait, if options['socials'] is empty array, it means no checkboxes were checked.
            // If options wasn't passed, we'd include all, but we pass [] by default in the controller if none selected.
            $theme['social_links'] = [];
        } else {
            $theme['social_links'] = array_filter($allSocials, function ($social) use ($selectedSocials) {
                return in_array($social['network'], $selectedSocials);
            });
        }
        
        return $theme;
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.media-kit',
            with: [
                'customMessage' => $this->customMessage,
                'theme'         => $this->getFilteredTheme(),
                'recipientName' => $this->recipientName,
                'includeLogo'   => $this->options['include_logo'] ?? true,
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
        $theme = $this->getFilteredTheme();

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setOption(['isRemoteEnabled' => true]);
        $pdf->loadView('pdf.media-kit', [
            'theme'         => $theme,
            'recipientName' => $this->recipientName,
            'includeLogo'   => $this->options['include_logo'] ?? true,
        ]);

        $attachments = [
            Attachment::fromData(fn () => $pdf->output(), 'Media_Kit_Seven_Rock_Radio.pdf')
                ->withMime('application/pdf')
        ];

        // Attach logo if includeLogo is true
        $includeLogo = $this->options['include_logo'] ?? true;
        if ($includeLogo) {
            $settings = \App\Models\ThemeSetting::current();
            if (!empty($settings->logo_url)) {
                $logoContent = @file_get_contents($settings->logo_url);
                if ($logoContent) {
                    $ext = pathinfo($settings->logo_url, PATHINFO_EXTENSION) ?: 'png';
                    $attachments[] = Attachment::fromData(fn () => $logoContent, 'Logo_Oficial.' . $ext);
                }
            } else {
                // Attach default logo
                $logoPath = public_path('assets/lucille/logo.png');
                if (file_exists($logoPath)) {
                    $attachments[] = Attachment::fromPath($logoPath)->as('Logo_Oficial.png');
                }
            }
        }

        return $attachments;
    }
}
