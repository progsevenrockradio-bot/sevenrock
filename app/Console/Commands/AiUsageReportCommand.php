<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ThemeSetting;
use App\Services\AiUsageTracker;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AiUsageReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:usage {--date= : Fecha a consultar en formato YYYY-MM-DD (por defecto hoy)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Muestra el resumen de consumo de IA diario, correos filtrados y estimación de gasto evitado.';

    public function handle(AiUsageTracker $tracker): int
    {
        $dateParam = $this->option('date');
        $date = $dateParam ? Carbon::parse($dateParam) : now();
        $dateStr = $date->toDateString();

        $settings = ThemeSetting::current();
        $maxDailyCalls = (int) ($settings?->ai_daily_max_calls ?? 30);

        $callsToday = $tracker->getCallsToday($date);
        $providerCalls = $tracker->getCallsByProviderToday($date);
        $filteredCount = $tracker->getFilteredToday($date);

        // Posts creados hoy a partir de correos de Dark Vader (publicados directo sin coste)
        $darkVaderPosts = DB::table('posts')
            ->whereDate('created_at', $dateStr)
            ->where('author_email', 'dark.vader.agent@gmail.com')
            ->count();

        // Posts clasificados vía IA
        $aiClassifiedPosts = DB::table('posts')
            ->whereDate('created_at', $dateStr)
            ->where('author_email', '!=', 'dark.vader.agent@gmail.com')
            ->count();

        $aiClassifiedReleases = DB::table('new_releases')
            ->whereDate('created_at', $dateStr)
            ->where('author_email', '!=', 'dark.vader.agent@gmail.com')
            ->count();

        $classifiedCount = $aiClassifiedPosts + $aiClassifiedReleases;

        $savingsEur = $tracker->getEstimatedSavingsEur($date);

        $this->newLine();
        $this->info("=== REPORTE DE USO DE IA (Seven Rock Radio) ===");
        $this->line("Fecha consultada: <comment>{$dateStr}</comment>");
        $this->newLine();

        $providerBreakdown = empty($providerCalls) 
            ? '0' 
            : collect($providerCalls)->map(fn($calls, $p) => "{$p}: {$calls}")->implode(', ');

        $statusLimit = $callsToday >= $maxDailyCalls 
            ? '<fg=red;options=bold>ALCANZADO (bloqueado)</>' 
            : "<fg=green>{$callsToday}/{$maxDailyCalls} permitidas</>";

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Llamadas a la IA', "{$callsToday} ({$providerBreakdown})"],
                ['Tope diario configurado', "{$maxDailyCalls} llamadas"],
                ['Estado del tope diario', $statusLimit],
                ['Correos descartados por pre-filtro (sin coste)', "{$filteredCount} correos"],
                ['Noticias Dark Vader directas (sin coste)', "{$darkVaderPosts} noticias"],
                ['Contenidos clasificados por IA', "{$classifiedCount} ({$aiClassifiedPosts} posts, {$aiClassifiedReleases} lanzamientos)"],
                ['Estimación de gasto evitado hoy', "~{$savingsEur} €"],
            ]
        );

        $this->newLine();
        return Command::SUCCESS;
    }
}
