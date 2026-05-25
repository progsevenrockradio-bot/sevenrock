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
        $plan = $talent?->planDefinition() ?? TalentPlan::definition('free');
        $media = $talent ? $talent->media()->latest()->get() : collect();
        $subscription = $talent ? $talent->activeSubscription() : null;

        $used = [
            'photo' => (int) $media->where('type', 'photo')->count(),
            'mp3' => (int) $media->where('type', 'mp3')->count(),
            'document' => (int) $media->where('type', 'document')->count(),
            'interacts' => (int) ($talent?->interacts ?? 0),
        ];

        return view('talentos.dashboard', [
            'talent' => $talent,
            'plan' => $plan,
            'used' => $used,
            'subscription' => $subscription,
            'media' => $media,
        ]);
    }
}
