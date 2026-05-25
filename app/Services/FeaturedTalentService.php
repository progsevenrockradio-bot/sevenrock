<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Talent;
use Illuminate\Support\Collection;

class FeaturedTalentService
{
    public function getFeatured(int $limit = 6): Collection
    {
        $thirtyDaysAgo = now()->subDays(30);

        return Talent::query()
            ->where('subscription_status', 'active')
            ->withCount(['interactions' => function ($query) use ($thirtyDaysAgo): void {
                $query->where('created_at', '>=', $thirtyDaysAgo);
            }])
            ->get()
            ->sortByDesc(function (Talent $talent): float {
                $score = (float) $talent->interactions_count;

                if ($talent->plan === 'premium') {
                    $score *= 3;
                } elseif ($talent->plan === 'pro') {
                    $score *= 1.5;
                }

                if ($talent->is_featured) {
                    $score *= 3;
                }

                return $score;
            })
            ->take($limit)
            ->values();
    }
}
