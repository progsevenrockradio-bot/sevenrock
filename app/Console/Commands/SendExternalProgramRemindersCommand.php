<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ExternalPrograms\ExternalProgramReminderService;
use Illuminate\Console\Command;

class SendExternalProgramRemindersCommand extends Command
{
    protected $signature = 'external-programs:send-reminders
                            {--dry-run : Simular recordatorios sin enviar mensajes ni modificar registros}';

    protected $description = 'Envía recordatorios diarios y avisos de caducidad conocida para programas externos pendientes';

    public function handle(ExternalProgramReminderService $reminderService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $stats = $reminderService->processReminders(dryRun: $dryRun);

        $prefix = $dryRun ? '[DRY-RUN] ' : '';
        $this->info(sprintf(
            '%sRevisados=%d, recordatorios_diarios=%d, avisos_caducidad=%d, aplazados=%d, fallos=%d',
            $prefix,
            $stats['checked'],
            $stats['daily_sent'],
            $stats['expiry_sent'],
            $stats['skipped_snoozed'],
            $stats['failed']
        ));

        return self::SUCCESS;
    }
}
