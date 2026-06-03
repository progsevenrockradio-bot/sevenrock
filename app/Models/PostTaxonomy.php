<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PostTaxonomy extends Model
{
    public const TYPE_CATEGORY = 'category';

    public const TYPE_TAG = 'tag';

    protected $fillable = [
        'type',
        'name',
        'slug',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $taxonomy): void {
            $taxonomy->slug = Str::slug($taxonomy->slug ?: $taxonomy->name);
            $taxonomy->name = trim($taxonomy->name);
        });
    }

    public function scopeCategories(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_CATEGORY);
    }

    public function scopeTags(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_TAG);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_taxonomy_post')
            ->withTimestamps();
    }
}
