<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EmailSenderSource extends Model
{
    protected $table = 'email_sender_sources';

    protected $fillable = [
        'name',
        'slug',
        'organization_type',
        'agency_id',
        'authorized_emails',
        'verified_domains',
        'status',
        'auto_publish_allowed',
        'authorized_at',
        'authorized_by',
        'incidents_count',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'authorized_emails'    => 'array',
            'verified_domains'     => 'array',
            'auto_publish_allowed' => 'boolean',
            'authorized_at'        => 'datetime',
            'incidents_count'      => 'integer',
        ];
    }

    public static function boot(): void
    {
        parent::boot();

        static::creating(function (EmailSenderSource $source) {
            if (empty($source->slug)) {
                $source->slug = Str::slug($source->name);
            }
            if ($source->authorized_emails === null) {
                $source->authorized_emails = [];
            }
            if ($source->verified_domains === null) {
                $source->verified_domains = [];
            }
        });
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function scopeAuthorized(Builder $query): Builder
    {
        return $query->where('status', 'authorized');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeBlocked(Builder $query): Builder
    {
        return $query->where('status', 'blocked');
    }

    public function scopeSuspended(Builder $query): Builder
    {
        return $query->where('status', 'suspended');
    }

    public function isAuthorized(): bool
    {
        return $this->status === 'authorized';
    }

    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Verifica si una dirección de correo exacta está registrada en la lista de correos autorizados.
     */
    public function matchesEmail(string $email): bool
    {
        $email = strtolower(trim($email));
        $emails = array_map('strtolower', array_map('trim', $this->authorized_emails ?? []));

        return in_array($email, $emails, true);
    }

    /**
     * Verifica si un dominio coincide exactamente con los dominios verificados.
     */
    public function matchesDomain(string $domain): bool
    {
        $domain = ltrim(strtolower(trim($domain)), '@');
        $verified = array_map(function ($d) {
            return ltrim(strtolower(trim($d)), '@');
        }, $this->verified_domains ?? []);

        return in_array($domain, $verified, true);
    }
}
