<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Genera iconos PWA en los tamaños requeridos (192×192, 512×512, 180×180)
 * usando la extensión GD de PHP. Los guarda en public/icons/.
 *
 * Uso:
 *   php artisan pwa:icons
 */
class GeneratePwaIcons extends Command
{
    protected $signature   = 'pwa:icons';
    protected $description = 'Genera iconos PWA (192, 512, 180 px) desde el logo del sitio.';

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('La extensión GD de PHP no está disponible. Instálala para generar los iconos.');
            return self::FAILURE;
        }

        // Carpeta de salida
        $outputDir = public_path('icons');
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Fuentes candidatas (de mayor a menor calidad)
        $sources = [
            public_path('assets/lucille/logo_share.png'),
            public_path('assets/lucille/logo.png'),
        ];

        $sourcePath = null;
        foreach ($sources as $candidate) {
            if (file_exists($candidate)) {
                $sourcePath = $candidate;
                break;
            }
        }

        if (! $sourcePath) {
            $this->error('No se encontró ningún logo fuente en public/assets/lucille/.');
            return self::FAILURE;
        }

        $this->info("Usando fuente: {$sourcePath}");

        // Cargar imagen fuente
        $sourceImg = $this->loadImage($sourcePath);
        if (! $sourceImg) {
            $this->error("No se pudo cargar la imagen fuente: {$sourcePath}");
            return self::FAILURE;
        }

        $srcW = imagesx($sourceImg);
        $srcH = imagesy($sourceImg);
        $this->line("  Dimensiones originales: {$srcW}×{$srcH}");

        // Definición de iconos a generar
        $icons = [
            ['file' => 'icon-192.png',           'size' => 192, 'padding' => 0,    'bg' => [18, 18, 18]],
            ['file' => 'icon-512.png',            'size' => 512, 'padding' => 0,    'bg' => [18, 18, 18]],
            ['file' => 'icon-maskable-192.png',   'size' => 192, 'padding' => 0.20, 'bg' => [18, 18, 18]],
            ['file' => 'icon-maskable-512.png',   'size' => 512, 'padding' => 0.20, 'bg' => [18, 18, 18]],
            ['file' => 'apple-touch-icon.png',    'size' => 180, 'padding' => 0.10, 'bg' => [18, 18, 18]],
        ];

        foreach ($icons as $icon) {
            $size    = $icon['size'];
            $padding = (int) ($size * $icon['padding']);
            $inner   = $size - ($padding * 2);
            [$r, $g, $b] = $icon['bg'];

            // Canvas de destino
            $canvas = imagecreatetruecolor($size, $size);
            imagesavealpha($canvas, true);

            // Fondo oscuro sólido
            $bgColor = imagecolorallocate($canvas, $r, $g, $b);
            imagefill($canvas, 0, 0, $bgColor);

            // Escalar y centrar el logo (con transparencia)
            imagecopyresampled(
                $canvas, $sourceImg,
                $padding, $padding,          // dst x,y
                0, 0,                        // src x,y
                $inner, $inner,              // dst w,h
                $srcW, $srcH                 // src w,h
            );

            $outPath = $outputDir . DIRECTORY_SEPARATOR . $icon['file'];
            imagepng($canvas, $outPath, 9);
            imagedestroy($canvas);

            $this->line("  ✓ {$icon['file']} ({$size}×{$size}" . ($padding ? ", padding {$padding}px" : '') . ')');
        }

        imagedestroy($sourceImg);

        $this->newLine();
        $this->info('Iconos PWA generados en public/icons/');
        $this->comment('Recuerda ejecutar este comando también en producción después del git pull.');

        return self::SUCCESS;
    }

    /** Carga una imagen PNG o JPG y devuelve el recurso GD. */
    private function loadImage(string $path): \GdImage|false
    {
        $mime = mime_content_type($path);

        return match (true) {
            str_contains($mime, 'png')  => imagecreatefrompng($path),
            str_contains($mime, 'jpeg'),
            str_contains($mime, 'jpg')  => imagecreatefromjpeg($path),
            str_contains($mime, 'webp') => imagecreatefromwebp($path),
            default                     => false,
        };
    }
}
