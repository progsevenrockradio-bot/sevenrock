<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayHistory extends Model
{
    protected $table = 'play_history';

    protected $fillable = [
        'song_id',
        'program_id',
        'title',
        'artist',
        'cover_image',
        'source',
        'duration_seconds',
        'is_live',
        'metadata',
        'played_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'int',
            'is_live' => 'bool',
            'metadata' => 'array',
            'played_at' => 'datetime',
        ];
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
