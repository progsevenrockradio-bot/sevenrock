<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmailPostSanitizedMeta;
use App\Models\Post;
use App\Models\PostTaxonomy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EditorialBatchProcessor
{
    public function __construct(
        protected EditorialDarthVaderParser $parser,
        protected ?DvAutoPublishPolicy $autoPublishPolicy = null
    ) {
        $this->autoPublishPolicy = $autoPublishPolicy ?? app(DvAutoPublishPolicy::class);
    }

    /**
     * Valida y procesa un lote estructurado DV-1 desde texto crudo.
     *
     * @param string $rawContent Contenido completo del lote DV-1
     * @param array<string, mixed> $imagesManifest Manifiesto de imágenes asociadas
     * @param string|null $sourceMessageId Identificador de mensaje de correo (si proviene de IMAP)
     * @param string $senderEmail Correo del remitente autenticado
     * @param bool $isDryRun Si es true, simula sin persistir en BD
     * @param bool $allowUpdateApproval Si es true, permite actualizar borradores existentes ante contenido modificado
     * @param bool|null $autoPublish Si es true o null con configuración activa, evalúa política automática DV-1
     * @return array{
     *     success: bool,
     *     batch_id: string,
     *     total_declared: int,
     *     total_extracted: int,
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     conflicts: int,
     *     auto_published_noticias: int,
     *     auto_published_efemerides: int,
     *     auto_published_total: int,
     *     warnings: array<int, string>,
     *     processed_elements: array<int, string>,
     *     failed_elements: array<string, string>,
     *     elements_detail: array<string, array<string, mixed>>,
     *     error: ?string
     * }
     */
    public function process(
        string $rawContent,
        array $imagesManifest = [],
        ?string $sourceMessageId = null,
        string $senderEmail = 'dark.vader.agent@gmail.com',
        bool $isDryRun = false,
        bool $allowUpdateApproval = false,
        ?bool $autoPublish = null
    ): array {
        // ── 1. VALIDACIÓN PREVIA DEL LOTE (FAIL-FAST ATÓMICO) ─────────────────
        if (! $this->parser->isDv1Format('Lote Editorial', $rawContent)) {
            return $this->failureResponse('UNKNOWN', 'El contenido no cumple con la especificación estructurada DV-1.');
        }

        $parsed = $this->parser->parseDv1('Lote Editorial', $rawContent);
        $batchId = $parsed['batch_id'];
        $totalDeclared = $parsed['total_declared'];
        $totalExtracted = $parsed['total_extracted'];
        $elements = $parsed['elements'];

        // Si no se proporcionó manifiesto explícito, autodescubrir si existe en almacenamiento local
        if (empty($imagesManifest) && ! empty($batchId)) {
            $defaultManifestPath = storage_path("editorial_batches/{$batchId}/images_installed.json");
            if (file_exists($defaultManifestPath)) {
                $imagesManifest = json_decode((string) file_get_contents($defaultManifestPath), true) ?: [];
            }
        }

        // Validar conteo declarado vs extraído
        if ($totalDeclared > 0 && $totalDeclared !== $totalExtracted) {
            return $this->failureResponse(
                $batchId,
                "Discrepancia de conteo en lote {$batchId}: se declararon {$totalDeclared} elementos pero se extrajeron {$totalExtracted}."
            );
        }

        if ($totalExtracted === 0) {
            return $this->failureResponse($batchId, "El lote {$batchId} no contiene elementos válidos.");
        }

        // Validar identificadores duplicados dentro del mismo lote
        $seenIds = [];
        foreach ($elements as $idx => $elem) {
            $id = trim((string) ($elem['id'] ?? ''));
            if ($id === '') {
                return $this->failureResponse($batchId, "El elemento en posición {$idx} carece de identificador ID obligatorio.");
            }
            if (isset($seenIds[$id])) {
                return $this->failureResponse($batchId, "Identificador duplicado dentro del lote {$batchId}: '{$id}'.");
            }
            $seenIds[$id] = true;

            // Validar campos obligatorios por elemento
            $titulo = trim((string) ($elem['titulo'] ?? ''));
            if ($titulo === '') {
                return $this->failureResponse($batchId, "El elemento '{$id}' carece de campo TITULO obligatorio.");
            }

            $texto = trim((string) ($elem['texto'] ?? ''));
            if (mb_strlen($texto) < 30) {
                return $this->failureResponse($batchId, "El elemento '{$id}' contiene un campo TEXTO insuficiente (mínimo 30 caracteres).");
            }

            $tipo = strtoupper(trim((string) ($elem['tipo'] ?? '')));
            if (! in_array($tipo, ['NOTICIA', 'EFEMERIDE'], true)) {
                return $this->failureResponse($batchId, "El elemento '{$id}' tiene un TIPO inválido: '{$tipo}'. Debe ser NOTICIA o EFEMERIDE.");
            }
        }

        // Si no se proporcionó manifiesto de imágenes (ej. recepción directa por correo), intentar resolver manifiesto local preexistente
        if (empty($imagesManifest)) {
            $manifestCandidate = storage_path("editorial_batches/{$batchId}/images_installed.json");
            if (file_exists($manifestCandidate)) {
                $decodedManifest = json_decode((string) file_get_contents($manifestCandidate), true);
                if (is_array($decodedManifest)) {
                    $imagesManifest = $decodedManifest;
                }
            }
        }

        // ── 2. PREPARACIÓN DE ENTORNO Y RECURSOS ─────────────────────────────
        $taxNoticias = PostTaxonomy::where('slug', 'noticias-rock')->first();
        $taxEfemerides = PostTaxonomy::where('slug', 'hoy-en-el-rock')->first();
        $adminUser = User::first();

        $isAutoPublishEnabled = $autoPublish !== null
            ? (bool) $autoPublish
            : (bool) (
                \App\Models\ThemeSetting::current()->email_auto_publish 
                ?? config('services.email_import.auto_publish', false) 
                ?? env('EMAIL_AUTO_PUBLISH', false)
            );

        $autoPublishedNoticias = 0;
        $autoPublishedEfemerides = 0;

        $processedElements = [];
        $failedElements = [];
        $elementsDetail = [];
        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $conflictCount = 0;

        // ── 3. PROCESAMIENTO ELEMENTO A ELEMENTO CON REIMPORTACIÓN SEGURA ────
        foreach ($elements as $elem) {
            $elemId = $elem['id'];
            $tipo = $elem['tipo'];
            $artist = $elem['artista_banda'] ?? '';
            $title = $elem['titulo'];
            $text = $elem['texto'];
            $fuentes = $elem['fuentes'] ?? [];
            $revisionRequerida = (bool) ($elem['revision_requerida'] ?? false);
            $motivoRevision = $elem['motivo_revision'] ?? null;

            // Resolver imagen y metadatos accesibles desde manifiesto o elemento
            $imageMeta = $imagesManifest[$elemId] ?? null;

            // Calcular hash determinista y canónico del payload del elemento
            $payloadHash = self::computeStablePayloadHash($elem, $imageMeta);

            // Comprobar si ya existe registro previo para (batch_id, element_id)
            $existingMeta = EmailPostSanitizedMeta::where('batch_id', $batchId)
                ->where('element_id', $elemId)
                ->first();

            $existingPost = null;
            if ($existingMeta && $existingMeta->post_id) {
                $existingPost = Post::find($existingMeta->post_id);
            }

            // CASO A: Lote idéntico ya procesado previamente (mismo hash)
            if ($existingMeta && $existingMeta->payload_hash === $payloadHash && $existingPost) {
                $skippedCount++;
                $processedElements[] = $elemId;
                $elementsDetail[$elemId] = [
                    'action'     => 'skipped_identical',
                    'post_id'    => $existingPost->id,
                    'status'     => $existingPost->status,
                    'published'  => (bool) $existingPost->is_published,
                    'revision'   => $revisionRequerida,
                    'reason'     => 'Contenido idéntico al procesado previamente. Omitido sin modificaciones.',
                ];
                continue;
            }

            // CASO B: El artículo ya existe y ESTÁ PUBLICADO
            if ($existingPost && ($existingPost->is_published || $existingPost->status === 'published')) {
                // REGLA CRÍTICA: NUNCA despublicar, nunca convertir a draft, nunca borrar fecha ni sobrescribir
                $conflictCount++;
                $processedElements[] = $elemId;

                $existingMeta->editorial_notes = "[CONFLICTO_DETECTADO] Se recibió contenido modificado para el elemento ya publicado. Conservado el artículo publicado sin sobrescribir ni despublicar.";
                $existingMeta->security_status = 'conflict';
                if (! $isDryRun) {
                    $existingMeta->save();
                }

                $elementsDetail[$elemId] = [
                    'action'     => 'protected_published',
                    'post_id'    => $existingPost->id,
                    'status'     => $existingPost->status,
                    'published'  => true,
                    'revision'   => true,
                    'reason'     => 'Artículo ya publicado protegido: no se sobrescribe ni se convierte en borrador.',
                ];
                continue;
            }

            // CASO C: El artículo ya existe como borrador pero el contenido cambió
            if ($existingPost && $existingMeta && $existingMeta->payload_hash !== $payloadHash) {
                if (! $allowUpdateApproval) {
                    // REGLA CRÍTICA: Conservar íntegros título, contenido, imagen y cambios editoriales existentes
                    $conflictCount++;
                    $processedElements[] = $elemId;

                    $existingMeta->editorial_notes = "[CONFLICTO_DETECTADO] Se recibió contenido modificado para el borrador existente. Se conservan íntegros título, contenido, imagen y cambios editoriales a la espera de aprobación explícita.";
                    $existingMeta->security_status = 'conflict';
                    if (Schema::hasColumn('email_post_sanitized_meta', 'requires_review')) {
                        $existingMeta->requires_review = true;
                    }
                    if (Schema::hasColumn('email_post_sanitized_meta', 'review_reason')) {
                        $existingMeta->review_reason = "[CONTENIDO_MODIFICADO] El contenido recibido difiere del borrador previo. Requiere aprobación explícita para actualizar.";
                    }
                    if (! $isDryRun) {
                        $existingMeta->save();
                    }

                    $elementsDetail[$elemId] = [
                        'action'    => 'conflict_preserved_existing',
                        'post_id'   => $existingPost->id,
                        'status'    => $existingPost->status,
                        'published' => false,
                        'revision'  => true,
                        'reason'    => 'Borrador existente preservado íntegro: se detectó contenido modificado y se requiere aprobación explícita para actualizar.',
                    ];
                    continue; // NO continúa hacia la sobrescritura ni descarga de imágenes
                }

                // Si hay aprobación explícita de actualización:
                $conflictCount++;
                $revisionRequerida = true;
                $motivoRevision = "[CONTENIDO_MODIFICADO_APROBADO] Actualización explícitamente autorizada. " . ($motivoRevision ?: '');
            }

            if ($isDryRun) {
                $processedElements[] = $elemId;
                $elementsDetail[$elemId] = [
                    'action'    => $existingPost ? 'would_update_draft' : 'would_create_draft',
                    'post_id'   => $existingPost?->id,
                    'status'    => 'draft',
                    'published' => false,
                    'revision'  => $revisionRequerida,
                    'reason'    => $motivoRevision,
                ];
                if ($existingPost) {
                    $updatedCount++;
                } else {
                    $createdCount++;
                }
                continue;
            }

            // ── RESOLUCIÓN DE IMAGEN (SOLO PARA ARTÍCULOS QUE SE CREAN O ACTUALIZAN) ──
            $rawPath = $imageMeta['path'] ?? $elem['imagen_url'] ?? null;
            $rawCredit = $imageMeta['meta']['credit'] ?? $elem['imagen_credito'] ?? null;
            $imageCredit = ($rawCredit === 'NO_CONFIRMADO' || $rawCredit === 'none' || $rawCredit === '') ? null : $rawCredit;
            $imageAlt = $imageMeta['meta']['alt'] ?? $elem['imagen_alt'] ?? $title;

            $isExplicitlyNone = ($rawPath === 'NINGUNA' || $rawPath === 'none');
            $imagePath = $isExplicitlyNone ? null : $rawPath;

            if ($isExplicitlyNone) {
                // IMAGEN_URL era NINGUNA o none -> Pendiente visible de imagen
                $revisionRequerida = true;
                $motivoRevision = "[IMAGEN_PENDIENTE] Imagen no proporcionada ('{$rawPath}')" . ($motivoRevision ? " | {$motivoRevision}" : "");
            } elseif (! empty($imagePath) && (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://'))) {
                $imageService = app(\App\Services\EditorialImageService::class);
                $imgResult = $imageService->resolveAndStore($imagePath, $batchId, $elemId, Str::slug($title), $imageCredit);

                if ($imgResult['success'] && ! empty($imgResult['path'])) {
                    $imagePath = $imgResult['path'];
                    if (! empty($imgResult['credit'])) {
                        $imageCredit = $imgResult['credit'];
                    }
                } else {
                    $imagePath = null;
                    $revisionRequerida = true;
                    $motivoRevision = "[IMAGEN_PENDIENTE] " . ($imgResult['error'] ?? 'Fallo de descarga de imagen') . ($motivoRevision ? " | {$motivoRevision}" : "");
                }
            } elseif (! empty($imagePath)) {
                // Ruta local: validar existencia
                $storageRelative = str_starts_with($imagePath, 'storage/') ? substr($imagePath, 8) : $imagePath;
                $fullPath = storage_path('app/public/' . $storageRelative);
                $publicPath = public_path($imagePath);

                if (! file_exists($fullPath) && ! file_exists($publicPath)) {
                    $revisionRequerida = true;
                    $motivoRevision = "[IMAGEN_PENDIENTE] Archivo local no encontrado en disco: '{$rawPath}'" . ($motivoRevision ? " | {$motivoRevision}" : "");
                }
            }

            // ── PERSISTENCIA TRANSACCIONAL POR ELEMENTO ─────────────────────
            DB::beginTransaction();
            try {
                // REGLA CRÍTICA: Nunca borrar una imagen ya asignada a un post existente si el nuevo payload no aporta una imagen
                if (empty($imagePath) && $existingPost && ! empty($existingPost->featured_image_path)) {
                    $imagePath = $existingPost->featured_image_path;
                }
                if (empty($imageAlt) && $existingPost && ! empty($existingPost->image_alt)) {
                    $imageAlt = $existingPost->image_alt;
                }
                if (empty($imageCredit) && $existingMeta && ! empty($existingMeta->image_credit)) {
                    $imageCredit = $existingMeta->image_credit;
                }

                $htmlContent = $this->buildHtmlContent($text, $fuentes, $imageCredit);

                $slug = Str::slug($title);
                if (! $existingPost) {
                    $slugCount = Post::where('slug', $slug)->count();
                    if ($slugCount > 0) {
                        $slug = "{$slug}-" . Str::lower(substr($elemId, -3));
                    }
                }

                $post = $existingPost ?: new Post();
                if (Schema::hasColumn('posts', 'user_id')) {
                    $post->user_id = $adminUser ? $adminUser->id : 1;
                }
                $post->author = 'Seven Rock Radio';
                $post->title = $title;
                $post->slug = $existingPost ? $post->slug : $slug;
                $post->content = $htmlContent;
                $post->excerpt = Str::limit(strip_tags($text), 220, '...');
                $post->source_name = 'Darth Vader / Agencia Editorial';
                $post->source_subject = Str::limit("[DV-1] {$title}", 255, '');

                $safeImageCredit = $imageCredit ? Str::limit((string) $imageCredit, 500, '') : null;
                $safeImageAlt = $imageAlt ? Str::limit((string) $imageAlt, 500, '') : null;

                if ($safeImageCredit && Schema::hasColumn('posts', 'image_source')) {
                    $post->image_source = $safeImageCredit;
                }

                if ($imagePath) {
                    if (Schema::hasColumn('posts', 'featured_image_path')) {
                        $post->featured_image_path = $imagePath;
                    }
                    if (Schema::hasColumn('posts', 'featured_image')) {
                        $post->featured_image = $imagePath;
                    }
                }

                // Persistir image_alt si existe la columna en posts
                if (Schema::hasColumn('posts', 'image_alt')) {
                    $post->image_alt = $safeImageAlt;
                }

                // Asignar taxonomía y categorías antes de evaluar política
                $taxonomyId = ($tipo === 'EFEMERIDE')
                    ? ($taxEfemerides?->id)
                    : ($taxNoticias?->id);

                $categoryName = ($tipo === 'EFEMERIDE') ? 'Hoy en el Rock' : 'Noticias Rock';
                if (Schema::hasColumn('posts', 'categories')) {
                    $post->categories = [$categoryName];
                }

                // ── EVALUACIÓN DE POLÍTICA DE AUTO-PUBLICACIÓN DV-1 ───────────
                $policyEval = ['can_auto_publish' => false, 'reason' => 'Publicación automática no requerida'];
                if ($isAutoPublishEnabled) {
                    $policyEval = $this->autoPublishPolicy->evaluate(
                        $elem,
                        $post,
                        $imagePath,
                        $safeImageCredit,
                        $safeImageAlt,
                        [
                            'sender_email' => $senderEmail,
                            'is_sender_authenticated' => true,
                            'is_conflict' => false,
                        ]
                    );
                }

                $isAutoPublished = false;
                if ($isAutoPublishEnabled && $policyEval['can_auto_publish']) {
                    $post->status = 'published';
                    $post->is_published = 1;
                    $post->published_at = now();
                    $isAutoPublished = true;
                    if ($tipo === 'EFEMERIDE') {
                        $autoPublishedEfemerides++;
                    } else {
                        $autoPublishedNoticias++;
                    }
                } else {
                    $post->status = 'draft';
                    $post->is_published = 0;
                    $post->published_at = null;
                }

                $post->save();

                if ($taxonomyId) {
                    $post->taxonomies()->syncWithoutDetaching([$taxonomyId]);
                }

                // Guardar/actualizar metadatos sanitizados con restricción única
                $meta = $existingMeta ?: new EmailPostSanitizedMeta();
                $meta->post_id = $post->id;
                $meta->source_message_id = $sourceMessageId ?: "dv-local-{$batchId}-{$elemId}";
                $meta->batch_id = $batchId;
                $meta->element_id = $elemId;
                $meta->payload_hash = $payloadHash;
                $meta->sender_email = $senderEmail;
                $meta->source_subject = Str::limit("[DV-1] {$title}", 255, '');
                $meta->received_at = now();
                $meta->artist_name = $artist ? Str::limit((string) $artist, 255, '') : null;
                $meta->event_date = $elem['fecha_acontecimiento'] ?? null;
                $meta->sources = json_encode($fuentes, JSON_UNESCAPED_SLASHES);
                $meta->image_credit = $safeImageCredit;
                if (Schema::hasColumn('email_post_sanitized_meta', 'image_alt')) {
                    $meta->image_alt = $safeImageAlt;
                }

                if ($isAutoPublished) {
                    // Evidencia explícita de política automática (sin firma humana falsa)
                    $meta->approved_urls = $policyEval['approved_urls'];
                    $meta->approved_at = now();
                    $meta->approved_by = null; // ACREDITA POLÍTICA AUTOMÁTICA
                    $meta->security_status = 'passed';
                    $meta->editorial_notes = '[AUTO_PUBLICADO] Publicado conforme a política automática DV-1';
                    $meta->incident_reason = null;
                } else {
                    if ($revisionRequerida) {
                        $meta->editorial_notes = "[REVISION_REQUERIDA] " . ($motivoRevision ?: 'Pendiente de validación editorial');
                        $meta->security_status = 'needs_review';
                        if ($motivoRevision) {
                            $meta->incident_reason = Str::limit((string) $motivoRevision, 500, '');
                        }
                    } elseif ($isAutoPublishEnabled && ! $policyEval['can_auto_publish']) {
                        $meta->editorial_notes = '[BORRADOR_POR_POLITICA] ' . $policyEval['reason'];
                        $meta->security_status = 'needs_review';
                        $meta->incident_reason = Str::limit((string) $policyEval['reason'], 500, '');
                    } else {
                        $meta->editorial_notes = '[APROBADO_PARA_REVISION] Sin observaciones pendientes';
                        $meta->security_status = 'sanitized';
                    }
                }
                $meta->save();

                // Registrar auditoría si fue auto-publicado
                if ($isAutoPublished) {
                    try {
                        app(AuditTrailService::class)->recordModel(
                            event: 'editorial_auto_policy_published',
                            model: $post,
                            before: ['status' => 'draft', 'is_published' => 0],
                            after: ['status' => 'published', 'is_published' => 1, 'published_at' => $post->published_at?->toIso8601String()],
                            context: [
                                'action'   => 'auto_policy_publish',
                                'batch_id' => $batchId,
                                'elem_id'  => $elemId,
                                'sources'  => $policyEval['approved_urls'],
                            ]
                        );
                    } catch (\Throwable $auditEx) {
                        Log::warning("EditorialBatchProcessor: Fallo al registrar auditoría de auto-publicación: {$auditEx->getMessage()}");
                    }
                }

                DB::commit();

                $processedElements[] = $elemId;
                $action = $existingPost ? 'updated' : 'created';
                if ($existingPost) {
                    $updatedCount++;
                } else {
                    $createdCount++;
                }

                $elementsDetail[$elemId] = [
                    'action'    => $action,
                    'post_id'   => $post->id,
                    'status'    => $post->status,
                    'published' => (bool) $post->is_published,
                    'revision'  => $revisionRequerida,
                    'reason'    => $isAutoPublished ? 'Publicado por política automática' : ($motivoRevision ?: ($policyEval['reason'] ?? null)),
                ];
            } catch (\Throwable $e) {
                DB::rollBack();
                $isUniqueViolation = str_contains($e->getMessage(), 'Integrity constraint violation')
                    || str_contains($e->getMessage(), 'UNIQUE constraint failed')
                    || str_contains($e->getMessage(), 'Duplicate entry');

                $errorLabel = $isUniqueViolation
                    ? "[COLISIÓN_CONCURRENCIA] Clave compuesta ({$batchId}, {$elemId}) duplicada: {$e->getMessage()}"
                    : $e->getMessage();

                $failedElements[$elemId] = $errorLabel;
                Log::error("EditorialBatchProcessor: Fallo al persistir elemento {$elemId}", [
                    'batch_id' => $batchId,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        $warnings = [];
        if ($isAutoPublishEnabled) {
            if ($autoPublishedNoticias < 4) {
                $warnings[] = "Aviso editorial: Se publicaron {$autoPublishedNoticias} noticias nuevas (menos del umbral de 4 recomendado para renovar completamente el bloque de portada).";
            }
            if ($autoPublishedEfemerides < 4) {
                $warnings[] = "Aviso editorial: Se publicaron {$autoPublishedEfemerides} efemérides nuevas (menos del umbral de 4 recomendado).";
            }
        }

        $allSucceeded = count($failedElements) === 0;

        return [
            'success'                   => $allSucceeded,
            'batch_id'                  => $batchId,
            'total_declared'            => $totalDeclared,
            'total_extracted'           => $totalExtracted,
            'created'                   => $createdCount,
            'updated'                   => $updatedCount,
            'skipped'                   => $skippedCount,
            'conflicts'                 => $conflictCount,
            'auto_published_noticias'   => $autoPublishedNoticias,
            'auto_published_efemerides' => $autoPublishedEfemerides,
            'auto_published_total'      => $autoPublishedNoticias + $autoPublishedEfemerides,
            'warnings'                  => $warnings,
            'processed_elements'        => $processedElements,
            'failed_elements'           => $failedElements,
            'elements_detail'           => $elementsDetail,
            'error'                     => $allSucceeded ? null : 'Uno o más elementos no pudieron ser persistidos.',
        ];
    }

    protected function failureResponse(string $batchId, string $errorMessage): array
    {
        return [
            'success'                   => false,
            'batch_id'                  => $batchId,
            'total_declared'            => 0,
            'total_extracted'           => 0,
            'created'                   => 0,
            'updated'                   => 0,
            'skipped'                   => 0,
            'conflicts'                 => 0,
            'auto_published_noticias'   => 0,
            'auto_published_efemerides' => 0,
            'auto_published_total'      => 0,
            'warnings'                  => [],
            'processed_elements'        => [],
            'failed_elements'           => [],
            'elements_detail'           => [],
            'error'                     => $errorMessage,
        ];
    }

    protected function buildHtmlContent(string $text, array $sources, ?string $imageCredit): string
    {
        $paragraphs = array_values(array_filter(array_map('trim', preg_split('/\r?\n\r?\n/', $text) ?: [])));
        $html = '';

        foreach ($paragraphs as $p) {
            $html .= '<p>' . htmlspecialchars($p, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>' . "\n";
        }

        if (! empty($sources)) {
            $html .= "\n" . '<div class="editorial-sources mt-4 pt-3 border-t border-gray-700 text-sm text-gray-400">' . "\n";
            $html .= '<p class="font-semibold text-gray-300">Fuentes consultadas:</p>' . "\n";
            $html .= '<ul class="list-disc pl-5 mt-1 space-y-1">' . "\n";
            foreach ($sources as $s) {
                $safeUrl = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $host = parse_url($s, PHP_URL_HOST) ?: $s;
                $html .= '<li><a href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer" class="text-red-400 hover:underline">' . htmlspecialchars((string) $host, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a></li>' . "\n";
            }
            $html .= '</ul>' . "\n";
            $html .= '</div>' . "\n";
        }

        return $html;
    }

    /**
     * Calcula una representación canónica y un hash determinista SHA-256 para el elemento.
     * Incorpora tipo, título, artista/banda, fechas, texto, fuentes ordenadas, estado de revisión,
     * motivo de revisión y metadatos de imagen canónicos.
     */
    public static function computeStablePayloadHash(array $elem, ?array $imageMeta = null): string
    {
        $tipo = strtoupper(trim((string) ($elem['tipo'] ?? '')));
        $title = trim((string) ($elem['titulo'] ?? ''));
        $artist = trim((string) ($elem['artista_banda'] ?? ''));
        $eventDate = trim((string) ($elem['fecha_acontecimiento'] ?? $elem['fecha_editorial'] ?? ''));
        $text = preg_replace('/\r\n|\r/', "\n", trim((string) ($elem['texto'] ?? '')));

        $fuentes = (array) ($elem['fuentes'] ?? []);
        $cleanFuentes = array_values(array_filter(array_map('trim', $fuentes)));
        sort($cleanFuentes);

        $revReq = ! empty($elem['revision_requerida']) && $elem['revision_requerida'] !== false && $elem['revision_requerida'] !== 'NO' ? '1' : '0';
        $revMotivo = trim((string) ($elem['motivo_revision'] ?? ''));

        $rawUrl = (string) ($imageMeta['meta']['url'] ?? $elem['imagen_url'] ?? '');
        $imageUrl = ($rawUrl === 'NINGUNA' || $rawUrl === 'none') ? '' : trim($rawUrl);

        $rawCredit = (string) ($imageMeta['meta']['credit'] ?? $elem['imagen_credito'] ?? '');
        $imageCredit = ($rawCredit === 'NO_CONFIRMADO' || $rawCredit === 'none') ? '' : trim($rawCredit);

        $imageAlt = trim((string) ($imageMeta['meta']['alt'] ?? $elem['imagen_alt'] ?? ''));

        $canonical = [
            'tipo'                 => $tipo,
            'titulo'               => $title,
            'artista_banda'        => $artist,
            'fecha_acontecimiento' => $eventDate,
            'texto'                => $text,
            'fuentes'              => $cleanFuentes,
            'revision_requerida'   => $revReq,
            'motivo_revision'      => $revMotivo,
            'imagen_url'           => $imageUrl,
            'imagen_credito'       => $imageCredit,
            'imagen_alt'           => $imageAlt,
        ];

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
