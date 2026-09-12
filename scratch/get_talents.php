<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (\App\Models\Talent::take(5)->get() as $t) {
    echo $t->band_name . ',' . $t->email . PHP_EOL;
}
