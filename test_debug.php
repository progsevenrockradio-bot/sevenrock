<?php
require 'vendor/autoload.php';
$g = new \JamesHeinrich\GetID3\GetID3();
$f = $g->analyze('C:\Users\JOSE FONT\Desktop\MusicaNew para la Radio\OnlyMP3.cx - Greta Van Fleet - Runway Blues Official Audio.mp3');
if (class_exists('getid3_lib')) {
    getid3_lib::CopyTagsToComments($f);
} elseif (class_exists('\JamesHeinrich\GetID3\GetId3Lib')) {
    \JamesHeinrich\GetID3\GetId3Lib::CopyTagsToComments($f);
}
print_r(array_keys($f));
if (isset($f['comments'])) print_r($f['comments']);
if (isset($f['tags'])) print_r($f['tags']);
