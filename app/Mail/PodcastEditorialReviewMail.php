<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\RadioProgram;
use App\Support\PublicMediaUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

final class PodcastEditorialReviewMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public string $reviewUrl;
    public string $confirmApproveUrl;
    public string $confirmApprovalUrl;
    public RadioProgram $radioProgram;
    public int $editorialVersion;

    public function __construct(
        public readonly RadioProgram $episode,
        public readonly string $submitterName,
        public readonly string $submitterEmail,
        public readonly int $version,
        public readonly string $versionToken,
        ?string $reviewUrl = null,
        ?string $confirmApproveUrl = null,
    ) {
        $this->radioProgram = $this->episode;
        $this->editorialVersion = $this->version;
        $this->reviewUrl = $reviewUrl ?: route('admin.podcast-uploads.editorial.show', ['radioProgram' => $this->episode->id]);
        $this->confirmApproveUrl = $confirmApproveUrl ?: URL::temporarySignedRoute(
            'podcast-editorial.email.confirm',
            now()->addHours(48),
            [
                'radioProgram' => $this->episode->id,
                'version' => $this->version,
                'vtoken' => $this->versionToken,
            ]
        );
        $this->confirmApprovalUrl = $this->confirmApproveUrl;
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
            subject: sprintf(
                '[Revisión Editorial v%d] %s #%d — %s',
                $this->version,
                $programName,
                $episodeNumber,
                (string) ($this->episode->live_title ?: 'Nuevo episodio')
            ),
        );
    }

    public function content(): Content
    {
        $checks = is_array($this->episode->editorial_checks) ? $this->episode->editorial_checks : [];
        $objectiveIssues = (array) data_get($checks, 'objective_checks.issues', []);
        $editorialItems = (array) data_get($checks, 'editorial_recommendations.items', []);
        $rawCover = trim((string) ($this->episode->imagen_episodio ?: $this->episode->caratula_programa ?: $this->episode->masterProgram?->caratula_url ?: ''));
        $coverPreviewUrl = PublicMediaUrl::normalizePublicUrl($rawCover);

        return new Content(
            view: 'emails.podcast-editorial-review',
            with: [
                'episode' => $this->episode,
                'programName' => (string) ($this->episode->titulo_programa ?: $this->episode->masterProgram?->nombre ?: 'Seven Rock Radio'),
                'submitterName' => $this->submitterName,
                'submitterEmail' => $this->submitterEmail,
                'version' => $this->version,
                'summaryText' => trim((string) ($this->episode->editorial_summary ?: $this->episode->resena ?: '')),
                'coverPreviewUrl' => $coverPreviewUrl,
                'objectiveIssues' => $objectiveIssues,
                'editorialItems' => $editorialItems,
                'reviewUrl' => $this->reviewUrl,
                'confirmApproveUrl' => $this->confirmApproveUrl,
            ],
        );
    }
}
