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
        'batch_id',
        'element_id',
        'payload_hash',
        'sender_email',
        'received_at',
        'musical_release_date',
        'event_date',
        'artist_name',
        'sources',
        'approved_urls',
        'approved_at',
        'approved_by',
        'image_credit',
        'image_alt',
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
        'corrects_element_id',
    ];

    protected function casts(): array
    {
        return [
            'received_at'          => 'datetime',
            'musical_release_date' => 'date',
            'embargo_until'        => 'datetime',
            'publish_permitted_at' => 'datetime',
            'is_embargo_active'    => 'boolean',
            'approved_urls'        => 'array',
            'approved_at'          => 'datetime',
            'approved_by'          => 'integer',
            'redacted_links'       => 'array',
            'media_inventory'      => 'array',
        ];
    }

    /**
     * Registra URLs concretas verificadas como aprobadas para esta publicación con autoría y fecha.
     *
     * @param array<int, string> $urls
     */
    public function registerApprovedUrls(array $urls, ?int $userId = null): void
    {
        $existing = is_array($this->approved_urls) ? $this->approved_urls : [];
        $clean = [];
        foreach ($urls as $url) {
            $u = trim((string) $url);
            if ($u !== '') {
                $clean[] = $u;
            }
        }
        $this->approved_urls = array_values(array_unique(array_merge($existing, $clean)));
        $this->approved_at = now();
        if ($userId) {
            $this->approved_by = $userId;
        }
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }
}
