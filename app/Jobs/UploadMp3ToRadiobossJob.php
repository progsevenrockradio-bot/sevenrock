<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\NewRelease;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
     * Create a new job instance.
     */
    public function __construct(
        protected int $newReleaseId,
        protected string $tempFilePath,
        protected string $fileName,
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

        if (! file_exists($this->tempFilePath)) {
            Log::error("UploadMp3ToRadiobossJob: El archivo temporal no existe en la ruta: {$this->tempFilePath}");
            return;
        }

        // Limpiar caracteres extraños en la ruta
        $folder = trim(str_replace('\\', '/', $this->folder), '/');
        $remotePath = $folder . '/' . ltrim(basename($this->fileName), '/');

        Log::info("UploadMp3ToRadiobossJob: Iniciando subida FTP a RadioBOSS de {$remotePath}...");

        try {
            $ftpDisk = Storage::disk('radioboss');

            // Asegurar directorio
            $ftpDisk->makeDirectory($folder);

            $stream = fopen($this->tempFilePath, 'rb');
            if ($stream === false) {
                throw new \Exception("No se pudo abrir el flujo del archivo temporal.");
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

            Log::info("UploadMp3ToRadiobossJob: Subida exitosa a RadioBOSS: {$remotePath}");

        } catch (\Throwable $e) {
            Log::error("UploadMp3ToRadiobossJob: Error al subir a RadioBOSS: " . $e->getMessage());
            throw $e; // Reintentar el Job
        }
    }
}
