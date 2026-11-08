<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Post;
use App\Support\WordPressContent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class RepairEditorialBodyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'post:repair-editorial-body 
                            {id : ID del artículo a inspeccionar o reparar}
                            {--apply : Aplica la reparación en la base de datos local (por defecto solo previsualiza)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inspecciona y repara el cuerpo de un post contaminado por HTML crudo de correo (DOCTYPE, tablas, metadatos), con respaldo previo y vista previa segura.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $id = (int) $this->argument('id');
        $post = Post::find($id);

        if (! $post) {
            $this->error("No se encontró el artículo con ID {$id}.");
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');

        $this->info("=== REPARADOR EDITORIAL DE ARTÍCULOS ===");
        $this->line("ID: {$post->id}");
        $this->line("Título: {$post->title}");
        $this->line("Slug: {$post->slug}");
        $this->line("Fecha de publicación: {$post->published_at}");
        $this->line("Remitente: " . ($post->author_email ?: 'N/A'));

        $originalBlocks = $post->content;
        if (! is_array($originalBlocks)) {
            $originalBlocks = [$originalBlocks];
        }

        $originalCount = count($originalBlocks);
        $originalRaw = $post->getRawOriginal('content');
        $originalLength = is_string($originalRaw) ? strlen($originalRaw) : strlen(json_encode($originalBlocks));

        $this->newLine();
        $this->info("--- ESTADO ORIGINAL ---");
        $this->line("Total de bloques actuales: {$originalCount}");
        $this->line("Longitud total del payload: {$originalLength} bytes");

        // Detectar si contiene artefactos de correo
        $hasDoctype = false;
        $hasXmlns = false;
        $hasDanglingTables = false;
        $hasTrackingPixels = false;

        foreach ($originalBlocks as $b) {
            $bStr = (string) $b;
            if (stripos($bStr, 'doctype') !== false) {
                $hasDoctype = true;
            }
            if (stripos($bStr, 'xmlns:') !== false || stripos($bStr, 'urn:schemas') !== false) {
                $hasXmlns = true;
            }
            if (stripos($bStr, '</td>') !== false || stripos($bStr, '</table>') !== false) {
                $hasDanglingTables = true;
            }
            if (stripos($bStr, 'list-manage.com') !== false || stripos($bStr, 'display:none') !== false) {
                $hasTrackingPixels = true;
            }
        }

        $this->line("Artefactos detectados: " . implode(', ', array_filter([
            $hasDoctype ? 'DOCTYPE HTML crudo' : null,
            $hasXmlns ? 'Namespaces XML/MSO' : null,
            $hasDanglingTables ? 'Tablas/Celdas descolgadas' : null,
            $hasTrackingPixels ? 'Píxeles/Enlaces de rastreo' : null,
        ])) ?: 'Ninguno detectado');

        // Extraer contenido limpio usando el limpiador editorial
        $cleanedBlocks = WordPressContent::cleanEmailHtmlBlocks($originalBlocks);
        $cleanExcerpt = Post::sanitizeExcerpt($post->excerpt, $cleanedBlocks, 160);

        $this->newLine();
        $this->info("--- VISTA PREVIA DEL CONTENIDO SANEADO ---");
        $this->line("Bloques legibles extraídos: " . count($cleanedBlocks));
        $this->line("Extracto limpio generado: {$cleanExcerpt}");

        $this->newLine();
        $this->line("Muestra de bloques depurados:");
        foreach ($cleanedBlocks as $idx => $cleanBlock) {
            $plain = trim(strip_tags(html_entity_decode($cleanBlock, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $preview = mb_substr($plain, 0, 90) . (mb_strlen($plain) > 90 ? '...' : '');
            $this->line("  [Bloque {$idx}] {$preview}");
        }

        if (! $apply) {
            $this->newLine();
            $this->warn("MODO VISTA PREVIA ACTIVO: No se modificó la base de datos.");
            $this->line("Para aplicar los cambios sobre este registro localmente, ejecute:");
            $this->line("  php artisan post:repair-editorial-body {$id} --apply");
            return self::SUCCESS;
        }

        // Respaldo de seguridad previo a cualquier modificación
        $backupDir = storage_path('app/backups/posts');
        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }
        $backupPath = $backupDir . "/post_{$id}_original_" . date('Ymd_His') . '.json';
        File::put($backupPath, json_encode([
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'raw_content' => $originalRaw,
            'backed_up_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Aplicar contenido limpio
        $post->content = $cleanedBlocks;
        if (empty($post->excerpt) || stripos((string) $post->excerpt, 'doctype') !== false) {
            $post->excerpt = $cleanExcerpt;
        }
        $post->save();

        $this->newLine();
        $this->info("✔ REPARACIÓN LOCAL APLICADA CON ÉXITO.");
        $this->line("Respaldo íntegro guardado en: {$backupPath}");
        $this->line("Bloques originales: {$originalCount} → Bloques limpios: " . count($cleanedBlocks));

        return self::SUCCESS;
    }
}

