<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanSpuriousPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:clean-spurious {--dry-run : Solo muestra los posts que se eliminarían sin borrarlos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina posts generados erróneamente con código HTML/CSS roto o mal clasificados en Noticias Rock.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Buscando posts con código CSS/HTML roto o mal clasificados...");

        $posts = Post::query()
            ->where(function ($q) {
                // Títulos conocidos de la captura o emails de sellos
                $q->where('title', 'like', '%GOOD STOCK RECORDS PRESENTS%')
                  ->orWhere('title', 'like', '%RISE ABOVE DEAD%')
                  ->orWhere('title', 'like', '%HOMECOMING %CAITLIN%%')
                  ->orWhere('title', 'like', '%NAIVE MEN LEADING THE BLIND%')
                  // Contenido con código CSS residual de Mailchimp o plantillas
                  ->orWhere('content', 'like', '%html,body,table%')
                  ->orWhere('content', 'like', '%mso-table-lspace%')
                  ->orWhere('content', 'like', '%-ms-interpolation-mode%')
                  ->orWhere('excerpt', 'like', '%html,body,table%')
                  ->orWhere('excerpt', 'like', '%mso-table-lspace%')
                  ->orWhere('excerpt', 'like', '%-ms-interpolation-mode%')
                  // Posts en Noticias Rock que no son de Dark Vader y no fueron creados por admin (author_email no nulo y distinto de dark vader)
                  ->orWhere(function ($sub) {
                      $sub->whereJsonContains('categories', 'Noticias Rock')
                          ->whereNotNull('author_email')
                          ->where('author_email', '!=', 'dark.vader.agent@gmail.com');
                  });
            })
            ->get();

        if ($posts->isEmpty()) {
            $this->info("No se encontraron posts espurios.");
            return self::SUCCESS;
        }

        $this->warn("Se encontraron " . $posts->count() . " post(s) espurio(s):");

        foreach ($posts as $post) {
            $this->line("- ID: {$post->id} | Título: {$post->title} | Email: " . ($post->author_email ?? 'null') . " | Fecha: {$post->created_at}");
            
            if (! $dryRun) {
                // Desvincular taxonomías
                if (method_exists($post, 'taxonomies')) {
                    $post->taxonomies()->detach();
                }
                $post->delete();
                $this->info("  -> Eliminado post ID {$post->id}");
            }
        }

        if ($dryRun) {
            $this->warn("Modo --dry-run activo. No se eliminó ningún registro.");
        } else {
            $this->info("¡Limpieza de posts espurios completada con éxito!");
        }

        return self::SUCCESS;
    }
}
