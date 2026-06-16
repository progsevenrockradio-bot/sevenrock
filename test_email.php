<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sub = \App\Models\TrackSubmission::first();
$mail = new \App\Mail\TrackSubmissionReceived($sub);
echo $mail->render();
