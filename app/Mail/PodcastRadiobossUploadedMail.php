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

final class PodcastRadiobossUploadedMail extends Mailable
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

        return new Envelope(
            from: new Address(
                (string) config('mail.from.address', 'prog.sevenrockradio@gmail.com'),
                (string) config('mail.from.name', config('app.name', 'Seven Rock Radio'))
            ),
            subject: sprintf('✅ RadioBOSS verificado - %s #%d', $programName, $episodeNumber),
        );
    }

    public function content(): Content
    {
        $archiveIdentifier = (string) data_get($this->episode->archive_org_metadata, 'identifier', '');
        $archiveItemUrl = $archiveIdentifier !== ''
            ? 'https://archive.org/details/' . rawurlencode($archiveIdentifier)
            : null;

        return new Content(
            markdown: 'emails.podcast-radioboss-uploaded',
            with: [
                'episode' => $this->episode,
                'archiveItemUrl' => $archiveItemUrl,
                'archiveStatus' => (string) ($this->episode->archive_org_status ?? 'archive_pending'),
                'deliveryStatus' => (string) ($this->episode->delivery_status ?? 'delivery_pending'),
                'remotePath' => (string) ($this->episode->radioboss_metadata['remote_path'] ?? ''),
            ],
        );
    }
}
