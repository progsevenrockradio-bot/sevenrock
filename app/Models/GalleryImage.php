<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Support\PublicMediaUrl;

class GalleryImage extends Model
{
    use Auditable;
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
