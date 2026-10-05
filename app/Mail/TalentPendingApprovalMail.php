<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Talent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

final class TalentPendingApprovalMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly Talent $talent)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[7RR] Nueva banda pendiente de aprobación: {$this->talent->band_name}",
        );
    }

    public function content(): Content
    {
        $approveUrl = URL::temporarySignedRoute(
            'admin.talents.approve-email',
            now()->addDays(7),
            ['talent' => $this->talent->id]
        );

        $rejectUrl = URL::temporarySignedRoute(
            'admin.talents.reject-email',
            now()->addDays(7),
            ['talent' => $this->talent->id]
        );

        return new Content(
            view: 'emails.talents.pending_approval',
            with: [
                'talent'                => $this->talent,
                'approveUrl'            => $approveUrl,
                'rejectUrl'             => $rejectUrl,
                'panelUrl'              => route('admin.talents.index'),
                'facebookScreenshotUrl' => $this->talent->facebook_screenshot
                    ? (str_starts_with($this->talent->facebook_screenshot, 'http')
                        ? $this->talent->facebook_screenshot
                        : asset('storage/' . $this->talent->facebook_screenshot))
                    : null,
                'instagramScreenshotUrl' => $this->talent->instagram_screenshot
                    ? (str_starts_with($this->talent->instagram_screenshot, 'http')
                        ? $this->talent->instagram_screenshot
                        : asset('storage/' . $this->talent->instagram_screenshot))
                    : null,
            ],
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        if ($this->talent->facebook_screenshot && Storage::disk('public')->exists($this->talent->facebook_screenshot)) {
            $attachments[] = Attachment::fromPath(Storage::disk('public')->path($this->talent->facebook_screenshot))
                ->as('facebook_screenshot.jpg');
        }

        if ($this->talent->instagram_screenshot && Storage::disk('public')->exists($this->talent->instagram_screenshot)) {
            $attachments[] = Attachment::fromPath(Storage::disk('public')->path($this->talent->instagram_screenshot))
                ->as('instagram_screenshot.jpg');
        }

        return $attachments;
    }
}
