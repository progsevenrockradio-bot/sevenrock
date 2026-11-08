<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TalentSubscriptionReminder extends Model
{
    public const STAGE_7_DAYS_BEFORE = '7_days_before';
    public const STAGE_1_DAY_BEFORE = '1_day_before';
    public const STAGE_AT_EXPIRY = 'at_expiry';
    public const STAGE_3_DAYS_BEFORE_PURGE = '3_days_before_purge';
    public const STAGE_RECOVERY_7_DAYS_BEFORE = 'recovery_7_days_before';
    public const STAGE_RECOVERY_1_DAY_BEFORE = 'recovery_1_day_before';

    protected $fillable = [
        'talent_id',
        'talent_subscription_id',
        'stage',
        'cycle_end_date',
        'sent_at',
        'recipient_email',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'cycle_end_date' => 'date',
            'sent_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TalentSubscription::class, 'talent_subscription_id');
    }
}

