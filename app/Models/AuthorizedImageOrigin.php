<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorizedImageOrigin extends Model
{
    use Auditable;

    public const SCOPE_MANUAL_EDITORIAL = 'manual_editorial';
    public const SCOPE_ALL = 'all';

    protected $fillable = [
        'label',
        'host',
        'path_prefix',
        'require_https',
        'is_active',
        'scope',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'require_https' => 'boolean',
            'is_active'     => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForContext(Builder $query, string $context): Builder
    {
        if ($context === self::SCOPE_MANUAL_EDITORIAL) {
            return $query->whereIn('scope', [self::SCOPE_MANUAL_EDITORIAL, self::SCOPE_ALL]);
        }

        return $query->where('scope', self::SCOPE_ALL);
    }

    /**
     * Normaliza un host (minúsculas, sin esquema ni barras).
     */
    public static function normalizeHost(string $host): string
    {
        $host = trim(strtolower($host));
        if (str_contains($host, '://')) {
            $parsed = parse_url($host, PHP_URL_HOST);
            $host = is_string($parsed) ? strtolower($parsed) : '';
        }

        return trim($host, "/ \t\n\r\0\x0B");
    }

    /**
     * Normaliza un prefijo de ruta (siempre empieza por '/' y termina en '/' si no es vacío).
     */
    public static function normalizePathPrefix(?string $prefix): ?string
    {
        $prefix = trim((string) $prefix);
        if ($prefix === '' || $prefix === '/') {
            return null;
        }

        $prefix = '/' . ltrim($prefix, '/');
        if (! str_ends_with($prefix, '/')) {
            $prefix .= '/';
        }

        return $prefix;
    }

    /**
     * Comprueba si una URL parseada coincide con esta regla de origen autorizado.
     * Exige coincidencia exacta de host, esquema HTTPS si require_https=true,
     * y prefijo de ruta si path_prefix está definido.
     */
    public function matchesUrl(string $scheme, string $host, string $path): bool
    {
        $normalizedHost = strtolower(trim($host));
        if ($normalizedHost === '' || $normalizedHost !== strtolower($this->host)) {
            return false;
        }

        if ($this->require_https && strtolower($scheme) !== 'https') {
            return false;
        }

        $prefix = self::normalizePathPrefix($this->path_prefix);
        if ($prefix !== null) {
            $normalizedPath = '/' . ltrim($path, '/');
            // Evitar evasión por segmentos '..' o barras duplicadas
            if (str_contains($normalizedPath, '..') || str_contains($normalizedPath, '//')) {
                return false;
            }

            return str_starts_with($normalizedPath, $prefix);
        }

        return true;
    }
}

