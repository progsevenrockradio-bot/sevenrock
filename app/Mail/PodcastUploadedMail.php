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

final class PodcastUploadedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly RadioProgram $episode,
        public readonly string $localPath,
        public readonly string $remotePath,
        public readonly bool $radiobossVerified,
        public readonly bool $archiveVerified,
        public readonly string $deliveryStatus,
    ) {
    }

    public function envelope(): Envelope
    {
        $programName = trim((string) ($this->episode->titulo_programa ?: $this->episode->masterProgram?->nombre ?: 'Seven Rock Radio'));
        $episodeNumber = (int) ($this->episode->numero_episodio ?? 0);
        $subjectPrefix = match ($this->deliveryStatus) {
            'delivery_verified' => '✅ Entrega completa',
            'delivery_partial' => '⚠️ Entrega parcial',
            'delivery_failed' => '❌ Error en entrega',
            default => '⚠️ Estado de entrega',
        };

        return new Envelope(
            from: new Address(
                (string) config('mail.from.address', 'prog.sevenrockradio@gmail.com'),
                (string) config('mail.from.name', config('app.name', 'Seven Rock Radio'))
            ),
            subject: sprintf('%s - %s #%d', $subjectPrefix, $programName, $episodeNumber),
        );
    }

    public function content(): Content
    {
        $identifier = (string) data_get($this->episode->archive_org_metadata, 'identifier', '');
        $archivePendingIndexing = (bool) data_get($this->episode->archive_org_metadata, 'pending_indexing', false);

        return new Content(
            markdown: 'emails.podcast-uploaded',
            with: [
                'episode' => $this->episode,
                'localPath' => $this->localPath,
                'remotePath' => $this->remotePath,
                'radiobossVerified' => $this->radiobossVerified,
                'archiveVerified' => $this->archiveVerified,
                'deliveryStatus' => $this->deliveryStatus,
                'archiveItemUrl' => $this->archiveVerified && $identifier !== ''
                    ? 'https://archive.org/details/' . rawurlencode($identifier)
                    : null,
                'archivePendingIndexing' => $archivePendingIndexing,
            ],
        );
    }
}
