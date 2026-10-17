<?php

namespace Tests\Feature;

use App\Models\RadioArtist;
use App\Models\Song;
use App\Services\RadioPlayerService;
use App\Support\RadioPlayerStateStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Prompt60CoversTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        config([
            'player.streams.direct' => '',
            'player.streams.alt_direct' => '',
            'player.radioboss.api_url' => '',
        ]);
    }

    public function test_song_catalog_cover_takes_precedence_for_real_song(): void
    {
        $song = Song::create([
            'slug' => 'whole-lotta-rosie',
            'title' => 'Whole Lotta Rosie',
            'artist' => 'AC/DC',
            'cover_image' => 'covers/acdc-album.jpg',
            'published_at' => now(),
        ]);

        $service = app(RadioPlayerService::class);
        $service->storeTrack([
            'title' => 'Whole Lotta Rosie',
            'artist' => 'AC/DC',
        ]);

        $state = $service->resolve();

        $this->assertFalse($state['cover_is_fallback']);
        $this->assertEquals('song', $state['meta_sources']['cover']);
        $this->assertStringContainsString('covers/acdc-album.jpg', $state['track']['cover']);
    }

    public function test_band_profile_image_used_when_song_has_no_cover(): void
    {
        $artist = RadioArtist::create([
            'name' => 'AC/DC',
            'image_path' => 'artists/acdc-band.jpg',
        ]);

        $service = app(RadioPlayerService::class);
        $service->storeTrack([
            'title' => 'Highway to Hell',
            'artist' => 'AC/DC',
        ]);

        $state = $service->resolve();

        $this->assertFalse($state['cover_is_fallback']);
        $this->assertEquals('band', $state['meta_sources']['cover']);
        $this->assertStringContainsString('artists/acdc-band.jpg', $state['track']['cover']);
    }

    public function test_fallback_cover_used_when_no_data_available(): void
    {
        $service = app(RadioPlayerService::class);
        $service->storeTrack([
            'title' => 'Canción Desconocida',
            'artist' => 'Banda Desconocida',
        ]);

        $state = $service->resolve();

        $this->assertTrue($state['cover_is_fallback']);
        $this->assertEquals('default', $state['meta_sources']['cover']);
    }

    public function test_webhook_saves_artwork_with_content_hash(): void
    {
        config(['player.webhook.key' => 'secret123']);

        $sampleBinary = str_repeat('X', 300);
        $payload = [
            'key' => 'secret123',
            'title' => 'Thunderstruck',
            'artist' => 'AC/DC',
            'artwork' => base64_encode($sampleBinary),
        ];

        $response = $this->postJson('/api/radio/metadata', $payload);
        $response->assertOk();

        $expectedHash = substr(md5($sampleBinary), 0, 16);
        $expectedRelPath = "radio/covers/cover-{$expectedHash}.jpg";

        Storage::disk('public')->assertExists($expectedRelPath);

        $stateStore = app(RadioPlayerStateStore::class);
        $state = $stateStore->read();

        $this->assertStringContainsString($expectedRelPath, $state['cover']);
    }

    public function test_webhook_does_not_overwrite_good_cover_when_artwork_is_empty(): void
    {
        config(['player.webhook.key' => 'secret123']);

        $stateStore = app(RadioPlayerStateStore::class);
        $stateStore->write([
            'title' => 'Back in Black',
            'artist' => 'AC/DC',
            'cover' => 'https://example.com/good-cover.jpg',
        ]);

        $payload = [
            'key' => 'secret123',
            'title' => 'Back in Black',
            'artist' => 'AC/DC',
            'artwork' => null,
        ];

        $response = $this->postJson('/api/radio/metadata', $payload);
        $response->assertOk();

        $state = $stateStore->read();
        $this->assertEquals('https://example.com/good-cover.jpg', $state['cover']);
    }

    public function test_webhook_resets_cover_when_different_track_arrives_without_artwork(): void
    {
        config(['player.webhook.key' => 'secret123']);

        $stateStore = app(RadioPlayerStateStore::class);
        $stateStore->write([
            'title' => 'Back in Black',
            'artist' => 'AC/DC',
            'cover' => 'https://example.com/good-cover.jpg',
        ]);

        $payload = [
            'key' => 'secret123',
            'title' => 'Smoke on the Water',
            'artist' => 'Deep Purple',
            'artwork' => null,
        ];

        $response = $this->postJson('/api/radio/metadata', $payload);
        $response->assertOk();

        $state = $stateStore->read();
        $this->assertNull($state['cover']);
    }
}
