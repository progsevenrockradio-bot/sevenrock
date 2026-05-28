<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class OutreachTemplate extends Model
{
    protected $fillable = [
        'name',
        'subject',
        'body',
        'variables',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(OutreachCampaign::class, 'template_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(OutreachLog::class, 'template_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return array<int, array{key:string,label:string}>
     */
    public static function defaultVariables(): array
    {
        return [
            ['key' => '{band_name}', 'label' => 'Nombre de la banda'],
            ['key' => '{band_genre}', 'label' => 'Género musical'],
            ['key' => '{band_country}', 'label' => 'País'],
            ['key' => '{website_url}', 'label' => 'Sitio web'],
            ['key' => '{admin_url}', 'label' => 'Registro de talentos'],
            ['key' => '{radio_name}', 'label' => 'Nombre de la radio'],
            ['key' => '{contact_person}', 'label' => 'Persona de contacto'],
            ['key' => '{year}', 'label' => 'Año actual'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function placeholderMap(?BandContact $contact = null): array
    {
        $artist = $contact?->radioArtist;

        return [
            '{band_name}' => trim((string) ($artist?->name ?: $contact?->bandName() ?: 'Banda')),
            '{band_genre}' => trim((string) ($artist?->genre ?: 'Rock')),
            '{band_country}' => trim((string) ($artist?->country ?: '')),
            '{website_url}' => 'https://sevenrockradio.com',
            '{admin_url}' => 'https://sevenrockradio.shop/talentos/register',
            '{radio_name}' => 'Seven Rock Radio',
            '{contact_person}' => trim((string) ($contact?->contact_person ?: '')),
            '{year}' => Carbon::now()->year,
        ];
    }

    public function renderText(string $value, ?BandContact $contact = null): string
    {
        return str_replace(array_keys($this->placeholderMap($contact)), array_values($this->placeholderMap($contact)), $value);
    }

    public function renderSubject(?BandContact $contact = null): string
    {
        return $this->renderText((string) $this->subject, $contact);
    }

    public function renderBody(?BandContact $contact = null): string
    {
        return $this->renderText((string) $this->body, $contact);
    }
}
