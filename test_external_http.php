<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $response = \App\Support\ExternalHttp::client()->get('https://lrclib.net/api/get', [
        'artist_name' => 'Queen',
        'track_name' => 'Bohemian Rhapsody',
    ]);
    echo "STATUS: " . $response->status() . "\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
