<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationItem extends Model
{
    protected $fillable = [
        'type',
        'subject_type',
        'subject_id',
        'title',
        'summary',
        'payload',
        'submitter_name',
        'submitter_email',
        'submitter_ip',
        'source',
        'status',
        'decided_by',
        'decided_at',
        'decision_note',
        'notified_at',
        'notify_attempts',
        'last_notify_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'decided_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
    
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
