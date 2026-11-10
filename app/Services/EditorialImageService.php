<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EditorialImageService
{
    public const MAX_SIZE_BYTES = 12582912; // 12 MB
    public const MIN_SIZE_BYTES = 4096;     // 4 KB
    public const USER_AGENT = 'SevenRockRadioEditorial/1.0 (https://sevenrockradio.com; prog.sevenrockradio@gmail.com)';

    /**
     * Resuelve, valida, descarga y optimiza una imagen nueva para un elemento editorial.
     *
     * @return array{success: bool, path: ?string, credit: ?string, error: ?string}
     */
    public function resolveAndStore(string $rawUrlOrPath, string $elementId, string $slug, ?string $declaredCredit = null): array
    {
        $raw = trim($rawUrlOrPath);

        if ($raw === '' || $raw === 'NINGUNA' || $raw === 'none') {
            return [
                'success' => true,
                'path'    => null,
                'credit'  => null,
                'error'   => null,
            ];
        }

        // 1. Caso A: Ruta local preexistente en almacenamiento persistente
        if (! str_starts_with($raw, 'http://') && ! str_starts_with($raw, 'https://')) {
            $storageRelative = str_starts_with($raw, 'storage/') ? substr($raw, 8) : $raw;
            $fullStoragePath = storage_path('app/public/' . $storageRelative);
            $publicPath = public_path($raw);

            if (file_exists($fullStoragePath) || file_exists($publicPath)) {
                return [
                    'success' => true,
                    'path'    => str_starts_with($raw, 'storage/') ? $raw : "storage/{$raw}",
                    'credit'  => $declaredCredit,
                    'error'   => null,
                ];
            }

            return [
                'success' => false,
                'path'    => null,
                'credit'  => $declaredCredit,
                'error'   => "Ruta local no encontrada en disco: '{$raw}'",
            ];
        }

        // 2. Caso B: Ficha de Wikimedia Commons o Wikipedia (resolución de API)
        $downloadUrl = $raw;
        $resolvedCredit = $declaredCredit;

        if ($this->isWikimediaPageUrl($raw)) {
            $metaResult = $this->resolveFromWikimediaCommonsApi($raw);
            if (! $metaResult['success']) {
                return [
                    'success' => false,
                    'path'    => null,
                    'credit'  => $declaredCredit,
                    'error'   => "Error al resolver ficha de Wikimedia Commons: " . $metaResult['error'],
                ];
            }

            $downloadUrl = $metaResult['download_url'];
            if (empty($resolvedCredit) && ! empty($metaResult['credit'])) {
                $resolvedCredit = $metaResult['credit'];
            }
        }

        // 3. Validación de seguridad y prevención de SSRF
        $securityCheck = $this->validateSafeRemoteUrl($downloadUrl);
        if (! $securityCheck['valid']) {
            return [
                'success' => false,
                'path'    => null,
                'credit'  => $declaredCredit,
                'error'   => "Seguridad: " . $securityCheck['error'],
            ];
        }

        // 4. Descarga controlada a fichero temporal
        $downloadResult = $this->downloadToTempFile($downloadUrl);
        if (! $downloadResult['success']) {
            return [
                'success' => false,
                'path'    => null,
                'credit'  => $declaredCredit,
                'error'   => "Fallo de descarga: " . $downloadResult['error'],
            ];
        }

        $tempPath = $downloadResult['temp_path'];

        // 5. Validación de formato, dimensiones y optimización con GD
        try {
            $storeResult = $this->optimizeAndStoreImage($tempPath, $elementId, $slug);
            @unlink($tempPath);

            if (! $storeResult['success']) {
                return [
                    'success' => false,
                    'path'    => null,
                    'credit'  => $declaredCredit,
                    'error'   => "Fallo de optimización: " . $storeResult['error'],
                ];
            }

            return [
                'success' => true,
                'path'    => $storeResult['relative_path'],
                'credit'  => $resolvedCredit,
                'error'   => null,
            ];
        } catch (\Throwable $e) {
            @unlink($tempPath);
            return [
                'success' => false,
                'path'    => null,
                'credit'  => $declaredCredit,
                'error'   => "Excepción en procesamiento de imagen: " . $e->getMessage(),
            ];
        }
    }

    /**
     * Determina si la URL corresponde a una página de ficha de archivo en Wikimedia/Wikipedia.
     */
    public function isWikimediaPageUrl(string $url): bool
    {
        return (bool) preg_match('/https?:\/\/(?:[a-z0-9_-]+\.)?(?:wikimedia|wikipedia)\.org\/wiki\/(?:File|Archivo):/i', $url);
    }

    /**
     * Consulta la API de Wikimedia Commons para obtener la URL directa y metadatos del archivo.
     *
     * @return array{success: bool, download_url: ?string, credit: ?string, error: ?string}
     */
    public function resolveFromWikimediaCommonsApi(string $pageUrl): array
    {
        if (! preg_match('/(?:File|Archivo):([^#?]+)/i', $pageUrl, $matches)) {
            return ['success' => false, 'download_url' => null, 'credit' => null, 'error' => 'No se pudo extraer el título del archivo'];
        }

        $fileTitle = 'File:' . urldecode($matches[1]);
        $apiUrl = 'https://commons.wikimedia.org/w/api.php?action=query&titles=' . urlencode($fileTitle) . '&prop=imageinfo&iiprop=url|size|mime|extmetadata&format=json';

        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(12)
                ->get($apiUrl);

            if (! $response->successful()) {
                return ['success' => false, 'download_url' => null, 'credit' => null, 'error' => 'HTTP ' . $response->status() . ' desde Wikimedia API'];
            }

            $data = $response->json();
            $pages = $data['query']['pages'] ?? [];
            if (empty($pages)) {
                return ['success' => false, 'download_url' => null, 'credit' => null, 'error' => 'Respuesta vacía de Wikimedia API'];
            }

            $page = reset($pages);
            if (isset($page['missing'])) {
                return ['success' => false, 'download_url' => null, 'credit' => null, 'error' => "El archivo '{$fileTitle}' no existe en Wikimedia Commons"];
            }

            $imageInfo = $page['imageinfo'][0] ?? null;
            if (! $imageInfo || empty($imageInfo['url'])) {
                return ['success' => false, 'download_url' => null, 'credit' => null, 'error' => 'No se encontró URL directa en imageinfo'];
            }

            $directUrl = (string) $imageInfo['url'];
            $meta = $imageInfo['extmetadata'] ?? [];

            $artistRaw = (string) ($meta['Artist']['value'] ?? '');
            $artistClean = trim(strip_tags($artistRaw)) ?: 'Desconocido';

            $license = (string) ($meta['LicenseShortName']['value'] ?? ($meta['License']['value'] ?? ''));
            $licenseClean = trim(strip_tags($license));

            $credit = "Foto: {$artistClean} / Wikimedia Commons" . ($licenseClean !== '' ? " ({$licenseClean})" : "");

            return [
                'success'      => true,
                'download_url' => $directUrl,
                'credit'       => $credit,
                'error'        => null,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'download_url' => null, 'credit' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Valida que la URL sea remota, legítima y no apunte a direcciones internas (anti-SSRF).
     *
     * @return array{valid: bool, error: ?string}
     */
    public function validateSafeRemoteUrl(string $url): array
    {
        $parsed = parse_url($url);
        $scheme = strtolower($parsed['scheme'] ?? '');

        if ($scheme !== 'http' && $scheme !== 'https') {
            return ['valid' => false, 'error' => "Esquema no permitido: '{$scheme}'"];
        }

        $host = $parsed['host'] ?? '';
        if ($host === '') {
            return ['valid' => false, 'error' => 'Host ausente'];
        }

        $cleanHost = trim($host, '[]');
        if ($cleanHost === 'localhost' || $cleanHost === '127.0.0.1' || $cleanHost === '::1') {
            return ['valid' => false, 'error' => "Dirección loopback rechazada: {$cleanHost}"];
        }

        $ip = gethostbyname($cleanHost);
        if ($ip !== $cleanHost && filter_var($ip, FILTER_VALIDATE_IP)) {
            $isPublic = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if (! $isPublic) {
                return ['valid' => false, 'error' => "La dirección IP resuelta ({$ip}) pertenece a una red privada o restringida (SSRF)"];
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Descarga de forma segura el archivo remoto respetando límites de tamaño y redirecciones.
     *
     * @return array{success: bool, temp_path: ?string, error: ?string}
     */
    public function downloadToTempFile(string $url): array
    {
        $tempDir = storage_path('app/temp');
        if (! file_exists($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        $tempPath = $tempDir . '/dv_img_' . Str::uuid()->toString() . '.tmp';

        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(20)
                ->sink($tempPath)
                ->get($url);

            if (! $response->successful()) {
                @unlink($tempPath);
                return ['success' => false, 'temp_path' => null, 'error' => "HTTP {$response->status()}"];
            }

            if (! file_exists($tempPath)) {
                return ['success' => false, 'temp_path' => null, 'error' => 'No se generó fichero temporal de descarga'];
            }

            $fileSize = filesize($tempPath);
            if ($fileSize > self::MAX_SIZE_BYTES) {
                @unlink($tempPath);
                return ['success' => false, 'temp_path' => null, 'error' => "Archivo excede el límite de 12 MB ({$fileSize} bytes)"];
            }

            if ($fileSize < self::MIN_SIZE_BYTES) {
                @unlink($tempPath);
                return ['success' => false, 'temp_path' => null, 'error' => "Archivo demasiado pequeño o vacío ({$fileSize} bytes)"];
            }

            return ['success' => true, 'temp_path' => $tempPath, 'error' => null];
        } catch (\Throwable $e) {
            @unlink($tempPath);
            return ['success' => false, 'temp_path' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Valida el formato MIME real, optimiza dimensiones y guarda la imagen en formato JPEG persistente.
     *
     * @return array{success: bool, relative_path: ?string, error: ?string}
     */
    public function optimizeAndStoreImage(string $tempPath, string $elementId, string $slug): array
    {
        $imageInfo = @getimagesize($tempPath);
        if (! $imageInfo) {
            return ['success' => false, 'relative_path' => null, 'error' => 'El archivo descargado no es una imagen válida'];
        }

        $mime = $imageInfo['mime'] ?? '';
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (! in_array($mime, $allowedMimes, true)) {
            return ['success' => false, 'relative_path' => null, 'error' => "Formato de imagen no soportado: '{$mime}'. Se requiere JPEG, PNG o WebP"];
        }

        // Crear recurso GD según MIME
        $srcImage = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tempPath),
            'image/png'  => @imagecreatefrompng($tempPath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tempPath) : null,
            default      => null,
        };

        if (! $srcImage) {
            return ['success' => false, 'relative_path' => null, 'error' => 'No se pudo decodificar la imagen con GD'];
        }

        $origWidth = imagesx($srcImage);
        $origHeight = imagesy($srcImage);

        // Escalar proporcionalmente a un ancho máximo de 1200px
        $maxWidth = 1200;
        if ($origWidth > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int) round(($origHeight * $maxWidth) / $origWidth);
            $dstImage = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
            imagedestroy($srcImage);
            $finalImage = $dstImage;
        } else {
            $finalImage = $srcImage;
        }

        // Directorio de destino persistente
        $coversDir = storage_path('app/public/posts/covers');
        if (! file_exists($coversDir)) {
            @mkdir($coversDir, 0755, true);
        }

        $cleanElemCode = Str::lower(substr($elementId, -3));
        $cleanSlugShort = Str::slug(Str::limit($slug, 35, ''));
        $fileName = "post-dv-{$cleanElemCode}-{$cleanSlugShort}.jpg";
        $finalStoragePath = "{$coversDir}/{$fileName}";

        // Guardar JPEG optimizado con calidad 85
        $saved = imagejpeg($finalImage, $finalStoragePath, 85);
        imagedestroy($finalImage);

        if (! $saved || ! file_exists($finalStoragePath)) {
            return ['success' => false, 'relative_path' => null, 'error' => 'No se pudo persistir el archivo optimizado en disco'];
        }

        // Si existe el directorio público (Hostinger), replicar archivo para disponibilidad inmediata
        $publicHtmlCovers = base_path('../public_html/storage/posts/covers');
        if (file_exists(base_path('../public_html/storage')) && ! file_exists($publicHtmlCovers)) {
            @mkdir($publicHtmlCovers, 0755, true);
        }
        if (file_exists($publicHtmlCovers)) {
            @copy($finalStoragePath, "{$publicHtmlCovers}/{$fileName}");
        }

        $relativePath = "storage/posts/covers/{$fileName}";

        return [
            'success'       => true,
            'relative_path' => $relativePath,
            'error'         => null,
        ];
    }
}
