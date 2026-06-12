<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\PublicMediaUrl;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RadioArtistTalentFallback extends Talent
{
    protected $table = 'radio_artists';

    protected $fillable = [
        'name',
        'agency_id',
        'biography',
        'editorial_summary',
        'image_path',
        'founded_date',
        'logo_path',
        'country',
        'genre',
        'members_count',
        'status',
        'labels',
        'featured_facts',
        'milestones',
        'related_artists',
        'official_links',
        'last_verified_at',
        'source',
    ];

    protected $casts = [
        'founded_date' => 'date',
        'members_count' => 'integer',
        'featured_facts' => 'array',
        'milestones' => 'array',
        'related_artists' => 'array',
        'official_links' => 'array',
        'last_verified_at' => 'datetime',
    ];

    public function getBandNameAttribute(): string
    {
        return (string) $this->name;
    }

    public function getBioAttribute(): ?string
    {
        return $this->biography;
    }

    public function getLogoAttribute(): ?string
    {
        return $this->logo_path;
    }

    public function getPlanAttribute(): string
    {
        return 'agencia';
    }

    public function getIsFeaturedAttribute(): bool
    {
        return false;
    }

    public function logoUrl(): ?string
    {
        return PublicMediaUrl::normalizePublicUrl((string) $this->logo_path);
    }

    public function socialLinkMap(): array
    {
        $stored = is_array($this->official_links ?? null) ? $this->official_links : [];
        $map = [];
        foreach ($stored as $link) {
            if (! is_array($link)) {
                continue;
            }
            $label = strtolower(trim((string) ($link['label'] ?? '')));
            $url = trim((string) ($link['url'] ?? ''));
            if ($url !== '') {
                if ($label === '') {
                    $label = 'website';
                }
                $map[$label] = $url;
            }
        }
        return $map;
    }

    public function media(): HasMany
    {
        return $this->hasMany(TalentMedia::class, 'talent_id')->whereRaw('1 = 0');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'talent_id')->whereRaw('1 = 0');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(TalentInteraction::class, 'talent_id')->whereRaw('1 = 0');
    }
}
