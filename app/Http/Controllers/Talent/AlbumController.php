<?php

declare(strict_types=1);

namespace App\Http\Controllers\Talent;

use App\Http\Controllers\Controller;
use App\Models\TalentAlbum;
use App\Services\BackblazeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AlbumController extends Controller
{
    public function index(): View
    {
        $talent = Auth::guard('talent')->user();
        $albums = $talent ? $talent->albums()->latest()->get() : collect();

        return view('talentos.albums.index', [
            'talent' => $talent,
            'albums' => $albums,
        ]);
    }

    public function create(): View
    {
        return view('talentos.albums.create', [
            'talent' => Auth::guard('talent')->user(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $talent = Auth::guard('talent')->user();
        if (! $talent) {
            return redirect()->route('talents.login');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'release_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:5000'],
            'tracks_json' => ['nullable', 'string'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $slug = Str::slug($validated['title']);

        $tracks = [];
        if (! empty($validated['tracks_json'])) {
            $parsed = json_decode($validated['tracks_json'], true);
            if (is_array($parsed)) {
                $tracks = $parsed;
            }
        }

        $album = TalentAlbum::query()->create([
            'talent_id' => $talent->id,
            'title' => $validated['title'],
            'slug' => $slug,
            'release_date' => $validated['release_date'] ?? null,
            'description' => $validated['description'] ?? null,
            'tracks' => $tracks,
            'is_published' => (bool) ($validated['is_published'] ?? false),
        ]);

        if ($request->hasFile('cover_image')) {
            try {
                $folder = "talents/{$talent->id}/albums/{$album->id}";
                $upload = app(BackblazeService::class)->upload($request->file('cover_image'), $folder);
                $album->update([
                    'cover_image' => $upload['key'],
                    'cover_url' => $upload['url'],
                ]);
            } catch (\Throwable $e) {
                return redirect()->route('talents.albums.edit', $album->id)
                    ->with('warning', 'Álbum creado pero no se pudo subir la portada.');
            }
        }

        return redirect()->route('talents.albums.index')
            ->with('status', 'Álbum creado correctamente.');
    }

    public function edit(string $id): View
    {
        $talent = Auth::guard('talent')->user();
        $album = TalentAlbum::query()->whereKey((int) $id)->firstOrFail();

        if ((int) $album->talent_id !== (int) $talent->id) {
            abort(403);
        }

        return view('talentos.albums.edit', [
            'talent' => $talent,
            'album' => $album,
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $talent = Auth::guard('talent')->user();
        $album = TalentAlbum::query()->whereKey((int) $id)->firstOrFail();

        if ((int) $album->talent_id !== (int) $talent->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'release_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:5000'],
            'tracks_json' => ['nullable', 'string'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $tracks = [];
        if (! empty($validated['tracks_json'])) {
            $parsed = json_decode($validated['tracks_json'], true);
            if (is_array($parsed)) {
                $tracks = $parsed;
            }
        }

        $album->update([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']),
            'release_date' => $validated['release_date'] ?? null,
            'description' => $validated['description'] ?? null,
            'tracks' => $tracks,
            'is_published' => (bool) ($validated['is_published'] ?? false),
        ]);

        if ($request->hasFile('cover_image')) {
            try {
                if ($album->cover_image) {
                    app(BackblazeService::class)->delete($album->cover_image);
                }
                $folder = "talents/{$talent->id}/albums/{$album->id}";
                $upload = app(BackblazeService::class)->upload($request->file('cover_image'), $folder);
                $album->update([
                    'cover_image' => $upload['key'],
                    'cover_url' => $upload['url'],
                ]);
            } catch (\Throwable) {
                return redirect()->route('talents.albums.edit', $album->id)
                    ->with('warning', 'Álbum actualizado pero no se pudo subir la portada.');
            }
        }

        if ($request->boolean('remove_cover')) {
            if ($album->cover_image) {
                try {
                    app(BackblazeService::class)->delete($album->cover_image);
                } catch (\Throwable) {
                    //
                }
            }
            $album->update(['cover_image' => null, 'cover_url' => null]);
        }

        return redirect()->route('talents.albums.index')
            ->with('status', 'Álbum actualizado correctamente.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $talent = Auth::guard('talent')->user();
        $album = TalentAlbum::query()->whereKey((int) $id)->firstOrFail();

        if ((int) $album->talent_id !== (int) $talent->id) {
            abort(403);
        }

        if ($album->cover_image) {
            try {
                app(BackblazeService::class)->delete($album->cover_image);
            } catch (\Throwable) {
                //
            }
        }

        $album->delete();

        return redirect()->route('talents.albums.index')
            ->with('status', 'Álbum eliminado.');
    }
}
