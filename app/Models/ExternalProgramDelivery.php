<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalProgramDelivery extends Model
{
    public const SOURCE_EMAIL = 'email';
    public const SOURCE_MANUAL = 'manual';

    public const PLATFORM_GOOGLE_DRIVE = 'google_drive';
    public const PLATFORM_SMASH = 'smash';
    public const PLATFORM_TRANSFERNOW = 'transfernow';
    public const PLATFORM_WETRANSFER = 'wetransfer';
    public const PLATFORM_OTHER = 'other';

    public const STATUS_PENDING = 'pending';
    public const STATUS_NEEDS_REVIEW = 'needs_review';
    public const STATUS_DOWNLOADED = 'downloaded';
    public const STATUS_DISCARDED = 'discarded';

    public const ID_MATCHED = 'matched';
    public const ID_AMBIGUOUS = 'ambiguous';
    public const ID_UNIDENTIFIED = 'unidentified';
    public const ID_MANUAL = 'manual';

    public const TELEGRAM_PENDING = 'pending';
    public const TELEGRAM_SENT = 'sent';
    public const TELEGRAM_FAILED = 'failed';
    public const TELEGRAM_SKIPPED = 'skipped';

    protected $table = 'external_program_deliveries';

    protected $fillable = [
        'master_program_id',
        'message_id',
        'mailbox',
        'folder',
        'imap_uid',
        'source_type',
        'sender_email',
        'sender_name',
        'raw_from_email',
        'reply_to_email',
        'subject',
        'received_at',
        'received_timezone',
        'platform',
        'delivery_url',
        'additional_links',
        'expires_at',
        'expiry_source',
        'auth_status',
        'auth_reason',
        'auth_details',
        'identification_status',
        'status',
        'incident_reason',
        'snoozed_until',
        'initial_notified_at',
        'telegram_status',
        'telegram_last_error',
        'telegram_notified_at',
        'last_reminded_at',
        'reminder_count',
        'expiry_alert_sent_at',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    protected $casts = [
        'master_program_id' => 'integer',
        'imap_uid' => 'integer',
        'received_at' => 'datetime',
        'additional_links' => 'array',
        'expires_at' => 'datetime',
        'auth_details' => 'array',
        'snoozed_until' => 'datetime',
        'initial_notified_at' => 'datetime',
        'telegram_notified_at' => 'datetime',
        'last_reminded_at' => 'datetime',
        'reminder_count' => 'integer',
        'expiry_alert_sent_at' => 'datetime',
        'resolved_at' => 'datetime',
        'resolved_by' => 'integer',
    ];

    public function masterProgram(): BelongsTo
    {
        return $this->belongsTo(MasterProgram::class, 'master_program_id');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeActivePending(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_NEEDS_REVIEW]);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_DOWNLOADED, self::STATUS_DISCARDED]);
    }

    public function isResolved(): bool
    {
        return in_array($this->status, [self::STATUS_DOWNLOADED, self::STATUS_DISCARDED], true);
    }

    public function isSnoozed(): bool
    {
        return $this->snoozed_until !== null && $this->snoozed_until->isFuture();
    }

    public function platformLabel(): string
    {
        return match ($this->platform) {
            self::PLATFORM_GOOGLE_DRIVE => 'Google Drive',
            self::PLATFORM_SMASH => 'Smash',
            self::PLATFORM_TRANSFERNOW => 'TransferNow',
            self::PLATFORM_WETRANSFER => 'WeTransfer',
            default => 'Enlace externo',
        };
    }

    public function statusLabel(): string
    {
        if (! $this->isResolved() && $this->isSnoozed()) {
            return 'Aplazado';
        }

        return match ($this->status) {
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_NEEDS_REVIEW => 'Requiere revisión',
            self::STATUS_DOWNLOADED => 'Descargado',
            self::STATUS_DISCARDED => 'Descartado',
            default => ucfirst((string) $this->status),
        };
    }

    public function programDisplayName(): string
    {
        return $this->masterProgram?->name ?: 'Pendiente de identificar';
    }

    public function formattedReceivedAt(): string
    {
        $tz = $this->effectiveTimezone();
        $dt = $this->received_at ?? $this->created_at;

        if ($dt === null) {
            return 'Fecha desconocida';
        }

        return $dt->copy()->setTimezone($tz)->format('d/m/Y H:i') . ' (' . $tz . ')';
    }

    public function formattedExpiresAt(): string
    {
        if ($this->expires_at === null) {
            return 'Caducidad desconocida';
        }

        $tz = $this->effectiveTimezone();

        return $this->expires_at->copy()->setTimezone($tz)->format('d/m/Y H:i') . ' (' . $tz . ')';
    }

    public function formattedSnoozedUntil(): ?string
    {
        if ($this->snoozed_until === null) {
            return null;
        }

        $tz = $this->effectiveTimezone();

        return $this->snoozed_until->copy()->setTimezone($tz)->format('d/m/Y H:i') . ' (' . $tz . ')';
    }

    public function effectiveTimezone(): string
    {
        $tz = trim((string) ($this->received_timezone ?: config('app.timezone', 'Europe/Madrid')));

        return in_array($tz, timezone_identifiers_list(), true)
            ? $tz
            : (string) config('app.timezone', 'Europe/Madrid');
    }
}

