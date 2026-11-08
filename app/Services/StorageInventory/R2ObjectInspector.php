<?php

declare(strict_types=1);

namespace App\Services\StorageInventory;

use App\Services\FileUploadService;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Consultas de SOLO LECTURA sobre Cloudflare R2.
 *
 * - head(): HEAD del objeto con el cliente S3, distinguiendo «no existe» (404)
 *   de «error de consulta». El disco r2 está configurado con throw=false, por lo
 *   que Storage::exists() devolvería false ante cualquier error; aquí no se usa.
 * - hash(): descarga el objeto en streaming y calcula SHA-256 y MD5 en memoria,
 *   sin escribir nada en disco.
 *
 * Nunca escribe, copia ni borra objetos.
 */
class R2ObjectInspector
{
    public function __construct(private readonly FileUploadService $files)
    {
    }

    public function isConfigured(): bool
    {
        return $this->files->isR2Configured();
    }

    public function bucket(): string
    {
        return (string) config('filesystems.disks.r2.bucket', '');
    }

    public function publicBaseUrl(): string
    {
        return rtrim((string) config('filesystems.disks.r2.url', ''), '/');
    }

    /**
     * URL que la aplicación generaría para la clave en R2.
     */
    public function appUrl(string $key): string
    {
        return $this->files->url($key, 'r2');
    }

    /**
     * @return array{status:string,size:?int,etag:?string,last_modified:?string,error:?string}
     */
    public function head(string $key): array
    {
        $result = ['status' => 'error', 'size' => null, 'etag' => null, 'last_modified' => null, 'error' => null];

        if (! $this->isConfigured()) {
            $result['status'] = 'not_configured';

            return $result;
        }

        try {
            $adapter = Storage::disk('r2');
            if (! method_exists($adapter, 'getClient')) {
                $result['error'] = 'El adaptador R2 no expone cliente S3; no se puede distinguir 404 de error.';

                return $result;
            }

            $response = $adapter->getClient()->headObject([
                'Bucket' => $this->bucket(),
                'Key' => $key,
            ]);

            $result['status'] = 'present';
            $result['size'] = isset($response['ContentLength']) ? (int) $response['ContentLength'] : null;
            $result['etag'] = isset($response['ETag']) ? trim((string) $response['ETag'], '"') : null;
            $result['last_modified'] = isset($response['LastModified']) ? (string) $response['LastModified'] : null;
        } catch (Throwable $e) {
            $status = method_exists($e, 'getStatusCode') ? (int) $e->getStatusCode() : 0;
            if ($status === 404) {
                $result['status'] = 'missing';
            } else {
                $result['error'] = class_basename($e) . ($status > 0 ? " HTTP {$status}" : '');
            }
        }

        return $result;
    }

    /**
     * @return array{status:string,sha256:?string,md5:?string,bytes:int,error:?string}
     */
    public function hash(string $key): array
    {
        $result = ['status' => 'error', 'sha256' => null, 'md5' => null, 'bytes' => 0, 'error' => null];

        if (! $this->isConfigured()) {
            $result['status'] = 'not_configured';

            return $result;
        }

        try {
            $stream = Storage::disk('r2')->readStream($key);
            if (! is_resource($stream)) {
                $result['error'] = 'readStream no devolvió un flujo legible.';

                return $result;
            }

            $sha = hash_init('sha256');
            $md5 = hash_init('md5');
            $bytes = 0;

            try {
                while (! feof($stream)) {
                    $chunk = fread($stream, 1024 * 1024);
                    if ($chunk === false) {
                        $result['error'] = 'Lectura interrumpida.';

                        return $result;
                    }
                    $bytes += strlen($chunk);
                    hash_update($sha, $chunk);
                    hash_update($md5, $chunk);
                }
            } finally {
                fclose($stream);
            }

            $result['status'] = 'ok';
            $result['sha256'] = hash_final($sha);
            $result['md5'] = hash_final($md5);
            $result['bytes'] = $bytes;
        } catch (Throwable $e) {
            $result['error'] = class_basename($e);
        }

        return $result;
    }
}

