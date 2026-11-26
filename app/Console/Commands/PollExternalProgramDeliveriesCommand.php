<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ExternalPrograms\ExternalProgramInboxPoller;
use Illuminate\Console\Command;
use Throwable;

class PollExternalProgramDeliveriesCommand extends Command
{
    protected $signature = 'external-programs:poll
                            {--since= : Fecha/hora mínima de recepción (ej. 2026-11-26 o 2026-11-26 10:00:00)}
                            {--mailbox= : Buzón IMAP específico a consultar}
                            {--limit=50 : Límite de mensajes por consulta (1-200)}
                            {--dry-run : Simular detección sin guardar ni enviar avisos}';

    protected $description = 'Consulta en modo solo lectura el buzón de entregas externas de programas y envía avisos iniciales sin descargar archivos';

    public function handle(ExternalProgramInboxPoller $poller): int
    {
        $since = $this->option('since');
        $mailbox = $this->option('mailbox');
        $limit = max(1, min(200, (int) $this->option('limit')));
        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $poller->poll(
                sinceOverride: is_string($since) && trim($since) !== '' ? trim($since) : null,
                mailboxOverride: is_string($mailbox) && trim($mailbox) !== '' ? trim($mailbox) : null,
                limit: $limit,
                dryRun: $dryRun
            );
        } catch (Throwable $e) {
            $this->error('Error al consultar entregas externas: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($result['status'] === 'skipped') {
            $this->line('Omitido: ' . $result['reason']);
            return self::SUCCESS;
        }

        if ($result['status'] === 'error') {
            $this->error('Error: ' . $result['reason']);
            return self::FAILURE;
        }

        $prefix = $dryRun ? '[DRY-RUN] ' : '';
        $this->info(sprintf(
            '%sBuzón %s (desde %s): analizados=%d, registrados=%d, duplicados=%d, ignorados=%d, avisos_ok=%d, avisos_fallidos=%d',
            $prefix,
            (string) $result['mailbox'],
            (string) $result['since'],
            (int) $result['scanned'],
            (int) $result['registered'],
            (int) $result['duplicates'],
            (int) $result['ignored'],
            (int) $result['notified'],
            (int) $result['notification_failures']
        ));

        return self::SUCCESS;
    }
}

