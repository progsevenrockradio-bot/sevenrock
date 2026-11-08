<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SubmissionFollowupService;
use Illuminate\Console\Command;

final class ProcessSubmissionMuroFollowupsCommand extends Command
{
    protected $signature = 'submissions:process-muro-followups {--dry-run : Simula y explica a quién correspondería enviar y por qué, sin enviar nada}';

    protected $description = 'Procesa o simula el seguimiento opcional de maquetas hacia el Muro del Rock (3 días tras la primera publicación efectiva).';

    public function handle(SubmissionFollowupService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $summary = $service->processDueFollowups($dryRun);

        $this->info(($dryRun ? '[SIMULACIÓN / DRY-RUN] ' : '') . 'Seguimiento Muro del Rock (' . $service->campaignKey() . ')');
        $this->line(' - Campaña activa: ' . ($summary['campaign_enabled'] ? 'SÍ' : 'NO (desactivada por defecto)'));
        $this->line(' - URL comercial aprobada: ' . ($summary['has_approved_cta_url'] ? 'SÍ (' . $service->configuredCtaUrl() . ')' : 'PENDIENTE (bloquea envíos)'));
        $this->line(' - Buzón Reply-To configurado: ' . $summary['reply_to']);
        $this->line(" - Resumen: enviables/enviados={$summary['sent']}, excluidos={$summary['skipped']}, fallidos={$summary['failed']}, retenidos/en espera={$summary['blocked_or_waiting']}");

        if ($dryRun && $summary['items'] !== []) {
            $rows = array_map(static fn (array $item): array => [
                (string) $item['id'],
                (string) $item['band_name'],
                (string) $item['contact_email'],
                (string) $item['status'],
                (string) ($item['scheduled_for'] ?? '—'),
                $item['can_send_now'] ? 'SÍ' : 'NO',
                (string) $item['reason_text'],
            ], $summary['items']);

            $this->table(
                ['ID', 'Banda', 'Contacto', 'Estado', 'Previsto', '¿Enviar ahora?', 'Diagnóstico'],
                $rows
            );
        }

        return self::SUCCESS;
    }
}

