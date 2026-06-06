<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\WordPressContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Support\PublicMediaUrl;
use Illuminate\Support\Facades\Schema;

class Post extends Model
{
    use Auditable;

    protected static function booted(): void
    {
        $bumpVersion = static function (): void {
            Cache::forever('cache.version.posts', now()->timestamp);
        };

        static::saved($bumpVersion);
        static::deleted($bumpVersion);
    }

    protected $fillable = [
        'title',
        'slug',
        'user_id',
        'author',
        'excerpt',
        'content',
        'quote',
        'featured_image',
        'featured_image_path',
        'facebook_url',
        'instagram_url',
        'twitter_url',
        'youtube_url',
        'external_link_url',
        'external_link_label',
        'source_name',
        'source_url',
        'meta_title',
        'meta_description',
        'published_at',
        'status',
        'categories',
        'tags',
        'is_published',
    ];

    protected $appends = [
        'featured_image_url',
    ];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'tags' => 'array',
            'published_at' => 'datetime',
            'is_published' => 'bool',
        ];
    }

    public function getContentAttribute(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            return [['type' => 'raw', 'value' => $value]];
        }

        return [];
    }

    public function setContentAttribute(mixed $value): void
    {
        $blocks = WordPressContent::toRenderableBlocks($value);
        $this->attributes['content'] = json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    public function getFeaturedImageAttribute(?string $value): ?string
    {
        return [] ?: ($this->featured_image_path ?: null);
    }

    public function scopePublished(Builder $query): Builder
    {
        if (Schema::hasColumn($query->getModel()->getTable(), 'is_published')) {
            return $query->where('is_published', true);
        }

        if (Schema::hasColumn($query->getModel()->getTable(), 'status')) {
            return $query->where('status', 'published');
        }

        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function getIsPublishedAttribute(mixed $value): bool
    {
        if ($value !== null && $value !== '') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if (Schema::hasColumn($this->getTable(), 'status')) {
            return (string) ($this->getAttribute('status') ?? '') === 'published';
        }

        return (bool) ($this->getAttribute('published_at') && $this->getAttribute('published_at') <= now());
    }

    public function getFeaturedImageUrlAttribute(): string
    {
        $path = (string) ($this->featured_image ?? $this->featured_image_path ?? '');

        if ($resolved = PublicMediaUrl::normalizePublicUrl($path)) {
            return $resolved;
        }

        return $path !== '' ? asset($path) : '';
    }

    public function taxonomies(): BelongsToMany
    {
        return $this->belongsToMany(PostTaxonomy::class, 'post_taxonomy_post')
            ->withTimestamps();
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(PostReaction::class);
    }

    public function categoryTaxonomies(): BelongsToMany
    {
        return $this->taxonomies()->where('type', PostTaxonomy::TYPE_CATEGORY);
    }

    public function tagTaxonomies(): BelongsToMany
    {
        return $this->taxonomies()->where('type', PostTaxonomy::TYPE_TAG);
    }

    /**
     * @return array<int, string>
     */
    public function categoryNames(): array
    {
        return $this->taxonomyNames(PostTaxonomy::TYPE_CATEGORY, 'categories');
    }

    /**
     * @return array<int, string>
     */
    public function tagNames(): array
    {
        return $this->taxonomyNames(PostTaxonomy::TYPE_TAG, 'tags');
    }

    /**
     * @return array<int, string>
     */
    private function taxonomyNames(string $type, string $attribute): array
    {
        if (Schema::hasTable('post_taxonomy_post')) {
            if ($this->relationLoaded('taxonomies')) {
                $names = $this->taxonomies
                    ->where('type', $type)
                    ->sortBy('name')
                    ->pluck('name')
                    ->all();
            } else {
                $names = $this->taxonomies()
                    ->where('type', $type)
                    ->orderBy('name')
                    ->pluck('name')
                    ->all();
            }

            if ($names !== []) {
                return $names;
            }
        }

        return array_values(array_filter(array_map('strval', $this->{$attribute} ?? [])));
    }

}
