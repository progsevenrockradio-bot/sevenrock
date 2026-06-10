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
            $cm = new ClientManager();
            $client = $cm->make([
                'host'          => $imapHost,
                'port'          => $imapPort,
                'encryption'    => $imapEncryption,
                'validate_cert' => config('services.imap.validate_cert', true),
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

                $this->info("Procesando correo: {$subject}");

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
                        try {
                            $uploaded = app(\App\Services\FileUploadService::class)->uploadRaw(
                                $content,
                                'catalog/releases/covers/' . Str::uuid()->toString() . '.' . $ext
                            );
                            $coverUrl = $uploaded['url'];
                            $this->info("Adjunto de imagen detectado y subido: {$coverUrl}");
                        } catch (\Throwable $e) {
                            Log::error("ProcessIncomingEmails: Fallo al subir portada adjunta: " . $e->getMessage());
                        }
                    }
                }

                // Obtener el cuerpo del correo
                $body = $message->getHTMLBody() ?: $message->getTextBody() ?: '';
                if (trim($body) === '') {
                    $this->warn("El cuerpo del correo está vacío. Saltando correo.");
                    continue;
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
                        ]);

                        $releasesCreatedToday++;
                        $this->info("Lanzamiento creado con éxito en estado " . ($isActive ? '[Activo]' : '[Borrador]') . ": {$title} - {$artistName}");

                        // Si hay MP3 adjunto, encolar subida a Archive.org
                        if ($tempMp3Path) {
                            \App\Jobs\UploadMp3ToArchiveOrg::dispatch(
                                $release->id,
                                $tempMp3Path,
                                $tempMp3Name
                            );
                            $this->info("Subida a Archive.org encolada en segundo plano para el MP3: {$tempMp3Name}");
                            $tempMp3Path = null; // Evitar que se borre en este ciclo
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
