<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\PublicMediaUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NewRelease extends Model
{
    use Auditable;

    protected static function booted(): void
    {
        $bumpVersion = static function (): void {
            \Illuminate\Support\Facades\Cache::forever('cache.version.new_releases', now()->timestamp);
        };

        static::saved($bumpVersion);
        static::deleted($bumpVersion);

        static::saved(static function (NewRelease $newRelease): void {
            $wasActive = filter_var($newRelease->getOriginal('is_active'), FILTER_VALIDATE_BOOLEAN);
            $isActiveNow = filter_var($newRelease->is_active, FILTER_VALIDATE_BOOLEAN);

            $newlyActive = $newRelease->wasRecentlyCreated && $isActiveNow;
            $updatedToActive = ! $wasActive && $isActiveNow;

            if ($newlyActive || $updatedToActive) {
                // Las maquetas publicadas desde el panel (origin='submission') tienen su
                // propio circuito de aviso (App\Services\SubmissionPublisher), que se envía
                // tras confirmar la transacción y deja estado reintentable. Aquí se omite
                // para no duplicar el correo; el resto de orígenes conserva su comportamiento.
                if ($newRelease->origin !== 'submission') {
                    $newRelease->sendPublishedNotification();
                }

                if ($newRelease->show_in_feed) {
                    try {
                    \App\Models\CommunityPost::query()->create([
                        'user_id' => null,
                        'talent_id' => null,
                        'content' => "📢 ¡Nuevo Lanzamiento en la señal de Seven Rock Radio! 🎸\n\nEscucha \"{$newRelease->title}\" de {$newRelease->artist_name}. Descubre la reseña completa, su música y redes sociales ingresando al enlace:\n" . route('new-releases.single', $newRelease->slug),
                        'youtube_url' => $newRelease->youtube_url ?: null,
                    ]);
                } catch (\Throwable $e) {
                    Log::error("Fallo al crear post automático en Muro para lanzamiento ID {$newRelease->id}: " . $e->getMessage());
                }
                }
            }
        });
    }

    public const REVIEW_PENDING = 'pending_review';
    public const REVIEW_PUBLISHED = 'published';
    public const REVIEW_REJECTED = 'rejected';

    /** @var array<string, string> */
    public const RELEASE_TYPES = [
        'single' => 'Single',
        'ep' => 'EP',
        'album' => 'Álbum',
        'other' => 'Otro',
    ];

    protected $fillable = [
        'title',
        'slug',
        'artist_name',
        'release_type',
        'radio_artist_id',
        'released_at',
        'announced_at',
        'published_at',
        'cover_image',
        'cover_source',
        'cover_permission_credit',
        'audio_path',
        'youtube_url',
        'spotify_url',
        'official_url',
        'download_url',
        'description',
        'proposal_summary',
        'is_active',
        'review_status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'received_at',
        'is_embargo_active',
        'embargo_until',
        'usage_conditions',
        'intake_metadata',
        'source_subject',
        'source_message_id',
        'source_sender_email',
        'source_sender_auth',
        'security_status',
        'incident_reason',
        'show_in_feed',
        'author_email',
        'notification_sender',
        'origin',
    ];

    protected function casts(): array
    {
        return [
            'released_at'        => 'date',
            'announced_at'       => 'date',
            'published_at'       => 'datetime',
            'reviewed_at'        => 'datetime',
            'received_at'        => 'datetime',
            'embargo_until'      => 'datetime',
            'is_active'          => 'boolean',
            'is_embargo_active'  => 'boolean',
            'show_in_feed'       => 'boolean',
            'reviewed_by'        => 'integer',
            'source_sender_auth' => 'array',
            'intake_metadata'    => 'array',
        ];
    }

    public function getUrlAttribute(): string
    {
        return route('new-releases.single', $this->slug);
    }

    /**
     * Criterio público único de la ficha: activo, publicado efectivamente y sin embargo vigente.
     *
     * - Permite anunciar un álbum/single con fecha musical futura (`released_at > now()`)
     *   siempre que esté aprobado, activo y sin embargo vigente (la vista lo identifica como
     *   «Lanzamiento previsto para…», nunca como ya editado).
     * - Excluye borradores, pendientes de revisión, rechazados, retenciones de seguridad y embargos.
     */
    public function scopePubliclyAccessible(Builder $query): Builder
    {
        $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q
                ->where('is_embargo_active', false)
                ->orWhereNull('is_embargo_active')
                ->orWhere(fn (Builder $e) => $e->whereNotNull('embargo_until')->where('embargo_until', '<=', now())))
            ->where(fn (Builder $q) => $q
                ->whereNull('security_status')
                ->orWhereNotIn('security_status', self::SECURITY_HOLD_STATUSES));

        if (Schema::hasColumn($this->getTable(), 'review_status')) {
            $query->where(fn (Builder $q) => $q
                ->whereNull('review_status')
                ->orWhere('review_status', self::REVIEW_PUBLISHED));
        }

        if (Schema::hasColumn($this->getTable(), 'published_at')) {
            $query->where(fn (Builder $q) => $q
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now()));
        }

        return $query;
    }

    public function isPubliclyAccessible(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->hasSecurityHold()) {
            return false;
        }

        if ($this->review_status !== null && $this->review_status !== self::REVIEW_PUBLISHED) {
            return false;
        }

        if ($this->published_at !== null && $this->published_at->isFuture()) {
            return false;
        }

        if ($this->is_embargo_active) {
            return $this->embargo_until !== null && ! $this->embargo_until->isFuture();
        }

        return true;
    }

    public function effectiveReviewStatus(): string
    {
        $explicit = trim((string) ($this->review_status ?? ''));
        if (in_array($explicit, [self::REVIEW_PENDING, self::REVIEW_PUBLISHED, self::REVIEW_REJECTED], true)) {
            return $explicit;
        }

        if ((string) $this->security_status === 'rejected') {
            return self::REVIEW_REJECTED;
        }

        return $this->is_active ? self::REVIEW_PUBLISHED : self::REVIEW_PENDING;
    }

    public function releaseTypeLabel(): string
    {
        $key = trim((string) ($this->release_type ?? ''));

        return self::RELEASE_TYPES[$key] ?? 'Sin clasificar';
    }

    /**
     * Lista explícita de datos faltantes para revisión editorial (sin inventar valores).
     *
     * @return array<int, string>
     */
    public function missingFieldsSummary(): array
    {
        $missing = [];
        if (blank($this->cover_image)) {
            $missing[] = 'Portada';
        } elseif (blank($this->cover_permission_credit)) {
            $missing[] = 'Permiso o crédito de portada';
        }
        if ($this->released_at === null) {
            $missing[] = 'Fecha de lanzamiento musical';
        }
        if (blank($this->release_type)) {
            $missing[] = 'Tipo (single/EP/álbum)';
        }
        if (blank($this->description)) {
            $missing[] = 'Reseña editorial verificada';
        }
        if (blank($this->official_url) && blank($this->spotify_url) && blank($this->youtube_url)) {
            $missing[] = 'Enlaces oficiales o de escucha';
        }

        return $missing;
    }

    /**
     * Detecta posibles duplicados por Message-ID, artista+título normalizados o enlaces de origen.
     * Nunca fusiona automáticamente un single con un álbum del mismo nombre.
     *
     * @return \Illuminate\Support\Collection<int, array{release: self, reason: string, different_type: bool}>
     */
    public function findPotentialDuplicates(): \Illuminate\Support\Collection
    {
        $candidates = static::query()
            ->where('id', '!=', $this->id)
            ->where(function (Builder $q): void {
                $hasCondition = false;

                if (filled($this->source_message_id)) {
                    $q->orWhere('source_message_id', $this->source_message_id);
                    $hasCondition = true;
                }

                if (filled($this->artist_name) && filled($this->title)) {
                    $q->orWhere(function (Builder $sub): void {
                        $sub->whereRaw('LOWER(TRIM(artist_name)) = ?', [mb_strtolower(trim((string) $this->artist_name))])
                            ->whereRaw('LOWER(TRIM(title)) = ?', [mb_strtolower(trim((string) $this->title))]);
                    });
                    $hasCondition = true;
                }

                foreach (['official_url', 'spotify_url', 'youtube_url'] as $urlCol) {
                    $val = trim((string) ($this->{$urlCol} ?? ''));
                    if ($val !== '' && Schema::hasColumn($this->getTable(), $urlCol)) {
                        $q->orWhere($urlCol, $val);
                        $hasCondition = true;
                    }
                }

                if (! $hasCondition) {
                    $q->whereRaw('1 = 0');
                }
            })
            ->limit(10)
            ->get();

        $myType = trim((string) ($this->release_type ?? ''));

        return $candidates->map(function (self $other) use ($myType): array {
            $reasons = [];
            if (filled($this->source_message_id) && strcasecmp((string) $this->source_message_id, (string) $other->source_message_id) === 0) {
                $reasons[] = 'Mismo Message-ID';
            }
            if (mb_strtolower(trim((string) $this->artist_name)) === mb_strtolower(trim((string) $other->artist_name))
                && mb_strtolower(trim((string) $this->title)) === mb_strtolower(trim((string) $other->title))) {
                $reasons[] = 'Mismo artista y título';
            }
            foreach (['official_url' => 'enlace oficial', 'spotify_url' => 'Spotify', 'youtube_url' => 'YouTube'] as $col => $label) {
                $a = trim((string) ($this->{$col} ?? ''));
                $b = trim((string) ($other->{$col} ?? ''));
                if ($a !== '' && $a === $b) {
                    $reasons[] = "Mismo {$label}";
                }
            }

            $otherType = trim((string) ($other->release_type ?? ''));
            $differentType = $myType !== '' && $otherType !== '' && $myType !== $otherType;

            return [
                'release' => $other,
                'reason' => implode(' · ', array_unique($reasons)),
                'different_type' => $differentType,
            ];
        });
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Determina si el lanzamiento se encuentra bajo un embargo activo vigente o fecha futura.
     */
    public function isUnderActiveEmbargo(): bool
    {
        if ($this->is_embargo_active) {
            if ($this->embargo_until === null) {
                return true;
            }

            return $this->embargo_until->isFuture();
        }

        if ($this->released_at !== null && $this->released_at->isFuture()) {
            return true;
        }

        return false;
    }

    public const STATUS_APPROVED_FOR_BROADCAST = 'approved_for_broadcast';
    public const STATUS_BROADCAST_DELIVERED = 'broadcast_delivered';
    public const STATUS_BROADCAST_DELIVERY_FAILED = 'broadcast_delivery_failed';

    public const SECURITY_HOLD_STATUSES = [
        'needs_review',
        'held_security',
        'rejected',
        'failed_metadata',
    ];

    /**
     * Indica si el lanzamiento tiene una retención de seguridad o validación activa
     * (incluyendo 'needs_review') que bloquea su aprobación para emisión.
     */
    public function hasSecurityHold(): bool
    {
        return in_array((string) $this->security_status, self::SECURITY_HOLD_STATUSES, true);
    }

    /**
     * Aprobación expresa de EMISIÓN EN RADIO (solo RadioBOSS).
     * - NUNCA reutiliza is_active (visibilidad web ≠ permiso de emisión).
     * - El canal /submissions queda excluido aunque la maqueta figure como aprobada.
     * - NO autoriza Archive.org (ver isApprovedForArchive()).
     */
    public function isApprovedForBroadcast(): bool
    {
        if ($this->origin === 'submission') {
            return false;
        }

        return in_array((string) $this->security_status, [
            self::STATUS_APPROVED_FOR_BROADCAST,
            self::STATUS_BROADCAST_DELIVERED,
        ], true);
    }

    /**
     * Indica si la entrega efectiva del audio a RadioBOSS ya fue confirmada.
     */
    public function isBroadcastDelivered(): bool
    {
        return $this->origin !== 'submission'
            && $this->security_status === self::STATUS_BROADCAST_DELIVERED;
    }

    /**
     * Indica si una subida autorizada a RadioBOSS falló o se detuvo por colisión/verificación
     * y admite un reintento explícito del administrador.
     */
    public function hasBroadcastDeliveryFailed(): bool
    {
        return $this->origin !== 'submission'
            && $this->security_status === self::STATUS_BROADCAST_DELIVERY_FAILED;
    }

    /**
     * Autorización INDEPENDIENTE para redistribuir el audio en Archive.org (repositorio público descargable).
     * Desactivada por defecto: requiere una columna explícita y registrada (archive_upload_approved_at +
     * archive_upload_approved_by) que este parche NO crea. Mientras no exista, siempre devuelve false.
     * La aprobación de emisión en radio no implica esta autorización.
     */
    public function isApprovedForArchive(): bool
    {
        if ($this->origin === 'submission') {
            return false;
        }

        $attributes = $this->getAttributes();
        if (! array_key_exists('archive_upload_approved_at', $attributes) || ! array_key_exists('archive_upload_approved_by', $attributes)) {
            return false;
        }

        return $attributes['archive_upload_approved_at'] !== null && $attributes['archive_upload_approved_by'] !== null;
    }

    /**
     * Requisitos para enviar a RadioBOSS: aprobación de emisión y sin embargo vigente.
     */
    public function canDispatchBroadcast(): bool
    {
        return $this->isApprovedForBroadcast() && ! $this->isUnderActiveEmbargo();
    }

    /**
     * Requisitos para enviar a Archive.org: autorización independiente y sin embargo vigente.
     */
    public function canDispatchArchive(): bool
    {
        return $this->isApprovedForArchive() && ! $this->isUnderActiveEmbargo();
    }

    public function trackSubmission(): HasOne
    {
        return $this->hasOne(TrackSubmission::class, 'new_release_id');
    }

    public function sendPublishedNotification(): void
    {
        if (! Schema::hasColumn($this->getTable(), 'author_email')) {
            return;
        }

        // Distinción de procedencia:
        // Solo los lanzamientos enviados voluntariamente por artistas ('submission')
        // o creados manualmente por administradores ('manual') pueden enviar notificaciones.
        // Los lanzamientos importados ('email') y los de procedencia desconocida ('unknown' o nulos)
        // tienen bloqueadas las notificaciones automáticas para evitar spam a terceros.
        $origin = (string) ($this->origin ?? 'unknown');
        if (! in_array($origin, ['submission', 'manual'], true)) {
            Log::info("Notificación omitida para NewRelease ID {$this->id}: procedencia '{$origin}' no autorizada para avisos automáticos.");
            return;
        }

        $emailString = trim((string) $this->author_email);
        if ($emailString === '') {
            return;
        }

        // Dividir por punto y coma (;) o coma (,) y limpiar espacios
        $emails = array_values(array_filter(array_map('trim', preg_split('/[;,]/', $emailString))));
        if (empty($emails)) {
            return;
        }

        // Excluir bots internos y direcciones del sistema
        $ignored = array_filter([
            'dark.vader.agent@gmail.com',
            'prog.sevenrockradio@gmail.com',
            strtolower(trim((string) config('mail.from.address'))),
        ]);

        $emails = array_values(array_filter($emails, function ($e) use ($ignored) {
            return ! in_array(strtolower($e), $ignored, true);
        }));

        if (empty($emails)) {
            return;
        }

        try {
            $sender = Schema::hasColumn($this->getTable(), 'notification_sender') && $this->notification_sender
                ? trim((string) $this->notification_sender)
                : config('mail.from.address');

            Mail::to($emails)->send(
                new \App\Mail\NewReleasePublishedMail($this, $sender)
            );

            Log::info("Email de notificación de publicación enviado a los autores (" . implode(', ', $emails) . ") para el lanzamiento ID: {$this->id}");
        } catch (\Throwable $e) {
            Log::error("Fallo al enviar email de notificación de publicación para lanzamiento ID {$this->id}: " . $e->getMessage());
        }
    }

    public function radioArtist(): BelongsTo
    {
        return $this->belongsTo(RadioArtist::class, 'radio_artist_id');
    }

    public function getCoverImageUrlAttribute(): string
    {
        if ($this->cover_image) {
            return PublicMediaUrl::normalizePublicUrl($this->cover_image);
        }

        // Diseño neutro y sobrio para lanzamientos sin portada (evita las barras del tema)
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800"><rect width="100%" height="100%" fill="#101012"/><text x="50%" y="50%" font-family="system-ui, sans-serif" font-size="24" fill="#444" text-anchor="middle" dominant-baseline="middle">AUDIO</text></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public function getAudioUrlAttribute(): string
    {
        return PublicMediaUrl::normalizePublicUrl($this->audio_path) ?: '';
    }

    public function getYoutubeEmbedUrlAttribute(): string
    {
        $url = trim((string) $this->youtube_url);
        if ($url === '') {
            return '';
        }

        if (str_contains($url, 'youtube.com/embed/')) {
            return $url;
        }

        if (preg_match('~(?:v=|youtu\.be/)([A-Za-z0-9_-]{6,})~', $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1] . '?autoplay=0&enablejsapi=1&wmode=transparent&rel=0&showinfo=0';
        }

        return $url;
    }

    public function isFromEmailPipeline(): bool
    {
        return $this->origin === 'email'
            || !empty($this->source_subject)
            || !empty($this->source_message_id);
    }
}
