<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::capture();
$app->instance('request', $request);
$kernel->bootstrap();

try {
    $tempPath = tempnam(sys_get_temp_dir(), 'mp3_');
    $mp3TempPath = $tempPath . '.mp3';
    rename($tempPath, $mp3TempPath);
    $tempPath = $mp3TempPath;

    file_put_contents($tempPath, "dummy content");

    $tagwriter = new \JamesHeinrich\GetID3\WriteTags();
    $tagwriter->filename = $tempPath;
    $tagwriter->tagformats = ['id3v2.3'];
    $tagwriter->overwrite_tags = true; 
    $tagwriter->tag_encoding = 'UTF-8';
    $tagwriter->remove_other_tags = false;

    $TagData = [
        'title'  => ['Song Title'],
        'artist' => ['Band Name'],
        'album'  => ['Seven Rock Radio Submissions'],
    ];

    $tagwriter->tag_data = $TagData;
    
    if ($tagwriter->WriteTags()) {
        echo "Successfully wrote tags to temp file: $tempPath\n";
    } else {
        echo "Failed to write tags!\n";
        print_r($tagwriter->errors);
        print_r($tagwriter->warnings);
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
