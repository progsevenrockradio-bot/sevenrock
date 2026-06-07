<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RadioProgramEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'radio_program_id',
        'event_type',
        'event_message',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function radioProgram(): BelongsTo
    {
        return $this->belongsTo(RadioProgram::class);
    }
}
