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
            return $value;
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
            return asset($relative);
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

    private static function localAssetExists(string $relativePath): bool
    {
        $clean = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($clean === '') {
            return false;
        }

        return File::exists(public_path($clean)) || File::exists(base_path($clean));
    }
}
