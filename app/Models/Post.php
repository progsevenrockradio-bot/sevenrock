<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\WordPressContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'image_source',
        'image_alt',
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
        'en_memoria',
        'en_memoria_nombre',
        'image_fetch_error',
        'source_subject',
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
            'en_memoria' => 'boolean',
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
        $path = $value ?: ($this->featured_image_path ?: null);
        
        return $path ?: null;
    }

    public function setFeaturedImageAttribute(?string $value): void
    {
        try {
            $table = $this->getTable();
            if (Schema::hasColumn($table, 'featured_image_path') && ! Schema::hasColumn($table, 'featured_image')) {
                $this->attributes['featured_image_path'] = $value;
                return;
            }
        } catch (\Throwable) {
        }
        $this->attributes['featured_image'] = $value;
    }


    public function scopePublished(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('is_published', true)
              ->orWhere(function ($sub) {
                  if (Schema::hasColumn($sub->getModel()->getTable(), 'status')) {
                      $sub->where('status', 'published');
                  }
              })
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

    public function getCleanExcerptAttribute(): string
    {
        return self::sanitizeExcerpt($this->excerpt, $this->content);
    }

    public static function sanitizeExcerpt(?string $raw, string|array|null $fallback = null, int $limit = 160): string
    {
        $text = (string) ($raw ?? '');

        $trimmed = trim($text);
        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $text = data_get($decoded, 'rendered')
                    ?? data_get($decoded, 'content')
                    ?? data_get($decoded, 'text')
                    ?? data_get($decoded, '0.value')
                    ?? data_get($decoded, '0.content.html')
                    ?? '';
            }
        }

        if (trim((string) $text) === '' && ! empty($fallback)) {
            if (is_array($fallback)) {
                foreach ($fallback as $candidate) {
                    $candStr = is_string($candidate)
                        ? $candidate
                        : (is_array($candidate) ? (data_get($candidate, 'value') ?? data_get($candidate, 'content.html') ?? data_get($candidate, 'content') ?? data_get($candidate, 'text') ?? '') : (string) $candidate);
                    $decoded = html_entity_decode((string) $candStr, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if (preg_match('/(?:<!doctype|xmlns:|urn:schemas|<head|<body|<td|<tr|<table)/i', $decoded)) {
                        continue;
                    }
                    $plainCand = trim(strip_tags($decoded));
                    if (strlen($plainCand) >= 15) {
                        $text = $plainCand;
                        break;
                    }
                }
                if (trim((string) $text) === '') {
                    $first = reset($fallback);
                    $text = is_string($first) ? $first : (is_array($first) ? (data_get($first, 'value') ?? '') : (string) $first);
                }
            } else {
                $trimmedFallback = trim((string) $fallback);
                if (str_starts_with($trimmedFallback, '{') || str_starts_with($trimmedFallback, '[')) {
                    $c = json_decode($trimmedFallback, true);
                    if (is_array($c)) {
                        $firstC = reset($c);
                        if (is_string($firstC)) {
                            $text = $firstC;
                        } elseif (is_array($firstC)) {
                            $text = data_get($firstC, 'value')
                                ?? data_get($firstC, 'content.html')
                                ?? data_get($firstC, 'content')
                                ?? data_get($firstC, 'text')
                                ?? '';
                        } else {
                            $text = (string) $firstC;
                        }
                    } else {
                        $text = $fallback;
                    }
                } else {
                    $text = $fallback;
                }
            }
        }

        $text = preg_replace('/<!--\s*\/?wp:[^>]*-->/i', ' ', (string) $text) ?? $text;
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);

        $text = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($m) {
            return mb_chr((int) hexdec($m[1]), 'UTF-8');
        }, $text) ?? $text;

        $text = str_replace(['\r', '\n', '\t', '\/'], [' ', ' ', ' ', '/'], $text);
        $clean = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return mb_strlen($clean) > $limit ? mb_substr($clean, 0, $limit - 3) . '...' : $clean;
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

        // Si el post proviene de la recepción automática de correos (source_subject presente),
        // no enviar notificaciones indebidas a agencias externas ni a bots.
        if (! empty($this->source_subject)) {
            \Illuminate\Support\Facades\Log::info("Notificación omitida para post ID {$this->id}: generado automáticamente desde correo externo.");
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

        // Excluir bots internos y direcciones del sistema
        $ignored = array_filter([
            'dark.vader.agent@gmail.com',
            'prog.sevenrockradio@gmail.com',
            strtolower(trim((string) config('mail.from.address'))),
        ]);

        $emails = array_values(array_filter($emails, function ($e) use ($ignored) {
            return ! in_array(strtolower($e), $ignored, true);
        }));

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

    public function getFeaturedImageUrlAttribute(): ?string
    {
        $path = (string) ($this->attributes['featured_image'] ?? $this->featured_image_path ?? '');

        if ($path === '') {
            return null;
        }

        if ($resolved = PublicMediaUrl::normalizePublicUrl($path)) {
            return $resolved;
        }

        return asset($path);
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

    public function incidents(): HasMany
    {
        return $this->hasMany(EditorialIncident::class);
    }

    public function sanitizedMeta(): HasOne
    {
        return $this->hasOne(EmailPostSanitizedMeta::class, 'post_id');
    }

    /**
     * Determina de forma fehaciente si este post procede de la ingesta editorial de correo.
     */
    public function isFromEmailPipeline(): bool
    {
        return !empty($this->source_subject)
            || ($this->relationLoaded('sanitizedMeta') ? $this->sanitizedMeta !== null : $this->sanitizedMeta()->exists());
    }

    /**
     * Obtiene las fuentes que aún no han sido aprobadas editorialmente.
     *
     * @return array<int, string>
     */
    public function getPendingSources(): array
    {
        return app(\App\Services\EmailSafetyValidator::class)->getPendingUnapprovedSources($this);
    }

    /**
     * Comprueba si el post tiene fuentes pendientes de aprobación.
     */
    public function hasPendingSources(): bool
    {
        return ! empty($this->getPendingSources());
    }
}

