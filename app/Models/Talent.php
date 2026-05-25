<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\TalentPlan;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class Talent extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'user_id',
        'band_name',
        'email',
        'password',
        'bio',
        'logo',
        'plan',
        'subscription_status',
        'payment_customer_id',
        'interacts',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'interacts' => 'integer',
            'password' => 'hashed',
        ];
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

    public function interactions(): HasMany
    {
        return $this->hasMany(TalentInteraction::class);
    }

    public function activeSubscription(): ?TalentSubscription
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->whereDate('end_date', '>=', today())
            ->latest('end_date')
            ->first();
    }

    public function planDefinition(): array
    {
        return TalentPlan::definition((string) $this->plan);
    }

    public function logoUrl(): ?string
    {
        if (! filled($this->logo)) {
            return null;
        }

        try {
            return Storage::disk('backblaze-b2')->url($this->logo);
        } catch (\Throwable) {
            return null;
        }
    }
}
