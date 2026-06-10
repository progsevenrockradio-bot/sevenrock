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

        static::saved(static function (Post $post): void {
            $wasPublished = filter_var($post->getOriginal('is_published'), FILTER_VALIDATE_BOOLEAN);
            $isPublishedNow = filter_var($post->is_published, FILTER_VALIDATE_BOOLEAN);

            $newlyPublished = $post->wasRecentlyCreated && $isPublishedNow;
            $updatedToPublished = ! $wasPublished && $isPublishedNow;

            if ($newlyPublished || $updatedToPublished) {
                $post->sendPublishedNotification();
            }
        });
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
        'author_email',
        'notification_sender',
        'timezone',
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
            'timezone' => 'string',
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
        return $query->where(function ($q) {
            $q->where('is_published', true)
              ->orWhere(function ($sub) {
                  if (Schema::hasColumn($sub->getModel()->getTable(), 'status')) {
                      $sub->where('status', 'scheduled');
                  } else {
                      $sub->where('is_published', false);
                  }
                  $sub->whereNotNull('published_at')
                      ->where('published_at', '<=', now());
              });
        });
    }

    public function getIsPublishedAttribute(mixed $value): bool
    {
        if ($value !== null && $value !== '') {
            $isPublished = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            if ($isPublished) {
                return true;
            }
        }

        $status = Schema::hasColumn($this->getTable(), 'status') ? $this->getAttribute('status') : null;
        if ($status === 'scheduled') {
            return (bool) ($this->getAttribute('published_at') && $this->getAttribute('published_at') <= now());
        }

        if ($status === 'published') {
            return true;
        }

        if ($status === 'draft') {
            return false;
        }

        return (bool) ($this->getAttribute('published_at') && $this->getAttribute('published_at') <= now());
    }

    public function getUrlAttribute(): string
    {
        if (! $this->published_at || ! $this->slug) {
            return '#';
        }

        return route('posts.single', [
            'year' => $this->published_at->format('Y'),
            'month' => $this->published_at->format('m'),
            'day' => $this->published_at->format('d'),
            'slug' => $this->slug,
        ]);
    }

    public function sendPublishedNotification(): void
    {
        if (! Schema::hasColumn($this->getTable(), 'author_email')) {
            return;
        }

        $emailString = trim((string) $this->author_email);
        if ($emailString === '') {
            return;
        }

        // Split by semicolon (;) or comma (,) and trim each email
        $emails = array_values(array_filter(array_map('trim', preg_split('/[;,]/', $emailString))));
        if (empty($emails)) {
            return;
        }

        try {
            $sender = Schema::hasColumn($this->getTable(), 'notification_sender') && $this->notification_sender
                ? trim((string) $this->notification_sender)
                : config('mail.from.address');

            \Illuminate\Support\Facades\Mail::to($emails)->send(
                new \App\Mail\PostPublishedMail($this, $sender)
            );

            \Illuminate\Support\Facades\Log::info("Email de notificación de publicación enviado a los autores (" . implode(', ', $emails) . ") para el post ID: {$this->id}");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Fallo al enviar email de notificación de publicación para post ID {$this->id}: " . $e->getMessage());
        }
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
