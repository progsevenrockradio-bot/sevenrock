<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutreachLog extends Model
{
    protected $fillable = [
        'campaign_id',
        'band_contact_id',
        'template_id',
        'recipient_email',
        'subject',
        'body',
        'status',
        'opened_at',
        'responded_at',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'campaign_id' => 'integer',
            'band_contact_id' => 'integer',
            'template_id' => 'integer',
            'opened_at' => 'datetime',
            'responded_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(OutreachCampaign::class, 'campaign_id');
    }

    public function bandContact(): BelongsTo
    {
        return $this->belongsTo(BandContact::class, 'band_contact_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(OutreachTemplate::class, 'template_id');
    }
}
