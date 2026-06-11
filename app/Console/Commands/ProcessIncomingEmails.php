<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NewRelease;
use App\Models\Post;
use App\Models\ThemeSetting;
use App\Services\GeminiContentParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Webklex\PHPIMAP\ClientManager;

class ProcessIncomingEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa los correos de Gmail recibidos vía IMAP, extrae información con Gemini API y crea Posts o Nuevos Lanzamientos automáticamente.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $settings = ThemeSetting::current();

        if (! $settings->email_processing_enabled) {
            $this->info('El procesamiento automático de correos está deshabilitado en los Ajustes del Tema.');
            return 0;
        }

        $geminiKey = trim((string) $settings->gemini_api_key);
        if ($geminiKey === '') {
            $this->error('La API Key de Gemini no está configurada en los Ajustes del Tema.');
            return 1;
        }

        $imapHost = config('services.imap.host', 'imap.gmail.com');
        $imapPort = (int) config('services.imap.port', 993);
        $imapEncryption = config('services.imap.encryption', 'ssl');
        $imapUsername = config('services.imap.username') ?: $settings->notification_email;
        $imapPassword = config('services.imap.password');

        if (empty($imapPassword)) {
            $this->error('La contraseña de IMAP (IMAP_PASSWORD) no está configurada en el archivo .env.');
            return 1;
        }

        $this->info("Conectando a {$imapHost}:{$imapPort} para el usuario {$imapUsername}...");

        try {
            $cm = app(ClientManager::class);
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
            return 1;
        }

        try {
            $folder = $client->getFolder('INBOX');
            $messages = $folder->query()->unseen()->get();

            $this->info("Encontrados " . count($messages) . " correos no leídos.");

            // Contadores diarios para límites (máx 3 de cada tipo por día)
            $releasesCreatedToday = NewRelease::whereDate('created_at', today())->count();
            $postsCreatedToday = Post::whereDate('created_at', today())->count();

            foreach ($messages as $message) {
                $messageId = (string) $message->getMessageId();
                $subject = (string) $message->getSubject();

                // Evitar procesar correos duplicados
                if (DB::table('processed_emails')->where('message_id', $messageId)->exists()) {
                    $this->info("Ignorando correo ya procesado: {$subject}");
                    $message->setFlag('SEEN');
                    continue;
                }

                // Obtener remitente
                $fromAttribute = $message->getFrom();
                $senderAddress = $fromAttribute ? $fromAttribute->first() : null;
                $senderEmail = $senderAddress instanceof \Webklex\PHPIMAP\Address ? trim((string) $senderAddress->mail) : null;

                $isWhitelisted = false;
                if ($senderEmail && $settings->email_whitelist_senders) {
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

                $this->info("Procesando correo de " . ($senderEmail ?: 'Remitente Desconocido') . " (Lista blanca: " . ($isWhitelisted ? 'SI' : 'NO') . "): {$subject}");

                // Extraer adjuntos
                $tempMp3Path = null;
                $tempMp3Name = null;
                $coverUrl = null;

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
                    } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        $sizeInBytes = strlen((string) $content);
                        
                        // Omitir imágenes pequeñas menores a 40 KB (como firmas, logos o íconos)
                        if ($sizeInBytes < 40960) {
                            $this->info("Ignorando imagen pequeña (posible firma/logo): {$filename} ({$sizeInBytes} bytes)");
                            continue;
                        }

                        // Conservar la primera imagen grande detectada como portada y evitar sobrescribirla
                        if ($coverUrl === null) {
                            try {
                                $uploaded = app(\App\Services\FileUploadService::class)->uploadRaw(
                                    $content,
                                    'catalog/releases/covers/' . Str::uuid()->toString() . '.' . $ext
                                );
                                $coverUrl = $uploaded['url'];
                                $this->info("Adjunto de imagen principal detectado y subido: {$coverUrl}");
                            } catch (\Throwable $e) {
                                Log::error("ProcessIncomingEmails: Fallo al subir portada adjunta: " . $e->getMessage());
                            }
                        } else {
                            $this->info("Ignorando imagen extra: {$filename} (ya se asignó la portada principal)");
                        }
                    }
                }

                // Obtener el cuerpo del correo
                $body = $message->getHTMLBody() ?: $message->getTextBody() ?: '';
                if (trim($body) === '') {
                    $this->warn("El cuerpo del correo está vacío. Saltando correo.");
                    continue;
                }

                // Si no se encontró portada en los adjuntos, intentar extraerla del cuerpo HTML
                if ($coverUrl === null) {
                    $htmlBody = $message->getHTMLBody() ?: '';
                    if ($htmlBody !== '') {
                        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $htmlBody, $matches);
                        if (! empty($matches[1])) {
                            $this->info("Buscando imágenes en el cuerpo HTML del correo (" . count($matches[1]) . " encontradas)...");
                            foreach ($matches[1] as $imgUrl) {
                                $imgUrl = html_entity_decode($imgUrl, ENT_QUOTES | ENT_HTML5);
                                if (! filter_var($imgUrl, FILTER_VALIDATE_URL)) {
                                    continue;
                                }

                                $lowerUrl = strtolower($imgUrl);
                                if (str_contains($lowerUrl, 'facebook') ||
                                    str_contains($lowerUrl, 'twitter') ||
                                    str_contains($lowerUrl, 'instagram') ||
                                    str_contains($lowerUrl, 'youtube') ||
                                    str_contains($lowerUrl, 'linkedin') ||
                                    str_contains($lowerUrl, 'pinterest') ||
                                    str_contains($lowerUrl, 'tiktok') ||
                                    str_contains($lowerUrl, 'spotify') ||
                                    str_contains($lowerUrl, 'pixel') ||
                                    str_contains($lowerUrl, 'tracker') ||
                                    str_contains($lowerUrl, 'analytics') ||
                                    str_contains($lowerUrl, 'logo') ||
                                    str_contains($lowerUrl, 'icon') ||
                                    str_contains($lowerUrl, 'avatar') ||
                                    str_contains($lowerUrl, 'banner-mailchimp') ||
                                    preg_match('/\b(footer|social|share|icon|badge|button)\b/i', $lowerUrl)
                                ) {
                                    continue;
                                }

                                try {
                                    $this->info("Descargando imagen del cuerpo HTML: {$imgUrl}");
                                    $response = \Illuminate\Support\Facades\Http::timeout(5)->get($imgUrl);
                                    if ($response->successful()) {
                                        $imgContent = $response->body();
                                        $imgSize = strlen($imgContent);

                                        if ($imgSize >= 40960) {
                                            $ext = pathinfo(parse_url($imgUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
                                            $ext = in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? strtolower($ext) : 'jpg';

                                            $uploaded = app(\App\Services\FileUploadService::class)->uploadRaw(
                                                $imgContent,
                                                'catalog/releases/covers/' . Str::uuid()->toString() . '.' . $ext
                                            );
                                            $coverUrl = $uploaded['url'];
                                            $this->info("Imagen extraída del cuerpo HTML del correo y subida: {$coverUrl}");
                                            break;
                                        } else {
                                            $this->info("Imagen ignorada por tamaño menor a 40 KB: {$imgSize} bytes");
                                        }
                                    }
                                } catch (\Throwable $e) {
                                    Log::warning("ProcessIncomingEmails: No se pudo descargar la imagen del cuerpo HTML: {$imgUrl}. Error: " . $e->getMessage());
                                }
                            }
                        }
                    }
                }

                // Llamar a Gemini API
                $this->info("Consultando a Gemini API para redactar y clasificar...");
                $parser = app(GeminiContentParser::class);
                $parsed = $parser->parse($subject, $body, $geminiKey);

                if (! $parsed || ! isset($parsed['type'])) {
                    $this->error("Gemini no pudo clasificar o procesar este correo.");
                    if ($parser->lastError) {
                        $this->error("  -> Detalle del error: " . $parser->lastError);
                    }
                    DB::table('processed_emails')->insert([
                        'message_id' => $messageId,
                        'subject' => $subject,
                        'status' => 'failed',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if ($tempMp3Path && file_exists($tempMp3Path)) {
                        @unlink($tempMp3Path);
                    }
                    continue;
                }

                $type = $parsed['type'];
                $title = $parsed['title'] ?? 'Sin título';
                $importance = isset($parsed['importance']) ? (int) $parsed['importance'] : 1;

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
                    // Validar límite
                    if ($postsCreatedToday >= 3) {
                        $this->warn("Límite diario de posts alcanzado (3/3). El correo quedará pendiente para mañana.");
                        if ($tempMp3Path && file_exists($tempMp3Path)) {
                            @unlink($tempMp3Path);
                        }
                        continue; // No marcamos como leído (SEEN) para procesarlo otro día
                    }

                    // Evitar duplicados por título
                    if (Post::where('title', $title)->exists()) {
                        $this->info("Ignorando post duplicado con el título: {$title}");
                    } else {
                        // Crear Post
                        $status = $settings->email_auto_publish ? 'published' : 'draft';
                        Post::create([
                            'title' => $title,
                            'slug' => Str::slug($title),
                            'content' => $parsed['content'] ?? '',
                            'excerpt' => $parsed['excerpt'] ?? '',
                            'status' => $status,
                            'published_at' => now(),
                            'featured_image' => $coverUrl,
                            'facebook_url' => $parsed['facebook_url'] ?? null,
                            'youtube_url' => $parsed['youtube_url'] ?? null,
                            'instagram_url' => $parsed['instagram_url'] ?? null,
                            'twitter_url' => $parsed['twitter_url'] ?? null,
                            'author_email' => $senderEmail,
                        ]);
                        $postsCreatedToday++;
                        $this->info("Post creado con éxito en estado [{$status}]: {$title}");
                    }
                } elseif ($type === 'release') {
                    $artistName = $parsed['artist_name'] ?? 'Artista Desconocido';

                    // Validar límite
                    if ($releasesCreatedToday >= 3) {
                        $this->warn("Límite diario de lanzamientos alcanzado (3/3). El correo quedará pendiente.");
                        if ($tempMp3Path && file_exists($tempMp3Path)) {
                            @unlink($tempMp3Path);
                        }
                        continue;
                    }

                    // Evitar duplicados por título y artista
                    if (NewRelease::where('title', $title)->where('artist_name', $artistName)->exists()) {
                        $this->info("Ignorando lanzamiento duplicado: {$title} - {$artistName}");
                    } else {
                        // Crear Lanzamiento
                        $isActive = (bool) $settings->email_auto_publish;
                        $release = NewRelease::create([
                            'title' => $title,
                            'slug' => Str::slug($title . '-' . $artistName),
                            'artist_name' => $artistName,
                            'description' => $parsed['content'] ?? '',
                            'released_at' => now(),
                            'is_active' => $isActive,
                            'cover_image' => $coverUrl,
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
            return 1;
        }

        $this->info("Procesamiento de correos finalizado.");
        return 0;
    }
}
