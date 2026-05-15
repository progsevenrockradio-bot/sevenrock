<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Str;

class VideoController extends Controller
{
    public function index(): View
    {
        return view('admin.videos.index', [
            'videos' => Video::query()->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.videos.create', [
            'video' => new Video(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $data['image'] = $this->resolveImage($request, null, $data['image'] ?? null, 'catalog/videos');

        Video::query()->create($data);

        return redirect()->route('admin.videos.index')->with('status', 'Video created.');
    }

    public function edit(Video $video): View
    {
        return view('admin.videos.edit', compact('video'));
    }

    public function update(Request $request, Video $video): RedirectResponse
    {
        $data = $this->validated($request, $video->id);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $data['image'] = $this->resolveImage($request, $video->image, $data['image'] ?? null, 'catalog/videos');

        $video->update($data);

        return redirect()->route('admin.videos.index')->with('status', 'Video updated.');
    }

    public function destroy(Video $video): RedirectResponse
    {
        $this->deleteUploaded($video->image);
        $video->delete();

        return redirect()->route('admin.videos.index')->with('status', 'Video deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return tap($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('videos', 'slug')->ignore($ignoreId),
            ],
            'image' => ['nullable', 'string', 'max:2048', 'required_without:image_file'],
            'image_file' => ['nullable', 'image', 'max:6144'],
            'youtube_url' => ['nullable', 'url', 'max:2048'],
            'summary' => ['nullable', 'string'],
        ]), function (array &$validated): void {
            unset($validated['image_file']);
        });
    }

    private function resolveImage(Request $request, ?string $current, ?string $input, string $directory): string
    {
        if ($request->hasFile('image_file')) {
            $this->deleteUploaded($current);

            return $request->file('image_file')->store($directory, 'public');
        }

        return trim((string) $input) !== '' ? trim((string) $input) : (string) $current;
    }

    private function deleteUploaded(?string $path): void
    {
        if (! $path || str_starts_with($path, 'assets/') || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
