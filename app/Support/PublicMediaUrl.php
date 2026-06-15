<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

class PublicMediaUrl
{
    public static function normalize(mixed $value): ?string
    {
        if (is_array($value)) {
            if (array_key_exists('disk', $value) && array_key_exists('key', $value)) {
                $disk = trim((string) $value['disk']);
                $key = trim((string) $value['key']);

                if ($disk !== '' && $key !== '') {
                    $resolved = self::resolveStoredFileUrl($key, $disk);
                    if ($resolved !== null) {
                        return $resolved;
                    }
                }
            }

            if (array_key_exists('disk', $value) && array_key_exists('path', $value)) {
                $disk = trim((string) $value['disk']);
                $key = trim((string) $value['path']);

                if ($disk !== '' && $key !== '') {
                    $resolved = self::resolveStoredFileUrl($key, $disk);
                    if ($resolved !== null) {
                        return $resolved;
                    }
                }
            }

            foreach (['url', 'src', 'image', 'path', 'file', 'asset', 'thumbnail', 'poster'] as $key) {
                if (array_key_exists($key, $value)) {
                    $resolved = self::normalize($value[$key]);
                    if ($resolved !== null) {
                        return $resolved;
                    }
                }
            }

            foreach (['content', 'media', 'images', 'items', 'children', 'blocks'] as $key) {
                if (array_key_exists($key, $value)) {
                    $resolved = self::normalize($value[$key]);
                    if ($resolved !== null) {
                        return $resolved;
                    }
                }
            }

            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim(str_replace('\\', '/', $value));
        if ($value === '') {
            return null;
        }

        $isUrl = false;
        try {
            $isUrl = filter_var($value, FILTER_VALIDATE_URL)
                || str_starts_with(strtolower($value), 'http://')
                || str_starts_with(strtolower($value), 'https://');
        } catch (\Throwable) {
        }

        if ($isUrl) {
            $normalizedUrl = str_replace(' ', '%20', $value);

            $b2Url = trim((string) config('filesystems.disks.backblaze.url', ''));
            $customResolves = self::customB2UrlResolves();
            $bucketName = trim((string) config('filesystems.disks.backblaze.bucket_name', ''));
            if ($bucketName === '') {
                $bucketName = '7RR-DATOS';
            }

            if ($b2Url !== '' && $customResolves) {
                $normalizedUrl = preg_replace(
                    '~https?://[a-z0-9]+\.backblazeb2\.com/file/[^/]+/~i',
                    rtrim($b2Url, '/') . '/',
                    $normalizedUrl
                ) ?? $normalizedUrl;
            } else {
                if ($b2Url !== '') {
                    $customHost = parse_url($b2Url, PHP_URL_HOST);
                    if ($customHost) {
                        $normalizedUrl = preg_replace(
                            '~https?://' . preg_quote($customHost, '~') . '/file/[^/]+/~i',
                            'https://f003.backblazeb2.com/file/' . $bucketName . '/',
                            $normalizedUrl
                        ) ?? $normalizedUrl;
                    }
                }

                $normalizedUrl = preg_replace(
                    '~https?://media\.sevenrockradio\.com/file/[^/]+/~i',
                    'https://f003.backblazeb2.com/file/' . $bucketName . '/',
                    $normalizedUrl
                ) ?? $normalizedUrl;
            }

            // Fast path: convert WordPress upload URLs to local legacy-wp-uploads
            // without scanning the filesystem (avoids slow RecursiveDirectoryIterator)
            $relative = self::extractLegacyWordPressUploadRelativePath($normalizedUrl);
            if ($relative !== null) {
                if (Route::has("legacy-wp-uploads.show")) {
                    return route("legacy-wp-uploads.show", ["path" => $relative]);
                }
            }

            // Adapt localhost/127.0.0.1 assets to the current HTTP host/port or config('app.url')
            try {
                $currentHost = request()->getSchemeAndHttpHost();
                $configUrl = config('app.url');
                if ($configUrl && !str_contains($configUrl, 'localhost') && !str_contains($configUrl, '127.0.0.1')) {
                    $currentHost = $configUrl;
                }
                $parsed = parse_url($normalizedUrl);
                if (isset($parsed['host']) && ($parsed['host'] === 'localhost' || $parsed['host'] === '127.0.0.1')) {
                    $path = $parsed['path'] ?? '';
                    $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
                    return rtrim($currentHost, '/') . '/' . ltrim($path, '/') . $query;
                }
            } catch (\Throwable) {
                // Fallback to original value
            }

            return $normalizedUrl;
        }

        if (str_starts_with($value, '//')) {
            return $value;
        }

        $relative = ltrim($value, '/');

        if (str_starts_with($relative, 'public/')) {
            $relative = substr($relative, 7);
        }

        $candidates = [
            $relative,
            'public/' . $relative,
            'storage/' . ltrim($relative, '/'),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && self::localAssetExists($candidate)) {
                return self::assetUrl($candidate);
            }

            $storageCandidate = ltrim((string) preg_replace('#^(public/|storage/)#', '', $candidate), '/');
            if ($storageCandidate !== '' && Storage::disk('public')->exists($storageCandidate)) {
                return Storage::disk('public')->url($storageCandidate);
            }

            if ($storageCandidate !== '' && self::isCloudflareR2Configured()) {
                try {
                    if (Storage::disk('r2')->exists($storageCandidate)) {
                        return self::getCloudflareR2Url($storageCandidate);
                    }
                } catch (\Throwable) {
                    // Ignore and keep checking other candidates.
                }
            }

            if ($storageCandidate !== '' && self::isBackblazeConfigured()) {
                try {
                    if (Storage::disk('backblaze')->exists($storageCandidate)) {
                        return self::getBackblazeUrl($storageCandidate);
                    }
                } catch (\Throwable) {
                    // Ignore and keep checking other candidates.
                }
            }
        }

        if (str_contains($relative, '/wp-content/uploads/')) {
            return self::resolveLegacyWordPressUploadUrl($relative) ?? self::assetUrl($relative);
        }

        if (preg_match('/\.(?:jpe?g|png|gif|webp|svg|avif|bmp|mp4|webm|mp3|pdf)(?:\?.*)?$/i', $relative)) {
            return self::assetUrl($relative);
        }

        return null;
    }

    public static function normalizePublicUrl(mixed $value): string
    {
        return self::normalize($value) ?? '';
    }

    public static function rewriteLegacyWordPressUploadsInHtml(string $html): string
    {
        if ($html === '' || ! str_contains($html, 'wp-content/uploads')) {
            return $html;
        }

        return preg_replace_callback(
            '/\b(src|href|data-src|poster)=(["\'])([^"\']+)\2/i',
            static function (array $matches): string {
                $resolved = self::normalize($matches[3]);

                if ($resolved === null || $resolved === $matches[3]) {
                    return $matches[0];
                }

                return $matches[1] . '=' . $matches[2] . htmlspecialchars($resolved, ENT_QUOTES | ENT_HTML5) . $matches[2];
            },
            $html
        ) ?? $html;
    }

    private static function localAssetExists(string $relativePath): bool
    {
        $clean = ltrim(str_replace('\\', '/', rawurldecode($relativePath)), '/');
        if ($clean === '') {
            return false;
        }

        return File::exists(public_path($clean)) || File::exists(base_path($clean));
    }

    private static function resolveStoredFileUrl(string $key, string $disk): ?string
    {
        $key = ltrim(str_replace('\\', '/', trim($key)), '/');
        if ($key === '') {
            return null;
        }

        $disk = strtolower(trim($disk));
        if ($disk === 'backblaze-b2') {
            $disk = 'backblaze';
        }

        try {
            if ($disk === 'r2' && self::isCloudflareR2Configured()) {
                if (Storage::disk('r2')->exists($key)) {
                    return self::getCloudflareR2Url($key);
                }

                if (Storage::disk('public')->exists($key)) {
                    return Storage::disk('public')->url($key);
                }
            }

            if ($disk === 'backblaze' && self::isBackblazeConfigured()) {
                if (Storage::disk('backblaze')->exists($key)) {
                    return self::getBackblazeUrl($key);
                }

                if (Storage::disk('public')->exists($key)) {
                    return Storage::disk('public')->url($key);
                }
            }

            if (Storage::disk('public')->exists($key)) {
                return Storage::disk('public')->url($key);
            }

            if ($disk !== 'public' && self::isCloudflareR2Configured() && Storage::disk('r2')->exists($key)) {
                return self::getCloudflareR2Url($key);
            }

            if ($disk !== 'public' && self::isBackblazeConfigured() && Storage::disk('backblaze')->exists($key)) {
                return self::getBackblazeUrl($key);
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private static function isBackblazeConfigured(): bool
    {
        return trim((string) config('filesystems.disks.backblaze.account_id', '')) !== ''
            && trim((string) config('filesystems.disks.backblaze.application_key', '')) !== ''
            && trim((string) config('filesystems.disks.backblaze.bucket_id', '')) !== ''
            && trim((string) config('filesystems.disks.backblaze.bucket_name', '')) !== ''
            && trim((string) config('filesystems.disks.backblaze.url', '')) !== '';
    }

    private static function isCloudflareR2Configured(): bool
    {
        return trim((string) config('filesystems.disks.r2.key', '')) !== ''
            && trim((string) config('filesystems.disks.r2.secret', '')) !== ''
            && trim((string) config('filesystems.disks.r2.bucket', '')) !== ''
            && trim((string) config('filesystems.disks.r2.endpoint', '')) !== '';
    }

    private static function resolveLegacyWordPressUploadUrl(string $value): ?string
    {
        $relative = self::extractLegacyWordPressUploadRelativePath($value);

        if ($relative === null) {
            return null;
        }

        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if ($relative === '') {
            return null;
        }

        $configuredUrl = trim((string) config('media.legacy_wp_uploads_url', ''));
        foreach (self::legacyWordPressUploadRoots() as $basePath) {
            $filesystemPath = $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (File::exists($filesystemPath)) {
                $legacyRelative = self::relativeLegacyWordPressUploadPath($filesystemPath) ?? $relative;
                return self::buildLegacyWordPressUploadUrl($legacyRelative, $configuredUrl);
            }

            $foundPath = self::findLegacyWordPressUploadByBasename($basePath, $relative);
            if ($foundPath === null) {
                continue;
            }

            $legacyRelative = self::relativeLegacyWordPressUploadPath($foundPath) ?? $relative;

            return self::buildLegacyWordPressUploadUrl($legacyRelative, $configuredUrl);
        }

        $publicRelative = 'wp-content/uploads/' . $relative;

        if (self::localAssetExists($publicRelative)) {
            return self::assetUrl($publicRelative);
        }

        if ($configuredUrl !== '') {
            return rtrim($configuredUrl, '/') . '/' . $relative;
        }

        return null;
    }

    private static function relativeLegacyWordPressUploadPath(string $filesystemPath): ?string
    {
        $filesystemPath = str_replace('\\', '/', $filesystemPath);
        $candidateRoots = self::legacyWordPressUploadRoots();

        foreach ($candidateRoots as $configuredPath) {
            $basePath = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configuredPath), DIRECTORY_SEPARATOR);
            if ($basePath === '') {
                continue;
            }

            $base = str_replace('\\', '/', $basePath);
            if (str_starts_with($filesystemPath, $base . '/')) {
                return ltrim(substr($filesystemPath, strlen($base)), '/');
            }
        }

        return null;
    }

    public static function resolveLegacyWordPressUploadFilesystemPath(string $value): ?string
    {
        $relative = self::extractLegacyWordPressUploadRelativePath($value);
        if ($relative === null) {
            $relative = trim(str_replace('\\', '/', $value));
        }

        $relative = ltrim($relative, '/');
        if ($relative === '') {
            return null;
        }

        $candidateRoots = self::legacyWordPressUploadRoots();

        foreach ($candidateRoots as $configuredPath) {
            $basePath = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configuredPath), DIRECTORY_SEPARATOR);
            if ($basePath === '') {
                continue;
            }

            $filesystemPath = $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (File::exists($filesystemPath)) {
                return $filesystemPath;
            }

            $foundPath = self::findLegacyWordPressUploadByBasename($basePath, $relative);
            if ($foundPath === null) {
                continue;
            }

            if (File::exists($foundPath)) {
                return $foundPath;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private static function legacyWordPressUploadRoots(): array
    {
        $roots = [
            trim((string) config('media.legacy_wp_uploads_path', '')),
            public_path('wp-content/uploads'),
            realpath(public_path('wp-content/uploads')) ?: null,
            dirname(dirname(dirname(base_path()))) . '/shared/sevenrock-assets/public/wp-content/uploads',
            base_path('../shared/sevenrock-assets/public/wp-content/uploads'),
            base_path('../../shared/sevenrock-assets/public/wp-content/uploads'),
            '/shared/sevenrock-assets/public/wp-content/uploads',
        ];

        $normalized = [];

        foreach ($roots as $root) {
            $root = self::normalizeFilesystemRoot($root);
            if ($root !== null) {
                $normalized[] = $root;
            }
        }

        return array_values(array_unique($normalized));
    }

    private static function normalizeFilesystemRoot(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    private static function buildLegacyWordPressUploadUrl(string $legacyRelative, string $configuredUrl): ?string
    {
        $legacyRelative = ltrim(str_replace('\\', '/', $legacyRelative), '/');
        if ($legacyRelative === '') {
            return null;
        }

        if ($configuredUrl !== '') {
            return rtrim($configuredUrl, '/') . '/' . $legacyRelative;
        }

        if (Route::has('legacy-wp-uploads.show')) {
            return route('legacy-wp-uploads.show', ['path' => $legacyRelative]);
        }

        $publicRelative = 'wp-content/uploads/' . $legacyRelative;

        return self::localAssetExists($publicRelative) ? self::assetUrl($publicRelative) : null;
    }

    private static function assetUrl(string $path): string
    {
        $parts = explode('/', str_replace('\\', '/', $path));
        $encodedParts = array_map(fn($part) => rawurlencode(rawurldecode($part)), $parts);
        return asset(implode('/', $encodedParts));
    }

    private static function getBackblazeUrl(string $key): string
    {
        $b2Url = trim((string) config('filesystems.disks.backblaze.url', ''));
        if ($b2Url !== '') {
            return rtrim($b2Url, '/') . '/' . ltrim($key, '/');
        }

        try {
            return Storage::disk('backblaze')->url($key);
        } catch (\Throwable) {
            return '';
        }
    }

    private static function getCloudflareR2Url(string $key): string
    {
        $r2Url = trim((string) config('filesystems.disks.r2.url', ''));
        if ($r2Url !== '') {
            return rtrim($r2Url, '/') . '/' . ltrim($key, '/');
        }

        try {
            return Storage::disk('r2')->url($key);
        } catch (\Throwable) {
            return '';
        }
    }

    private static function findLegacyWordPressUploadByBasename(string $basePath, string $relative): ?string
    {
        $basename = basename($relative);
        if ($basename === '') {
            return null;
        }

        $stem = pathinfo($basename, PATHINFO_FILENAME);
        $extension = pathinfo($basename, PATHINFO_EXTENSION);
        $candidates = array_values(array_unique(array_filter([
            $basename,
            self::stripWordPressThumbnailSuffix($basename),
            $stem . ($extension !== '' ? '.' . $extension : ''),
            $stem,
        ])));

        $candidateLower = array_map(static fn (string $name): string => mb_strtolower($name), $candidates);
        $stemLower = mb_strtolower($stem);

        if ($candidates === []) {
            return null;
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $filename = $file->getFilename();
                $filenameLower = mb_strtolower($filename);

                if (! in_array($filenameLower, $candidateLower, true)) {
                    if ($stemLower === '') {
                        continue;
                    }

                    if (! str_starts_with($filenameLower, $stemLower)) {
                        continue;
                    }
                }

                return $file->getPathname();
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private static function stripWordPressThumbnailSuffix(string $filename): string
    {
        return (string) preg_replace('/-\d+x\d+(?=\.[a-z0-9]+$)/i', '', $filename);
    }

    private static function extractLegacyWordPressUploadRelativePath(string $value): ?string
    {
        $value = trim(str_replace('\\', '/', $value));
        if ($value === '') {
            return null;
        }

        $path = $value;

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $path = (string) (parse_url($value, PHP_URL_PATH) ?? '');
        }

        $needle = '/wp-content/uploads/';
        $normalizedPath = '/' . ltrim($path, '/');
        $position = strpos($normalizedPath, $needle);

        if ($position === false) {
            return null;
        }

        return substr($normalizedPath, $position + strlen($needle));
    }

    private static function customB2UrlResolves(): bool
    {
        $b2Url = trim((string) config('filesystems.disks.backblaze.url', ''));
        if ($b2Url === '') {
            return false;
        }

        $host = parse_url($b2Url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }

        $forced = config('filesystems.disks.backblaze.custom_url_resolves');
        if ($forced !== null) {
            return (bool) $forced;
        }

        try {
            return (bool) cache()->remember('b2_custom_url_resolves_' . md5($host), 3600, function () use ($host) {
                return @gethostbyname($host) !== $host;
            });
        } catch (\Throwable) {
            return @gethostbyname($host) !== $host;
        }
    }
}
