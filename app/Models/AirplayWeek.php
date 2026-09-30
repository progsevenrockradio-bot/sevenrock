<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AirplayWeek extends Model
{
    protected $table = 'airplay_weeks';

    protected $fillable = [
        'semana',
        'generado_en',
        'recibido_en',
        'pistas_total',
        'novedades',
        'hueco_publicidad_min',
        'estado',
    ];

    protected $casts = [
        'generado_en'          => 'datetime',
        'recibido_en'          => 'datetime',
        'pistas_total'         => 'integer',
        'novedades'            => 'integer',
        'hueco_publicidad_min' => 'integer',
    ];

    public function tracks(): HasMany
    {
        return $this->hasMany(AirplaySchedule::class, 'semana', 'semana');
    }

    /**
     * Devuelve el año e índice de semana ISO a partir de "YYYY-Www".
     */
    public static function parseWeekString(string $semana): ?array
    {
        if (! preg_match('/^(\d{4})-W(\d{2})$/', $semana, $m)) {
            return null;
        }

        return ['year' => (int) $m[1], 'week' => (int) $m[2]];
    }
}
