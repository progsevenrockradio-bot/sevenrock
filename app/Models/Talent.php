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

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Talent extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    public const FREE_DURATION_DAYS = 45;
    public const GRACE_DAYS = 15;
    public const REFERRAL_BONUS_DAYS = 15;

    protected $table = 'talents';

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
        'is_hidden',
        'expires_grace_at',
        'referral_code',
        'referred_by_code',
        'facebook_screenshot',
        'instagram_screenshot',
        'country',
        'contact_phone',
        'rejection_reason',
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
            'is_hidden' => 'boolean',
            'expires_grace_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'social_links' => 'array',
            'payment_links' => 'array',
            'notification_preferences' => 'array',
        ];
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('is_hidden', false)
            ->where('subscription_status', 'active');
    }

    public function scopeByPlan(Builder $query, string $plan): Builder
    {
        return $query->where('plan', $plan);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function referralsGiven(): HasMany
    {
        return $this->hasMany(TalentReferral::class, 'referrer_talent_id');
    }

    public function referralReceived(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TalentReferral::class, 'referred_talent_id');
    }

    public function activeReferralsCount(): int
    {
        return (int) $this->referralsGiven()->whereIn('status', ['active', 'paid'])->count();
    }

    public function pendingReferralsCount(): int
    {
        return (int) $this->referralsGiven()->where('status', 'pending')->count();
    }

    public function paidReferralsCount(): int
    {
        return (int) $this->referralsGiven()->where('status', 'paid')->count();
    }

    /**
     * Devuelve el estado de referidos y cuánto falta para el siguiente premio.
     * @return array{active_count: int, paid_count: int, next_milestone: int, needed: int, reward_label: string}
     */
    public function nextRewardInfo(): array
    {
        $active = $this->activeReferralsCount();

        if ($active < TalentReferral::REWARD_TIER_1_BANDS) {
            $next = TalentReferral::REWARD_TIER_1_BANDS;
            $needed = $next - $active;
            $label = '1 mes gratis del plan Básico';
        } elseif ($active < TalentReferral::REWARD_TIER_2_BANDS) {
            $next = TalentReferral::REWARD_TIER_2_BANDS;
            $needed = $next - $active;
            $label = '2 meses gratis del plan Básico';
        } else {
            $next = $active + 6; // Siguiente ciclo
            $needed = 0;
            $label = '¡Has alcanzado los hitos principales de referidos!';
        }

        return [
            'active_count' => $active,
            'paid_count' => $this->paidReferralsCount(),
            'next_milestone' => $next,
            'needed' => max(0, $needed),
            'reward_label' => $label,
        ];
    }

    public static function generateUniqueReferralCode(int $length = 8): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (self::where('referral_code', $code)->exists() || PromoterCode::where('code', $code)->exists());

        return $code;
    }

    public function ensureReferralCode(): string
    {
        if (filled($this->referral_code)) {
            return (string) $this->referral_code;
        }

        $code = self::generateUniqueReferralCode();
        $this->update(['referral_code' => $code]);

        return $code;
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

    public function albums(): HasMany
    {
        return $this->hasMany(TalentAlbum::class);
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
            'free' => ['photos' => 1, 'songs' => 3, 'documents' => 0, 'videos' => 0, 'storage_mb' => 50],
            'basic' => ['photos' => 3, 'songs' => 7, 'documents' => 0, 'videos' => 0, 'storage_mb' => 120],
            'pro' => ['photos' => 50, 'songs' => 15, 'documents' => 10, 'videos' => 5, 'storage_mb' => 400],
            'premium' => ['photos' => 999, 'songs' => 50, 'documents' => 999, 'videos' => 999, 'storage_mb' => 20480],
            default => ['photos' => 1, 'songs' => 3, 'documents' => 0, 'videos' => 0, 'storage_mb' => 50],
        };
    }

    public function maxFileSizeKb(string $type): int
    {
        if (trim($type) === 'mp3' || trim($type) === 'song' || trim($type) === 'songs') {
            $maxMb = match ($this->plan) {
                'free' => 12,
                'basic' => 15,
                'pro' => 20,
                'premium' => 25,
                default => 12,
            };
            return $maxMb * 1024;
        }

        return match (trim($type)) {
            'photo', 'photos' => 10 * 1024,
            'document', 'documents' => 10 * 1024,
            'video', 'videos' => 100 * 1024,
            default => 10 * 1024,
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

        return \App\Support\PublicMediaUrl::normalize($this->logo);
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