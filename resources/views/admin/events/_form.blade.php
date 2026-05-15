@php
    $isEdit = $event->exists;
    $admin = $themeAppearance['admin_texts'];
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['form_title_label'] }}</label>
        <input name="title" value="{{ old('title', $event->title) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['form_slug_label'] }}</label>
        <input name="slug" value="{{ old('slug', $event->slug) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['starts_at_label'] }}</label>
        <input type="datetime-local" name="starts_at" value="{{ old('starts_at', optional($event->starts_at)->format('Y-m-d\TH:i')) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['ends_at_label'] }}</label>
        <input type="datetime-local" name="ends_at" value="{{ old('ends_at', optional($event->ends_at)->format('Y-m-d\TH:i')) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['location_label'] }}</label>
        <input name="location" value="{{ old('location', $event->location) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['venue_label'] }}</label>
        <input name="venue" value="{{ old('venue', $event->venue) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['ticket_url_label'] }}</label>
        <input name="ticket_url" value="{{ old('ticket_url', $event->ticket_url) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['ticket_label_label'] }}</label>
        <input name="ticket_label" value="{{ old('ticket_label', $event->ticket_label) }}" class="lucille-product-field w-full">
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit" class="lucille-button-solid">{{ $isEdit ? $admin['edit_event'] : $admin['new_event'] }}</button>
    <a href="{{ route('admin.events.index') }}" class="lucille-button">{{ $admin['back_to_events'] }}</a>
</div>
