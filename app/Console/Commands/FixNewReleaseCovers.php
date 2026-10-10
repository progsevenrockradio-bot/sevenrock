<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\NewRelease;
use App\Models\TrackSubmission;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FixNewReleaseCovers extends Command
{
    protected $signature = 'releases:fix-covers {--days=60 : Número de días hacia atrás para buscar lanzamientos} {--dry-run : Solo muestra lo que haría sin cambiar nada}';
    protected $description = 'Copia la imagen (portada) extraída de la maqueta (TrackSubmission) a la ficha del Nuevo Lanzamiento si este no tiene portada.';

    public function handle()
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info("Buscando lanzamientos sin portada en los últimos {$days} días...");

        $releases = NewRelease::whereNull('cover_image')
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        if ($releases->isEmpty()) {
            $this->info('No se encontraron lanzamientos sin portada.');
            return;
        }

        $this->info("Se encontraron {$releases->count()} lanzamientos problemáticos.");

        $getId3 = new \JamesHeinrich\GetID3\GetID3();

        foreach ($releases as $release) {
            $this->info("Procesando Release ID: {$release->id} | Título: {$release->title}");
            
            // Buscar la submission original (por el título y banda)
            $submission = TrackSubmission::where('song_title', $release->title)
                ->where('band_name', $release->artist_name)
                ->first();

            if (!$submission || !$submission->file_path) {
                $this->error("  -> No se encontró maqueta (TrackSubmission) para este lanzamiento o no tiene file_path.");
                continue;
            }

            // Descargar temporalmente el MP3 para extraer el ID3 tag
            $tmpPath = storage_path('app/temp_id3_' . Str::random(10) . '.mp3');
            try {
                $fileContents = Storage::disk('r2')->get($submission->file_path);
                if (!$fileContents) {
                    $this->error("  -> No se pudo descargar el archivo de la maqueta desde R2.");
                    continue;
                }
                file_put_contents($tmpPath, $fileContents);

                $fileInfo = $getId3->analyze($tmpPath);

                if (isset($fileInfo['comments']['picture'][0]['data'])) {
                    $picture = $fileInfo['comments']['picture'][0];
                    $imageBytes = $picture['data'];
                    $imageMime = $picture['image_mime'] ?? 'image/jpeg';
                    $ext = explode('/', $imageMime)[1] ?? 'jpg';
                    $ext = str_replace('jpeg', 'jpg', $ext);
                    
                    $newFileName = 'catalog/releases/covers/' . Str::uuid()->toString() . '.' . $ext;
                    
                    if ($dryRun) {
                        $this->info("  [DRY RUN] Extraería la imagen ID3 y la guardaría en: {$newFileName}");
                    } else {
                        Storage::disk('public')->put($newFileName, $imageBytes);
                        $release->cover_image = $newFileName;
                        $release->save();
                        $this->info("  [EXITO] Imagen extraída y guardada en: {$newFileName}");
                    }
                } else {
                    $this->error("  -> El archivo MP3 de la maqueta no contiene una imagen incrustada (ID3 Picture).");
                }
            } catch (\Exception $e) {
                $this->error("  -> Error al procesar el archivo: " . $e->getMessage());
            } finally {
                if (file_exists($tmpPath)) {
                    @unlink($tmpPath);
                }
            }
        }

        $this->info("Proceso terminado.");
    }
}
