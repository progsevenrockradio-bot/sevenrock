<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionMuroFollowup extends Model
{
    public const CAMPAIGN_DEFAULT = 'muro_rock_basic_v1';

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_UNKNOWN = 'unknown';

    public const REASON_HISTORIC_NO_CONSENT = 'historic_no_consent';
    public const REASON_MISSING_CONSENT = 'missing_consent';
    public const REASON_UNSUBSCRIBED = 'unsubscribed';
    public const REASON_ACTIVE_PAID_PLAN = 'active_paid_plan';
    public const REASON_RELEASE_WITHDRAWN = 'release_withdrawn';
    public const REASON_INVALID_EMAIL = 'invalid_email';
    public const REASON_ADMIN_PAUSED = 'admin_paused';
    public const REASON_ADMIN_CANCELLED = 'admin_cancelled';
    public const REASON_READY_WAIT_WINDOW = 'waiting_delay_window';
    public const REASON_SENT_OK = 'sent_ok';
    public const REASON_SEND_FAILED = 'send_failed';
    public const REASON_LOG_FAILED = 'log_failed_after_send';

    protected $table = 'submission_muro_followups';

    protected $fillable = [
        'campaign_key',
        'email_normalized',
        'contact_email',
        'band_name',
        'track_submission_id',
        'new_release_id',
        'status',
        'status_reason',
        'status_detail',
        'first_published_at',
        'scheduled_for',
        'sent_at',
        'paused_at',
        'cancelled_at',
        'attempts',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'first_published_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'paused_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function trackSubmission(): BelongsTo
    {
        return $this->belongsTo(TrackSubmission::class, 'track_submission_id');
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(NewRelease::class, 'new_release_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SCHEDULED => 'Programado',
            self::STATUS_PAUSED => 'Pausado',
            self::STATUS_CANCELLED => 'Cancelado',
            self::STATUS_SKIPPED => 'Excluido',
            self::STATUS_SENDING => 'Enviando',
            self::STATUS_SENT => 'Enviado',
            self::STATUS_FAILED => 'Fallo de envío',
            self::STATUS_UNKNOWN => 'Por revisar (incierto)',
            default => ucfirst((string) $this->status),
        };
    }

    public function reasonLabel(): string
    {
        return match ($this->status_reason) {
            self::REASON_HISTORIC_NO_CONSENT => 'Contacto histórico sin consentimiento registrado',
            self::REASON_MISSING_CONSENT => 'No marcó la casilla opcional de consentimiento',
            self::REASON_UNSUBSCRIBED => 'Contacto dado de baja de comunicaciones promocionales',
            self::REASON_ACTIVE_PAID_PLAN => 'La banda ya tiene un plan de pago activo en el Muro del Rock',
            self::REASON_RELEASE_WITHDRAWN => 'La publicación asociada fue retirada o ya no es accesible públicamente',
            self::REASON_INVALID_EMAIL => 'Dirección de correo electrónico no válida',
            self::REASON_ADMIN_PAUSED => 'Pausado manualmente por administración',
            self::REASON_ADMIN_CANCELLED => 'Cancelado manualmente por administración',
            self::REASON_READY_WAIT_WINDOW => 'Programado para 3 días después de la primera publicación efectiva',
            self::REASON_SENT_OK => 'Invitación enviada correctamente',
            self::REASON_SEND_FAILED => 'Fallo confirmado del servidor de correo (reintentable)',
            self::REASON_LOG_FAILED => 'Correo entregado pero falló el registro local (no reenviar automáticamente)',
            default => (string) ($this->status_detail ?: $this->status_reason ?: '—'),
        };
    }
}

