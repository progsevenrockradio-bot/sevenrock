<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\ProducerInvitationMail;
use App\Models\MasterProgram;
use App\Models\OutreachTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendProducerInvitationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public function __construct(
        public int $programId,
        public int $templateId,
    ) {
    }

    public function handle(): void
    {
        $program = MasterProgram::query()->find($this->programId);
        $template = OutreachTemplate::query()->find($this->templateId);

        if (! $program instanceof MasterProgram || ! $template instanceof OutreachTemplate) {
            return;
        }

        $email = trim((string) $program->email_notificacion);
        if ($email === '') {
            return;
        }

        Mail::to($email)->send(new ProducerInvitationMail($program, $template));
    }
}
