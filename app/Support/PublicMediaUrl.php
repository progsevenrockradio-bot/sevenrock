<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class PublicMediaUrl
{
    public static function normalize(mixed $value): ?string
    {
        if (is_array($value)) {
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

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return self::resolveLegacyWordPressUploadUrl($value) ?? $value;
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
                return asset($candidate);
            }

            $storageCandidate = ltrim((string) preg_replace('#^(public/|storage/)#', '', $candidate), '/');
            if ($storageCandidate !== '' && Storage::disk('public')->exists($storageCandidate)) {
                return Storage::disk('public')->url($storageCandidate);
            }
        }

        if (str_contains($relative, '/wp-content/uploads/')) {
            return self::resolveLegacyWordPressUploadUrl($relative) ?? asset($relative);
        }

        if (preg_match('/\.(?:jpe?g|png|gif|webp|svg|avif|bmp|mp4|webm|mp3|pdf)(?:\?.*)?$/i', $relative)) {
            return asset($relative);
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
        $clean = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($clean === '') {
            return false;
        }

        return File::exists(public_path($clean)) || File::exists(base_path($clean));
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

        $configuredPath = trim((string) config('media.legacy_wp_uploads_path', ''));
        $configuredUrl = trim((string) config('media.legacy_wp_uploads_url', ''));

        if ($configuredPath !== '') {
            $filesystemPath = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configuredPath), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (File::exists($filesystemPath)) {
                if ($configuredUrl !== '') {
                    return rtrim($configuredUrl, '/') . '/' . $relative;
                }

                $publicRelative = 'wp-content/uploads/' . $relative;

                if (self::localAssetExists($publicRelative)) {
                    return asset($publicRelative);
                }
            }
        }

        $publicRelative = 'wp-content/uploads/' . $relative;

        if (self::localAssetExists($publicRelative)) {
            return asset($publicRelative);
        }

        if ($configuredUrl !== '') {
            return rtrim($configuredUrl, '/') . '/' . $relative;
        }

        return null;
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
}
