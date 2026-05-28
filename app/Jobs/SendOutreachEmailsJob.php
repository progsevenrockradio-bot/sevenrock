<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\OutreachMail;
use App\Models\BandContact;
use App\Models\OutreachCampaign;
use App\Models\OutreachLog;
use App\Models\OutreachTemplate;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOutreachEmailsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public string $queue = 'default';

    /**
     * @param array<int, int> $contactIds
     */
    public function __construct(
        public int $campaignId,
        public array $contactIds = [],
    ) {
    }

    public function handle(): void
    {
        $campaign = OutreachCampaign::query()->with('template')->findOrFail($this->campaignId);
        if ($campaign->completed_at !== null) {
            return;
        }

        $template = $campaign->template;
        if (! $template instanceof OutreachTemplate) {
            $campaign->forceFill([
                'completed_at' => now(),
            ])->saveQuietly();

            return;
        }

        $contactQuery = BandContact::query()->with('radioArtist');
        if ($this->contactIds !== []) {
            $contactQuery->whereIn('id', $this->contactIds);
        }

        $contacts = $contactQuery
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->orderBy('id')
            ->get();

        $sent = 0;
        $opened = (int) $campaign->opened_count;
        $responded = (int) $campaign->responded_count;

        foreach ($contacts as $index => $contact) {
            $subject = $template->renderSubject($contact);
            $body = $template->renderBody($contact);

            try {
                Mail::to($contact->email)->send(new OutreachMail(
                    subjectLine: $subject,
                    bodyHtml: $body,
                    campaignName: $campaign->name,
                    bandName: $contact->bandName(),
                    contactPerson: (string) ($contact->contact_person ?? ''),
                ));

                OutreachLog::query()->create([
                    'campaign_id' => $campaign->id,
                    'band_contact_id' => $contact->id,
                    'template_id' => $template->id,
                    'recipient_email' => (string) $contact->email,
                    'subject' => $subject,
                    'body' => $body,
                    'status' => 'sent',
                    'sent_at' => Carbon::now(),
                ]);

                $sent++;
                $contact->forceFill([
                    'status' => 'contacted',
                    'last_contacted_at' => now(),
                ])->saveQuietly();
            } catch (Throwable $exception) {
                OutreachLog::query()->create([
                    'campaign_id' => $campaign->id,
                    'band_contact_id' => $contact->id,
                    'template_id' => $template->id,
                    'recipient_email' => (string) $contact->email,
                    'subject' => $subject,
                    'body' => $body,
                    'status' => 'failed',
                    'error_message' => $exception->getMessage(),
                    'sent_at' => Carbon::now(),
                ]);
            }

            if ($index < $contacts->count() - 1) {
                usleep(1200000);
            }
        }

        $campaign->forceFill([
            'sent_count' => $sent,
            'opened_count' => $opened,
            'responded_count' => $responded,
            'completed_at' => Carbon::now(),
        ])->saveQuietly();
    }
}
