<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TalentReferral extends Model
{
    public const REWARD_TIER_1_BANDS = 12; // 1 mes básico gratis
    public const REWARD_TIER_2_BANDS = 18; // 2 meses básico gratis
    public const REWARD_TIER_PAID_BANDS = 3; // 3 meses extra gratis cuando 3 pasan a pago

    protected $table = 'talent_referrals';

    protected $fillable = [
        'referrer_talent_id',
        'promoter_code_id',
        'referred_talent_id',
        'code',
        'status',
        'reward_applied',
        'activated_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['active', 'paid']);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Talent::class, 'referrer_talent_id');
    }

    public function promoterCode(): BelongsTo
    {
        return $this->belongsTo(PromoterCode::class, 'promoter_code_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(Talent::class, 'referred_talent_id');
    }
}
