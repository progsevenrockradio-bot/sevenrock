<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\NewRelease;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadMp3ToRadiobossJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * El número de veces que el Job puede ser reintentado.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * El número de segundos que el Job puede ejecutarse antes de expirar.
     *
     * @var int
     */
    public $timeout = 300;

    /**
     * Contador interno de intentos para ejecuciones sin payload de worker inyectado.
     */
    protected int $attemptCount = 0;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $newReleaseId,
        protected ?string $tempFilePath = null,
        protected ?string $fileName = null,
        protected string $folder = 'RADIO/Lanzamientos'
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $newRelease = NewRelease::find($this->newReleaseId);
        if (! $newRelease) {
            Log::warning("UploadMp3ToRadiobossJob: Lanzamiento ID {$this->newReleaseId} no encontrado.");
            return;
        }

        // 1. Verificación de origen y aprobación expresa de emisión (aplica también a invocaciones directas del job)
        if ($newRelease->origin === 'submission' || ! $newRelease->isApprovedForBroadcast()) {
            Log::warning("UploadMp3ToRadiobossJob: Lanzamiento ID {$this->newReleaseId} no está aprobado para emisión. Subida abortada.");
            return;
        }

        // 2. Verificación de embargo activo vigente
        if ($newRelease->isUnderActiveEmbargo()) {
            Log::warning("UploadMp3ToRadiobossJob: Lanzamiento ID {$this->newReleaseId} está bajo embargo activo vigente. Subida abortada.");
            return;
        }

        $currentAttempt = max((int) $this->attempts(), ++$this->attemptCount);
        $cleanupTempFile = null;
        try {
            $localFilePath = $this->resolveLocalAudioFile($newRelease, $cleanupTempFile);
            if (! $localFilePath || ! file_exists($localFilePath)) {
                Log::error("UploadMp3ToRadiobossJob: No se pudo resolver el archivo de audio para el lanzamiento ID: {$this->newReleaseId}");
                $this->markDeliveryFailed($newRelease, 'No se pudo localizar el archivo de audio local para enviarlo a RadioBOSS.');
                return;
            }

            // Limpiar caracteres extraños en la ruta
            $effectiveFileName = $this->fileName ?: (Str::slug($newRelease->title . '-' . $newRelease->artist_name) . '.mp3');
            $folder = trim(str_replace('\\', '/', $this->folder), '/');
            $remotePath = $folder . '/' . ltrim(basename($effectiveFileName), '/');

            $ftpDisk = Storage::disk('radioboss');

            // 3. Colisión de nombre remoto: nunca sobrescribir. Solo se da por entregado si tamaño Y huella coinciden.
            //    Si no se puede comprobar la existencia por error transitorio de red/FTP, se lanza excepción para permitir reintentos sin sobrescribir.
            try {
                $remoteExists = $ftpDisk->exists($remotePath);
            } catch (\Throwable $diskEx) {
                throw new \RuntimeException(
                    "No se pudo verificar la existencia remota de {$remotePath} en RadioBOSS; subida detenida para evitar sobrescrituras: " . $diskEx->getMessage(),
                    0,
                    $diskEx
                );
            }

            if ($remoteExists) {
                if ($this->remoteMatchesLocal($ftpDisk, $remotePath, $localFilePath)) {
                    Log::info("UploadMp3ToRadiobossJob: {$remotePath} ya existe con el mismo tamaño y huella. Entrega previa confirmada; no se resube.");
                    $this->markDelivered($newRelease);
                } else {
                    Log::error("UploadMp3ToRadiobossJob: COLISIÓN en RadioBOSS: {$remotePath} ya existe y no se puede demostrar que sea el mismo audio. No se sobrescribe ni se declara entregado.", [
                        'new_release_id' => $newRelease->id,
                    ]);
                    $this->markDeliveryFailed($newRelease, "Colisión en RadioBOSS: {$remotePath} ya existe con otro contenido y no fue sobrescrito.");
                }
                return;
            }

            Log::info("UploadMp3ToRadiobossJob: Iniciando subida FTP a RadioBOSS de {$remotePath} (intento {$currentAttempt}/{$this->tries})...");

            // Asegurar directorio
            $ftpDisk->makeDirectory($folder);

            $stream = fopen($localFilePath, 'rb');
            if ($stream === false) {
                throw new \Exception("No se pudo abrir el flujo del archivo de audio.");
            }

            // Aumentar el tamaño del búfer para subidas más rápidas
            stream_set_chunk_size($stream, 5 * 1024 * 1024);

            $uploaded = false;
            try {
                $uploaded = (bool) $ftpDisk->writeStream($remotePath, $stream);

                if (! $uploaded) {
                    rewind($stream);
                    $uploaded = (bool) $ftpDisk->put($remotePath, stream_get_contents($stream) ?: '');
                }
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if (! $uploaded) {
                throw new \Exception("La subida FTP a RadioBOSS devolvió falso.");
            }

            $this->markDelivered($newRelease);
            Log::info("UploadMp3ToRadiobossJob: Subida exitosa a RadioBOSS: {$remotePath}");

        } catch (\Throwable $e) {
            $maxTries = max(1, (int) $this->tries);
            if ($currentAttempt >= $maxTries) {
                $this->markDeliveryFailed(
                    $newRelease,
                    "Error FTP tras agotar reintentos ({$currentAttempt}/{$maxTries}): " . Str::limit($e->getMessage(), 200)
                );
                Log::error("UploadMp3ToRadiobossJob: Error definitivo tras {$currentAttempt}/{$maxTries} intentos al subir a RadioBOSS: " . $e->getMessage());
            } else {
                Log::warning("UploadMp3ToRadiobossJob: Fallo transitorio en intento {$currentAttempt}/{$maxTries} al subir a RadioBOSS (se mantiene aprobado para reintento automático): " . $e->getMessage());
            }
            throw $e; // Reintentar el Job hasta agotar $tries
        } finally {
            if ($cleanupTempFile && file_exists($cleanupTempFile)) {
                @unlink($cleanupTempFile);
            }
        }
    }

    /**
     * Hook de fallo definitivo invocado por el worker de cola al agotarse todos los reintentos.
     */
    public function failed(?\Throwable $e = null): void
    {
        $newRelease = NewRelease::find($this->newReleaseId);
        if (! $newRelease || $newRelease->origin === 'submission') {
            return;
        }

        if (in_array($newRelease->security_status, [NewRelease::STATUS_APPROVED_FOR_BROADCAST, NewRelease::STATUS_BROADCAST_DELIVERY_FAILED], true)) {
            $maxTries = max(1, (int) $this->tries);
            $reason = $e
                ? "Error FTP tras agotar reintentos ({$maxTries}/{$maxTries}): " . Str::limit($e->getMessage(), 200)
                : "Error FTP tras agotar los {$maxTries} intentos de subida a RadioBOSS.";
            $this->markDeliveryFailed($newRelease, $reason);
        }
    }

    /**
     * Registra la entrega efectiva confirmada en RadioBOSS sin alterar is_active.
     */
    protected function markDelivered(NewRelease $newRelease): void
    {
        if ($newRelease->security_status !== NewRelease::STATUS_BROADCAST_DELIVERED || $newRelease->incident_reason !== null) {
            $newRelease->update([
                'security_status' => NewRelease::STATUS_BROADCAST_DELIVERED,
                'incident_reason' => null,
            ]);
        }
    }

    /**
     * Registra un fallo o colisión de entrega a RadioBOSS para que el panel lo muestre y permita reintentar.
     */
    protected function markDeliveryFailed(NewRelease $newRelease, string $reason): void
    {
        $newRelease->update([
            'security_status' => NewRelease::STATUS_BROADCAST_DELIVERY_FAILED,
            'incident_reason' => $reason,
        ]);
    }

    /**
     * Compara tamaño y huella SHA-256 del archivo remoto con el local. Ante cualquier duda devuelve false.
     */
    protected function remoteMatchesLocal($ftpDisk, string $remotePath, string $localFilePath): bool
    {
        try {
            $localSize = filesize($localFilePath);
            $remoteSize = $ftpDisk->size($remotePath);
            if ($localSize === false || (int) $remoteSize !== (int) $localSize) {
                return false;
            }

            $stream = $ftpDisk->readStream($remotePath);
            if (! is_resource($stream)) {
                return false;
            }
            $ctx = hash_init('sha256');
            hash_update_stream($ctx, $stream);
            fclose($stream);

            return hash_equals((string) hash_file('sha256', $localFilePath), hash_final($ctx));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Resuelve la ruta local accesible del archivo de audio.
     */
    protected function resolveLocalAudioFile(NewRelease $newRelease, ?string &$cleanupTempFile = null): ?string
    {
        if (! empty($this->tempFilePath) && file_exists($this->tempFilePath)) {
            return $this->tempFilePath;
        }

        $audioPath = (string) $newRelease->audio_path;
        if (empty($audioPath)) {
            return null;
        }

        // Ruta de archivo directa
        if (file_exists($audioPath)) {
            return $audioPath;
        }

        // Disco público / local
        $relative = ltrim((string) (preg_replace('#^/storage/#', '', parse_url($audioPath, PHP_URL_PATH) ?? $audioPath)), '/');
        if (Storage::disk('public')->exists($relative)) {
            return Storage::disk('public')->path($relative);
        }
        if (file_exists(public_path($relative))) {
            return public_path($relative);
        }

        // URL remota: descargar a un temporal local para transmitir a RadioBOSS
        if (str_starts_with($audioPath, 'http://') || str_starts_with($audioPath, 'https://')) {
            try {
                $tempPath = tempnam(sys_get_temp_dir(), 'rb_upload_');
                $response = Http::timeout(60)->sink($tempPath)->get($audioPath);
                if ($response->successful()) {
                    $cleanupTempFile = $tempPath;
                    return $tempPath;
                }
                @unlink($tempPath);
            } catch (\Throwable $e) {
                Log::warning("UploadMp3ToRadiobossJob: No se pudo descargar audio desde URL: " . $e->getMessage());
            }
        }

        return null;
    }
}
