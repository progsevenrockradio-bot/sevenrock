<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PromoterCode extends Model
{
    public const DEFAULT_MAX_BANDS = 18;

    protected $table = 'promoter_codes';

    protected $fillable = [
        'code',
        'owner_name',
        'owner_email',
        'owner_type',
        'max_bands',
        'assigned_talent_id',
        'is_archived',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'max_bands' => 'integer',
            'is_archived' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(TalentReferral::class, 'promoter_code_id');
    }

    public function assignedTalent(): BelongsTo
    {
        return $this->belongsTo(Talent::class, 'assigned_talent_id');
    }

    public function activeBandsCount(): int
    {
        return (int) $this->referrals()->whereIn('status', ['active', 'paid'])->count();
    }

    public function hasReachedLimit(): bool
    {
        if ($this->max_bands === null) {
            return false;
        }

        return $this->activeBandsCount() >= $this->max_bands;
    }

    public function expandLimit(int $newLimit): void
    {
        $this->update(['max_bands' => max($newLimit, (int) $this->max_bands)]);
    }

    /**
     * Archiva este código conservando su histórico y emite uno nuevo para el mismo dueño.
     */
    public function archiveAndRenew(): self
    {
        $this->update(['is_archived' => true]);

        return self::create([
            'code'               => self::generateUniqueCode(),
            'owner_name'         => $this->owner_name,
            'owner_email'        => $this->owner_email,
            'owner_type'         => $this->owner_type ?: 'conductor',
            'max_bands'          => self::DEFAULT_MAX_BANDS,
            'assigned_talent_id' => $this->assigned_talent_id,
            'is_archived'        => false,
            'notes'              => 'Renovado a partir del código archivado ' . $this->code,
        ]);
    }

    /**
     * Genera un código legible de 8 caracteres alfanuméricos sin caracteres ambiguos.
     */
    public static function generateUniqueCode(int $length = 8): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (self::where('code', $code)->exists() || Talent::where('referral_code', $code)->exists());

        return $code;
    }
}
