<?php
require dirname(__DIR__, 4) . '/vendor/autoload.php';

require dirname(__DIR__, 4) . '/vendor/autoload.php';

$dir = "C:\\Users\\JOSE FONT\\Desktop\\MusicaNew para la Radio";
$files = glob(rtrim($dir, '\\/') . '/*.mp3');

$getID3 = new \JamesHeinrich\GetID3\GetID3();
$getID3->setOption(array('encoding' => 'UTF-8'));

echo "Iniciando descarga e incrustación de carátulas (Cover Art) en: $dir\n";
echo "=================================================\n";

foreach ($files as $file) {
    $basename = basename($file);
    
    $fileInfo = $getID3->analyze($file);
    
    $artist = '';
    $title = '';
    
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
    
    // Si no tiene tags, intentar adivinar del nombre del archivo (Ej: Artista - Titulo)
    if (empty($artist) || empty($title)) {
        $cleanName = preg_replace('/(?i)\s*MP3$/', '', pathinfo($basename, PATHINFO_FILENAME));
        $parts = explode(' - ', $cleanName, 2);
        if (count($parts) == 2) {
            $artist = trim($parts[0]);
            $title = trim($parts[1]);
        }
    }
    
    if (empty($artist) || empty($title)) {
        echo "[SALTADO] No se pudo determinar artista/título para: $basename\n";
        continue;
    }
    
    echo "Buscando cover para: $artist - $title ... ";
    
    $searchTerm = urlencode("$artist $title");
    $url = "https://itunes.apple.com/search?term=$searchTerm&entity=song&limit=1";
    
    $context = stream_context_create([
        'http' => ['user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36']
    ]);
    
    $response = @file_get_contents($url, false, $context);
    if (!$response) {
        echo "[ERROR API]\n";
        continue;
    }
    
    $data = json_decode($response, true);
    if (empty($data['results'])) {
        echo "[NO ENCONTRADO]\n";
        continue;
    }
    
    // Obtener la url de la imagen en alta calidad (500x500)
    $artworkUrl = $data['results'][0]['artworkUrl100'];
    $artworkUrl = str_replace('100x100bb', '500x500bb', $artworkUrl);
    
    $imgData = @file_get_contents($artworkUrl, false, $context);
    if (!$imgData) {
        echo "[ERROR DESCARGA IMG]\n";
        continue;
    }
    
    // Escribir tags
    $tagwriter = new \JamesHeinrich\GetID3\WriteTags();
    $tagwriter->filename = $file;
    $tagwriter->tagformats = ['id3v2.3']; // ID3v2 es lo estándar para imágenes
    $tagwriter->overwrite_tags = true;
    $tagwriter->tag_encoding = 'UTF-8';
    
    $mime = (strpos($artworkUrl, '.png') !== false) ? 'image/png' : 'image/jpeg';
    
    $TagData = [
        'title' => [$title],
        'artist' => [$artist],
        'attached_picture' => [
            0 => [
                'data' => $imgData,
                'picturetypeid' => 3, // Cover (front)
                'description' => 'Cover',
                'mime' => $mime
            ]
        ]
    ];
    
    $tagwriter->tag_data = $TagData;
    
    if ($tagwriter->WriteTags()) {
        echo "[OK GUARDADO]\n";
        // Si el archivo tenía el nombre feo "Artista - Titulo MP3.mp3", lo renombramos a uno limpio
        if (strpos($basename, ' MP3.mp3') !== false) {
            $newName = "$artist - $title.mp3";
            $newName = preg_replace('/[\\\\\\/:*?"<>|]/', '', $newName); // clean invalid chars
            $newPath = dirname($file) . '/' . $newName;
            rename($file, $newPath);
        }
    } else {
        echo "[ERROR ESCRITURA TAGS]\n";
    }
}

echo "=================================================\n";
echo "Proceso de carátulas finalizado.\n";
