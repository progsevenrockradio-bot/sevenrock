<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function create(): View
    {
        return view('admin.events.create', [
            'event' => new Event([
                'starts_at' => now()->addWeek(),
                'ticket_label' => 'Tickets',
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateEvent($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);

        Event::query()->create($data);

        return redirect()->route('admin.events.index')->with('status', 'Event created.');
    }

    public function edit(Event $event): View
    {
        return view('admin.events.edit', compact('event'));
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $data = $this->validateEvent($request, $event->id);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);

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
        ]);

        $validated['starts_at'] = Carbon::parse($validated['starts_at']);
        $validated['ends_at'] = ! empty($validated['ends_at']) ? Carbon::parse($validated['ends_at']) : null;

        return $validated;
    }
}
