<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TalentSubscription extends Model
{
    protected $fillable = [
        'talent_id',
        'plan',
        'amount',
        'currency',
        'payment_provider',
        'payment_id',
        'payment_reference',
        'confirmed_by_user_id',
        'confirmed_at',
        'notes',
        'idempotency_token',
        'start_date',
        'end_date',
        'status',
        'is_courtesy',
        'courtesy_type',
        'is_refunded',
        'refunded_at',
        'refund_reason',
        'refunded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'confirmed_at' => 'datetime',
            'is_courtesy' => 'boolean',
            'is_refunded' => 'boolean',
            'refunded_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by_user_id');
    }
}
