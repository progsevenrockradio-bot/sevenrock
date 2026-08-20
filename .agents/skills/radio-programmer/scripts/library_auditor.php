<?php
require dirname(__DIR__, 4) . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 4));
$dotenv->load();

$ftp_server = $_ENV['RADIOBOSS_FTP_SERVER'];
$ftp_user = $_ENV['RADIOBOSS_FTP_USER'];
$ftp_pass = $_ENV['RADIOBOSS_FTP_PASS'];

$folder = '10.- HEAVY METAL';
if ($argc > 1) {
    $folder = $argv[1];
}

$c = ftp_connect($ftp_server);
if (!@ftp_login($c, $ftp_user, $ftp_pass)) {
    die("Login failed.\n");
}
ftp_pasv($c, true);

echo "Auditor de Librería Siete Rock Radio\n";
echo "Escaneando carpeta en FTP: /$folder\n";
echo "Generando listado, por favor espera...\n\n";

$files = ftp_nlist($c, "/$folder");

$report = "# Reporte de Auditoría: $folder\n\n";
$report .= "Revisa este listado. Los archivos marcados con **[SUGERENCIA: BORRAR]** son candidatos para liberar espacio.\n\n";
$report .= "| Archivo | Tamaño Estimado | Recomendación | Razón |\n";
$report .= "|---|---|---|---|\n";

$to_delete = [];

if (is_array($files)) {
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) == 'mp3') {
            $size = ftp_size($c, $file);
            $sizeMB = round($size / 1048576, 2);
            $basename = basename($file);
            $lowername = strtolower($basename);
            
            $action = "**MANTENER**";
            $reason = "Aprobado";
            
            // Reglas básicas de auditoría:
            if ($size > 0 && $sizeMB < 3.5) {
                $action = "**[SUGERENCIA: BORRAR]**";
                $reason = "Baja Calidad (< 192kbps)";
                $to_delete[] = $file;
            } elseif (strpos($lowername, 'live') !== false || strpos($lowername, 'acoustic') !== false) {
                $action = "**[SUGERENCIA: BORRAR]**";
                $reason = "Versión en Vivo/Acústica (Satura programación regular)";
                $to_delete[] = $file;
            }
            // Add more rules as needed...
            
            $report .= "| $basename | {$sizeMB}MB | $action | $reason |\n";
        }
    }
}

// Guardar listado para script cleaner
file_put_contents(__DIR__ . '/to_delete.json', json_encode($to_delete));

// Guardar artefacto
$artifactPath = 'C:/Users/JOSE FONT/.gemini/antigravity-ide/brain/450ff9f9-1508-478f-bd93-95e226dc6491/auditoria.md';
file_put_contents($artifactPath, $report);

echo "\nReporte guardado exitosamente.\n";
ftp_close($c);
