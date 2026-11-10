<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiCallAudit;
use App\Models\EditorialIncident;
use App\Models\EmailPostSanitizedMeta;
use App\Models\EmailSenderSource;
use App\Models\Post;
use App\Services\AuditTrailService;
use App\Services\EmailMediaInventoryExtractor;
use App\Services\EmailSafetyValidator;
use App\Services\EmailSenderSourceResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminEditorialController extends Controller
{
    public function __construct(
        protected EmailSafetyValidator $safetyValidator,
        protected EmailMediaInventoryExtractor $mediaExtractor,
        protected EmailSenderSourceResolver $sourceResolver,
        protected AuditTrailService $auditTrail
    ) {}

    /**
     * Bandeja Principal Editorial: Lista exclusivamente posts del circuito de correo.
     */
    public function index(Request $request): View
    {
        $this->ensureAuthorized();

        $query = Post::query()
            ->with(['sanitizedMeta', 'incidents' => function ($q) {
                $q->where('resolution_status', 'pending_review');
            }, 'taxonomies'])
            ->where(function (Builder $q) {
                $q->whereNotNull('source_subject')->where('source_subject', '!=', '')
                  ->orWhereHas('sanitizedMeta');
            });

        // 1. Filtro por Estado Editorial
        $status = $request->string('status')->trim()->value();
        if ($status !== '' && $status !== 'all') {
            if ($status === 'held') {
                $query->whereHas('sanitizedMeta', function ($q) {
                    $q->whereIn('security_status', ['held_security', 'held_embargo', 'needs_review']);
                });
            } elseif ($status === 'with_incidents') {
                $query->whereHas('incidents', function ($q) {
                    $q->where('resolution_status', 'pending_review');
                });
            } else {
                $query->where('status', $status);
            }
        }

        // 2. Filtro por Búsqueda (Título, Asunto, Remitente, Artista)
        $search = $request->string('search')->trim()->value();
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('source_subject', 'like', "%{$search}%")
                  ->orWhere('author_email', 'like', "%{$search}%");
            });
        }

        // 3. Filtro por Seguridad / Embargo
        $security = $request->string('security')->trim()->value();
        if ($security !== '' && $security !== 'all') {
            if ($security === 'active_embargo') {
                $query->whereHas('sanitizedMeta', function ($q) {
                    $q->where('is_embargo_active', true)
                      ->where('embargo_until', '>', now());
                });
            } else {
                $query->whereHas('sanitizedMeta', function ($q) use ($security) {
                    $q->where('security_status', $security);
                });
            }
        }

        // 4. Ordenación
        $sort = $request->string('sort', 'newest')->value();
        switch ($sort) {
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'embargo':
                $query->leftJoin('email_post_sanitized_meta as epsm', 'posts.id', '=', 'epsm.post_id')
                      ->orderByRaw('CASE WHEN epsm.embargo_until IS NOT NULL THEN 0 ELSE 1 END, epsm.embargo_until ASC')
                      ->select('posts.*');
                break;
            case 'urgent':
                $query->leftJoin('editorial_incidents as ei', function ($join) {
                    $join->on('posts.id', '=', 'ei.post_id')
                         ->where('ei.resolution_status', '=', 'pending_review');
                })->orderByRaw("CASE 
                    WHEN ei.priority = 'urgent' THEN 1 
                    WHEN ei.priority = 'high' THEN 2 
                    WHEN ei.priority = 'medium' THEN 3 
                    ELSE 4 END ASC")
                  ->select('posts.*')->distinct();
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $perPage = in_array((int) $request->input('per_page'), [15, 30, 50], true) ? (int) $request->input('per_page') : 15;
        $posts = $query->paginate($perPage)->withQueryString();

        // Contadores globales de cabecera
        $counts = [
            'total'          => Post::where(fn($q) => $q->whereNotNull('source_subject')->where('source_subject', '!=', '')->orWhereHas('sanitizedMeta'))->count(),
            'drafts'         => Post::where('status', 'draft')->where(fn($q) => $q->whereNotNull('source_subject')->where('source_subject', '!=', '')->orWhereHas('sanitizedMeta'))->count(),
            'scheduled'      => Post::where('status', 'scheduled')->where(fn($q) => $q->whereNotNull('source_subject')->where('source_subject', '!=', '')->orWhereHas('sanitizedMeta'))->count(),
            'published'      => Post::where('status', 'published')->where(fn($q) => $q->whereNotNull('source_subject')->where('source_subject', '!=', '')->orWhereHas('sanitizedMeta'))->count(),
            'with_incidents' => EditorialIncident::where('resolution_status', 'pending_review')->count(),
            'embargo_active' => EmailPostSanitizedMeta::where('is_embargo_active', true)->where('embargo_until', '>', now())->count(),
        ];

        return view('admin.editorial.index', [
            'posts'          => $posts,
            'counts'         => $counts,
            'currentStatus'  => $status ?: 'all',
            'currentSearch'  => $search,
            'currentSecurity'=> $security ?: 'all',
            'currentSort'    => $sort,
            'perPage'        => $perPage,
        ]);
    }

    /**
     * Vista Rápida Segura (Drawer / Modal AJAX).
     * El servidor sanitiza estrictamente los datos, impidiendo el envío de contraseñas, tokens o URLs privadas.
     */
    public function quickView(Post $post): JsonResponse
    {
        $this->ensureAuthorized();
        $this->ensureFromEmailPipeline($post);

        $post->loadMissing(['sanitizedMeta', 'incidents' => function ($q) {
            $q->orderByDesc('created_at');
        }, 'taxonomies']);

        $meta = $post->sanitizedMeta;

        // 1. Inventario multimedia efectivo (sin inventar datos)
        $rawContent = is_array($post->content) ? json_encode($post->content) : (string) $post->content;
        $mediaInventory = $this->mediaExtractor->getEffectiveInventory(
            $meta?->media_inventory,
            $rawContent
        );

        // 2. Información de verificación de la fuente / remitente
        $sourceInfo = $this->sourceResolver->resolve((string) ($post->author_email ?? $meta?->sender_email));

        // 3. Auditoría de IA (telemetría si existe)
        $aiAudit = null;
        if ($meta?->source_message_id) {
            $auditRecord = AiCallAudit::where('status', 'success')
                ->where('created_at', '>=', $post->created_at->subMinutes(30))
                ->where('created_at', '<=', $post->created_at->addMinutes(30))
                ->orderByDesc('id')
                ->first();

            if ($auditRecord) {
                $aiAudit = [
                    'provider'         => $auditRecord->provider,
                    'model'            => $auditRecord->model,
                    'prompt_tokens'    => $auditRecord->prompt_tokens,
                    'completion_tokens'=> $auditRecord->completion_tokens,
                    'total_tokens'     => $auditRecord->total_tokens,
                    'duration_ms'      => $auditRecord->duration_ms,
                    'cost_eur'         => $auditRecord->cost_eur,
                ];
            }
        }

        // 4. SANITIZACIÓN ESTRICTA EN EL SERVIDOR: Enmascarar contraseñas y tokens privados
        $cleanEditorialNotes = $meta?->editorial_notes ? $this->maskSensitiveData($meta->editorial_notes) : null;
        $cleanIncidentReason = $meta?->incident_reason ? $this->maskSensitiveData($meta->incident_reason) : null;
        $cleanContentExcerpt = Str::limit(strip_tags($rawContent), 500);

        // Enlaces redactados: NUNCA enviar URLs privadas con tokens
        $safeRedactedLinks = [];
        if (!empty($meta?->redacted_links)) {
            foreach ($meta->redacted_links as $rl) {
                $safeRedactedLinks[] = [
                    'platform'  => $rl['platform'] ?? 'Material Privado',
                    'category'  => $rl['category'] ?? 'restricted_private',
                    'note'      => 'Acceso restringido para prensa. URL privada protegida por seguridad.',
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                   => $post->id,
                'title'                => $post->title,
                'slug'                 => $post->slug,
                'status'               => $post->status,
                'is_published'         => (bool) $post->is_published,
                'published_at'         => $post->published_at?->format('d/m/Y H:i'),
                'created_at'           => $post->created_at?->format('d/m/Y H:i'),
                'time_ago'             => $post->created_at?->diffForHumans(),
                'featured_image'       => $post->featured_image_url,
                'excerpt'              => $post->excerpt,
                'content_preview'      => $cleanContentExcerpt,
                'author_email'         => $post->author_email,
                'source_subject'       => $post->source_subject,
                'categories'           => $post->categoryNames(),
                'tags'                 => $post->tagNames(),
                'version_token'        => md5((string) $post->updated_at?->timestamp . '_' . $post->status),
                'meta'                 => [
                    'source_message_id'    => $meta?->source_message_id,
                    'received_at'          => $meta?->received_at?->format('d/m/Y H:i'),
                    'musical_release_date' => $meta?->musical_release_date?->format('d/m/Y'),
                    'embargo_until'        => $meta?->embargo_until?->format('d/m/Y H:i'),
                    'is_embargo_active'    => (bool) ($meta?->is_embargo_active && $meta?->embargo_until?->isFuture()),
                    'security_status'      => $meta?->security_status ?? 'passed',
                    'incident_reason'      => $cleanIncidentReason,
                    'editorial_notes'      => $cleanEditorialNotes,
                    'redacted_materials'   => $safeRedactedLinks,
                    'sources'              => is_array($meta?->sources) ? $meta->sources : (json_decode((string) ($meta?->sources ?? ''), true) ?: []),
                    'approved_urls'        => $meta?->approved_urls ?? [],
                    'pending_sources'      => $this->safetyValidator->getPendingUnapprovedSources($post),
                    'has_pending_sources'  => ! empty($this->safetyValidator->getPendingUnapprovedSources($post)),
                    'approved_at'          => $meta?->approved_at?->format('d/m/Y H:i'),
                    'approved_by'          => $meta?->approver?->name ?? ($meta?->approved_by ? "Usuario #{$meta->approved_by}" : null),
                ],
                'media_inventory'      => $mediaInventory,
                'source'               => [
                    'name'                 => $sourceInfo['source']?->name ?? 'Remitente No Registrado',
                    'status'               => $sourceInfo['status'],
                    'organization_type'    => $sourceInfo['source']?->organization_type ?? 'Desconocido',
                    'is_authorized'        => ($sourceInfo['status'] === 'authorized'),
                ],
                'ai_telemetry'         => $aiAudit,
                'incidents'            => $post->incidents->map(fn($inc) => [
                    'id'               => $inc->id,
                    'incident_type'    => $inc->incident_type,
                    'priority'         => $inc->priority,
                    'action_taken'     => $inc->action_taken,
                    'correlation_score'=> $inc->correlation_score,
                    'detected_diff'    => $inc->detected_diff,
                    'resolution_status'=> $inc->resolution_status,
                    'created_at'       => $inc->created_at->format('d/m/Y H:i'),
                ]),
            ],
        ]);
    }

    /**
     * Acción: Aprobar y Publicar Inmediatamente.
     */
    public function approve(Request $request, Post $post): JsonResponse|RedirectResponse
    {
        $this->ensureAuthorized();
        $this->ensureFromEmailPipeline($post);

        DB::beginTransaction();
        try {
            // Bloqueo pesimista para evitar colisiones de concurrencia
            $lockedPost = Post::lockForUpdate()->find($post->id);

            // 1. Detección de concurrencia / Modificación simultánea
            if (!$lockedPost || $lockedPost->is_published || $lockedPost->status === 'published') {
                DB::rollBack();
                $msg = 'La publicación ya fue aprobada o publicada anteriormente por otro administrador.';
                return $request->wantsJson()
                    ? response()->json(['success' => false, 'message' => $msg, 'code' => 'already_published'], 409)
                    : back()->with('error', $msg);
            }

            // 2. Comprobar incidencias pendientes
            if ($lockedPost->incidents()->where('resolution_status', 'pending_review')->exists()) {
                DB::rollBack();
                $msg = 'No se puede publicar: tiene incidencias o rectificaciones editoriales pendientes de resolución.';
                return $request->wantsJson()
                    ? response()->json(['success' => false, 'message' => $msg, 'code' => 'pending_incidents'], 422)
                    : back()->with('error', $msg);
            }

            // 3. Comprobar retenciones de seguridad y embargos activos
            $meta = $lockedPost->sanitizedMeta;
            if ($meta) {
                if (in_array($meta->security_status, ['held_security', 'rejected'], true)) {
                    DB::rollBack();
                    $msg = "Publicación bloqueada: el contenido se encuentra retenido cautelarmente ({$meta->security_status}). Requiere resolución expresa.";
                    return $request->wantsJson()
                        ? response()->json(['success' => false, 'message' => $msg, 'code' => 'security_held'], 422)
                        : back()->with('error', $msg);
                }

                if ($meta->is_embargo_active) {
                    if ($meta->embargo_until === null) {
                        DB::rollBack();
                        $msg = 'Publicación bloqueada: el material está bajo embargo indefinido o sin fecha confirmada. Requiere resolución expresa.';
                        return $request->wantsJson()
                            ? response()->json(['success' => false, 'message' => $msg, 'code' => 'active_embargo'], 422)
                            : back()->with('error', $msg);
                    }

                    if ($meta->embargo_until->isFuture()) {
                        DB::rollBack();
                        $embargoStr = $meta->embargo_until->format('d/m/Y H:i');
                        $msg = "Publicación bloqueada: el material está bajo embargo hasta el {$embargoStr}. Debe programarse para una fecha posterior.";
                        return $request->wantsJson()
                            ? response()->json(['success' => false, 'message' => $msg, 'code' => 'active_embargo'], 422)
                            : back()->with('error', $msg);
                    }
                }
            }

            // 3.1 Acción Explícita: «Aprobar estas fuentes y publicar»
            // Registra usuario, fecha y URLs aprobadas de forma explícita sin consentimiento oculto
            if ($request->boolean('approve_explicit_sources') || $request->has('approved_urls')) {
                if (! $meta) {
                    $meta = new EmailPostSanitizedMeta();
                    $meta->post_id = $lockedPost->id;
                    $meta->security_status = 'sanitized';
                }

                $candidateUrls = [];
                if ($request->has('sources_to_approve')) {
                    $candidateUrls = array_merge($candidateUrls, (array) $request->input('sources_to_approve'));
                } elseif ($request->has('approved_urls')) {
                    $candidateUrls = array_merge($candidateUrls, (array) $request->input('approved_urls'));
                }

                $validApproved = [];
                foreach ($candidateUrls as $rawCandidate) {
                    $candidate = trim((string) $rawCandidate);
                    if ($candidate === '') {
                        continue;
                    }

                    // Bloqueo estricto de credenciales en URL
                    if (parse_url($candidate, PHP_URL_USER) !== null || parse_url($candidate, PHP_URL_PASS) !== null) {
                        continue;
                    }

                    // Bloqueo estricto de SSRF, esquemas no permitidos, enlaces privados o embargo
                    $cl = $this->safetyValidator->getLinkClassifier()->classify($candidate);
                    if (in_array($cl['classification'], ['suspicious', 'restricted_private', 'under_embargo'], true)) {
                        continue;
                    }

                    $validApproved[] = $candidate;
                }

                if (! empty($validApproved)) {
                    $meta->registerApprovedUrls($validApproved, auth()->id());
                    $meta->save();
                    $lockedPost->setRelation('sanitizedMeta', $meta);

                    $this->auditTrail->recordModel(
                        event: 'editorial_sources_explicitly_approved',
                        model: $lockedPost,
                        before: ['approved_urls' => $meta->getOriginal('approved_urls')],
                        after: [
                            'approved_urls' => $meta->approved_urls,
                            'approved_at'   => $meta->approved_at?->toIso8601String(),
                            'approved_by'   => $meta->approved_by,
                        ],
                        context: [
                            'action'   => 'approve_explicit_sources_and_publish',
                            'actor_id' => auth()->id(),
                            'count'    => count($validApproved),
                        ]
                    );
                }
            }

            // 4. Validación centralizada de seguridad de contenido
            $safety = $this->safetyValidator->validatePost($lockedPost);
            if (!$safety['is_safe']) {
                DB::rollBack();
                $violations = implode('; ', $safety['violations']);
                $msg = "Publicación rechazada por motivos de seguridad: {$violations}";
                return $request->wantsJson()
                    ? response()->json(['success' => false, 'message' => $msg, 'code' => 'safety_violation', 'violations' => $safety['violations']], 422)
                    : back()->with('error', $msg);
            }

            // 5. Publicar coherentemente
            $before = $lockedPost->getOriginal();
            $lockedPost->status = 'published';
            $lockedPost->is_published = true;
            $lockedPost->published_at = now();
            $lockedPost->save();

            // Actualizar metadatos sanitizados
            if ($meta) {
                $meta->security_status = 'passed';
                $meta->save();
            }

            // Registrar auditoría
            $this->auditTrail->recordModel(
                event: 'editorial_approved',
                model: $lockedPost,
                before: $before,
                after: $lockedPost->getAttributes(),
                context: ['action' => 'approve_and_publish', 'actor_id' => auth()->id()]
            );

            DB::commit();

            $msg = "La noticia '{$lockedPost->title}' ha sido aprobada y publicada correctamente.";
            return $request->wantsJson()
                ? response()->json(['success' => true, 'message' => $msg, 'post_id' => $lockedPost->id])
                : back()->with('success', $msg);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("AdminEditorialController: Error al aprobar post ID {$post->id}.", [
                'post_id' => $post->id,
                'code'    => $e->getCode(),
            ]);
            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => 'Error interno al procesar la aprobación.'], 500)
                : back()->with('error', 'Error al procesar la aprobación.');
        }
    }

    /**
     * Acción: Aprobar y Programar Publicación Futura.
     */
    public function schedule(Request $request, Post $post): JsonResponse|RedirectResponse
    {
        $this->ensureAuthorized();
        $this->ensureFromEmailPipeline($post);

        $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $scheduledAt = Carbon::parse($request->input('scheduled_at'));

        DB::beginTransaction();
        try {
            $lockedPost = Post::lockForUpdate()->find($post->id);

            if (!$lockedPost || $lockedPost->is_published) {
                DB::rollBack();
                $msg = 'La publicación ya fue aprobada o publicada anteriormente.';
                return $request->wantsJson()
                    ? response()->json(['success' => false, 'message' => $msg], 409)
                    : back()->with('error', $msg);
            }

            // 1. Respeto estricto de retenciones de seguridad y embargo
            $meta = $lockedPost->sanitizedMeta;
            if ($meta) {
                if (in_array($meta->security_status, ['held_security', 'rejected'], true)) {
                    DB::rollBack();
                    $msg = "Programación bloqueada: el contenido se encuentra retenido cautelarmente ({$meta->security_status}). Requiere resolución expresa.";
                    return $request->wantsJson()
                        ? response()->json(['success' => false, 'message' => $msg, 'code' => 'security_held'], 422)
                        : back()->with('error', $msg);
                }

                if ($meta->is_embargo_active) {
                    if ($meta->embargo_until === null) {
                        DB::rollBack();
                        $msg = 'Programación bloqueada: el material está bajo embargo indefinido o sin fecha confirmada. Requiere resolución expresa.';
                        return $request->wantsJson()
                            ? response()->json(['success' => false, 'message' => $msg, 'code' => 'embargo_conflict'], 422)
                            : back()->with('error', $msg);
                    }

                    if ($scheduledAt->lt($meta->embargo_until)) {
                        DB::rollBack();
                        $embargoStr = $meta->embargo_until->format('d/m/Y H:i');
                        $msg = "La fecha programada ({$scheduledAt->format('d/m/Y H:i')}) no puede ser anterior al levantamiento del embargo ({$embargoStr}).";
                        return $request->wantsJson()
                            ? response()->json(['success' => false, 'message' => $msg, 'code' => 'embargo_conflict'], 422)
                            : back()->with('error', $msg);
                    }
                }
            }

            // 2. Comprobar incidencias pendientes
            if ($lockedPost->incidents()->where('resolution_status', 'pending_review')->exists()) {
                DB::rollBack();
                $msg = 'No se puede programar: tiene incidencias editoriales pendientes de resolución.';
                return $request->wantsJson()
                    ? response()->json(['success' => false, 'message' => $msg], 422)
                    : back()->with('error', $msg);
            }

            // 2.1 Acción Explícita: Registrar URLs aprobadas si se autorizan explícitamente
            if ($request->boolean('approve_explicit_sources') || $request->has('approved_urls')) {
                if (! $meta) {
                    $meta = new EmailPostSanitizedMeta();
                    $meta->post_id = $lockedPost->id;
                    $meta->security_status = 'sanitized';
                }

                $candidateUrls = [];
                if ($request->has('sources_to_approve')) {
                    $candidateUrls = array_merge($candidateUrls, (array) $request->input('sources_to_approve'));
                } elseif ($request->has('approved_urls')) {
                    $candidateUrls = array_merge($candidateUrls, (array) $request->input('approved_urls'));
                }

                $validApproved = [];
                foreach ($candidateUrls as $rawCandidate) {
                    $candidate = trim((string) $rawCandidate);
                    if ($candidate === '') {
                        continue;
                    }
                    if (parse_url($candidate, PHP_URL_USER) !== null || parse_url($candidate, PHP_URL_PASS) !== null) {
                        continue;
                    }
                    $cl = $this->safetyValidator->getLinkClassifier()->classify($candidate);
                    if (in_array($cl['classification'], ['suspicious', 'restricted_private', 'under_embargo'], true)) {
                        continue;
                    }
                    $validApproved[] = $candidate;
                }

                if (! empty($validApproved)) {
                    $meta->registerApprovedUrls($validApproved, auth()->id());
                    $meta->save();
                    $lockedPost->setRelation('sanitizedMeta', $meta);
                }
            }

            // 3. Validación de seguridad (respetando fecha programada posterior al embargo)
            $safety = $this->safetyValidator->validatePost($lockedPost, $scheduledAt);
            if (!$safety['is_safe']) {
                DB::rollBack();
                $msg = 'Seguridad comprometida: ' . implode('; ', $safety['violations']);
                return $request->wantsJson()
                    ? response()->json(['success' => false, 'message' => $msg], 422)
                    : back()->with('error', $msg);
            }

            // 4. Programar coherentemente
            $before = $lockedPost->getOriginal();
            $lockedPost->status = 'scheduled';
            $lockedPost->is_published = false;
            $lockedPost->published_at = $scheduledAt;
            $lockedPost->save();

            if ($meta) {
                $meta->publish_permitted_at = $scheduledAt;
                $meta->security_status = 'passed';
                $meta->save();
            }

            $this->auditTrail->recordModel(
                event: 'editorial_scheduled',
                model: $lockedPost,
                before: $before,
                after: $lockedPost->getAttributes(),
                context: ['scheduled_at' => $scheduledAt->toDateTimeString(), 'actor_id' => auth()->id()]
            );

            DB::commit();

            $msg = "La noticia ha sido programada con éxito para el {$scheduledAt->format('d/m/Y H:i')}.";
            return $request->wantsJson()
                ? response()->json(['success' => true, 'message' => $msg, 'scheduled_at' => $scheduledAt->toDateTimeString()])
                : back()->with('success', $msg);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("AdminEditorialController: Error al programar post ID {$post->id}.", [
                'post_id' => $post->id,
                'code'    => $e->getCode(),
            ]);
            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => 'Error interno al programar la publicación.'], 500)
                : back()->with('error', 'Error interno al programar la publicación.');
        }
    }

    /**
     * Acción: Registrar aprobación editorial de URLs concretas verificadas para una noticia.
     */
    public function approveUrls(Request $request, Post $post): JsonResponse|RedirectResponse
    {
        $this->ensureAuthorized();
        $this->ensureFromEmailPipeline($post);

        $urlsInput = $request->input('urls', $request->input('approved_urls', []));
        if (is_string($urlsInput)) {
            $urlsInput = array_filter(array_map('trim', preg_split('/\r?\n|,/', $urlsInput) ?: []));
        }

        if (empty($urlsInput) && $request->boolean('approve_verified_sources')) {
            $meta = $post->sanitizedMeta;
            if ($meta && ! empty($meta->sources)) {
                $decoded = is_array($meta->sources) ? $meta->sources : json_decode((string) $meta->sources, true);
                if (is_array($decoded)) {
                    $urlsInput = $decoded;
                }
            }
        }

        if (empty($urlsInput) || ! is_array($urlsInput)) {
            $msg = 'Debe indicar al menos una URL concreta para aprobar.';
            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->with('error', $msg);
        }

        $meta = $post->sanitizedMeta ?: new EmailPostSanitizedMeta(['post_id' => $post->id, 'security_status' => 'sanitized']);

        $approvedList = [];
        $rejectedList = [];

        foreach ($urlsInput as $rawCandidate) {
            $candidate = trim((string) $rawCandidate);
            if ($candidate === '') {
                continue;
            }

            // 1. Bloqueo estricto de credenciales en URL
            if (parse_url($candidate, PHP_URL_USER) !== null || parse_url($candidate, PHP_URL_PASS) !== null) {
                $rejectedList[] = "La URL '{$this->maskSensitiveData($candidate)}' contiene credenciales no permitidas.";
                continue;
            }

            // 2. Bloqueo estricto de SSRF, esquemas no permitidos, enlaces privados o embargo
            $cl = $this->safetyValidator->getLinkClassifier()->classify($candidate);
            if (in_array($cl['classification'], ['suspicious', 'restricted_private', 'under_embargo'], true)) {
                $rejectedList[] = "La URL '{$cl['redacted_url']}' no es admisible para aprobación ({$cl['reason']}).";
                continue;
            }

            $approvedList[] = $candidate;
        }

        if (! empty($approvedList)) {
            $existing = is_array($meta->approved_urls) ? $meta->approved_urls : [];
            $meta->registerApprovedUrls($approvedList, auth()->id());
            $meta->save();

            $this->auditTrail->recordModel(
                event: 'editorial_urls_approved',
                model: $post,
                before: ['approved_urls' => $existing],
                after: [
                    'approved_urls' => $meta->approved_urls,
                    'approved_at'   => $meta->approved_at?->toIso8601String(),
                    'approved_by'   => $meta->approved_by,
                ],
                context: ['actor_id' => auth()->id(), 'count' => count($approvedList)]
            );
        }

        if (! empty($rejectedList)) {
            $msg = implode('; ', $rejectedList);
            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => $msg, 'approved' => $approvedList, 'rejected' => $rejectedList], 422)
                : back()->with('error', $msg);
        }

        $msg = count($approvedList) . ' URL(s) verificada(s) y aprobada(s) para esta noticia.';
        return $request->wantsJson()
            ? response()->json(['success' => true, 'message' => $msg, 'approved_urls' => $meta->approved_urls])
            : back()->with('success', $msg);
    }

    /**
     * Acción: Rechazar con trazabilidad (sin borrado físico).
     */
    public function reject(Request $request, Post $post): JsonResponse|RedirectResponse
    {
        $this->ensureAuthorized();
        $this->ensureFromEmailPipeline($post);

        $reason = $request->string('reason')->trim()->value() ?: 'Rechazado por el editor';

        DB::beginTransaction();
        try {
            $before = $post->getOriginal();
            $post->status = 'rejected';
            $post->is_published = false;
            $post->save();

            if ($meta = $post->sanitizedMeta) {
                $meta->security_status = 'rejected';
                $meta->incident_reason = $reason;
                $meta->save();
            }

            $this->auditTrail->recordModel(
                event: 'editorial_rejected',
                model: $post,
                before: $before,
                after: $post->getAttributes(),
                context: ['reason' => $reason, 'actor_id' => auth()->id()]
            );

            DB::commit();

            $msg = "Noticia rechazada y archivada con trazabilidad.";
            return $request->wantsJson()
                ? response()->json(['success' => true, 'message' => $msg])
                : back()->with('success', $msg);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("AdminEditorialController: Error al programar post ID {$post->id}.", [
                'post_id' => $post->id,
                'code'    => $e->getCode(),
            ]);
            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => 'Error interno al programar la publicación.'], 500)
                : back()->with('error', 'Error interno al programar la publicación.');
        }
    }

    /**
     * Acción: Retener por Seguridad o Investigación.
     */
    public function hold(Request $request, Post $post): JsonResponse|RedirectResponse
    {
        $this->ensureAuthorized();
        $this->ensureFromEmailPipeline($post);

        $reason = $request->string('reason')->trim()->value() ?: 'Retenido para revisión cautelar de seguridad';

        DB::beginTransaction();
        try {
            $post->status = 'draft';
            $post->is_published = false;
            $post->save();

            if ($meta = $post->sanitizedMeta) {
                $meta->security_status = 'held_security';
                $meta->incident_reason = $reason;
                $meta->save();
            }

            DB::commit();
            $msg = "Noticia retenida preventivamente.";
            return $request->wantsJson()
                ? response()->json(['success' => true, 'message' => $msg])
                : back()->with('success', $msg);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("AdminEditorialController: Error al programar post ID {$post->id}.", [
                'post_id' => $post->id,
                'code'    => $e->getCode(),
            ]);
            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => 'Error interno al programar la publicación.'], 500)
                : back()->with('error', 'Error interno al programar la publicación.');
        }
    }

    /**
     * Acción Masiva Aislada: Procesa cada elemento individualmente sin abortar los demás.
     */
    public function bulkAction(Request $request): JsonResponse|RedirectResponse
    {
        $this->ensureAuthorized();

        $request->validate([
            'action'       => ['required', 'in:approve,schedule,reject,hold'],
            'post_ids'     => ['required', 'array', 'min:1'],
            'post_ids.*'   => ['integer', 'exists:posts,id'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'reason'       => ['nullable', 'string', 'max:255'],
        ]);

        $action = $request->input('action');
        $postIds = array_map('intval', $request->input('post_ids'));
        $scheduledAt = $request->input('scheduled_at') ? Carbon::parse($request->input('scheduled_at')) : null;
        $reason = $request->input('reason', 'Acción masiva editorial');

        if ($action === 'schedule' && !$scheduledAt) {
            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => 'Se requiere una fecha y hora futura para programar.'], 422)
                : back()->with('error', 'Se requiere una fecha y hora para programar.');
        }

        $results = [
            'approved'  => [],
            'scheduled' => [],
            'rejected'  => [],
            'held'      => [],
            'blocked'   => [],
            'errors'    => [],
        ];

        foreach ($postIds as $postId) {
            DB::beginTransaction();
            try {
                $post = Post::lockForUpdate()->find($postId);

                // 1. Validar que provenga de correo
                if (!$post || !$post->isFromEmailPipeline()) {
                    DB::rollBack();
                    $results['blocked'][] = [
                        'id'     => $postId,
                        'title'  => $post?->title ?? 'Desconocido',
                        'reason' => 'No pertenece al circuito de ingesta editorial de correo.',
                    ];
                    continue;
                }

                // 2. Ejecutar según la acción solicitada
                if ($action === 'approve') {
                    // Validar concurrencia
                    if ($post->is_published || $post->status === 'published') {
                        DB::rollBack();
                        $results['blocked'][] = ['id' => $postId, 'title' => $post->title, 'reason' => 'Ya estaba publicada previamente.'];
                        continue;
                    }

                    // Validar incidencias
                    if ($post->incidents()->where('resolution_status', 'pending_review')->exists()) {
                        DB::rollBack();
                        $results['blocked'][] = ['id' => $postId, 'title' => $post->title, 'reason' => 'Incidencia editorial pendiente de resolución.'];
                        continue;
                    }

                    // Validar retención cautelar y embargo
                    $meta = $post->sanitizedMeta;
                    if ($meta) {
                        if (in_array($meta->security_status, ['held_security', 'rejected'], true)) {
                            DB::rollBack();
                            $results['blocked'][] = ['id' => $postId, 'title' => $post->title, 'reason' => "Retenido cautelarmente por seguridad ({$meta->security_status}). Requiere resolución expresa."];
                            continue;
                        }

                        if ($meta->is_embargo_active) {
                            if ($meta->embargo_until === null) {
                                DB::rollBack();
                                $results['blocked'][] = ['id' => $postId, 'title' => $post->title, 'reason' => 'Bajo embargo indefinido o sin fecha confirmada. Requiere resolución expresa.'];
                                continue;
                            }

                            if ($meta->embargo_until->isFuture()) {
                                DB::rollBack();
                                $results['blocked'][] = ['id' => $postId, 'title' => $post->title, 'reason' => "Bajo embargo activo hasta {$meta->embargo_until->format('d/m/Y H:i')}."];
                                continue;
                            }
                        }
                    }

                    // Acción Explícita: Registrar URLs aprobadas si se autorizan explícitamente
                    if ($request->boolean('approve_explicit_sources') || $request->has('approved_urls')) {
                        if (! $meta) {
                            $meta = new EmailPostSanitizedMeta();
                            $meta->post_id = $post->id;
                            $meta->security_status = 'sanitized';
                        }
                        $candidateUrls = [];
                        if ($request->has('sources_to_approve')) {
                            $candidateUrls = array_merge($candidateUrls, (array) $request->input('sources_to_approve'));
                        } elseif ($request->has('approved_urls')) {
                            $candidateUrls = array_merge($candidateUrls, (array) $request->input('approved_urls'));
                        }
                        $validApproved = [];
                        foreach ($candidateUrls as $rawCandidate) {
                            $candidate = trim((string) $rawCandidate);
                            if ($candidate === '' || parse_url($candidate, PHP_URL_USER) !== null || parse_url($candidate, PHP_URL_PASS) !== null) {
                                continue;
                            }
                            $cl = $this->safetyValidator->getLinkClassifier()->classify($candidate);
                            if (in_array($cl['classification'], ['suspicious', 'restricted_private', 'under_embargo'], true)) {
                                continue;
                            }
                            $validApproved[] = $candidate;
                        }
                        if (! empty($validApproved)) {
                            $meta->registerApprovedUrls($validApproved, auth()->id());
                            $meta->save();
                            $post->setRelation('sanitizedMeta', $meta);
                        }
                    }

                    // Validar seguridad
                    $safety = $this->safetyValidator->validatePost($post);
                    if (!$safety['is_safe']) {
                        DB::rollBack();
                        $results['blocked'][] = ['id' => $postId, 'title' => $post->title, 'reason' => 'Fallo de seguridad: ' . implode('; ', $safety['violations'])];
                        continue;
                    }

                    $post->status = 'published';
                    $post->is_published = true;
                    $post->published_at = now();
                    $post->save();

                    if ($meta) {
                        $meta->security_status = 'passed';
                        $meta->save();
                    }

                    $results['approved'][] = ['id' => $postId, 'title' => $post->title];
                } elseif ($action === 'schedule') {
                    if ($post->is_published) {
                        DB::rollBack();
                        $results['blocked'][] = ['id' => $postId, 'title' => $post->title, 'reason' => 'La noticia ya se encuentra publicada.'];
                        continue;
                    }

                    $meta = $post->sanitizedMeta;
                    if ($meta) {
                        if (in_array($meta->security_status, ['held_security', 'rejected'], true)) {
                            DB::rollBack();
                            $results['blocked'][] = ['id' => $postId, 'title' => $post->title, 'reason' => "Retenido cautelarmente por seguridad ({$meta->security_status}). Requiere resolución expresa."];
                            continue;
                        }

                        if ($meta->is_embargo_active) {
                            if ($meta->embargo_until === null) {
                                DB::rollBack();
                                $results['blocked'][] = ['id' => $postId, 'title' => $post->title, 'reason' => 'Bajo embargo indefinido o sin fecha confirmada. Requiere resolución expresa.'];
                                continue;
                            }

                            if ($scheduledAt->lt($meta->embargo_until)) {
                                DB::rollBack();
                                $results['blocked'][] = ['id' => $postId, 'title' => $post->title, 'reason' => "Fecha programada anterior al fin del embargo ({$meta->embargo_until->format('d/m/Y H:i')})."];
                                continue;
                            }
                        }
                    }

                    if ($post->incidents()->where('resolution_status', 'pending_review')->exists()) {
                        DB::rollBack();
                        $results['blocked'][] = ['id' => $postId, 'title' => $post->title, 'reason' => 'Incidencia editorial pendiente.'];
                        continue;
                    }

                    $safety = $this->safetyValidator->validatePost($post, $scheduledAt);
                    if (!$safety['is_safe']) {
                        DB::rollBack();
                        $results['blocked'][] = ['id' => $postId, 'title' => $post->title, 'reason' => 'Fallo de seguridad: ' . implode('; ', $safety['violations'])];
                        continue;
                    }

                    $post->status = 'scheduled';
                    $post->is_published = false;
                    $post->published_at = $scheduledAt;
                    $post->save();

                    if ($meta) {
                        $meta->publish_permitted_at = $scheduledAt;
                        $meta->save();
                    }

                    $results['scheduled'][] = ['id' => $postId, 'title' => $post->title];
                } elseif ($action === 'reject') {
                    $post->status = 'rejected';
                    $post->is_published = false;
                    $post->save();

                    if ($meta = $post->sanitizedMeta) {
                        $meta->security_status = 'rejected';
                        $meta->incident_reason = $reason;
                        $meta->save();
                    }

                    $results['rejected'][] = ['id' => $postId, 'title' => $post->title];
                } elseif ($action === 'hold') {
                    $post->status = 'draft';
                    $post->is_published = false;
                    $post->save();

                    if ($meta = $post->sanitizedMeta) {
                        $meta->security_status = 'held_security';
                        $meta->incident_reason = $reason;
                        $meta->save();
                    }

                    $results['held'][] = ['id' => $postId, 'title' => $post->title];
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $results['errors'][] = ['id' => $postId, 'reason' => $e->getMessage()];
            }
        }

        $successCount = count($results['approved']) + count($results['scheduled']) + count($results['rejected']) + count($results['held']);
        $blockedCount = count($results['blocked']) + count($results['errors']);

        $summaryMsg = "Acción masiva finalizada: {$successCount} procesadas correctamente" . ($blockedCount > 0 ? ", {$blockedCount} retenidas o bloqueadas." : ".");

        return $request->wantsJson()
            ? response()->json([
                'success' => true,
                'message' => $summaryMsg,
                'results' => $results,
            ])
            : back()->with('bulk_results', $results)->with('success', $summaryMsg);
    }

    /**
     * Enmascara contraseñas, tokens y claves para garantizar que no viajen al navegador.
     */
    protected function maskSensitiveData(string $text): string
    {
        return preg_replace(
            '/\b(?:password|passcode|contrase[ñn]a|token|secret|key|download_code)\s*[:=]\s*[^\s<"\'&]+/iu',
            '[PROTECTED_CREDENTIAL]',
            $text
        ) ?? $text;
    }

    protected function ensureAuthorized(): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(401, 'No autenticado.');
        }

        // Requiere rol de administrador o permiso explícito
        $isAuthorized = false;
        if (method_exists($user, 'hasAnyRole')) {
            $isAuthorized = $user->hasAnyRole(['Admin', 'Super Admin']);
        } elseif (method_exists($user, 'can')) {
            $isAuthorized = $user->can('admin') || $user->can('publish-posts');
        }

        if (!$isAuthorized && !($user->is_admin ?? false)) {
            abort(403, 'Acceso denegado: se requieren permisos administrativos para operar la Bandeja Editorial.');
        }
    }

    protected function ensureFromEmailPipeline(Post $post): void
    {
        if (!$post->isFromEmailPipeline()) {
            abort(403, 'Acceso restringido: Esta publicación fue creada manualmente y es ajena a la Bandeja Editorial.');
        }
    }
}
