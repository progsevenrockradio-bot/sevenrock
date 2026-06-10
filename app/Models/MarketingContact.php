<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingContact extends Model
{
    protected $table = 'marketing_contacts';

    protected $fillable = [
        'email',
        'name',
        'company_or_band',
        'role',
        'is_active',
        'source_account_id',
        'source_type',
        'last_scraped_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_scraped_at' => 'datetime',
    ];

    /**
     * Get the source mail account from which this contact was scraped.
     */
    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(MarketingMailAccount::class, 'source_account_id');
    }
}
