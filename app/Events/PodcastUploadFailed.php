<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class PodcastUploadFailed
{
    use Dispatchable;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public readonly int $radioProgramId,
        public readonly string $stage,
        public readonly string $message,
        public readonly array $context = [],
    ) {
    }
}
