<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
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

    public function getCoverUrlAttribute(): string
    {
        if ($resolved = PublicMediaUrl::normalizePublicUrl($this->cover_image)) {
            return $resolved;
        }

        return $this->cover_image ? asset($this->cover_image) : '';
    }
}
