<?php

namespace App\Console\Commands;

use App\Services\AudienceReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class GenerateWeeklyAudienceReportCommand extends Command
{
    protected $signature = 'audience:generate-weekly-report
                            {--week= : Número de semana ISO a procesar (por defecto: semana anterior)}
                            {--year= : Año ISO (por defecto: año correspondiente a la semana anterior)}
                            {--dry-run : Ejecutar simulación sin guardar ficheros en disco}
                            {--force : Sobrescribir el informe si ya fue generado previamente}';

    protected $description = 'Genera y consolida el informe semanal de audiencia combinando métricas de RadioBOSS y Cloudflare';

    public function handle(AudienceReportService $service): int
    {
        $now = Carbon::now('Europe/Madrid');
        $defaultPrevious = $now->copy()->subWeek();

        $week = (int) ($this->option('week') ?: $defaultPrevious->isoWeek());
        $year = (int) ($this->option('year') ?: $defaultPrevious->isoWeekYear());
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->info("Iniciando consolidación de informe semanal [Año: {$year}, Semana: {$week}]" . ($dryRun ? ' (MODO SIMULACIÓN)' : ''));

        try {
            $report = $service->generateReport($year, $week, $dryRun, $force);

            $this->newLine();
            $this->info("✅ Informe {$report['id']} generado con éxito.");
            $this->table(
                ['Métrica', 'Valor'],
                [
                    ['Período', $report['period']['label']],
                    ['Streaming - Sesiones', number_format($report['radioboss']['total_sessions'], 0, ',', '.')],
                    ['Streaming - Horas de escucha (TLH)', number_format($report['radioboss']['total_tlh'], 0, ',', '.') . ' h'],
                    ['Streaming - Oyentes únicos (suma diaria)', number_format($report['radioboss']['total_unique'], 0, ',', '.')],
                    ['Web - Páginas vistas (HTML)', number_format($report['cloudflare']['page_views'], 0, ',', '.')],
                    ['Web - Visitantes únicos semanales', number_format($report['cloudflare']['unique_visitors'], 0, ',', '.')],
                    ['Web - Peticiones totales edge', number_format($report['cloudflare']['requests'], 0, ',', '.')],
                    ['PDF generado', $dryRun ? 'Simulado OK' : ($report['pdf_path'] ?? 'OK')],
                ]
            );

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error("❌ Error al generar el informe: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

