<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Song;
use App\Support\BandInfoResolver;
use App\Support\LyricsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BandInfoMismatchGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_ignores_song_band_info_when_the_lookup_artist_does_not_match(): void
    {
        Song::query()->create([
            'slug' => 'battle-hymn-masacre',
            'title' => 'Battle Hymn',
            'artist' => 'Masacre',
            'band_info' => 'Wrong band profile for a different artist.',
            'lyrics' => '',
        ]);

        $this->app->instance(BandInfoResolver::class, new class extends BandInfoResolver
        {
            public function resolve(string $artist): array
            {
                return [
                    'summary' => 'Fallback band summary',
                    'thumbnail' => '',
                    'social_links' => [],
                    'formed_year' => null,
                    'formed_label' => '',
                    'facts' => [],
                ];
            }
        });

        $this->app->instance(LyricsResolver::class, new class extends LyricsResolver
        {
            public function resolve(string $artist, string $title): string
            {
                return 'Lyrics from the correct lookup path';
            }
        });

        $response = $this->getJson('/api/player/band-info?artist=Manowar&title=Battle%20Hymn');

        $response->assertOk();
        $response->assertJsonPath('data.summary', 'Fallback band summary');
        $response->assertJsonPath('data.lyrics', 'Lyrics from the correct lookup path');
    }
}
