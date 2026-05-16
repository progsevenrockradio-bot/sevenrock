<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Song;
use App\Models\BandProfile;
use App\Support\BandInfoAggregator;
use Illuminate\Support\Facades\Log;
use App\Support\LyricsResolver;

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
                app(BandInfoAggregator::class)->aggregate($artist);

                $profile = BandProfile::query()
                    ->get()
                    ->first(function (BandProfile $candidate) use ($artist): bool {
                        $normalizedArtist = preg_replace('/[^a-z0-9]+/i', '', mb_strtolower(trim($artist))) ?: '';

                        if (preg_replace('/[^a-z0-9]+/i', '', mb_strtolower($candidate->name)) === $normalizedArtist) {
                            return true;
                        }

                        foreach ((array) $candidate->related_artists as $relatedArtist) {
                            if (is_string($relatedArtist) && preg_replace('/[^a-z0-9]+/i', '', mb_strtolower($relatedArtist)) === $normalizedArtist) {
                                return true;
                            }
                        }

                        return false;
                    });

                if ($profile) {
                    Song::query()
                        ->whereRaw('LOWER(artist) = ?', [mb_strtolower($artist)])
                        ->whereNull('band_profile_id')
                        ->update(['band_profile_id' => $profile->id]);
                }
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
                $lyrics = app(LyricsResolver::class)->resolve($artist, $title);
                $lyrics = trim((string) $lyrics);

                if ($lyrics !== '' && $lyrics !== 'Letra no disponible') {
                    Song::query()
                        ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])
                        ->whereRaw('LOWER(artist) = ?', [mb_strtolower($artist)])
                        ->update(['lyrics' => $lyrics]);
                }
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
