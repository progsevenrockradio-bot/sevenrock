<?php
require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$ftp_server = $_ENV['RADIOBOSS_FTP_SERVER'];
$ftp_user = $_ENV['RADIOBOSS_FTP_USER'];
$ftp_pass = $_ENV['RADIOBOSS_FTP_PASS'];

$c = ftp_connect($ftp_server);
if (!@ftp_login($c, $ftp_user, $ftp_pass)) {
    die("Login failed.\n");
}
ftp_pasv($c, true);

$folders = ftp_nlist($c, '/');
$counts = [];

echo "Contando archivos por carpeta...\n";

foreach ($folders as $folder) {
    if (in_array(basename($folder), ['.', '..'])) continue;
    
    // Only check folders that start with numbers (01.-, 02.-, etc) to avoid 99.- BANDAS if it's huge or irrelevant, 
    // actually let's check all of them.
    $files = ftp_nlist($c, $folder);
    if (is_array($files)) {
        $count = count(array_filter($files, function($f) { return pathinfo($f, PATHINFO_EXTENSION) == 'mp3'; }));
        $counts[$folder] = $count;
        echo "Carpeta: $folder -> $count MP3s\n";
    }
}

arsort($counts);
$biggest = array_key_first($counts);
echo "\n====================\n";
echo "La carpeta más grande es: $biggest con " . $counts[$biggest] . " archivos.\n";

ftp_close($c);
