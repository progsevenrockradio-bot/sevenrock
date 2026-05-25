<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RadioArtist;
use App\Models\Song;
use App\Models\Program;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SongController extends Controller
{
    public function index(): View
    {
        return view('admin.songs.index', [
            'songs' => Song::query()->with(['program', 'bandProfile'])->orderByDesc('published_at')->orderBy('artist')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $selectedBandProfile = $this->selectedBandProfile($request);

        return view('admin.songs.create', [
            'song' => new Song([
                'published_at' => now(),
                'band_members' => [],
                'social_links' => [],
                'is_live' => false,
            ]),
            'selectedBandProfile' => $selectedBandProfile,
            'programs' => Program::query()->active()->latestEditorial()->get(),
            'bandMembersText' => '',
            'socialLinksText' => '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Song::query()->create($this->validated($request));

        return redirect()->route('admin.songs.index')->with('status', 'Song created.');
    }

    public function edit(Request $request, Song $song): View
    {
        $selectedBandProfile = $this->selectedBandProfile($request, $song);

        return view('admin.songs.edit', [
            'song' => $song->load(['program', 'bandProfile']),
            'selectedBandProfile' => $selectedBandProfile,
            'programs' => Program::query()->active()->latestEditorial()->get(),
            'bandMembersText' => implode("\n", array_map('strval', $song->band_members ?? [])),
            'socialLinksText' => $this->linksToText($song->social_links ?? []),
        ]);
    }

    public function update(Request $request, Song $song): RedirectResponse
    {
        $song->update($this->validated($request, $song->id));

        return redirect()->route('admin.songs.index')->with('status', 'Song updated.');
    }

    public function destroy(Song $song): RedirectResponse
    {
        $song->delete();

        return redirect()->route('admin.songs.index')->with('status', 'Song deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('songs', 'slug')->ignore($ignoreId)],
            'title' => ['required', 'string', 'max:255'],
            'artist' => ['required', 'string', 'max:255'],
            'band_profile_id' => ['nullable', 'integer', 'exists:radio_artists,id'],
            'album' => ['nullable', 'string', 'max:255'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'audio_url' => ['nullable', 'string', 'max:2048'],
            'cover_image' => ['nullable', 'string', 'max:2048'],
            'lyrics' => ['nullable', 'string'],
            'band_info' => ['nullable', 'string'],
            'band_members_text' => ['nullable', 'string'],
            'social_links_text' => ['nullable', 'string'],
            'program_id' => ['nullable', 'integer', 'exists:radio_programs,id'],
            'is_live' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['title'] . '-' . $validated['artist']);
        $validated['band_members'] = $this->splitLines((string) ($validated['band_members_text'] ?? ''));
        $validated['social_links'] = $this->splitLinks((string) ($validated['social_links_text'] ?? ''));
        $validated['is_live'] = $request->boolean('is_live', false);
        $validated['published_at'] = ! empty($validated['published_at']) ? Carbon::parse($validated['published_at']) : null;

        unset($validated['band_members_text'], $validated['social_links_text']);

        return $validated;
    }

    /**
     * @return array<int, string>
     */
    private function splitLines(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', trim($text)) ?: [])));
    }

    /**
     * @return array<int, array{label:string,url:string}>
     */
    private function splitLinks(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];
        $links = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            [$label, $url] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            if ($url === '') {
                $url = $label;
                $label = '';
            }

            if ($url === '') {
                continue;
            }

            $links[] = [
                'label' => $label,
                'url' => $url,
            ];
        }

        return $links;
    }

    private function selectedBandProfile(Request $request, ?Song $song = null): ?RadioArtist
    {
        $selectedId = $request->old('band_profile_id');
        if (is_numeric($selectedId)) {
            return RadioArtist::query()->find((int) $selectedId);
        }

        if ($song?->band_profile_id) {
            return $song->bandProfile;
        }

        return null;
    }

    /**
     * @param array<int, array{label?:string,url?:string}> $links
     */
    private function linksToText(array $links): string
    {
        return collect($links)
            ->map(fn (array $link): string => trim(($link['label'] ?? '') . '|' . ($link['url'] ?? '')))
            ->implode("\n");
    }
}
