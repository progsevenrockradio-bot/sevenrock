<?php

namespace Tests\Feature;

use App\Models\NewRelease;
use App\Models\ThemeSetting;
use App\Models\TrackSubmission;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Services\FeaturedVideoRotator;

class FeaturedVideoRotationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        ThemeSetting::query()->create([
            'featured_video_rotation_enabled' => true,
        ]);
    }

    public function test_it_does_not_add_channel_urls_to_pool()
    {
        NewRelease::factory()->create([
            'youtube_url' => 'https://youtube.com/@Bottlenext'
        ]);

        $rotator = app(FeaturedVideoRotator::class);
        $this->assertCount(0, $rotator->pool());
    }

    public function test_it_adds_valid_video_urls_to_pool_and_rotates()
    {
        NewRelease::factory()->create([
            'artist_name' => 'Dragonforce',
            'title' => 'Through the Fire and Flames',
            'youtube_url' => 'https://www.youtube.com/watch?v=0jgrCKhxE1s',
            'cover_image' => 'dragonforce.jpg'
        ]);

        $rotator = app(FeaturedVideoRotator::class);
        $pool = $rotator->pool();
        $this->assertCount(1, $pool);
        $this->assertEquals('Dragonforce — Through the Fire and Flames', $pool[0]['title']);

        $video = $rotator->rotate();
        
        $this->assertNotNull($video);
        $this->assertEquals('Dragonforce — Through the Fire and Flames', $video->title);
        $this->assertEquals('0jgrCKhxE1s', \App\Support\YouTubeUrl::videoId($video->youtube_url));
        $this->assertTrue($video->is_featured);
        $this->assertFalse($video->is_manual);
        $this->assertEquals('dragonforce.jpg', $video->image);
    }

    public function test_track_submissions_enter_pool()
    {
        TrackSubmission::factory()->create([
            'band_name' => 'BieDmA BrotherS',
            'song_title' => 'Dejame Quererte',
            'social_link' => 'https://youtube.com/watch?v=dgh_nSa7FzY',
        ]);

        $rotator = app(FeaturedVideoRotator::class);
        $this->assertCount(1, $rotator->pool());
        $this->assertEquals('BieDmA BrotherS — Dejame Quererte', $rotator->pool()[0]['title']);
    }

    public function test_with_3_manual_featured_videos_none_are_removed()
    {
        Video::factory()->count(3)->create([
            'is_featured' => true,
            'is_manual' => true,
        ]);

        NewRelease::factory()->create([
            'youtube_url' => 'https://www.youtube.com/watch?v=0jgrCKhxE1s',
        ]);

        $rotator = app(FeaturedVideoRotator::class);
        $video = $rotator->rotate();

        $this->assertNotNull($video);
        // The newly added video should be featured, making total 4 (3 manual + 1 auto)
        // because manual videos cannot be removed by the rotator.
        $this->assertEquals(4, Video::where('is_featured', true)->count());
        $this->assertEquals(3, Video::where('is_manual', true)->count());
    }

    public function test_when_pool_is_empty_rotate_returns_null_and_keeps_featured()
    {
        Video::factory()->count(3)->create([
            'is_featured' => true,
            'is_manual' => false,
        ]);

        $rotator = app(FeaturedVideoRotator::class);
        $video = $rotator->rotate();

        $this->assertNull($video);
        $this->assertEquals(3, Video::where('is_featured', true)->count());
    }

    public function test_a_used_video_does_not_reenter_until_pool_is_exhausted()
    {
        $release = NewRelease::factory()->create([
            'youtube_url' => 'https://www.youtube.com/watch?v=0jgrCKhxE1s',
        ]);

        Video::factory()->create([
            'source_type' => 'release',
            'source_id' => $release->id,
            'is_featured' => true,
            'is_manual' => false,
        ]);

        $rotator = app(FeaturedVideoRotator::class);
        $this->assertCount(0, $rotator->pool());
    }
}
