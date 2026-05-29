<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Jobs\Concerns\InteractsWithPodcastUploadPipeline;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Services\AuditTrailService;
use App\Services\FileUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMp3Job implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use InteractsWithPodcastUploadPipeline;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public RadioProgram $radioProgram,
        public string $localPath,
        public bool $preserveLocalCopy = false,
        public ?int $initiatedByUserId = null,
        public ?string $initiatedByName = null,
        public ?string $initiatedByEmail = null,
    ) {
    }

    public function handle(AuditTrailService $auditTrailService): void
    {
        ini_set('memory_limit', '512M');
        set_time_limit(600);

        $actor = $this->resolveAuditActor($this->initiatedByUserId, $this->initiatedByName, $this->initiatedByEmail);
        $fileUploadService = app(FileUploadService::class);
        $sourceDisk = (string) $this->radioProgram->archivo_mp3_disk;
        $sourceAbsolutePath = null;

        try {
            $auditTrailService->recordSystem('upload.started', 'Procesamiento del MP3 iniciado', [
                'actor' => $actor,
                'program_id' => $this->radioProgram->id,
                'master_program_id' => $this->radioProgram->master_program_id,
                'local_path' => $this->localPath,
                'disk' => $sourceDisk,
                'preserve_local_copy' => $this->preserveLocalCopy,
            ]);

            RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                'status_message' => 'Procesando y etiquetando el MP3.',
            ]));

            $master = $this->radioProgram->masterProgram
                ?: MasterProgram::query()->find($this->radioProgram->master_program_id);

            $folder = $this->resolveUploadFolder($master, $this->radioProgram);
            $episodeNumber = $this->syncEpisodeNumberBeforeProcessing($master, $this->radioProgram, $folder);
            $numeroEp = str_pad((string) $episodeNumber, 3, '0', STR_PAD_LEFT);
            $nombreProg = strtoupper(trim((string) $this->radioProgram->titulo_programa));
            $fecha = $this->radioProgram->fecha_emision ? $this->radioProgram->fecha_emision->format('d-m-Y') : date('d-m-Y');
            $fechaTitulo = $this->radioProgram->fecha_emision ? $this->radioProgram->fecha_emision->format('d/m/Y') : date('d/m/Y');
            $anio = $this->radioProgram->fecha_emision ? $this->radioProgram->fecha_emision->format('Y') : date('Y');
            $invitado = trim(strip_tags((string) $this->radioProgram->biografia_invitado));

            $nuevoNombre = "{$numeroEp}.- {$nombreProg}" . ($invitado !== '' ? " ({$invitado})" : '') . " {$fecha}.mp3";
            $nuevoNombre = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '', $nuevoNombre);

            $nombreFtp = function_exists('iconv')
                ? (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nuevoNombre) ?: $nuevoNombre)
                : $nuevoNombre;
            $nombreFtp = preg_replace('/[^A-Za-z0-9\-\.\s#\(\)]/', '', (string) $nombreFtp) ?: $nuevoNombre;

            $nuevaRuta = $this->isProcessedLocalBackup($this->localPath)
                ? (string) $this->localPath
                : 'programas_procesados/' . $nombreFtp;

            $sourceAbsolutePath = $fileUploadService->localPath($this->localPath, $sourceDisk);
            if ($sourceAbsolutePath === null || ! is_file($sourceAbsolutePath)) {
                Log::warning('ProcessMp3Job: No se encontró el MP3 crudo.', [
                    'program_id' => $this->radioProgram->id,
                    'local_path' => $this->localPath,
                    'disk' => $sourceDisk,
                ]);

                if ($sourceAbsolutePath !== null && str_starts_with($sourceAbsolutePath, storage_path('app/tmp/backblaze')) && is_file($sourceAbsolutePath)) {
                    @unlink($sourceAbsolutePath);
                }

                return;
            }

            // Escribir metadata ANTES de subir para que el archivo subido tenga los tags
            $rutaAbsoluta = $sourceDisk === 'backblaze'
                ? $sourceAbsolutePath
                : \Illuminate\Support\Facades\Storage::disk('public')->path(ltrim($nuevaRuta, '/'));
            try {
                $this->escribirMetadata($rutaAbsoluta, $master, $this->radioProgram, $nombreProg, $invitado, $fecha, $fechaTitulo, $anio);
            } catch (Throwable $metadataException) {
                Log::warning('ProcessMp3Job: no se pudo escribir o verificar la metadata del MP3; se continúa con el pipeline.', [
                    'program_id' => $this->radioProgram->id,
                    'localPath' => $this->localPath,
                    'message' => $metadataException->getMessage(),
                ]);
            }

            if (! $this->isProcessedLocalBackup($this->localPath) || $sourceDisk !== 'public') {
                $sourceContents = file_get_contents($sourceAbsolutePath);
                if (! is_string($sourceContents)) {
                    throw new \RuntimeException("No se pudo leer el archivo de origen {$this->localPath}");
                }

                $upload = $fileUploadService->uploadRaw($sourceContents, $nuevaRuta, $sourceDisk === 'backblaze' ? 'backblaze' : 'public');
                if ($upload['key'] === '') {
                    throw new \RuntimeException("No se pudo copiar el archivo de {$this->localPath} a {$nuevaRuta}");
                }

                if ($sourceDisk === 'public' && ! $this->isProcessedLocalBackup($this->localPath)) {
                    $fileUploadService->delete($this->localPath, 'public');
                } elseif ($sourceDisk === 'backblaze' && $this->localPath !== $nuevaRuta && ! $this->preserveLocalCopy) {
                    $fileUploadService->delete($this->localPath, 'backblaze');
                }

                RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                    'archivo_mp3' => $upload['key'],
                    'archivo_mp3_disk' => $upload['disk'],
                ]));
            } elseif ((string) $this->radioProgram->archivo_mp3 !== $nuevaRuta) {
                RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                    'archivo_mp3' => $nuevaRuta,
                    'archivo_mp3_disk' => $sourceDisk !== '' ? $sourceDisk : 'public',
                ]));
            }

            // Metadata ya fue escrita antes de la subida
            $fileName = basename($nuevaRuta);
            $auditTrailService->recordSystem('upload.metadata_verified', 'Metadata del MP3 verificada', [
                'actor' => $actor,
                'program_id' => $this->radioProgram->id,
                'file' => $fileName,
                'local_path' => $nuevaRuta,
            ]);

            RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                'status_message' => 'Metadata escrita. Preparando subidas paralelas.',
                'delivery_metadata' => array_merge((array) ($this->radioProgram->delivery_metadata ?? []), [
                    'preserve_local_copy' => $this->preserveLocalCopy,
                ]),
            ]));

            UploadRadiobossJob::dispatch(
                $this->radioProgram->id,
                $nuevaRuta,
                $folder,
            );

            UploadArchiveOrgJob::dispatch(
                $this->radioProgram->id,
                $nuevaRuta,
            );

            $auditTrailService->recordSystem('upload.completed', 'Procesamiento del MP3 finalizado', [
                'actor' => $actor,
                'program_id' => $this->radioProgram->id,
                'radioboss_status' => $this->radioProgram->radioboss_status,
                'archive_org_status' => $this->radioProgram->archive_org_status,
            ], 'info');
        } catch (Throwable $e) {
            if ($sourceAbsolutePath !== null && str_starts_with($sourceAbsolutePath, storage_path('app/tmp/backblaze')) && is_file($sourceAbsolutePath)) {
                @unlink($sourceAbsolutePath);
            }

            RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                'status_message' => 'El procesamiento del episodio falló.',
            ]));

            $auditTrailService->recordSystem('upload.failed', 'Procesamiento del MP3 falló', [
                'actor' => $actor,
                'program_id' => $this->radioProgram->id,
                'message' => $e->getMessage(),
                'exception' => class_basename($e),
            ], 'error');

            Log::error('Error en ProcessMp3Job', [
                'program_id' => $this->radioProgram->id,
                'localPath' => $this->localPath,
                'exception' => $e,
            ]);

            throw $e;
        }

        if ($sourceAbsolutePath !== null && str_starts_with($sourceAbsolutePath, storage_path('app/tmp/backblaze')) && is_file($sourceAbsolutePath)) {
            @unlink($sourceAbsolutePath);
        }
    }

    private function isProcessedLocalBackup(string $path): bool
    {
        return str_starts_with($path, 'programas_procesados/');
    }
}
