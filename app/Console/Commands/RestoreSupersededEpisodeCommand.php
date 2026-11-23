<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\UploadRadiobossJob;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RestoreSupersededEpisodeCommand extends Command
{
    protected $signature = 'podcast:restore-superseded-episode
                            {master_program_id=1 : ID del programa maestro (1 para Tardes Literarias)}
                            {--force : Forzar la restauración ignorando la ventana de protección}';

    protected $description = 'Restaura episodios superados en RadioBOSS FTP respetando la ventana de emisión en vivo';

    public function handle(): int
    {
        $masterId = (int) $this->argument('master_program_id');
        $master = MasterProgram::find($masterId);

        if (! $master) {
            $this->error("Programa maestro ID {$masterId} no encontrado.");
            return self::FAILURE;
        }

        // Buscar el episodio más reciente (el que debe estar en emisión)
        $latestEpisode = RadioProgram::where('master_program_id', $masterId)
            ->whereNotNull('fecha_emision')
            ->orderByDesc('fecha_emision')
            ->orderByDesc('numero_episodio')
            ->first();

        // Buscar el episodio anterior superado
        $supersededEpisode = RadioProgram::where('master_program_id', $masterId)
            ->where('id', '!=', $latestEpisode?->id)
            ->whereNotNull('fecha_emision')
            ->orderByDesc('fecha_emision')
            ->orderByDesc('numero_episodio')
            ->first();

        if (! $latestEpisode || ! $supersededEpisode) {
            $this->info("No se identificaron dos episodios comparables para {$master->nombre}.");
            return self::SUCCESS;
        }

        $this->line(sprintf(
            "Verificando programa '%s': Episodio actual #%d (Ep. %s, %s) vs Anterior #%d (Ep. %s, %s)",
            $master->nombre,
            $latestEpisode->id,
            $latestEpisode->numero_episodio,
            optional($latestEpisode->fecha_emision)->format('Y-m-d'),
            $supersededEpisode->id,
            $supersededEpisode->numero_episodio,
            optional($supersededEpisode->fecha_emision)->format('Y-m-d')
        ));

        // 1. Comprobar ventana de protección de emisión en vivo
        $job = new UploadRadiobossJob($latestEpisode->id);
        $window = $job->isInsideLiveBroadcastProtectionWindow($latestEpisode);

        $stateFile = storage_path('app/podcast-restoration-pending.json');

        if ($window !== null && ($window['status'] ?? '') === 'inside_window' && ! $this->option('force')) {
            $remaining = (int) ($window['remaining_seconds'] ?? 0);
            $msg = sprintf(
                "[VENTANA EN VIVO ACTIVA] '%s' está en su horario de emisión/protección (%s a %s). Restan %d segundos (%d min). Se pospone el movimiento de archivos para no interrumpir la reproducción en vivo.",
                $master->nombre,
                $window['window_start'],
                $window['window_end'],
                $remaining,
                (int) ceil($remaining / 60)
            );

            $this->warn($msg);

            file_put_contents($stateFile, json_encode([
                'status' => 'postponed_live_protection',
                'master_program_id' => $masterId,
                'program_name' => $master->nombre,
                'latest_episode_id' => $latestEpisode->id,
                'latest_episode_num' => $latestEpisode->numero_episodio,
                'superseded_episode_id' => $supersededEpisode->id,
                'superseded_episode_num' => $supersededEpisode->numero_episodio,
                'window' => $window,
                'postponed_at' => now()->toIso8601String(),
                'next_attempt_at' => $window['retry_at'] ?? now()->addMinutes(5)->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        // 2. Si estamos fuera de la ventana de protección, proceder con la restauración por FTP
        try {
            $ftpDisk = Storage::disk('radioboss');
            $folder = '01.- LUNES/14.- TARDES LITERARIAS';
            $backupDir = 'respaldos_programas/01.- LUNES/14.- TARDES LITERARIAS';

            $activeFiles = (array) $ftpDisk->files($folder);
            $backupFiles = (array) $ftpDisk->files($backupDir);

            $ep212InBackup = null;
            $ep211InActive = null;

            foreach ($backupFiles as $bf) {
                if (str_contains(basename($bf), '212')) {
                    $ep212InBackup = $bf;
                }
            }

            foreach ($activeFiles as $af) {
                if (str_contains(basename($af), '211')) {
                    $ep211InActive = $af;
                }
            }

            if (! $ep212InBackup && ! $ep211InActive) {
                $this->info("Los archivos de '{$master->nombre}' ya están en su ubicación correcta.");
                @unlink($stateFile);
                return self::SUCCESS;
            }

            $this->info("Iniciando restauración segura de episodios fuera de ventana en vivo...");

            // Paso A: Mover el 211 de emisión activa hacia respaldo
            if ($ep211InActive) {
                $ep211Target = $backupDir . '/' . basename($ep211InActive);
                if ($ftpDisk->exists($ep211Target)) {
                    $ep211Target = $backupDir . '/' . pathinfo(basename($ep211InActive), PATHINFO_FILENAME) . '_' . date('Ymd_His') . '.mp3';
                }
                $this->line("  Moviendo episodio anterior ({$ep211InActive}) a respaldo: {$ep211Target}");
                $ftpDisk->move($ep211InActive, $ep211Target);
            }

            // Paso B: Mover el 212 de respaldo hacia emisión activa
            if ($ep212InBackup) {
                $ep212Target = $folder . '/' . basename($ep212InBackup);
                $this->line("  Moviendo episodio actual ({$ep212InBackup}) a emisión activa: {$ep212Target}");
                $ftpDisk->move($ep212InBackup, $ep212Target);
            }

            // Actualizar metadatos en base de datos
            RadioProgram::withoutEvents(function () use ($latestEpisode, $supersededEpisode, $folder, $backupDir): void {
                $latestEpisode->forceFill([
                    'radioboss_status' => 'radioboss_verified',
                    'enviado_radioboss' => true,
                    'status_message' => 'Episodio 212 restaurado correctamente a emisión activa.',
                    'radioboss_metadata' => array_merge((array) ($latestEpisode->radioboss_metadata ?? []), [
                        'restored_at' => now()->toIso8601String(),
                        'remote_path' => $folder . '/212.- TARDES LITERARIAS. 23-11-2026.mp3',
                    ]),
                ])->saveQuietly();

                $supersededEpisode->forceFill([
                    'status_message' => 'Episodio 211 conservado en respaldos_programas (superado por Ep. 212).',
                    'radioboss_metadata' => array_merge((array) ($supersededEpisode->radioboss_metadata ?? []), [
                        'superseded' => true,
                        'remote_path' => $backupDir . '/211.- TARDES LITERARIAS. 16-11-2026.mp3',
                    ]),
                ])->saveQuietly();
            });

            @unlink($stateFile);

            $this->info("✓ Restauración completada con éxito: Ep 212 devuelto a emisión activa y Ep 211 conservado en respaldo.");
            Log::info("RestoreSupersededEpisodeCommand: Ep 212 restaurado a emisión activa para {$master->nombre}.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Error durante la restauración FTP: " . $e->getMessage());
            Log::error("RestoreSupersededEpisodeCommand: fallo durante restauración FTP", ['exception' => $e]);
            return self::FAILURE;
        }
    }
}
