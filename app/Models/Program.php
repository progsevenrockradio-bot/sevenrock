<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\MasterProgram;
use App\Support\PublicMediaUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Program extends Model
{
    use Auditable;

    /**
     * The legacy schema stores schedule rows in radio_programs.
     * Keep the model aligned with the actual table used by the app.
     */
    protected $table = 'radio_programs';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'host',
        'schedule',
        'cover_image',
        'social_links',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'is_active' => 'bool',
            'sort_order' => 'int',
        ];
    }

    public function songs(): HasMany
    {
        return $this->hasMany(Song::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        if (! Schema::hasColumn($query->getModel()->getTable(), 'is_active')) {
            return $query;
        }

        return $query->where('is_active', true);
    }

    public function scopeLatestEditorial(Builder $query): Builder
    {
        return $query->latest();
    }

    public function getCoverUrlAttribute(): string
    {
        if ($resolved = PublicMediaUrl::normalizePublicUrl($this->cover_image)) {
            return $resolved;
        }

        return $this->cover_image ? asset($this->cover_image) : '';
    }

    public function getNameAttribute(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value !== '') {
            return $value;
        }

        return trim((string) ($this->attributes['titulo_programa'] ?? ''));
    }

    public function getDescriptionAttribute(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value !== '') {
            return $value;
        }

        $legacy = trim((string) ($this->attributes['resena'] ?? ''));
        if ($legacy !== '') {
            return $legacy;
        }

        $legacy = trim((string) ($this->attributes['informacion_fija_programa'] ?? ''));

        return $legacy !== '' ? $legacy : null;
    }

    public function getHostAttribute(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value !== '') {
            return $value;
        }

        $legacy = trim((string) ($this->attributes['conductor'] ?? ''));

        return $legacy !== '' ? $legacy : null;
    }

    public function getScheduleAttribute(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value !== '') {
            return $value;
        }

        $parts = array_filter([
            trim((string) ($this->attributes['dia_transmision'] ?? '')),
            trim((string) ($this->attributes['hora_inicio'] ?? '')),
            trim((string) ($this->attributes['hora_fin'] ?? '')),
        ]);

        if ($parts === []) {
            return null;
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        $day = array_shift($parts);

        return trim($day . ' ' . implode(' - ', $parts));
    }

    public function getSlugAttribute(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value !== '') {
            return $value;
        }

        $label = $this->getNameAttribute(null);

        return $label !== '' ? \Illuminate\Support\Str::slug($label) : null;
    }

    public function getCoverImageAttribute(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value !== '') {
            return $value;
        }

        $legacy = trim((string) ($this->attributes['caratula_programa'] ?? ''));

        return $legacy !== '' ? $legacy : null;
    }

    public function getSortOrderAttribute(mixed $value): int
    {
        if ($value !== null && $value !== '') {
            return (int) $value;
        }

        if (isset($this->attributes['numero_episodio']) && $this->attributes['numero_episodio'] !== '') {
            return (int) $this->attributes['numero_episodio'];
        }

        return 0;
    }

    /**
     * Hora de transmisión formateada (HH:MM).
     */
    public function getScheduleTimeAttribute(): string
    {
        $master = MasterProgram::query()->find($this->master_program_id);
        $time = trim((string) ($this->hora_transmision ?: $master?->hora_transmision ?? ''));

        if ($time === '') {
            return '';
        }

        // Formatear: "17:00:00" → "17:00"
        if (preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return $time;
    }
}
