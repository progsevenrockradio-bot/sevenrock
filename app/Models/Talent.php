<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\TalentPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Talent extends Authenticatable
{
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'band_name',
        'email',
        'password',
        'email_verified_at',
        'bio',
        'logo',
        'instagram_url',
        'youtube_url',
        'tiktok_url',
        'spotify_url',
        'website_url',
        'social_links',
        'payment_links',
        'notification_preferences',
        'plan',
        'subscription_status',
        'payment_customer_id',
        'payment_provider',
        'interacts',
        'is_featured',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'interacts' => 'integer',
            'is_featured' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'social_links' => 'array',
            'payment_links' => 'array',
            'notification_preferences' => 'array',
        ];
    }

    public function scopeByPlan(Builder $query, string $plan): Builder
    {
        return $query->where('plan', $plan);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TalentSubscription::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(TalentMedia::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(TalentInteraction::class);
    }

    public function activeSubscription(): ?TalentSubscription
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', today());
            })
            ->latest('end_date')
            ->first();
    }

    public function planLimits(): array
    {
        return match ($this->plan) {
            'free' => ['photos' => 1, 'songs' => 1, 'documents' => 0, 'videos' => 0, 'storage_mb' => 50],
            'basic' => ['photos' => 10, 'songs' => 5, 'documents' => 0, 'videos' => 0, 'storage_mb' => 200],
            'pro' => ['photos' => 50, 'songs' => 20, 'documents' => 10, 'videos' => 5, 'storage_mb' => 1000],
            'premium' => ['photos' => 999, 'songs' => 999, 'documents' => 999, 'videos' => 999, 'storage_mb' => 5000],
            default => ['photos' => 1, 'songs' => 1, 'documents' => 0, 'videos' => 0, 'storage_mb' => 50],
        };
    }

    public function planDefinition(): array
    {
        return $this->planLimits();
    }

    public function logoUrl(): ?string
    {
        if (! filled($this->logo)) {
            return null;
        }

        try {
            return Storage::disk('backblaze')->url($this->logo);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, string>
     */
    public function socialLinkMap(): array
    {
        $stored = is_array($this->social_links ?? null) ? $this->social_links : [];

        return array_filter([
            'instagram' => (string) ($stored['instagram'] ?? $this->instagram_url ?? ''),
            'youtube' => (string) ($stored['youtube'] ?? $this->youtube_url ?? ''),
            'tiktok' => (string) ($stored['tiktok'] ?? $this->tiktok_url ?? ''),
            'spotify' => (string) ($stored['spotify'] ?? $this->spotify_url ?? ''),
            'website' => (string) ($stored['website'] ?? $this->website_url ?? ''),
        ], static fn (string $value): bool => trim($value) !== '');
    }

    /**
     * @return array<string, string>
     */
    public function paymentLinkMap(): array
    {
        $stored = is_array($this->payment_links ?? null) ? $this->payment_links : [];

        return array_filter([
            'paypal' => (string) ($stored['paypal'] ?? ''),
            'mercadopago' => (string) ($stored['mercadopago'] ?? ''),
            'otro' => (string) ($stored['otro'] ?? ''),
        ], static fn (string $value): bool => trim($value) !== '');
    }

    /**
     * @return array<string, bool>
     */
    public function notificationPreferences(): array
    {
        $defaults = [
            'likes' => true,
            'comments' => true,
            'renewals' => true,
        ];

        $stored = is_array($this->notification_preferences ?? null) ? $this->notification_preferences : [];

        return array_merge($defaults, array_map(
            static fn ($value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            array_intersect_key($stored, $defaults)
        ));
    }

    public function notificationPreferenceEnabled(string $key, bool $default = true): bool
    {
        $preferences = $this->notificationPreferences();

        return (bool) ($preferences[$key] ?? $default);
    }

    public function storageUsed(): int
    {
        return (int) $this->media()->sum('size');
    }

    public function canUpload(string $type): bool
    {
        $limits = $this->planLimits();
        $map = [
            'photo' => 'photos',
            'photos' => 'photos',
            'mp3' => 'songs',
            'song' => 'songs',
            'songs' => 'songs',
            'document' => 'documents',
            'documents' => 'documents',
            'video' => 'videos',
            'videos' => 'videos',
        ];

        $limitKey = $map[trim($type)] ?? null;
        if ($limitKey === null) {
            return false;
        }

        return (int) $this->media()->where('type', $this->normalizeMediaType($type))->count() < (int) ($limits[$limitKey] ?? 0);
    }

    private function normalizeMediaType(string $type): string
    {
        return match (trim($type)) {
            'song', 'songs' => 'mp3',
            'photo', 'photos' => 'photo',
            'document', 'documents' => 'document',
            'video', 'videos' => 'video',
            default => trim($type),
        };
    }
}
