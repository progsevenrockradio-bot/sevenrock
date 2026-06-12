<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\RadioArtist;
use App\Services\FileUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $agency = Auth::guard('agency')->user();
        $bandsCount = $agency->radioArtists()->count();

        return view('agency.dashboard', [
            'agency' => $agency,
            'bandsCount' => $bandsCount,
            'bands' => $agency->radioArtists()->limit(5)->latest()->get(),
        ]);
    }

    public function profile(): View
    {
        return view('agency.profile', [
            'agency' => Auth::guard('agency')->user(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $agency = Auth::guard('agency')->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('agencies', 'email')->ignore($agency->id)],
            'website_url' => ['nullable', 'url', 'max:2048'],
            'logo_file' => ['nullable', 'image', 'max:4096'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'website_url' => $validated['website_url'],
        ];

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        if ($request->hasFile('logo_file')) {
            if ($agency->logo_path) {
                app(FileUploadService::class)->delete($agency->logo_path);
            }
            $data['logo_path'] = app(FileUploadService::class)->upload($request->file('logo_file'), 'catalog/agencies/logos')['url'];
        }

        $agency->update($data);

        return redirect()->route('agency.profile')->with('status', 'Perfil de la agencia actualizado con éxito.');
    }

    public function bands(): View
    {
        $agency = Auth::guard('agency')->user();

        return view('agency.bands.index', [
            'bands' => $agency->radioArtists()->orderBy('name')->get(),
        ]);
    }

    public function createBand(): View
    {
        return view('agency.bands.create', [
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

    public function storeBand(Request $request): RedirectResponse
    {
        $agency = Auth::guard('agency')->user();
        
        $data = $this->validateBand($request);
        $data['agency_id'] = $agency->id; // Vincular automáticamente a la agencia

        RadioArtist::query()->create($data);

        return redirect()->route('agency.bands')->with('status', 'Banda registrada y vinculada a tu agencia.');
    }

    public function editBand(RadioArtist $band): View
    {
        $agency = Auth::guard('agency')->user();
        
        // Evitar que editen bandas de otra agencia
        if ($band->agency_id !== $agency->id) {
            abort(403, 'No tienes permiso para editar esta banda.');
        }

        return view('agency.bands.edit', [
            'bandProfile' => $band,
            'featuredFactsText' => $this->linesToText($band->featured_facts ?? []),
            'officialLinksText' => $this->linksToText($band->official_links ?? []),
            'relatedArtistsText' => implode("\n", array_map('strval', (array) ($band->related_artists ?? []))),
            'labelsText' => (string) ($band->labels ?? ''),
        ]);
    }

    public function updateBand(Request $request, RadioArtist $band): RedirectResponse
    {
        $agency = Auth::guard('agency')->user();

        if ($band->agency_id !== $agency->id) {
            abort(403, 'No tienes permiso para editar esta banda.');
        }

        $data = $this->validateBand($request, $band->id);
        $band->update($data);

        return redirect()->route('agency.bands')->with('status', 'Perfil de la banda actualizado con éxito.');
    }

    private function validateBand(Request $request, ?int $ignoreId = null): array
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
        ]);

        $validated['members_count'] = $validated['members_count'] !== null ? (int) $validated['members_count'] : null;
        $validated['status'] = $validated['status'] !== '' ? $validated['status'] : null;
        $validated['featured_facts'] = $this->splitLines((string) ($validated['featured_facts_text'] ?? ''));
        $validated['official_links'] = $this->splitLinks((string) ($validated['official_links_text'] ?? ''));
        $validated['related_artists'] = $this->splitLines((string) ($validated['related_artists_text'] ?? ''));
        $validated['source'] = 'Seven Rock Radio';
        $validated['labels'] = trim((string) ($validated['labels'] ?? '')) !== '' ? trim((string) $validated['labels']) : null;

        unset($validated['featured_facts_text'], $validated['official_links_text'], $validated['related_artists_text']);

        return $validated;
    }

    private function splitLines(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', trim($text)) ?: [])));
    }

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

    private function linesToText(array $lines): string
    {
        return implode("\n", array_map('strval', $lines));
    }

    private function linksToText(array $links): string
    {
        return collect($links)
            ->map(fn (array $link): string => trim(($link['label'] ?? '') . '|' . ($link['url'] ?? '')))
            ->implode("\n");
    }
}
