<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\TalentExpiryWarningMail;
use App\Models\Talent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envía un aviso por correo a las bandas cuya suscripción vence en 7 días.
 *
 * Se ejecuta diariamente. Para no enviar el aviso más de una vez, comprueba
 * que la suscripción activa termina EXACTAMENTE en 7 días (±0 días de margen,
 * con tolerancia de 1 día para el cron diario).
 */
class SendExpiryWarningsCommand extends Command
{
    /** @var string */
    protected $signature = 'talents:send-expiry-warnings
                            {--days=7 : Días antes del vencimiento para enviar el aviso}
                            {--dry-run : Muestra los candidatos sin enviar emails}';

    /** @var string */
    protected $description = 'Envía avisos de pre-vencimiento a talentos free que vencen pronto';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $targetDate = today()->addDays($days);

        $this->info("Buscando talentos con suscripción activa que vence el {$targetDate->toDateString()} ({$days} días)...");

        // Bandas con suscripción activa cuyo end_date es exactamente en $days días
        $candidates = Talent::query()
            ->where('is_hidden', false)
            ->where('subscription_status', 'active')
            ->whereHas('subscriptions', function ($q) use ($targetDate): void {
                $q->where('status', 'active')
                  ->whereDate('end_date', $targetDate);
            })
            ->with(['subscriptions' => fn ($q) => $q->where('status', 'active')->latest('end_date')])
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No hay talentos que necesiten aviso hoy.');
            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($candidates as $talent) {
            if ($dryRun) {
                $this->line("  [DRY-RUN] {$talent->band_name} ({$talent->email}) — vence en {$days} días");
                continue;
            }

            if (! filled($talent->email)) {
                continue;
            }

            try {
                Mail::to($talent->email)->send(new TalentExpiryWarningMail($talent, $days));
                $this->line("  [OK] Aviso enviado a: {$talent->band_name} ({$talent->email})");
                $sent++;
            } catch (Throwable $e) {
                Log::error("SendExpiryWarnings: error enviando a {$talent->email}: " . $e->getMessage());
                $this->warn("  [ERROR] {$talent->band_name}: " . $e->getMessage());
            }
        }

        if (! $dryRun) {
            $this->info("Completado: {$sent} aviso(s) enviados de " . $candidates->count() . " candidato(s).");
        }

        return self::SUCCESS;
    }
}
