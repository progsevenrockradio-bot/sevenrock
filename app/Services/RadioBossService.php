<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class RadioBossService
{
    public function canSync(): bool
    {
        return trim((string) config('filesystems.disks.radioboss.host', '')) !== ''
            && trim((string) config('filesystems.disks.radioboss.username', '')) !== ''
            && trim((string) config('filesystems.disks.radioboss.password', '')) !== '';
    }

    public function upload(string $folder, string $remotePath, string $localPath, bool $clearBeforeUpload = false): void
    {
        $folder = $this->normalizeRemotePath($folder);
        $remotePath = $this->normalizeRemotePath($remotePath);

        if ($folder === '' || $remotePath === '') {
            throw new RuntimeException('La ruta remota de RadioBOSS está vacía.');
        }

        if (! is_file($localPath) || ! is_readable($localPath)) {
            Log::warning('RadioBossService: local file missing or unreadable before upload.', [
                'folder' => $folder,
                'remote_path' => $remotePath,
                'local_path' => $localPath,
            ]);
            throw new RuntimeException("No se pudo leer el archivo local para RadioBOSS: {$localPath}");
        }

        $localSize = $this->fileSize($localPath);
        Log::info('RadioBossService: iniciando subida FTP.', [
            'folder' => $folder,
            'remote_path' => $remotePath,
            'local_path' => $localPath,
            'local_size' => $localSize,
            'clear_before_upload' => $clearBeforeUpload,
        ]);

        $connection = $this->connect();
        $tempPath = '';

        try {
            $this->ensureRemoteDirectory($connection, $folder);

            // Destinos versionados sin colisión: NUNCA borrar destino existente antes de renombrar
            if ($this->remoteFileExists($connection, $remotePath)) {
                $filename = pathinfo($remotePath, PATHINFO_FILENAME);
                $ext = pathinfo($remotePath, PATHINFO_EXTENSION);
                $dir = dirname($remotePath);
                $prefix = ($dir === '.' || $dir === '/' || $dir === '') ? '' : rtrim($dir, '/') . '/';
                $versionedPath = $prefix . "{$filename}_v2.{$ext}";

                if ($this->remoteFileExists($connection, $versionedPath)) {
                    throw new RuntimeException("Destino existente en RadioBOSS ('{$versionedPath}'). Entrega detenida para no sobrescribir un archivo existente o potencialmente en uso.");
                }

                $remotePath = $versionedPath;
            }

            // Temporal único por transferencia
            $uniqueId = bin2hex(random_bytes(6));
            $dir = dirname($remotePath);
            $prefix = ($dir === '.' || $dir === '/' || $dir === '') ? '' : rtrim($dir, '/') . '/';
            $tempPath = $prefix . '.tmp_' . $uniqueId . '_' . basename($remotePath) . '.part';

            // Subir a un archivo temporal único para que RadioBOSS no lea un stream parcial
            $this->uploadStream($connection, $tempPath, $localPath, $localSize);

            // Verificar tamaño remoto transferido estrictamente
            $remoteSize = @ftp_size($connection, $tempPath);
            if ($remoteSize <= 0 || ($localSize > 0 && $remoteSize !== $localSize)) {
                @ftp_delete($connection, $tempPath);
                throw new RuntimeException("El tamaño en RadioBOSS ({$remoteSize} bytes) no coincide con el local ({$localSize} bytes).");
            }

            // Renombrado estricto sin sobrescritura previa
            if (! @ftp_rename($connection, $tempPath, $remotePath)) {
                @ftp_delete($connection, $tempPath);
                throw new RuntimeException("No se pudo renombrar {$tempPath} a {$remotePath} en RadioBOSS.");
            }

            // Verificación post-renombrado del archivo definitivo
            $finalSize = @ftp_size($connection, $remotePath);
            if ($finalSize <= 0 || ($localSize > 0 && $finalSize !== $localSize)) {
                throw new RuntimeException("Verificación post-renombrado fallida para {$remotePath} en RadioBOSS (tamaño: {$finalSize}, esperado: {$localSize}).");
            }

            // Se preservan TODOS los archivos anteriores en el servidor. Limpieza automática desactivada.
            if ($clearBeforeUpload) {
                $this->cleanObsoleteRemoteFiles($connection, $folder, $remotePath);
            }

            Log::info('RadioBossService: subida FTP completada de forma atómica y verificada.', [
                'folder' => $folder,
                'remote_path' => $remotePath,
                'local_path' => $localPath,
                'local_size' => $localSize,
            ]);
        } catch (Throwable $e) {
            if (isset($connection) && is_resource($connection) && $tempPath !== '') {
                @ftp_delete($connection, $tempPath);
            }
            throw $e;
        } finally {
            ftp_close($connection);
        }
    }

    /**
     * Verifica si un archivo existe en el servidor FTP de RadioBOSS.
     * Usa ftp_size como método principal, y ftp_rawlist como fallback
     * (algunos servidores FTP no soportan el comando SIZE).
     */
    public function exists(string $remotePath): bool
    {
        $remotePath = $this->normalizeRemotePath($remotePath);
        if ($remotePath === '') {
            return false;
        }

        $connection = $this->connect();

        try {
            // Método 1: ftp_size (rápido, pero no todos los servidores lo soportan)
            $size = @ftp_size($connection, $remotePath);
            if ($size >= 0) {
                return true;
            }

            // Método 2: fallback a ftp_rawlist para servidores que no soportan SIZE
            $directory = dirname($remotePath);
            $filename = basename($remotePath);

            if ($directory === '.' || $directory === '') {
                $directory = '/';
            }

            $listing = @ftp_rawlist($connection, $directory);
            if (is_array($listing)) {
                foreach ($listing as $line) {
                    $parts = preg_split('/\s+/', trim((string) $line), 9);
                    $name = $parts[8] ?? '';
                    if ($name === $filename) {
                        return true;
                    }
                }
            }

            return false;
        } finally {
            ftp_close($connection);
        }
    }

    public function read(string $remotePath): ?string
    {
        $remotePath = $this->normalizeRemotePath($remotePath);
        if ($remotePath === '') {
            return null;
        }

        $connection = $this->connect();

        try {
            $stream = fopen('php://temp', 'w+b');
            if ($stream === false) {
                return null;
            }

            try {
                if (! @ftp_fget($connection, $stream, $remotePath, FTP_BINARY)) {
                    return null;
                }

                rewind($stream);
                $contents = stream_get_contents($stream);

                return is_string($contents) && $contents !== '' ? $contents : null;
            } finally {
                fclose($stream);
            }
        } finally {
            ftp_close($connection);
        }
    }

    /**
     * @return array<int, string>
     */
    public function files(string $folder): array
    {
        $folder = $this->normalizeRemotePath($folder);
        if ($folder === '') {
            return [];
        }

        $connection = $this->connect();

        try {
            $listing = @ftp_rawlist($connection, $folder);
            if (! is_array($listing)) {
                return [];
            }

            $files = [];
            foreach ($listing as $line) {
                $parts = preg_split('/\s+/', trim((string) $line), 9);
                $name = $parts[8] ?? '';
                if ($name === '' || $name === '.' || $name === '..') {
                    continue;
                }

                $files[] = $folder . '/' . ltrim($name, '/');
            }

            return $files;
        } finally {
            ftp_close($connection);
        }
    }

    private function connect()
    {
        if (! function_exists('ftp_connect')) {
            throw new RuntimeException('La extensión FTP de PHP no está disponible.');
        }

        $host = trim((string) config('filesystems.disks.radioboss.host', ''));
        $user = trim((string) config('filesystems.disks.radioboss.username', ''));
        $password = (string) config('filesystems.disks.radioboss.password', '');
        $port = (int) config('filesystems.disks.radioboss.port', 21);
        $timeout = (int) config('filesystems.disks.radioboss.timeout', 60);
        $ssl = filter_var(config('filesystems.disks.radioboss.ssl', false), FILTER_VALIDATE_BOOL);
        $passive = filter_var(config('filesystems.disks.radioboss.passive', true), FILTER_VALIDATE_BOOL);

        $connection = $ssl && function_exists('ftp_ssl_connect')
            ? @ftp_ssl_connect($host, $port, $timeout)
            : @ftp_connect($host, $port, $timeout);

        if ($connection === false) {
            Log::warning('RadioBossService: no se pudo abrir conexión FTP con RadioBOSS.', [
                'host' => $host,
                'port' => $port,
                'ssl' => $ssl,
            ]);
            throw new RuntimeException("No se pudo abrir conexión FTP con RadioBOSS en {$host}:{$port}");
        }

        if (! @ftp_login($connection, $user, $password)) {
            Log::warning('RadioBossService: fallo de autenticación FTP con RadioBOSS.', [
                'host' => $host,
                'port' => $port,
                'user' => $user,
                'ssl' => $ssl,
            ]);
            ftp_close($connection);
            throw new RuntimeException('No se pudo autenticar con RadioBOSS.');
        }

        ftp_pasv($connection, $passive);

        return $connection;
    }

    private function ensureRemoteDirectory($connection, string $folder): void
    {
        $segments = array_values(array_filter(explode('/', str_replace('\\', '/', trim($folder))), static fn (string $segment): bool => $segment !== ''));
        if ($segments === []) {
            return;
        }

        $path = '';
        foreach ($segments as $segment) {
            $path = $path === '' ? $segment : $path . '/' . $segment;
            @ftp_mkdir($connection, $path);
        }
    }

    private function uploadStream($connection, string $remotePath, string $localPath, ?int $localSize = null): void
    {
        // Set a high timeout for the FTP data transfer (large MP3 files)
        @ftp_set_option($connection, FTP_TIMEOUT_SEC, 600);
        $stream = fopen($localPath, 'rb');
        if ($stream === false) {
            throw new RuntimeException("No se pudo abrir el archivo local para RadioBOSS: {$localPath}");
        }

        try {
            if (! @ftp_fput($connection, $remotePath, $stream, FTP_BINARY)) {
                $error = error_get_last();
                $ftpMessage = $error["message"] ?? "sin mensaje de error";
                Log::error('RadioBossService: ftp_fput falló', [
                    'remote_path' => $remotePath,
                    'local_path' => $localPath,
                    'local_size' => $localSize ?? $this->fileSize($localPath),
                    'error' => $ftpMessage,
                ]);
                throw new RuntimeException("La subida FTP a RadioBOSS falló para {$remotePath}: {$ftpMessage}");
            }
        } finally {
            fclose($stream);
        }
    }

    private function cleanObsoleteRemoteFiles($connection, string $folder, string $currentRemotePath): void
    {
        // Desactivado: Por política de seguridad y preservación de emisión, NUNCA se eliminan
        // archivos MP3 ni temporales ajenos automáticamente. El episodio anterior actúa como respaldo.
        Log::info('RadioBossService: limpieza automática de archivos remotos desactivada para preservar respaldos y emisión.', [
            'folder' => $folder,
            'current' => $currentRemotePath,
        ]);
    }

    private function remoteFileExists($connection, string $remotePath): bool
    {
        $remotePath = $this->normalizeRemotePath($remotePath);
        if ($remotePath === '') {
            return false;
        }

        $size = @ftp_size($connection, $remotePath);
        if ($size >= 0) {
            return true;
        }

        $directory = dirname($remotePath);
        $filename = basename($remotePath);
        if ($directory === '.' || $directory === '') {
            $directory = '/';
        }

        $listing = @ftp_rawlist($connection, $directory);
        if (is_array($listing)) {
            foreach ($listing as $line) {
                $parts = preg_split('/\s+/', trim((string) $line), 9);
                $name = $parts[8] ?? '';
                if ($name === $filename) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function listRemoteFiles($connection, string $folder): array
    {
        $listing = @ftp_rawlist($connection, $folder);
        if (! is_array($listing)) {
            return [];
        }

        $files = [];
        foreach ($listing as $line) {
            $entry = $this->parseRawListLine((string) $line);
            if ($entry === null) {
                continue;
            }

            if ($entry['name'] === '.' || $entry['name'] === '..') {
                continue;
            }

            if ($entry['type'] !== 'file') {
                continue;
            }

            $files[] = $folder . '/' . ltrim($entry['name'], '/');
        }

        return $files;
    }

    /**
     * @return array{type:'file'|'dir'|'unknown', name:string}|null
     */
    private function parseRawListLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        $parts = preg_split('/\s+/', $line, 9);
        if (! is_array($parts) || count($parts) < 9) {
            return null;
        }

        $name = trim((string) ($parts[8] ?? ''));
        $type = match ($line[0] ?? '') {
            'd' => 'dir',
            '-' => 'file',
            default => 'unknown',
        };

        return [
            'type' => $type,
            'name' => $name,
        ];
    }

    private function normalizeRemotePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        $path = preg_replace('#/+#', '/', $path) ?: $path;

        return trim($path, '/');
    }

    private function fileSize(string $path): int
    {
        return is_file($path) ? (int) filesize($path) : 0;
    }
}
