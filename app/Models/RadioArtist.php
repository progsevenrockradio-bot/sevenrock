<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\PublicMediaUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RadioArtist extends Model
{
    use Auditable;

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

    protected function casts(): array
    {
        return [
            'founded_date' => 'date',
            'members_count' => 'integer',
            'featured_facts' => 'array',
            'milestones' => 'array',
            'related_artists' => 'array',
            'official_links' => 'array',
            'last_verified_at' => 'datetime',
        ];
    }

    public function normalizedImageUrl(): ?string
    {
        if ($resolved = PublicMediaUrl::normalizePublicUrl((string) $this->image_path)) {
            return $resolved;
        }

        if ($this->image_path) {
            return asset($this->image_path);
        }

        if ($this->logo_path) {
            return $this->logo_path;
        }

        return null;
    }

    public function publicSlug(): string
    {
        return Str::slug($this->name ?: 'banda');
    }

    public function songs(): HasMany
    {
        return $this->hasMany(Song::class, 'band_profile_id');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function normalizedMilestones(): array
    {
        return collect((array) $this->milestones)
            ->map(function ($milestone) {
                if (! is_array($milestone)) {
                    return null;
                }

                $dateIso = trim((string) ($milestone['date_iso'] ?? ''));
                $dateLabel = trim((string) ($milestone['date_label'] ?? ''));

                if ($dateLabel === '' && $dateIso !== '') {
                    try {
                        $dateLabel = Carbon::parse($dateIso)->translatedFormat('d M Y');
                    } catch (\Throwable) {
                        $dateLabel = $dateIso;
                    }
                }

                return [
                    'date_iso' => $dateIso !== '' ? $dateIso : null,
                    'date_label' => $dateLabel,
                    'title' => trim((string) ($milestone['title'] ?? '')),
                    'description' => trim((string) ($milestone['description'] ?? '')),
                ];
            })
            ->filter(fn ($milestone) => is_array($milestone) && $milestone['title'] !== '')
            ->values()
            ->all();
    }
}
