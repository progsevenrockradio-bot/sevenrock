<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BandContact extends Model
{
    protected $fillable = [
        'radio_artist_id',
        'email',
        'phone',
        'facebook',
        'instagram',
        'contact_person',
        'notes',
        'status',
        'last_contacted_at',
    ];

    protected function casts(): array
    {
        return [
            'radio_artist_id' => 'integer',
            'last_contacted_at' => 'datetime',
        ];
    }

    public function radioArtist(): BelongsTo
    {
        return $this->belongsTo(RadioArtist::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(OutreachLog::class);
    }

    public function displayName(): string
    {
        return trim((string) ($this->radioArtist?->name ?: $this->contact_person ?: 'Sin banda'));
    }

    public function bandName(): string
    {
        return trim((string) ($this->radioArtist?->name ?: $this->contact_person ?: 'Sin banda'));
    }
}
