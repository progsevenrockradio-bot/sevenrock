<?php
$c = ftp_connect('c30.radioboss.fm');
// Let's try some common passwords or just check if they provided it. The screenshot shows DarkVader in two columns.
if (@ftp_login($c, 'DarkVader', 'DarkVader')) {
    echo "Login success with DarkVader / DarkVader\n";
    ftp_pasv($c, true);
    print_r(ftp_nlist($c, '/'));
} else {
    echo "Login failed. Password needed.\n";
}
ftp_close($c);
