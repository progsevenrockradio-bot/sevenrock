<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Support\PublicMediaUrl;

class Post extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'author',
        'excerpt',
        'content',
        'quote',
        'featured_image',
        'published_at',
        'categories',
        'tags',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'categories' => 'array',
            'tags' => 'array',
            'published_at' => 'datetime',
            'is_published' => 'bool',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function getFeaturedImageUrlAttribute(): string
    {
        if ($resolved = PublicMediaUrl::normalizePublicUrl($this->featured_image)) {
            return $resolved;
        }

        return $this->featured_image ? asset($this->featured_image) : '';
    }
}
