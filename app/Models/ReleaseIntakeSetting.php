<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ReleaseIntakeSetting extends Model
{
    protected $table = 'release_intake_settings';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        if (! Schema::hasTable('release_intake_settings')) {
            return $default;
        }

        $row = static::query()->where('key', $key)->first();
        if (! $row || $row->value === null || trim((string) $row->value) === '') {
            return $default;
        }

        return (string) $row->value;
    }

    public static function setValue(string $key, ?string $value): void
    {
        if (! Schema::hasTable('release_intake_settings')) {
            return;
        }

        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value !== null ? trim($value) : null]
        );
    }

    public static function isEnabled(): bool
    {
        return filter_var(static::getValue('enabled', '0'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function useExternalAi(): bool
    {
        return filter_var(static::getValue('use_external_ai', '0'), FILTER_VALIDATE_BOOLEAN);
    }
}

