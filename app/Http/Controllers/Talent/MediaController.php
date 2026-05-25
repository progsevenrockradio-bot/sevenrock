<?php

declare(strict_types=1);

namespace App\Http\Controllers\Talent;

use App\Http\Controllers\Controller;
use App\Models\TalentMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function index(): View
    {
        $talent = Auth::guard('talent')->user();

        return view('talentos.media', [
            'talent' => $talent,
            'media' => $talent ? $talent->media()->latest()->get() : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $talent = Auth::guard('talent')->user();
        if (! $talent) {
            return redirect()->route('talents.login');
        }

        $validated = $request->validate([
            'type' => ['required', 'in:photo,mp3,document'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file' => ['required', 'file', 'max:51200'],
        ]);

        try {
            $file = $request->file('file');
            $folder = "talents/{$talent->id}/media/{$validated['type']}";
            $path = $file->storePublicly($folder, 'backblaze-b2');
            $url = Storage::disk('backblaze-b2')->url($path);

            TalentMedia::query()->create([
                'talent_id' => $talent->id,
                'type' => $validated['type'],
                'filename' => $file->getClientOriginalName(),
                'backblaze_key' => $path,
                'url' => $url,
                'title' => $validated['title'] ?? null,
                'description' => $validated['description'] ?? null,
                'size' => (int) $file->getSize(),
            ]);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors([
                'file' => 'Unable to upload media.',
            ]);
        }

        return redirect()->route('talents.media.index')->with('status', 'Media uploaded.');
    }

    public function destroy(TalentMedia $talentMedia): RedirectResponse
    {
        $talent = Auth::guard('talent')->user();
        if (! $talent || (int) $talentMedia->talent_id !== (int) $talent->id) {
            abort(403);
        }

        try {
            Storage::disk('backblaze-b2')->delete($talentMedia->backblaze_key);
        } catch (\Throwable) {
            //
        }

        $talentMedia->delete();

        return redirect()->route('talents.media.index')->with('status', 'Media deleted.');
    }
}
