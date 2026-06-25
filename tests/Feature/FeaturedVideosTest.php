<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeaturedVideosTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_video_with_featured_status(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.videos.store'), [
            'title' => 'Test Video',
            'slug' => 'test-video',
            'image' => 'catalog/videos/test.jpg',
            'youtube_url' => 'https://www.youtube.com/watch?v=123456',
            'summary' => 'A test video summary',
            'is_featured' => '1',
        ]);

        $response->assertRedirect(route('admin.videos.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('videos', [
            'title' => 'Test Video',
            'is_featured' => true,
        ]);
    }

    public function test_admin_can_update_video_featured_status(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $video = Video::query()->create([
            'title' => 'Test Video',
            'slug' => 'test-video',
            'image' => 'catalog/videos/test.jpg',
            'youtube_url' => 'https://www.youtube.com/watch?v=123456',
            'summary' => 'A test video summary',
            'is_featured' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.videos.update', $video), [
            'title' => 'Test Video Updated',
            'slug' => 'test-video',
            'image' => 'catalog/videos/test.jpg',
            'youtube_url' => 'https://www.youtube.com/watch?v=123456',
            'summary' => 'A test video summary',
            'is_featured' => '0',
        ]);

        $response->assertRedirect(route('admin.videos.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            'title' => 'Test Video Updated',
            'is_featured' => false,
        ]);
    }

    public function test_homepage_shows_up_to_three_featured_videos(): void
    {
        // Create 4 featured videos and 1 non-featured video
        Video::query()->forceCreate([
            'title' => 'Video Oldest Featured',
            'slug' => 'video-oldest-featured',
            'image' => 'test.jpg',
            'is_featured' => true,
            'created_at' => now()->subDays(4),
        ]);

        Video::query()->forceCreate([
            'title' => 'Video Non Featured',
            'slug' => 'video-non-featured',
            'image' => 'test.jpg',
            'is_featured' => false,
            'created_at' => now()->subDays(3),
        ]);

        Video::query()->forceCreate([
            'title' => 'Featured Video 1',
            'slug' => 'featured-video-1',
            'image' => 'test.jpg',
            'is_featured' => true,
            'created_at' => now()->subDays(2),
        ]);

        Video::query()->forceCreate([
            'title' => 'Featured Video 2',
            'slug' => 'featured-video-2',
            'image' => 'test.jpg',
            'is_featured' => true,
            'created_at' => now()->subDays(1),
        ]);

        Video::query()->forceCreate([
            'title' => 'Featured Video 3',
            'slug' => 'featured-video-3',
            'image' => 'test.jpg',
            'is_featured' => true,
            'created_at' => now(),
        ]);

        $response = $this->get(route('home'));
        $response->assertStatus(200);

        // Should see the 3 latest featured videos (checked by title parts because of bicolor HTML formatting)
        $response->assertSee('Featured Video');
        $response->assertSee('1');
        $response->assertSee('2');
        $response->assertSee('3');

        // Should not see the non-featured video or the oldest featured video
        $response->assertDontSee('Video Non Featured');
        $response->assertDontSee('Video Oldest Featured');
    }

    public function test_homepage_hides_video_section_when_no_videos_are_featured(): void
    {
        // No videos featured at all (only 1 non-featured)
        Video::query()->create([
            'title' => 'Video Non Featured Only',
            'slug' => 'video-non-featured-only',
            'image' => 'test.jpg',
            'is_featured' => false,
        ]);

        $response = $this->get(route('home'));
        $response->assertStatus(200);

        // The "featured_video" section heading translation shouldn't be rendered
        $response->assertDontSee('Video Non Featured Only');
        $response->assertDontSee('Video destacado');
    }
}
