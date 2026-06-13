<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\RadioProgram;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class PodcastArchiveUploadedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly RadioProgram $episode,
    ) {
    }

    public function envelope(): Envelope
    {
        $programName = trim((string) ($this->episode->titulo_programa ?: $this->episode->masterProgram?->nombre ?: 'Seven Rock Radio'));
        $episodeNumber = (int) ($this->episode->numero_episodio ?? 0);

        $settings = \App\Models\ThemeSetting::current();
        $ui = $settings->uiTexts();
        $verifiedTitle = $ui['email_title_verified_podcast'] ?? 'Servidor de podcast';

        return new Envelope(
            from: new Address(
                (string) config('mail.from.address', 'prog.sevenrockradio@gmail.com'),
                (string) config('mail.from.name', config('app.name', 'Seven Rock Radio'))
            ),
            subject: sprintf('✅ %s - %s #%d', $verifiedTitle, $programName, $episodeNumber),
        );
    }

    public function content(): Content
    {
        $archiveIdentifier = (string) data_get($this->episode->archive_org_metadata, 'identifier', '');
        $archiveItemUrl = $archiveIdentifier !== ''
            ? 'https://archive.org/details/' . rawurlencode($archiveIdentifier)
            : null;

        return new Content(
            view: 'emails.podcast-archive-uploaded',
            with: [
                'episode' => $this->episode,
                'archiveItemUrl' => $archiveItemUrl,
                'archiveStatus' => (string) ($this->episode->archive_org_status ?? 'archive_verified'),
                'deliveryStatus' => (string) ($this->episode->delivery_status ?? 'delivery_pending'),
                'remotePath' => (string) ($this->episode->archive_org_remote_path ?? ''),
            ],
        );
    }
}
