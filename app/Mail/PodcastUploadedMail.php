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
    ) {
    }

    public function envelope(): Envelope
    {
        $programName = trim((string) ($this->episode->titulo_programa ?: $this->episode->masterProgram?->name ?: 'Seven Rock Radio'));
        $episodeNumber = (int) ($this->episode->numero_episodio ?? 0);

        return new Envelope(
            from: new Address(
                (string) config('mail.from.address', 'prog.sevenrockradio@gmail.com'),
                (string) config('mail.from.name', config('app.name', 'Seven Rock Radio'))
            ),
            subject: sprintf('Nuevo episodio subido - %s #%d', $programName, $episodeNumber),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.podcast-uploaded',
            with: [
                'episode' => $this->episode,
                'localPath' => $this->localPath,
                'remotePath' => $this->remotePath,
            ],
        );
    }
}
