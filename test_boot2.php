<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create("https://sevenrockradio.shop", "GET");
try {
    $response = $kernel->handle($request);
    echo "Status: " . $response->getStatusCode() . "\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
