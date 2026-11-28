<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ReleaseIntake\ReleaseEmailIntakeService;
use Illuminate\Console\Command;

class PollReleaseIntakeCommand extends Command
{
    protected $signature = 'releases:poll-intake
                            {--limit=30 : Máximo de correos a inspeccionar por carpeta}
                            {--since= : Fecha de inicio opcional (YYYY-MM-DD)}
                            {--dry-run : Simula la consulta sin registrar propuestas}';

    protected $description = 'Consulta en modo solo lectura el buzón configurado para propuestas de nuevos lanzamientos musicales';

    public function handle(ReleaseEmailIntakeService $intakeService): int
    {
        $result = $intakeService->poll(
            limit: max(1, min(100, (int) $this->option('limit'))),
            dryRun: (bool) $this->option('dry-run'),
            sinceOverride: $this->option('since') ? (string) $this->option('since') : null,
        );

        $status = (string) ($result['status'] ?? 'error');
        if ($status === 'disabled' || $status === 'skipped') {
            $this->line('[' . strtoupper($status) . '] ' . ($result['reason'] ?? 'Consulta omitida.'));

            return self::SUCCESS;
        }

        if ($status !== 'ok') {
            $this->error('[ERROR] ' . ($result['reason'] ?? 'No se pudo consultar el buzón.'));

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Consulta completada (%s desde %s): revisados=%d, registrados=%d, duplicados=%d, ignorados=%d, retenidos_spoofing=%d',
            $result['mailbox'] ?? '-',
            $result['since'] ?? '-',
            $result['scanned'] ?? 0,
            $result['registered'] ?? 0,
            $result['duplicates'] ?? 0,
            $result['ignored'] ?? 0,
            $result['spoofing_held'] ?? 0
        ));

        return self::SUCCESS;
    }
}

