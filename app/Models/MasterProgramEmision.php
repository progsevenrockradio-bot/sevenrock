<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterProgramEmision extends Model
{
    protected $table = 'master_program_emisiones';

    protected $fillable = [
        'master_program_id',
        'tipo',
        'etiqueta',
        'dia_semana',
        'hora_inicio',
        'duracion_minutos',
        'enlace',
        'url_podcast',
        'notas',
        'activo',
    ];

    protected $casts = [
        'activo'           => 'boolean',
        'duracion_minutos' => 'integer',
    ];

    /** Tipos válidos de emisión. */
    public const TIPOS = [
        'normal'         => 'Normal',
        'retransmision'  => 'Retransmisión / Podcast',
        'en_vivo'        => 'En Vivo',
    ];

    /** Días válidos en mayúsculas (compatible con master_programs). */
    public const DIAS = [
        'LUNES'     => 'Lunes',
        'MARTES'    => 'Martes',
        'MIERCOLES' => 'Miércoles',
        'JUEVES'    => 'Jueves',
        'VIERNES'   => 'Viernes',
        'SABADO'    => 'Sábado',
        'DOMINGO'   => 'Domingo',
    ];

    public function masterProgram(): BelongsTo
    {
        return $this->belongsTo(MasterProgram::class, 'master_program_id');
    }

    /**
     * Scope que devuelve solo las emisiones activas.
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Etiqueta de visualización en la parrilla:
     * usa la etiqueta personalizada si existe, o el nombre del programa.
     */
    public function etiquetaParrilla(): string
    {
        if (filled($this->etiqueta)) {
            return $this->etiqueta;
        }

        return $this->masterProgram?->nombre ?? '';
    }

    /**
     * Hora en formato HH:MM (para uso en HTML y parrilla).
     */
    public function horaFormateada(): string
    {
        return substr((string) $this->hora_inicio, 0, 5);
    }
}
