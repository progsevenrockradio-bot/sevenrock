<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\PostImageResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class FixNewsImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:fix-images {--dry-run : Muestra qué posts serían modificados sin hacer cambios}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige las imágenes de noticias de los últimos 15 días (quita imágenes genéricas y hotlinks).';

    /**
     * Execute the console command.
     */
    public function handle(PostImageResolver $resolver)
    {
        $days = 15;
        $isDryRun = $this->option('dry-run');

        $this->info("Buscando posts problemáticos en los últimos {$days} días" . ($isDryRun ? ' (MODO DRY-RUN)' : ''));

        $posts = Post::query()
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->get();

        $problematicPosts = collect();

        foreach ($posts as $post) {
            $image = $post->featured_image;
            $needsFix = false;
            $reason = '';

            // Si no hay imagen, o es la imagen genérica del tema
            if (empty($image) || str_contains($image, 'theme/V8aldD')) {
                $needsFix = true;
                $reason = empty($image) ? 'Sin imagen' : 'Imagen genérica del tema';
            } 
            // Si es un hotlink que no es de media.sevenrockradio.com
            elseif (str_starts_with($image, 'http') && !str_starts_with($image, 'https://media.sevenrockradio.com')) {
                $needsFix = true;
                $reason = 'Hotlink detectado (' . parse_url($image, PHP_URL_HOST) . ')';
            }

            if ($needsFix) {
                $problematicPosts->push([
                    'post' => $post,
                    'reason' => $reason
                ]);
            }
        }

        $this->info('Se encontraron ' . $problematicPosts->count() . ' posts problemáticos.');

        foreach ($problematicPosts as $item) {
            /** @var Post $post */
            $post = $item['post'];
            $reason = $item['reason'];

            $this->warn("Post ID: {$post->id} | Título: {$post->title} | Razón: {$reason}");

            if (!$isDryRun) {
                if (!$post->source_url) {
                    $this->error("  -> No se puede arreglar: source_url está vacío.");
                    continue;
                }

                $this->info("  -> Intentando resolver desde: {$post->source_url}");
                $result = $resolver->retryPostImage($post);

                if ($result['success']) {
                    $this->info("  -> ÉXITO: " . $result['message'] . " (Nueva img: " . $post->featured_image . ")");
                } else {
                    $this->error("  -> ERROR: " . $result['message']);
                }
            }
        }

        if ($isDryRun) {
            $this->info("Prueba completada en modo dry-run. No se realizaron cambios.");
        } else {
            $this->info("Proceso de corrección completado.");
        }
    }
}
