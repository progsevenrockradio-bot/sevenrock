<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RadioArtist;
use App\Support\BandProfileMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Str;

class BandProfileController extends Controller
{
    public function index(): View
    {
        return view('admin.radio-artists.index', [
            'bandProfiles' => RadioArtist::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.radio-artists.create', [
            'bandProfile' => new RadioArtist([
                'featured_facts' => [],
                'official_links' => [],
                'related_artists' => [],
            ]),
            'featuredFactsText' => '',
            'officialLinksText' => '',
            'relatedArtistsText' => '',
            'labelsText' => '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        RadioArtist::query()->create($this->validated($request));

        return redirect()->route('admin.radio-artists.index')->with('status', 'Radio artist created.');
    }

    public function edit(RadioArtist $bandProfile): View
    {
        return view('admin.radio-artists.edit', [
            'bandProfile' => $bandProfile,
            'featuredFactsText' => $this->linesToText($bandProfile->featured_facts ?? []),
            'officialLinksText' => $this->linksToText($bandProfile->official_links ?? []),
            'relatedArtistsText' => implode("\n", array_map('strval', (array) ($bandProfile->related_artists ?? []))),
            'labelsText' => (string) ($bandProfile->labels ?? ''),
        ]);
    }

    public function update(Request $request, RadioArtist $bandProfile): RedirectResponse
    {
        $bandProfile->update($this->validated($request, $bandProfile->id));

        return redirect()->route('admin.radio-artists.index')->with('status', 'Radio artist updated.');
    }

    public function destroy(RadioArtist $bandProfile): RedirectResponse
    {
        $bandProfile->delete();

        return redirect()->route('admin.radio-artists.index')->with('status', 'Radio artist deleted.');
    }

    public function search(Request $request, BandProfileMatcher $matcher): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $profiles = $matcher->search((string) ($validated['q'] ?? ''), 12);

        return response()->json([
            'success' => true,
            'data' => [
                'results' => $profiles->map(function (RadioArtist $profile): array {
                    $summary = trim((string) ($profile->editorial_summary ?: $profile->biography ?: ''));

                    return [
                        'id' => $profile->id,
                        'text' => $profile->name,
                        'summary' => $summary !== '' ? Str::limit(strip_tags($summary), 120, '') : '',
                        'related_artists' => array_values(array_filter(array_map('strval', (array) ($profile->related_artists ?? [])))),
                    ];
                })->values()->all(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */

    public function autoGenerate(Request $request, RadioArtist $bandProfile): JsonResponse
    {
        $artist = trim((string) $bandProfile->name);
        if ($artist === '') {
            return response()->json([
                'success' => false,
                'message' => 'El artista no tiene nombre.',
            ]);
        }

        try {
            $aggregator = app(\App\Support\BandInfoAggregator::class);
            $data = $aggregator->aggregate($artist);

            if (empty($data['summary']) && empty($data['thumbnail'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró información para ' . $artist . ' en ninguna fuente.',
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $data['summary'] ?? '',
                    'biography' => $data['summary'] ?? '',
                    'thumbnail' => $data['thumbnail'] ?? '',
                    'country' => $data['country'] ?? '',
                    'genre' => $data['genre'] ?? '',
                    'members_count' => $data['members_count'],
                    'status' => $data['status'] ?? '',
                    'labels' => $data['labels'] ?? '',
                    'formed_year' => $data['formed_year'],
                    'formed_label' => $data['formed_label'] ?? '',
                    'facts' => $data['facts'] ?? [],
                    'social_links' => $data['social_links'] ?? [],
                    'source' => 'Seven Rock Radio',
                ],
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Auto-generate failed', [
                'artist' => $artist,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar información: ' . $e->getMessage(),
            ]);
        }
    }


        private function validated(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('radio_artists', 'name')->ignore($ignoreId)],
            'biography' => ['nullable', 'string'],
            'editorial_summary' => ['nullable', 'string'],
            'image_path' => ['nullable', 'string', 'max:2048'],
            'founded_date' => ['nullable', 'date'],
            'logo_path' => ['nullable', 'url', 'max:2048'],
            'country' => ['nullable', 'string', 'max:255'],
            'genre' => ['nullable', 'string', 'max:255'],
            'members_count' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'in:active,on_hold,disbanded,unknown'],
            'labels' => ['nullable', 'string'],
            'featured_facts_text' => ['nullable', 'string'],
            'official_links_text' => ['nullable', 'string'],
            'related_artists_text' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['members_count'] = $validated['members_count'] !== null ? (int) $validated['members_count'] : null;
        $validated['status'] = $validated['status'] !== '' ? $validated['status'] : null;
        $validated['featured_facts'] = $this->splitLines((string) ($validated['featured_facts_text'] ?? ''));
        $validated['official_links'] = $this->splitLinks((string) ($validated['official_links_text'] ?? ''));
        $validated['related_artists'] = $this->splitLines((string) ($validated['related_artists_text'] ?? ''));
        $validated['source'] = $validated['source'] ?: 'Seven Rock Radio';
        $validated['labels'] = trim((string) ($validated['labels'] ?? '')) !== '' ? trim((string) $validated['labels']) : null;

        unset($validated['featured_facts_text'], $validated['official_links_text'], $validated['related_artists_text']);

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

    /**
     * @param array<int, string> $lines
     */
    private function linesToText(array $lines): string
    {
        return implode("\n", array_map('strval', $lines));
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
