<?php
$c = ftp_connect('c30.radioboss.fm');
ftp_login($c, 'DarkVader', 'R@DIOBOZZ_2026*-User');
ftp_pasv($c, true);

file_put_contents('test.pls', '[playlist]');
$putResult = ftp_put($c, '/PROGRAMAS/test.pls', 'test.pls', FTP_BINARY);
echo "ftp_put result: " . ($putResult ? 'true' : 'false') . "\n";

if ($putResult) {
    $renameResult = ftp_rename($c, '/PROGRAMAS/test.pls', '/PROGRAMAS/test.m3u');
    echo "ftp_rename result: " . ($renameResult ? 'true' : 'false') . "\n";
}
