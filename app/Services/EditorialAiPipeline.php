<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiCallAudit;
use App\Models\Post;
use App\Models\ThemeSetting;
use App\Support\AiPrompts;
use App\Support\EmbargoDetector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EditorialAiPipeline
{
    protected EditorialCorrectionClassifier $correctionClassifier;
    protected EditorialCorrelator $correlator;
    protected EditorialIncidentManager $incidentManager;

    public function __construct(
        protected AiParserManager $aiManager,
        protected EmailContentSanitizer $sanitizer,
        protected LinkSecurityClassifier $linkClassifier,
        protected ReleaseScheduleManager $scheduleManager,
        protected FactConsistencyChecker $consistencyChecker,
        protected EmailSafetyValidator $safetyValidator,
        ?EditorialCorrectionClassifier $correctionClassifier = null,
        ?EditorialCorrelator $correlator = null,
        ?EditorialIncidentManager $incidentManager = null
    ) {
        $this->correctionClassifier = $correctionClassifier ?: app(EditorialCorrectionClassifier::class);
        $this->correlator = $correlator ?: app(EditorialCorrelator::class);
        $this->incidentManager = $incidentManager ?: app(EditorialIncidentManager::class);
    }

    /**
     * Procesa un correo entrante a través del pipeline editorial inteligente de dos etapas.
     *
     * @param array $emailData Contiene: 'subject', 'body', 'sender', 'message_id', 'account'
     * @return array Resultado del pipeline con estado, clasificación, borrador estructurado y telemetría
     */
    public function process(array $emailData): array
    {
        $subject = (string) ($emailData['subject'] ?? '');
        $rawBody = (string) ($emailData['body'] ?? '');
        $sender = (string) ($emailData['sender'] ?? '');
        $messageId = (string) ($emailData['message_id'] ?? Str::uuid()->toString());

        // ── 1. PRE-PROCESAMIENTO Y SANEAMIENTO DE SEGURIDAD (FASE 3) ──────────────
        $sanitizedResult = $this->sanitizer->sanitize($rawBody);
        $cleanBody = $sanitizedResult['sanitized_content'] ?? strip_tags($rawBody);
        $cleanText = strip_tags($cleanBody);

        // Limpiar tokens y credenciales sensibles para no enviarlos a la IA
        $cleanTextForAi = preg_replace('/\[PROTECTED_CREDENTIAL\]/i', '[DATO CONFIDENCIAL PROTEGIDO]', $cleanText);

        // Hashing para auditoría y deduplicación
        $normalizedSubject = mb_strtolower(trim(preg_replace('/\s+/', ' ', $subject)));
        $normalizedText = mb_strtolower(trim(preg_replace('/\s+/', ' ', $cleanText)));
        $contentHash = hash('sha256', $normalizedSubject . '|' . $normalizedText);

        // ── 2. CLASIFICACIÓN DE CORRECCIONES, RETIRADAS Y EMBARGOS (FASE 5) ────────
        $correctionClass = $this->correctionClassifier->classify($subject, $cleanText);
        $isRectification = $correctionClass['is_correction'] || $this->scheduleManager->isRectification($subject, $cleanText);
        $embargoInfo = $this->scheduleManager->resolveSchedule($cleanText, now());
        $isEmbargoed = $embargoInfo['is_under_embargo'] || EmbargoDetector::isEmbargoed($cleanText) || EmbargoDetector::isEmbargoed($subject);

        $incidentResult = null;
        if ($isRectification) {
            $correlation = $this->correlator->correlate(array_merge($emailData, [
                'body' => $cleanText,
            ]));
            $incidentResult = $this->incidentManager->handleIncident($correctionClass, $correlation, $emailData);
        }

        // ── 3. DEDUPLICACIÓN CON DETECCIÓN PREVIA DE RECTIFICACIONES ───────────────
        if (!$isRectification) {
            $duplicateCheck = $this->checkDuplicates($contentHash, $normalizedSubject, $normalizedText);
            if ($duplicateCheck['is_duplicate']) {
                Log::info("EditorialAiPipeline: Correo duplicado detectado. Omitiendo llamadas de IA redundantes.", [
                    'message_id' => $messageId,
                    'hash'       => $contentHash,
                    'reason'     => $duplicateCheck['reason'],
                ]);

                // Registrar en auditoría como reutilizado de caché (0 tokens, 0 coste)
                $this->recordCacheAudit($messageId, $contentHash, 'classification_extraction');

                return [
                    'status'        => 'duplicate',
                    'action'        => 'discard_duplicate',
                    'reason'        => $duplicateCheck['reason'],
                    'matched_post_id' => $duplicateCheck['matched_post_id'],
                    'content_hash'  => $contentHash,
                ];
            }
        }


        // ── 4. VERIFICAR LÍMITES DE CONSUMO DE IA ─────────────────────────────────
        $usageTracker = app(AiUsageTracker::class);
        $settings = ThemeSetting::current();
        $maxCalls = (int) ($settings->ai_daily_max_calls ?? 50);

        if ($maxCalls > 0 && $usageTracker->getCallsToday() >= $maxCalls) {
            Log::warning("EditorialAiPipeline: Límite diario de IA alcanzado ({$maxCalls}). Generando borrador seguro.");
            return $this->buildFallbackResult($subject, $cleanBody, $sender, $messageId, $contentHash, "Límite diario de IA alcanzado ({$maxCalls})", 'limit_reached');
        }

        // ── 5. ETAPA 1: CLASIFICACIÓN Y EXTRACCIÓN ESTRUCTURADA ────────────────────
        $stage1Prompt = AiPrompts::getClassificationExtractionPrompt($subject, $cleanTextForAi);
        $stage1Schema = AiPrompts::getClassificationExtractionSchema();

        $stage1Result = $this->aiManager->generateStructured(
            $stage1Prompt,
            $stage1Schema,
            'classification_extraction',
            $messageId,
            $contentHash
        );

        if (!$stage1Result || !isset($stage1Result['content_type'])) {
            $err = $this->aiManager->lastError ?? 'Fallo en Etapa 1 de IA';
            Log::warning("EditorialAiPipeline: Fallo en Etapa 1. Aplicando fallback seguro.", ['error' => $err]);
            return $this->buildFallbackResult($subject, $cleanBody, $sender, $messageId, $contentHash, $err, 'ai_failure');
        }

        $contentType = $stage1Result['content_type'];
        $importance = (int) ($stage1Result['editorial_importance'] ?? 1);
        $facts = $stage1Result['facts'] ?? [];

        // 5.1 Descarte inmediato para mensajes administrativos o comerciales ajenos
        if ($contentType === 'administrative_commercial') {
            Log::info("EditorialAiPipeline: Mensaje clasificado como administrativo/comercial. Descartado sin llamada de redacción.", [
                'message_id' => $messageId,
                'subject'    => $subject,
            ]);
            return [
                'status'        => 'discarded',
                'action'        => 'discard',
                'content_type'  => $contentType,
                'reason'        => 'Mensaje administrativo, factura o comercial ajeno a la radio',
                'stage1_data'   => $stage1Result,
                'content_hash'  => $contentHash,
            ];
        }

        // 5.2 Desvío para entregas de programas de radio y podcasts
        if ($contentType === 'radio_program_podcast') {
            Log::info("EditorialAiPipeline: Mensaje clasificado como programa de radio / podcast. Desviado fuera de noticias.", [
                'message_id' => $messageId,
                'subject'    => $subject,
            ]);
            return [
                'status'        => 'radio_program',
                'action'        => 'route_to_audio_catalog',
                'content_type'  => $contentType,
                'stage1_data'   => $stage1Result,
                'content_hash'  => $contentHash,
            ];
        }

        // ── 6. ETAPA 2: REDACCIÓN EDITORIAL (VOZ DE LA CASA) ──────────────────────
        $stage2Prompt = AiPrompts::getEditorialWritingPrompt($subject, $stage1Result);
        $stage2Schema = AiPrompts::getEditorialWritingSchema();

        $stage2Result = $this->aiManager->generateStructured(
            $stage2Prompt,
            $stage2Schema,
            'editorial_writing',
            $messageId,
            $contentHash
        );

        if (!$stage2Result || empty($stage2Result['content'])) {
            Log::warning("EditorialAiPipeline: Falló la Etapa 2 de redacción. Utilizando hechos de Etapa 1 para borrador seguro.");
            $stage2Result = [
                'title'             => $facts['release_title'] ?? $subject,
                'excerpt'           => Str::limit($cleanText, 160),
                'content'           => $cleanBody,
                'editorial_warning' => 'La redacción automática de Etapa 2 no estuvo disponible. Se conservan hechos de Etapa 1.',
            ];
        }

        // ── 7. VERIFICACIÓN DE CONSISTENCIA Y ANTI-ALUCINACIÓN ─────────────────────
        $consistencyCheck = $this->consistencyChecker->check($stage1Result, $stage2Result);
        if (!$consistencyCheck['is_consistent']) {
            $stage2Result['editorial_warning'] = $consistencyCheck['editorial_warning'];
            Log::warning("EditorialAiPipeline: Inconsistencia o posible alucinación detectada.", [
                'discrepancies' => $consistencyCheck['discrepancies'],
            ]);
        }

        // ── 8. VALIDACIÓN FINAL DE SEGURIDAD (FASE 3) ──────────────────────────────
        $finalDraftContent = $stage2Result['content'];
        $safetyReport = $this->safetyValidator->validateEditorialSafety([
            'title'   => $stage2Result['title'] ?? $subject,
            'excerpt' => $stage2Result['excerpt'] ?? '',
            'content' => $finalDraftContent,
        ]);

        $editorialWarning = $stage2Result['editorial_warning'] ?? null;
        if (!empty($safetyReport['warnings'])) {
            $editorialWarning = ($editorialWarning ? $editorialWarning . ' // ' : '') . implode(' | ', $safetyReport['warnings']);
        }

        if ($incidentResult && !empty($incidentResult['notes'])) {
            $editorialWarning = ($editorialWarning ? $editorialWarning . ' // ' : '') . "[INCIDENCIA EDITORIAL]: " . $incidentResult['notes'];
        }

        // ── 9. PREPARACIÓN FINAL: BORRADOR ESTRICTAMENTE PROTEGIDO ────────────────
        return [
            'status'            => 'draft_ready',
            'action'            => 'create_draft',
            'content_type'      => $contentType,
            'importance'        => $importance,
            'is_rectification'  => $isRectification,
            'incident'          => $incidentResult,
            'is_under_embargo'  => $isEmbargoed,
            'embargo_until'     => $embargoInfo['embargo_until'] ?? null,
            'publish_permitted_at' => $embargoInfo['publish_permitted_at'] ?? now(),
            'musical_release_date' => $facts['musical_release_date'] ?? null,
            'content_hash'      => $contentHash,
            'draft'             => [
                'title'             => $stage2Result['title'] ?? $subject,
                'excerpt'           => $stage2Result['excerpt'] ?? Str::limit($cleanText, 160),
                'content'           => $finalDraftContent,
                'artist_name'       => $facts['artist_name'] ?? null,
                'release_title'     => $facts['release_title'] ?? null,
                'status'            => 'draft', // SIEMPRE BORRADOR EN FASE 4 Y 5
                'is_published'      => false,  // MODO B ESTRICTAMENTE DESACTIVADO
                'origin'            => 'email',
                'editorial_warning' => $editorialWarning,
                'safety_report'     => $safetyReport,
            ],
            'stage1_facts'      => $facts,
            'consistency'       => $consistencyCheck,
        ];

    }

    /**
     * Comprueba duplicados por hash exacto o similitud textual alta en publicaciones recientes.
     */
    protected function checkDuplicates(string $contentHash, string $normalizedSubject, string $normalizedText): array
    {
        // 1. Verificación por hash exacto en tabla de auditoría o posts recientes (últimos 30 días)
        if (\Illuminate\Support\Facades\Schema::hasTable('ai_call_audits')) {
            $existingAudit = AiCallAudit::where('content_hash', $contentHash)
                ->where('status', 'success')
                ->where('created_at', '>=', now()->subDays(30))
                ->first();

            if ($existingAudit) {
                return [
                    'is_duplicate'    => true,
                    'reason'          => 'Hash exacto de contenido ya procesado en los últimos 30 días',
                    'matched_post_id' => null,
                ];
            }
        }

        // 2. Verificación contra posts creados recientemente
        $recentPosts = Post::where('created_at', '>=', now()->subDays(30))
            ->select('id', 'title', 'source_subject')
            ->get();

        foreach ($recentPosts as $post) {
            $postSubject = mb_strtolower(trim((string) ($post->source_subject ?: $post->title)));
            if ($postSubject === $normalizedSubject && $normalizedSubject !== '') {
                return [
                    'is_duplicate'    => true,
                    'reason'          => "Asunto idéntico a publicación previa ID {$post->id}",
                    'matched_post_id' => $post->id,
                ];
            }

            // Similitud textual alta (> 85%) en el titular/asunto
            similar_text($postSubject, $normalizedSubject, $percent);
            if ($percent > 85.0) {
                return [
                    'is_duplicate'    => true,
                    'reason'          => "Similitud del " . round($percent, 1) . "% con publicación previa ID {$post->id}",
                    'matched_post_id' => $post->id,
                ];
            }
        }

        return [
            'is_duplicate'    => false,
            'reason'          => null,
            'matched_post_id' => null,
        ];
    }

    /**
     * Construye un resultado de fallback seguro sin HTML crudo.
     */
    protected function buildFallbackResult(
        string $subject,
        string $cleanBody,
        string $sender,
        string $messageId,
        string $contentHash,
        string $reason,
        string $failureType
    ): array {
        $cleanText = strip_tags($cleanBody);

        return [
            'status'            => 'fallback_draft',
            'action'            => 'create_draft',
            'content_type'      => 'insufficient_ambiguous',
            'importance'        => 1,
            'is_rectification'  => false,
            'is_under_embargo'  => false,
            'content_hash'      => $contentHash,
            'draft'             => [
                'title'             => $subject ?: 'Comunicado pendiente de revisión',
                'excerpt'           => Str::limit($cleanText, 160),
                'content'           => $cleanBody,
                'status'            => 'draft',
                'is_published'      => false,
                'origin'            => 'email',
                'editorial_warning' => "[AVISO EDITORIAL - FALLBACK]: {$reason}. Requiere revisión manual antes de publicar.",
            ],
            'failure_reason'    => $reason,
            'failure_type'      => $failureType,
        ];
    }

    /**
     * Registra un evento de auditoría para acierto de caché / duplicado omitido.
     */
    protected function recordCacheAudit(string $messageId, string $contentHash, string $stage): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('ai_call_audits')) {
                AiCallAudit::create([
                    'message_id'        => $messageId,
                    'content_hash'      => $contentHash,
                    'provider'          => 'cache',
                    'model'             => 'duplicate_filter',
                    'stage'             => $stage,
                    'prompt_tokens'     => 0,
                    'completion_tokens' => 0,
                    'total_tokens'      => 0,
                    'usage_source'      => 'fallback',
                    'estimated_cost_eur'=> 0.000000,
                    'duration_ms'       => 0,
                    'status'            => 'cached',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("EditorialAiPipeline: Error al registrar auditoría de caché: " . $e->getMessage());
        }
    }
}
