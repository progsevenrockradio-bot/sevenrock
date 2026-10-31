<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RadioProgram extends Model
{
    use Auditable;
    public const EDITORIAL_STATUS_NOT_REQUIRED = 'not_required';
    public const EDITORIAL_STATUS_PENDING = 'pending_editorial_review';
    public const EDITORIAL_STATUS_RETAINED = 'retained_incident';
    public const EDITORIAL_STATUS_APPROVED = 'approved';
    public const EDITORIAL_STATUS_APPROVED_AUTONOMOUS = 'approved_autonomous';
    public const EDITORIAL_STATUS_CHANGES_REQUESTED = 'changes_requested';

    protected $table = 'radio_programs';

    protected $fillable = [
        'numero_episodio',
        'titulo_programa',
        'master_program_id',
        'submitted_by_user_id',
        'dia_transmision',
        'hora_inicio',
        'hora_fin',
        'conductor',
        'pais',
        'fecha_emision',
        'duration_seconds',
        'biografia_invitado',
        'archivo_mp3',
        'archivo_mp3_disk',
        'enviado_radioboss',
        'radioboss_status',
        'schedule_inclusion_status',
        'radioboss_verified_at',
        'radioboss_last_error',
        'radioboss_metadata',
        'radioboss_started_at',
        'radioboss_finished_at',
        'radioboss_notification_sent_at',
        'sync_archive_org',
        'allow_public_download',
        'late_submission_authorized_by',
        'late_submission_authorized_at',
        'late_submission_reason',
        'archive_org_status',
        'archive_org_remote_path',
        'archive_org_uploaded_at',
        'archive_org_verified_at',
        'archive_org_last_error',
        'archive_org_metadata',
        'archive_started_at',
        'archive_finished_at',
        'archive_notification_sent_at',
        'delivery_status',
        'editorial_status',
        'editorial_version',
        'editorial_version_token',
        'editorial_original_payload',
        'editorial_checks',
        'editorial_ai_proposal',
        'editorial_approved_at',
        'editorial_approved_by',
        'editorial_approved_version',
        'delivery_verified_at',
        'delivery_last_error',
        'delivery_metadata',
        'processing_started_at',
        'processing_finished_at',
        'status_message',
        'informacion_fija_programa',
        'genero_musical',
        'facebook_url',
        'instagram_url',
        'caratula_programa',
        'imagen_episodio',
        'imagen_invitado',
        'ruta_ftp_radioboss',
        'resena',
        'live_title',
        'editorial_summary',
        'live_description',
        'live_image_url',
        'live_news_ids',
        'preview_news_ids',
        'comentario_episodio',
        'email_notificacion',
    ];

    protected static function booted(): void
    {
        $bustCatalog = static function (): void {
            \Illuminate\Support\Facades\Cache::forget('archive-org:home-podcasts:v5:10');
            \Illuminate\Support\Facades\Cache::forget('archive-org:home-podcasts:v5:12');
            \Illuminate\Support\Facades\Cache::forget('archive-org:home-podcasts:v5:20');
        };

        static::saved($bustCatalog);
        static::deleted($bustCatalog);
    }

    protected function casts(): array
    {
        return [
            'numero_episodio' => 'integer',
            'master_program_id' => 'integer',
            'submitted_by_user_id' => 'integer',
            'fecha_emision' => 'date',
            'duration_seconds' => 'integer',
            'enviado_radioboss' => 'boolean',
            'radioboss_verified_at' => 'datetime',
            'radioboss_started_at' => 'datetime',
            'radioboss_finished_at' => 'datetime',
            'radioboss_notification_sent_at' => 'datetime',
            'radioboss_metadata' => 'array',
            'sync_archive_org' => 'boolean',
            'allow_public_download' => 'boolean',
            'late_submission_authorized_by' => 'integer',
            'late_submission_authorized_at' => 'datetime',
            'archive_org_uploaded_at' => 'datetime',
            'archive_org_verified_at' => 'datetime',
            'archive_started_at' => 'datetime',
            'archive_finished_at' => 'datetime',
            'archive_notification_sent_at' => 'datetime',
            'archive_org_metadata' => 'array',
            'editorial_version' => 'integer',
            'editorial_original_payload' => 'array',
            'editorial_checks' => 'array',
            'editorial_ai_proposal' => 'array',
            'editorial_approved_at' => 'datetime',
            'editorial_approved_by' => 'integer',
            'editorial_approved_version' => 'integer',
            'delivery_verified_at' => 'datetime',
            'delivery_metadata' => 'array',
            'processing_started_at' => 'datetime',
            'processing_finished_at' => 'datetime',
            'live_news_ids' => 'array',
            'preview_news_ids' => 'array',
        ];
    }

    public function getArchivoMp3DiskAttribute(?string $value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : 'public';
    }

    public function storageFolderSlug(): string
    {
        $metadataFolder = trim((string) data_get($this->delivery_metadata, 'storage_folder', ''));
        if ($metadataFolder !== '') {
            return MasterProgram::normalizeStorageFolder($metadataFolder);
        }

        $master = $this->relationLoaded('masterProgram')
            ? $this->masterProgram
            : ($this->master_program_id ? MasterProgram::query()->find($this->master_program_id) : null);

        if ($master instanceof MasterProgram) {
            return $master->resolveStorageFolder();
        }

        return MasterProgram::normalizeStorageFolder(
            (string) ($this->titulo_programa ?: 'programa-' . ($this->master_program_id ?: $this->id ?: 'general'))
        );
    }

    public function readableDownloadFilename(): string
    {
        $stored = ltrim(str_replace('\\', '/', trim((string) ($this->archivo_mp3 ?? ''))), '/');
        if (str_starts_with($stored, 'programas_procesados/')) {
            $base = basename($stored);
            if (preg_match('/^\d{3}\.-\s+.+\.mp3$/iu', $base)) {
                return $base;
            }
        }

        return $this->buildCanonicalFilename();
    }

    public function buildCanonicalFilename(): string
    {
        $episodeNumber = max(1, (int) ($this->numero_episodio ?? 0));
        $masterName = $this->relationLoaded('masterProgram')
            ? $this->masterProgram?->nombre
            : ($this->master_program_id ? MasterProgram::query()->whereKey($this->master_program_id)->value('nombre') : null);

        $programName = strtoupper(trim((string) ($this->titulo_programa ?: $masterName ?: $this->live_title ?: 'PODCAST')));
        $safeName = preg_replace('/[\/\\\\:*?"<>|]/u', '-', $programName);
        $safeName = trim((string) $safeName);
        if ($safeName === '') {
            $safeName = 'PODCAST';
        }

        $fecha = $this->fecha_emision
            ? $this->fecha_emision->format('d-m-Y')
            : ($this->created_at ? $this->created_at->format('d-m-Y') : now()->format('d-m-Y'));

        $paddedEpisode = str_pad((string) $episodeNumber, 3, '0', STR_PAD_LEFT);

        return "{$paddedEpisode}.- {$safeName} {$fecha}.mp3";
    }

    public function canonicalProcessedPath(): string
    {
        return 'programas_procesados/' . $this->storageFolderSlug() . '/' . $this->buildCanonicalFilename();
    }

    public static function isProcessedStoragePath(?string $path): bool
    {
        $normalized = ltrim(str_replace('\\', '/', trim((string) $path)), '/');
        if (! str_starts_with($normalized, 'programas_procesados/')) {
            return false;
        }

        $base = basename($normalized);

        return (bool) preg_match('/^\d{3}\.-\s+.+\.mp3$/iu', $base);
    }

    public function isProcessedPath(?string $path = null): bool
    {
        return self::isProcessedStoragePath($path ?? $this->archivo_mp3);
    }

    public function hasPreparedBroadcastAudio(): bool
    {
        if (! $this->isProcessedPath()) {
            return false;
        }

        return $this->archivo_mp3_disk === 'r2'
            || (bool) data_get($this->delivery_metadata, 'r2_verified', false)
            || (bool) $this->enviado_radioboss
            || $this->radioboss_status === 'radioboss_verified';
    }

    public function isEditorialApproved(): bool
    {
        return in_array(
            trim((string) ($this->editorial_status ?: self::EDITORIAL_STATUS_NOT_REQUIRED)),
            [self::EDITORIAL_STATUS_NOT_REQUIRED, self::EDITORIAL_STATUS_APPROVED, self::EDITORIAL_STATUS_APPROVED_AUTONOMOUS],
            true
        );
    }

    /**
     * Distingue explícitamente aprobación editorial, procesamiento del audio,
     * entrega al FTP de RadioBOSS e inclusión en la programación de RadioBOSS.
     *
     * @return array<string, array<string, mixed>>
     */
    public function operationalStates(): array
    {
        $editorialStatus = trim((string) ($this->editorial_status ?: self::EDITORIAL_STATUS_NOT_REQUIRED));
        $isProcessedInR2 = $this->isProcessedPath() && ($this->archivo_mp3_disk === 'r2' || (bool) data_get($this->delivery_metadata, 'r2_verified', false));

        if ($isProcessedInR2) {
            $audioProcessingState = 'processed_in_r2';
        } elseif ($this->processing_started_at !== null && $this->processing_finished_at === null) {
            $audioProcessingState = 'processing';
        } elseif ($this->delivery_status === 'delivery_failed' && ! $this->isProcessedPath()) {
            $audioProcessingState = 'failed';
        } else {
            $audioProcessingState = 'pending';
        }

        $radiobossDeliveryState = trim((string) ($this->radioboss_status ?: ($this->enviado_radioboss ? 'radioboss_verified' : 'radioboss_pending')));
        $scheduleInclusionState = trim((string) ($this->schedule_inclusion_status ?: 'pending_schedule'));
        if ($scheduleInclusionState === 'not_scheduled') {
            $scheduleInclusionState = 'pending_schedule';
        }

        return [
            'editorial_approval' => [
                'label' => '1. Aprobación editorial',
                'state' => $editorialStatus,
                'status' => $editorialStatus,
                'description' => $this->isEditorialApproved()
                    ? 'Aprobado editorialmente (v' . (int) ($this->editorial_version ?: 1) . ')'
                    : ($editorialStatus === self::EDITORIAL_STATUS_RETAINED ? 'Retenido por incidencia para revisión' : 'Pendiente de revisión editorial'),
                'is_approved' => $this->isEditorialApproved(),
                'version' => (int) ($this->editorial_version ?: 1),
                'approved_version' => $this->editorial_approved_version,
                'approved_at' => $this->editorial_approved_at?->toIso8601String(),
            ],
            'audio_processing' => [
                'label' => '2. Procesamiento de audio (R2)',
                'state' => $audioProcessingState,
                'status' => $audioProcessingState,
                'description' => $isProcessedInR2
                    ? 'MP3 definitivo etiquetado en Cloudflare R2'
                    : 'Pendiente de procesamiento definitivo en R2',
                'is_processed_in_r2' => $isProcessedInR2,
                'disk' => $this->archivo_mp3_disk,
                'path' => $this->archivo_mp3,
            ],
            'radioboss_delivery' => [
                'label' => '3. Entrega a RadioBOSS (FTP)',
                'state' => $radiobossDeliveryState,
                'status' => $radiobossDeliveryState,
                'description' => $radiobossDeliveryState === 'radioboss_verified'
                    ? 'Archivo transferido y verificado en FTP de RadioBOSS'
                    : 'Pendiente de transferencia a RadioBOSS',
                'is_delivered_ftp' => $radiobossDeliveryState === 'radioboss_verified' || (bool) $this->enviado_radioboss,
                'verified_at' => $this->radioboss_verified_at?->toIso8601String(),
            ],
            'radioboss_schedule_inclusion' => [
                'label' => '4. Inclusión en programación RadioBOSS',
                'state' => $scheduleInclusionState,
                'status' => $scheduleInclusionState,
                'description' => in_array($scheduleInclusionState, ['scheduled_verified', 'scheduled_in_radioboss'], true)
                    ? 'Incluido en la parrilla de programación de RadioBOSS'
                    : 'Pendiente de inclusión en programación (separado de la entrega FTP)',
                'is_scheduled_in_grid' => in_array($scheduleInclusionState, ['scheduled_verified', 'scheduled_in_radioboss'], true),
            ],
        ];
    }

    public function audioPlaybackUrl(): string
    {
        $stored = ltrim(str_replace('\\', '/', trim((string) ($this->archivo_mp3 ?? ''))), '/');
        if ($stored === '') {
            return '';
        }

        $disk = trim((string) $this->archivo_mp3_disk) !== '' ? trim((string) $this->archivo_mp3_disk) : 'r2';

        return app(\App\Services\FileUploadService::class)->url($stored, $disk);
    }

    public function isApproved(): bool
    {
        return in_array($this->editorial_status, [
            self::EDITORIAL_STATUS_APPROVED,
            self::EDITORIAL_STATUS_APPROVED_AUTONOMOUS,
        ], true);
    }

    public function isProcessedInR2(): bool
    {
        return $this->archivo_mp3_disk === 'r2'
            && self::isProcessedStoragePath($this->archivo_mp3)
            && (bool) data_get($this->delivery_metadata, 'r2_verified', false);
    }

    public function hasActiveLateSubmissionException(): bool
    {
        return $this->late_submission_authorized_by !== null && $this->late_submission_authorized_at !== null;
    }

    public function isPublicDownloadAllowed(): bool
    {
        return (bool) $this->allow_public_download && $this->isApproved() && $this->isProcessedInR2();
    }

    public function canConductorShareAndDownload(): bool
    {
        return $this->isApproved() && $this->isProcessedInR2();
    }

    public function publicShareUrl(): string
    {
        $master = $this->relationLoaded('masterProgram') ? $this->masterProgram : $this->masterProgram()->first();
        $slug = $master?->publicSlug() ?: MasterProgram::normalizeStorageFolder((string) $this->titulo_programa);

        return route('public.podcasts.episode', [
            'programSlug' => $slug,
            'episodeNumber' => (int) ($this->numero_episodio ?? 1),
        ]);
    }

    public function emissionDeadlineInfo(): array
    {
        $master = $this->relationLoaded('masterProgram') ? $this->masterProgram : $this->masterProgram()->first();
        if (! $master instanceof MasterProgram) {
            return [
                'status' => 'undetermined',
                'reason' => 'Episodio no vinculado a un Programa Maestro.',
                'cutoff_minutes' => 90,
                'timezone' => config('app.timezone', 'Europe/Madrid'),
                'is_closed' => false,
                'emission_datetime' => null,
                'emission_formatted' => null,
                'deadline_datetime' => null,
                'deadline_formatted' => null,
                'crosses_midnight' => false,
                'remaining_seconds' => 0,
                'remaining_human' => 'Horario no determinado',
            ];
        }

        return $master->calculateEmissionDeadline($this->fecha_emision);
    }

    public function lateSubmissionAuthorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'late_submission_authorized_by');
    }

    public function masterProgram(): BelongsTo
    {
        return $this->belongsTo(MasterProgram::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(RadioProgramEvent::class);
    }
}
