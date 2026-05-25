<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$program = \App\Models\RadioProgram::find(83);
if (!$program) {
    echo "Program 83 not found\n";
    exit(1);
}

\App\Jobs\ProcessMp3Job::dispatch(
    $program,
    (string) $program->archivo_mp3,
    true, // preserve local copy
);

echo "ProcessMp3Job dispatched for program #{$program->id}\n";
