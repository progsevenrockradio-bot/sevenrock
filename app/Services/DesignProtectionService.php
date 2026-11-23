<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class DesignProtectionService
{
    protected string $internalBuildPath;
    protected ?string $publicBuildPath;
    protected string $backupDir;
    protected string $backupMetaFile;
    protected string $statusFile;
    protected string $lockFile;

    public function __construct(
        ?string $internalBuildPath = null,
        ?string $publicBuildPath = null,
        ?string $backupDir = null
    ) {
        $this->internalBuildPath = $internalBuildPath ?: public_path('build');

        if ($publicBuildPath !== null) {
            $this->publicBuildPath = $publicBuildPath;
        } else {
            $candidate = base_path('../public_html/build');
            $this->publicBuildPath = is_dir($candidate) ? realpath($candidate) : null;
        }

        $baseStorage = storage_path('app/design-protection');
        $this->backupDir = $backupDir ?: $baseStorage . '/backup';
        $this->backupMetaFile = $baseStorage . '/backup-meta.json';
        $this->statusFile = $baseStorage . '/status.json';
        $this->lockFile = storage_path('framework/design-protection.lock');

        $this->ensureDirectories();
    }

    protected function ensureDirectories(): void
    {
        if (!is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0755, true);
        }
        $storageDir = dirname($this->statusFile);
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0755, true);
        }
    }

    public function getInternalBuildPath(): string
    {
        return $this->internalBuildPath;
    }

    public function getPublicBuildPath(): ?string
    {
        return $this->publicBuildPath;
    }

    public function setPublicBuildPath(?string $path): void
    {
        $this->publicBuildPath = $path;
    }

    public function setInternalBuildPath(string $path): void
    {
        $this->internalBuildPath = $path;
    }

    public function setBackupDir(string $path): void
    {
        $this->backupDir = $path;
        $this->backupMetaFile = dirname($path) . '/backup-meta.json';
        $this->statusFile = dirname($path) . '/status.json';
        $this->ensureDirectories();
    }

    public function setLockFile(string $path): void
    {
        $this->lockFile = $path;
    }

    /**
     * Valida la estructura de un build según su manifest.json.
     */
    public function validateBuild(string $buildPath, string $manifestName = 'manifest.json'): array
    {
        $manifestPath = rtrim($buildPath, '/\\') . DIRECTORY_SEPARATOR . $manifestName;

        if (!file_exists($manifestPath) || !is_readable($manifestPath)) {
            return [
                'valid' => false,
                'manifest_exists' => false,
                'manifest_hash' => null,
                'files' => [],
                'errors' => ["El archivo de manifiesto no existe o no es legible en {$manifestPath}"],
            ];
        }

        $manifestContent = file_get_contents($manifestPath);
        if ($manifestContent === false || trim($manifestContent) === '') {
            return [
                'valid' => false,
                'manifest_exists' => true,
                'manifest_hash' => null,
                'files' => [],
                'errors' => ["El manifiesto {$manifestPath} está vacío"],
            ];
        }

        $manifestData = json_decode($manifestContent, true);
        if (!is_array($manifestData) || empty($manifestData)) {
            return [
                'valid' => false,
                'manifest_exists' => true,
                'manifest_hash' => null,
                'files' => [],
                'errors' => ["El manifiesto {$manifestPath} no contiene un JSON válido o está vacío"],
            ];
        }

        $manifestHash = hash('sha256', $manifestContent);
        $files = [
            $manifestName => $manifestHash,
        ];
        $errors = [];

        foreach ($manifestData as $key => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            // Validar archivo principal de la entrada
            if (isset($entry['file']) && is_string($entry['file'])) {
                $relFile = $entry['file'];
                $fullPath = rtrim($buildPath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relFile);

                if (!file_exists($fullPath) || filesize($fullPath) === 0) {
                    $errors[] = "Falta el archivo principal o tiene 0 bytes: {$relFile} (entrada {$key})";
                } else {
                    $files[$relFile] = hash_file('sha256', $fullPath);
                }
            } else {
                $errors[] = "La entrada {$key} no define la propiedad 'file'";
            }

            // Validar dependencias CSS asociadas
            if (isset($entry['css']) && is_array($entry['css'])) {
                foreach ($entry['css'] as $cssRel) {
                    $fullCss = rtrim($buildPath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cssRel);
                    if (!file_exists($fullCss) || filesize($fullCss) === 0) {
                        $errors[] = "Falta el CSS dependiente o tiene 0 bytes: {$cssRel} (entrada {$key})";
                    } else {
                        $files[$cssRel] = hash_file('sha256', $fullCss);
                    }
                }
            }

            // Validar assets adicionales asociados
            if (isset($entry['assets']) && is_array($entry['assets'])) {
                foreach ($entry['assets'] as $assetRel) {
                    $fullAsset = rtrim($buildPath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $assetRel);
                    if (!file_exists($fullAsset) || filesize($fullAsset) === 0) {
                        $errors[] = "Falta el asset dependiente o tiene 0 bytes: {$assetRel} (entrada {$key})";
                    } else {
                        $files[$assetRel] = hash_file('sha256', $fullAsset);
                    }
                }
            }
        }

        return [
            'valid' => empty($errors),
            'manifest_exists' => true,
            'manifest_hash' => $manifestHash,
            'files' => $files,
            'errors' => $errors,
        ];
    }

    /**
     * Verifica que el build interno y el público estén sincronizados y sean idénticos.
     */
    public function verifySynchronized(?string $internalPath = null, ?string $publicPath = null): array
    {
        $internal = $internalPath ?: $this->internalBuildPath;
        $public = $publicPath ?: $this->publicBuildPath;

        if ($public === null || !is_dir($public)) {
            return [
                'synchronized' => true,
                'skipped_public' => true,
                'internal_validation' => $this->validateBuild($internal),
                'errors' => [],
            ];
        }

        $internalVal = $this->validateBuild($internal);
        $publicVal = $this->validateBuild($public);

        $errors = [];
        if (!$internalVal['valid']) {
            $errors = array_merge($errors, array_map(fn($e) => "[Build Interno] {$e}", $internalVal['errors']));
        }
        if (!$publicVal['valid']) {
            $errors = array_merge($errors, array_map(fn($e) => "[Build Público] {$e}", $publicVal['errors']));
        }

        if ($internalVal['manifest_hash'] !== $publicVal['manifest_hash']) {
            $errors[] = "Desincronización de manifiestos: hash interno ({$internalVal['manifest_hash']}) difiere del público ({$publicVal['manifest_hash']})";
        }

        // Verificar que todos los archivos del build interno existan en el público con el mismo hash
        if ($internalVal['valid'] && $publicVal['valid']) {
            foreach ($internalVal['files'] as $relPath => $intHash) {
                if (!isset($publicVal['files'][$relPath])) {
                    $errors[] = "El archivo {$relPath} existe en el build interno pero falta en el público";
                } elseif ($publicVal['files'][$relPath] !== $intHash) {
                    $errors[] = "El hash de {$relPath} difiere entre build interno y público";
                }
            }
        }

        return [
            'synchronized' => empty($errors),
            'skipped_public' => false,
            'internal_validation' => $internalVal,
            'public_validation' => $publicVal,
            'errors' => $errors,
        ];
    }

    /**
     * Crea un respaldo completo y verificado del build activo.
     */
    public function createBackup(?string $sourceBuildPath = null): array
    {
        $source = $sourceBuildPath ?: $this->internalBuildPath;
        $validation = $this->validateBuild($source);

        if (!$validation['valid']) {
            throw new \RuntimeException(
                "No se puede crear el respaldo: el build en {$source} es inválido. Errores: " . implode(', ', $validation['errors'])
            );
        }

        $this->ensureDirectories();

        File::cleanDirectory($this->backupDir);

        $copiedFiles = [];
        $filesMeta = [];

        // Copiar manifest.json y variantes
        $manifestFiles = ['manifest.json', 'manifest.json.br', 'manifest.json.gz'];
        foreach ($manifestFiles as $mf) {
            $srcFile = rtrim($source, '/\\') . DIRECTORY_SEPARATOR . $mf;
            if (file_exists($srcFile)) {
                $dstFile = rtrim($this->backupDir, '/\\') . DIRECTORY_SEPARATOR . $mf;
                File::copy($srcFile, $dstFile);
                $copiedFiles[] = $mf;
                $filesMeta[$mf] = hash_file('sha256', $dstFile);
            }
        }

        // Copiar todos los archivos declarados y variantes comprimidas
        foreach ($validation['files'] as $relPath => $originalHash) {
            if ($relPath === 'manifest.json') {
                continue;
            }

            $srcFile = rtrim($source, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
            $dstFile = rtrim($this->backupDir, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);

            $dstDir = dirname($dstFile);
            if (!is_dir($dstDir)) {
                @mkdir($dstDir, 0755, true);
            }

            File::copy($srcFile, $dstFile);
            $copiedFiles[] = $relPath;
            $filesMeta[$relPath] = hash_file('sha256', $dstFile);

            foreach (['.br', '.gz'] as $ext) {
                $compressedSrc = $srcFile . $ext;
                if (file_exists($compressedSrc)) {
                    $compressedDst = $dstFile . $ext;
                    File::copy($compressedSrc, $compressedDst);
                    $compressedRel = $relPath . $ext;
                    $copiedFiles[] = $compressedRel;
                    $filesMeta[$compressedRel] = hash_file('sha256', $compressedDst);
                }
            }
        }

        $meta = [
            'manifest_hash' => $validation['manifest_hash'],
            'created_at' => date('c'),
            'source_path' => $source,
            'total_files' => count($copiedFiles),
            'files' => $filesMeta,
        ];

        file_put_contents($this->backupMetaFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->updateStatus([
            'status' => 'healthy',
            'last_backup' => $meta['created_at'],
            'backup_manifest_hash' => $meta['manifest_hash'],
            'message' => 'Respaldo verificado creado correctamente',
        ]);

        return [
            'success' => true,
            'manifest_hash' => $validation['manifest_hash'],
            'backup_dir' => $this->backupDir,
            'total_files' => count($copiedFiles),
            'files' => $copiedFiles,
        ];
    }

    /**
     * Obtiene los metadatos del respaldo actual.
     */
    public function getBackupMetadata(): ?array
    {
        if (!file_exists($this->backupMetaFile)) {
            return null;
        }

        $content = file_get_contents($this->backupMetaFile);
        $data = json_decode((string) $content, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Comprueba si el respaldo actual es compatible con una versión de manifiesto.
     */
    public function isBackupCompatible(string $targetManifestHash): bool
    {
        $meta = $this->getBackupMetadata();
        if ($meta === null || !isset($meta['manifest_hash'])) {
            return false;
        }

        return hash_equals($meta['manifest_hash'], $targetManifestHash);
    }

    /**
     * Sistema de Bloqueo / Coordinación común entre despliegue y vigilancia.
     */
    public function acquireLock(int $timeoutSeconds = 60, string $reason = 'deployment'): bool
    {
        if ($this->isLocked()) {
            return false;
        }

        $data = [
            'locked_at' => time(),
            'timeout' => $timeoutSeconds,
            'expires_at' => time() + $timeoutSeconds,
            'reason' => $reason,
            'pid' => getmypid(),
        ];

        $dir = dirname($this->lockFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return file_put_contents($this->lockFile, json_encode($data)) !== false;
    }

    public function releaseLock(): void
    {
        if (file_exists($this->lockFile)) {
            @unlink($this->lockFile);
        }
    }

    public function isLocked(): bool
    {
        if (!file_exists($this->lockFile)) {
            return false;
        }

        $content = @file_get_contents($this->lockFile);
        $data = json_decode((string) $content, true);

        if (!is_array($data) || !isset($data['expires_at'])) {
            @unlink($this->lockFile);
            return false;
        }

        if (time() >= $data['expires_at']) {
            @unlink($this->lockFile);
            return false;
        }

        return true;
    }

    public function getLockInfo(): ?array
    {
        if (!$this->isLocked()) {
            return null;
        }

        $content = @file_get_contents($this->lockFile);
        $data = json_decode((string) $content, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Auditoría y recuperación segura de archivos de diseño.
     */
    public function auditAndRecover(
        bool $autoRecover = true,
        bool $checkHttp = false,
        ?string $publicBuildPath = null
    ): array {
        if ($this->isLocked()) {
            $lock = $this->getLockInfo();
            $msg = 'Vigilancia pospuesta: bloqueo activo de ' . ($lock['reason'] ?? 'despliegue');
            $this->updateStatus([
                'status' => 'locked',
                'message' => $msg,
            ]);

            return [
                'status' => 'locked',
                'action_taken' => 'skipped',
                'message' => $msg,
            ];
        }

        $public = $publicBuildPath ?: $this->publicBuildPath;
        $syncCheck = $this->verifySynchronized($this->internalBuildPath, $public);
        $internalVal = $syncCheck['internal_validation'];

        $issuesFound = !$syncCheck['synchronized'] || !$internalVal['valid'];
        $missingFiles = [];
        $recoveredFiles = [];
        $recoveryAttempted = false;

        $httpErrors = [];
        if ($checkHttp && $internalVal['valid']) {
            $httpErrors = $this->checkPublicAssetsHttp($internalVal);
            if (!empty($httpErrors)) {
                $issuesFound = true;
            }
        }

        if (!$internalVal['manifest_exists']) {
            $targetHash = null;
            $missingFiles[] = 'manifest.json';
        } else {
            $targetHash = $internalVal['manifest_hash'];
            foreach ($internalVal['errors'] as $err) {
                if (preg_match('/Falta.*?:\s*([^\s]+)/', $err, $m)) {
                    $missingFiles[] = $m[1];
                }
            }
        }

        if ($public && is_dir($public) && isset($syncCheck['public_validation'])) {
            $pubVal = $syncCheck['public_validation'];
            foreach ($pubVal['errors'] as $err) {
                if (preg_match('/Falta.*?:\s*([^\s]+)/', $err, $m)) {
                    $missingFiles[] = 'public:' . $m[1];
                }
            }
        }

        if ($issuesFound && $autoRecover) {
            $recoveryAttempted = true;
            $backupMeta = $this->getBackupMetadata();

            if ($backupMeta === null) {
                $this->notifyAlert(
                    'critical',
                    'Alerta de diseño: Fallo detectado sin respaldo',
                    'Se han detectado problemas en los archivos de diseño pero no existe un respaldo verificado disponible.',
                    ['errors' => array_merge($syncCheck['errors'], $httpErrors)]
                );
                $this->updateStatus([
                    'status' => 'corrupt_unrecoverable',
                    'message' => 'Fallo detectado sin respaldo compatible',
                    'errors' => array_merge($syncCheck['errors'], $httpErrors),
                ]);

                return [
                    'status' => 'corrupt_unrecoverable',
                    'action_taken' => 'none',
                    'message' => 'No existe respaldo verificado para recuperar',
                    'errors' => array_merge($syncCheck['errors'], $httpErrors),
                ];
            }

            $isCompatible = false;
            if ($targetHash !== null && hash_equals($backupMeta['manifest_hash'], $targetHash)) {
                $isCompatible = true;
            } elseif ($targetHash === null && isset($backupMeta['manifest_hash'])) {
                $isCompatible = true;
            }

            if (!$isCompatible) {
                $this->notifyAlert(
                    'critical',
                    'Alerta de diseño: Respaldo incompatible',
                    "El respaldo existente (hash {$backupMeta['manifest_hash']}) no corresponde a la versión activa ($targetHash). No se mezclan versiones.",
                    ['errors' => array_merge($syncCheck['errors'], $httpErrors)]
                );
                $this->updateStatus([
                    'status' => 'corrupt_unrecoverable',
                    'message' => 'Respaldo incompatible rechazado',
                    'errors' => array_merge($syncCheck['errors'], $httpErrors),
                ]);

                return [
                    'status' => 'corrupt_unrecoverable',
                    'action_taken' => 'rejected_incompatible_backup',
                    'message' => 'Respaldo incompatible con la versión activa. No se realizaron cambios a ciegas.',
                    'errors' => array_merge($syncCheck['errors'], $httpErrors),
                ];
            }

            $targets = [$this->internalBuildPath];
            if ($public && is_dir($public)) {
                $targets[] = $public;
            }

            $restoredCount = 0;
            foreach ($targets as $targetDir) {
                foreach ($backupMeta['files'] as $relFile => $expectedHash) {
                    $targetFile = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relFile);
                    $backupFile = rtrim($this->backupDir, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relFile);

                    $needsRestore = false;
                    if (!file_exists($targetFile) || filesize($targetFile) === 0) {
                        $needsRestore = true;
                    } elseif (hash_file('sha256', $targetFile) !== $expectedHash) {
                        $needsRestore = true;
                    }

                    if ($needsRestore && file_exists($backupFile)) {
                        $dstDir = dirname($targetFile);
                        if (!is_dir($dstDir)) {
                            @mkdir($dstDir, 0755, true);
                        }
                        File::copy($backupFile, $targetFile);

                        $actualHash = hash_file('sha256', $targetFile);
                        if ($actualHash === $expectedHash) {
                            $recoveredFiles[] = basename($targetDir) . ':' . $relFile;
                            $restoredCount++;
                        }
                    }
                }
            }

            try {
                Artisan::call('view:clear');
            } catch (Throwable $e) {
                Log::warning('DesignProtection view:clear warning: ' . $e->getMessage());
            }

            $postRecoveryVal = $this->verifySynchronized($this->internalBuildPath, $public);
            $recoverySuccess = $postRecoveryVal['synchronized'] && $postRecoveryVal['internal_validation']['valid'];

            if ($recoverySuccess) {
                $this->notifyAlert(
                    'info',
                    'Diseño recuperado exitosamente',
                    "Se restauraron {$restoredCount} archivos desde el respaldo compatible de la versión {$backupMeta['manifest_hash']}.",
                    ['recovered_files' => $recoveredFiles]
                );

                $this->updateStatus([
                    'status' => 'recovered',
                    'message' => "Recuperación exitosa de {$restoredCount} archivos",
                    'recovered_files' => $recoveredFiles,
                    'last_recovery' => date('c'),
                ]);

                return [
                    'status' => 'recovered',
                    'action_taken' => 'restored_from_backup',
                    'recovered_files' => $recoveredFiles,
                    'message' => "Se restauraron {$restoredCount} archivos con su hash correcto",
                ];
            }
        }

        if ($issuesFound) {
            $allErrors = array_merge($syncCheck['errors'], $httpErrors);
            $this->updateStatus([
                'status' => 'error',
                'message' => 'Problemas detectados en los archivos de diseño',
                'errors' => $allErrors,
            ]);

            return [
                'status' => 'error',
                'action_taken' => $recoveryAttempted ? 'recovery_failed' : 'none',
                'errors' => $allErrors,
            ];
        }

        $this->updateStatus([
            'status' => 'healthy',
            'message' => 'Build y manifiesto sincronizados e íntegros',
            'manifest_hash' => $internalVal['manifest_hash'],
        ]);

        return [
            'status' => 'healthy',
            'action_taken' => 'none',
            'manifest_hash' => $internalVal['manifest_hash'],
            'total_files' => count($internalVal['files']),
        ];
    }

    /**
     * Realiza una comprobación HTTP de los recursos clave solicitados por el HTML público.
     */
    protected function checkPublicAssetsHttp(array $internalValidation): array
    {
        $appUrl = rtrim((string) config('app.url', 'https://sevenrockradio.com'), '/');
        $errors = [];

        foreach ($internalValidation['files'] as $relPath => $hash) {
            if (str_ends_with($relPath, '.css') && str_contains($relPath, 'app-')) {
                $cssUrl = "{$appUrl}/build/{$relPath}";
                try {
                    $response = Http::timeout(5)->head($cssUrl);
                    if ($response->status() !== 200) {
                        $errors[] = "El CSS público {$cssUrl} respondió con código HTTP {$response->status()}";
                    } else {
                        $ct = $response->header('Content-Type') ?? '';
                        if (!str_contains($ct, 'text/css')) {
                            $errors[] = "El CSS público {$cssUrl} respondió con Content-Type incorrecto: {$ct}";
                        }
                    }
                } catch (Throwable $e) {
                    $errors[] = "Error de conexión al verificar CSS público {$cssUrl}: " . $e->getMessage();
                }
                break;
            }
        }

        return $errors;
    }

    /**
     * Notificación de alertas con deduplicación/throttling para evitar spam.
     */
    public function notifyAlert(string $level, string $title, string $message, array $context = []): void
    {
        $signature = md5($level . $title . $message);
        $throttleKey = storage_path("app/design-protection/alert-throttle-{$signature}");

        if (file_exists($throttleKey) && (time() - filemtime($throttleKey) < 3600)) {
            return;
        }

        @touch($throttleKey);

        if ($level === 'critical' || $level === 'error') {
            Log::error("[DesignProtection] {$title}: {$message}", $context);
        } else {
            Log::info("[DesignProtection] {$title}: {$message}", $context);
        }

        $token = env('TELEGRAM_BOT_TOKEN');
        $chatId = env('TELEGRAM_ADMIN_CHAT_ID') ?: env('TELEGRAM_CHAT_ID');

        if ($token && $chatId) {
            $text = "🛡️ *[Seven Rock Radio - Design Protection]*\n*{$title}*\n{$message}";
            try {
                Http::timeout(5)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'Markdown',
                ]);
            } catch (Throwable $e) {
                Log::warning('No se pudo enviar alerta de diseño a Telegram: ' . $e->getMessage());
            }
        }
    }

    /**
     * Persiste el estado de la vigilancia para consulta administrativa.
     */
    protected function updateStatus(array $data): void
    {
        $existing = [];
        if (file_exists($this->statusFile)) {
            $existing = json_decode((string) file_get_contents($this->statusFile), true) ?: [];
        }

        $merged = array_merge($existing, $data, [
            'updated_at' => date('c'),
        ]);

        file_put_contents($this->statusFile, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Resumen para el panel de administración.
     */
    public function getStatusSummary(): array
    {
        $statusData = [];
        if (file_exists($this->statusFile)) {
            $statusData = json_decode((string) file_get_contents($this->statusFile), true) ?: [];
        }

        $backupMeta = $this->getBackupMetadata();
        $val = $this->validateBuild($this->internalBuildPath);

        $state = $statusData['status'] ?? ($val['valid'] ? 'healthy' : 'error');

        $labels = [
            'healthy' => 'Protegido y Operativo',
            'recovered' => 'Recuperado Automáticamente',
            'locked' => 'Despliegue en curso',
            'corrupt_unrecoverable' => 'Alerta: Respaldo no disponible',
            'error' => 'Alerta de Integridad',
        ];

        return [
            'status' => $state,
            'label' => $labels[$state] ?? 'Desconocido',
            'last_check' => $statusData['updated_at'] ?? null,
            'manifest_hash' => $val['manifest_hash'] ? substr($val['manifest_hash'], 0, 10) : 'N/A',
            'backup_available' => $backupMeta !== null,
            'backup_hash' => isset($backupMeta['manifest_hash']) ? substr($backupMeta['manifest_hash'], 0, 10) : 'N/A',
            'backup_created_at' => $backupMeta['created_at'] ?? null,
            'is_compatible' => $backupMeta && $val['manifest_hash'] ? hash_equals($backupMeta['manifest_hash'], $val['manifest_hash']) : false,
            'message' => $statusData['message'] ?? 'Sistema listo',
        ];
    }
}
