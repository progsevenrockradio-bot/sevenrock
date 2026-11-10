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
        protected EditorialDarthVaderParser $parser
    ) {}

    /**
     * Valida y procesa un lote estructurado DV-1 desde texto crudo.
     *
     * @param string $rawContent Contenido completo del lote DV-1
     * @param array<string, mixed> $imagesManifest Manifiesto de imágenes asociadas
     * @param string|null $sourceMessageId Identificador de mensaje de correo (si proviene de IMAP)
     * @param string $senderEmail Correo del remitente autenticado
     * @param bool $isDryRun Si es true, simula sin persistir en BD
     * @return array{
     *     success: bool,
     *     batch_id: string,
     *     total_declared: int,
     *     total_extracted: int,
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     conflicts: int,
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
        bool $isDryRun = false
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

        // ── 2. PREPARACIÓN DE ENTORNO Y RECURSOS ─────────────────────────────
        $taxNoticias = PostTaxonomy::where('slug', 'noticias-rock')->first();
        $taxEfemerides = PostTaxonomy::where('slug', 'hoy-en-el-rock')->first();
        $adminUser = User::first();

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

            // Calcular hash determinista del payload del elemento
            $payloadHash = hash('sha256', "{$tipo}|{$title}|{$text}|" . json_encode($fuentes, JSON_UNESCAPED_SLASHES));

            // Resolver imagen y metadatos accesibles desde manifiesto o elemento
            $imageMeta = $imagesManifest[$elemId] ?? null;
            $imagePath = $imageMeta['path'] ?? $elem['imagen_url'] ?? null;
            $imageCredit = $imageMeta['meta']['credit'] ?? $elem['imagen_credito'] ?? null;
            $imageAlt = $imageMeta['meta']['alt'] ?? $title;

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
                $conflictCount++;
                $revisionRequerida = true;
                $motivoRevision = "[CONTENIDO_MODIFICADO] El contenido recibido difiere del borrador previo. " . ($motivoRevision ?: 'Requiere revisión.');
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

            // ── PERSISTENCIA TRANSACCIONAL POR ELEMENTO ─────────────────────
            DB::beginTransaction();
            try {
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
                $post->status = 'draft'; // ESTRICTAMENTE BORRADOR
                $post->is_published = 0; // NO PUBLICADO
                $post->published_at = null;
                $post->content = $htmlContent;
                $post->excerpt = Str::limit(strip_tags($text), 220, '...');
                $post->source_name = 'Darth Vader / Agencia Editorial';
                $post->source_subject = "[DV-1] {$title}";

                if ($imagePath) {
                    if (Schema::hasColumn('posts', 'featured_image_path')) {
                        $post->featured_image_path = $imagePath;
                    }
                    if (Schema::hasColumn('posts', 'featured_image')) {
                        $post->featured_image = $imagePath;
                    }
                    $post->image_source = $imageCredit;
                }

                // Persistir image_alt si existe la columna en posts
                if (Schema::hasColumn('posts', 'image_alt')) {
                    $post->image_alt = $imageAlt;
                }

                $post->save();

                // Asignar taxonomía
                $taxonomyId = ($tipo === 'EFEMERIDE')
                    ? ($taxEfemerides?->id)
                    : ($taxNoticias?->id);

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
                $meta->source_subject = "[DV-1] {$title}";
                $meta->received_at = now();
                $meta->artist_name = $artist;
                $meta->event_date = $elem['fecha_acontecimiento'] ?? null;
                $meta->sources = json_encode($fuentes, JSON_UNESCAPED_SLASHES);
                $meta->image_credit = $imageCredit;
                if (Schema::hasColumn('email_post_sanitized_meta', 'image_alt')) {
                    $meta->image_alt = $imageAlt;
                }
                $meta->editorial_notes = $revisionRequerida
                    ? "[REVISION_REQUERIDA] " . ($motivoRevision ?: 'Pendiente de validación editorial')
                    : "[APROBADO_PARA_REVISION] Sin observaciones pendientes";
                $meta->security_status = $revisionRequerida ? 'needs_review' : 'sanitized';
                $meta->save();

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
                    'status'    => 'draft',
                    'published' => false,
                    'revision'  => $revisionRequerida,
                    'reason'    => $motivoRevision,
                ];
            } catch (\Throwable $e) {
                DB::rollBack();
                $failedElements[$elemId] = $e->getMessage();
                Log::error("EditorialBatchProcessor: Fallo al persistir elemento {$elemId}", [
                    'batch_id' => $batchId,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        $allSucceeded = count($failedElements) === 0;

        return [
            'success'            => $allSucceeded,
            'batch_id'           => $batchId,
            'total_declared'     => $totalDeclared,
            'total_extracted'    => $totalExtracted,
            'created'            => $createdCount,
            'updated'            => $updatedCount,
            'skipped'            => $skippedCount,
            'conflicts'          => $conflictCount,
            'processed_elements' => $processedElements,
            'failed_elements'    => $failedElements,
            'elements_detail'    => $elementsDetail,
            'error'              => $allSucceeded ? null : 'Uno o más elementos no pudieron ser persistidos.',
        ];
    }

    protected function failureResponse(string $batchId, string $errorMessage): array
    {
        return [
            'success'            => false,
            'batch_id'           => $batchId,
            'total_declared'     => 0,
            'total_extracted'    => 0,
            'created'            => 0,
            'updated'            => 0,
            'skipped'            => 0,
            'conflicts'          => 0,
            'processed_elements' => [],
            'failed_elements'    => [],
            'elements_detail'    => [],
            'error'              => $errorMessage,
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
}
