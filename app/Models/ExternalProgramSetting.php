<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ExternalProgramSetting extends Model
{
    protected $table = 'external_program_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        if (! Schema::hasTable('external_program_settings')) {
            return $default;
        }

        $row = static::query()->find($key);
        if (! $row || $row->value === null || trim((string) $row->value) === '') {
            return $default;
        }

        return trim((string) $row->value);
    }

    public static function setValue(string $key, ?string $value): void
    {
        if (! Schema::hasTable('external_program_settings')) {
            return;
        }

        $clean = $value !== null ? trim($value) : null;
        if ($clean === '') {
            $clean = null;
        }

        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $clean]
        );
    }
}
