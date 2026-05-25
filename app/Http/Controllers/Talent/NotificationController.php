<?php

declare(strict_types=1);

namespace App\Http\Controllers\Talent;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function edit(): View
    {
        return view('talentos.notifications', [
            'talent' => Auth::guard('talent')->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $talent = Auth::guard('talent')->user();
        if (! $talent) {
            return redirect()->route('talents.login');
        }

        $validated = $request->validate([
            'likes' => ['nullable', 'boolean'],
            'comments' => ['nullable', 'boolean'],
            'renewals' => ['nullable', 'boolean'],
        ]);

        $talent->notification_preferences = [
            'likes' => $request->boolean('likes'),
            'comments' => $request->boolean('comments'),
            'renewals' => $request->boolean('renewals'),
        ];
        $talent->save();

        return back()->with('status', 'Preferencias de notificación actualizadas.');
    }
}
