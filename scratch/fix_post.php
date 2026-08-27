<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require_once dirname(__DIR__) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$post = \App\Models\Post::where('title', 'like', '%rif; line height%')->first();
if ($post) {
    $post->title = 'Efemérides del Rock';
    $post->save();
    echo 'Fixed post ' . $post->id . PHP_EOL;
} else {
    echo 'Not found' . PHP_EOL;
}
