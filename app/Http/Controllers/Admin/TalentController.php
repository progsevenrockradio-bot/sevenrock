<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Talent;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TalentController extends Controller
{
    public function index(): View
    {
        $talents = Talent::query()
                ->withCount([
                    'media',
                    'interactions',
                ])
                ->with('subscriptions')
                ->orderByDesc('is_featured')
                ->orderByDesc('interacts')
                ->latest('id')
                ->paginate(20);

        return view('admin.talents.index', [
            'talents' => $talents,
            'stats' => [
                'total' => Talent::query()->count(),
                'featured' => Talent::query()->where('is_featured', true)->count(),
                'active' => Talent::query()->where('subscription_status', 'active')->count(),
                'interactions' => (int) Talent::query()->sum('interacts'),
            ],
        ]);
    }

    public function toggleFeatured(Talent $talent): RedirectResponse
    {
        $talent->update([
            'is_featured' => ! $talent->is_featured,
        ]);

        return back()->with('status', $talent->is_featured ? 'Talent marked as featured.' : 'Talent unmarked as featured.');
    }
}
