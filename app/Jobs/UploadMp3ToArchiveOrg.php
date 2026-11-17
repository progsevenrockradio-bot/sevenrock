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
        protected ?string $tempFilePath = null,
        protected ?string $originalFileName = null
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

        // 1. Autorización INDEPENDIENTE para Archive.org (desactivada por defecto).
        //    La aprobación de emisión en radio (approved_for_broadcast) NO autoriza la redistribución pública.
        //    Aplica también a invocaciones directas del job y excluye el canal /submissions.
        if ($newRelease->origin === 'submission' || ! $newRelease->isApprovedForArchive()) {
            Log::warning("UploadMp3ToArchiveOrg: Lanzamiento ID {$this->newReleaseId} sin autorización independiente para Archive.org. Subida abortada.");
            $this->cleanTempFile($newRelease);
            return;
        }

        // 2. Verificación de embargo activo vigente
        if ($newRelease->isUnderActiveEmbargo()) {
            Log::warning("UploadMp3ToArchiveOrg: Lanzamiento ID {$this->newReleaseId} está bajo embargo activo vigente. Subida abortada.");
            $this->cleanTempFile($newRelease);
            return;
        }

        // 3. Idempotencia: Si ya está alojado en Archive.org, omitir
        if (str_contains((string) $newRelease->audio_path, 'archive.org')) {
            Log::info("UploadMp3ToArchiveOrg: El lanzamiento ID {$this->newReleaseId} ya tiene audio alojado en Archive.org ({$newRelease->audio_path}). Subida omitida por idempotencia.");
            $this->cleanTempFile($newRelease);
            return;
        }

        $cleanupTempFile = null;
        try {
            $localFilePath = $this->resolveLocalAudioFile($newRelease, $cleanupTempFile);
            if (! $localFilePath || ! file_exists($localFilePath)) {
                Log::error("UploadMp3ToArchiveOrg: No se pudo resolver el archivo de audio para el lanzamiento ID: {$this->newReleaseId}");
                $this->cleanTempFile($newRelease);
                return;
            }

            $settings = \App\Models\ThemeSetting::current();
            $accessKey = trim((string) $settings->archive_access_key);
            $secretKey = trim((string) $settings->archive_secret_key);

            if ($accessKey === '' || $secretKey === '') {
                Log::error("UploadMp3ToArchiveOrg: Claves de API de Archive.org no configuradas.");
                $this->cleanTempFile($newRelease);
                return;
            }

            // Construir identificador del bucket único y válido para Archive.org (minúsculas, guiones)
            $cleanSlug = Str::slug($newRelease->slug ?: ($newRelease->title . '-' . $newRelease->artist_name));
            $configBucket = trim((string) config('services.imap.archive_bucket'));
            if ($configBucket === '') {
                $configBucket = trim((string) config('services.archive_org.bucket'));
            }
            
            $bucketName = ($configBucket !== '') ? $configBucket : ('sevenrockradio-' . substr($cleanSlug, 0, 80));
            $safeFileName = $cleanSlug . '.mp3';

            Log::info("UploadMp3ToArchiveOrg: Iniciando subida de {$safeFileName} al bucket Archive.org: {$bucketName}");

            $fileStream = fopen($localFilePath, 'r');
            if ($fileStream === false) {
                throw new \Exception("No se pudo abrir el flujo del archivo de audio.");
            }

            $headers = [
                'Authorization' => "LOW {$accessKey}:{$secretKey}",
            ];

            // Solo enviamos metadatos de creación del bucket si estamos usando un bucket dinámico único.
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

            // Limpiar el archivo temporal si procedía del constructor
            $this->cleanTempFile($newRelease);

        } catch (\Throwable $e) {
            Log::error("UploadMp3ToArchiveOrg: Error al subir a Archive.org: " . $e->getMessage());
            throw $e; // Reintentar el Job
        } finally {
            if ($cleanupTempFile && file_exists($cleanupTempFile)) {
                @unlink($cleanupTempFile);
            }
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

        if (file_exists($audioPath)) {
            return $audioPath;
        }

        $relative = ltrim((string) (preg_replace('#^/storage/#', '', parse_url($audioPath, PHP_URL_PATH) ?? $audioPath)), '/');
        if (Storage::disk('public')->exists($relative)) {
            return Storage::disk('public')->path($relative);
        }
        if (file_exists(public_path($relative))) {
            return public_path($relative);
        }

        if (str_starts_with($audioPath, 'http://') || str_starts_with($audioPath, 'https://')) {
            try {
                $tempPath = tempnam(sys_get_temp_dir(), 'ia_upload_');
                $response = Http::timeout(60)->sink($tempPath)->get($audioPath);
                if ($response->successful()) {
                    $cleanupTempFile = $tempPath;
                    return $tempPath;
                }
                @unlink($tempPath);
            } catch (\Throwable $e) {
                Log::warning("UploadMp3ToArchiveOrg: No se pudo descargar audio desde URL: " . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Limpia exclusivamente archivos temporales de ingesta/descarga; nunca borra el audio web permanente.
     */
    protected function cleanTempFile(?NewRelease $newRelease = null): void
    {
        if (! $this->tempFilePath || ! file_exists($this->tempFilePath)) {
            return;
        }

        if (! $this->isStrictlyTemporaryFile($this->tempFilePath, $newRelease)) {
            Log::warning("UploadMp3ToArchiveOrg: Se omitió el borrado de {$this->tempFilePath} por no ser una ruta temporal autorizada.");
            return;
        }

        @unlink($this->tempFilePath);
        Log::info("UploadMp3ToArchiveOrg: Archivo temporal eliminado: {$this->tempFilePath}");
    }

    /**
     * Comprueba que la ruta pertenezca a storage/app/temp_audio (o un temporal ia_upload_ del sistema)
     * y nunca coincida con el audio web permanente ni con rutas de catálogo/maquetas.
     */
    protected function isStrictlyTemporaryFile(string $candidatePath, ?NewRelease $newRelease = null): bool
    {
        $norm = static fn (string $p): string => strtolower(str_replace('\\', '/', (string) (realpath($p) ?: $p)));
        $target = $norm($candidatePath);

        if (str_contains($target, '/catalog/releases/') || str_contains($target, '/submissions/')) {
            return false;
        }

        if ($newRelease && ! empty($newRelease->audio_path)) {
            $audioPath = (string) $newRelease->audio_path;
            $relative = ltrim((string) (preg_replace('#^/storage/#', '', parse_url($audioPath, PHP_URL_PATH) ?? $audioPath)), '/');
            $protected = [
                $norm($audioPath),
                $norm(Storage::disk('public')->path($relative)),
                $norm(public_path($relative)),
            ];
            if (in_array($target, $protected, true)) {
                return false;
            }
        }

        $tempAudioDir = rtrim($norm(storage_path('app/temp_audio')), '/') . '/';
        if (str_starts_with($target, $tempAudioDir)) {
            return true;
        }

        $sysTempDir = rtrim($norm(sys_get_temp_dir()), '/') . '/';
        if (str_starts_with($target, $sysTempDir) && str_starts_with(basename($target), 'ia_upload_')) {
            return true;
        }

        return false;
    }
}
