<?php

declare(strict_types=1);

namespace App\Http\Controllers\Talent;

use App\Http\Controllers\Controller;
use App\Support\TalentPlan;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $talent = Auth::guard('talent')->user();
        $plan = $talent?->planLimits() ?? TalentPlan::definition('free')['limits'];
        $planMeta = $talent?->planDefinition() ?? TalentPlan::definition('free');
        $media = $talent ? $talent->media()->latest()->get() : collect();
        $subscription = $talent ? $talent->activeSubscription() : null;

        $usage = [
            'photos' => (int) $media->where('type', 'photo')->count(),
            'songs' => (int) $media->where('type', 'mp3')->count(),
            'documents' => (int) $media->where('type', 'document')->count(),
            'videos' => (int) $media->where('type', 'video')->count(),
            'visits' => (int) ($talent?->interacts ?? 0),
            'storage_used_mb' => round(((int) ($talent?->storageUsed() ?? 0)) / 1024 / 1024, 2),
        ];

        $storageLimitMb = (int) ($plan['storage_mb'] ?? 0);
        $storageUsedMb = (float) $usage['storage_used_mb'];

        return view('talentos.dashboard', [
            'talent' => $talent,
            'plan' => $planMeta,
            'limits' => $plan,
            'usage' => $usage,
            'subscription' => $subscription,
            'media' => $media,
            'storageProgress' => $storageLimitMb > 0
                ? min(100, (int) round(($storageUsedMb / $storageLimitMb) * 100))
                : 0,
        ]);
    }
}
