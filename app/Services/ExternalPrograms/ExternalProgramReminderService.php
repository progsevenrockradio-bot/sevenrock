<?php

declare(strict_types=1);

namespace App\Services\ExternalPrograms;

use App\Models\ExternalProgramDelivery;
use Illuminate\Support\Carbon;

class ExternalProgramReminderService
{
    public function __construct(
        protected ExternalProgramTelegramNotifier $telegramNotifier
    ) {
    }

    /**
     * Evalúa pendientes activos y envía:
     * - Avisos de caducidad próxima (solo cuando la caducidad es conocida).
     * - Recordatorios diarios respetando el aplazamiento (snoozed_until) y evitando repeticiones.
     * Los pendientes resueltos (descargado / descartado) nunca generan recordatorios.
     *
     * @return array{checked: int, daily_sent: int, expiry_sent: int, skipped_snoozed: int, failed: int}
     */
    public function processReminders(bool $dryRun = false, ?Carbon $now = null): array
    {
        $now = $now ? $now->copy() : now();
        $minAgeHours = max(1, (int) config('services.external_programs.reminder_min_age_hours', 20));
        $intervalHours = max(1, (int) config('services.external_programs.reminder_interval_hours', 23));
        $expiryWindowHours = max(1, (int) config('services.external_programs.expiry_alert_hours', 24));

        $stats = [
            'checked' => 0,
            'daily_sent' => 0,
            'expiry_sent' => 0,
            'skipped_snoozed' => 0,
            'failed' => 0,
        ];

        $deliveries = ExternalProgramDelivery::query()
            ->with('masterProgram')
            ->activePending()
            ->orderBy('received_at')
            ->get();

        foreach ($deliveries as $delivery) {
            $stats['checked']++;

            // Respetar el aplazamiento elegido por el usuario
            if ($delivery->snoozed_until !== null && $delivery->snoozed_until->gt($now)) {
                $stats['skipped_snoozed']++;
                continue;
            }

            // 1. Aviso de caducidad (solo cuando expires_at es conocida y está próxima)
            $shouldSendExpiry = $delivery->expires_at !== null
                && $delivery->expires_at->gt($now)
                && $delivery->expires_at->lte($now->copy()->addHours($expiryWindowHours))
                && $delivery->expiry_alert_sent_at === null;

            if ($shouldSendExpiry) {
                if ($dryRun) {
                    $stats['expiry_sent']++;
                } else {
                    $res = $this->telegramNotifier->sendReminder($delivery, 'expiry');
                    if ($res['ok']) {
                        $stats['expiry_sent']++;
                    } else {
                        $stats['failed']++;
                    }
                }
                // Evitar enviar también recordatorio diario en el mismo ciclo
                continue;
            }

            // 2. Recordatorio diario para pendientes
            $referenceTime = $delivery->initial_notified_at ?? $delivery->received_at ?? $delivery->created_at;
            $isOldEnough = $referenceTime !== null && $referenceTime->lte($now->copy()->subHours($minAgeHours));
            $dueForDaily = $delivery->last_reminded_at === null
                || $delivery->last_reminded_at->lte($now->copy()->subHours($intervalHours));

            if ($isOldEnough && $dueForDaily) {
                if ($dryRun) {
                    $stats['daily_sent']++;
                } else {
                    $res = $this->telegramNotifier->sendReminder($delivery, 'daily');
                    if ($res['ok']) {
                        $stats['daily_sent']++;
                    } else {
                        $stats['failed']++;
                    }
                }
            }
        }

        return $stats;
    }
}
