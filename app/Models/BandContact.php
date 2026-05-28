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
        'program_code',
        'referral_source',
        'email',
        'phone',
        'facebook',
        'instagram',
        'contact_person',
        'notes',
        'status',
        'last_contacted_at',
        'image_specs_met',
        'audio_specs_met',
        'submission_deadline',
        'materials_received_at',
        'materials_note',
        'backblaze_path',
    ];

    protected function casts(): array
    {
        return [
            'radio_artist_id' => 'integer',
            'image_specs_met' => 'boolean',
            'audio_specs_met' => 'boolean',
            'last_contacted_at' => 'datetime',
            'submission_deadline' => 'datetime',
            'materials_received_at' => 'datetime',
        ];
    }

    public function radioArtist(): BelongsTo
    {
        return $this->belongsTo(RadioArtist::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(MasterProgram::class, 'program_code', 'program_code');
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

    public function programLabel(): string
    {
        if ($this->program_code === '') {
            return 'Sin programa';
        }

        return trim((string) ($this->program?->name ?: $this->program_code));
    }

    public function specsBadge(): string
    {
        $items = [];
        if ($this->image_specs_met) {
            $items[] = 'IMG OK';
        }
        if ($this->audio_specs_met) {
            $items[] = 'AUDIO OK';
        }

        return $items !== [] ? implode(' · ', $items) : 'Pendiente';
    }
}
