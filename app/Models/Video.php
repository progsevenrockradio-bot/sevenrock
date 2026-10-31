<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use App\Support\PublicMediaUrl;

class Video extends Model
{
    use Auditable;

    protected static function booted(): void
    {
        $bumpVersion = static function (): void {
            \Illuminate\Support\Facades\Cache::forever('cache.version.videos', now()->timestamp);
        };

        static::saved($bumpVersion);
        static::deleted($bumpVersion);
    }

    protected $fillable = [
        'title',
        'slug',
        'image',
        'youtube_url',
        'summary',
        'is_featured',
        'is_manual',
        'source_type',
        'source_id',
        'featured_at',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_manual'   => 'boolean',
        'featured_at' => 'datetime',
    ];

    public function getImageUrlAttribute(): string
    {
        return PublicMediaUrl::normalizePublicUrl($this->image) ?: asset($this->image);
    }
}
