<?php
$c = ftp_connect('c30.radioboss.fm');
ftp_login($c, 'Usuario', 'R@DIOBOZZ_2026*-User');
ftp_pasv($c, true);

function scan_ftp($ftp, $dir, $level = 0) {
    if ($level > 3) return; // limit depth
    $contents = ftp_nlist($ftp, $dir);
    if (is_array($contents)) {
        foreach ($contents as $file) {
            // avoid . and ..
            if (basename($file) == '.' || basename($file) == '..') continue;
            
            // if it's a directory
            if (ftp_size($ftp, $file) == -1) {
                echo str_repeat("  ", $level) . "[DIR] $file\n";
                scan_ftp($ftp, $file, $level + 1);
            } else {
                if (pathinfo($file, PATHINFO_EXTENSION) == 'mp3' && $level < 2) {
                     echo str_repeat("  ", $level) . "[FILE] $file\n";
                }
            }
        }
    }
}

echo "Buscando carpetas de música en el FTP...\n";
scan_ftp($c, '/');
ftp_close($c);
