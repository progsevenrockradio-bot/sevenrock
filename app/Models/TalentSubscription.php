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
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
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
}
