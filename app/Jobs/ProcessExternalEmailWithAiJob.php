<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\NewRelease;
use App\Models\Post;
use App\Models\PostTaxonomy;
use App\Models\ThemeSetting;
use App\Services\AiParserManager;
use App\Services\AiUsageTracker;
use App\Services\PostImageResolver;
use App\Support\EntityMatcher;
use App\Support\TextNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProcessExternalEmailWithAiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Reintentar hasta 3 veces (intento inicial + 2 reintentos con espera).
     */
    public int $tries = 3;

    /**
     * Tiempos de espera entre reintentos: 5 minutos y 15 minutos (en segundos).
     *
     * @var array<int, int>
     */
    public array $backoff = [300, 900];

    /**
     * Datos del correo a procesar.
     *
     * @var array
     */
    public array $emailData;

    public function __construct(array $emailData)
    {
        $this->emailData = $emailData;
    }

    public function handle(AiParserManager $parserManager, AiUsageTracker $usageTracker, PostImageResolver $imageResolver): void
    {
        $messageId = $this->emailData['message_id'] ?? 'unknown';
        $senderEmail = $this->emailData['sender'] ?? '';
        $subject = $this->emailData['subject'] ?? '';
        $body = $this->emailData['body'] ?? '';
        $tempMp3Path = $this->emailData['temp_mp3'] ?? null;
        $tempMp3Name = $this->emailData['temp_name'] ?? null;

        $settings = ThemeSetting::current();

        Log::info("ProcessExternalEmailWithAiJob: Procesando intento {$this->attempts()}/{$this->tries} para correo '{$subject}'", [
            'message_id' => $messageId,
            'sender' => $senderEmail,
        ]);

        // 1. Verificar si se ha alcanzado el tope diario de IA
        if ($usageTracker->isDailyLimitReached($settings)) {
            Log::warning("ProcessExternalEmailWithAiJob: Límite diario de IA alcanzado. Creando borrador determinista sin IA.");
            $this->applyDeterministicFallback($subject, $body, $senderEmail, $messageId, $settings, $imageResolver, "Tope diario de IA alcanzado");
            return;
        }

        // 2. Procesar a través de EditorialAiPipeline de dos etapas
        $pipeline = app(\App\Services\EditorialAiPipeline::class);
        $pipelineResult = $pipeline->process([
            'subject'    => $subject,
            'body'       => $body,
            'sender'     => $senderEmail,
            'message_id' => $messageId,
        ]);

        if ($pipelineResult['status'] === 'duplicate') {
            Log::info("ProcessExternalEmailWithAiJob: Duplicado detectado por pipeline.", ['subject' => $subject]);
            $this->markProcessed($messageId, $subject, 'duplicate');
            $this->cleanTempMp3($tempMp3Path);
            return;
        }

        if ($pipelineResult['status'] === 'discarded') {
            Log::info("ProcessExternalEmailWithAiJob: Descartado por pipeline.", ['subject' => $subject]);
            $this->markProcessed($messageId, $subject, 'discarded');
            $this->cleanTempMp3($tempMp3Path);
            return;
        }

        if ($pipelineResult['status'] === 'radio_program') {
            Log::info("ProcessExternalEmailWithAiJob: Desviado programa de radio.", ['subject' => $subject]);
            $this->markProcessed($messageId, $subject, 'radio_program');
            $this->cleanTempMp3($tempMp3Path);
            return;
        }

        if ($pipelineResult['status'] === 'fallback_draft' && ($pipelineResult['failure_type'] ?? '') === 'ai_failure') {
            $lastError = $pipelineResult['failure_reason'] ?? 'Fallo desconocido de IA';

            // Comprobar si el error es recuperable (429, 500, 503, timeouts)
            $isRecoverable = $parserManager->isRecoverableError($lastError);

            if ($isRecoverable && $this->attempts() < $this->tries) {
                Log::warning("ProcessExternalEmailWithAiJob: Error temporal de IA ({$lastError}). Se reintentará con backoff (intento {$this->attempts()}/{$this->tries}).");
                throw new \RuntimeException("Error temporal de IA recuperable: {$lastError}");
            }

            // Si es un error no recuperable o se agotaron los intentos: fallback determinista a draft
            Log::error("ProcessExternalEmailWithAiJob: Fallo definitivo o no recuperable ({$lastError}). Aplicando fallback determinista a draft.");
            $this->applyDeterministicFallback($subject, $body, $senderEmail, $messageId, $settings, $imageResolver, $lastError);
            return;
        }

        // 3. Pipeline estructuró exitosamente el borrador
        $draft = $pipelineResult['draft'] ?? [];
        $facts = $pipelineResult['stage1_facts'] ?? [];
        $cType = $pipelineResult['content_type'] ?? 'musical_news';

        $parsed = [
            'type'        => in_array($cType, ['new_release', 'release']) ? 'release' : 'post',
            'title'       => $draft['title'] ?? TextNormalizer::normalizeTitle($subject),
            'artist_name' => $facts['artist_name'] ?? null,
            'content'     => $draft['content'] ?? '',
            'excerpt'     => $draft['excerpt'] ?? '',
            'events'      => $facts['confirmed_tour_dates'] ?? [],
            'youtube_url' => $facts['verified_evidence']['youtube_url'] ?? null,
            'spotify_url' => $facts['verified_evidence']['spotify_url'] ?? null,
            'editorial_warning' => $draft['editorial_warning'] ?? null,
        ];

        $type = $parsed['type'];
        $title = $parsed['title'];
        $content = $parsed['content'];
        $excerpt = $parsed['excerpt'];
        $status = 'draft';


        if ($type === 'release') {
            $artistName = $parsed['artist_name'] ?? 'Artista Desconocido';
            $resolverInfo = $imageResolver->resolveForPost([
                'message' => null,
                'body' => $body,
                'subject' => $subject,
                'clean_title' => $title,
                'is_dark_vader' => false,
                'artist_name' => $artistName,
            ]);

            $release = NewRelease::create([
                'title'        => $title,
                'slug'         => Str::slug($title . '-' . $artistName),
                'artist_name'  => $artistName,
                'description'  => $content,
                'released_at'  => now(),
                'is_active'    => false, // En FASE 2: Todo NewRelease procesado por IA queda inactivo (borrador)
                'cover_image'  => $resolverInfo['url'] ?? null,
                'youtube_url'  => $parsed['youtube_url'] ?? null,
                'spotify_url'  => $parsed['spotify_url'] ?? null,
                'author_email' => null,
                'origin'       => 'email',
            ]);

            Log::info("ProcessExternalEmailWithAiJob: Lanzamiento creado vía IA (ID {$release->id}).");
        } else {
            // Post
            $resolverInfo = $imageResolver->resolveForPost([
                'message' => null,
                'body' => $body,
                'subject' => $subject,
                'clean_title' => $title,
                'is_dark_vader' => false,
                'artist_name' => null,
            ]);

            $categories = $parsed['categories'] ?? ['General'];
            $categories = array_values(array_filter($categories, fn($c) => !in_array($c, ['Noticias Rock', 'Hoy en el Rock'])));
            if (empty($categories)) {
                $categories = ['General'];
            }

            $baseSlug = Str::slug($title);
            $slug = $baseSlug;
            $suffix = 1;
            while (DB::table('posts')->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $suffix++;
            }

            $post = Post::create([
                'title'             => $title,
                'slug'              => $slug,
                'content'           => $content,
                'excerpt'           => $excerpt,
                'status'            => $status,
                'is_published'      => ($status === 'published'),
                'published_at'      => now(),
                'featured_image'    => $resolverInfo['url'] ?? null,
                'source_url'        => $resolverInfo['article_url'] ?? null,
                'source_name'       => $resolverInfo['credit'] ?? null,
                'image_source'      => $resolverInfo['source'] ?? null,
                'image_fetch_error' => $resolverInfo['error_reason'] ?? null,
                'categories'        => $categories,
                'author_email'      => $senderEmail,
                'source_subject'    => $subject,
            ]);

            $this->syncTaxonomies($post, $categories);

            // Validación de seguridad centralizada (FASE 3)
            $safetyValidator = app(\App\Services\EmailSafetyValidator::class);
            $safetyResult = $safetyValidator->validatePost($post);

            try {
                $attachmentsList = [];
                if (!empty($tempMp3Name)) {
                    $attachmentsList[] = $tempMp3Name;
                }
                $mediaExtractor = app(\App\Services\EmailMediaInventoryExtractor::class);
                $mediaInventory = $mediaExtractor->extract($body, !empty($tempMp3Name) ? $attachmentsList : null);

                \App\Models\EmailPostSanitizedMeta::create([
                    'post_id'           => $post->id,
                    'source_message_id' => $messageId,
                    'sender_email'      => $senderEmail,
                    'source_subject'    => $subject,
                    'received_at'       => now(),
                    'security_status'   => $safetyResult['is_safe'] ? 'passed' : 'needs_review',
                    'incident_reason'   => ! empty($safetyResult['violations']) ? implode(' | ', $safetyResult['violations']) : null,
                    'media_inventory'   => $mediaInventory,
                ]);
            } catch (\Throwable $e) {
                // Silencioso si la tabla aún no se ha migrado
            }

            Log::info("ProcessExternalEmailWithAiJob: Post creado vía IA (ID {$post->id}).");
        }

        $this->markProcessed($messageId, $subject, 'processed');
        $this->cleanTempMp3($tempMp3Path);
    }

    /**
     * Aplica el fallback determinista creando SIEMPRE un borrador (draft).
     */
    protected function applyDeterministicFallback(
        string $subject,
        string $body,
        string $senderEmail,
        string $messageId,
        ThemeSetting $settings,
        PostImageResolver $imageResolver,
        string $errorReason
    ): void {
        $subjectLower = mb_strtolower($subject);
        $cleanContent = $this->limpiarContenidoDeCorreo($body);

        $isRelease = str_contains($subjectLower, 'out now')
            || str_contains($subjectLower, 'new single')
            || str_contains($subjectLower, 'single')
            || str_contains($subjectLower, 'album')
            || str_contains($subjectLower, 'álbum')
            || str_contains($subjectLower, 'ep')
            || str_contains($subjectLower, 'premiere')
            || str_contains($subjectLower, 'lanzamiento');

        if ($isRelease) {
            $artistName = 'Artista';
            $titleName = TextNormalizer::normalizeTitle($subject) ?: $subject;
            if (preg_match('/^([^\'\":\-–]+)[\s\'\":\-–]+(.*)$/u', $subject, $m)) {
                $artistName = trim($m[1]);
                $titleName = trim($m[2], " '\"-–:");
            }

            $resolverInfo = $imageResolver->resolveForPost([
                'message' => null,
                'body' => $body,
                'subject' => $subject,
                'clean_title' => $titleName,
                'is_dark_vader' => false,
                'artist_name' => $artistName,
            ]);

            NewRelease::create([
                'title'        => $titleName ?: $subject,
                'slug'         => Str::slug(($titleName ?: $subject) . '-' . $artistName) . '-' . Str::random(4),
                'artist_name'  => $artistName ?: 'Artista',
                'description'  => $cleanContent,
                'released_at'  => now(),
                'is_active'    => false, // SIEMPRE borrador en fallback
                'cover_image'  => $resolverInfo['url'] ?? null,
                'author_email' => null,
                'origin'       => 'email',
            ]);
        } else {
            $title = TextNormalizer::normalizeTitle($subject) ?: $subject;
            $resolverInfo = $imageResolver->resolveForPost([
                'message' => null,
                'body' => $body,
                'subject' => $subject,
                'clean_title' => $title,
                'is_dark_vader' => false,
                'artist_name' => null,
            ]);

            $baseSlug = Str::slug($title);
            $slug = $baseSlug;
            $suffix = 1;
            while (DB::table('posts')->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $suffix++;
            }

            $post = Post::create([
                'title'             => $title,
                'slug'              => $slug,
                'content'           => $cleanContent,
                'excerpt'           => \App\Support\ExcerptCleaner::clean($cleanContent),
                'status'            => 'draft', // SIEMPRE borrador en fallback
                'is_published'      => false,
                'published_at'      => now(),
                'featured_image'    => $resolverInfo['url'] ?? null,
                'source_url'        => $resolverInfo['article_url'] ?? null,
                'source_name'       => $resolverInfo['credit'] ?? null,
                'image_source'      => $resolverInfo['source'] ?? null,
                'image_fetch_error' => $resolverInfo['error_reason'] ?? null,
                'categories'        => ['General'],
                'author_email'      => $senderEmail,
                'source_subject'    => $subject,
            ]);

            $this->syncTaxonomies($post, ['General']);
        }

        $this->markProcessed($messageId, $subject, 'processed_fallback', $errorReason);

        // Alerta al administrador una sola vez
        $this->sendAdminAlertOnce(
            'ai_fallback_job_' . date('Y-m-d'),
            '⚠️ Fallback determinista aplicado a correo externo',
            "El correo '{$subject}' de {$senderEmail} se procesó como BORRADOR mediante fallback determinista debido a: {$errorReason}",
            $settings
        );
    }

    protected function markProcessed(string $messageId, string $subject, string $status, ?string $error = null): void
    {
        try {
            DB::table('processed_emails')->updateOrInsert(
                ['message_id' => $messageId],
                [
                    'subject'    => $subject,
                    'status'     => $status,
                    'last_error' => $error ? Str::limit($error, 490) : null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::error("Error al registrar processed_emails en Job: " . $e->getMessage());
        }
    }

    protected function sendAdminAlertOnce(string $key, string $subject, string $message, ThemeSetting $settings): void
    {
        $cacheKey = "admin_alert_sent_{$key}";
        if (! Cache::has($cacheKey)) {
            try {
                $recipient = $settings->notification_email ?: 'prog.sevenrockradio@gmail.com';
                Mail::raw($message, function ($msg) use ($recipient, $subject) {
                    $msg->to($recipient)->subject($subject);
                });
                Cache::put($cacheKey, true, now()->addHours(24));
            } catch (\Throwable $e) {
                Log::error("No se pudo enviar alerta de admin en Job: " . $e->getMessage());
            }
        }
    }

    protected function cleanTempMp3(?string $path): void
    {
        if ($path && file_exists($path)) {
            @unlink($path);
        }
    }

    protected function limpiarContenidoDeCorreo(string $texto): string
    {
        $s = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $texto) ?? $texto;
        $s = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $s) ?? $s;
        $s = preg_replace('/<!--.*?-->/s', '', $s) ?? $s;
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = preg_replace('/(?:<p[^>]*>)?\s*(?:FUENTE|Source|Creditos|Créditos)\s*:.*?(?:<\/p>|$)/iu', '', $s) ?? $s;
        $s = preg_replace('/<p[^>]*>\s*<\/p>/i', '', $s) ?? $s;
        $s = preg_replace('/\n{3,}/', "\n\n", $s) ?? $s;
        $s = trim($s);

        if ($s !== '' && !preg_match('/<[a-zA-Z]/', $s)) {
            $parrafos = preg_split('/\n{2,}/', $s) ?: [$s];
            $wrapped = [];
            foreach ($parrafos as $p) {
                $p = trim($p);
                if ($p !== '') {
                    $wrapped[] = '<p>' . nl2br(htmlspecialchars($p, ENT_QUOTES | ENT_HTML5, 'UTF-8', false)) . '</p>';
                }
            }
            $s = implode("\n", $wrapped);
        }

        return $s;
    }

    protected function syncTaxonomies(Post $post, array $categories): void
    {
        if (! Schema::hasTable('post_taxonomies') || ! Schema::hasTable('post_taxonomy_post')) {
            return;
        }

        $ids = [];
        foreach ($categories as $cat) {
            if (trim($cat) !== '') {
                $taxonomy = PostTaxonomy::firstOrCreate(
                    ['slug' => Str::slug($cat), 'type' => PostTaxonomy::TYPE_CATEGORY],
                    ['name' => $cat]
                );
                $ids[] = $taxonomy->id;
            }
        }

        $post->taxonomies()->sync(array_values(array_unique($ids)));
    }
}
