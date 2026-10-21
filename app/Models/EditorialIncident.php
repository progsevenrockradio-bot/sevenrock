<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditorialIncident extends Model
{
    protected $table = 'editorial_incidents';

    protected $fillable = [
        'post_id',
        'source_message_id',
        'original_message_id',
        'incident_type',
        'priority',
        'post_previous_status',
        'action_taken',
        'correlation_score',
        'correlation_evidence',
        'detected_diff',
        'resolution_status',
        'resolved_by_user_id',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'correlation_score'    => 'integer',
        'correlation_evidence' => 'array',
        'detected_diff'        => 'array',
        'resolved_at'          => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    /**
     * Comprueba si el incidente requiere atención urgente (noticia publicada o pausada).
     */
    public function isUrgent(): bool
    {
        return $this->priority === 'urgent' || $this->priority === 'high';
    }
}
