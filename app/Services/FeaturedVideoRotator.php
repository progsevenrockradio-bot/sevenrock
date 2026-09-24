<?php

namespace App\Services;

use App\Models\NewRelease;
use App\Models\ThemeSetting;
use App\Models\TrackSubmission;
use App\Models\TalentMedia;
use App\Models\Video;
use App\Support\YouTubeUrl;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FeaturedVideoRotator
{
    /**
     * @return array<int, array{source_type: string, source_id: int, title: string, youtube_url: string, image: ?string}>
     */
    public function pool(): array
    {
        $existingUsed = Video::query()
            ->whereNotNull('source_type')
            ->whereNotNull('source_id')
            ->get()
            ->map(fn($v) => $v->source_type . '-' . $v->source_id)
            ->flip();

        $candidates = [];

        // a) new_releases
        $releases = NewRelease::query()
            ->whereNotNull('youtube_url')
            ->where('youtube_url', '!=', '')
            ->get();

        foreach ($releases as $release) {
            $url = $release->youtube_url;
            if (YouTubeUrl::videoId($url) !== null) {
                if (!$existingUsed->has('release-' . $release->id)) {
                    $candidates[] = [
                        'source_type' => 'release',
                        'source_id'   => $release->id,
                        'title'       => $release->artist_name . ' — ' . $release->title,
                        'youtube_url' => $url,
                        'image'       => $release->cover_image,
                    ];
                }
            }
        }

        // b) track_submissions
        $submissions = TrackSubmission::query()
            ->whereNotNull('social_link')
            ->where('social_link', '!=', '')
            ->get();

        foreach ($submissions as $sub) {
            $url = $sub->social_link;
            if (YouTubeUrl::videoId($url) !== null) {
                if (!$existingUsed->has('submission-' . $sub->id)) {
                    $candidates[] = [
                        'source_type' => 'submission',
                        'source_id'   => $sub->id,
                        'title'       => $sub->band_name . ' — ' . $sub->song_title,
                        'youtube_url' => $url,
                        'image'       => null,
                    ];
                }
            }
        }

        // c) talent_media
        $talentMedias = TalentMedia::query()
            ->where('type', 'video')
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->with('talent')
            ->get();

        foreach ($talentMedias as $media) {
            $url = $media->url;
            if (YouTubeUrl::videoId($url) !== null) {
                if (!$existingUsed->has('talent_media-' . $media->id)) {
                    $band = $media->talent ? $media->talent->band_name : 'Unknown';
                    $candidates[] = [
                        'source_type' => 'talent_media',
                        'source_id'   => $media->id,
                        'title'       => $band . ' — ' . ($media->title ?: $media->filename),
                        'youtube_url' => $url,
                        'image'       => null,
                    ];
                }
            }
        }

        return $candidates;
    }

    public function rotate(bool $dryRun = false): ?Video
    {
        $settings = ThemeSetting::current();
        if (!$settings || !$settings->featured_video_rotation_enabled) {
            return null;
        }

        $pool = $this->pool();
        if (empty($pool)) {
            return null;
        }

        $candidate = $pool[array_rand($pool)];
        $video = null;

        if (!$dryRun) {
            $slug = Str::slug($candidate['title']);
            if (Video::query()->where('slug', $slug)->exists()) {
                $slug .= '-' . Str::random(5);
            }

            $video = Video::create([
                'title'       => $candidate['title'],
                'slug'        => $slug,
                'image'       => $candidate['image'],
                'youtube_url' => $candidate['youtube_url'],
                'summary'     => '',
                'is_featured' => true,
                'is_manual'   => false,
                'source_type' => $candidate['source_type'],
                'source_id'   => $candidate['source_id'],
                'featured_at' => now(),
            ]);

            // Dejar exactamente 3 destacados en total
            $featuredVideos = Video::query()
                ->where('is_featured', true)
                ->orderBy('is_manual', 'desc')
                ->orderByDesc('featured_at') // los más recientes primero
                ->get();

            $totalFeatured = $featuredVideos->count();
            
            $removed = [];
            if ($totalFeatured > 3) {
                // Buscamos los automáticos más viejos para quitarles el destacado
                $automaticVideos = $featuredVideos->where('is_manual', false)->sortBy('featured_at');
                
                foreach ($automaticVideos as $autoVid) {
                    if ($totalFeatured <= 3) {
                        break;
                    }
                    // Desmarcar
                    $autoVid->update(['is_featured' => false]);
                    $removed[] = $autoVid->title;
                    $totalFeatured--;
                }
            }

            Log::info('Featured Video Rotated', [
                'added' => $video->title,
                'removed' => $removed,
                'total_remaining' => Video::where('is_featured', true)->count()
            ]);
        } else {
            // Dry run
            $video = new Video([
                'title'       => $candidate['title'],
                'youtube_url' => $candidate['youtube_url'],
                'source_type' => $candidate['source_type'],
                'source_id'   => $candidate['source_id'],
            ]);
        }

        return $video;
    }
}
