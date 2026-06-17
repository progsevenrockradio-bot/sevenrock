<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$artist = 'Queen';
$title = 'Bohemian Rhapsody';

try {
    $response = \Illuminate\Support\Facades\Http::get('https://lrclib.net/api/get', [
        'artist_name' => $artist,
        'track_name' => $title,
    ]);

    echo "LRCLIB STATUS: " . $response->status() . "\n";
    echo "LRCLIB BODY: " . $response->body() . "\n";
} catch (\Throwable $e) {
    echo "ERROR LRCLIB: " . $e->getMessage() . "\n";
}

try {
    $response = \Illuminate\Support\Facades\Http::get('https://api.lyrics.ovh/v1/' . rawurlencode($artist) . '/' . rawurlencode($title));
    echo "LYRICS.OVH STATUS: " . $response->status() . "\n";
    echo "LYRICS.OVH BODY: " . substr($response->body(), 0, 200) . "\n";
} catch (\Throwable $e) {
    echo "ERROR LYRICS.OVH: " . $e->getMessage() . "\n";
}
