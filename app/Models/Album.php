<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Support\PublicMediaUrl;

class Album extends Model
{
    use Auditable;

    protected static function booted(): void
    {
        $bumpVersion = static function (): void {
            Cache::forever('cache.version.albums', now()->timestamp);
        };

        static::saved($bumpVersion);
        static::deleted($bumpVersion);
        static::restored($bumpVersion);
    }

    protected $fillable = [
        'title',
        'slug',
        'artist',
        'cover_image',
        'summary',
        'released_at',
        'tracks',
        'buy_links',
    ];

    protected function casts(): array
    {
        return [
            'released_at' => 'date',
            'tracks' => 'array',
            'buy_links' => 'array',
        ];
    }

    public function getCoverImageUrlAttribute(): string
    {
        return PublicMediaUrl::normalizePublicUrl($this->cover_image) ?: asset($this->cover_image);
    }
}
