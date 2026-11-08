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
    public const PAID_RECOVERY_DAYS = 30;
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

    public function subscriptionReminders(): HasMany
    {
        return $this->hasMany(TalentSubscriptionReminder::class);
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
            'free' => TalentPlan::definition('free')['limits'],
            'basic' => TalentPlan::definition('basic')['limits'],
            'pro' => TalentPlan::definition('pro')['limits'],
            'premium' => ['photos' => 999, 'songs' => 50, 'documents' => 999, 'videos' => 999, 'storage_mb' => 20480],
            default => TalentPlan::definition('free')['limits'],
        };
    }

    public function maxFileSizeKb(string $type): int
    {
        $sizeKey = match (trim($type)) {
            'mp3', 'song', 'songs' => 'songs',
            'photo', 'photos' => 'photos',
            'document', 'documents' => 'documents',
            'video', 'videos' => 'videos',
            default => null,
        };

        if ($sizeKey) {
            $planMb = (int) (TalentPlan::definition($this->plan)['max_file_size_mb'][$sizeKey] ?? 0);
            if ($planMb > 0) {
                return $planMb * 1024;
            }
        }

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
     * Devuelve el mapa de enlaces externos de apoyo y compra directa propios de la banda.
     * Soporta las claves predefinidas 'support' y 'merch', preservando valores históricos.
     *
     * @return array<string, string>
     */
    public function paymentLinkMap(): array
    {
        $stored = is_array($this->payment_links ?? null) ? $this->payment_links : [];

        $map = [];

        // 1. «Apoyar a la banda» (PayPal.Me, Ko-fi u otro compatible)
        $support = (string) ($stored['support'] ?? $stored['paypal'] ?? '');
        if (trim($support) !== '') {
            $map['support'] = trim($support);
        }

        // 2. «Comprar música / Merch» (Bandcamp, tienda oficial u otro destino)
        $merch = (string) ($stored['merch'] ?? $stored['otro'] ?? $stored['store'] ?? $stored['bandcamp'] ?? '');
        if (trim($merch) !== '') {
            $map['merch'] = trim($merch);
        }

        // 3. Preservar claves históricas adicionales sin reinterpretarlas automáticamente
        foreach ($stored as $key => $url) {
            if (! in_array($key, ['support', 'merch', 'paypal', 'otro', 'store', 'bandcamp'], true) && is_string($url) && trim($url) !== '') {
                $map[$key] = trim($url);
            }
        }

        return $map;
    }

    public function supportPaymentUrl(): ?string
    {
        $map = $this->paymentLinkMap();

        return $map['support'] ?? null;
    }

    public function merchPaymentUrl(): ?string
    {
        $map = $this->paymentLinkMap();

        return $map['merch'] ?? null;
    }

    public static function paymentLinkLabel(string $key): string
    {
        return match (strtolower(trim($key))) {
            'support', 'paypal' => 'Apoyar a la banda',
            'merch', 'store', 'otro', 'bandcamp' => 'Comprar música / Merch',
            'mercadopago' => 'Mercado Pago',
            default => ucfirst(str_replace('_', ' ', $key)),
        };
    }

    public function isPaidPlan(): bool
    {
        return in_array($this->plan, ['basic', 'pro'], true);
    }

    public function recoveryPeriodEndsAt(): ?\Carbon\CarbonInterface
    {
        if (! $this->isPaidPlan()) {
            return null;
        }

        $latestSub = $this->subscriptions()
            ->whereNotNull('end_date')
            ->latest('end_date')
            ->first();

        if (! $latestSub || ! $latestSub->end_date) {
            return $this->expires_grace_at;
        }

        return $latestSub->end_date->copy()->addDays(self::PAID_RECOVERY_DAYS);
    }

    public function isInRecoveryPeriod(): bool
    {
        return $this->isPaidPlan() && (bool) $this->is_hidden && $this->subscription_status === 'expired';
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