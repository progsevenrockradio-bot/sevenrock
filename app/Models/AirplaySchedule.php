<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AirplaySchedule extends Model
{
    public  = false;

    protected  = 'airplay_schedule';

    protected  = [
        'semana',
        'dia',
        'hora',
        'posicion',
        'artista',
        'titulo',
        'album',
        'genero',
        'categoria',
        'tipo_item',
        'duracion_seg',
        'duracion_fmt',
        'es_novedad',
        'es_primer_pase',
        'sello',
        'email_sello',
        'email_artista',
        'contacto_email',
        'spotify_track_id',
        'isrc',
        'talent_id',
        'sha1',
        'notificado_at',
        'avisos_enviados',
    ];

    protected  = [
        'dia'             => 'integer',
        'hora'            => 'integer',
        'posicion'        => 'integer',
        'duracion_seg'    => 'integer',
        'es_novedad'      => 'boolean',
        'es_primer_pase'  => 'boolean',
        'notificado_at'   => 'datetime',
        'avisos_enviados' => 'integer',
        'created_at'      => 'datetime',
    ];

    public function week(): BelongsTo
    {
        return ->belongsTo(AirplayWeek::class, 'semana', 'semana');
    }

    public function talent(): BelongsTo
    {
        return ->belongsTo(Talent::class);
    }

    public function notices(): HasMany
    {
        return ->hasMany(AirplayNotice::class, 'airplay_schedule_id');
    }

    public function getDiaNombreAttribute(): string
    {
         = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];

        return [->dia] ?? 'Día ' . ->dia;
    }

    public function getHoraFormateadaAttribute(): string
    {
        return sprintf('%02d:00', ->hora);
    }
}
