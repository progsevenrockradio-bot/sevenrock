<?php

namespace App\Console\Commands;

use App\Services\AudienceReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class GenerateDailyAudienceReportCommand extends Command
{
    protected $signature = 'audience:generate-daily-report
                            {date? : Fecha en formato YYYY-MM-DD (por defecto: día de ayer)}
                            {--dry-run : Ejecutar simulación sin guardar en disco}
                            {--force : Sobrescribir si ya existe en disco}';

    protected $description = 'Obtiene y consolida las métricas diarias de streaming de RadioBOSS para un día cerrado';

    public function handle(AudienceReportService $service): int
    {
        $yesterday = Carbon::yesterday('Europe/Madrid')->format('Y-m-d');
        $date = (string) ($this->argument('date') ?: $yesterday);
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->info("Iniciando obtención de audiencia diaria para [{$date}]" . ($dryRun ? ' (MODO SIMULACIÓN)' : ''));

        try {
            $report = $service->generateDailyReport($date, $dryRun, $force);

            $this->newLine();
            $this->info("✅ Informe diario {$report['id']} generado con éxito.");
            $this->table(
                ['Métrica', 'Valor'],
                [
                    ['Fecha', $report['formatted_date']],
                    ['Sesiones de streaming', number_format($report['radioboss']['total_sessions'], 0, ',', '.')],
                    ['Horas de escucha (TLH)', number_format($report['radioboss']['total_tlh'], 0, ',', '.') . ' h'],
                    ['Oyentes únicos del día', number_format($report['radioboss']['total_unique'], 0, ',', '.')],
                    ['PDF generado', $dryRun ? 'Simulado OK' : ($report['pdf_path'] ?? 'OK')],
                ]
            );

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

