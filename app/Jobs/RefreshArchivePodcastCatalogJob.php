<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\ArchiveOrgService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshArchivePodcastCatalogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(
        public int $limit = 20
    ) {}

    public function handle(ArchiveOrgService $archiveOrgService): void
    {
        $archiveOrgService->refreshCatalog($this->limit);
    }
}

