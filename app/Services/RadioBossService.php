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
            throw new RuntimeException("No se pudo leer el archivo local para RadioBOSS: {$localPath}");
        }

        $connection = $this->connect();

        try {
            $this->ensureRemoteDirectory($connection, $folder);

            $this->clearRemoteMp3Files($connection, $folder);

            $this->uploadStream($connection, $remotePath, $localPath);
        } finally {
            ftp_close($connection);
        }
    }

    public function exists(string $remotePath): bool
    {
        $remotePath = $this->normalizeRemotePath($remotePath);
        if ($remotePath === '') {
            return false;
        }

        $connection = $this->connect();

        try {
            $size = @ftp_size($connection, $remotePath);

            return $size >= 0;
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

    private function uploadStream($connection, string $remotePath, string $localPath): void
    {
        $stream = fopen($localPath, 'rb');
        if ($stream === false) {
            throw new RuntimeException("No se pudo abrir el archivo local para RadioBOSS: {$localPath}");
        }

        try {
            if (! @ftp_fput($connection, $remotePath, $stream, FTP_BINARY)) {
                throw new RuntimeException("La subida FTP a RadioBOSS falló para {$remotePath}");
            }
        } finally {
            fclose($stream);
        }
    }

    private function clearRemoteMp3Files($connection, string $folder): void
    {
        try {
            $files = $this->listRemoteFiles($connection, $folder);
            if ($files === []) {
                return;
            }

            foreach ($files as $file) {
                if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'mp3') {
                    continue;
                }

                $deleted = @ftp_delete($connection, $file);
                if ($deleted) {
                    Log::info('RadioBossService: archivo MP3 remoto eliminado antes de la subida.', [
                        'folder' => $folder,
                        'file' => $file,
                    ]);
                    continue;
                }

                Log::warning('RadioBossService: no se pudo eliminar un archivo MP3 remoto antes de la subida.', [
                    'folder' => $folder,
                    'file' => $file,
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('RadioBossService: no se pudieron limpiar los archivos remotos', [
                'folder' => $folder,
                'exception' => $exception->getMessage(),
            ]);
        }
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
}
