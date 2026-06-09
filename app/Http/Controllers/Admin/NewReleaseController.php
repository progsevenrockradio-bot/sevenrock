<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewRelease;
use App\Models\RadioArtist;
use App\Services\FileUploadService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NewReleaseController extends Controller
{
    public function index(): View
    {
        return view('admin.new-releases.index', [
            'newReleases' => NewRelease::query()
                ->with('radioArtist')
                ->orderByDesc('released_at')
                ->orderBy('title')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.new-releases.create', [
            'newRelease' => new NewRelease([
                'released_at' => now(),
                'is_active' => true,
            ]),
            'radioArtists' => RadioArtist::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title'] . '-' . $data['artist_name']);
        
        $data['cover_image'] = $this->resolveFile(
            $request,
            'cover_image_file',
            $data['cover_image'] ?? null,
            null,
            'catalog/releases/covers'
        );

        $data['audio_path'] = $this->resolveFile(
            $request,
            'audio_file',
            $data['audio_path'] ?? null,
            null,
            'catalog/releases/audio'
        );

        NewRelease::query()->create($data);

        return redirect()->route('admin.new-releases.index')->with('status', 'Lanzamiento creado con éxito.');
    }

    public function edit(NewRelease $newRelease): View
    {
        return view('admin.new-releases.edit', [
            'newRelease' => $newRelease,
            'radioArtists' => RadioArtist::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, NewRelease $newRelease): RedirectResponse
    {
        $data = $this->validated($request, $newRelease->id);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title'] . '-' . $data['artist_name']);

        $data['cover_image'] = $this->resolveFile(
            $request,
            'cover_image_file',
            $data['cover_image'] ?? null,
            $newRelease->cover_image,
            'catalog/releases/covers'
        );

        $data['audio_path'] = $this->resolveFile(
            $request,
            'audio_file',
            $data['audio_path'] ?? null,
            $newRelease->audio_path,
            'catalog/releases/audio'
        );

        $newRelease->update($data);

        return redirect()->route('admin.new-releases.index')->with('status', 'Lanzamiento actualizado con éxito.');
    }

    public function destroy(NewRelease $newRelease): RedirectResponse
    {
        $this->deleteUploaded($newRelease->cover_image);
        $this->deleteUploaded($newRelease->audio_path);
        $newRelease->delete();

        return redirect()->route('admin.new-releases.index')->with('status', 'Lanzamiento eliminado con éxito.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('new_releases', 'slug')->ignore($ignoreId),
            ],
            'artist_name' => ['required', 'string', 'max:255'],
            'radio_artist_id' => ['nullable', 'integer', 'exists:radio_artists,id'],
            'released_at' => ['nullable', 'date'],
            'cover_image' => ['nullable', 'string', 'max:2048', 'required_without:cover_image_file'],
            'cover_image_file' => ['nullable', 'image', 'max:6144'],
            'audio_path' => ['nullable', 'string', 'max:2048', 'required_without:audio_file'],
            'audio_file' => ['nullable', 'file', 'mimes:mp3,wav,ogg,mpga,mpeg', 'max:20480'],
            'youtube_url' => ['nullable', 'url', 'max:2048'],
            'spotify_url' => ['nullable', 'url', 'max:2048'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['released_at'] = ! empty($validated['released_at']) ? Carbon::parse($validated['released_at']) : null;
        $validated['is_active'] = $request->boolean('is_active', true);

        unset($validated['cover_image_file'], $validated['audio_file']);

        return $validated;
    }

    private function resolveFile(Request $request, string $fileKey, ?string $inputPath, ?string $currentPath, string $directory): ?string
    {
        if ($request->hasFile($fileKey)) {
            $this->deleteUploaded($currentPath);

            return app(FileUploadService::class)->upload($request->file($fileKey), $directory)['url'];
        }

        return trim((string) $inputPath) !== '' ? trim((string) $inputPath) : $currentPath;
    }

    private function deleteUploaded(?string $path): void
    {
        if (! $path || str_starts_with($path, 'assets/') || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        app(FileUploadService::class)->delete($path);
    }
}
