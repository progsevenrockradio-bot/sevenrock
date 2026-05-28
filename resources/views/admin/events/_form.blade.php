@php
    $isEdit = $event->exists;
    $admin = $themeAppearance['admin_texts'];
    $categoriesValue = old('categories_text', $categoriesText ?? implode(', ', $event->categories ?? []));
    $contentValue = old('content_text', $contentText ?? implode("\n\n", $event->content ?? []));
    $posterValue = old('poster', $event->poster);
@endphp

<div class="grid gap-6 xl:grid-cols-[1.05fr_.95fr]">
    <div class="space-y-6">
        <section class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] p-6">
            <div class="mb-5">
                <h2 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">Event data</h2>
                <p class="mt-1 text-sm text-[#7b7b7b]">Core information used on the listing and the public event page.</p>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['form_title_label'] }}</label>
                    <input name="title" value="{{ old('title', $event->title) }}" class="lucille-product-field w-full">
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['form_slug_label'] }}</label>
                    <input name="slug" value="{{ old('slug', $event->slug) }}" class="lucille-product-field w-full">
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['ticket_label_label'] }}</label>
                    <input name="ticket_label" value="{{ old('ticket_label', $event->ticket_label) }}" class="lucille-product-field w-full" placeholder="Tickets">
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
                    <input name="location" value="{{ old('location', $event->location) }}" class="lucille-product-field w-full" placeholder="Loch Ness, UK">
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['venue_label'] }}</label>
                    <input name="venue" value="{{ old('venue', $event->venue) }}" class="lucille-product-field w-full" placeholder="Rockness Festival">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Categories</label>
                    <input
                        name="categories_text"
                        value="{{ $categoriesValue }}"
                        class="lucille-product-field w-full"
                        placeholder="Guest Appearance, Music Festivals"
                    >
                    <p class="mt-2 text-xs uppercase tracking-[.14em] text-[#7b7b7b]">Separate with commas or line breaks.</p>
                </div>
            </div>
        </section>

        <section class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] p-6">
            <div class="mb-5">
                <h2 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">Event content</h2>
                <p class="mt-1 text-sm text-[#7b7b7b]">Each paragraph is stored separately so the public page can render the long-form event layout.</p>
            </div>

            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Description paragraphs</label>
            <textarea
                name="content_text"
                rows="12"
                class="lucille-product-field w-full font-body text-[15px] leading-7"
                placeholder="Paste one paragraph per block, leaving a blank line between paragraphs."
            >{{ $contentValue }}</textarea>
        </section>
    </div>

    <div class="space-y-6">
        <section class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] p-6">
            <div class="mb-5">
                <h2 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">Featured media</h2>
                <p class="mt-1 text-sm text-[#7b7b7b]">Poster, video and map data live here in one card for a faster event workflow.</p>
            </div>

            <div class="grid gap-5 lg:grid-cols-[1fr_.9fr]">
                <div class="space-y-5">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Poster URL or path</label>
                        <input name="poster" value="{{ $posterValue }}" class="lucille-product-field w-full" placeholder="assets/lucille/ozzfest_poster.jpg">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Poster image file</label>
                        <input type="file" name="poster_file" class="block w-full text-sm text-[#7b7b7b]">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Embed URL</label>
                        <input name="embed_url" value="{{ old('embed_url', $event->embed_url) }}" class="lucille-product-field w-full" placeholder="https://www.youtube.com/embed/...">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Map URL</label>
                        <input name="map_url" value="{{ old('map_url', $event->map_url) }}" class="lucille-product-field w-full" placeholder="https://www.google.com/maps/embed?...">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['ticket_url_label'] }}</label>
                        <input name="ticket_url" value="{{ old('ticket_url', $event->ticket_url) }}" class="lucille-product-field w-full" placeholder="https://...">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Venue URL</label>
                        <input name="venue_url" value="{{ old('venue_url', $event->venue_url) }}" class="lucille-product-field w-full" placeholder="https://...">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Facebook URL</label>
                        <input name="facebook_url" value="{{ old('facebook_url', $event->facebook_url) }}" class="lucille-product-field w-full" placeholder="https://www.facebook.com/...">
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.28)] p-3">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Poster preview</span>
                            <span class="text-[10px] uppercase tracking-[.18em] text-[#7b7b7b]">Live</span>
                        </div>
                        @if (! empty($posterPreview))
                            <img src="{{ $posterPreview }}" alt="Event poster preview" class="w-full border border-[#2b2b2b] object-cover" loading="lazy">
                        @else
                            <div class="flex min-h-[240px] items-center justify-center border border-dashed border-[#2b2b2b] text-xs uppercase tracking-[.18em] text-[#7b7b7b]">
                                No poster yet
                            </div>
                        @endif
                    </div>

                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.28)] p-4">
                        <p class="font-display text-xs uppercase tracking-[.18em] text-[#dcdcdc]">Featured media status</p>
                        <ul class="mt-3 space-y-2 text-sm text-[#7b7b7b]">
                            <li>Poster, embed and map are stored together for the single event page.</li>
                            <li>The public view renders the poster and video stacked on the right column.</li>
                            <li>The map stays below the two-column layout to match the reference composition.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <section class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] p-6">
            <div class="mb-5">
                <h2 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">Quick preview</h2>
                <p class="mt-1 text-sm text-[#7b7b7b]">A compact summary of the event card and page opening state.</p>
            </div>

            <div class="space-y-4">
                <div class="flex flex-wrap gap-2">
                    <span class="border border-[#2b2b2b] px-3 py-1 text-[11px] uppercase tracking-[.18em] text-[#dcdcdc]">{{ $event->starts_at?->format('d M Y') ?? 'Date' }}</span>
                    <span class="border border-[#2b2b2b] px-3 py-1 text-[11px] uppercase tracking-[.18em] text-[#dcdcdc]">{{ $event->starts_at?->format('g:i a') ?? 'Time' }}</span>
                </div>
                <div class="border border-[#2b2b2b] bg-black/30 p-4 text-sm text-[#7b7b7b]">
                    <p class="font-display text-[15px] uppercase tracking-[.12em] text-[#dcdcdc]">{{ $event->title ?: 'Event title' }}</p>
                    <p class="mt-2">This preview follows the same stacked editorial feel as the public event page.</p>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit" class="lucille-button-solid">{{ $isEdit ? $admin['edit_event'] : $admin['new_event'] }}</button>
    <a href="{{ route('admin.events.index') }}" class="lucille-button">{{ $admin['back_to_events'] }}</a>
</div>
