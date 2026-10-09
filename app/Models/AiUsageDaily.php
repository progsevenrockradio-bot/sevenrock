<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageDaily extends Model
{
    protected $table = 'ai_usage_daily';

    protected $fillable = [
        'date',
        'provider',
        'calls',
        'filtered_emails',
    ];

    protected $casts = [
        'date' => 'date',
        'calls' => 'integer',
        'filtered_emails' => 'integer',
    ];

    // Aliases to support both Spanish and English nomenclature
    public function getFechaAttribute(): ?string
    {
        return $this->date ? $this->date->format('Y-m-d') : null;
    }

    public function getProveedorAttribute(): ?string
    {
        return $this->provider;
    }

    public function getLlamadasAttribute(): int
    {
        return (int) $this->calls;
    }

    public function getCorreosFiltradosAttribute(): int
    {
        return (int) $this->filtered_emails;
    }

    public static function recordCall(string $provider = 'total'): void
    {
        app(\App\Services\AiUsageTracker::class)->incrementCall($provider);
    }

    public static function recordFiltered(): void
    {
        app(\App\Services\AiUsageTracker::class)->incrementFiltered();
    }
}
