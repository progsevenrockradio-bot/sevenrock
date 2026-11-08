<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TalentReferralReward extends Model
{
    use HasFactory;

    public const STATUS_GRANTED = 'granted';
    public const STATUS_PENDING_PRO_RESOLUTION = 'pending_pro_resolution';
    public const STATUS_FLAGGED_REVIEW = 'flagged_review';
    public const STATUS_RESOLVED = 'resolved';

    protected $table = 'talent_referral_rewards';

    protected $fillable = [
        'promoter_code_id',
        'referrer_talent_id',
        'campaign',
        'reward_tier',
        'qualifying_referral_ids',
        'status',
        'granted_subscription_id',
        'granted_days',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'reward_tier' => 'integer',
            'qualifying_referral_ids' => 'array',
            'granted_days' => 'integer',
        ];
    }

    public function promoterCode(): BelongsTo
    {
        return $this->belongsTo(PromoterCode::class, 'promoter_code_id');
    }

    public function referrerTalent(): BelongsTo
    {
        return $this->belongsTo(Talent::class, 'referrer_talent_id');
    }

    public function grantedSubscription(): BelongsTo
    {
        return $this->belongsTo(TalentSubscription::class, 'granted_subscription_id');
    }
}

