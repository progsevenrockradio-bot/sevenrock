<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Album;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Str;

class AlbumController extends Controller
{
    public function index(): View
    {
        return view('admin.albums.index', [
            'albums' => Album::query()->orderByDesc('released_at')->orderBy('title')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.albums.create', [
            'album' => new Album([
                'released_at' => now(),
                'tracks' => [],
                'buy_links' => [],
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $data['cover_image'] = $this->resolveCoverImage($request, null, $data['cover_image'] ?? null, 'catalog/albums');

        Album::query()->create($data);

        return redirect()->route('admin.albums.index')->with('status', 'Album created.');
    }

    public function edit(Album $album): View
    {
        return view('admin.albums.edit', [
            'album' => $album,
            'tracksText' => $this->tracksToText($album->tracks ?? []),
            'buyLinksText' => $this->buyLinksToText($album->buy_links ?? []),
        ]);
    }

    public function update(Request $request, Album $album): RedirectResponse
    {
        $data = $this->validated($request, $album->id);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $data['cover_image'] = $this->resolveCoverImage($request, $album->cover_image, $data['cover_image'] ?? null, 'catalog/albums');

        $album->update($data);

        return redirect()->route('admin.albums.index')->with('status', 'Album updated.');
    }

    public function destroy(Album $album): RedirectResponse
    {
        $this->deleteUploaded($album->cover_image);
        $album->delete();

        return redirect()->route('admin.albums.index')->with('status', 'Album deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('albums', 'slug')->ignore($ignoreId),
            ],
            'artist' => ['required', 'string', 'max:255'],
            'cover_image' => ['nullable', 'string', 'max:2048', 'required_without:cover_image_file'],
            'cover_image_file' => ['nullable', 'image', 'max:6144'],
            'summary' => ['nullable', 'string'],
            'released_at' => ['nullable', 'date'],
            'tracks_text' => ['nullable', 'string'],
            'buy_links_text' => ['nullable', 'string'],
        ]);

        $validated['released_at'] = ! empty($validated['released_at']) ? Carbon::parse($validated['released_at']) : null;
        $validated['tracks'] = $this->parseTracks((string) ($validated['tracks_text'] ?? ''));
        $validated['buy_links'] = $this->parseBuyLinks((string) ($validated['buy_links_text'] ?? ''));
        unset($validated['tracks_text'], $validated['buy_links_text'], $validated['cover_image_file']);

        return $validated;
    }

    private function resolveCoverImage(Request $request, ?string $current, ?string $input, string $directory): string
    {
        if ($request->hasFile('cover_image_file')) {
            $this->deleteUploaded($current);

            return $request->file('cover_image_file')->store($directory, 'public');
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

    /**
     * @return array<int, array{title:string,duration:string}>
     */
    private function parseTracks(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];
        $tracks = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            [$title, $duration] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            $tracks[] = [
                'title' => $title,
                'duration' => $duration,
            ];
        }

        return $tracks;
    }

    /**
     * @return array<int, array{label:string,url:string}>
     */
    private function parseBuyLinks(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];
        $links = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            [$label, $url] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            if ($label === '' || $url === '') {
                continue;
            }

            $links[] = [
                'label' => $label,
                'url' => $url,
            ];
        }

        return $links;
    }

    /**
     * @param array<int, array{title:string,duration?:string}> $tracks
     */
    private function tracksToText(array $tracks): string
    {
        return collect($tracks)
            ->map(fn (array $track): string => trim(($track['title'] ?? '') . '|' . ($track['duration'] ?? '')))
            ->implode("\n");
    }

    /**
     * @param array<int, array{label:string,url:string}> $links
     */
    private function buyLinksToText(array $links): string
    {
        return collect($links)
            ->map(fn (array $link): string => trim(($link['label'] ?? '') . '|' . ($link['url'] ?? '')))
            ->implode("\n");
    }
}
