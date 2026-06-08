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
            ['key' => '{program_code}', 'label' => 'Código del programa'],
            ['key' => '{program_name}', 'label' => 'Nombre del programa'],
            ['key' => '{producer_name}', 'label' => 'Nombre del productor'],
            ['key' => '{image_specs}', 'label' => 'Especificaciones de imagen'],
            ['key' => '{audio_specs}', 'label' => 'Especificaciones de audio'],
            ['key' => '{submission_days}', 'label' => 'Plazo de envío'],
            ['key' => '{launch_date}', 'label' => 'Fecha de lanzamiento'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function placeholderMap(?MasterProgram $program = null, ?BandContact $contact = null): array
    {
        $artist = $contact?->radioArtist;
        $program = $program ?: $contact?->program;
        $launchDate = Carbon::now()->isSaturday()
            ? 'hoy'
            : 'este sábado';

        return [
            '{band_name}' => trim((string) ($artist?->name ?: $contact?->bandName() ?: 'Banda')),
            '{band_genre}' => trim((string) ($artist?->genre ?: 'Rock')),
            '{band_country}' => trim((string) ($artist?->country ?: '')),
            '{website_url}' => 'https://sevenrockradio.com',
            '{admin_url}' => 'https://sevenrockradio.com/talentos/register',
            '{radio_name}' => 'Seven Rock Radio',
            '{contact_person}' => trim((string) ($contact?->contact_person ?: '')),
            '{year}' => Carbon::now()->year,
            '{program_code}' => trim((string) ($program?->program_code ?: $contact?->program_code ?: '')),
            '{program_name}' => trim((string) ($program?->program_name ?: $program?->name ?: '')),
            '{producer_name}' => trim((string) ($program?->conductor ?: '')),
            '{image_specs}' => '1200×800 píxeles, JPG o PNG',
            '{audio_specs}' => 'MP3, 192 kbps mínimo, stack completo',
            '{submission_days}' => '8 a 10 días',
            '{launch_date}' => $launchDate,
        ];
    }

    public function renderText(string $value, ?MasterProgram $program = null, ?BandContact $contact = null): string
    {
        return str_replace(array_keys($this->placeholderMap($program, $contact)), array_values($this->placeholderMap($program, $contact)), $value);
    }

    public function renderSubject(?MasterProgram $program = null, ?BandContact $contact = null): string
    {
        return $this->renderText((string) $this->subject, $program, $contact);
    }

    public function renderBody(?MasterProgram $program = null, ?BandContact $contact = null): string
    {
        return $this->renderText((string) $this->body, $program, $contact);
    }
}
