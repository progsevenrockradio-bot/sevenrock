<?php

declare(strict_types=1);

namespace App\Http\Controllers\Talent;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('talentos.profile', [
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
            'band_name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'logo' => ['nullable', 'image', 'max:4096'],
        ]);

        try {
            if ($request->hasFile('logo')) {
                if (filled($talent->logo)) {
                    try {
                        Storage::disk('backblaze-b2')->delete($talent->logo);
                    } catch (\Throwable) {
                        //
                    }
                }

                $path = $request->file('logo')->storePublicly("talents/{$talent->id}/logo", 'backblaze-b2');
                $talent->logo = $path;
            }

            $talent->band_name = $validated['band_name'];
            $talent->bio = $validated['bio'] ?? null;
            $talent->save();
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors([
                'logo' => 'Unable to update talent profile.',
            ]);
        }

        return redirect()->route('talents.profile')->with('status', 'Profile updated.');
    }
}
