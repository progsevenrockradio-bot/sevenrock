<?php

require dirname(__DIR__, 4) . '/vendor/autoload.php';

if ($argc < 2) {
    die("Uso: php mp3_renamer.php \"C:\\ruta\\a\\la\\carpeta\"\n");
}

$directory = $argv[1];

if (!is_dir($directory)) {
    die("Error: El directorio '$directory' no existe.\n");
}

echo "Iniciando limpieza de Tags y Renombrado de MP3 en: $directory\n";
echo "=================================================\n";

$getID3 = new \JamesHeinrich\GetID3\GetID3();
$getID3->setOption(array('encoding' => 'UTF-8'));

$files = glob(rtrim($directory, '\\/') . '/*.mp3');
$count = 0;

// Textos comunes para limpiar
$cleanRegex = '/\s*[\(\[]\s*(official|audio|video|lyric|music video|hq|hd|live)[\s\w]*[\)\]]/i';

foreach ($files as $file) {
    $fileInfo = $getID3->analyze($file);

    $artist = '';
    $title = '';
    $album = '';

    if (isset($fileInfo['tags']['id3v2'])) {
        $tags = $fileInfo['tags']['id3v2'];
    } elseif (isset($fileInfo['tags']['id3v1'])) {
        $tags = $fileInfo['tags']['id3v1'];
    } else {
        $tags = [];
    }

    if (!empty($tags['artist'])) {
        $artist = implode(', ', $tags['artist']);
    }

    if (!empty($tags['title'])) {
        $title = implode(', ', $tags['title']);
    }

    if (!empty($tags['album'])) {
        $album = implode(', ', $tags['album']);
    }

    if ($artist !== '' && $title !== '') {
        $originalTitle = $title;
        
        // 1. Quitar (Official Audio), [Official Video], etc.
        $title = preg_replace($cleanRegex, '', $title);
        
        // 2. Quitar el "Artista - " del inicio del título si existe
        // Preparamos el artista para la expresión regular (escapando caracteres)
        $artistRegex = preg_quote($artist, '/');
        // Buscamos "Artista - " al principio del título (case-insensitive)
        $title = preg_replace('/^' . $artistRegex . '\s*-\s*/i', '', $title);
        
        // 3. Limpiar espacios extra
        $title = trim($title);

        $tagsModified = false;
        
        // Si el título cambió, actualizamos los Tags ID3
        if ($title !== $originalTitle) {
            $tagwriter = new \JamesHeinrich\GetID3\WriteTags();
            $tagwriter->filename = $file;
            $tagwriter->tagformats = ['id3v2.3'];
            $tagwriter->overwrite_tags = true; 
            $tagwriter->tag_encoding = 'UTF-8';
            $tagwriter->remove_other_tags = false;

            $TagData = [
                'title'  => [$title],
                'artist' => [$artist],
            ];
            
            // También limpiamos el álbum si es igual al título sucio
            if ($album === $originalTitle) {
                $TagData['album'] = [$title];
            }

            $tagwriter->tag_data = $TagData;
            
            if ($tagwriter->WriteTags()) {
                echo "[TAGS OK] Metadatos actualizados.\n";
                $tagsModified = true;
            } else {
                echo "[TAGS ERROR] No se pudieron actualizar los metadatos de: " . basename($file) . "\n";
            }
        }

        // 4. Renombrar el archivo físicamente
        $invalidChars = ['\\', '/', ':', '*', '?', '"', '<', '>', '|'];
        $artistClean = str_replace($invalidChars, '_', $artist);
        $titleClean = str_replace($invalidChars, '_', $title);

        $newName = $artistClean . ' - ' . $titleClean . '.mp3';
        $newPath = dirname($file) . '/' . $newName;

        if ($file !== $newPath) {
            if (rename($file, $newPath)) {
                echo "[RENAME OK] " . basename($file) . " -> $newName\n";
                $count++;
            } else {
                echo "[RENAME ERROR] No se pudo renombrar: " . basename($file) . "\n";
            }
        } else {
            if ($tagsModified) {
                echo "[RENAME IGNORADO] Ya tiene el formato correcto de nombre: " . basename($file) . "\n";
                $count++;
            }
        }
    } else {
        echo "[SALTADO] No tiene ID3 tags completos (Artista/Título faltante): " . basename($file) . "\n";
    }
}

echo "=================================================\n";
echo "Proceso finalizado. $count archivos procesados/renombrados.\n";
