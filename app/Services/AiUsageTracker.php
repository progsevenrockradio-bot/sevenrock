<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiUsageDaily;
use App\Models\ThemeSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiUsageTracker
{
    /**
     * Coste estimado por llamada a la API de IA (en euros).
     * Calculado como promedio de 0.005 € por llamada a Gemini / OpenRouter.
     */
    public const ESTIMATED_COST_PER_CALL_EUR = 0.005;

    /**
     * Incrementa el contador de llamadas para un proveedor en la fecha dada (hoy por defecto).
     * Debe llamarse ANTES de realizar la petición HTTP a la IA.
     */
    public function incrementCall(string $provider = 'total', ?Carbon $date = null): void
    {
        $dateStr = ($date ?: now())->toDateString();
        $provider = strtolower(trim($provider)) ?: 'total';

        try {
            DB::table('ai_usage_daily')->upsert(
                [
                    ['date' => $dateStr, 'provider' => $provider, 'calls' => 1, 'filtered_emails' => 0, 'created_at' => now(), 'updated_at' => now()],
                    ['date' => $dateStr, 'provider' => 'total', 'calls' => 1, 'filtered_emails' => 0, 'created_at' => now(), 'updated_at' => now()],
                ],
                ['date', 'provider'],
                [
                    'calls' => DB::raw('ai_usage_daily.calls + 1'),
                    'updated_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::error("AiUsageTracker: Error al incrementar llamadas para {$provider}: " . $e->getMessage());
        }
    }

    /**
     * Incrementa el contador de correos descartados por el pre-filtro de relevancia.
     */
    public function incrementFiltered(?Carbon $date = null): void
    {
        $dateStr = ($date ?: now())->toDateString();

        try {
            DB::table('ai_usage_daily')->upsert(
                [
                    ['date' => $dateStr, 'provider' => 'prefilter', 'calls' => 0, 'filtered_emails' => 1, 'created_at' => now(), 'updated_at' => now()],
                ],
                ['date', 'provider'],
                [
                    'filtered_emails' => DB::raw('ai_usage_daily.filtered_emails + 1'),
                    'updated_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::error("AiUsageTracker: Error al incrementar correos filtrados: " . $e->getMessage());
        }
    }

    /**
     * Obtiene el número total de llamadas a la IA realizadas hoy (o en la fecha dada).
     */
    public function getCallsToday(?Carbon $date = null): int
    {
        $dateStr = ($date ?: now())->toDateString();

        // Si existe el registro 'total', lo usamos; si no, sumamos los proveedores reales
        $totalRow = DB::table('ai_usage_daily')
            ->where('date', $dateStr)
            ->where('provider', 'total')
            ->first();

        if ($totalRow) {
            return (int) $totalRow->calls;
        }

        return (int) DB::table('ai_usage_daily')
            ->where('date', $dateStr)
            ->whereNotIn('provider', ['total', 'prefilter'])
            ->sum('calls');
    }

    /**
     * Obtiene el desglose de llamadas por proveedor para hoy.
     *
     * @return array<string, int>
     */
    public function getCallsByProviderToday(?Carbon $date = null): array
    {
        $dateStr = ($date ?: now())->toDateString();

        $rows = DB::table('ai_usage_daily')
            ->where('date', $dateStr)
            ->whereNotIn('provider', ['total', 'prefilter'])
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->provider] = (int) $row->calls;
        }

        return $result;
    }

    /**
     * Obtiene el número de correos descartados por el filtro determinista hoy.
     */
    public function getFilteredToday(?Carbon $date = null): int
    {
        $dateStr = ($date ?: now())->toDateString();

        return (int) DB::table('ai_usage_daily')
            ->where('date', $dateStr)
            ->sum('filtered_emails');
    }

    /**
     * Verifica si se ha alcanzado el límite diario configurado de llamadas a la IA.
     */
    public function isDailyLimitReached(?ThemeSetting $settings = null, ?Carbon $date = null): bool
    {
        $settings = $settings ?: ThemeSetting::current();
        $limit = (int) ($settings?->ai_daily_max_calls ?? 30);

        if ($limit <= 0) {
            return false; // Sin límite si se configura en 0 o negativo
        }

        return $this->getCallsToday($date) >= $limit;
    }

    /**
     * Calcula la estimación de gasto evitado hoy (en euros).
     * Se evitan llamadas tanto por el pre-filtro de correos externos como
     * por las noticias directas de Dark Vader que ya no consumen IA.
     */
    public function getEstimatedSavingsEur(?Carbon $date = null): float
    {
        $dateStr = ($date ?: now())->toDateString();
        $filteredCount = $this->getFilteredToday($date);

        // Contar noticias publicadas directamente hoy (de Dark Vader o en general)
        $darkVaderNewsCount = DB::table('posts')
            ->whereDate('created_at', $dateStr)
            ->where('author_email', 'dark.vader.agent@gmail.com')
            ->count();

        $totalCallsAvoided = $filteredCount + $darkVaderNewsCount;

        return round($totalCallsAvoided * self::ESTIMATED_COST_PER_CALL_EUR, 4);
    }
}
