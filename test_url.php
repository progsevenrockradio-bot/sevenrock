<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

config(['filesystems.disks.r2.url' => 'https://media.sevenrockradio.com']);

echo App\Support\PublicMediaUrl::normalizePublicUrl('https://media.sevenrockradio.com/file/7RR-DATOS/catalog/releases/covers/048d7bbd-386a-481b-b9ce-9d113a9b876b.jpeg');
echo "\n";
