<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\OutreachMail;
use App\Models\BandContact;
use App\Models\OutreachCampaign;
use App\Models\OutreachLog;
use App\Models\OutreachTemplate;
use App\Models\MasterProgram;
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
    public function __construct(
        public int $campaignId,
        public string $recipientMode = 'contacts',
        public array $contactIds = [],
        public ?string $programCode = null,
        public ?string $statusFilter = null,
        public array $producerProgramIds = [],
    ) {
    }

    public function handle(): void
    {
        $campaign = OutreachCampaign::query()->with(['template', 'program'])->findOrFail($this->campaignId);
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

        $sent = 0;

        $recipientRecords = $this->resolveRecipients();
        $totalRecipients = $recipientRecords->count();
        $opened = (int) $campaign->opened_count;
        $responded = (int) $campaign->responded_count;

        foreach ($recipientRecords->values() as $index => $recipient) {
            $contact = $recipient['contact'] ?? null;
            $program = $recipient['program'] ?? null;
            $email = (string) ($recipient['email'] ?? '');
            if ($email === '') {
                continue;
            }

            $subject = $template->renderSubject($program, $contact);
            $body = $template->renderBody($program, $contact);

            try {
                Mail::to($email)->send(new OutreachMail(
                    subjectLine: $subject,
                    bodyHtml: $body,
                    campaignName: $campaign->name,
                    bandName: $contact instanceof BandContact ? $contact->bandName() : (string) ($program?->name ?? 'Programa'),
                    contactPerson: (string) ($contact?->contact_person ?? $program?->conductor ?? ''),
                ));

                OutreachLog::query()->create([
                    'campaign_id' => $campaign->id,
                    'band_contact_id' => $contact instanceof BandContact ? $contact->id : null,
                    'template_id' => $template->id,
                    'recipient_email' => $email,
                    'subject' => $subject,
                    'body' => $body,
                    'status' => 'sent',
                    'sent_at' => Carbon::now(),
                ]);

                $sent++;
                if ($contact instanceof BandContact) {
                    $contact->forceFill([
                        'status' => 'contacted',
                        'last_contacted_at' => now(),
                        'program_code' => $contact->program_code ?: ($program?->program_code ?? $campaign->program_code),
                    ])->saveQuietly();
                }
            } catch (Throwable $exception) {
                OutreachLog::query()->create([
                    'campaign_id' => $campaign->id,
                    'band_contact_id' => $contact instanceof BandContact ? $contact->id : null,
                    'template_id' => $template->id,
                    'recipient_email' => $email,
                    'subject' => $subject,
                    'body' => $body,
                    'status' => 'failed',
                    'error_message' => $exception->getMessage(),
                    'sent_at' => Carbon::now(),
                ]);
            }

            if ($index < $totalRecipients - 1) {
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

    /**
     * @return \Illuminate\Support\Collection<int, array{contact?: BandContact, program?: MasterProgram, email: string}>
     */
    private function resolveRecipients(): \Illuminate\Support\Collection
    {
        if ($this->recipientMode === 'producers') {
            $programs = MasterProgram::query()
                ->whereNotNull('email_notificacion')
                ->where('email_notificacion', '<>', '')
                ->when($this->producerProgramIds !== [], fn ($query) => $query->whereIn('id', $this->producerProgramIds))
                ->get();

            return $programs->map(static function (MasterProgram $program): array {
                return [
                    'program' => $program,
                    'email' => (string) $program->email_notificacion,
                ];
            });
        }

        $contactQuery = BandContact::query()->with('radioArtist', 'program');
        if ($this->contactIds !== []) {
            $contactQuery->whereIn('id', $this->contactIds);
        }
        if (filled($this->programCode)) {
            $contactQuery->where('program_code', $this->programCode);
        }
        if (filled($this->statusFilter)) {
            $contactQuery->where('status', $this->statusFilter);
        }

        return $contactQuery
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->orderBy('id')
            ->get()
            ->map(static function (BandContact $contact): array {
                return [
                    'contact' => $contact,
                    'program' => $contact->program,
                    'email' => (string) $contact->email,
                ];
            });
    }
}
