<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\PublicMediaUrl;

class Album extends Model
{
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
