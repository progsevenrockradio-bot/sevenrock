<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$submission = \App\Models\TrackSubmission::first();
echo "Submission ID: " . $submission->id . "\n";
echo "File Path: " . $submission->file_path . "\n";
try {
    $existsLocal = \Illuminate\Support\Facades\Storage::disk('local')->exists($submission->file_path);
    echo "Exists local: " . ($existsLocal ? 'yes' : 'no') . "\n";
} catch (\Exception $e) {
    echo "Exists local error: " . $e->getMessage() . "\n";
}

try {
    $existsR2 = \Illuminate\Support\Facades\Storage::disk('r2')->exists($submission->file_path);
    echo "Exists R2: " . ($existsR2 ? 'yes' : 'no') . "\n";
} catch (\Exception $e) {
    echo "Exists R2 error: " . $e->getMessage() . "\n";
}
