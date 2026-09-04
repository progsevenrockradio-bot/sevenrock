<?php
require dirname(__DIR__, 4) . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 4));
$dotenv->load();

$ftp_server = $_ENV['RADIOBOSS_FTP_SERVER'];
$ftp_user = $_ENV['RADIOBOSS_FTP_USER'];
$ftp_pass = $_ENV['RADIOBOSS_FTP_PASS'];

$c = ftp_connect($ftp_server);
if (!@ftp_login($c, $ftp_user, $ftp_pass)) {
    die("Login failed.\n");
}
ftp_pasv($c, true);

echo "Iniciando escaneo masivo de toda la librería...\n";
$folders = ftp_nlist($c, '/');
$totalFiles = 0;
$totalDuplicates = 0;
$totalJunk = 0;

$report = [];

function scan_recursive($ftp, string $dir, array &$filesList): void {
    $contents = ftp_nlist($ftp, $dir);
    if (is_array($contents)) {
        foreach ($contents as $item) {
            $base = basename($item);
            if ($base == '.' || $base == '..') continue;
            
            // Si ftp_nlist devuelve solo el nombre, lo concatenamos con el directorio
            $fullPath = (strpos($item, $dir) === 0) ? $item : rtrim($dir, '/') . '/' . $base;
            
            if (pathinfo($fullPath, PATHINFO_EXTENSION) == 'mp3') {
                $filesList[] = $fullPath;
            } elseif (pathinfo($fullPath, PATHINFO_EXTENSION) == '') {
                // Asumimos que es carpeta si no tiene extensión
                scan_recursive($ftp, $fullPath, $filesList);
            }
        }
    }
}

foreach ($folders as $folder) {
    if (in_array(basename($folder), ['.', '..'])) continue;
    
    // Solo escanear carpetas principales que contengan música (empiezan con números)
    if (!preg_match('/^\d{2}\.-/', basename($folder))) continue;

    $files = [];
    scan_recursive($c, $folder, $files);
    
    if (empty($files)) continue;
    
    $groups = [];
    foreach ($files as $file) {
        $totalFiles++;
        $basename = basename($file);
        $cleanName = preg_replace('/^\d+\s*-\s*/', '', $basename);
        $lowername = strtolower($basename);
        
        // Check for Live / Acoustic / Instrumental versions that clutter regular programming
        if (strpos($lowername, 'live') !== false || strpos($lowername, 'acoustic') !== false) {
            $totalJunk++;
            $report['junk'][] = $file;
        } else {
            $groups[$cleanName][] = $file;
        }
    }
    
    foreach ($groups as $cleanName => $filePaths) {
        if (count($filePaths) > 1) {
            $totalDuplicates += (count($filePaths) - 1);
            sort($filePaths);
            $keep = array_shift($filePaths);
            $report['duplicates'] = array_merge($report['duplicates'] ?? [], $filePaths);
        }
    }
}

file_put_contents(__DIR__ . '/massive_scan_report.json', json_encode($report, JSON_PRETTY_PRINT));

echo "=== RESULTADOS DEL ESCANEO MASIVO ===\n";
echo "Total de MP3s escaneados: $totalFiles\n";
echo "Total de archivos duplicados encontrados: $totalDuplicates\n";
echo "Total de archivos Live/Acústicos (Candidatos a borrar): $totalJunk\n";
echo "Total de archivos que se pueden eliminar de inmediato: " . ($totalDuplicates + $totalJunk) . "\n";

ftp_close($c);
