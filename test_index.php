<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::capture();
$app->instance('request', $request);
$kernel->bootstrap();

try {
    $controller = new \App\Http\Controllers\Admin\AdminTrackSubmissionController();
    $view = $controller->index();
    echo get_class($view) . "\n";
    $rendered = $view->render();
    echo "Rendered length: " . strlen($rendered) . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
