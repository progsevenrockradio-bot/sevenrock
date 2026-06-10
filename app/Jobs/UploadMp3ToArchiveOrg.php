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
use Illuminate\Support\Str;

class UploadMp3ToArchiveOrg implements ShouldQueue
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
        protected string $originalFileName
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $newRelease = NewRelease::find($this->newReleaseId);
        if (! $newRelease) {
            Log::warning("UploadMp3ToArchiveOrg: Lanzamiento ID {$this->newReleaseId} no encontrado.");
            $this->cleanTempFile();
            return;
        }

        if (! file_exists($this->tempFilePath)) {
            Log::error("UploadMp3ToArchiveOrg: El archivo temporal no existe en la ruta: {$this->tempFilePath}");
            return;
        }

        $settings = \App\Models\ThemeSetting::current();
        $accessKey = trim((string) $settings->archive_access_key);
        $secretKey = trim((string) $settings->archive_secret_key);

        if ($accessKey === '' || $secretKey === '') {
            Log::error("UploadMp3ToArchiveOrg: Claves de API de Archive.org no configuradas.");
            $this->cleanTempFile();
            return;
        }

        // Construir identificador del bucket único y válido para Archive.org (minúsculas, guiones)
        $cleanSlug = Str::slug($newRelease->slug ?: ($newRelease->title . '-' . $newRelease->artist_name));
        $configBucket = trim((string) config('services.archive_org.bucket'));
        
        $bucketName = ($configBucket !== '') ? $configBucket : ('sevenrockradio-' . substr($cleanSlug, 0, 80));
        $safeFileName = $cleanSlug . '.mp3';

        Log::info("UploadMp3ToArchiveOrg: Iniciando subida de {$safeFileName} al bucket Archive.org: {$bucketName}");

        try {
            $fileStream = fopen($this->tempFilePath, 'r');
            if ($fileStream === false) {
                throw new \Exception("No se pudo abrir el flujo del archivo temporal.");
            }

            $headers = [
                'Authorization' => "LOW {$accessKey}:{$secretKey}",
            ];

            // Solo enviamos metadatos de creación del bucket si estamos usando un bucket dinámico único.
            // Si es un bucket compartido (configurado en .env), evitamos sobreescribir su título y colección.
            if ($configBucket === '') {
                $headers['x-archive-auto-make-bucket'] = '1';
                $headers['x-archive-meta-mediatype'] = 'audio';
                $headers['x-archive-meta-collection'] = 'opensource_audio';
                $headers['x-archive-meta-title'] = "Lanzamiento: {$newRelease->title} - {$newRelease->artist_name}";
            }

            // Realizar petición PUT usando la API S3-like de Archive.org
            $response = Http::withHeaders($headers)
                ->withBody($fileStream, 'audio/mpeg')
                ->put("https://s3.us.archive.org/{$bucketName}/{$safeFileName}");

            if (is_resource($fileStream)) {
                fclose($fileStream);
            }

            if ($response->failed()) {
                throw new \Exception("Archive.org respondió con error: Code {$response->status()} - Body: " . $response->body());
            }

            // Generar la URL de reproducción directa
            $playUrl = "https://archive.org/download/{$bucketName}/{$safeFileName}";

            // Actualizar el lanzamiento con la URL final de Archive.org
            $newRelease->update([
                'audio_path' => $playUrl,
            ]);

            Log::info("UploadMp3ToArchiveOrg: Subida exitosa. URL generada: {$playUrl}");

            // Limpiar el archivo temporal
            $this->cleanTempFile();

        } catch (\Throwable $e) {
            Log::error("UploadMp3ToArchiveOrg: Error al subir a Archive.org: " . $e->getMessage());
            throw $e; // Reintentar el Job
        }
    }

    /**
     * Limpiar el archivo temporal.
     */
    protected function cleanTempFile(): void
    {
        if (file_exists($this->tempFilePath)) {
            @unlink($this->tempFilePath);
            Log::info("UploadMp3ToArchiveOrg: Archivo temporal eliminado: {$this->tempFilePath}");
        }
    }
}
