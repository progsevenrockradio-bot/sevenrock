<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NewRelease;
use App\Models\Post;
use App\Models\ThemeSetting;
use App\Services\GeminiContentParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\PostTaxonomy;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Webklex\PHPIMAP\ClientManager;

class ProcessIncomingEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:process {--reset : Vaciar el registro de correos procesados antes de iniciar} {--retry-failed : Reintentar los correos que fallaron en el procesamiento}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa los correos de Gmail recibidos vía IMAP, extrae información con Gemini API y crea Posts o Nuevos Lanzamientos automáticamente.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        try {
        if ($this->option('reset')) {
            DB::table('processed_emails')->truncate();
            $this->info('Registro de correos procesados vaciado con éxito.');
        }
        
        if ($this->option('retry-failed')) {
            DB::table('processed_emails')->where('status', 'failed')->delete();
            $this->info('Correos en estado "failed" marcados para reintento.');
        }

        $settings = ThemeSetting::current();

        if (! $settings->email_processing_enabled) {
            $this->info('El procesamiento automático de correos está deshabilitado en los Ajustes del Tema.');
            return 0;
        }

        $aiManager = app(\App\Services\AiParserManager::class);
        if (! $aiManager->hasAnyProvider()) {
            $this->error('No hay ningún proveedor de IA configurado en los Ajustes del Tema (Gemini u OpenRouter).');
            $this->sendAdminAlert(
                'ai_api_key_missing', 
                '⚠️ Error Crítico: Proveedor de IA faltante en SevenRockRadio', 
                'El cron de procesamiento de correos se ha detenido porque no hay ningún proveedor de IA configurado (Gemini u OpenRouter) en los Ajustes del Tema.', 
                $settings
            );
            throw new \Exception("AI provider missing");
        }

        Cache::forget('admin_alert_sent_ai_api_key_missing');

        $imapHost = config('services.imap.host', 'imap.gmail.com');
        $imapPort = (int) config('services.imap.port', 993);
        $imapEncryption = config('services.imap.encryption', 'ssl');
        $imapUsername = trim((string) $settings->imap_username) ?: config('services.imap.username') ?: $settings->notification_email;
        $imapPassword = trim((string) $settings->imap_password) ?: config('services.imap.password');

        if (empty($imapPassword)) {
            $this->error('La contraseña de IMAP no está configurada en los Ajustes del Tema (Contraseña de correo) ni en el archivo .env.');
            throw new \Exception("IMAP password missing");
        }

        $this->info("Conectando a {$imapHost}:{$imapPort} para el usuario {$imapUsername}...");

        try {
            $cm = app(\Webklex\PHPIMAP\ClientManager::class);
            $client = $cm->make([
                'host'          => $imapHost,
                'port'          => $imapPort,
                'encryption'    => $imapEncryption,
                'validate_cert' => config('services.imap.validate_cert', false),
                'username'      => $imapUsername,
                'password'      => $imapPassword,
                'protocol'      => 'imap'
            ]);

            $client->connect();
        } catch (\Throwable $e) {
            Log::error("ProcessIncomingEmails: Fallo de conexión IMAP: " . $e->getMessage());
            $this->error("Error de conexión IMAP: " . $e->getMessage());
            $this->sendAdminAlert(
                'imap_connection_failed',
                '⚠️ Error Crítico: Fallo de conexión IMAP en SevenRockRadio',
                "El cron no pudo conectarse al servidor de correo.\n\nError: " . $e->getMessage(),
                $settings
            );
            file_put_contents('scratch/test_output.txt', 'IMAP FAIL: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            throw new \Exception("IMAP Fail: " . $e->getMessage());
        }

        Cache::forget('admin_alert_sent_imap_connection_failed');

        try {
            $folder = $client->getFolder('INBOX');
            $messages = $folder->query()->unseen()->get();

            $this->info("Encontrados " . count($messages) . " correos no leídos.");

            // Contadores diarios para límites (máx 3 de cada tipo por día)
            // Solo cuenta posts que NO son de Dark Vader, para no interferir con sus publicaciones
            $releasesCreatedToday = NewRelease::whereDate('created_at', today())->count();
            $postsCreatedToday = Post::whereDate('created_at', today())
                ->where(function ($q) {
                    $q->where('author_email', '!=', 'dark.vader.agent@gmail.com')
                      ->orWhereNull('author_email');
                })
                ->count();

            Log::info("ProcessIncomingEmails: Contadores del día — Posts normales: {$postsCreatedToday}, Lanzamientos: {$releasesCreatedToday}.");

            foreach ($messages as $message) {
                $messageId = (string) $message->getMessageId();
                $subject = (string) $message->getSubject();
                
                // Sanitizar caracteres UTF-8 malformados que causan errores en base de datos y json_encode
                $subject = mb_convert_encoding($subject, 'UTF-8', 'UTF-8');
                $subject = iconv('UTF-8', 'UTF-8//IGNORE', $subject) ?: $subject;

                // Evitar procesar correos duplicados
                if (DB::table('processed_emails')->where('message_id', $messageId)->exists()) {
                    $this->info("Ignorando correo ya procesado: {$subject}");
                    $message->setFlag('SEEN');
                    continue;
                }

                // Obtener remitente
                $senderAddress = $message->getFrom()->first();
                $senderEmail = $senderAddress instanceof \Webklex\PHPIMAP\Address ? trim((string) $senderAddress->mail) : null;

                // Dark Vader es un agente de confianza: siempre pasa el filtro de relevancia sin depender de la whitelist manual
                $isDarkVaderAgent = strtolower((string) $senderEmail) === 'dark.vader.agent@gmail.com';

                $isWhitelisted = $isDarkVaderAgent; // Dark Vader siempre está en whitelist implícita
                file_put_contents('scratch/test_output.txt', "Sender: {$senderEmail}, Whitelist Setting: " . ($settings->email_whitelist_senders ?? 'NULL') . "\n", FILE_APPEND);
                if (! $isWhitelisted && $senderEmail && $settings->email_whitelist_senders) {
                    $whitelist = array_values(array_filter(array_map('trim', explode(',', $settings->email_whitelist_senders))));
                    foreach ($whitelist as $allowed) {
                        if ($allowed !== '') {
                            if (strcasecmp($senderEmail, $allowed) === 0 || str_ends_with(strtolower($senderEmail), strtolower($allowed))) {
                                $isWhitelisted = true;
                                break;
                            }
                        }
                    }
                }

                $whitelistReason = $isDarkVaderAgent ? 'Dark Vader Agent (implícito)' : ($isWhitelisted ? 'Lista blanca manual' : 'NO');
                $this->info("[EMAIL] Remitente: " . ($senderEmail ?: 'Desconocido') . " | Whitelist: {$whitelistReason} | Asunto: {$subject}");
                Log::info("ProcessIncomingEmails: Procesando correo.", [
                    'message_id' => $messageId,
                    'sender'     => $senderEmail,
                    'subject'    => $subject,
                    'whitelisted' => $isWhitelisted,
                    'is_dark_vader' => $isDarkVaderAgent,
                ]);

                // Extraer MP3 (la portada se maneja luego con PostImageResolver)
                $tempMp3Path = null;
                $tempMp3Name = null;

                foreach ($message->getAttachments() as $attachment) {
                    $filename = (string) $attachment->getName();
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    $content = $attachment->getContent();

                    if ($ext === 'mp3') {
                        $tempDir = storage_path('app/temp');
                        if (! file_exists($tempDir)) {
                            mkdir($tempDir, 0755, true);
                        }
                        $tempMp3Path = $tempDir . '/' . Str::uuid()->toString() . '.mp3';
                        file_put_contents($tempMp3Path, $content);
                        $tempMp3Name = $filename;
                        $this->info("Adjunto de audio detectado y guardado temporalmente: {$filename}");
                    }
                }

                // Obtener el cuerpo del correo
                $body = $message->getHTMLBody() ?: $message->getTextBody() ?: '';
                $body = mb_convert_encoding($body, 'UTF-8', 'UTF-8');
                $body = iconv('UTF-8', 'UTF-8//IGNORE', $body) ?: $body;

                if (trim($body) === '') {
                    $this->warn("El cuerpo del correo está vacío. Saltando correo.");
                    continue;
                }

                // Comprobar si es un correo especial
                $isDarkVader = $isDarkVaderAgent;
                $subjectLower = mb_strtolower($subject);
                $isEfemerides = str_starts_with($subjectLower, 'hoy en el rock') || 
                                str_contains($subjectLower, 'efeméride') || 
                                str_contains($subjectLower, 'efemerides');
                $isNoticiaRock = $isWhitelisted && !$isEfemerides;

                Log::info("ProcessIncomingEmails: Tipo de correo Dark Vader detectado.", [
                    'is_dark_vader'   => $isDarkVader,
                    'is_efemerides'   => $isEfemerides,
                    'is_noticia_rock' => $isNoticiaRock,
                    'subject'         => $subject,
                ]);

                $parser = app(GeminiContentParser::class);

                // ── PROCESAMIENTO DIRECTO PARA DARK VADER (sin Gemini, sin consumo de cuota) ─────

                // Efemérides: un post independiente por cada evento del día
                if ($isEfemerides) {
                    $this->info("[DARK VADER] Procesando Efemérides directamente (sin Gemini)...");
                    $plainBody = strip_tags($body);
                    $plainBody = html_entity_decode($plainBody, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                    // ── Estrategia 1: items numerados (1. texto, 2. texto...)  ──────────────
                    // Detectamos líneas que empiezan por número + punto/paréntesis
                    $lines = array_values(array_filter(
                        array_map('trim', preg_split('/\r?\n/', $plainBody) ?: [])
                    ));

                    // Agrupar líneas en "items": una nueva entrada cada vez que aparece
                    // una línea que empieza por \d+[.)]\s
                    $items = [];
                    $current = null;
                    foreach ($lines as $line) {
                        if (preg_match('/^\d+[.)]\s+/', $line)) {
                            if ($current !== null) {
                                $items[] = $current;
                            }
                            $current = $line;
                        } elseif ($current !== null && $line !== '') {
                            $current .= ' ' . $line; // línea de continuación del mismo item
                        }
                    }
                    if ($current !== null) {
                        $items[] = $current;
                    }

                    // ── Estrategia 2: fallback — separar por doble salto de línea ──────────
                    if (count($items) < 2) {
                        $chunks = preg_split('/\n{2,}|\r\n{2,}/', $plainBody) ?: [];
                        $items  = array_values(array_filter(array_map('trim', $chunks)));
                    }

                    if (empty($items)) {
                        $this->warn("Efemérides: cuerpo vacío tras parsear. Saltando.");
                        DB::table('processed_emails')->insert([
                            'message_id' => $messageId, 'subject' => $subject,
                            'status' => 'failed', 'created_at' => now(), 'updated_at' => now(),
                        ]);
                        if ($tempMp3Path && file_exists($tempMp3Path)) @unlink($tempMp3Path);
                        continue;
                    }

                    $this->info("Efemérides: " . count($items) . " evento(s) detectado(s).");
                    $status = $settings->email_auto_publish ? 'published' : 'draft';
                    $efemCreadas = 0;

                    foreach ($items as $item) {
                        // Quitar el prefijo numérico y el emoji para obtener el título real
                        // Ejemplo: "1. 🎸 1942 - Nace Ronnie James Dio..." → "1942 - Nace Ronnie James Dio..."
                        $cleanItem = preg_replace('/^\d+[.)]\s*/', '', $item);
                        // Quitar emojis del inicio (caracteres unicode en rango de emojis)
                        $cleanItem = preg_replace('/^[\x{1F300}-\x{1FFFF}\x{2600}-\x{27BF}]\s*/u', '', $cleanItem);
                        $cleanItem = trim($cleanItem);

                        if ($cleanItem === '' || strlen($cleanItem) < 8) continue;

                        // Título = todo el texto hasta el primer punto final (máx 120 chars)
                        // Esto evita títulos kilométricos
                        $efTitle = $cleanItem;
                        if (preg_match('/^(.{10,120}?[.!?])\s/u', $cleanItem, $m)) {
                            $efTitle = rtrim($m[1], '.!?');
                        } elseif (mb_strlen($cleanItem) > 100) {
                            // Cortar por longitud si no hay punto
                            $efTitle = Str::limit($cleanItem, 100, '');
                            // Cortar en la última palabra completa
                            $efTitle = preg_replace('/\s+\S+$/', '', $efTitle) ?: $efTitle;
                        }
                        $efTitle = trim($efTitle, ' .,;:—-');

                        if ($efTitle === '' || strlen($efTitle) < 5) continue;

                        // Contenido = el texto completo del item
                        if (Post::where('title', $efTitle)->exists()) {
                            $this->info("Ignorando efeméride duplicada: {$efTitle}");
                            continue;
                        }

                        $baseSlug = Str::slug($efTitle);
                        $slug = $baseSlug; $suffix = 1;
                        while (DB::table('posts')->where('slug', $slug)->exists()) {
                            $slug = $baseSlug . '-' . $suffix++;
                        }

                        $resolverInfo = app(\App\Services\PostImageResolver::class)->resolveForPost([
                            'message' => $message,
                            'body' => $body,
                            'subject' => $subject,
                            'clean_title' => $efTitle,
                            'is_dark_vader' => $isDarkVaderAgent,
                            'artist_name' => null
                        ]);

                        Post::create([
                            'title'          => $efTitle,
                            'slug'           => $slug,
                            'content'        => $cleanItem,
                            'excerpt'        => Str::limit($cleanItem, 160),
                            'status'         => $status,
                            'is_published'   => $settings->email_auto_publish,
                            'published_at'   => now(),
                            'featured_image' => $resolverInfo['url'],
                            'source_url'     => $resolverInfo['article_url'],
                            'source_name'    => $resolverInfo['credit'],
                            'image_source'   => $resolverInfo['source'],
                            'categories'     => ['Hoy en el Rock'],
                            'author_email'   => $senderEmail,
                        ]);
                        $this->syncTaxonomies(
                            Post::where('slug', $slug)->first(),
                            ['Hoy en el Rock'],
                            $this->extractHashtags($efTitle . ' ' . $cleanItem)
                        );
                        $this->info("[OK] Efeméride creada: {$efTitle}");
                        $efemCreadas++;
                    }

                    $this->info("Efemérides procesadas: {$efemCreadas} de " . count($items) . " evento(s).");
                    DB::table('processed_emails')->insert([
                        'message_id' => $messageId, 'subject' => $subject,
                        'status' => 'processed', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $message->setFlag('SEEN');
                    if ($tempMp3Path && file_exists($tempMp3Path)) @unlink($tempMp3Path);
                    continue;
                }

                // Noticias Rock: publicar directamente sin Gemini
                if ($isNoticiaRock) {
                    $this->info("[DARK VADER] Procesando Noticia Rock directamente (sin Gemini)...");

                    // Limpiar el asunto: quitar prefijos
                    $cleanTitle = TextNormalizer::normalizeTitle($subject);
                    $cleanTitle = $cleanTitle ?: $subject;

                    $status = $settings->email_auto_publish ? 'published' : 'draft';

                    $normalizedTitle = TextNormalizer::normalizeTitle($cleanTitle);
                    $normalizedSlug = TextNormalizer::normalizeSlug($cleanTitle);
                    $originalSubjectNormalized = TextNormalizer::normalizeSlug($subject);
                    $threshold = (float) ($settings->post_duplicate_similarity_threshold ?? 0.82);

                    $recentPosts = Post::where('created_at', '>=', now()->subHours(48))->get();
                    $isDuplicate = false;
                    $similarPostId = null;

                    foreach ($recentPosts as $recent) {
                        $recentSubjectNorm = TextNormalizer::normalizeSlug($recent->source_subject ?? '');
                        if ($recentSubjectNorm !== '' && $recentSubjectNorm === $originalSubjectNormalized) {
                            $isDuplicate = true;
                            $similarPostId = $recent->id;
                            break;
                        }

                        $recentNormSlug = TextNormalizer::normalizeSlug($recent->title);
                        if ($recentNormSlug === $normalizedSlug || TextNormalizer::similarity($recentNormSlug, $normalizedSlug) >= $threshold) {
                            $isDuplicate = true;
                            $similarPostId = $recent->id;
                            break;
                        }
                    }

                    if ($isDuplicate) {
                        $this->info("Ignorando Noticia Rock duplicada (similar a post ID: {$similarPostId})");
                        Log::info("ProcessIncomingEmails: Noticia Rock duplicada ignorada.", ['title' => $cleanTitle, 'subject' => $subject, 'similar_to_post_id' => $similarPostId]);
                    } else {
                        $baseSlug = Str::slug($cleanTitle);
                        $slug = $baseSlug; $suffix = 1;
                        while (DB::table('posts')->where('slug', $slug)->exists()) {
                            $slug = $baseSlug . '-' . $suffix++;
                        }

                        // Usar cuerpo HTML directamente como contenido
                        $contentToSave = $body ?: strip_tags($body);

                        $resolverInfo = app(\App\Services\PostImageResolver::class)->resolveForPost([
                            'message' => $message,
                            'body' => $body,
                            'subject' => $subject,
                            'clean_title' => $cleanTitle,
                            'is_dark_vader' => $isDarkVaderAgent,
                            'artist_name' => null
                        ]);

                        Log::info("ProcessIncomingEmails: Creando Noticia Rock (sin Gemini).", [
                            'title'  => $cleanTitle, 'slug' => $slug,
                            'status' => $status, 'cover_url' => $resolverInfo['url'],
                        ]);

                        $post = Post::create([
                            'title'          => $cleanTitle,
                            'slug'           => $slug,
                            'content'        => $contentToSave,
                            'excerpt'        => Str::limit(strip_tags($contentToSave), 160),
                            'status'         => $status,
                            'is_published'   => $settings->email_auto_publish,
                            'published_at'   => now(),
                            'featured_image' => $resolverInfo['url'],
                            'source_url'     => $resolverInfo['article_url'],
                            'source_name'    => $resolverInfo['credit'],
                            'image_source'   => $resolverInfo['source'],
                            'author_email'   => $senderEmail,
                            'categories'     => ['Noticias Rock'],
                            'source_subject' => $subject,
                        ]);
                        $this->syncTaxonomies($post, ['Noticias Rock'], $this->extractHashtags($cleanTitle . ' ' . strip_tags($contentToSave)));
                        $this->info("[OK] Noticia Rock creada en estado [{$status}]: ID {$post->id} — {$cleanTitle}");
                    }

                    DB::table('processed_emails')->insert([
                        'message_id' => $messageId, 'subject' => $subject,
                        'status' => 'processed', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $message->setFlag('SEEN');
                    if ($tempMp3Path && file_exists($tempMp3Path)) @unlink($tempMp3Path);
                    continue;
                }

                // Llamar a la IA para redactar y clasificar
                $this->info("Consultando a la IA para redactar y clasificar...");
                $parserManager = app(\App\Services\AiParserManager::class);
                $parsed = $parserManager->parse($subject, $body);

                if (! $parsed || ! isset($parsed['type'])) {
                    $this->error("La IA no pudo clasificar o procesar este correo.");
                    if ($parserManager->lastError) {
                        $this->error("  -> Detalle del error: " . $parserManager->lastError);
                    }
                    
                    $isFallbackEnabled = $settings->ai_fallback_enabled ?? true;
                    if ($isFallbackEnabled && !$isDarkVaderAgent) {
                        $this->warn("[FALLBACK] Aplicando fallback determinista para el correo: {$subject}");
                        $parsed = [
                            'type' => 'post',
                            'importance' => 3,
                            'title' => TextNormalizer::normalizeTitle($subject) ?: $subject,
                            'excerpt' => Str::limit(strip_tags($body), 160),
                            'content' => strip_tags($body),
                            'categories' => ['Noticias Rock'],
                            'fallback_used' => true
                        ];
                        
                        $this->sendAdminAlert(
                            'ai_deterministic_fallback', 
                            '⚠️ Fallback de IA activado', 
                            "Todos los proveedores de IA fallaron al procesar el correo '{$subject}'. Se ha creado un borrador usando el fallback determinista. Error: " . $parserManager->lastError, 
                            $settings
                        );
                    } else {
                        Log::error("ProcessIncomingEmails: Fallo de la IA.", [
                            'message_id' => $messageId,
                            'subject'    => $subject,
                            'sender'     => $senderEmail,
                            'error'      => $parserManager->lastError,
                        ]);
                        DB::table('processed_emails')->insert([
                            'message_id' => $messageId,
                            'subject' => $subject,
                            'status' => 'failed',
                            'attempts' => 1,
                            'last_error' => Str::limit($parserManager->lastError ?? '', 490),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        if ($tempMp3Path && file_exists($tempMp3Path)) {
                            @unlink($tempMp3Path);
                        }
                        continue;
                    }
                }

                $aiType = $parsed['type'];
                $type = $isNoticiaRock ? 'post' : $aiType;
                $title = $parsed['title'] ?? 'Sin título';
                $importance = isset($parsed['importance']) ? (int) $parsed['importance'] : 1;
                $isFallback = $parsed['fallback_used'] ?? false;

                Log::info("ProcessIncomingEmails: IA clasificó el correo.", [
                    'subject'       => $subject,
                    'ai_type'       => $aiType,
                    'effective_type' => $type,
                    'title'         => $title,
                    'importance'    => $importance,
                    'is_noticia_rock' => $isNoticiaRock,
                ]);
                $this->info("[IA] Tipo devuelto: {$aiType} | Tipo efectivo: {$type} | Importancia: {$importance} | Título: {$title}");

                // 1. Filtrar si es descarte/spam
                if ($type === 'discard') {
                    $this->info("Correo descartado por la IA (spam/publicidad/promo): {$subject}");
                    DB::table('processed_emails')->insert([
                        'message_id' => $messageId,
                        'subject' => $subject,
                        'status' => 'discarded',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if ($tempMp3Path && file_exists($tempMp3Path)) {
                        @unlink($tempMp3Path);
                    }
                    $message->setFlag('SEEN');
                    continue;
                }

                // 2. Filtrar por relevancia si no está en lista blanca
                $minImportance = (int) ($settings->email_min_importance ?? 1);
                if (! $isWhitelisted && $importance < $minImportance) {
                    $this->info("Correo omitido por baja relevancia (Relevancia: {$importance} < Mínima: {$minImportance}): {$subject}");
                    Log::warning("ProcessIncomingEmails: Correo descartado por baja relevancia.", [
                        'message_id'    => $messageId,
                        'subject'       => $subject,
                        'sender'        => $senderEmail,
                        'importance'    => $importance,
                        'min_importance' => $minImportance,
                    ]);
                    DB::table('processed_emails')->insert([
                        'message_id' => $messageId,
                        'subject' => $subject,
                        'status' => 'skipped',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if ($tempMp3Path && file_exists($tempMp3Path)) {
                        @unlink($tempMp3Path);
                    }
                    $message->setFlag('SEEN');
                    continue;
                }

                if ($type === 'post') {
                    // Validar límite (ignorarlo para Noticias Rock de Dark Vader)
                    $postsLimit = (int) ($settings->email_daily_posts_limit ?? 3);
                    if (! $isNoticiaRock && $postsCreatedToday >= $postsLimit) {
                        $this->warn("Límite diario de posts alcanzado ({$postsLimit}/{$postsLimit}). El correo quedará pendiente para mañana.");
                        if ($tempMp3Path && file_exists($tempMp3Path)) {
                            @unlink($tempMp3Path);
                        }
                        continue; // No marcamos como leído (SEEN) para procesarlo otro día
                    }

                    $normalizedTitle = TextNormalizer::normalizeTitle($title);
                    $normalizedSlug = TextNormalizer::normalizeSlug($title);
                    $originalSubjectNormalized = TextNormalizer::normalizeSlug($subject);
                    $threshold = (float) ($settings->post_duplicate_similarity_threshold ?? 0.82);

                    $recentPosts = Post::where('created_at', '>=', now()->subHours(48))->get();
                    $isDuplicate = false;
                    $similarPostId = null;

                    foreach ($recentPosts as $recent) {
                        $recentSubjectNorm = TextNormalizer::normalizeSlug($recent->source_subject ?? '');
                        if ($recentSubjectNorm !== '' && $recentSubjectNorm === $originalSubjectNormalized) {
                            $isDuplicate = true;
                            $similarPostId = $recent->id;
                            break;
                        }

                        $recentNormSlug = TextNormalizer::normalizeSlug($recent->title);
                        if ($recentNormSlug === $normalizedSlug || TextNormalizer::similarity($recentNormSlug, $normalizedSlug) >= $threshold) {
                            $isDuplicate = true;
                            $similarPostId = $recent->id;
                            break;
                        }
                    }

                    if ($isDuplicate) {
                        $this->info("Ignorando post duplicado por similitud (similar a post ID: {$similarPostId})");
                        Log::info("ProcessIncomingEmails: Post duplicado ignorado.", ['title' => $title, 'subject' => $subject, 'similar_to_post_id' => $similarPostId]);
                    } else {
                        // Crear Post
                        $status = ($settings->email_auto_publish && !$isFallback) ? 'published' : 'draft';

                        // Asignar categoría correcta
                        if ($isEfemerides) {
                            $categories = ['Hoy en el Rock'];
                        } elseif ($isNoticiaRock) {
                            $categories = ['Noticias Rock'];
                        } else {
                            $categories = $parsed['categories'] ?? [];
                            if (empty($categories)) {
                                $categories = ['Noticias Rock'];
                                Log::warning("ProcessIncomingEmails: Correo procesado como post sin categoría devuelta por la IA. Se asignó 'Noticias Rock' por defecto.", ['message_id' => $messageId]);
                            }
                        }

                        // Generar slug único con sufijo numérico si ya existe
                        $baseSlug = Str::slug($title);
                        $slug = $baseSlug;
                        $suffix = 1;
                        while (DB::table('posts')->where('slug', $slug)->exists()) {
                            $slug = $baseSlug . '-' . $suffix;
                            $suffix++;
                        }

                        $resolverInfo = app(\App\Services\PostImageResolver::class)->resolveForPost([
                            'message' => $message,
                            'body' => $body,
                            'subject' => $subject,
                            'clean_title' => $title,
                            'is_dark_vader' => $isDarkVaderAgent,
                            'artist_name' => $parsed['artist_name'] ?? null
                        ]);

                        if (empty($resolverInfo['url'])) {
                            $resolverInfo['url'] = $settings->email_default_cover_path;
                            Log::warning("ProcessIncomingEmails: Fallback de imagen aplicado para Post (PostImageResolver devolvió vacío).", ['message_id' => $messageId]);
                        }

                        Log::info("ProcessIncomingEmails: Creando post.", [
                            'title'       => $title,
                            'slug'        => $slug,
                            'status'      => $status,
                            'categories'  => $categories,
                            'cover_url'   => $resolverInfo['url'],
                            'is_published' => $settings->email_auto_publish,
                        ]);

                        $post = Post::create([
                            'title'        => $title,
                            'slug'         => $slug,
                            'content'      => $parsed['content'] ?? '',
                            'excerpt'      => $parsed['excerpt'] ?? '',
                            'status'       => $status,
                            'is_published' => $settings->email_auto_publish,
                            'published_at' => now(),
                            'featured_image' => $resolverInfo['url'],
                            'source_url'     => $resolverInfo['article_url'],
                            'source_name'    => $resolverInfo['credit'],
                            'image_source'   => $resolverInfo['source'],
                            'facebook_url'   => $parsed['facebook_url'] ?? null,
                            'youtube_url'    => $parsed['youtube_url'] ?? null,
                            'instagram_url'  => $parsed['instagram_url'] ?? null,
                            'twitter_url'    => $parsed['twitter_url'] ?? null,
                            'author_email'   => $senderEmail,
                            'categories'     => $categories,
                            'source_subject' => $subject,
                        ]);
                        $this->syncTaxonomies($post, $categories);
                        if (! $isNoticiaRock) $postsCreatedToday++;
                        $this->info("[OK] Post creado en estado [{$status}]: ID {$post->id} — {$title}");
                        Log::info("ProcessIncomingEmails: Post creado exitosamente.", [
                            'post_id'    => $post->id,
                            'title'      => $title,
                            'status'     => $status,
                            'categories' => $categories,
                        ]);
                    }
                } elseif ($type === 'release') {
                    $artistName = $parsed['artist_name'] ?? 'Artista Desconocido';

                    // Validar límite
                    $releasesLimit = (int) ($settings->email_daily_releases_limit ?? 3);
                    if ($releasesCreatedToday >= $releasesLimit) {
                        $this->warn("Límite diario de lanzamientos alcanzado ({$releasesLimit}/{$releasesLimit}). El correo quedará pendiente.");
                        if ($tempMp3Path && file_exists($tempMp3Path)) {
                            @unlink($tempMp3Path);
                        }
                        continue;
                    }

                    // Evitar duplicados por título y artista
                    if (NewRelease::where('title', $title)->where('artist_name', $artistName)->exists()) {
                        $this->info("Ignorando lanzamiento duplicado: {$title} - {$artistName}");
                    } else {
                        $resolverInfo = app(\App\Services\PostImageResolver::class)->resolveForPost([
                            'message' => $message,
                            'body' => $body,
                            'subject' => $subject,
                            'clean_title' => $title,
                            'is_dark_vader' => $isDarkVaderAgent,
                            'artist_name' => $artistName
                        ]);

                        if (empty($resolverInfo['url'])) {
                            $resolverInfo['url'] = $settings->email_default_cover_path;
                            Log::warning("ProcessIncomingEmails: Fallback de imagen aplicado para NewRelease (PostImageResolver devolvió vacío).", ['message_id' => $messageId]);
                        }

                        // Crear Lanzamiento
                        $isActive = (bool) $settings->email_auto_publish;
                        $release = NewRelease::create([
                            'title' => $title,
                            'slug' => Str::slug($title . '-' . $artistName),
                            'artist_name' => $artistName,
                            'description' => $parsed['content'] ?? '',
                            'released_at' => now(),
                            'is_active' => $isActive,
                            'cover_image' => $resolverInfo['url'],
                            // Not fully mapping source_url here unless added in migration, 
                            // but we use the resolved cover image.
                            'youtube_url' => $parsed['youtube_url'] ?? null,
                            'spotify_url' => $parsed['spotify_url'] ?? null,
                            'author_email' => $senderEmail,
                        ]);

                        $releasesCreatedToday++;
                        $this->info("Lanzamiento creado con éxito en estado " . ($isActive ? '[Activo]' : '[Borrador]') . ": {$title} - {$artistName}");

                        // Si hay MP3 adjunto, guardar localmente y encolar subidas a RadioBOSS y Archive.org
                        if ($tempMp3Path) {
                            try {
                                $fileContent = file_get_contents($tempMp3Path);
                                $cleanName = Str::slug(pathinfo($tempMp3Name, PATHINFO_FILENAME)) . '.mp3';
                                
                                // 1. Guardar permanentemente en local/B2 para el reproductor web
                                $uploadedAudio = app(\App\Services\FileUploadService::class)->uploadRaw(
                                    $fileContent,
                                    'catalog/releases/audios/' . Str::uuid()->toString() . '/' . $cleanName
                                );
                                
                                $release->update([
                                    'audio_path' => $uploadedAudio['url'],
                                ]);
                                
                                $this->info("Audio del lanzamiento guardado permanentemente en la web: {$uploadedAudio['url']}");
                                
                                // 2. Despachar cadena de trabajos en segundo plano: RadioBOSS FTP primero, luego Archive.org como respaldo (el cual limpia el archivo temporal al final)
                                \Illuminate\Support\Facades\Bus::chain([
                                    new \App\Jobs\UploadMp3ToRadiobossJob($release->id, $tempMp3Path, $tempMp3Name, 'RADIO/Lanzamientos'),
                                    new \App\Jobs\UploadMp3ToArchiveOrg($release->id, $tempMp3Path, $tempMp3Name)
                                ])->dispatch();
                                
                                $this->info("Subidas a RadioBOSS y Archive.org encoladas en cadena.");
                                $tempMp3Path = null; // Evitar que se borre en este ciclo
                            } catch (\Throwable $e) {
                                Log::error("ProcessIncomingEmails: Error al procesar audio adjunto: " . $e->getMessage());
                                $this->error("Error al procesar audio: " . $e->getMessage());
                            }
                        }
                    }
                }

                // Borrar archivos temporales remanentes si no se encoló la subida
                if ($tempMp3Path && file_exists($tempMp3Path)) {
                    @unlink($tempMp3Path);
                }

                // Registrar correo como procesado
                DB::table('processed_emails')->insert([
                    'message_id' => $messageId,
                    'subject' => $subject,
                    'status' => 'processed',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Marcar como leído en la bandeja
                $message->setFlag('SEEN');
            }

        } catch (\Throwable $e) {
            Log::error("ProcessIncomingEmails: Excepción general en el procesamiento de correos: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->error("Excepción: " . $e->getMessage());
            throw new \Exception("Excepción general: " . $e->getMessage(), 0, $e);
        }

        $this->info("Proceso de revisión de correos completado.");
        return self::SUCCESS;
        } catch (\Throwable $e) {
            file_put_contents('scratch/test_output.txt', "ERROR IN HANDLE: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Envía una notificación por correo al administrador asegurando que no se sature (rate limit de 24h).
     */
    protected function sendAdminAlert(string $errorKey, string $subject, string $message, ThemeSetting $settings): void
    {
        $cacheKey = "admin_alert_sent_{$errorKey}";
        if (!Cache::has($cacheKey)) {
            try {
                $recipient = $settings->notification_email ?: config('mail.from.address');
                if ($recipient) {
                    Mail::raw($message, function($msg) use ($recipient, $subject) {
                        $msg->to($recipient)->subject($subject);
                    });
                    Cache::put($cacheKey, true, now()->addHours(24));
                    $this->info("Alerta de administrador enviada a {$recipient}.");
                }
            } catch (\Throwable $e) {
                Log::error("No se pudo enviar la alerta de administrador: " . $e->getMessage());
            }
        }
    }

    private function syncTaxonomies(Post $post, array $categories, array $tags = []): void
    {
        if (! Schema::hasTable('post_taxonomies') || ! Schema::hasTable('post_taxonomy_post')) {
            return;
        }

        $ids = [];
        foreach ($categories as $category) {
            if (trim($category) !== '') {
                $ids[] = $this->ensureTaxonomy(PostTaxonomy::TYPE_CATEGORY, $category)->id;
            }
        }
        foreach ($tags as $tag) {
            if (trim($tag) !== '') {
                $ids[] = $this->ensureTaxonomy(PostTaxonomy::TYPE_TAG, ltrim($tag, '#'))->id;
            }
        }

        $post->taxonomies()->sync(array_values(array_unique($ids)));
    }

    /**
     * Extrae hashtags relevantes del texto de una noticia o efeméride.
     * Sin llamadas a APIs externas — análisis de patrones del propio texto.
     *
     * @return array<int, string>  Máx. 4 hashtags sin el símbolo #
     */
    private function extractHashtags(string $text): array
    {
        $tags = [];

        // 1. Año histórico  → RockNNNN
        if (preg_match('/\b(1[89]\d{2}|20\d{2})\b/', $text, $m)) {
            $tags[] = 'Rock' . $m[1];
        }

        // 2. Tipo de evento
        $lower = mb_strtolower($text);
        if (mb_strpos($lower, 'nace') !== false || mb_strpos($lower, 'cumpleaños') !== false) {
            $tags[] = 'NaceHoy';
        } elseif (mb_strpos($lower, 'fallece') !== false || mb_strpos($lower, 'muere') !== false) {
            $tags[] = 'RIPRock';
        } elseif (mb_strpos($lower, 'lanza') !== false || mb_strpos($lower, 'álbum') !== false || mb_strpos($lower, 'album') !== false) {
            $tags[] = 'NuevoAlbum';
        } elseif (mb_strpos($lower, 'anuncia') !== false) {
            $tags[] = 'NoticiaRock';
        } elseif (mb_strpos($lower, 'gira') !== false || mb_strpos($lower, 'tour') !== false) {
            $tags[] = 'RockTour';
        }

        // 3. Bandas/artistas entre paréntesis  → "(Rainbow, Black Sabbath, Dio)"
        if (preg_match('/\(([^)]{3,60})\)/', $text, $pm)) {
            $names = array_slice(array_map('trim', explode(',', $pm[1])), 0, 2);
            foreach ($names as $name) {
                if (strlen($name) >= 2 && strlen($name) <= 30) {
                    // CamelCase: "Black Sabbath" → "BlackSabbath"
                    $tag = preg_replace('/\s+/', '', ucwords(mb_strtolower($name)));
                    if ($tag && !in_array($tag, $tags, true)) {
                        $tags[] = $tag;
                    }
                }
            }
        }

        // 4. Género musical común detectado en el texto
        $genres = [
            'heavy metal' => 'HeavyMetal', 'metal' => 'Metal',
            'hard rock'   => 'HardRock',   'punk'  => 'PunkRock',
            'prog rock'   => 'ProgRock',   'blues' => 'Blues',
            'jazz'        => 'Jazz',        'grunge' => 'Grunge',
        ];
        foreach ($genres as $keyword => $tagName) {
            if (mb_strpos($lower, $keyword) !== false && !in_array($tagName, $tags, true)) {
                $tags[] = $tagName;
                break; // un género por post
            }
        }

        return array_slice(array_unique($tags), 0, 4);
    }

    private function ensureTaxonomy(string $type, string $name): PostTaxonomy
    {
        $name = trim($name);
        return PostTaxonomy::query()->firstOrCreate(
            [
                'type' => $type,
                'slug' => Str::slug($name),
            ],
            [
                'name' => $name,
            ]
        );
    }
}
