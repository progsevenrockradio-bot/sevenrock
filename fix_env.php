<?php
$env = file_get_contents(".env");
$lines = explode("\n", $env);
foreach ($lines as $i => $line) {
    if (strpos($line, "MAIL_PASSWORD=") === 0) {
        $lines[$i] = "MAIL_PASSWORD=zttt abcd efgh ijkl";
    }
}
file_put_contents(".env", implode("\n", $lines));
echo "Fixed!\n";
