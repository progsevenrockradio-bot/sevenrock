<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use App\Models\ThemeSetting;
use App\Models\Post;
use App\Models\Album;
use App\Models\Product;
use App\Models\GalleryImage;
use App\Support\PublicMediaUrl;
use App\Services\FileUploadService;
use Throwable;

class MediaDiagnosticController extends Controller
{
    public function show(Request $request)
    {
        // 1. Security Token Check
        if ($request->query('token') !== 'sevenrock_audit_2026') {
            abort(403, 'Acceso denegado: Token de auditoría no válido.');
        }

        // 2. Gather environment details
        $env = [
            'PHP Version' => PHP_VERSION,
            'Laravel Version' => app()->version(),
            'APP_ENV' => config('app.env'),
            'APP_DEBUG' => config('app.debug') ? 'true' : 'false',
            'APP_URL' => config('app.url'),
            'cURL Extension' => extension_loaded('curl') ? 'Habilitado' : 'Deshabilitado',
            'OpenSSL Extension' => extension_loaded('openssl') ? 'Habilitado' : 'Deshabilitado',
            'FileInfo Extension' => extension_loaded('fileinfo') ? 'Habilitado' : 'Deshabilitado',
            'GD Extension' => extension_loaded('gd') ? 'Habilitado' : 'Deshabilitado',
        ];

        // 3. Gather Backblaze Config
        $b2ConfigRaw = config('filesystems.disks.backblaze') ?? [];
        $b2Config = [
            'account_id' => $this->maskString($b2ConfigRaw['account_id'] ?? ''),
            'application_key' => $this->maskString($b2ConfigRaw['application_key'] ?? ''),
            'bucket_id' => $this->maskString($b2ConfigRaw['bucket_id'] ?? ''),
            'bucket_name' => $b2ConfigRaw['bucket_name'] ?? '(Vacío)',
            'url' => $b2ConfigRaw['url'] ?? '(Vacío)',
            'prefix' => $b2ConfigRaw['prefix'] ?? '(Vacío)',
        ];

        $isB2Configured = app(FileUploadService::class)->isB2Configured();

        // 4. DNS Resolution Test
        $dnsTargets = [
            'media.sevenrockradio.com',
            'f003.backblazeb2.com',
            's3.eu-central-003.backblazeb2.com',
        ];
        
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        if ($appHost) {
            $dnsTargets[] = $appHost;
        }

        $dnsResults = [];
        foreach ($dnsTargets as $target) {
            $ip = @gethostbyname($target);
            $resolves = ($ip !== $target);
            $dnsResults[$target] = [
                'resolves' => $resolves,
                'ip' => $resolves ? $ip : 'Fallo de resolución',
            ];
        }

        // 5. Storage Symlink and Folder Auditing
        $storageSymlink = [
            'path' => public_path('storage'),
            'exists' => file_exists(public_path('storage')),
            'is_link' => is_link(public_path('storage')),
            'target' => is_link(public_path('storage')) ? @readlink(public_path('storage')) : null,
            'points_to_correct_target' => false,
            'is_readable' => @is_readable(public_path('storage')),
            'is_writable' => @is_writable(public_path('storage')),
        ];

        if ($storageSymlink['is_link']) {
            $expectedTarget = storage_path('app/public');
            $storageSymlink['points_to_correct_target'] = (
                realpath($storageSymlink['target']) === realpath($expectedTarget) ||
                $storageSymlink['target'] === $expectedTarget ||
                $storageSymlink['target'] === '../storage/app/public'
            );
        }

        $folders = [
            'storage/app/public' => storage_path('app/public'),
            'storage/app/public/theme' => storage_path('app/public/theme'),
            'storage/app/public/catalog' => storage_path('app/public/catalog'),
        ];
        $folderResults = [];
        foreach ($folders as $name => $path) {
            $exists = file_exists($path);
            $folderResults[$name] = [
                'exists' => $exists,
                'is_writable' => $exists ? @is_writable($path) : false,
                'is_readable' => $exists ? @is_readable($path) : false,
                'permissions' => $exists ? substr(sprintf('%o', fileperms($path)), -4) : 'N/A',
            ];
        }

        // 6. Direct Backblaze Connection Check
        $b2Connection = [
            'connected' => false,
            'message' => 'No se intentó (Backblaze no está configurado).',
            'api_url' => null,
        ];

        if ($isB2Configured) {
            try {
                $client = new \Zaxbux\BackblazeB2\Client([
                    'applicationKeyId' => (string) ($b2ConfigRaw['account_id'] ?? ''),
                    'applicationKey' => (string) ($b2ConfigRaw['application_key'] ?? ''),
                ]);
                $client->refreshAccountAuthorization();
                $auth = $client->accountAuthorization();
                
                $b2Connection['connected'] = true;
                $b2Connection['message'] = 'Conexión y autorización exitosas.';
                $b2Connection['api_url'] = $auth ? $auth->apiUrl() : 'N/A';
            } catch (Throwable $e) {
                $b2Connection['connected'] = false;
                $b2Connection['message'] = 'Fallo al conectar a B2: ' . $e->getMessage();
            }
        }

        // 7. Audit DB Media Records
        $themeSetting = null;
        $dbConnected = true;
        $dbMessage = 'Conectado con éxito.';
        $mediaAudit = [];

        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
            $themeSetting = ThemeSetting::current();
        } catch (\Throwable $e) {
            $dbConnected = false;
            $dbMessage = 'Fallo de conexión a la Base de Datos: ' . $e->getMessage();
        }

        if ($dbConnected) {
            if ($themeSetting) {
                $mediaColumns = [
                    'logo_path' => 'Logo',
                    'background_path' => 'Fondo',
                    'hero_slide_primary_path' => 'Hero Slide Primario',
                    'hero_slide_secondary_path' => 'Hero Slide Secundario',
                    'home_album_cover_path' => 'Portada Álbum Home',
                    'home_video_image_path' => 'Portada Video Home',
                    'hero_video_path' => 'Video Hero',
                    'email_default_cover_path' => 'Portada Email por Defecto',
                ];

                foreach ($mediaColumns as $col => $label) {
                    $dbVal = $themeSetting->{$col};
                    if ($dbVal) {
                        $mediaAudit[] = $this->auditMediaItem('ThemeSetting', $label, $dbVal, $b2ConfigRaw);
                    }
                }
            }

            // Add last 5 Posts
            try {
                $posts = Post::query()->orderByDesc('id')->take(5)->get();
                foreach ($posts as $post) {
                    $val = $post->featured_image ?? $post->featured_image_path ?? null;
                    if ($val) {
                        $mediaAudit[] = $this->auditMediaItem("Post (ID: {$post->id})", $post->title, $val, $b2ConfigRaw);
                    }
                }
            } catch (\Throwable $e) {}

            // Add last 5 Albums
            try {
                $albums = Album::query()->orderByDesc('id')->take(5)->get();
                foreach ($albums as $album) {
                    $val = $album->cover_image_path ?? null;
                    if ($val) {
                        $mediaAudit[] = $this->auditMediaItem("Album (ID: {$album->id})", $album->title, $val, $b2ConfigRaw);
                    }
                }
            } catch (\Throwable $e) {}

            // Add last 5 Products
            try {
                $products = Product::query()->orderByDesc('id')->take(5)->get();
                foreach ($products as $product) {
                    $val = $product->image ?? null;
                    if ($val) {
                        $mediaAudit[] = $this->auditMediaItem("Product (ID: {$product->id})", $product->title, $val, $b2ConfigRaw);
                    }
                }
            } catch (\Throwable $e) {}

            // Add last 5 Gallery Images
            try {
                $galleryImages = GalleryImage::query()->orderByDesc('id')->take(5)->get();
                foreach ($galleryImages as $gi) {
                    $val = $gi->image_path ?? null;
                    if ($val) {
                        $mediaAudit[] = $this->auditMediaItem("GalleryImage (ID: {$gi->id})", $gi->caption ?? 'Imagen', $val, $b2ConfigRaw);
                    }
                }
            } catch (\Throwable $e) {}
        }

        // 8. Generate HTML report view
        return view('admin.diagnose-media-report', [
            'env' => $env,
            'b2Config' => $b2Config,
            'isB2Configured' => $isB2Configured,
            'dnsResults' => $dnsResults,
            'storageSymlink' => $storageSymlink,
            'folderResults' => $folderResults,
            'b2Connection' => $b2Connection,
            'mediaAudit' => $mediaAudit,
            'dbConnected' => $dbConnected,
            'dbMessage' => $dbMessage,
        ]);
    }

    private function maskString(?string $str): string
    {
        $str = trim((string) $str);
        if ($str === '') {
            return '(Vacío)';
        }
        $len = strlen($str);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        return substr($str, 0, 2) . str_repeat('*', $len - 4) . substr($str, -2);
    }

    private function auditMediaItem(string $entity, string $label, string $dbValue, array $b2Config): array
    {
        $normalizedUrl = PublicMediaUrl::normalizePublicUrl($dbValue);

        // Check if value is a full URL or relative
        $isUrl = filter_var($dbValue, FILTER_VALIDATE_URL) || str_starts_with(strtolower($dbValue), 'http://') || str_starts_with(strtolower($dbValue), 'https://');

        // Extract key from URL or database value
        $key = $dbValue;
        if ($isUrl) {
            $parsed = parse_url($dbValue, PHP_URL_PATH);
            if ($parsed) {
                // If it's a B2 URL: /file/{bucket_name}/{key}
                $bucketName = $b2Config['bucket_name'] ?? '7RR-DATOS';
                $prefix = "/file/{$bucketName}/";
                if (str_contains($parsed, $prefix)) {
                    $key = substr($parsed, strpos($parsed, $prefix) + strlen($prefix));
                } else {
                    // Fallback to removing storage prefix if present
                    $key = ltrim($parsed, '/');
                    if (str_starts_with($key, 'storage/')) {
                        $key = substr($key, 8);
                    }
                }
            }
        }

        $key = trim(str_replace('\\', '/', $key), '/');

        // Check if it exists locally on the public disk
        $localPath = storage_path('app/public/' . $key);
        $localExists = file_exists($localPath);
        
        // Check if it exists in public folder directly (in case it wasn't uploaded via disk public)
        $publicPath = public_path($key);
        $publicExists = file_exists($publicPath);

        // Perform HTTP Check to generated public URL
        $publicUrlStatus = $this->checkUrlAccessibility($normalizedUrl);

        // Perform HTTP Check to direct B2 friendly URL if it's a B2 key/URL
        $b2FriendlyUrl = null;
        $b2FriendlyStatus = 'N/A';
        $b2BucketName = $b2Config['bucket_name'] ?? '7RR-DATOS';
        
        if ($isUrl || $b2Config) {
            $b2FriendlyUrl = "https://f003.backblazeb2.com/file/{$b2BucketName}/{$key}";
            $b2FriendlyStatus = $this->checkUrlAccessibility($b2FriendlyUrl);
        }

        // Perform HTTP Check to S3 endpoint
        $b2S3Url = null;
        $b2S3Status = 'N/A';
        if ($isUrl || $b2Config) {
            $b2S3Url = "https://s3.eu-central-003.backblazeb2.com/{$b2BucketName}/{$key}";
            $b2S3Status = $this->checkUrlAccessibility($b2S3Url);
        }

        return [
            'entity' => $entity,
            'label' => $label,
            'db_value' => $dbValue,
            'key' => $key,
            'normalized_url' => $normalizedUrl,
            'local' => [
                'exists' => $localExists || $publicExists,
                'path' => $localExists ? $localPath : ($publicExists ? $publicPath : null),
                'where' => $localExists ? 'storage/app/public/' . $key : ($publicExists ? 'public/' . $key : 'No encontrado'),
            ],
            'public_url_status' => $publicUrlStatus,
            'b2_friendly_url' => $b2FriendlyUrl,
            'b2_friendly_status' => $b2FriendlyStatus,
            'b2_s3_url' => $b2S3Url,
            'b2_s3_status' => $b2S3Status,
        ];
    }

    private function checkUrlAccessibility(string $url): string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return 'URL Inválida';
        }

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                return "Error: {$err}";
            }

            return (string) $httpCode;
        } catch (Throwable $e) {
            return 'Excepción: ' . $e->getMessage();
        }
    }
}
