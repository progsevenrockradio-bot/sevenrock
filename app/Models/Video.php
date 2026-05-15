<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\PublicMediaUrl;

class Video extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'image',
        'youtube_url',
        'summary',
    ];

    public function getImageUrlAttribute(): string
    {
        return PublicMediaUrl::normalizePublicUrl($this->image) ?: asset($this->image);
    }
}
