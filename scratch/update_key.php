<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$s = App\Models\ThemeSetting::first();
if($s) {
    $s->gemini_api_key = 'AlzaSyB_2GxXToPMDHwUykF3BGwndWM7y-ZNhNE';
    $s->save();
    echo "API Key updated successfully.\n";
} else {
    echo "No ThemeSetting found.\n";
}
