<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('posts:publish-scheduled')]
#[Description('Publica automáticamente los posts programados cuya fecha de publicación ya ha llegado')]
class PublishScheduledPosts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = 0;

        try {
            // Find posts that are not yet published but have a published_at in the past
            $posts = Post::query()
                ->where(function ($q) {
                    $q->where('is_published', false)
                      ->orWhere('is_published', 0)
                      ->orWhereNull('is_published');
                })
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->get();

            foreach ($posts as $post) {
                $post->update([
                    'is_published' => true,
                    'status' => 'published',
                    'published_at' => $post->published_at, // keep original scheduled date
                ]);

                $count++;

                $this->line(sprintf(
                    '  ✓ Published: [%d] %s (scheduled: %s)',
                    $post->id,
                    $post->title,
                    $post->published_at
                ));
            }

            $this->info(sprintf('Published %d scheduled post(s).', $count));

            Log::info('posts:publish-scheduled completed', [
                'published_count' => $count,
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error publishing scheduled posts: ' . $e->getMessage());

            Log::error('posts:publish-scheduled failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
