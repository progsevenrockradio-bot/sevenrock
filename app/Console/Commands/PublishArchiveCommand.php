<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RadioProgram;
use App\Services\ArchiveOrgPodcastService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('podcast:publish-archive')]
#[Description('Publica en Archive.org los episodios cuya fecha de emisión ya pasó')]
class PublishArchiveCommand extends Command
{
    public function handle(ArchiveOrgPodcastService $archiveOrgService): int
    {
        if (! $archiveOrgService->canSync()) {
            $this->warn('Archive.org credentials missing. Skipping.');

            return self::SUCCESS;
        }

        $episodes = RadioProgram::query()
            ->with('masterProgram')
            ->where('sync_archive_org', true)
            ->whereIn('archive_org_status', ['archive_verified', 'archive_pending', 'archive_pending_indexing'])
            ->whereNotNull('archive_org_remote_path')
            ->whereDate('fecha_emision', '<=', now()->toDateString())
            ->get()
            ->filter(function (RadioProgram $episode): bool {
                $metadata = $episode->archive_org_metadata;
                if (! is_array($metadata)) {
                    return true;
                }

                $published = (bool) data_get($metadata, 'published', false);
                $collection = (string) data_get($metadata, 'collection', data_get($metadata, 'item_metadata.collection', ''));

                return ! $published && $collection !== 'opensource_audio';
            });

        if ($episodes->isEmpty()) {
            Log::info('PublishArchiveCommand: nothing to publish.');

            return self::SUCCESS;
        }

        $published = 0;
        $failed = 0;

        foreach ($episodes as $episode) {
            $episode->loadMissing('masterProgram');
            $identifier = $archiveOrgService->resolveIdentifier($episode, $episode->masterProgram);

            $this->info("Publishing: #{$episode->id} -> {$identifier}");

            $result = $archiveOrgService->publishItem($identifier);

            if ($result['success'] ?? false) {
                $published++;

                RadioProgram::withoutEvents(function () use ($episode): void {
                    $episode->forceFill([
                        'archive_org_status' => 'archive_verified',
                        'archive_org_metadata' => array_merge(
                            (array) ($episode->archive_org_metadata ?? []),
                            [
                                'published' => true,
                                'collection' => 'opensource_audio',
                                'published_at' => now()->toIso8601String(),
                            ]
                        ),
                        'status_message' => 'Archive.org publicado correctamente.',
                    ])->saveQuietly();
                });

                $this->line('  Published.');
                continue;
            }

            $failed++;
            $message = (string) ($result['message'] ?? 'Unknown error');

            Log::warning('PublishArchiveCommand: failed to publish', [
                'program_id' => $episode->id,
                'identifier' => $identifier,
                'error' => $message,
            ]);

            $this->warn("  Failed: {$message}");
        }

        Log::info('PublishArchiveCommand: done.', [
            'published' => $published,
            'failed' => $failed,
        ]);

        $this->info("Done. Published: {$published}, Failed: {$failed}.");

        return self::SUCCESS;
    }
}
