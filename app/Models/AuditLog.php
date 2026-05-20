<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'actor_name',
        'actor_email',
        'actor_type',
        'category',
        'event',
        'level',
        'summary',
        'subject_type',
        'subject_id',
        'before_state',
        'after_state',
        'changes',
        'context',
        'request_payload',
        'request_meta',
        'response_meta',
        'method',
        'route_name',
        'url',
        'status_code',
        'duration_ms',
        'ip_address',
        'user_agent',
        'request_id',
    ];

    protected function casts(): array
    {
        return [
            'before_state' => 'array',
            'after_state' => 'array',
            'changes' => 'array',
            'context' => 'array',
            'request_payload' => 'array',
            'request_meta' => 'array',
            'response_meta' => 'array',
            'status_code' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
