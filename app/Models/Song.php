<?php

namespace App\Models;

use App\Support\PublicMediaUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Song extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'artist',
        'album',
        'duration_seconds',
        'audio_url',
        'cover_image',
        'lyrics',
        'band_info',
        'band_members',
        'social_links',
        'program_id',
        'is_live',
        'published_at',
        'play_count',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'band_members' => 'array',
            'social_links' => 'array',
            'is_live' => 'bool',
            'published_at' => 'datetime',
            'play_count' => 'int',
            'sort_order' => 'int',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%'.mb_strtolower(trim($term)).'%';

        return $query->whereRaw('LOWER(title) LIKE ? OR LOWER(artist) LIKE ? OR LOWER(album) LIKE ?', [$like, $like, $like]);
    }

    public function getCoverUrlAttribute(): string
    {
        if ($resolved = PublicMediaUrl::normalizePublicUrl($this->cover_image)) {
            return $resolved;
        }

        return $this->cover_image ? asset($this->cover_image) : '';
    }
}
