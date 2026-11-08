<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrackSubmission extends Model
{
    use HasFactory, SoftDeletes;

    public const NOTICE_PENDING = 'pending';
    public const NOTICE_SENT = 'sent';
    public const NOTICE_FAILED = 'failed';
    public const NOTICE_UNKNOWN = 'unknown';
    public const NOTICE_SKIPPED = 'skipped';

    public const CONSENT_SOURCE_FORM = 'submission_form';
    public const CONSENT_TEXT_VERSION = 'muro_opt_in_v1_2026-11';
    public const CONSENT_TEXT = 'Quiero recibir información sobre los servicios y planes del Muro del Rock. Puedo darme de baja cuando quiera.';

    /**
     * Atributos que son asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'band_name',
        'song_title',
        'contact_email',
        'social_link',
        'file_path',
        'status',
        'published_to_hub',
        'new_release_id',
        'published_at',
        'publication_notice_status',
        'publication_notice_attempts',
        'publication_notice_sent_at',
        'publication_notice_error',
        'marketing_opt_in',
        'marketing_opt_in_at',
        'marketing_opt_in_source',
        'marketing_opt_in_version',
    ];

    protected function casts(): array
    {
        return [
            'published_to_hub' => 'boolean',
            'published_at' => 'datetime',
            'publication_notice_sent_at' => 'datetime',
            'marketing_opt_in' => 'boolean',
            'marketing_opt_in_at' => 'datetime',
        ];
    }

    /**
     * Indica si esta maqueta cuenta con consentimiento comercial explícito y registrado.
     * Los registros históricos (sin fecha ni versión) devuelven siempre false.
     */
    public function hasValidMarketingConsent(): bool
    {
        return $this->marketing_opt_in === true
            && $this->marketing_opt_in_at !== null
            && filled($this->marketing_opt_in_source)
            && filled($this->marketing_opt_in_version);
    }

    /**
     * Opcional: Mutadores genéricos o lógicas rápidas
     * Devuelve la URL pública del archivo si usas el disco r2
     */
    public function getFileUrlAttribute(): ?string
    {
        if ($this->file_path) {
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = \Illuminate\Support\Facades\Storage::disk('r2');
            return $disk->url($this->file_path);
        }
        
        return null;
    }

    /**
     * Get the email logs associated with the submission.
     */
    public function emailLogs()
    {
        return $this->hasMany(EmailLog::class);
    }

    /**
     * Lanzamiento vinculado de forma explícita (nunca por coincidencia de título/artista).
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(NewRelease::class, 'new_release_id');
    }

    /**
     * Seguimiento del Muro del Rock originado por esta maqueta.
     */
    public function muroFollowup(): HasOne
    {
        return $this->hasOne(SubmissionMuroFollowup::class, 'track_submission_id');
    }

    /**
     * Talento registrado vinculado mediante correo electrónico estricto.
     */
    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class, 'contact_email', 'email');
    }
}
