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
        'duracion_segundos',
        'enlace',
        'url_podcast',
        'notas',
        'activo',
    ];

    protected $casts = [
        'activo'            => 'boolean',
        'duracion_minutos'  => 'integer',
        'duracion_segundos' => 'integer',
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

    /**
     * Duración real formateada en minutos y segundos (ej. "62m 15s" o "1h 02m 15s").
     */
    public function duracionRealFormateada(): ?string
    {
        if ($this->duracion_segundos === null || $this->duracion_segundos <= 0) {
            return null;
        }

        $minutos = (int) floor($this->duracion_segundos / 60);
        $segundos = (int) ($this->duracion_segundos % 60);

        if ($minutos >= 60) {
            $horas = (int) floor($minutos / 60);
            $minutosRestantes = $minutos % 60;
            return sprintf('%dh %02dm %02ds', $horas, $minutosRestantes, $segundos);
        }

        return sprintf('%dm %02ds', $minutos, $segundos);
    }

    /**
     * Sincroniza la duración real en segundos de la emisión correspondiente para un programa maestro.
     */
    public static function syncRealDurationForMaster(MasterProgram $master, ?string $fechaEmision, int $durationSeconds): ?self
    {
        if ($durationSeconds <= 0) {
            return null;
        }

        $diaKey = null;
        if ($fechaEmision) {
            try {
                $date = \Carbon\Carbon::parse($fechaEmision);
                $diaKey = match ($date->dayOfWeekIso) {
                    1 => 'LUNES',
                    2 => 'MARTES',
                    3 => 'MIERCOLES',
                    4 => 'JUEVES',
                    5 => 'VIERNES',
                    6 => 'SABADO',
                    7 => 'DOMINGO',
                };
            } catch (\Throwable) {
                $diaKey = null;
            }
        }

        $query = $master->emisiones();
        $emision = null;
        if ($diaKey) {
            $emision = (clone $query)->where('dia_semana', $diaKey)->first();
        }
        if (! $emision) {
            $emision = (clone $query)->where('tipo', 'retransmision')->first();
        }
        if (! $emision) {
            $emision = (clone $query)->first();
        }

        if ($emision) {
            $emision->update(['duracion_segundos' => $durationSeconds]);
            return $emision;
        }

        return null;
    }
}
