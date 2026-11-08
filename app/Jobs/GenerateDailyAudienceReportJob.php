<?php

namespace App\Jobs;

use App\Services\AudienceReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateDailyAudienceReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $date;
    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(string $date)
    {
        $this->date = $date;
        $this->onQueue('audience');
    }

    public static function statusKey(string $date): string
    {
        return "daily_audience_status_{$date}";
    }

    public function handle(AudienceReportService $service): void
    {
        $key = self::statusKey($this->date);

        if ($service->hasDailyReport($this->date)) {
            Cache::put($key, [
                'status' => 'ready',
                'updated_at' => now()->timestamp,
                'error' => null,
            ], 3600);
            return;
        }

        $current = Cache::get($key, []);
        $startedAt = $current['started_at'] ?? now()->timestamp;

        Cache::put($key, [
            'status' => 'processing',
            'started_at' => $startedAt,
            'updated_at' => now()->timestamp,
            'error' => null,
        ], 180);

        try {
            $service->generateDailyReport($this->date);

            Cache::put($key, [
                'status' => 'ready',
                'started_at' => $startedAt,
                'updated_at' => now()->timestamp,
                'error' => null,
            ], 3600);

            Log::info("GenerateDailyAudienceReportJob: Informe diario {$this->date} generado con éxito en cola 'audience'.");
        } catch (Throwable $e) {
            Cache::put($key, [
                'status' => 'failed',
                'started_at' => $startedAt,
                'updated_at' => now()->timestamp,
                'error' => $e->getMessage(),
            ], 300);

            Log::error("GenerateDailyAudienceReportJob: Fallo al generar informe {$this->date}: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Cache::put(self::statusKey($this->date), [
            'status' => 'failed',
            'updated_at' => now()->timestamp,
            'error' => $exception ? $exception->getMessage() : 'Tiempo límite o error no recuperable en cola de audiencia.',
        ], 300);
    }
}
