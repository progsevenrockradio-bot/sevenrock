<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ThemeSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envía resúmenes editoriales deduplicados mediante @SevenRock_Bot
 * cuando un lote queda retenido sin renovar portada por bloqueos técnicos o editoriales.
 * Respeta un ajuste administrable independiente y nunca altera los avisos de programas recibidos.
 */
class EditorialTelegramDigestService
{
    public const SETTING_KEY = 'editorial_telegram_alerts_enabled';

    public function isEnabled(): bool
    {
        try {
            $settings = ThemeSetting::current()->settings ?? [];

            return (bool) ($settings[self::SETTING_KEY] ?? false);
        } catch (\Throwable) {
            return false;
        }
    }

    public function setEnabled(bool $enabled): void
    {
        $theme = ThemeSetting::current();
        $settings = is_array($theme->settings) ? $theme->settings : [];
        $settings[self::SETTING_KEY] = $enabled;
        $theme->settings = $settings;
        $theme->save();
    }

    /**
     * Envía un único resumen deduplicado por lote/estado sin repetir el mismo incidente
     * ni enviar un mensaje por cada imagen.
     *
     * @param array{
     *   batch_id: string,
     *   published_count: int,
     *   technical_retry_count: int,
     *   technical_permanent_count: int,
     *   editorial_pending_count: int,
     *   next_retry_at?: ?string
     * } $summary
     * @return array{sent: bool, reason: string}
     */
    public function notifyBatchBlockedSummary(array $summary): array
    {
        if (! $this->isEnabled()) {
            return ['sent' => false, 'reason' => 'disabled'];
        }

        $published = (int) ($summary['published_count'] ?? 0);
        $techRetry = (int) ($summary['technical_retry_count'] ?? 0);
        $techPerm = (int) ($summary['technical_permanent_count'] ?? 0);
        $editorial = (int) ($summary['editorial_pending_count'] ?? 0);
        $batchId = trim((string) ($summary['batch_id'] ?? 'lote'));

        // Solo avisar cuando el lote no renueve portada por bloqueos (0 publicadas y algún bloqueo)
        if ($published > 0 || ($techRetry + $techPerm + $editorial) === 0) {
            return ['sent' => false, 'reason' => 'not_blocked'];
        }

        $fingerprint = hash('sha256', implode('|', [$batchId, $published, $techRetry, $techPerm, $editorial]));
        if (! Cache::add("editorial_tg_digest:{$fingerprint}", true, now()->addHours(6))) {
            return ['sent' => false, 'reason' => 'deduplicated'];
        }

        $token = trim((string) config('services.external_programs.telegram_bot_token', ''));
        $chatId = trim((string) config('services.external_programs.telegram_chat_id', ''));

        if ($token === '' || $chatId === '') {
            return ['sent' => false, 'reason' => 'missing_telegram_config'];
        }

        $lines = [
            "📰 <b>Resumen Bandeja Editorial</b> ({$batchId})",
            "El lote no ha renovado portada por bloqueos activos:",
            "• Publicadas: <b>{$published}</b>",
            "• Fallos técnicos recuperables (reintento prog.): <b>{$techRetry}</b>",
            "• Fallos técnicos permanentes: <b>{$techPerm}</b>",
            "• Decisiones editoriales pendientes: <b>{$editorial}</b>",
        ];

        if (! empty($summary['next_retry_at'])) {
            $lines[] = "⏱ Próximo reintento técnico: {$summary['next_retry_at']}";
        }

        $lines[] = "🔗 Bandeja: https://sevenrockradio.com/admin/editorial?tab=review";

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id'                  => $chatId,
                'text'                     => implode("\n", $lines),
                'parse_mode'               => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            if (! $response->successful()) {
                Log::warning('EditorialTelegramDigestService: fallo al enviar resumen.', ['status' => $response->status()]);

                return ['sent' => false, 'reason' => 'http_' . $response->status()];
            }

            return ['sent' => true, 'reason' => 'sent'];
        } catch (\Throwable $e) {
            Log::warning('EditorialTelegramDigestService: excepción enviando resumen.', ['error' => $e->getMessage()]);

            return ['sent' => false, 'reason' => 'exception'];
        }
    }
}

