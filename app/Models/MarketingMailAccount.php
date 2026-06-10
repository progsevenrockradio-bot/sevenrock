<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingMailAccount extends Model
{
    protected $table = 'marketing_mail_accounts';

    protected $fillable = [
        'email',
        'sender_name',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_password',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_password',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'imap_port' => 'integer',
        'smtp_port' => 'integer',
        'imap_password' => 'encrypted',
        'smtp_password' => 'encrypted',
    ];

    /**
     * Get contacts scraped from this account.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(MarketingContact::class, 'source_account_id');
    }

    /**
     * Get campaigns sent from this account.
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(MarketingCampaign::class, 'sender_account_id');
    }
}
