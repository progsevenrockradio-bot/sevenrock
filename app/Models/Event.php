<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Event extends Model
{
    use Auditable;

    protected static function booted(): void
    {
        $bumpVersion = static function (): void {
            Cache::forever('cache.version.events', now()->timestamp);
        };

        static::saved($bumpVersion);
        static::deleted($bumpVersion);
    }

    protected $fillable = [
        'title',
        'slug',
        'status',
        'starts_at',
        'ends_at',
        'location',
        'venue',
        'ticket_url',
        'ticket_label',
        'categories',
        'poster',
        'venue_url',
        'facebook_url',
        'embed_url',
        'map_url',
        'content',
        'is_cancelled',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'categories' => 'array',
        'content' => 'array',
        'is_cancelled' => 'boolean',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'categories' => 'array',
            'content' => 'array',
            'is_cancelled' => 'boolean',
        ];
    }

    /**
     * Scope: only events approved for public display.
     * Use this on every public-facing query. Do NOT use as a globalScope
     * because admin panel and pipeline need to see pending events too.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', now()->startOfDay());
    }
}
