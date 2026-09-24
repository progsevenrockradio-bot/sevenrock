<?php

namespace App\Console\Commands;

use App\Services\FeaturedVideoRotator;
use Illuminate\Console\Command;

class RotateFeaturedVideo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'videos:rotate-featured {--force} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rotate the featured video in the home page from the pool of unused videos';

    /**
     * Execute the console command.
     */
    public function handle(FeaturedVideoRotator $rotator)
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in dry-run mode. Discovering pool...');
            $pool = $rotator->pool();
            $this->info('Pool size: ' . count($pool));
            if (count($pool) > 0) {
                $this->table(['Source', 'ID', 'Title', 'YouTube URL'], array_map(function($p) {
                    return [$p['source_type'], $p['source_id'], $p['title'], $p['youtube_url']];
                }, $pool));
                
                $video = $rotator->rotate(true);
                $this->info('Would have picked: ' . $video->title . ' (' . $video->youtube_url . ')');
            } else {
                $this->warn('Pool is empty. No video would be rotated.');
            }
            return self::SUCCESS;
        }

        $video = $rotator->rotate();

        if ($video) {
            $this->info('Rotated featured video successfully. New video: ' . $video->title);
        } else {
            $this->warn('No video rotated (pool empty or rotation disabled).');
        }

        return self::SUCCESS;
    }
}
