<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\BandInfoResolver;
use App\Support\LyricsResolver;
use Illuminate\Support\Facades\Log;

final class PlayerWarmupService
{
    public function warmBandInfo(string $artist): void
    {
        $artist = trim($artist);
        if ($artist === '') {
            return;
        }

        dispatch(static function () use ($artist): void {
            try {
                app(BandInfoResolver::class)->resolve($artist);
            } catch (\Throwable $exception) {
                Log::debug('PlayerWarmupService: band warmup skipped', [
                    'artist' => $artist,
                    'message' => $exception->getMessage(),
                ]);
            }
        })->afterResponse();
    }

    public function warmLyrics(string $artist, string $title): void
    {
        $artist = trim($artist);
        $title = trim($title);

        if ($artist === '' || $title === '') {
            return;
        }

        dispatch(static function () use ($artist, $title): void {
            try {
                app(LyricsResolver::class)->resolve($artist, $title);
            } catch (\Throwable $exception) {
                Log::debug('PlayerWarmupService: lyrics warmup skipped', [
                    'artist' => $artist,
                    'title' => $title,
                    'message' => $exception->getMessage(),
                ]);
            }
        })->afterResponse();
    }

    public function warmComplete(string $artist, string $title): void
    {
        $this->warmBandInfo($artist);
        $this->warmLyrics($artist, $title);
    }
}
