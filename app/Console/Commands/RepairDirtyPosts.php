<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\AiParserManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('posts:repair {--id= : El ID específico del post a reparar} {--recent= : Reparar los últimos N posts} {--all-dirty : Buscar automáticamente posts con contenido crudo o "basura" en inglés}')]
#[Description('Repara posts que fueron publicados crudos o con basura pasando su contenido nuevamente por la IA')]
class RepairDirtyPosts extends Command
{
    public function handle()
    {
        $id = $this->option('id');
        $recent = $this->option('recent');
        $allDirty = $this->option('all-dirty');

        $query = Post::query();

        if ($id) {
            $query->where('id', $id);
        } elseif ($recent) {
            $query->orderBy('id', 'desc')->take((int)$recent);
        } elseif ($allDirty) {
            // Busca posts que parezcan crudos (ej. "Dear Mediapartner", "START---")
            $query->where('content', 'LIKE', '%Dear Media%')
                  ->orWhere('content', 'LIKE', '%START-%')
                  ->orWhere('content', 'LIKE', '%Press Release%')
                  ->orWhere('content', 'LIKE', '%News submission%');
        } else {
            $this->error('Debes proporcionar un flag: --id=X, --recent=N o --all-dirty');
            return 1;
        }

        $posts = $query->get();

        if ($posts->isEmpty()) {
            $this->info('No se encontraron posts para reparar.');
            return 0;
        }

        $aiManager = app(AiParserManager::class);

        foreach ($posts as $post) {
            $this->info("Reparando Post ID {$post->id}: {$post->title}...");

            $subject = $post->source_subject ?: $post->title;
            
            // Extraer el texto de los bloques de contenido
            $body = '';
            $blocks = is_array($post->content) ? $post->content : [];
            foreach ($blocks as $block) {
                if (isset($block['value'])) {
                    $body .= strip_tags($block['value']) . "\n\n";
                } elseif (isset($block['data']['text'])) {
                    $body .= strip_tags($block['data']['text']) . "\n\n";
                }
            }
            $body = trim($body);

            $this->line(" Consultando a la IA...");
            $parsed = $aiManager->parse($subject, $body);

            if (!$parsed || !isset($parsed['type'])) {
                $this->error("  -> Falló la IA para este post.");
                if ($aiManager->lastError) {
                    $this->error("  -> Error: " . $aiManager->lastError);
                }
                continue;
            }

            if ($parsed['type'] === 'discard') {
                $this->warn("  -> La IA clasificó este post como 'discard' (spam/basura). Borrando o pasando a borrador...");
                $post->update(['status' => 'draft']);
                $this->line("  -> Pasado a borrador.");
                continue;
            }

            $newTitle = $parsed['title'] ?? $post->title;
            $newExcerpt = $parsed['excerpt'] ?? $post->excerpt;
            $newContent = $parsed['content'] ?? $post->content;

            // Mantener el slug si es posible, o crear uno nuevo si el título cambió drásticamente
            $post->update([
                'title'   => $newTitle,
                'excerpt' => $newExcerpt,
                'content' => $newContent,
            ]);

            $this->info("  [OK] Post reparado y actualizado exitosamente.");
        }

        $this->info("Proceso completado.");
        return 0;
    }
}
