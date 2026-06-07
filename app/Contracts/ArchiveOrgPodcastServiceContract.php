<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\RadioProgram;

interface ArchiveOrgPodcastServiceContract
{
    public function canSync(): bool;

    /**
     * @return array<string, mixed>
     */
    public function syncEpisode(RadioProgram $episode): array;

    public function patchFileMetadata(RadioProgram $episode): void;
}
