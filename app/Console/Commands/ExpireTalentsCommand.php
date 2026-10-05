<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\TalentHiddenNotificationMail;
use App\Mail\TalentMaterialDeletedMail;
use App\Models\Talent;
use App\Models\TalentMedia;
use App\Services\BackblazeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ExpireTalentsCommand extends Command
{
    public const FREE_DURATION_DAYS = Talent::FREE_DURATION_DAYS;
    public const GRACE_DAYS = Talent::GRACE_DAYS;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'talents:expire {--force : Ejecutar sin esperar confirmación}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gestiona el vencimiento de bandas (ocultación a los 45 días y borrado de material tras 15 días de gracia)';

    /**
     * Execute the console command.
     */
    public function handle(BackblazeService $backblaze): int
    {
        $this->info("Iniciando revisión de vencimientos de talentos...");

        // 1. PASO 2: Identificar bandas cuyo end_date ha llegado o vencido y pasarlas a OCULTO
        $now = now();
        $today = today();

        $toHide = Talent::query()
            ->where('is_hidden', false)
            ->where('subscription_status', 'active')
            ->whereHas('subscriptions', function ($q) use ($today): void {
                $q->where('status', 'active')
                  ->whereNotNull('end_date')
                  ->whereDate('end_date', '<=', $today);
            })
            ->with(['subscriptions' => fn ($q) => $q->where('status', 'active')->latest('end_date')])
            ->get();

        $hiddenCount = 0;
        foreach ($toHide as $talent) {
            $latestSub = $talent->subscriptions->first();

            $talent->update([
                'is_hidden' => true,
                'subscription_status' => 'expired',
                'expires_grace_at' => $now->copy()->addDays(self::GRACE_DAYS),
            ]);

            if ($latestSub) {
                $latestSub->update(['status' => 'expired']);
            }

            if (filled($talent->email)) {
                try {
                    Mail::to($talent->email)->send(new TalentHiddenNotificationMail($talent));
                    $this->line("  [OCULTO] Perfil ocultado y notificado: {$talent->band_name} ({$talent->email})");
                } catch (Throwable $e) {
                    Log::error("Error al enviar email de talento oculto a {$talent->email}: " . $e->getMessage());
                }
            }

            $hiddenCount++;
        }

        $this->info("Paso 1 completado: {$hiddenCount} banda(s) pasaron a OCULTO.");

        // 2. PASO 4: Identificar bandas en gracia vencida (han pasado los 15 días sin renovar) y purgar material
        $toPurge = Talent::query()
            ->where('is_hidden', true)
            ->where('subscription_status', 'expired')
            ->where(function ($q) use ($now, $today): void {
                $q->where(function ($sub) use ($now): void {
                    $sub->whereNotNull('expires_grace_at')
                        ->where('expires_grace_at', '<=', $now);
                })->orWhere(function ($sub) use ($today): void {
                    // Fallback para registros sin expires_grace_at calculado
                    $sub->whereNull('expires_grace_at')
                        ->whereHas('subscriptions', function ($sq) use ($today): void {
                            $sq->whereNotNull('end_date')
                               ->whereDate('end_date', '<=', $today->copy()->subDays(self::GRACE_DAYS));
                        });
                });
            })
            ->with(['media', 'albums'])
            ->get();

        $purgedCount = 0;
        foreach ($toPurge as $talent) {
            $mediaCount = $talent->media->count();

            // Eliminar archivos físicos de TalentMedia y sus registros
            foreach ($talent->media as $media) {
                if (filled($media->backblaze_key)) {
                    try {
                        $backblaze->delete($media->backblaze_key);
                    } catch (Throwable) {
                        //
                    }
                }

                if (filled($media->path) && Storage::disk('public')->exists($media->path)) {
                    try {
                        Storage::disk('public')->delete($media->path);
                    } catch (Throwable) {
                        //
                    }
                }

                $media->forceDelete();
            }

            // Eliminar álbumes asociados si existen
            foreach ($talent->albums as $album) {
                if (filled($album->cover_path) && Storage::disk('public')->exists($album->cover_path)) {
                    try {
                        Storage::disk('public')->delete($album->cover_path);
                    } catch (Throwable) {
                        //
                    }
                }
                $album->forceDelete();
            }

            // La ficha de la banda se conserva con el material vacío y status = expired
            $talent->update([
                'is_hidden' => true,
                'subscription_status' => 'expired',
                'expires_grace_at' => null, // Ya se ejecutó la purga
            ]);

            if (filled($talent->email) && $mediaCount > 0) {
                try {
                    Mail::to($talent->email)->send(new TalentMaterialDeletedMail($talent));
                    $this->line("  [PURGADO] Material borrado y notificado: {$talent->band_name} ({$talent->email})");
                } catch (Throwable $e) {
                    Log::error("Error al enviar email de material purgado a {$talent->email}: " . $e->getMessage());
                }
            }

            $purgedCount++;
        }

        $this->info("Paso 2 completado: {$purgedCount} banda(s) tuvieron su material borrado.");
        return self::SUCCESS;
    }
}
