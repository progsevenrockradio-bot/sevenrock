<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Post;
use App\Support\ExcerptCleaner;

class FixBlogExcerpts extends Command
{
    protected $signature = 'posts:fix-excerpts {--dry-run : Muestra los resúmenes sin actualizar}';
    protected $description = 'Limpia los campos excerpt JSON y los convierte a texto legible en todos los posts.';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info("Buscando posts con excerpts en formato JSON...");

        $posts = Post::where('excerpt', 'like', '\[%')->get();

        if ($posts->isEmpty()) {
            $this->info('No se encontraron posts con excerpt en JSON.');
            return;
        }

        $this->info("Se encontraron {$posts->count()} posts para corregir.");

        foreach ($posts as $post) {
            $cleaned = ExcerptCleaner::clean($post->excerpt);
            
            if ($dryRun) {
                $this->info("ID: {$post->id} | Antes: " . substr($post->excerpt, 0, 50) . "... -> Después: {$cleaned}");
            } else {
                $post->excerpt = $cleaned;
                $post->save();
                $this->info("Post ID {$post->id} actualizado.");
            }
        }

        $this->info("Proceso terminado.");
    }
}
