<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\FileUploadService;
use App\Support\PublicMediaUrl;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Str;

class EventController extends Controller
{
    public function index(): View
    {
        return view('admin.events.index', [
            'events' => Event::query()->orderBy('starts_at')->get(),
        ]);
    }

    public function preview(): RedirectResponse
    {
        $slug = Event::query()->orderBy('starts_at')->value('slug') ?: 'rockness-festival';

        return redirect()->route('events.single', ['slug' => $slug]);
    }

    public function create(): View
    {
        return view('admin.events.create', [
            'event' => new Event([
                'starts_at' => now()->addWeek(),
                'ticket_label' => 'Tickets',
                'categories' => [],
                'content' => [],
            ]),
            'posterPreview' => '',
            'categoriesText' => '',
            'contentText' => '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateEvent($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $data['poster'] = $this->resolvePoster($request, null, $data['poster'] ?? null);

        Event::query()->create($data);

        return redirect()->route('admin.events.index')->with('status', 'Event created.');
    }

    public function edit(Event $event): View
    {
        return view('admin.events.edit', [
            'event' => $event,
            'posterPreview' => PublicMediaUrl::normalizePublicUrl($event->poster),
            'categoriesText' => implode(', ', $event->categories ?? []),
            'contentText' => implode("\n\n", $event->content ?? []),
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $data = $this->validateEvent($request, $event->id);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $data['poster'] = $this->resolvePoster($request, $event->poster, $data['poster'] ?? null);

        $event->update($data);

        return redirect()->route('admin.events.index')->with('status', 'Event updated.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $event->delete();

        return redirect()->route('admin.events.index')->with('status', 'Event deleted.');
    }

    private function validateEvent(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('events', 'slug')->ignore($ignoreId),
            ],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'venue' => ['nullable', 'string', 'max:255'],
            'ticket_url' => ['nullable', 'url', 'max:2048'],
            'ticket_label' => ['nullable', 'string', 'max:80'],
            'poster' => ['nullable', 'string', 'max:2048', 'required_without:poster_file'],
            'poster_file' => ['nullable', 'image', 'max:6144'],
            'venue_url' => ['nullable', 'url', 'max:2048'],
            'facebook_url' => ['nullable', 'url', 'max:2048'],
            'embed_url' => ['nullable', 'url', 'max:4096'],
            'map_url' => ['nullable', 'url', 'max:4096'],
            'categories_text' => ['nullable', 'string'],
            'content_text' => ['nullable', 'string'],
        ]);

        $validated['starts_at'] = Carbon::parse($validated['starts_at']);
        $validated['ends_at'] = ! empty($validated['ends_at']) ? Carbon::parse($validated['ends_at']) : null;
        $validated['categories'] = $this->splitTerms((string) ($validated['categories_text'] ?? ''));
        $validated['content'] = $this->splitParagraphs((string) ($validated['content_text'] ?? ''));

        unset($validated['categories_text'], $validated['content_text'], $validated['poster_file']);

        return $validated;
    }

    private function resolvePoster(Request $request, ?string $current, ?string $input): string
    {
        if ($request->hasFile('poster_file')) {
            $this->deleteUploaded($current);

            return app(FileUploadService::class)->upload($request->file('poster_file'), 'catalog/events')['key'];
        }

        $value = trim((string) $input);

        return $value !== '' ? $value : (string) $current;
    }

    private function deleteUploaded(?string $path): void
    {
        if (! $path || str_starts_with($path, 'assets/') || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        app(FileUploadService::class)->delete($path);
    }

    /**
     * @return array<int, string>
     */
    private function splitTerms(string $text): array
    {
        $terms = preg_split('/[\r\n,]+/', $text) ?: [];

        return array_values(array_filter(array_map('trim', $terms)));
    }

    /**
     * @return array<int, string>
     */
    private function splitParagraphs(string $text): array
    {
        $paragraphs = preg_split('/\R{2,}/', trim($text)) ?: [];

        return array_values(array_filter(array_map(static function (string $paragraph): string {
            return trim(preg_replace('/\s+/u', ' ', $paragraph) ?? '');
        }, $paragraphs)));
    }
}
