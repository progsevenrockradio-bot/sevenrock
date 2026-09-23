<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\NewRelease;
use App\Models\Video;
use Illuminate\Support\Str;

class RotateFeaturedVideosCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'videos:rotate-releases';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rotate featured videos using YouTube links from last week\'s new releases';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting video rotation from new releases...');

        // Buscamos lanzamientos de los últimos 7 días que tengan enlace a YouTube
        $recentReleases = NewRelease::query()
            ->whereNotNull('youtube_url')
            ->where('youtube_url', '!=', '')
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->get();

        if ($recentReleases->isEmpty()) {
            $this->warn('No new releases with YouTube links found in the last 7 days. Keeping current featured videos.');
            return 0;
        }

        // Si hay nuevos, quitamos is_featured a los actuales
        Video::query()->update(['is_featured' => false]);
        $this->info('Cleared previous featured videos.');

        $added = 0;

        foreach ($recentReleases as $release) {
            $title = trim($release->artist_name . ' - ' . $release->title);
            
            // Verificamos si ya existe un video con esa url o titulo
            $video = Video::query()->where('youtube_url', $release->youtube_url)->first();

            if (! $video) {
                // Creamos un nuevo video
                Video::query()->create([
                    'title' => $title,
                    'slug' => Str::slug($title) . '-' . Str::random(5),
                    'image' => $release->cover_image,
                    'youtube_url' => $release->youtube_url,
                    'summary' => Str::limit($release->description, 250),
                    'is_featured' => true,
                ]);
            } else {
                // Lo marcamos como destacado y actualizamos datos si es necesario
                $video->update([
                    'is_featured' => true,
                    'image' => $video->image ?: $release->cover_image,
                ]);
            }

            $added++;
        }

        $this->info("Successfully rotated $added featured videos.");

        return 0;
    }
}
