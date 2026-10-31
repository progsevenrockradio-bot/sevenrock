<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\TalentHiddenNotificationMail;
use App\Mail\TalentMaterialDeletedMail;
use App\Models\Talent;
use App\Models\TalentMedia;
use App\Models\TalentSubscriptionReminder;
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
    public function handle(): int
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

            if (filled($talent->email) && $latestSub) {
                $cycleEndDate = $latestSub->end_date;
                $purgeDate = $talent->expires_grace_at;

                $alreadySent = TalentSubscriptionReminder::query()
                    ->where('talent_id', $talent->id)
                    ->where('stage', TalentSubscriptionReminder::STAGE_AT_EXPIRY)
                    ->where('cycle_end_date', $cycleEndDate)
                    ->exists();

                if (! $alreadySent) {
                    try {
                        Mail::to($talent->email)->send(new TalentHiddenNotificationMail(
                            talent: $talent,
                            expiredDate: $cycleEndDate,
                            purgeDate: $purgeDate,
                        ));

                        TalentSubscriptionReminder::query()->create([
                            'talent_id' => $talent->id,
                            'talent_subscription_id' => $latestSub->id,
                            'stage' => TalentSubscriptionReminder::STAGE_AT_EXPIRY,
                            'cycle_end_date' => $cycleEndDate instanceof \Carbon\CarbonInterface ? $cycleEndDate->toDateString() : (string) $cycleEndDate,
                            'sent_at' => now(),
                            'recipient_email' => $talent->email,
                            'metadata' => [
                                'end_date' => $cycleEndDate?->toDateString(),
                                'grace_days' => self::GRACE_DAYS,
                                'expires_grace_at' => $purgeDate?->toIso8601String(),
                            ],
                        ]);

                        $this->line("  [OCULTO] Perfil ocultado y notificado: {$talent->band_name} ({$talent->email})");
                    } catch (Throwable $e) {
                        Log::error("Error al enviar email de talento oculto a {$talent->email}: " . $e->getMessage());
                    }
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
            $hasFailure = false;
            $deletedMediaCount = 0;

            // 1. Eliminar archivos físicos de TalentMedia en su disco exacto
            foreach ($talent->media as $media) {
                $key = $media->backblaze_key;

                if (! $media->hasDemonstratedDisk()) {
                    Log::warning("ExpireTalentsCommand: medio sin disco demostrado bloquea la purga", [
                        'talent_id' => $talent->id,
                        'media_id'  => $media->id,
                        'key'       => $key,
                    ]);
                    $hasFailure = true;
                    continue; // Conservar registro en BD para reintento futuro y proteger archivo
                }

                $disk = (string) $media->resolvedDisk();

                if (filled($key)) {
                    // Proteger objetos compartidos con otras bandas
                    $isShared = TalentMedia::query()
                        ->where('id', '!=', $media->id)
                        ->where('backblaze_key', $key)
                        ->where(function ($q) use ($disk): void {
                            $q->where('disk', $disk)
                              ->orWhereNull('disk');
                        })
                        ->exists();

                    if (! $isShared) {
                        try {
                            $storage = Storage::disk($disk);
                            if ($storage->exists($key)) {
                                $deleted = $storage->delete($key);
                                if ($deleted === false) {
                                    Log::error("ExpireTalentsCommand: retorno false al borrar archivo físico de R2", [
                                        'talent_id' => $talent->id,
                                        'media_id'  => $media->id,
                                        'disk'      => $disk,
                                        'key'       => $key,
                                    ]);
                                    $hasFailure = true;
                                    continue; // Conservar registro en BD para reintento futuro
                                }
                            }
                        } catch (Throwable $e) {
                            Log::error("ExpireTalentsCommand: excepción al borrar archivo físico de R2", [
                                'talent_id' => $talent->id,
                                'media_id'  => $media->id,
                                'disk'      => $disk,
                                'key'       => $key,
                                'error'     => $e->getMessage(),
                            ]);
                            $hasFailure = true;
                            continue; // Conservar registro en BD para reintento futuro
                        }
                    }
                }

                // Borrado físico verificado o protegido por objeto compartido
                $media->forceDelete();
                $deletedMediaCount++;
            }

            // 2. Eliminar álbumes asociados si existen
            foreach ($talent->albums as $album) {
                if (filled($album->cover_image)) {
                    try {
                        if (Storage::disk('r2')->exists($album->cover_image)) {
                            $deleted = Storage::disk('r2')->delete($album->cover_image);
                            if ($deleted === false) {
                                Log::error("ExpireTalentsCommand: retorno false al borrar portada de álbum", [
                                    'talent_id' => $talent->id,
                                    'album_id'  => $album->id,
                                    'key'       => $album->cover_image,
                                ]);
                                $hasFailure = true;
                                continue;
                            }
                        }
                    } catch (Throwable) {
                        $hasFailure = true;
                        continue;
                    }
                }
                $album->forceDelete();
            }

            // 3. Evaluar si la purga se completó totalmente o tuvo fallos parciales
            if ($hasFailure) {
                $this->warn("  [PARCIAL] Purga incompleta para {$talent->band_name} ({$talent->email}). Se conservan referencias fallidas para reintento.");
                continue;
            }

            // La ficha de la banda se conserva con el material vacío y purga completada
            $talent->update([
                'is_hidden' => true,
                'subscription_status' => 'expired',
                'expires_grace_at' => null, // Marcada como completada
            ]);

            if (filled($talent->email) && $deletedMediaCount > 0) {
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
