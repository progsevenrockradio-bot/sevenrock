<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\PublicMediaUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MasterProgram extends Model
{
    use HasFactory;
    use Auditable;

    protected $table = 'master_programs';

    protected $fillable = [
        'nombre',
        'conductor',
        'dia_transmision',
        'hora_transmision',
        'timezone',
        'duracion_minutos',
        'genero',
        'caratula_url',
        'descripcion',
        'live_title',
        'live_description',
        'live_image_url',
        'live_starts_at',
        'live_ends_at',
        'default_news_ids',
        'live_news_ids',
        'preview_news_ids',
        'comentario_predeterminado',
        'red_social1_url',
        'red_social2_url',
        'activo',
        'archive_identifier',
        'vistas_archive',
        'escuchas_locales',
        'vistas_totales',
        'stats_updated_at',
        'ruta_ftp',
        'email_notificacion',
        'email_copia_notificacion',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'duracion_minutos' => 'integer',
        'default_news_ids' => 'array',
        'live_news_ids' => 'array',
        'preview_news_ids' => 'array',
        'live_starts_at' => 'datetime',
        'live_ends_at' => 'datetime',
        'stats_updated_at' => 'datetime',
        'vistas_archive' => 'integer',
        'escuchas_locales' => 'integer',
        'vistas_totales' => 'integer',
    ];

    public function radioPrograms(): HasMany
    {
        return $this->hasMany(RadioProgram::class, 'master_program_id');
    }

    public function getNameAttribute(): string
    {
        return trim((string) ($this->nombre ?: ''));
    }

    public function getHostAttribute(): string
    {
        $host = trim((string) $this->conductor);
        if ($host === '') {
            return '';
        }

        $host = preg_replace('/^\s*(conducido\s+por\s*:?\s*|conduce\s*:?\s*|host\s*:?\s*)/iu', '', $host) ?: $host;
        $host = preg_replace('/\s+/', ' ', $host) ?: $host;

        return trim($host);
    }

    public function getDescriptionAttribute(): string
    {
        return trim((string) (
            $this->live_description
            ?: $this->descripcion
            ?: $this->comentario_predeterminado
            ?: ''
        ));
    }

    public function getCoverUrlAttribute(): string
    {
        if ($resolved = PublicMediaUrl::normalizePublicUrl($this->live_image_url ?: $this->caratula_url)) {
            return $resolved;
        }

        $cover = trim((string) ($this->live_image_url ?: $this->caratula_url));

        return $cover !== '' ? asset($cover) : '';
    }

    public function getScheduleAttribute(): string
    {
        $parts = array_filter([
            trim((string) $this->dia_transmision),
            trim((string) $this->hora_transmision),
        ]);

        return $parts !== [] ? implode(' · ', $parts) : 'Programación continua';
    }

    public function publicSlug(): string
    {
        return Str::slug($this->name ?: 'programa');
    }
}
