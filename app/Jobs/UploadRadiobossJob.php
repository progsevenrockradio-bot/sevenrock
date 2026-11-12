<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\PodcastDeliveryVerified;
use App\Events\PodcastRadiobossUploaded;
use App\Events\PodcastUploadFailed;
use App\Models\RadioProgram;
use App\Services\PodcastPipelineAuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class UploadRadiobossJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    public int $maxExceptions = 3;

    public const MAX_POSTPONEMENTS = 12;

    public int $uniqueFor = 86400;

    /** @var array<int, int> */
    public array $backoff = [2, 5, 10];

    public ?int $postponedSeconds = null;

    public function uniqueId(): string
    {
        return (string) $this->radioProgramId;
    }

    public function retryUntil(): \DateTimeInterface
    {
        // Política persistente y acotada: permitir reintentos hasta 24 horas después del momento actual
        // para dar cabida a aplazamientos legítimos de emisión en vivo sin agotar los intentos del worker.
        return now()->addHours(24);
    }

    public function __construct(
        public int $radioProgramId,
    ) {
    }

    public function handle(): void
    {
        $lockKey = "upload_radioboss_program_{$this->radioProgramId}";
        // TTL de 1800s (30 min) para cubrir holgadamente el timeout máximo (600s) y reintentos (3 intentos + backoff)
        $lockTtl = 1800;
        $lock = Cache::lock($lockKey, $lockTtl);

        if (! $lock->get()) {
            Log::warning("UploadRadiobossJob: transferencia ya en curso para el episodio {$this->radioProgramId}. Ejecución concurrente omitida.");

            return;
        }

        try {
            $this->processUpload();
        } finally {
            try {
                $lock->release();
            } catch (Throwable) {
            }
        }
    }

    private function processUpload(): void
    {
        /** @var RadioProgram|null $radioProgram */
        $radioProgram = DB::transaction(function (): ?RadioProgram {
            return RadioProgram::query()->with('masterProgram')->lockForUpdate()->find($this->radioProgramId);
        });

        if (! $radioProgram instanceof RadioProgram) {
            return;
        }

        $audit = app(PodcastPipelineAuditService::class);
        $attempt = method_exists($this, 'attempts') ? $this->attempts() : null;
        $startedAt = microtime(true);

        $sourcePath = trim((string) $radioProgram->archivo_mp3);
        if ($sourcePath === '') {
            $this->markFailure($radioProgram, 'No se pudo determinar el archivo procesado para RadioBOSS.');

            return;
        }

        $currentVersion = (int) ($radioProgram->editorial_version ?: 1);

        // 1. Aprobación editorial estricta de la versión vigente
        if ($radioProgram->editorial_status !== RadioProgram::EDITORIAL_STATUS_NOT_REQUIRED) {
            if (! $radioProgram->isEditorialApproved() || (int) $radioProgram->editorial_approved_version !== $currentVersion) {
                $this->markFailure(
                    $radioProgram,
                    "El episodio no está aprobado editorialmente para la versión actual (v{$currentVersion}, aprobada: v" . (int) $radioProgram->editorial_approved_version . ', estado: ' . (string) $radioProgram->editorial_status . ').'
                );

                return;
            }
        }

        // 2. Puntualidad evaluada según la fecha de entrega completada del conductor, no la hora del worker
        $deadline = $radioProgram->emissionDeadlineInfo();
        if ($radioProgram->submitted_by_user_id !== null && ! $radioProgram->hasActiveLateSubmissionException()) {
            $submissionTime = $radioProgram->submitted_at ?? $radioProgram->created_at;
            $deadlineTime = $deadline['deadline_datetime'] ?? null;

            if ($submissionTime !== null && $deadlineTime !== null && $submissionTime->greaterThan($deadlineTime)) {
                $this->markFailure(
                    $radioProgram,
                    'Plazo de entrega de la emisión vencido sin excepción administrativa autorizada: la entrega fue recibida fuera de plazo (' . $submissionTime->toIso8601String() . ' posterior al cierre ' . $deadlineTime->toIso8601String() . ').'
                );

                return;
            }
        }

        // 3. Solo transferir versión procesada definitiva en R2 (bloquea raw_uploads, podcast-inbox o temporales)
        $normalizedPath = ltrim(str_replace('\\', '/', $sourcePath), '/');
        $isProcessed = RadioProgram::isProcessedStoragePath($normalizedPath)
            || str_starts_with($normalizedPath, 'podcasts/episodes/');

        if (! $isProcessed) {
            $this->markFailure($radioProgram, "El archivo MP3 ('{$sourcePath}') no es una versión procesada definitiva.");

            return;
        }

        $disk = (string) ($radioProgram->archivo_mp3_disk ?: 'public');
        $absolutePath = app(\App\Services\FileUploadService::class)->localPath($sourcePath, $disk);
        $canRead = $absolutePath !== null
            && is_file($absolutePath)
            && (is_readable($absolutePath) || ($testHandle = @fopen($absolutePath, 'rb')) !== false && (fclose($testHandle) || true));

        if (! $canRead) {
            $this->markFailure($radioProgram, "No se pudo leer el MP3 procesado: {$sourcePath}");

            return;
        }

        $localSize = (int) filesize($absolutePath);
        $localSha256 = hash_file('sha256', $absolutePath);
        $ftpDisk = Storage::disk('radioboss');
        $folder = $this->resolveRemoteFolder($radioProgram);
        if ($folder === null || ! \App\Support\Radio\RadioBossPathValidator::isSafeRemoteFolder($folder)) {
            $programName = $radioProgram->masterProgram?->nombre ?? 'Sin nombre';
            $this->markFailure(
                $radioProgram,
                "No se pudo resolver una carpeta de emisión comprobada para '{$programName}' contra events.txt en RadioBOSS. Se rechaza 'Programas' como destino genérico sin correspondencia comprobada."
            );

            return;
        }

        // 3.2 Protección de emisión en vivo: aplazar rotación si coincide con la emisión al aire o próxima a iniciar
        $liveWindow = $this->isInsideLiveBroadcastProtectionWindow($radioProgram);
        if ($liveWindow !== null) {
            if ($liveWindow['status'] === 'insufficient_info') {
                $this->holdForInsufficientInfo($radioProgram, $liveWindow['reason'], $audit);

                return;
            }

            if ($liveWindow['status'] === 'inside_window') {
                $this->postponeForLiveProtection($radioProgram, $liveWindow, $audit);

                return;
            }
        }

        // 3.3 Resolución y validación estricta de nombre remoto antes de tocar FTP
        $confirmedBaseName = $radioProgram->getConfirmedRadiobossFilename();
        $baseName = ($confirmedBaseName !== null && trim($confirmedBaseName) !== '')
            ? trim($confirmedBaseName)
            : self::resolveRemoteBaseName($sourcePath);

        if (! \App\Support\Radio\RadioBossPathValidator::isSafeRemoteName($baseName)) {
            $this->markFailure(
                $radioProgram,
                "El nombre remoto para RadioBOSS ('{$baseName}') contiene caracteres incompatibles que el servidor FTP rechaza. Debe validarse y confirmarse un nombre ASCII seguro antes de iniciar la entrega."
            );

            return;
        }

        $destinationPath = trim($folder, '/\\') . '/' . $baseName;
        $destinationPath = trim(str_replace(['//', '\\'], ['/', '/'], $destinationPath), '/');

        // 3.3 Bloqueo por carpeta de emisión: evita que dos episodios destinados a la misma carpeta roten simultáneamente
        $folderLockKey = 'upload_radioboss_folder_' . sha1(strtolower($folder));
        $folderLock = Cache::lock($folderLockKey, 1800);
        if (! $folderLock->get()) {
            $this->markFailure(
                $radioProgram,
                "Carpeta de emisión ('{$folder}') bloqueada por otra transferencia o rotación activa a RadioBOSS. Entrega pospuesta para evitar inconsistencia o colisión concurrente en la misma carpeta.",
                $destinationPath
            );

            return;
        }

        try {
            // 4. Idempotencia rigurosa: exige evidencia previa de hash/versión verificados, no solo tamaño
            $previousMeta = (array) ($radioProgram->radioboss_metadata ?? []);
            $previouslyVerified = $radioProgram->radioboss_status === 'radioboss_verified'
                && (bool) $radioProgram->enviado_radioboss
                && ($previousMeta['content_sha256'] ?? '') === $localSha256
                && (int) ($previousMeta['version'] ?? 0) === $currentVersion
                && ($previousMeta['remote_path'] ?? '') === $destinationPath;

            try {
                if ($previouslyVerified && $ftpDisk->exists($destinationPath)) {
                    $idempotentCheck = $this->verifyRemoteUploadedFile(
                        $ftpDisk,
                        $destinationPath,
                        $localSize,
                        $localSha256,
                        (int) $radioProgram->id,
                        'idempotency'
                    );
                    if ($idempotentCheck['verified']) {
                        Log::info('UploadRadiobossJob: transferencia omitida por idempotencia (contenido sha256 y versión previamente verificados).', [
                            'program_id' => $radioProgram->id,
                            'remote_path' => $destinationPath,
                            'sha256' => $localSha256,
                            'version' => $currentVersion,
                            'verification_method' => $idempotentCheck['method'],
                        ]);

                        $audit->record($radioProgram, 'RADIOBOSS_IDEMPOTENT_VERIFIED', 'El archivo ya se encontraba verificado e íntegro con idéntico contenido en RadioBOSS.', [
                            'program_id' => $radioProgram->id,
                            'remote_path' => $destinationPath,
                            'sha256' => $localSha256,
                            'version' => $currentVersion,
                        ]);

                        return;
                    }
                }
            } catch (Throwable $e) {
                Log::debug('UploadRadiobossJob: verificación de idempotencia previa arrojó error; continuando.', [
                    'error' => $this->sanitizeLogMessage($e->getMessage()),
                ]);
            }

            // 5. Protección del destino final: si existe o no se puede comprobar, detener sin sobrescribir
            $archiveToRespaldo = (bool) config('services.radioboss.archive_to_respaldo', true);
            $isSameEpisodeVersionUpgrade = (bool) $radioProgram->enviado_radioboss
                && ($previousMeta['remote_path'] ?? '') === $destinationPath
                && (int) ($previousMeta['version'] ?? 0) > 0
                && (int) ($previousMeta['version'] ?? 0) < $currentVersion;

            try {
                $destinationExists = (bool) $ftpDisk->exists($destinationPath);
                if ($destinationExists) {
                    if (! $archiveToRespaldo) {
                        $filenameWithoutExt = pathinfo($baseName, PATHINFO_FILENAME);
                        $ext = pathinfo($baseName, PATHINFO_EXTENSION);
                        $versionedBaseName = "{$filenameWithoutExt}_v{$currentVersion}.{$ext}";
                        $versionedPath = trim($folder, '/\\') . '/' . $versionedBaseName;
                        $versionedPath = trim(str_replace(['//', '\\'], ['/', '/'], $versionedPath), '/');

                        if ($ftpDisk->exists($versionedPath)) {
                            $this->markFailure(
                                $radioProgram,
                                "Destino existente en RadioBOSS ('{$versionedPath}'). Entrega detenida para no sobrescribir un archivo potencialmente en uso o en emisión.",
                                $versionedPath
                            );

                            return;
                        }

                        $destinationPath = $versionedPath;
                    } elseif (! $isSameEpisodeVersionUpgrade) {
                        $this->markFailure(
                            $radioProgram,
                            "Destino existente en RadioBOSS ('{$destinationPath}'). Entrega detenida para no sobrescribir un archivo existente.",
                            $destinationPath
                        );

                        return;
                    }
                }
            } catch (\RuntimeException $re) {
                $this->markFailure($radioProgram, $this->sanitizeLogMessage($re->getMessage()), $destinationPath);

                return;
            } catch (Throwable $e) {
                $this->markFailure(
                    $radioProgram,
                    "Error comprobando existencia del destino en RadioBOSS ('{$destinationPath}'): " . $this->sanitizeLogMessage($e->getMessage()),
                    $destinationPath
                );

                return;
            }

            // 5.1 Coordinación de colisiones por destino entre episodios distintos
            $destLockKey = 'upload_radioboss_dest_' . sha1(strtolower($destinationPath));
            $destLock = Cache::lock($destLockKey, 1800);
            if (! $destLock->get()) {
                $this->markFailure(
                    $radioProgram,
                    "Destino remoto ('{$destinationPath}') bloqueado por otra transferencia activa a RadioBOSS. Entrega pospuesta para evitar colisión entre episodios distintos.",
                    $destinationPath
                );

                return;
            }

            RadioProgram::withoutEvents(function () use ($radioProgram): void {
                $radioProgram->forceFill([
                    'radioboss_started_at' => $radioProgram->radioboss_started_at ?? now(),
                    'radioboss_status' => 'radioboss_pending',
                    'delivery_status' => 'delivery_partial',
                    'status_message' => 'Subiendo a RadioBOSS.',
                ])->saveQuietly();
            });

            $audit->record($radioProgram, 'RADIOBOSS_UPLOAD_STARTED', 'Inició la subida a RadioBOSS.', [
                'program_id' => $radioProgram->id,
                'attempt' => $attempt,
                'folder' => $folder,
                'remote_path' => $destinationPath,
                'size_bytes' => $localSize,
                'sha256' => $localSha256,
            ]);

            // 6. Temporal único por transferencia fuera del árbol de emisión (directorio de preparación staging)
            // Sube el archivo con nombre temporal 100% ASCII y extensión .tmp.mp3 a una carpeta de preparación aislada
            $uniqueId = bin2hex(random_bytes(6));
            $stagingRoot = (string) config('services.radioboss.staging_root', 'staging_programas');
            $stagingFolder = trim($stagingRoot, '/\\') . '/' . trim($folder, '/\\');
            $tempRemoteName = self::buildAsciiStagingFilename($destinationPath, $uniqueId);
            $tempRemotePath = $stagingFolder . '/' . $tempRemoteName;
            $tempRemotePath = trim(str_replace(['//', '\\'], ['/', '/'], $tempRemotePath), '/');

            $ftpDisk->makeDirectory($stagingFolder);

            $stream = fopen($absolutePath, 'rb');
            if (! is_resource($stream)) {
                $this->markFailure($radioProgram, "No se pudo abrir el MP3 para RadioBOSS: {$absolutePath}");

                return;
            }

            stream_set_chunk_size($stream, 5 * 1024 * 1024);

            $uploaded = false;

            try {
                $uploaded = (bool) $ftpDisk->writeStream($tempRemotePath, $stream);

                if (! $uploaded) {
                    rewind($stream);
                    $uploaded = (bool) $ftpDisk->put($tempRemotePath, stream_get_contents($stream) ?: '');
                }
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if (! $uploaded) {
                try {
                    if ($ftpDisk->exists($tempRemotePath)) {
                        @$ftpDisk->delete($tempRemotePath);
                    }
                } catch (Throwable) {
                }
                $this->markFailure($radioProgram, 'La subida FTP a RadioBOSS devolvió un resultado inválido.');

                return;
            }

            // 7. Verificación de staging obligatoria: intenta FTP SIZE y, si falla, recurre a lectura segura y comparación de contenido/SHA-256
            $stagingVerification = $this->verifyRemoteUploadedFile(
                $ftpDisk,
                $tempRemotePath,
                $localSize,
                $localSha256,
                (int) $radioProgram->id,
                'staging'
            );

            if (! $stagingVerification['verified']) {
                try {
                    if ($ftpDisk->exists($tempRemotePath)) {
                        @$ftpDisk->delete($tempRemotePath);
                    }
                } catch (Throwable) {
                }
                throw new \RuntimeException(
                    "Verificación de tamaño fallida en RadioBOSS ({$tempRemotePath}): " . ($stagingVerification['reason'] ?? 'no se pudo verificar el archivo temporal.')
                );
            }

            // 7.1. Verificación condicional pre-promoción: comprobar si el conductor editó el episodio durante la subida
            $freshProgram = null;
            $isStillValidPrePromotion = (bool) DB::transaction(function () use ($currentVersion, $sourcePath, $disk, $destinationPath, &$freshProgram): bool {
                /** @var RadioProgram|null $locked */
                $locked = RadioProgram::query()->with('masterProgram')->lockForUpdate()->find($this->radioProgramId);
                if (! $locked instanceof RadioProgram) {
                    return false;
                }

                $freshProgram = $locked;
                $lockedVersion = (int) ($locked->editorial_version ?: 1);
                if ($lockedVersion !== $currentVersion) {
                    return false;
                }

                if ($locked->editorial_status !== RadioProgram::EDITORIAL_STATUS_NOT_REQUIRED) {
                    if (! $locked->isEditorialApproved() || (int) $locked->editorial_approved_version !== $currentVersion) {
                        return false;
                    }
                }

                if (trim((string) $locked->archivo_mp3) !== $sourcePath || (string) ($locked->archivo_mp3_disk ?: 'public') !== $disk) {
                    return false;
                }

                $freshFolder = $this->resolveRemoteFolder($locked);
                $freshConfirmed = $locked->getConfirmedRadiobossFilename();
                $freshBaseName = ($freshConfirmed !== null && trim($freshConfirmed) !== '')
                    ? trim($freshConfirmed)
                    : self::resolveRemoteBaseName($sourcePath);
                $expectedPath = trim((string) $freshFolder, '/\\') . '/' . $freshBaseName;
                $expectedPath = trim(str_replace(['//', '\\'], ['/', '/'], $expectedPath), '/');

                if ($destinationPath !== $expectedPath && ! str_starts_with(basename($destinationPath), pathinfo($freshBaseName, PATHINFO_FILENAME))) {
                    return false;
                }

                return true;
            });

            if (! $isStillValidPrePromotion) {
                if ($ftpDisk->exists($tempRemotePath)) {
                    @$ftpDisk->delete($tempRemotePath);
                }

                $this->markSuperseded(
                    $freshProgram ?? $radioProgram,
                    $currentVersion,
                    $destinationPath,
                    "La entrega para la versión v{$currentVersion} quedó obsoleta porque el episodio fue modificado durante la subida (versión actual: v" . (int) (($freshProgram ?? $radioProgram)->editorial_version ?: 1) . ').'
                );

                return;
            }

            // 7.2 Revalidar la ventana de emisión inmediatamente antes de mover cualquier archivo
            $liveWindowPreMove = $this->isInsideLiveBroadcastProtectionWindow($freshProgram ?? $radioProgram);
            if ($liveWindowPreMove !== null) {
                if ($ftpDisk->exists($tempRemotePath)) {
                    @$ftpDisk->delete($tempRemotePath);
                }

                if ($liveWindowPreMove['status'] === 'inside_window') {
                    $this->postponeForLiveProtection($freshProgram ?? $radioProgram, $liveWindowPreMove, $audit);

                    return;
                }

                if ($liveWindowPreMove['status'] === 'insufficient_info') {
                    $this->holdForInsufficientInfo($freshProgram ?? $radioProgram, $liveWindowPreMove['reason'], $audit);

                    return;
                }
            }

            // 8. Renombrado estricto sin sobrescritura destructiva y rotación de respaldo comprobada
            $backedUpFiles = [];
            $unrotatedLegacyFiles = [];
            $preserveUnrotatableLegacy = (bool) config('services.radioboss.preserve_unrotatable_legacy_files', true);

            if ($archiveToRespaldo) {
                $backupRoot = (string) config('services.radioboss.backup_root', 'respaldos_programas');
                $backupDir = trim($backupRoot, '/') . '/' . trim($folder, '/');

                $existingFilesInFolder = (array) $ftpDisk->files($folder);
                foreach ($existingFilesInFolder as $existingFile) {
                    $fname = basename($existingFile);
                    $fileExt = strtolower(pathinfo($fname, PATHINFO_EXTENSION));

                    // Excluir temporales propios y solo rotar archivos de audio
                    if (in_array($fileExt, ['mp3', 'm4a', 'aac', 'ogg', 'wav'], true)
                        && ! str_starts_with($fname, '.tmp_')
                        && ! str_ends_with($fname, '.part')) {

                        // Creación comprobada del directorio de respaldo externo fuera del árbol del evento
                        try {
                            $dirCreated = $ftpDisk->makeDirectory($backupDir);
                        } catch (Throwable $e) {
                            $this->restoreBackedUpFiles($ftpDisk, $backedUpFiles);
                            throw new \RuntimeException("Fallo al crear directorio de respaldo externo '{$backupDir}': " . $this->sanitizeLogMessage($e->getMessage()));
                        }

                        if (! $dirCreated && ! $ftpDisk->exists($backupDir)) {
                            $this->restoreBackedUpFiles($ftpDisk, $backedUpFiles);
                            throw new \RuntimeException("Fallo al crear directorio de respaldo externo '{$backupDir}': makeDirectory devolvió false.");
                        }

                        $backupBaseName = self::resolveRemoteBaseName($fname);
                        try {
                            $backupTarget = $this->resolveAvailableBackupPath($ftpDisk, $backupDir, $backupBaseName);
                        } catch (Throwable $e) {
                            $this->restoreBackedUpFiles($ftpDisk, $backedUpFiles);
                            throw $e;
                        }

                        // RadioBOSS puede rechazar mover archivos antiguos con caracteres no ASCII, acentos o '?'.
                        $hasNonAscii = ! \App\Support\Radio\RadioBossPathValidator::isSafeRemoteName($fname)
                            || (bool) preg_match('/[^\x20-\x7E]/', (string) $existingFile)
                            || str_contains((string) $existingFile, '?');
                        $safeLogExisting = $this->normalizeUtf8String((string) $existingFile);
                        $safeLogFname = $this->normalizeUtf8String($fname);

                        // Traslado comprobado del episodio anterior hacia el respaldo externo
                        try {
                            $movedToBackup = (bool) $ftpDisk->move($existingFile, $backupTarget);
                        } catch (Throwable $e) {
                            $sourceStillExists = null;
                            $backupExists = null;
                            try {
                                $currentFiles = (array) $ftpDisk->files($folder);
                                $sourceStillExists = in_array($existingFile, $currentFiles, true)
                                    || in_array(basename($existingFile), array_map('basename', $currentFiles), true)
                                    || (bool) $ftpDisk->exists($existingFile);
                                $backupExists = (bool) $ftpDisk->exists($backupTarget);
                            } catch (Throwable) {
                                // El nombre legado puede ser precisamente lo que el servidor no puede consultar.
                            }
                            $sanitizedMoveError = $this->sanitizeLogMessage($e->getMessage());
                            Log::warning('UploadRadiobossJob: fallo al trasladar episodio anterior a respaldo.', [
                                'program_id' => $radioProgram->id,
                                'existing_file' => $safeLogExisting,
                                'backup_target' => $backupTarget,
                                'has_non_ascii' => $hasNonAscii,
                                'source_still_exists' => $sourceStillExists,
                                'backup_exists' => $backupExists,
                                'exception' => $sanitizedMoveError,
                            ]);

                            if ($hasNonAscii && $preserveUnrotatableLegacy) {
                                $unrotatedLegacyFiles[] = $safeLogExisting;
                                Log::info("UploadRadiobossJob: se conserva intacto el episodio legado '{$safeLogFname}' en emisión para no bloquear la publicación del nuevo episodio verificado.", [
                                    'program_id' => $radioProgram->id,
                                    'legacy_file' => $safeLogExisting,
                                ]);
                                continue;
                            }

                            $this->restoreBackedUpFiles($ftpDisk, $backedUpFiles);

                            if ($hasNonAscii) {
                                $preservation = $sourceStillExists === true && $backupExists === false
                                    ? 'El original sigue confirmado en la carpeta de emisión.'
                                    : 'No se pudo confirmar la ubicación final del original; no reintentar hasta revisar emisión y respaldo.';
                                throw new \RuntimeException("Fallo al rotar episodio legado '{$safeLogFname}' a respaldos: {$sanitizedMoveError}. {$preservation}");
                            }

                            throw new \RuntimeException("Fallo al trasladar episodio anterior '{$safeLogFname}' al respaldo: {$sanitizedMoveError}");
                        }

                        if (! $movedToBackup || ! $ftpDisk->exists($backupTarget)) {
                            $sourceStillExists = null;
                            $backupExists = null;
                            try {
                                $currentFiles = (array) $ftpDisk->files($folder);
                                $sourceStillExists = in_array($existingFile, $currentFiles, true)
                                    || in_array(basename($existingFile), array_map('basename', $currentFiles), true)
                                    || (bool) $ftpDisk->exists($existingFile);
                                $backupExists = (bool) $ftpDisk->exists($backupTarget);
                            } catch (Throwable) {
                                // El nombre legado puede ser precisamente lo que el servidor no puede consultar.
                            }
                            Log::warning('UploadRadiobossJob: no se pudo confirmar el traslado del episodio anterior a respaldo.', [
                                'program_id' => $radioProgram->id,
                                'existing_file' => $safeLogExisting,
                                'backup_target' => $backupTarget,
                                'has_non_ascii' => $hasNonAscii,
                                'source_still_exists' => $sourceStillExists,
                                'backup_exists' => $backupExists,
                            ]);

                            if ($hasNonAscii && $preserveUnrotatableLegacy) {
                                $unrotatedLegacyFiles[] = $safeLogExisting;
                                Log::info("UploadRadiobossJob: se conserva intacto el episodio legado '{$safeLogFname}' en emisión para no bloquear la publicación del nuevo episodio verificado.", [
                                    'program_id' => $radioProgram->id,
                                    'legacy_file' => $safeLogExisting,
                                ]);
                                continue;
                            }

                            $this->restoreBackedUpFiles($ftpDisk, $backedUpFiles);

                            if ($hasNonAscii) {
                                $preservation = $sourceStillExists === true && $backupExists === false
                                    ? 'El original sigue confirmado en la carpeta de emisión.'
                                    : 'No se pudo confirmar la ubicación final del original; no reintentar hasta revisar emisión y respaldo.';
                                throw new \RuntimeException("Fallo al rotar episodio legado '{$safeLogFname}' a respaldos: el servidor FTP rechazó mover el archivo. {$preservation}");
                            }

                            throw new \RuntimeException("Fallo al trasladar episodio anterior '{$safeLogFname}' al respaldo: move devolvió false o el destino no existe.");
                        }

                        $backedUpFiles[] = ['from' => $existingFile, 'to' => $backupTarget];
                    }
                }
            }

            // 8.5. Protección estricta del destino antes de promover: la ruta final debe ser distinta de cualquier legado conservado y estar libre
            foreach ($unrotatedLegacyFiles as $legacyPath) {
                if (strtolower(trim((string) $legacyPath, '/\\')) === strtolower(trim($destinationPath, '/\\'))
                    || strtolower(basename((string) $legacyPath)) === strtolower(basename($destinationPath))) {
                    $this->restoreBackedUpFiles($ftpDisk, $backedUpFiles);
                    try {
                        if ($ftpDisk->exists($tempRemotePath)) {
                            @$ftpDisk->delete($tempRemotePath);
                        }
                    } catch (Throwable) {
                    }
                    throw new \RuntimeException("Destino existente en RadioBOSS ('{$destinationPath}'): coincide con un archivo legado conservado. Entrega detenida sin sobrescribir.");
                }
            }

            $destinationStillOccupied = false;
            try {
                $destinationStillOccupied = (bool) $ftpDisk->exists($destinationPath);
            } catch (Throwable $destCheckError) {
                $this->restoreBackedUpFiles($ftpDisk, $backedUpFiles);
                try {
                    if ($ftpDisk->exists($tempRemotePath)) {
                        @$ftpDisk->delete($tempRemotePath);
                    }
                } catch (Throwable) {
                }
                throw new \RuntimeException(
                    "No se pudo comprobar disponibilidad del destino final '{$destinationPath}' antes de promover: " . $this->sanitizeLogMessage($destCheckError->getMessage())
                );
            }

            if ($destinationStillOccupied) {
                $this->restoreBackedUpFiles($ftpDisk, $backedUpFiles);
                try {
                    if ($ftpDisk->exists($tempRemotePath)) {
                        @$ftpDisk->delete($tempRemotePath);
                    }
                } catch (Throwable) {
                }
                throw new \RuntimeException("Destino existente en RadioBOSS ('{$destinationPath}'). Promoción detenida para no sobrescribir el archivo existente.");
            }

            $ftpDisk->makeDirectory($folder);
            $moved = (bool) $ftpDisk->move($tempRemotePath, $destinationPath);
            if (! $moved) {
                // Rollback verificado de archivos trasladados a respaldo
                $this->restoreBackedUpFiles($ftpDisk, $backedUpFiles);

                try {
                    if ($ftpDisk->exists($tempRemotePath)) {
                        @$ftpDisk->delete($tempRemotePath);
                    }
                } catch (Throwable) {
                }
                throw new \RuntimeException("Fallo al renombrar archivo temporal {$tempRemotePath} a {$destinationPath} en RadioBOSS.");
            }

            // 9. Verificación post-renombrado del archivo definitivo antes de marcar éxito
            $finalExists = (bool) $ftpDisk->exists($destinationPath);
            $finalVerification = $finalExists
                ? $this->verifyRemoteUploadedFile($ftpDisk, $destinationPath, $localSize, $localSha256, (int) $radioProgram->id, 'final')
                : ['verified' => false, 'size' => 0, 'method' => 'none', 'reason' => 'archivo no encontrado en destino'];
            $finalSize = $finalVerification['size'];

            if (! $finalExists || ! $finalVerification['verified']) {
                $this->restoreBackedUpFiles($ftpDisk, $backedUpFiles);
                throw new \RuntimeException(
                    "Verificación post-renombrado fallida para {$destinationPath}: archivo no encontrado o tamaño discrepante ({$finalSize} vs {$localSize}). " . ($finalVerification['reason'] ?? '')
                );
            }

            // Limpieza de carpeta de staging si quedó vacía
            if (isset($stagingFolder) && $ftpDisk->exists($stagingFolder)) {
                $remStaging = (array) $ftpDisk->files($stagingFolder);
                if (empty($remStaging)) {
                    try {
                        @$ftpDisk->deleteDirectory($stagingFolder);
                    } catch (Throwable) {
                    }
                }
            }

            // 9.1. Registro condicional atómico: verificar por segunda vez bajo lock antes de certificar radioboss_verified
            $persistedSuccessfully = (bool) DB::transaction(function () use ($currentVersion, $sourcePath, $disk, $destinationPath, $localSha256, $localSize, $unrotatedLegacyFiles, $stagingVerification, $finalVerification, &$freshProgram): bool {
                /** @var RadioProgram|null $finalLocked */
                $finalLocked = RadioProgram::query()->lockForUpdate()->find($this->radioProgramId);
                if (! $finalLocked instanceof RadioProgram) {
                    return false;
                }

                $freshProgram = $finalLocked;
                $finalVersion = (int) ($finalLocked->editorial_version ?: 1);
                if ($finalVersion !== $currentVersion) {
                    return false;
                }

                if ($finalLocked->editorial_status !== RadioProgram::EDITORIAL_STATUS_NOT_REQUIRED) {
                    if (! $finalLocked->isEditorialApproved() || (int) $finalLocked->editorial_approved_version !== $currentVersion) {
                        return false;
                    }
                }

                if (trim((string) $finalLocked->archivo_mp3) !== $sourcePath || (string) ($finalLocked->archivo_mp3_disk ?: 'public') !== $disk) {
                    return false;
                }

                $metaUpdates = [
                    'status' => 'radioboss_verified',
                    'remote_path' => $destinationPath,
                    'local_path' => (string) $finalLocked->archivo_mp3,
                    'content_sha256' => $localSha256,
                    'version' => $currentVersion,
                    'size' => $localSize,
                    'staging_verification_method' => $stagingVerification['method'] ?? 'ftp_size',
                    'final_verification_method' => $finalVerification['method'] ?? 'ftp_size',
                    'verified_at' => now()->toIso8601String(),
                ];

                if (! empty($unrotatedLegacyFiles)) {
                    $metaUpdates['unrotated_legacy_files'] = $unrotatedLegacyFiles;
                    $metaUpdates['coexistence_notice'] = 'Episodio nuevo verificado en destino. Se conservó intacto el episodio anterior legado por restricción de nombres del servidor FTP.';
                }

                $statusMsg = empty($unrotatedLegacyFiles)
                    ? 'RadioBOSS verificado. Archive.org pendiente.'
                    : 'RadioBOSS verificado (coexistiendo con episodio legado). Archive.org pendiente.';

                $finalLocked->forceFill([
                    'enviado_radioboss' => true,
                    'radioboss_status' => 'radioboss_verified',
                    'radioboss_verified_at' => now(),
                    'radioboss_finished_at' => now(),
                    'radioboss_last_error' => null,
                    'radioboss_metadata' => array_merge((array) ($finalLocked->radioboss_metadata ?? []), $metaUpdates),
                    'status_message' => RadioProgram::sanitizeStatusMessage($statusMsg),
                ])->saveQuietly();

                return true;
            });

            if (! $persistedSuccessfully) {
                $this->restoreBackedUpFiles($ftpDisk, $backedUpFiles);

                if ($ftpDisk->exists($destinationPath)) {
                    @$ftpDisk->delete($destinationPath);
                }

                $this->markSuperseded(
                    $freshProgram ?? $radioProgram,
                    $currentVersion,
                    $destinationPath,
                    "La entrega para la versión v{$currentVersion} fue invalidada antes de certificar la verificación (versión actual: v" . (int) (($freshProgram ?? $radioProgram)->editorial_version ?: 1) . ').'
                );

                return;
            }

            $completedAuditContext = [
                'program_id' => $radioProgram->id,
                'attempt' => $attempt,
                'folder' => $folder,
                'remote_path' => $destinationPath,
                'size_bytes' => $finalSize,
                'sha256' => $localSha256,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ];
            if (! empty($unrotatedLegacyFiles)) {
                $completedAuditContext['unrotated_legacy_files'] = $unrotatedLegacyFiles;
            }

            $audit->record($radioProgram, 'RADIOBOSS_UPLOAD_COMPLETED', 'La subida a RadioBOSS finalizó correctamente y fue verificada.', $completedAuditContext);

            $audit->record($radioProgram, 'RADIOBOSS_VERIFIED', 'RadioBOSS quedó verificado con destino sin colisión.', [
                'program_id' => $radioProgram->id,
                'attempt' => $attempt,
            ]);

            PodcastRadiobossUploaded::dispatch($radioProgram->id);

            $archiveStatus = (string) ($radioProgram->archive_org_status ?? '');
            if ($archiveStatus === 'archive_verified' || $archiveStatus === 'archive_skipped') {
                PodcastDeliveryVerified::dispatch($radioProgram->id);
            }
        } catch (Throwable $exception) {
            if (isset($tempRemotePath) && isset($ftpDisk)) {
                try {
                    if ($ftpDisk->exists($tempRemotePath)) {
                        $ftpDisk->delete($tempRemotePath);
                    }
                } catch (Throwable) {
                    // Silencio intencional en borrado de temporal ante fallo
                }
            }

            $sanitizedExceptionMessage = $this->sanitizeLogMessage($exception->getMessage());
            $currentFailures = (int) ($radioProgram->radioboss_metadata['real_failure_count'] ?? 0) + 1;

            if ($this->job && $currentFailures < $this->tries) {
                RadioProgram::withoutEvents(function () use ($radioProgram, $currentFailures, $sanitizedExceptionMessage): void {
                    $radioProgram->forceFill([
                        'radioboss_metadata' => array_merge((array) ($radioProgram->radioboss_metadata ?? []), [
                            'real_failure_count' => $currentFailures,
                            'last_failure_reason' => $sanitizedExceptionMessage,
                        ]),
                    ])->saveQuietly();
                });

                $delay = $this->backoff[$currentFailures - 1] ?? 10;
                $this->release($delay);

                return;
            }

            $this->markFailure($radioProgram, $sanitizedExceptionMessage, $destinationPath ?? null);
            $audit->record($radioProgram, 'ERROR', 'Falló la subida a RadioBOSS.', [
                'program_id' => $radioProgram->id,
                'attempt' => $attempt,
                'exception' => $sanitizedExceptionMessage,
                'remote_path' => $destinationPath ?? null,
                'stage' => 'radioboss',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            PodcastUploadFailed::dispatch($radioProgram->id, 'radioboss', $sanitizedExceptionMessage, [
                'remote_path' => $destinationPath ?? null,
                'attempt' => $attempt,
            ]);

            return;
        } finally {
            if (isset($destLock)) {
                try {
                    $destLock->release();
                } catch (Throwable) {
                }
            }
            if (isset($folderLock)) {
                try {
                    $folderLock->release();
                } catch (Throwable) {
                }
            }
            if (isset($absolutePath) && is_string($absolutePath)) {
                $isTemporaryPath = str_starts_with($absolutePath, storage_path('app/tmp/backblaze'))
                    || str_starts_with($absolutePath, storage_path('app/tmp/r2'))
                    || str_starts_with($absolutePath, storage_path('app/tmp/podcast-processing'));

                if ($isTemporaryPath && is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
            }
        }
    }

    public const EVENT_FOLDER_MAPPING = [
        'TARDES LITERARIAS' => '01.- LUNES/14.- TARDES LITERARIAS',
        'ABRIENDO CABEZAS' => '01.- LUNES/17.- ABRIENDO CABEZAS',
        'ELI ROCK' => '01.- LUNES/21.- ELI ROCK',
        'LA VIEJA GUARDIA' => '01.- LUNES/22.- LA VIEJA GUARDIA',
        'ROCK AL PALO' => '02.- MARTES/15.- ROCK AL PALO',
        'METAL ADICTO' => '02.- MARTES/17.- METAL ADICTO',
        'ZONA DEL METAL' => '02.- MARTES/22.- ZONA DEL METAL',
        'FANATICOS DEL METAL' => '03.- MIERCOLES/16.- FANATICOS DEL METAL',
        'FANÁTICOS DEL METAL' => '03.- MIERCOLES/16.- FANATICOS DEL METAL',
        'METAL MANIACS' => '03.- MIERCOLES/19.- METAL MANIACS',
        'ESTACION ROCK' => '03.- MIERCOLES/22.- ESTACION ROCK',
        'ESTACIÓN ROCK' => '03.- MIERCOLES/22.- ESTACION ROCK',
        'POLUCION' => '04.- JUEVES/18.- POLUCION',
        'POLUCIÓN' => '04.- JUEVES/18.- POLUCION',
        'ROCK MADE IN AMAZON' => '04.- JUEVES/21.- ROCK MADE IN AMAZON',
        'ADN SONORO DEL UNDERGROUND' => '04.- JUEVES/23.- ADN SONORO DEL UNDERGROUND',
        'REINAS DEL ROCK Y METAL' => '05.- VIERNES/14.- REINAS DEL ROCK Y METAL',
        'LOS ARCHIVOS SECRETOS DEL ROCK AND ROLL' => '05.- VIERNES/22.- LOS ARCHIVOS SECRETOS DEL ROCK AND ROLL',
        'ALTO VOLTAJE' => '06.- SABADO/19.- ALTO VOLTAJE',
        'LA ESENCIA' => '06.- SABADO/22.- LA ESENCIA',
        'CODIGO ZONA MISTERIO' => '07.- DOMINGO/10.- CODIGO ZONA MISTERIO',
        'CÓDIGO ZONA MISTERIO' => '07.- DOMINGO/10.- CODIGO ZONA MISTERIO',
        'TROVADOR URBANO' => '07.- DOMINGO/14.- TROVADOR URBANO',
        'FRIGIDEIRA ACUSTICA' => '07.- DOMINGO/20.- FRIGIDEIRA ACUSTICA',
        'FRIGIDEIRA ACÚSTICA' => '07.- DOMINGO/20.- FRIGIDEIRA ACUSTICA',
    ];

    public function resolveRemoteFolder(RadioProgram $radioProgram): ?string
    {
        $explicitRuta = trim((string) ($radioProgram->ruta_ftp_radioboss ?: $radioProgram->masterProgram?->ruta_ftp ?: ''));
        $explicitRuta = preg_replace('/\*\d+$/', '', $explicitRuta);
        $explicitRuta = str_replace('\\', '/', $explicitRuta);
        $explicitRuta = str_replace('..', '', $explicitRuta);
        $explicitRuta = trim($explicitRuta, '/');

        // Si se especificó una ruta concreta que no es el comodín genérico 'Programas'
        if ($explicitRuta !== '' && strtolower($explicitRuta) !== 'programas') {
            return $explicitRuta;
        }

        // Buscar correspondencia contra events.txt usando el nombre del programa maestro
        $masterName = trim((string) ($radioProgram->masterProgram?->nombre ?? ''));
        $normalizedName = Str::upper($masterName);
        if (isset(self::EVENT_FOLDER_MAPPING[$normalizedName])) {
            return self::EVENT_FOLDER_MAPPING[$normalizedName];
        }

        // En pruebas automatizadas que fijen explícitamente ruta_ftp en el fixture de test
        if (app()->environment('testing') && $explicitRuta !== '') {
            return $explicitRuta;
        }

        // No hay correspondencia comprobada ni ruta configurada: rechazar 'Programas'
        return null;
    }

    public function isInsideLiveBroadcastProtectionWindow(RadioProgram $radioProgram): ?array
    {
        $master = $radioProgram->masterProgram;
        if (! $master || empty($master->dia_transmision) || empty($master->hora_transmision)) {
            return [
                'status' => 'insufficient_info',
                'reason' => 'Información de programación incompleta (día u hora de transmisión no configurados).',
            ];
        }

        $durationMinutes = $master->duracion_minutos;
        if ($durationMinutes === null || (int) $durationMinutes <= 0) {
            return [
                'status' => 'insufficient_info',
                'reason' => 'Duración del programa no confirmada o inválida. No se asume duración por defecto.',
            ];
        }
        $durationMinutes = (int) $durationMinutes;

        $timezone = (string) ($master->timezone ?: config('app.timezone', 'America/Caracas'));
        try {
            $now = now($timezone);
        } catch (Throwable) {
            $timezone = 'America/Caracas';
            $now = now($timezone);
        }

        $dayMap = [
            'LUNES' => Carbon::MONDAY,
            'MARTES' => Carbon::TUESDAY,
            'MIERCOLES' => Carbon::WEDNESDAY,
            'MIÉRCOLES' => Carbon::WEDNESDAY,
            'JUEVES' => Carbon::THURSDAY,
            'VIERNES' => Carbon::FRIDAY,
            'SABADO' => Carbon::SATURDAY,
            'SÁBADO' => Carbon::SATURDAY,
            'DOMINGO' => Carbon::SUNDAY,
        ];

        $targetDayName = Str::upper(trim((string) $master->dia_transmision));
        $targetDayOfWeek = $dayMap[$targetDayName] ?? null;

        if ($targetDayOfWeek === null) {
            return [
                'status' => 'insufficient_info',
                'reason' => "Día de transmisión '{$master->dia_transmision}' no reconocido.",
            ];
        }

        $timeParts = explode(':', (string) $master->hora_transmision);
        $hour = (int) ($timeParts[0] ?? 0);
        $minute = (int) ($timeParts[1] ?? 0);
        $second = (int) ($timeParts[2] ?? 0);

        // Evaluar 3 fechas de referencia en la zona horaria de la emisora:
        // Ayer (emisiones que cruzan medianoche hacia hoy)
        // Hoy (emisiones programadas para hoy)
        // Mañana (emisiones de mañana cuya ventana de pre-protección de 15 min inicia antes de medianoche hoy)
        $candidateDates = [
            $now->copy()->subDay(),
            $now->copy(),
            $now->copy()->addDay(),
        ];

        foreach ($candidateDates as $candidateDate) {
            if ($candidateDate->dayOfWeek === $targetDayOfWeek) {
                $showStart = $candidateDate->copy()->setTime($hour, $minute, $second);
                $showEnd = $showStart->copy()->addMinutes($durationMinutes);

                // Ventana de protección de emisión en vivo: 15 minutos antes hasta 15 minutos después de culminar
                $windowStart = $showStart->copy()->subMinutes(15);
                $windowEnd = $showEnd->copy()->addMinutes(15);

                if ($now->between($windowStart, $windowEnd)) {
                    $retryAt = $windowEnd->copy()->addMinute();
                    // Calcular segundos desde ahora hasta windowEnd con signo positivo más el minuto de margen:
                    $remainingSeconds = max(60, (int) $now->diffInSeconds($windowEnd) + 60);

                    return [
                        'status' => 'inside_window',
                        'window_start' => $windowStart->toIso8601String(),
                        'window_end' => $windowEnd->toIso8601String(),
                        'retry_at' => $retryAt->toIso8601String(),
                        'show_start' => $showStart->toIso8601String(),
                        'show_end' => $showEnd->toIso8601String(),
                        'current_time' => $now->toIso8601String(),
                        'timezone' => $timezone,
                        'remaining_seconds' => $remainingSeconds,
                    ];
                }
            }
        }

        return null;
    }

    private function postponeForLiveProtection(RadioProgram $radioProgram, array $liveWindow, PodcastPipelineAuditService $audit): void
    {
        $delaySeconds = (int) ($liveWindow['remaining_seconds'] ?? 300);
        $this->postponedSeconds = $delaySeconds;
        $retryAt = $liveWindow['retry_at'] ?? (isset($liveWindow['window_end']) ? Carbon::parse($liveWindow['window_end'])->addMinute()->toIso8601String() : now()->addSeconds($delaySeconds)->toIso8601String());

        $currentPostponements = (int) ($radioProgram->radioboss_metadata['postponement_count'] ?? 0) + 1;

        if ($currentPostponements > self::MAX_POSTPONEMENTS) {
            $msg = 'Se superó la política máxima acotada de aplazamientos (' . self::MAX_POSTPONEMENTS . ') para el programa.';
            $this->markFailure($radioProgram, $msg);
            $audit->record($radioProgram, 'ERROR', $msg, [
                'program_id' => $radioProgram->id,
                'postponement_count' => $currentPostponements,
            ]);

            return;
        }

        $msg = "Emisión en vivo protegida en curso ([{$liveWindow['window_start']} - {$liveWindow['window_end']}] {$liveWindow['timezone']}). Entrega aplazada {$delaySeconds}s para reintento automático tras el cierre de la ventana (reintento programado para: {$retryAt}).";

        Log::info('UploadRadiobossJob: entrega aplazada por emisión protegida.', [
            'program_id' => $radioProgram->id,
            'protection_window' => $liveWindow,
            'delay_seconds' => $delaySeconds,
            'retry_at' => $retryAt,
            'postponement_count' => $currentPostponements,
        ]);

        RadioProgram::withoutEvents(function () use ($radioProgram, $liveWindow, $delaySeconds, $retryAt, $currentPostponements, $msg): void {
            $radioProgram->forceFill([
                'radioboss_status' => 'radioboss_pending',
                'delivery_status' => 'delivery_partial',
                'radioboss_last_error' => null,
                'status_message' => RadioProgram::sanitizeStatusMessage($msg),
                'radioboss_metadata' => array_merge((array) ($radioProgram->radioboss_metadata ?? []), [
                    'status' => 'radioboss_postponed',
                    'postponed_until' => $retryAt,
                    'protection_window' => $liveWindow,
                    'postponed_at' => now()->toIso8601String(),
                    'retry_delay_seconds' => $delaySeconds,
                    'postponement_count' => $currentPostponements,
                ]),
            ])->saveQuietly();
        });

        $audit->record($radioProgram, 'RADIOBOSS_DELIVERY_POSTPONED', $msg, [
            'program_id' => $radioProgram->id,
            'protection_window' => $liveWindow,
            'delay_seconds' => $delaySeconds,
            'retry_at' => $retryAt,
            'postponement_count' => $currentPostponements,
        ]);

        if ($this->job) {
            $this->release($delaySeconds);
        }
    }

    private function holdForInsufficientInfo(RadioProgram $radioProgram, string $reason, PodcastPipelineAuditService $audit): void
    {
        Log::warning('UploadRadiobossJob: entrega detenida por información de emisión insuficiente.', [
            'program_id' => $radioProgram->id,
            'reason' => $reason,
        ]);

        $msg = "Información de programación o duración insuficiente: {$reason} La entrega se mantiene pendiente conservando el episodio vigente.";

        RadioProgram::withoutEvents(function () use ($radioProgram, $msg, $reason): void {
            $radioProgram->forceFill([
                'radioboss_status' => 'radioboss_pending',
                'delivery_status' => 'delivery_partial',
                'radioboss_last_error' => $reason,
                'status_message' => RadioProgram::sanitizeStatusMessage($msg),
                'radioboss_metadata' => array_merge((array) ($radioProgram->radioboss_metadata ?? []), [
                    'status' => 'radioboss_pending_info',
                    'pending_reason' => $reason,
                    'updated_at' => now()->toIso8601String(),
                ]),
            ])->saveQuietly();
        });

        $audit->record($radioProgram, 'RADIOBOSS_HELD_INSUFFICIENT_INFO', $msg, [
            'program_id' => $radioProgram->id,
            'reason' => $reason,
        ]);
    }

    private function restoreBackedUpFiles(mixed $ftpDisk, array $backedUpFiles): void
    {
        $failures = [];

        foreach ($backedUpFiles as $item) {
            $backupTarget = $item['to'];
            $originalPath = $item['from'];

            try {
                $restored = (bool) $ftpDisk->move($backupTarget, $originalPath);
                $exists = $ftpDisk->exists($originalPath);
                if (! $restored || ! $exists) {
                    $failures[] = "No se pudo restaurar '{$originalPath}' desde '{$backupTarget}' (move devolvió false o destino no encontrado).";
                }
            } catch (Throwable $e) {
                $failures[] = "Excepción restaurando '{$originalPath}' desde '{$backupTarget}': " . $e->getMessage();
            }
        }

        if (! empty($failures)) {
            $msg = 'Inconsistencia crítica en RadioBOSS durante la restauración de respaldos: ' . implode(' | ', $failures);
            Log::critical('UploadRadiobossJob: ' . $msg);
            throw new \RuntimeException($msg);
        }
    }

    private function resolveAvailableBackupPath(mixed $ftpDisk, string $backupDir, string $baseName): string
    {
        $candidate = trim($backupDir, '/') . '/' . $baseName;
        if (! $ftpDisk->exists($candidate)) {
            return $candidate;
        }

        $extension = pathinfo($baseName, PATHINFO_EXTENSION);
        $stem = pathinfo($baseName, PATHINFO_FILENAME);
        $timestamp = now()->format('Ymd_His');

        for ($suffix = 1; $suffix <= 100; $suffix++) {
            $versionedName = $stem . '_rotacion_' . $timestamp . '_' . $suffix;
            if ($extension !== '') {
                $versionedName .= '.' . $extension;
            }

            $candidate = trim($backupDir, '/') . '/' . $versionedName;
            if (! $ftpDisk->exists($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException("No se encontró una ruta libre en respaldos para '{$baseName}'; se detuvo la rotación para evitar sobrescribir archivos.");
    }

    private function markFailure(RadioProgram $radioProgram, string $message, ?string $remotePath = null): void
    {
        $sanitizedMessage = $this->sanitizeLogMessage($message);
        $briefStatusMessage = RadioProgram::sanitizeStatusMessage(
            $this->conciseFailureStatusMessage($sanitizedMessage),
            180
        ) ?? 'RadioBOSS no pudo completarse.';

        Log::warning('UploadRadiobossJob: fallo la subida a RadioBOSS.', [
            'program_id' => $radioProgram->id,
            'remote_path' => $remotePath,
            'message' => $sanitizedMessage,
        ]);

        RadioProgram::withoutEvents(function () use ($radioProgram, $sanitizedMessage, $briefStatusMessage, $remotePath): void {
            $radioProgram->forceFill([
                'enviado_radioboss' => false,
                'radioboss_status' => 'radioboss_error',
                'radioboss_verified_at' => null,
                'radioboss_finished_at' => now(),
                'radioboss_last_error' => $sanitizedMessage,
                'radioboss_metadata' => array_merge((array) ($radioProgram->radioboss_metadata ?? []), [
                    'status' => 'radioboss_error',
                    'remote_path' => $remotePath,
                    'local_path' => (string) $radioProgram->archivo_mp3,
                    'last_error' => $sanitizedMessage,
                ]),
                'delivery_status' => 'delivery_partial',
                'delivery_last_error' => $sanitizedMessage,
                'status_message' => $briefStatusMessage,
            ])->saveQuietly();
        });
    }

    private function markSuperseded(RadioProgram $radioProgram, int $invalidatedVersion, string $destinationPath, string $reason): void
    {
        Log::warning('UploadRadiobossJob: entrega obsoleta por cambio concurrente del episodio.', [
            'program_id' => $radioProgram->id,
            'invalidated_version' => $invalidatedVersion,
            'current_version' => (int) ($radioProgram->editorial_version ?: 1),
            'remote_path' => $destinationPath,
            'reason' => $reason,
        ]);

        RadioProgram::withoutEvents(function () use ($radioProgram, $invalidatedVersion, $destinationPath, $reason): void {
            $radioProgram->forceFill([
                'enviado_radioboss' => false,
                'radioboss_status' => 'radioboss_superseded',
                'radioboss_verified_at' => null,
                'radioboss_finished_at' => now(),
                'radioboss_last_error' => $reason,
                'radioboss_metadata' => array_merge((array) ($radioProgram->radioboss_metadata ?? []), [
                    'status' => 'radioboss_superseded',
                    'remote_path' => $destinationPath,
                    'superseded_version' => $invalidatedVersion,
                    'superseded_at' => now()->toIso8601String(),
                    'last_error' => $reason,
                ]),
                'delivery_status' => 'delivery_partial',
                'delivery_last_error' => $reason,
                'status_message' => 'Entrega anterior obsoleta por edición concurrente del episodio. Requiere nueva transferencia.',
            ])->saveQuietly();
        });

        app(PodcastPipelineAuditService::class)->record($radioProgram, 'RADIOBOSS_DELIVERY_SUPERSEDED', $reason, [
            'invalidated_version' => $invalidatedVersion,
            'current_version' => (int) ($radioProgram->editorial_version ?: 1),
            'remote_path' => $destinationPath,
        ]);
    }

    /**
     * Resuelve el nombre base remoto transliterado a ASCII para RadioBOSS.
     * Convierte diacríticos y acentos (ej. CÓDIGO -> CODIGO, FANÁTICOS -> FANATICOS)
     * manteniendo intactos el archivo fuente en R2, metadatos y Archive.org,
     * para garantizar compatibilidad estricta con el demonio FTP y el comando SIZE.
     */
    public static function resolveRemoteBaseName(string $sourcePath): string
    {
        return \App\Support\Radio\RadioBossPathValidator::generateSafeProposal($sourcePath);
    }

    /**
     * Construye un nombre temporal de staging estrictamente ASCII para evitar rechazos
     * de FTP SIZE/STAT/RNFR en demonios FTP sensibles a codificación.
     */
    public static function buildAsciiStagingFilename(string $destinationPath, string $uniqueId): string
    {
        $safeBase = \App\Support\Radio\RadioBossPathValidator::generateSafeProposal(basename($destinationPath));
        $stem = (string) pathinfo($safeBase, PATHINFO_FILENAME);
        $asciiStem = preg_replace('/[^\x20-\x7E]/', '', Str::ascii($stem)) ?? '';
        $asciiStem = preg_replace('/[^A-Za-z0-9._ -]/', '', $asciiStem) ?? '';
        $asciiStem = trim((string) preg_replace('/\s+/', '_', $asciiStem), '._- ');

        if ($asciiStem === '') {
            $asciiStem = 'episode';
        }

        $asciiUniqueId = preg_replace('/[^A-Za-z0-9_-]/', '', $uniqueId) ?: 'tmp';

        return ".tmp_{$asciiUniqueId}_{$asciiStem}.tmp.mp3";
    }

    /**
     * Verifica la integridad del archivo subido en FTP.
     * Intenta primero obtener el tamaño con FTP SIZE; si el servidor FTP lanza excepción
     * (p. ej. "Unable to retrieve the file_size") o devuelve un valor no válido (< 0),
     * ejecuta una alternativa segura de lectura por stream/contenido comparando tamaño exacto
     * y hash SHA-256 contra el archivo local.
     *
     * @return array{verified: bool, size: int, method: string, sha256: ?string, reason: ?string}
     */
    private function verifyRemoteUploadedFile(
        mixed $ftpDisk,
        string $remotePath,
        int $expectedSize,
        string $expectedSha256,
        int $programId,
        string $stage
    ): array {
        $sizeError = null;

        try {
            $rawSize = $ftpDisk->size($remotePath);
            if (is_int($rawSize) && $rawSize >= 0) {
                if ($rawSize > 0 && ($expectedSize <= 0 || $rawSize === $expectedSize)) {
                    return [
                        'verified' => true,
                        'size' => $rawSize,
                        'method' => 'ftp_size',
                        'sha256' => null,
                        'reason' => null,
                    ];
                }

                return [
                    'verified' => false,
                    'size' => $rawSize,
                    'method' => 'ftp_size',
                    'sha256' => null,
                    'reason' => "{$rawSize} bytes transferidos (esperados: {$expectedSize} bytes)",
                ];
            }

            $sizeError = 'FTP SIZE devolvió un valor no válido (' . var_export($rawSize, true) . ').';
        } catch (Throwable $e) {
            $sizeError = $this->sanitizeLogMessage($e->getMessage());
        }

        Log::warning("UploadRadiobossJob: FTP SIZE no disponible en etapa '{$stage}', iniciando verificación alternativa por lectura de contenido.", [
            'program_id' => $programId,
            'stage' => $stage,
            'remote_path' => $remotePath,
            'expected_size' => $expectedSize,
            'ftp_size_error' => $sizeError,
        ]);

        try {
            $stream = $ftpDisk->readStream($remotePath);
            if (is_resource($stream)) {
                $hashCtx = hash_init('sha256');
                $readBytes = 0;

                try {
                    while (! feof($stream)) {
                        $chunk = fread($stream, 65536);
                        if ($chunk === false) {
                            throw new \RuntimeException('Fallo de lectura en mitad del stream FTP.');
                        }
                        $chunkLen = strlen($chunk);
                        if ($chunkLen > 0) {
                            $readBytes += $chunkLen;
                            hash_update($hashCtx, $chunk);
                        }
                    }
                } finally {
                    @fclose($stream);
                }

                $remoteSha256 = hash_final($hashCtx);
                $sizeMatches = $readBytes > 0 && ($expectedSize <= 0 || $readBytes === $expectedSize);
                $hashMatches = $expectedSha256 === '' || hash_equals($expectedSha256, $remoteSha256);

                if ($sizeMatches && $hashMatches) {
                    Log::info("UploadRadiobossJob: verificación alternativa por stream exitosa en etapa '{$stage}'.", [
                        'program_id' => $programId,
                        'stage' => $stage,
                        'remote_path' => $remotePath,
                        'verified_size' => $readBytes,
                        'sha256' => $remoteSha256,
                    ]);

                    return [
                        'verified' => true,
                        'size' => $readBytes,
                        'method' => 'stream_sha256',
                        'sha256' => $remoteSha256,
                        'reason' => null,
                    ];
                }

                return [
                    'verified' => false,
                    'size' => $readBytes,
                    'method' => 'stream_sha256',
                    'sha256' => $remoteSha256,
                    'reason' => "Fallo en verificación alternativa por stream tras error de FTP SIZE ({$sizeError}): leídos {$readBytes} bytes (esperados: {$expectedSize}), sha256_match=" . ($hashMatches ? 'true' : 'false'),
                ];
            }

            $content = $ftpDisk->get($remotePath);
            if (is_string($content)) {
                $readBytes = strlen($content);
                $remoteSha256 = hash('sha256', $content);
                $sizeMatches = $readBytes > 0 && ($expectedSize <= 0 || $readBytes === $expectedSize);
                $hashMatches = $expectedSha256 === '' || hash_equals($expectedSha256, $remoteSha256);

                if ($sizeMatches && $hashMatches) {
                    Log::info("UploadRadiobossJob: verificación alternativa por contenido exitosa en etapa '{$stage}'.", [
                        'program_id' => $programId,
                        'stage' => $stage,
                        'remote_path' => $remotePath,
                        'verified_size' => $readBytes,
                        'sha256' => $remoteSha256,
                    ]);

                    return [
                        'verified' => true,
                        'size' => $readBytes,
                        'method' => 'content_sha256',
                        'sha256' => $remoteSha256,
                        'reason' => null,
                    ];
                }

                return [
                    'verified' => false,
                    'size' => $readBytes,
                    'method' => 'content_sha256',
                    'sha256' => $remoteSha256,
                    'reason' => "Fallo en verificación alternativa por contenido tras error de FTP SIZE ({$sizeError}): leídos {$readBytes} bytes (esperados: {$expectedSize}), sha256_match=" . ($hashMatches ? 'true' : 'false'),
                ];
            }
        } catch (Throwable $readException) {
            $sanitizedReadError = $this->sanitizeLogMessage($readException->getMessage());

            return [
                'verified' => false,
                'size' => -1,
                'method' => 'fallback_failed',
                'sha256' => null,
                'reason' => "No fue posible verificar el archivo ni por FTP SIZE ({$sizeError}) ni por lectura alternativa ({$sanitizedReadError}).",
            ];
        }

        return [
            'verified' => false,
            'size' => -1,
            'method' => 'fallback_unavailable',
            'sha256' => null,
            'reason' => "No fue posible verificar el archivo tras fallo de FTP SIZE ({$sizeError}): lectura remota vacía o no soportada.",
        ];
    }

    private function conciseFailureStatusMessage(string $sanitizedMessage): string
    {
        $clean = trim((string) preg_replace('/\s+/', ' ', $sanitizedMessage));
        if ($clean === '') {
            return 'RadioBOSS no pudo completarse.';
        }

        return 'RadioBOSS no pudo completarse: ' . $clean;
    }

    private function sanitizeLogMessage(string $message): string
    {
        $normalized = $this->normalizeUtf8String($message);

        // Redactar esquemas ftp://user:pass@host o ftps://user:pass@host
        $sanitized = preg_replace('#(ftps?://)([^/\s:@]+):([^@\s]+)@#i', '$1[REDACTED]:[REDACTED]@', $normalized) ?? $normalized;

        // Redactar parámetros explícitos de password/pass/secret
        $sanitized = preg_replace('/((?:password|passwd|pwd|pass|secret|token)\s*[:=]\s*)([^\s,;]+)/i', '$1[REDACTED]', $sanitized) ?? $sanitized;

        // Redactar credenciales configuradas en el disco radioboss si aparecieran literal en una traza
        $ftpUsername = (string) config('filesystems.disks.radioboss.username', '');
        $ftpPassword = (string) config('filesystems.disks.radioboss.password', '');

        if ($ftpPassword !== '') {
            $sanitized = str_replace($ftpPassword, '[REDACTED]', $sanitized);
        }
        if ($ftpUsername !== '' && strlen($ftpUsername) >= 4) {
            $sanitized = str_replace($ftpUsername, '[REDACTED_USER]', $sanitized);
        }

        return $sanitized;
    }

    private function normalizeUtf8String(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');

        return is_string($converted) && $converted !== '' ? $converted : utf8_encode($value);
    }
}
