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
            // Find posts explicitly marked as 'scheduled' that are not yet published but have a published_at in the past
            $posts = Post::query()
                ->where('status', 'scheduled')
                ->where(function ($q) {
                    $q->where('is_published', false)
                      ->orWhere('is_published', 0)
                      ->orWhereNull('is_published');
                })
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->get();

            $safetyValidator = app(\App\Services\EmailSafetyValidator::class);

            foreach ($posts as $post) {
                // Comprobación de incidencias pendientes de corrección (Fase 5)
                if (\Illuminate\Support\Facades\Schema::hasTable('editorial_incidents')) {
                    $hasUnresolvedIncident = \App\Models\EditorialIncident::where('post_id', $post->id)
                        ->where('resolution_status', 'pending_review')
                        ->whereIn('action_taken', ['halted_scheduled_post', 'flagged_published_post'])
                        ->exists();

                    if ($hasUnresolvedIncident) {
                        $this->warn(sprintf('  ⚠️ Publicación detenida por incidencia de corrección pendiente: [%d] %s', $post->id, $post->title));
                        Log::warning('posts:publish-scheduled bloqueado por EditorialIncident pendiente', [
                            'post_id' => $post->id,
                            'title'   => $post->title,
                        ]);
                        $post->update(['status' => 'draft']);
                        continue;
                    }
                }

                // Validación estricta de seguridad editorial antes de publicar
                if (! $safetyValidator->isSafeToPublish($post)) {

                    $validation = $safetyValidator->validatePost($post);
                    $reason = implode('; ', $validation['violations']);
                    $this->warn(sprintf('  ⚠️ Bloqueado por seguridad: [%d] %s (motivo: %s)', $post->id, $post->title, $reason));
                    Log::warning('posts:publish-scheduled bloqueado por EmailSafetyValidator', [
                        'post_id'    => $post->id,
                        'title'      => $post->title,
                        'violations' => $validation['violations'],
                    ]);
                    $post->update(['status' => 'draft']);
                    continue;
                }

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
