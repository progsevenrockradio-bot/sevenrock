<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Support\PublicMediaUrl;

class GalleryImage extends Model
{
    use Auditable;

    protected static function booted(): void
    {
        $bumpVersion = static function (): void {
            Cache::forever('cache.version.gallery', now()->timestamp);
        };

        static::saved($bumpVersion);
        static::deleted($bumpVersion);
    }

    protected $fillable = [
        'image',
        'caption',
        'sort_order',
    ];

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function getImageUrlAttribute(): string
    {
        return PublicMediaUrl::normalizePublicUrl($this->image) ?: asset($this->image);
    }
}
