<?php
foreach(file(".env") as $l) {
    if(strpos($l, "MAIL_PASSWORD")===0) { 
        echo trim($l) . "\n"; 
    }
}
echo "---\n";
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create("https://sevenrockradio.shop", "GET");
$response = $kernel->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
