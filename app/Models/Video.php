<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use App\Support\PublicMediaUrl;

class Video extends Model
{
    use Auditable;
    protected $fillable = [
        'title',
        'slug',
        'image',
        'youtube_url',
        'summary',
        'is_featured',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
    ];

    public function getImageUrlAttribute(): string
    {
        return PublicMediaUrl::normalizePublicUrl($this->image) ?: asset($this->image);
    }
}
