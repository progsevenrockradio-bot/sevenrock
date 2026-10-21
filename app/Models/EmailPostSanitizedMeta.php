<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailPostSanitizedMeta extends Model
{
    protected $table = 'email_post_sanitized_meta';

    protected $fillable = [
        'post_id',
        'source_message_id',
        'sender_email',
        'received_at',
        'musical_release_date',
        'embargo_until',
        'publish_permitted_at',
        'is_embargo_active',
        'editorial_notes',
        'security_status',
        'incident_reason',
        'redacted_links',
        'media_inventory',
        'source_subject',
        'superseded_by_message_id',
    ];

    protected function casts(): array
    {
        return [
            'received_at'          => 'datetime',
            'musical_release_date' => 'date',
            'embargo_until'        => 'datetime',
            'publish_permitted_at' => 'datetime',
            'is_embargo_active'    => 'boolean',
            'redacted_links'       => 'array',
            'media_inventory'      => 'array',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }
}
