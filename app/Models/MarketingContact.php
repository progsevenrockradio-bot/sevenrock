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
        'unsubscribed_at',
        'unsubscribe_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_scraped_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (MarketingContact $contact) {
            if (empty($contact->unsubscribe_token)) {
                $contact->unsubscribe_token = bin2hex(random_bytes(32));
            }
        });
    }

    /**
     * Get the source mail account from which this contact was scraped.
     */
    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(MarketingMailAccount::class, 'source_account_id');
    }

    public static function getUnsubscribeTokenForEmail(string $email): string
    {
        $contact = self::firstOrCreate(
            ['email' => $email],
            [
                'name' => explode('@', $email)[0],
                'is_active' => true,
                'source_type' => 'auto_generated',
            ]
        );

        if (empty($contact->unsubscribe_token)) {
            $contact->update(['unsubscribe_token' => bin2hex(random_bytes(32))]);
        }

        return $contact->unsubscribe_token;
    }

    public static function isEmailUnsubscribed(string $email): bool
    {
        return self::where('email', $email)
            ->where(function ($q) {
                $q->where('is_active', false)->orWhereNotNull('unsubscribed_at');
            })->exists();
    }
}
