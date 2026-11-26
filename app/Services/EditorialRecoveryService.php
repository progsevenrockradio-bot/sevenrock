<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmailPostSanitizedMeta;
use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Reevaluación individual y recuperación acotada de fallos técnicos temporales (p. ej. HTTP 429)
 * sobre artículos ya importados, sin reimportar lotes ni duplicar registros.
 */
class EditorialRecoveryService
{
    public const STATUS_PENDING_RETRY = 'pending_retry';
    public const STATUS_RECOVERED = 'recovered';
    public const STATUS_PERMANENT_FAILURE = 'permanent_failure';
    public const STATUS_EXHAUSTED = 'exhausted';

    public function __construct(
        protected EditorialImageService $imageService,
        protected DvAutoPublishPolicy $autoPublishPolicy,
        protected AuditTrailService $auditTrail
    ) {}

    /**
     * Reevalúa una única pieza existente respetando concurrencia, decisiones manuales,
     * embargos, rechazos y la política formal de publicación vigente.
     *
     * @return array{
     *   post_id: int,
     *   outcome: string,
     *   image_recovered: bool,
     *   reused_from_cache: bool,
     *   published: bool,
     *   next_retry_at: ?string,
     *   remaining_blockers: array<int, string>,
     *   message: string
     * }
     */
    public function reevaluatePost(
        Post $post,
        bool $isManualAction = false,
        bool $attemptAutoPublish = true,
        ?Carbon $referenceNow = null
    ): array {
        $now = $referenceNow ? $referenceNow->copy() : Carbon::now('Europe/Madrid');

        // Coordinación por pieza para evitar ejecuciones simultáneas
        $lock = Cache::lock("editorial_reeval_post:{$post->id}", 60);
        if (! $lock->get()) {
            return [
                'post_id'            => $post->id,
                'outcome'            => 'locked',
                'image_recovered'    => false,
                'reused_from_cache'  => false,
                'published'          => false,
                'next_retry_at'      => null,
                'remaining_blockers' => ['Reevaluación ya en curso para esta pieza.'],
                'message'            => 'Ya hay una reevaluación en ejecución para esta pieza.',
            ];
        }

        try {
            $post = Post::with(['sanitizedMeta', 'incidents' => fn ($q) => $q->where('resolution_status', 'pending_review')])
                ->findOrFail($post->id);

            // 1. Nunca alterar artículos ya publicados o rechazados
            if ($post->status === 'published' || $post->is_published) {
                return [
                    'post_id'            => $post->id,
                    'outcome'            => 'already_published',
                    'image_recovered'    => false,
                    'reused_from_cache'  => false,
                    'published'          => false,
                    'next_retry_at'      => null,
                    'remaining_blockers' => [],
                    'message'            => 'La pieza ya está publicada; no se realizaron cambios.',
                ];
            }

            if ($post->status === 'rejected') {
                return [
                    'post_id'            => $post->id,
                    'outcome'            => 'rejected_respected',
                    'image_recovered'    => false,
                    'reused_from_cache'  => false,
                    'published'          => false,
                    'next_retry_at'      => null,
                    'remaining_blockers' => ['Pieza rechazada por decisión editorial.'],
                    'message'            => 'La pieza está rechazada; la recuperación automática respeta esa decisión.',
                ];
            }

            $meta = $post->sanitizedMeta;
            if (! $meta) {
                return [
                    'post_id'            => $post->id,
                    'outcome'            => 'no_metadata',
                    'image_recovered'    => false,
                    'reused_from_cache'  => false,
                    'published'          => false,
                    'next_retry_at'      => null,
                    'remaining_blockers' => ['Sin metadatos editoriales recuperables asociados.'],
                    'message'            => 'Esta pieza no tiene metadatos de recuperación técnica asociados.',
                ];
            }

            // 2. Respetar modificaciones manuales previas en ejecución automática
            if (! $isManualAction && $meta->manually_modified_at !== null) {
                return [
                    'post_id'            => $post->id,
                    'outcome'            => 'manual_edit_preserved',
                    'image_recovered'    => false,
                    'reused_from_cache'  => false,
                    'published'          => false,
                    'next_retry_at'      => null,
                    'remaining_blockers' => ['Modificada manualmente por un editor; pendiente de decisión manual.'],
                    'message'            => 'La pieza tiene ediciones manuales previas; se conserva intacta.',
                ];
            }

            $imageRecovered = false;
            $reusedFromCache = false;
            $structuredImageMeta = null;

            // 3. Paso A: Recuperación técnica de imagen (solo si falta portada y existe URL candidata acreditable)
            $hasCover = ! empty($post->featured_image);
            $candidateUrl = trim((string) ($meta->image_candidate_url ?? ''));

            if (! $hasCover && $candidateUrl !== '') {
                // En automático, respetar ventana de espera y límite de reintentos
                if (! $isManualAction) {
                    if ($meta->image_recovery_next_retry_at && $now->lt($meta->image_recovery_next_retry_at)) {
                        return [
                            'post_id'            => $post->id,
                            'outcome'            => 'waiting_retry_window',
                            'image_recovered'    => false,
                            'reused_from_cache'  => false,
                            'published'          => false,
                            'next_retry_at'      => $meta->image_recovery_next_retry_at->toIso8601String(),
                            'remaining_blockers' => ['Esperando ventana de reintento programada para imagen.'],
                            'message'            => 'Próximo reintento programado para ' . $meta->image_recovery_next_retry_at->timezone('Europe/Madrid')->format('d/m/Y H:i'),
                        ];
                    }

                    if ($meta->image_recovery_attempts >= EditorialImageService::MAX_RECOVERY_ATTEMPTS) {
                        $meta->image_recovery_status = self::STATUS_EXHAUSTED;
                        $meta->image_recovery_next_retry_at = null;
                        $meta->save();

                        return [
                            'post_id'            => $post->id,
                            'outcome'            => 'retries_exhausted',
                            'image_recovered'    => false,
                            'reused_from_cache'  => false,
                            'published'          => false,
                            'next_retry_at'      => null,
                            'remaining_blockers' => ['Reintentos automáticos de imagen agotados; requiere intervención manual.'],
                            'message'            => 'Se agotaron los reintentos automáticos de descarga de imagen.',
                        ];
                    }
                }

                $attemptNum = (int) $meta->image_recovery_attempts + 1;
                $imgResult = $this->imageService->resolveAndStore(
                    $candidateUrl,
                    (string) ($meta->batch_id ?: 'REC'),
                    (string) ($meta->element_id ?: 'P' . $post->id),
                    $post->slug ?: Str::slug($post->title),
                    $meta->image_credit
                );

                $meta->image_recovery_attempts = $attemptNum;
                $meta->image_recovery_last_attempt_at = $now->copy()->timezone(config('app.timezone', 'Europe/Madrid'));

                if ($imgResult['success'] && ! empty($imgResult['path'])) {
                    $imageRecovered = true;
                    $reusedFromCache = (bool) ($imgResult['reused_from_cache'] ?? false);
                    $structuredImageMeta = $imgResult['structured_meta'] ?? null;

                    if (Schema::hasColumn('posts', 'featured_image_path')) {
                        $post->featured_image_path = $imgResult['path'];
                    }
                    if (Schema::hasColumn('posts', 'featured_image')) {
                        $post->featured_image = $imgResult['path'];
                    }
                    if (! empty($imgResult['credit'])) {
                        $meta->image_credit = Str::limit((string) $imgResult['credit'], 500, '');
                        if (Schema::hasColumn('posts', 'image_source')) {
                            $post->image_source = $meta->image_credit;
                        }
                    }
                    $post->image_fetch_error = null;
                    $post->save();

                    // Eliminar SOLO el bloqueo de imagen pendiente, preservando intactos los demás bloqueos e historial
                    $cleanedIncident = $this->stripImagePendingBlocker((string) ($meta->incident_reason ?? ''));
                    $meta->incident_reason = $cleanedIncident !== '' ? $cleanedIncident : null;
                    $meta->image_recovery_status = self::STATUS_RECOVERED;
                    $meta->image_recovery_last_error = null;
                    $meta->image_recovery_next_retry_at = null;

                    // Guardar structured_meta en dv_policy_snapshot si existe
                    $snapshot = is_array($meta->dv_policy_snapshot) ? $meta->dv_policy_snapshot : [];
                    if ($structuredImageMeta) {
                        $snapshot['image_meta'] = [
                            'path' => $imgResult['path'],
                            'meta' => $structuredImageMeta,
                        ];
                        $meta->dv_policy_snapshot = $snapshot;
                    }

                    $stamp = '[IMAGEN_RECUPERADA ' . $now->format('d/m/Y H:i') . ($reusedFromCache ? ' desde caché verificada' : '') . ']';
                    $meta->editorial_notes = trim(($meta->editorial_notes ? $meta->editorial_notes . "\n" : '') . $stamp);
                    $meta->save();

                    $hasCover = true;
                } else {
                    $errorText = Str::limit((string) ($imgResult['error'] ?? 'Error técnico al recuperar imagen'), 480, '');
                    $isRetryable = (bool) ($imgResult['retryable'] ?? false);
                    $meta->image_recovery_last_error = $errorText;

                    if ($isRetryable && $attemptNum < EditorialImageService::MAX_RECOVERY_ATTEMPTS) {
                        $waitSeconds = EditorialImageService::computeBackoffSeconds(
                            $attemptNum,
                            $imgResult['retry_after_seconds'] ?? null
                        );
                        $nextRetry = $now->copy()->addSeconds($waitSeconds);
                        $meta->image_recovery_status = self::STATUS_PENDING_RETRY;
                        $meta->image_recovery_next_retry_at = $nextRetry->copy()->timezone(config('app.timezone', 'Europe/Madrid'));
                        $meta->save();

                        return [
                            'post_id'            => $post->id,
                            'outcome'            => 'image_retry_scheduled',
                            'image_recovered'    => false,
                            'reused_from_cache'  => false,
                            'published'          => false,
                            'next_retry_at'      => $nextRetry->toIso8601String(),
                            'remaining_blockers' => array_values(array_filter([$errorText, $this->stripImagePendingBlocker((string) ($meta->incident_reason ?? ''))])),
                            'message'            => "Fallo temporal de imagen ({$errorText}). Reintento #{$attemptNum} fallido; próximo intento programado para " . $nextRetry->timezone('Europe/Madrid')->format('d/m/Y H:i') . '.',
                        ];
                    }

                    $meta->image_recovery_status = $isRetryable ? self::STATUS_EXHAUSTED : self::STATUS_PERMANENT_FAILURE;
                    $meta->image_recovery_next_retry_at = null;
                    $meta->save();

                    return [
                        'post_id'            => $post->id,
                        'outcome'            => $isRetryable ? 'retries_exhausted' : 'image_permanent_failure',
                        'image_recovered'    => false,
                        'reused_from_cache'  => false,
                        'published'          => false,
                        'next_retry_at'      => null,
                        'remaining_blockers' => array_values(array_filter([$errorText, $this->stripImagePendingBlocker((string) ($meta->incident_reason ?? ''))])),
                        'message'            => $isRetryable
                            ? "Reintentos de imagen agotados ({$errorText}). Requiere revisión o carga manual."
                            : "Error permanente de imagen ({$errorText}). No se programan más reintentos.",
                    ];
                }
            }

            // 4. Paso B: Comprobar qué bloqueos siguen vigentes y si procede publicación automática formal
            $remainingBlockers = [];

            if (! $hasCover) {
                $remainingBlockers[] = $candidateUrl === ''
                    ? 'Imagen pendiente sin URL candidata acreditada para reintento automático.'
                    : 'Portada pendiente de recuperación.';
            }

            if ($meta->forced_as_draft) {
                $remainingBlockers[] = 'Importado expresamente con --as-draft (requiere aprobación manual para publicar).';
            }

            if ($meta->manually_modified_at !== null) {
                $remainingBlockers[] = 'Tiene ediciones manuales previas (requiere confirmación manual para publicar).';
            }

            if ($meta->is_embargo_active && ($meta->embargo_until === null || $meta->embargo_until->isFuture())) {
                $remainingBlockers[] = 'Bajo embargo activo.';
            }

            if ($post->incidents->isNotEmpty()) {
                $remainingBlockers[] = 'Incidencia editorial pendiente de resolución.';
            }

            if (in_array($meta->security_status, ['held_security', 'rejected'], true)) {
                $remainingBlockers[] = "Retención de seguridad activa ({$meta->security_status}).";
            }

            // Si es acción manual, NUNCA publicar automáticamente (regla explícita)
            $canAttemptPolicyAutoPublish = ! $isManualAction
                && $attemptAutoPublish
                && empty($remainingBlockers)
                && is_array($meta->dv_policy_snapshot)
                && ! empty($meta->dv_policy_snapshot['elem']);

            if ($canAttemptPolicyAutoPublish) {
                $snapshot = $meta->dv_policy_snapshot;
                $elem = (array) ($snapshot['elem'] ?? []);
                $context = (array) ($snapshot['context'] ?? []);
                $context['reference_now'] = $now->toIso8601String();
                $context['timezone'] = (string) ($context['timezone'] ?? 'Europe/Madrid');
                if ($meta->editorial_date) {
                    $context['fecha_editorial'] = $meta->editorial_date->format('Y-m-d');
                    $elem['fecha_editorial'] = $meta->editorial_date->format('Y-m-d');
                }
                if (! empty($snapshot['image_meta'])) {
                    $context['image_meta'] = $snapshot['image_meta'];
                }

                $eval = $this->autoPublishPolicy->evaluate(
                    $elem,
                    $post,
                    $post->featured_image,
                    $meta->image_credit,
                    $meta->image_alt ?: $post->image_alt,
                    $context
                );

                if ($eval['can_auto_publish']) {
                    DB::transaction(function () use ($post, $meta, $eval, $now) {
                        $before = $post->getOriginal();
                        $post->status = 'published';
                        $post->is_published = true;
                        $post->published_at = $now->copy()->timezone(config('app.timezone', 'Europe/Madrid'));
                        $post->save();

                        $meta->approved_urls = $eval['approved_urls'];
                        $meta->approved_at = $now->copy()->timezone(config('app.timezone', 'Europe/Madrid'));
                        $meta->approved_by = null;
                        $meta->security_status = 'passed';
                        $meta->incident_reason = null;
                        $meta->editorial_notes = trim(($meta->editorial_notes ? $meta->editorial_notes . "\n" : '') . '[AUTO_PUBLICADO_TRAS_RECUPERACION ' . $now->format('d/m/Y H:i') . '] Cumple política vigente.');
                        $meta->save();

                        $this->auditTrail->recordModel(
                            event: 'editorial_auto_policy_published',
                            model: $post,
                            before: $before,
                            after: $post->getAttributes(),
                            context: [
                                'action'    => 'recovery_auto_policy_publish',
                                'batch_id'  => $meta->batch_id,
                                'elem_id'   => $meta->element_id,
                                'sources'   => $eval['approved_urls'],
                            ]
                        );
                    });

                    return [
                        'post_id'            => $post->id,
                        'outcome'            => 'published_by_policy',
                        'image_recovered'    => $imageRecovered,
                        'reused_from_cache'  => $reusedFromCache,
                        'published'          => true,
                        'next_retry_at'      => null,
                        'remaining_blockers' => [],
                        'message'            => 'Imagen recuperada y pieza publicada automáticamente por cumplir la política editorial vigente.',
                    ];
                }

                // La política sigue bloqueando por motivo editorial o fecha fuera de vigencia
                if (! empty($eval['reason'])) {
                    $remainingBlockers[] = $eval['reason'];
                    $meta->incident_reason = Str::limit($eval['reason'], 500, '');
                    $meta->security_status = 'needs_review';
                    $meta->save();
                }
            } elseif (! empty($meta->incident_reason)) {
                $remainingBlockers[] = (string) $meta->incident_reason;
            }

            $remainingBlockers = array_values(array_unique(array_filter($remainingBlockers)));

            $msg = $imageRecovered
                ? (empty($remainingBlockers)
                    ? 'Imagen recuperada correctamente. La pieza permanece en borrador lista para decisión editorial.'
                    : 'Imagen recuperada, pero la pieza sigue en borrador por: ' . implode(' | ', $remainingBlockers))
                : (empty($remainingBlockers)
                    ? 'Sin fallos técnicos pendientes de recuperación.'
                    : 'Pendiente de decisión editorial: ' . implode(' | ', $remainingBlockers));

            return [
                'post_id'            => $post->id,
                'outcome'            => $imageRecovered ? 'image_recovered_still_draft' : 'remains_draft',
                'image_recovered'    => $imageRecovered,
                'reused_from_cache'  => $reusedFromCache,
                'published'          => false,
                'next_retry_at'      => null,
                'remaining_blockers' => $remainingBlockers,
                'message'            => $msg,
            ];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Ejecuta una ronda acotada de recuperación técnica sobre piezas con reintentos programados vencidos.
     *
     * @return array{processed: int, recovered: int, published: int, rescheduled: int, failed: int, details: array<int, array<string, mixed>>}
     */
    public function runScheduledBatch(int $limit = 8, ?Carbon $referenceNow = null): array
    {
        $now = $referenceNow ? $referenceNow->copy() : Carbon::now('Europe/Madrid');
        $limit = max(1, min(25, $limit));

        $candidates = Post::query()
            ->with('sanitizedMeta')
            ->where('status', 'draft')
            ->where(fn ($q) => $q->whereNull('is_published')->orWhere('is_published', false))
            ->whereHas('sanitizedMeta', function ($q) use ($now) {
                $q->where('image_recovery_status', self::STATUS_PENDING_RETRY)
                  ->whereNotNull('image_candidate_url')
                  ->where('image_candidate_url', '!=', '')
                  ->where('image_recovery_attempts', '<', EditorialImageService::MAX_RECOVERY_ATTEMPTS)
                  ->whereNull('manually_modified_at')
                  ->where(function ($t) use ($now) {
                    $t->whereNull('image_recovery_next_retry_at')
                      ->orWhere('image_recovery_next_retry_at', '<=', $now->copy()->timezone(config('app.timezone', 'Europe/Madrid')));
                  });
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $summary = [
            'processed'   => 0,
            'recovered'   => 0,
            'published'   => 0,
            'rescheduled' => 0,
            'failed'      => 0,
            'details'     => [],
        ];

        foreach ($candidates as $post) {
            $res = $this->reevaluatePost($post, isManualAction: false, attemptAutoPublish: true, referenceNow: $now);
            $summary['processed']++;
            if ($res['image_recovered']) {
                $summary['recovered']++;
            }
            if ($res['published']) {
                $summary['published']++;
            }
            if ($res['outcome'] === 'image_retry_scheduled') {
                $summary['rescheduled']++;
            }
            if (in_array($res['outcome'], ['image_permanent_failure', 'retries_exhausted'], true)) {
                $summary['failed']++;
            }
            $summary['details'][] = $res;
        }

        return $summary;
    }

    /**
     * Retira exclusivamente el prefijo/segmento [IMAGEN_PENDIENTE] manteniendo intactos
     * los demás motivos editoriales que sigan vigentes.
     */
    public function stripImagePendingBlocker(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            return '';
        }

        $parts = array_map('trim', explode('|', $reason));
        $kept = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (str_starts_with($part, '[IMAGEN_PENDIENTE]') || str_contains(mb_strtolower($part), 'http 429 al descargar imagen')) {
                continue;
            }
            $kept[] = $part;
        }

        return implode(' | ', $kept);
    }
}
