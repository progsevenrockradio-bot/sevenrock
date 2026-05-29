<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BandInfoController;
use App\Http\Controllers\Api\ProgramInfoController;
use App\Http\Controllers\Api\PlayerStatusController;
use App\Http\Controllers\Api\RadioWebhookController;

Route::get('/player/status', [PlayerStatusController::class, 'show'])->middleware('throttle:60,1')->name('api.player.status');
Route::get('/player/band-info', [BandInfoController::class, 'show'])->name('api.player.band-info');
Route::get('/player/program-info', [ProgramInfoController::class, 'show'])->middleware('throttle:60,1')->name('api.player.program-info');
Route::post('/radio/metadata', [RadioWebhookController::class, 'handle'])->middleware('throttle:30,1')->name('api.radio.metadata');
Route::post('/player/metadata_receiver.php', [RadioWebhookController::class, 'handle'])->middleware('throttle:30,1')->name('api.radio.metadata.receiver');
