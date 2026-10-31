<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\TalentExpiryWarningMail;
use App\Models\Talent;
use App\Models\TalentSubscriptionReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envía la secuencia de avisos de vencimiento y periodo de gracia a talentos:
 * 1. 7 días antes del vencimiento.
 * 2. 1 día antes del vencimiento.
 * 3. 3 días antes de finalizar los 15 días de gracia (último aviso antes de purga).
 *
 * Registra cada envío en talent_subscription_reminders para evitar duplicados por ciclo.
 */
class SendExpiryWarningsCommand extends Command
{
    /** @var string */
    protected $signature = 'talents:send-expiry-warnings
                            {--days= : Días específicos antes del vencimiento (opcional)}
                            {--stage= : Etapa específica (7_days_before, 1_day_before, 3_days_before_purge)}
                            {--dry-run : Muestra los candidatos sin enviar emails}';

    /** @var string */
    protected $description = 'Envía la secuencia de avisos de pre-vencimiento y periodo de gracia a talentos';

    public function handle(): int
    {
        $specificDays = $this->option('days') !== null ? (int) $this->option('days') : null;
        $specificStage = $this->option('stage') ? (string) $this->option('stage') : null;
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Iniciando revisión de avisos de suscripción de talentos (" . ($dryRun ? 'DRY-RUN' : 'REAL') . ")...");

        $totalSent = 0;

        // 1. AVISO 7 DÍAS ANTES (si no se filtró otra etapa o día específico incompatible)
        if (($specificStage === null || $specificStage === TalentSubscriptionReminder::STAGE_7_DAYS_BEFORE) &&
            ($specificDays === null || $specificDays === 7)) {
            $totalSent += $this->processActivePreExpiryNotice(7, TalentSubscriptionReminder::STAGE_7_DAYS_BEFORE, $dryRun);
        }

        // 2. AVISO 1 DÍA ANTES
        if (($specificStage === null || $specificStage === TalentSubscriptionReminder::STAGE_1_DAY_BEFORE) &&
            ($specificDays === null || $specificDays === 1)) {
            $totalSent += $this->processActivePreExpiryNotice(1, TalentSubscriptionReminder::STAGE_1_DAY_BEFORE, $dryRun);
        }

        // 3. AVISO 3 DÍAS ANTES DE FINALIZAR LOS 15 DÍAS DE GRACIA (Aviso previo a la purga)
        if (($specificStage === null || $specificStage === TalentSubscriptionReminder::STAGE_3_DAYS_BEFORE_PURGE) &&
            ($specificDays === null || $specificDays === 3)) {
            $totalSent += $this->processGraceFinalNotice($dryRun);
        }

        $this->info("Revisión de avisos completada. Total enviados: {$totalSent}.");

        return self::SUCCESS;
    }

    private function processActivePreExpiryNotice(int $daysLeft, string $stage, bool $dryRun): int
    {
        $targetDate = today()->addDays($daysLeft);
        $candidates = Talent::query()
            ->where('is_hidden', false)
            ->where('subscription_status', 'active')
            ->whereHas('subscriptions', function ($q) use ($targetDate): void {
                $q->where('status', 'active')
                  ->whereDate('end_date', $targetDate);
            })
            ->with(['subscriptions' => fn ($q) => $q->where('status', 'active')->latest('end_date')])
            ->get();

        $sentCount = 0;

        foreach ($candidates as $talent) {
            $subscription = $talent->subscriptions->first();
            if (! $subscription || ! $subscription->end_date) {
                continue;
            }

            $cycleEndDate = $subscription->end_date;

            // Evitar duplicados por reejecución para la misma banda, ciclo y etapa
            $alreadySent = TalentSubscriptionReminder::query()
                ->where('talent_id', $talent->id)
                ->where('stage', $stage)
                ->where('cycle_end_date', $cycleEndDate)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY-RUN {$stage}] {$talent->band_name} ({$talent->email}) — vence en {$daysLeft} días ({$cycleEndDate->toDateString()})");
                continue;
            }

            if (! filled($talent->email)) {
                continue;
            }

            $purgeDate = $cycleEndDate->copy()->addDays(Talent::GRACE_DAYS);

            try {
                Mail::to($talent->email)->send(new TalentExpiryWarningMail(
                    talent: $talent,
                    daysLeft: $daysLeft,
                    stage: $stage,
                    endDate: $cycleEndDate,
                    purgeDate: $purgeDate,
                ));

                TalentSubscriptionReminder::query()->create([
                    'talent_id' => $talent->id,
                    'talent_subscription_id' => $subscription->id,
                    'stage' => $stage,
                    'cycle_end_date' => $cycleEndDate->toDateString(),
                    'sent_at' => now(),
                    'recipient_email' => $talent->email,
                    'metadata' => [
                        'days_left' => $daysLeft,
                        'end_date' => $cycleEndDate->toDateString(),
                        'purge_date' => $purgeDate->toDateString(),
                    ],
                ]);

                $this->line("  [OK {$stage}] Aviso enviado a: {$talent->band_name} ({$talent->email})");
                $sentCount++;
            } catch (Throwable $e) {
                Log::error("SendExpiryWarnings: error enviando {$stage} a {$talent->email}: " . $e->getMessage());
                $this->warn("  [ERROR {$stage}] {$talent->band_name}: " . $e->getMessage());
            }
        }

        return $sentCount;
    }

    private function processGraceFinalNotice(bool $dryRun): int
    {
        $today = today();
        $targetPurgeDate = $today->copy()->addDays(3);

        // Bandas en gracia cuya purga está programada exactamente en 3 días
        $candidates = Talent::query()
            ->where('is_hidden', true)
            ->where('subscription_status', 'expired')
            ->whereNotNull('expires_grace_at')
            ->whereDate('expires_grace_at', $targetPurgeDate)
            ->with(['subscriptions' => fn ($q) => $q->latest('end_date')])
            ->get();

        $sentCount = 0;

        foreach ($candidates as $talent) {
            $subscription = $talent->subscriptions->first();
            $cycleEndDate = $subscription?->end_date ?? $talent->expires_grace_at?->copy()->subDays(Talent::GRACE_DAYS)?->startOfDay() ?? $today;

            $alreadySent = TalentSubscriptionReminder::query()
                ->where('talent_id', $talent->id)
                ->where('stage', TalentSubscriptionReminder::STAGE_3_DAYS_BEFORE_PURGE)
                ->where('cycle_end_date', $cycleEndDate)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY-RUN 3_days_before_purge] {$talent->band_name} ({$talent->email}) — purga en 3 días ({$targetPurgeDate->toDateString()})");
                continue;
            }

            if (! filled($talent->email)) {
                continue;
            }

            try {
                Mail::to($talent->email)->send(new TalentExpiryWarningMail(
                    talent: $talent,
                    daysLeft: 3,
                    stage: TalentSubscriptionReminder::STAGE_3_DAYS_BEFORE_PURGE,
                    endDate: $cycleEndDate,
                    purgeDate: $talent->expires_grace_at,
                ));

                $formattedCycleEndDate = $cycleEndDate instanceof \Carbon\CarbonInterface ? $cycleEndDate->toDateString() : (string) $cycleEndDate;

                TalentSubscriptionReminder::query()->create([
                    'talent_id' => $talent->id,
                    'talent_subscription_id' => $subscription?->id,
                    'stage' => TalentSubscriptionReminder::STAGE_3_DAYS_BEFORE_PURGE,
                    'cycle_end_date' => $formattedCycleEndDate,
                    'sent_at' => now(),
                    'recipient_email' => $talent->email,
                    'metadata' => [
                        'days_left' => 3,
                        'end_date' => $formattedCycleEndDate,
                        'purge_date' => $talent->expires_grace_at?->toDateString(),
                    ],
                ]);

                $this->line("  [OK 3_days_before_purge] Aviso final enviado a: {$talent->band_name} ({$talent->email})");
                $sentCount++;
            } catch (Throwable $e) {
                Log::error("SendExpiryWarnings: error enviando aviso final de gracia a {$talent->email}: " . $e->getMessage());
                $this->warn("  [ERROR 3_days_before_purge] {$talent->band_name}: " . $e->getMessage());
            }
        }

        return $sentCount;
    }
}
