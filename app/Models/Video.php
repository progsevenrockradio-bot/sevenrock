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
