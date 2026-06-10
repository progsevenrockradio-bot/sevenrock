<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingCampaign extends Model
{
    protected $table = 'marketing_campaigns';

    protected $fillable = [
        'subject',
        'sender_account_id',
        'template',
        'body_content',
        'button_text',
        'button_url',
        'status',
        'total_contacts',
        'sent_contacts',
    ];

    protected $casts = [
        'total_contacts' => 'integer',
        'sent_contacts' => 'integer',
    ];

    /**
     * Get the sender account that sent this campaign.
     */
    public function senderAccount(): BelongsTo
    {
        return $this->belongsTo(MarketingMailAccount::class, 'sender_account_id');
    }
}
