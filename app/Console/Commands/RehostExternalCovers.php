<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\NewRelease;
use App\Services\PostImageResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RehostExternalCovers extends Command
{
    protected $signature = 'covers:rehost-external {--dry-run} {--limit=50}';
    protected $description = 'Rehospeda portadas externas bloqueadas por hotlinking en Cloudflare R2.';

    public function handle(PostImageResolver $resolver)
    {
        $dryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $this->info("Iniciando revisión de portadas externas...");
        if ($dryRun) {
            $this->warn("MODO DRY-RUN ACTIVADO: No se guardarán cambios.");
        }

        $posts = Post::whereNotNull('featured_image')
            ->where('featured_image', 'NOT LIKE', 'https://media.sevenrockradio.com%')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $releases = NewRelease::whereNotNull('cover_image')
            ->where('cover_image', 'NOT LIKE', 'https://media.sevenrockradio.com%')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $totalChecked = 0;
        $totalOk = 0;
        $totalRehosted = 0;
        $totalFailed = 0;

        $items = $posts->concat($releases);

        foreach ($items as $item) {
            $type = $item instanceof Post ? 'Post' : 'Release';
            $imageUrl = $item instanceof Post ? $item->featured_image : $item->cover_image;
            
            if (!$imageUrl) continue;

            $totalChecked++;
            $domain = parse_url($imageUrl, PHP_URL_HOST);

            try {
                // Verificar si la imagen responde 200 CON el Referer del sitio
                $response = Http::withHeaders(['Referer' => 'https://sevenrockradio.com/'])
                    ->timeout(15)
                    ->get($imageUrl);

                if ($response->successful()) {
                    $this->line("[{$type} {$item->id}] OK (HTTP {$response->status()} con Referer) -> {$domain}");
                    $totalOk++;
                    continue;
                }

                $this->warn("[{$type} {$item->id}] FALLO (HTTP {$response->status()} con Referer) -> {$domain}");

                // Si falla (ej. 403), intentar rehospedar
                if ($dryRun) {
                    $this->info("  -> [DRY-RUN] Se intentaría descargar sin Referer y subir a R2.");
                    $totalRehosted++;
                    continue;
                }

                $this->info("  -> Intentando rehospedar...");
                
                // Usamos Reflection para llamar al método privado rehostExternalImage
                $reflection = new \ReflectionClass($resolver);
                $method = $reflection->getMethod('rehostExternalImage');
                $method->setAccessible(true);
                
                $rehostedUrl = $method->invoke($resolver, $imageUrl, 'command_rehost');

                if ($rehostedUrl) {
                    if ($item instanceof Post) {
                        $item->source_url = $imageUrl;
                        $item->featured_image = $rehostedUrl;
                    } else {
                        // En NewRelease podríamos no tener source_url tan directo, guardamos la imagen
                        $item->cover_image = $rehostedUrl;
                    }
                    
                    $item->save();
                    $this->info("  -> [ÉXITO] Rehospedada en: {$rehostedUrl}");
                    $totalRehosted++;
                } else {
                    $this->error("  -> [ERROR] Falló la descarga o subida de la imagen externa.");
                    $totalFailed++;
                }

            } catch (\Throwable $e) {
                $this->error("[{$type} {$item->id}] EXCEPCIÓN con {$domain}: " . $e->getMessage());
                $totalFailed++;
            }
        }

        $this->newLine();
        $this->info("Resumen Final:");
        $this->table(
            ['Revisadas', 'Correctas', 'Rehospedadas', 'Fallidas'],
            [[$totalChecked, $totalOk, $totalRehosted, $totalFailed]]
        );

        return Command::SUCCESS;
    }
}
