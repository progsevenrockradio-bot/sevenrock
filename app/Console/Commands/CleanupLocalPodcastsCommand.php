<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RadioProgram;
use App\Services\PodcastPipelineAuditService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('podcast:cleanup-local {--force : Force cleanup even if preserve_local_copy is enabled}')]
#[Description('Clean up local processed MP3 files that have been successfully delivered to RadioBOSS and/or Archive.org')]
final class CleanupLocalPodcastsCommand extends Command
{
    public function handle(PodcastPipelineAuditService $auditService): int
    {
        $force = (bool) $this->option('force');

        $programs = RadioProgram::query()
            ->whereIn('radioboss_status', ['radioboss_verified', 'skipped'])
            ->whereIn('archive_org_status', ['archive_verified', 'archive_skipped'])
            ->get();

        if ($programs->isEmpty()) {
            $this->info('No hay archivos locales de episodios procesados para limpiar.');
            return self::SUCCESS;
        }

        $cleanedCount = 0;
        $savedBytes = 0;

        foreach ($programs as $program) {
            $metadata = $program->delivery_metadata;
            $preserveLocalCopy = (bool) data_get($metadata, 'preserve_local_copy', false);

            if ($preserveLocalCopy && !$force) {
                continue;
            }

            $path = trim((string) $program->archivo_mp3);
            $disk = trim((string) $program->archivo_mp3_disk);
            if ($disk === '') {
                $disk = 'public';
            }

            if ($path !== '' && Storage::disk($disk)->exists($path)) {
                try {
                    $size = (int) Storage::disk($disk)->size($path);
                    Storage::disk($disk)->delete($path);
                    $cleanedCount++;
                    $savedBytes += $size;

                    $auditService->record($program, 'LOCAL_FILE_CLEANED', 'Se eliminó la copia local de audio procesado para liberar espacio en disco (limpieza manual/programada).', [
                        'path' => $path,
                        'disk' => $disk,
                        'size_bytes' => $size,
                    ]);

                    $this->line(sprintf('Archivo limpio: %s (%s MB)', basename($path), round($size / 1024 / 1024, 2)));
                } catch (\Throwable $e) {
                    $this->error(sprintf('No se pudo eliminar %s: %s', $path, $e->getMessage()));
                }
            }
        }

        $this->info(sprintf('Limpieza completada. Archivos eliminados: %d. Espacio liberado: %s MB.', $cleanedCount, round($savedBytes / 1024 / 1024, 2)));

        return self::SUCCESS;
    }
}
