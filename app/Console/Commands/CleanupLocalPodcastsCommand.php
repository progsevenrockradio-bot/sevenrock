<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessMp3Job;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Services\FileUploadService;
use App\Services\PodcastPipelineAuditService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Database\ModelIdentifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('podcast:cleanup-local {--days=7 : Plazo mínimo en días desde el procesamiento verificado} {--force : Ignorar el plazo de días (mantiene obligatorias las salvaguardas de cola, referencias, bloqueo y SHA-256 en R2)} {--dry-run : Modo de revisión previa: evalúa todas las salvaguardas y verifica SHA-256 sin eliminar archivos ni modificar datos}')]
#[Description('Evalúa y limpia copias locales residuales de podcasts procesados tras verificar cola completa, ausencia de referencias/carreras e identidad SHA-256 en Cloudflare R2')]
final class CleanupLocalPodcastsCommand extends Command
{
    public static function activeAccessCacheKey(int $programId): string
    {
        return 'podcast_active_access:program:' . $programId;
    }

    public static function cleanupLockKey(int $programId): string
    {
        return 'podcast_cleanup_lock:program:' . $programId;
    }

    public function handle(PodcastPipelineAuditService $auditService, FileUploadService $fileUploadService): int
    {
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $retentionDays = max(0, (int) ($this->option('days') ?? ProcessMp3Job::LOCAL_RETENTION_DAYS));

        try {
            $programs = RadioProgram::query()
                ->whereNotNull('processing_finished_at')
                ->whereIn('radioboss_status', ['radioboss_verified', 'skipped'])
                ->whereIn('archive_org_status', ['archive_verified', 'archive_skipped'])
                ->get();
        } catch (Throwable $exception) {
            $this->error('No se pudo consultar radio_programs: ' . $exception->getMessage());
            Log::warning('CleanupLocalPodcastsCommand: fallo consultando radio_programs.', [
                'exception' => $exception->getMessage(),
            ]);

            return self::FAILURE;
        }

        if ($programs->isEmpty()) {
            $this->info('No hay episodios elegibles para limpieza de copias locales.');

            return self::SUCCESS;
        }

        // Filtrar únicamente los episodios que realmente tienen un archivo local presente en public
        $candidates = [];
        foreach ($programs as $program) {
            $metadata = (array) ($program->delivery_metadata ?? []);
            $localCandidate = trim((string) ($metadata['local_copy_path'] ?? $program->archivo_mp3 ?? ''));
            $localPath = ltrim(str_replace('\\', '/', $localCandidate), '/');

            if ($localPath === '' || ! str_starts_with($localPath, 'programas_procesados/')) {
                continue;
            }

            if (! Storage::disk('public')->exists($localPath)) {
                continue;
            }

            $candidates[] = [
                'program' => $program,
                'local_path' => $localPath,
            ];
        }

        if ($candidates === []) {
            $this->info('No hay archivos locales presentes en programas_procesados para evaluar.');

            return self::SUCCESS;
        }

        // Salvaguarda 1 y 2: Comprobar completamente la cola (jobs + failed_jobs) sin límite de 500 y sin cargar toda la tabla en memoria.
        // Si la cola no puede comprobarse por completo, conservar todas las copias y registrar el motivo.
        try {
            $queueIndex = $this->scanAllQueueTablesWithoutLimit();
        } catch (Throwable $exception) {
            $reason = 'No se pudo comprobar completamente la cola de trabajos (jobs/failed_jobs): ' . $exception->getMessage();
            $this->error($reason);
            Log::warning('CleanupLocalPodcastsCommand: abortando por imposibilidad de verificar la cola.', [
                'exception' => $exception->getMessage(),
            ]);

            foreach ($candidates as $candidate) {
                $this->recordPreservedReason($auditService, $candidate['program'], 'queue_verification_failed', $reason, [
                    'local_path' => $candidate['local_path'],
                ]);
            }

            return self::FAILURE;
        }

        $cleanedCount = 0;
        $dryRunEligibleCount = 0;
        $skippedRetentionCount = 0;
        $skippedProtectedCount = 0;
        $savedBytes = 0;

        foreach ($candidates as $candidate) {
            /** @var RadioProgram $program */
            $program = $candidate['program'];
            $localPath = $candidate['local_path'];
            $metadata = (array) ($program->delivery_metadata ?? []);

            // Salvaguarda de cola (jobs en espera, en ejecución/reservados y failed_jobs reintentables)
            $queueMatchReason = $this->matchCandidateAgainstQueueIndex($program, $localPath, $metadata, $queueIndex);
            if ($queueMatchReason !== null) {
                $skippedProtectedCount++;
                $this->warn(sprintf('Conservado #%d (%s): %s', $program->id, basename($localPath), $queueMatchReason));
                $this->recordPreservedReason($auditService, $program, 'active_or_retryable_queue_job', $queueMatchReason, [
                    'local_path' => $localPath,
                ]);
                continue;
            }

            // Salvaguarda de referencias cruzadas desde otros registros de la base de datos
            try {
                $crossRefReason = $this->checkCrossReferencesFromOtherRecords($program, $localPath);
            } catch (Throwable $exception) {
                $crossRefReason = 'No se pudieron verificar las referencias cruzadas en la base de datos: ' . $exception->getMessage();
            }

            if ($crossRefReason !== null) {
                $skippedProtectedCount++;
                $this->warn(sprintf('Conservado #%d (%s): %s', $program->id, basename($localPath), $crossRefReason));
                $this->recordPreservedReason($auditService, $program, 'cross_reference_or_consumer_check_failed', $crossRefReason, [
                    'local_path' => $localPath,
                ]);
                continue;
            }

            // Salvaguarda de plazo de retención
            if (! $force && ! $this->isRetentionExpired($program, $metadata, $retentionDays)) {
                $skippedRetentionCount++;
                continue;
            }

            // Salvaguarda de configuración y asignación de R2
            $r2Path = ltrim(str_replace('\\', '/', trim((string) ($metadata['definitive_path'] ?? $program->archivo_mp3 ?? ''))), '/');
            $r2Disk = trim((string) $program->archivo_mp3_disk);

            if ($r2Disk !== 'r2' || $r2Path === '' || ! $fileUploadService->isR2Configured()) {
                $skippedProtectedCount++;
                $reason = sprintf('No tiene destino definitivo R2 configurado/asignado (disk=%s).', $r2Disk !== '' ? $r2Disk : 'public');
                $this->warn(sprintf('Protegido #%d (%s): %s', $program->id, basename($localPath), $reason));
                $this->recordPreservedReason($auditService, $program, 'r2_not_configured_or_assigned', $reason, [
                    'local_path' => $localPath,
                    'disk' => $r2Disk,
                ]);
                continue;
            }

            // Salvaguarda contra carreras: bloqueo atómico por episodio y verificación de acceso activo (descarga/reintento)
            if (Cache::has(self::activeAccessCacheKey((int) $program->id))) {
                $skippedProtectedCount++;
                $reason = 'Existe una operación reciente o en curso de descarga/reintento sobre este episodio.';
                $this->warn(sprintf('Conservado #%d (%s): %s', $program->id, basename($localPath), $reason));
                $this->recordPreservedReason($auditService, $program, 'active_download_or_retry_lease', $reason, [
                    'local_path' => $localPath,
                ]);
                continue;
            }

            $lock = Cache::lock(self::cleanupLockKey((int) $program->id), 60);
            if (! $lock->get()) {
                $skippedProtectedCount++;
                $reason = 'No se pudo adquirir el bloqueo exclusivo de limpieza para el episodio.';
                $this->warn(sprintf('Conservado #%d (%s): %s', $program->id, basename($localPath), $reason));
                $this->recordPreservedReason($auditService, $program, 'cleanup_lock_busy', $reason, [
                    'local_path' => $localPath,
                ]);
                continue;
            }

            try {
                $localAbsolutePath = Storage::disk('public')->path($localPath);
                if (! is_file($localAbsolutePath) || ! is_readable($localAbsolutePath)) {
                    $skippedProtectedCount++;
                    $reason = 'No se pudo leer el archivo local para calcular su SHA-256.';
                    $this->warn(sprintf('Protegido #%d (%s): %s', $program->id, basename($localPath), $reason));
                    $this->recordPreservedReason($auditService, $program, 'local_file_unreadable', $reason, [
                        'local_path' => $localPath,
                    ]);
                    continue;
                }

                clearstatcache(true, $localAbsolutePath);
                $localSizeBefore = (int) filesize($localAbsolutePath);
                $localMtimeBefore = (int) filemtime($localAbsolutePath);
                $localSha256 = (string) (hash_file('sha256', $localAbsolutePath) ?: '');

                if ($localSizeBefore <= 0 || $localSha256 === '') {
                    $skippedProtectedCount++;
                    $reason = 'Tamaño local inválido o fallo al calcular SHA-256 local.';
                    $this->warn(sprintf('Protegido #%d (%s): %s', $program->id, basename($localPath), $reason));
                    $this->recordPreservedReason($auditService, $program, 'invalid_local_hash', $reason, [
                        'local_path' => $localPath,
                    ]);
                    continue;
                }

                if (! Storage::disk('r2')->exists($r2Path)) {
                    $skippedProtectedCount++;
                    $reason = sprintf('El objeto definitivo no existe en R2 (%s). No se elimina la única copia válida.', $r2Path);
                    $this->warn(sprintf('Protegido #%d (%s): %s', $program->id, basename($localPath), $reason));
                    $this->recordPreservedReason($auditService, $program, 'r2_object_missing', $reason, [
                        'local_path' => $localPath,
                        'r2_path' => $r2Path,
                    ]);
                    continue;
                }

                $remoteSize = (int) Storage::disk('r2')->size($r2Path);
                if ($remoteSize !== $localSizeBefore) {
                    $skippedProtectedCount++;
                    $reason = sprintf('Tamaño en R2 (%d B) no coincide con copia local (%d B).', $remoteSize, $localSizeBefore);
                    $this->warn(sprintf('Protegido #%d (%s): %s', $program->id, basename($localPath), $reason));
                    $this->recordPreservedReason($auditService, $program, 'r2_size_mismatch', $reason, [
                        'local_path' => $localPath,
                        'r2_path' => $r2Path,
                        'local_size' => $localSizeBefore,
                        'remote_size' => $remoteSize,
                    ]);
                    continue;
                }

                // Salvaguarda 3: Verificación criptográfica SHA-256 vinculada al objeto remoto exacto en R2
                $remoteVerification = $this->computeRemoteSha256AndSize($r2Path);
                if ($remoteVerification === null) {
                    $skippedProtectedCount++;
                    $reason = sprintf('No se pudo leer el stream remoto de R2 para verificar SHA-256 (%s).', $r2Path);
                    $this->warn(sprintf('Protegido #%d (%s): %s', $program->id, basename($localPath), $reason));
                    $this->recordPreservedReason($auditService, $program, 'r2_stream_unreadable', $reason, [
                        'local_path' => $localPath,
                        'r2_path' => $r2Path,
                    ]);
                    continue;
                }

                $remoteSha256 = $remoteVerification['sha256'];
                $remoteStreamBytes = $remoteVerification['bytes'];

                if ($remoteStreamBytes !== $localSizeBefore || ! hash_equals($localSha256, $remoteSha256)) {
                    $skippedProtectedCount++;
                    $reason = sprintf(
                        'El SHA-256 del objeto remoto en R2 (%s) no coincide con el archivo local (%s).',
                        substr($remoteSha256, 0, 16) . '...',
                        substr($localSha256, 0, 16) . '...'
                    );
                    $this->warn(sprintf('Protegido #%d (%s): %s', $program->id, basename($localPath), $reason));
                    $this->recordPreservedReason($auditService, $program, 'sha256_mismatch', $reason, [
                        'local_path' => $localPath,
                        'r2_path' => $r2Path,
                        'local_sha256' => $localSha256,
                        'remote_sha256' => $remoteSha256,
                        'size_bytes' => $localSizeBefore,
                    ]);
                    continue;
                }

                $recordedR2Sha256 = trim((string) ($metadata['r2_sha256'] ?? ''));
                if ($recordedR2Sha256 !== '' && ! hash_equals($recordedR2Sha256, $remoteSha256)) {
                    $skippedProtectedCount++;
                    $reason = 'El SHA-256 actual en R2 no coincide con la evidencia r2_sha256 registrada en delivery_metadata.';
                    $this->warn(sprintf('Protegido #%d (%s): %s', $program->id, basename($localPath), $reason));
                    $this->recordPreservedReason($auditService, $program, 'metadata_sha256_mismatch', $reason, [
                        'local_path' => $localPath,
                        'r2_path' => $r2Path,
                        'recorded_r2_sha256' => $recordedR2Sha256,
                        'remote_sha256' => $remoteSha256,
                    ]);
                    continue;
                }

                // Re-verificación inmediata antes de borrar (protección frente a carreras durante la lectura del stream)
                $raceReason = $this->reverifyBeforeDelete($program, $localPath, $localAbsolutePath, $localSizeBefore, $localMtimeBefore);
                if ($raceReason !== null) {
                    $skippedProtectedCount++;
                    $this->warn(sprintf('Conservado #%d (%s): %s', $program->id, basename($localPath), $raceReason));
                    $this->recordPreservedReason($auditService, $program, 'race_condition_detected', $raceReason, [
                        'local_path' => $localPath,
                    ]);
                    continue;
                }

                if ($dryRun) {
                    $dryRunEligibleCount++;
                    $savedBytes += $localSizeBefore;
                    $this->line(sprintf(
                        '[DRY-RUN] Candidato verificado para eliminar: %s (%s MB, SHA-256: %s)',
                        basename($localPath),
                        round($localSizeBefore / 1024 / 1024, 2),
                        $remoteSha256
                    ));
                    continue;
                }

                if (! Storage::disk('public')->delete($localPath)) {
                    $this->error(sprintf('No se pudo eliminar la copia local en public: %s', $localPath));
                    continue;
                }

                $cleanedAt = now();
                $cleanedCount++;
                $savedBytes += $localSizeBefore;

                RadioProgram::withoutEvents(function () use ($program, $metadata, $cleanedAt, $localSizeBefore, $remoteSha256): void {
                    $program->forceFill([
                        'delivery_metadata' => array_merge($metadata, [
                            'r2_sha256' => $remoteSha256,
                            'local_copy_cleaned_at' => $cleanedAt->toIso8601String(),
                            'local_copy_cleaned_size_bytes' => $localSizeBefore,
                            'local_copy_verified_sha256' => $remoteSha256,
                        ]),
                    ])->saveQuietly();
                });

                $auditService->record($program, 'LOCAL_FILE_CLEANED', 'Se eliminó la copia local tras verificar cola completa, ausencia de referencias y coincidencia exacta SHA-256 en R2.', [
                    'local_path' => $localPath,
                    'local_disk' => 'public',
                    'r2_path' => $r2Path,
                    'r2_disk' => 'r2',
                    'size_bytes' => $localSizeBefore,
                    'sha256' => $remoteSha256,
                    'retention_days' => $retentionDays,
                ]);

                $this->line(sprintf('Copia local eliminada: %s (%s MB)', basename($localPath), round($localSizeBefore / 1024 / 1024, 2)));
            } catch (Throwable $e) {
                $skippedProtectedCount++;
                $reason = sprintf('Excepción durante verificación/limpieza: %s', $e->getMessage());
                $this->error(sprintf('Error al procesar limpieza local de %s: %s', $localPath, $e->getMessage()));
                $this->recordPreservedReason($auditService, $program, 'exception_during_cleanup', $reason, [
                    'local_path' => $localPath,
                ]);
            } finally {
                $lock->release();
            }
        }

        if ($dryRun) {
            $this->info(sprintf(
                '[DRY-RUN] Revisión completada sin eliminar archivos. Elegibles verificados (SHA-256 OK): %d. En retención (<%d días): %d. Conservados por salvaguarda: %d. Espacio potencial: %s MB.',
                $dryRunEligibleCount,
                $retentionDays,
                $skippedRetentionCount,
                $skippedProtectedCount,
                round($savedBytes / 1024 / 1024, 2)
            ));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Limpieza completada. Eliminados: %d. En retención (<%d días): %d. Conservados por salvaguarda: %d. Espacio liberado: %s MB.',
            $cleanedCount,
            $retentionDays,
            $skippedRetentionCount,
            $skippedProtectedCount,
            round($savedBytes / 1024 / 1024, 2)
        ));

        return self::SUCCESS;
    }

    /**
     * Recorre todas las filas de `jobs` y `failed_jobs` mediante cursor (sin límite de 500
     * y sin cargar toda la cola en memoria) extrayendo referencias de episodios y rutas
     * tanto de trabajos serializados reales de Laravel (PHP serialize / ModelIdentifier)
     * como de payloads JSON.
     *
     * @return array{
     *     program_ids: array<int, string>,
     *     paths: array<string, string>,
     *     basenames: array<string, string>
     * }
     */
    private function scanAllQueueTablesWithoutLimit(): array
    {
        if (! Schema::hasTable('jobs')) {
            throw new RuntimeException('La tabla de cola "jobs" no existe.');
        }

        if (! Schema::hasTable('failed_jobs')) {
            throw new RuntimeException('La tabla de trabajos fallidos "failed_jobs" no existe.');
        }

        $index = [
            'program_ids' => [],
            'paths' => [],
            'basenames' => [],
        ];

        foreach (DB::table('jobs')->select(['id', 'queue', 'payload', 'reserved_at'])->orderBy('id')->cursor() as $row) {
            $sourceLabel = ($row->reserved_at !== null ? 'job en ejecución #' : 'job en cola #') . $row->id;
            $this->indexSingleQueuePayload((string) ($row->payload ?? ''), $sourceLabel, $index);
        }

        foreach (DB::table('failed_jobs')->select(['id', 'queue', 'payload'])->orderBy('id')->cursor() as $row) {
            $sourceLabel = 'failed_job reintentable #' . $row->id;
            $this->indexSingleQueuePayload((string) ($row->payload ?? ''), $sourceLabel, $index);
        }

        return $index;
    }

    /**
     * @param array{
     *     program_ids: array<int, string>,
     *     paths: array<string, string>,
     *     basenames: array<string, string>
     * } $index
     */
    private function indexSingleQueuePayload(string $payload, string $sourceLabel, array &$index): void
    {
        $payload = trim($payload);
        if ($payload === '') {
            return;
        }

        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            if ($this->payloadMentionsPodcastDomain($payload)) {
                throw new RuntimeException("Payload corrupto o ilegible en {$sourceLabel}.");
            }

            return;
        }

        $commandSerialized = (string) data_get($decoded, 'data.command', '');
        if ($commandSerialized !== '') {
            $unserialized = @unserialize($commandSerialized, [
                'allowed_classes' => [ModelIdentifier::class],
            ]);

            if ($unserialized === false && $commandSerialized !== 'b:0;') {
                if ($this->payloadMentionsPodcastDomain($commandSerialized)) {
                    throw new RuntimeException("No se pudo deserializar data.command en {$sourceLabel}.");
                }
            } else {
                $this->walkUnserializedCommandNode($unserialized, $sourceLabel, $index);
            }

            $this->extractSerializedReferencesViaPatterns($commandSerialized, $sourceLabel, $index);
        }

        $this->extractSerializedReferencesViaPatterns($payload, $sourceLabel, $index);
    }

    /**
     * Recorre recursivamente el objeto `__PHP_Incomplete_Class`, `ModelIdentifier` o array
     * resultante de `unserialize()` para identificar cualquier referencia a `RadioProgram` o rutas MP3.
     *
     * @param array{
     *     program_ids: array<int, string>,
     *     paths: array<string, string>,
     *     basenames: array<string, string>
     * } $index
     */
    private function walkUnserializedCommandNode(mixed $node, string $sourceLabel, array &$index, int $depth = 0): void
    {
        if ($depth > 12 || $node === null || is_scalar($node)) {
            if (is_string($node)) {
                $this->registerPotentialPathReference($node, $sourceLabel, $index);
            }

            return;
        }

        if ($node instanceof ModelIdentifier) {
            $class = ltrim((string) $node->class, '\\');
            if ($class === RadioProgram::class || str_ends_with($class, '\\RadioProgram')) {
                $ids = is_array($node->id) ? $node->id : [$node->id];
                foreach ($ids as $id) {
                    if (is_numeric($id) && (int) $id > 0) {
                        $index['program_ids'][(int) $id] ??= $sourceLabel;
                    }
                }
            }

            return;
        }

        $props = is_object($node) ? (array) $node : (is_array($node) ? $node : []);
        if ($props === []) {
            return;
        }

        $incompleteClass = '';
        if (isset($props['__PHP_Incomplete_Class_Name']) && is_string($props['__PHP_Incomplete_Class_Name'])) {
            $incompleteClass = ltrim($props['__PHP_Incomplete_Class_Name'], '\\');
        }

        $isRadioProgramObject = $incompleteClass === RadioProgram::class || str_ends_with($incompleteClass, '\\RadioProgram');

        foreach ($props as $rawKey => $value) {
            $cleanKey = is_string($rawKey) ? $this->stripSerializedPropertyVisibilityPrefix($rawKey) : (string) $rawKey;

            if (in_array($cleanKey, ['radioProgramId', 'programId', 'episodeId'], true) && is_numeric($value) && (int) $value > 0) {
                $index['program_ids'][(int) $value] ??= $sourceLabel;
            }

            if ($isRadioProgramObject && $cleanKey === 'id' && is_numeric($value) && (int) $value > 0) {
                $index['program_ids'][(int) $value] ??= $sourceLabel;
            }

            if ($isRadioProgramObject && in_array($cleanKey, ['attributes', 'original'], true) && is_array($value)) {
                if (isset($value['id']) && is_numeric($value['id']) && (int) $value['id'] > 0) {
                    $index['program_ids'][(int) $value['id']] ??= $sourceLabel;
                }
                if (isset($value['archivo_mp3']) && is_string($value['archivo_mp3'])) {
                    $this->registerPotentialPathReference($value['archivo_mp3'], $sourceLabel, $index);
                }
            }

            if (in_array($cleanKey, ['rawPath', 'sourcePath', 'finalPath', 'archivo_mp3', 'localPath'], true) && is_string($value)) {
                $this->registerPotentialPathReference($value, $sourceLabel, $index);
            }

            $this->walkUnserializedCommandNode($value, $sourceLabel, $index, $depth + 1);
        }
    }

    private function stripSerializedPropertyVisibilityPrefix(string $key): string
    {
        if (! str_starts_with($key, "\0")) {
            return $key;
        }

        $lastNull = strrpos($key, "\0");
        if ($lastNull === false) {
            return $key;
        }

        return substr($key, $lastNull + 1);
    }

    /**
     * @param array{
     *     program_ids: array<int, string>,
     *     paths: array<string, string>,
     *     basenames: array<string, string>
     * } $index
     */
    private function extractSerializedReferencesViaPatterns(string $content, string $sourceLabel, array &$index): void
    {
        // Propiedades enteras o string en formato PHP serialize(): s:14:"radioProgramId";i:42; o con prefijo de visibilidad
        if (preg_match_all('/s:\d+:"(?:[^"]*\x00)?(?:radioProgramId|programId|episodeId)";(?:i:(\d+)|s:\d+:"(\d+)");/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $id = (int) (($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? 0));
                if ($id > 0) {
                    $index['program_ids'][$id] ??= $sourceLabel;
                }
            }
        }

        // ModelIdentifier de RadioProgram en PHP serialize()
        if (preg_match_all('/RadioProgram";s:2:"id";(?:i:(\d+)|s:\d+:"(\d+)");/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $id = (int) (($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? 0));
                if ($id > 0) {
                    $index['program_ids'][$id] ??= $sourceLabel;
                }
            }
        }

        // Propiedades en formato JSON: "radioProgramId": 42
        if (preg_match_all('/"(?:radioProgramId|programId|episodeId)"\s*:\s*"?(\d+)"?/', $content, $matches)) {
            foreach ($matches[1] ?? [] as $rawId) {
                $id = (int) $rawId;
                if ($id > 0) {
                    $index['program_ids'][$id] ??= $sourceLabel;
                }
            }
        }

        // Rutas MP3 dentro del payload
        $unescaped = str_replace('\\/', '/', $content);
        if (preg_match_all('/(?:programas_procesados|podcast-inbox)\/[^"\'\r\n\x00;]+\.mp3/iu', $unescaped, $pathMatches)) {
            foreach ($pathMatches[0] ?? [] as $matchedPath) {
                $this->registerPotentialPathReference($matchedPath, $sourceLabel, $index);
            }
        }
    }

    /**
     * @param array{
     *     program_ids: array<int, string>,
     *     paths: array<string, string>,
     *     basenames: array<string, string>
     * } $index
     */
    private function registerPotentialPathReference(string $value, string $sourceLabel, array &$index): void
    {
        $normalized = ltrim(str_replace('\\', '/', trim($value)), '/');
        if ($normalized === '' || ! str_ends_with(strtolower($normalized), '.mp3')) {
            return;
        }

        $index['paths'][$normalized] ??= $sourceLabel;
        $base = basename($normalized);
        if ($base !== '') {
            $index['basenames'][$base] ??= $sourceLabel;
        }
    }

    private function payloadMentionsPodcastDomain(string $payload): bool
    {
        return str_contains($payload, 'RadioProgram')
            || str_contains($payload, 'ProcessMp3Job')
            || str_contains($payload, 'UploadRadiobossJob')
            || str_contains($payload, 'UploadArchiveOrgJob')
            || str_contains($payload, 'programas_procesados')
            || str_contains($payload, 'podcast-inbox');
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array{
     *     program_ids: array<int, string>,
     *     paths: array<string, string>,
     *     basenames: array<string, string>
     * } $queueIndex
     */
    private function matchCandidateAgainstQueueIndex(RadioProgram $program, string $localPath, array $metadata, array $queueIndex): ?string
    {
        $programId = (int) $program->id;
        if (isset($queueIndex['program_ids'][$programId])) {
            return sprintf('Referenciado por %s (episodio #%d).', $queueIndex['program_ids'][$programId], $programId);
        }

        $candidatePaths = array_filter([
            $localPath,
            ltrim(str_replace('\\', '/', trim((string) ($program->archivo_mp3 ?? ''))), '/'),
            ltrim(str_replace('\\', '/', trim((string) ($metadata['raw_inbox_path'] ?? ''))), '/'),
        ]);

        foreach (array_unique($candidatePaths) as $path) {
            if (isset($queueIndex['paths'][$path])) {
                return sprintf('Ruta "%s" referenciada por %s.', $path, $queueIndex['paths'][$path]);
            }

            $base = basename($path);
            if ($base !== '' && isset($queueIndex['basenames'][$base])) {
                return sprintf('Archivo "%s" referenciado por %s.', $base, $queueIndex['basenames'][$base]);
            }
        }

        return null;
    }

    private function checkCrossReferencesFromOtherRecords(RadioProgram $program, string $localPath): ?string
    {
        $basename = basename($localPath);

        $otherEpisode = RadioProgram::query()
            ->where('id', '!=', $program->id)
            ->where(function ($query) use ($localPath, $basename): void {
                $query->where('archivo_mp3', $localPath)
                    ->orWhere('archivo_mp3', 'like', '%' . $basename);
            })
            ->first(['id', 'archivo_mp3', 'archivo_mp3_disk']);

        if ($otherEpisode instanceof RadioProgram) {
            return sprintf('El archivo local también está referenciado por otro episodio en radio_programs (#%d).', $otherEpisode->id);
        }

        if (Schema::hasTable('master_programs')) {
            $masterRef = MasterProgram::query()
                ->where('caratula_url', 'like', '%' . $basename)
                ->first(['id']);

            if ($masterRef instanceof MasterProgram) {
                return sprintf('Referenciado en master_programs (#%d).', $masterRef->id);
            }
        }

        return null;
    }

    /**
     * @return array{sha256: string, bytes: int}|null
     */
    private function computeRemoteSha256AndSize(string $r2Path): ?array
    {
        $stream = Storage::disk('r2')->readStream($r2Path);
        if (! is_resource($stream)) {
            return null;
        }

        $hashContext = hash_init('sha256');
        $bytes = 0;

        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 65536);
                if ($chunk === false) {
                    return null;
                }
                if ($chunk === '') {
                    continue;
                }
                $bytes += strlen($chunk);
                hash_update($hashContext, $chunk);
            }
        } finally {
            fclose($stream);
        }

        return [
            'sha256' => hash_final($hashContext),
            'bytes' => $bytes,
        ];
    }

    private function reverifyBeforeDelete(
        RadioProgram $program,
        string $localPath,
        string $localAbsolutePath,
        int $expectedSize,
        int $expectedMtime
    ): ?string {
        if (Cache::has(self::activeAccessCacheKey((int) $program->id))) {
            return 'Se inició una descarga o reintento del episodio durante la verificación.';
        }

        clearstatcache(true, $localAbsolutePath);
        if (! is_file($localAbsolutePath)) {
            return 'El archivo local dejó de existir durante la verificación.';
        }

        if ((int) filesize($localAbsolutePath) !== $expectedSize || (int) filemtime($localAbsolutePath) !== $expectedMtime) {
            return 'El archivo local fue modificado durante la verificación SHA-256.';
        }

        $freshProgram = RadioProgram::query()->find($program->id);
        if (! $freshProgram instanceof RadioProgram) {
            return 'El registro del episodio ya no existe en radio_programs.';
        }

        if (
            $freshProgram->processing_finished_at === null
            || ! in_array((string) $freshProgram->radioboss_status, ['radioboss_verified', 'skipped'], true)
            || ! in_array((string) $freshProgram->archive_org_status, ['archive_verified', 'archive_skipped'], true)
            || (string) $freshProgram->delivery_status === 'delivery_pending'
            || (string) $freshProgram->archivo_mp3_disk !== 'r2'
        ) {
            return 'El estado del episodio cambió a pendiente o en reprocesamiento durante la verificación.';
        }

        $freshQueueIndex = $this->scanAllQueueTablesWithoutLimit();
        $freshMatch = $this->matchCandidateAgainstQueueIndex(
            $freshProgram,
            $localPath,
            (array) ($freshProgram->delivery_metadata ?? []),
            $freshQueueIndex
        );

        if ($freshMatch !== null) {
            return 'Se encoló un trabajo para el episodio durante la verificación: ' . $freshMatch;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function recordPreservedReason(
        PodcastPipelineAuditService $auditService,
        RadioProgram $program,
        string $reasonCode,
        string $message,
        array $context = []
    ): void {
        try {
            $auditService->record(
                $program,
                'LOCAL_CLEANUP_SKIPPED',
                $message,
                array_merge(['reason_code' => $reasonCode], $context)
            );
        } catch (Throwable) {
            // Evitar que un fallo secundario al auditar interrumpa la preservación del archivo
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function isRetentionExpired(RadioProgram $program, array $metadata, int $retentionDays): bool
    {
        $expiresAtRaw = trim((string) ($metadata['local_copy_expires_at'] ?? ''));
        if ($expiresAtRaw !== '' && $retentionDays === ProcessMp3Job::LOCAL_RETENTION_DAYS) {
            try {
                return now()->greaterThanOrEqualTo(Carbon::parse($expiresAtRaw));
            } catch (Throwable) {
                // Fallback to reference timestamp below
            }
        }

        $referenceTime = null;
        $verifiedAtRaw = trim((string) ($metadata['r2_verified_at'] ?? $metadata['local_copy_saved_at'] ?? ''));
        if ($verifiedAtRaw !== '') {
            try {
                $referenceTime = Carbon::parse($verifiedAtRaw);
            } catch (Throwable) {
                $referenceTime = null;
            }
        }

        $referenceTime ??= $program->processing_finished_at ?? $program->delivery_verified_at;
        if (! $referenceTime instanceof Carbon) {
            return false;
        }

        return now()->greaterThanOrEqualTo($referenceTime->copy()->addDays($retentionDays));
    }
}
