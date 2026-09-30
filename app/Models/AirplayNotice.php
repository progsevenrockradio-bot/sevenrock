<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AirplayNotice extends Model
{
    protected $table = 'airplay_notices';

    protected $fillable = [
        'airplay_schedule_id',
        'email',
        'tipo',
        'enviado_en',
        'estado',
        'error',
    ];

    protected $casts = [
        'enviado_en' => 'datetime',
    ];

    public function track(): BelongsTo
    {
        return $this->belongsTo(AirplaySchedule::class, 'airplay_schedule_id');
    }
}
