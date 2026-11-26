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
                            {--message-id= : Procesar únicamente el correo con este Message-ID exacto (sin ampliar el histórico)}
                            {--inspect-sender= : Inspeccionar en modo solo lectura los correos recientes de un remitente sin guardar ni avisar}
                            {--limit=50 : Límite de mensajes por consulta (1-200)}
                            {--dry-run : Simular detección sin guardar ni enviar avisos}';

    protected $description = 'Consulta en modo solo lectura el buzón de entregas externas de programas y envía avisos iniciales sin descargar archivos';

    public function handle(ExternalProgramInboxPoller $poller): int
    {
        $since = $this->option('since');
        $mailbox = $this->option('mailbox');
        $messageId = $this->option('message-id');
        $inspectSender = $this->option('inspect-sender');
        $limit = max(1, min(200, (int) $this->option('limit')));
        $dryRun = (bool) $this->option('dry-run');

        if (is_string($inspectSender) && trim($inspectSender) !== '') {
            try {
                $rows = $poller->inspectSenderMessages(
                    senderEmail: trim($inspectSender),
                    mailboxOverride: is_string($mailbox) && trim($mailbox) !== '' ? trim($mailbox) : null,
                    days: 2,
                    limit: min(20, $limit)
                );
            } catch (Throwable $e) {
                $this->error('Error al inspeccionar correos: ' . $e->getMessage());
                return self::FAILURE;
            }

            if ($rows === []) {
                $this->warn('No se encontraron mensajes recientes para ' . trim($inspectSender) . '.');
                return self::SUCCESS;
            }

            foreach ($rows as $row) {
                $this->line('---');
                $this->line('Message-ID: ' . (string) ($row['message_id'] ?? '(sin id)'));
                $this->line('Asunto: ' . (string) ($row['subject'] ?? ''));
                $this->line('Fecha raw: ' . (string) ($row['raw_date'] ?? ''));
                $this->line('Fecha local: ' . (string) ($row['received_local'] ?? ''));
                $this->line('Flags: [' . implode(', ', (array) ($row['flags'] ?? [])) . ']');
                $links = (array) ($row['links'] ?? []);
                $this->line('Enlaces detectados: ' . count($links));
                foreach ($links as $l) {
                    $this->line('  - [' . $l['platform'] . '] ' . $l['url']);
                }
            }

            return self::SUCCESS;
        }

        try {
            $result = $poller->poll(
                sinceOverride: is_string($since) && trim($since) !== '' ? trim($since) : null,
                mailboxOverride: is_string($mailbox) && trim($mailbox) !== '' ? trim($mailbox) : null,
                limit: $limit,
                dryRun: $dryRun,
                messageIdOverride: is_string($messageId) && trim($messageId) !== '' ? trim($messageId) : null
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

