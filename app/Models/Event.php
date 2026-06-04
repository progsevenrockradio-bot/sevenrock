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
        static::restored($bumpVersion);
    }

    protected $fillable = [
        'title',
        'slug',
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
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'categories' => 'array',
            'content' => 'array',
        ];
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', now()->startOfDay());
    }
}
