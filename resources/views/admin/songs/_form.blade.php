@php $isEdit = $song->exists; @endphp

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Slug</label>
        <input name="slug" value="{{ old('slug', $song->slug) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Published At</label>
        <input type="datetime-local" name="published_at" value="{{ old('published_at', optional($song->published_at)->format('Y-m-d\TH:i')) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Title</label>
        <input name="title" value="{{ old('title', $song->title) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Artist</label>
        <input name="artist" value="{{ old('artist', $song->artist) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Radio Artist</label>
        <div
            x-data="bandProfilePicker({
                searchUrl: @js(route('admin.radio-artists.search')),
                selectedId: @js(old('band_profile_id', $song->band_profile_id)),
                selectedLabel: @js(optional($selectedBandProfile)->name ?? ''),
                minLength: 3,
            })"
            class="relative"
        >
            <input type="hidden" name="band_profile_id" :value="selectedId">
            <div class="flex gap-2">
                <input
                    type="search"
                    x-model="query"
                    @input="onInput()"
                    @focus="focus()"
                    @keydown.arrow-down.prevent="move(1)"
                    @keydown.arrow-up.prevent="move(-1)"
                    @keydown.enter="handleEnter($event)"
                    @keydown.escape.prevent="open = false"
                    @blur="closeSoon()"
                    placeholder="Search radio artist"
                    autocomplete="off"
                    aria-autocomplete="list"
                    aria-controls="band-profile-picker-results"
                    :aria-expanded="open ? 'true' : 'false'"
                    :aria-activedescendant="activeIndex >= 0 ? `band-profile-picker-result-${results[activeIndex]?.id}` : null"
                    class="lucille-product-field w-full"
                >
                <button
                    type="button"
                    class="lucille-button whitespace-nowrap"
                    @click="clear()"
                    :disabled="! selectedId && ! query"
                >
                    Clear
                </button>
            </div>
            <p class="mt-2 text-xs uppercase tracking-[.18em] text-[#7b7b7b]" x-show="selectedId && selectedLabel" x-cloak>
                Selected: <span x-text="selectedLabel"></span>
            </p>
            <div
                x-cloak
                x-show="open"
                @mousedown.prevent
                class="absolute left-0 right-0 z-20 mt-2 max-h-80 overflow-hidden border border-[#2b2b2b] bg-[rgba(12,12,14,.98)] shadow-[0_18px_36px_rgba(0,0,0,.35)]"
                id="band-profile-picker-results"
                role="listbox"
            >
                <div class="flex items-center justify-between border-b border-[#202020] px-4 py-2 text-[11px] uppercase tracking-[.18em] text-[#9d9d9d]">
                    <span x-text="loading ? 'Searching' : (results.length ? results.length + ' matches' : 'No matches')"></span>
                    <span x-show="loading" x-cloak>Loading</span>
                </div>
                <div class="max-h-72 overflow-auto">
                    <template x-if="! loading && results.length === 0">
                        <div class="px-4 py-5 text-sm text-[#7b7b7b]">
                            No radio artist matches this search.
                        </div>
                    </template>
                    <template x-for="(result, index) in results" :key="result.id">
                    <button
                        type="button"
                        :id="`band-profile-picker-result-${result.id}`"
                        class="flex w-full flex-col gap-1 border-b border-[#202020] px-4 py-3 text-left transition-colors hover:bg-[rgba(229,15,79,.08)]"
                        :class="activeIndex === index ? 'bg-[rgba(229,15,79,.12)]' : ''"
                        @click="choose(result)"
                        role="option"
                        :aria-selected="activeIndex === index ? 'true' : 'false'"
                    >
                        <span class="font-display text-sm uppercase tracking-[.12em] text-white" x-text="result.text"></span>
                        <span class="text-xs text-[#9d9d9d]" x-text="result.summary || 'Radio artist'"></span>
                    </button>
                    </template>
                </div>
            </div>
        </div>
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Album</label>
        <input name="album" value="{{ old('album', $song->album) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Program</label>
        <select name="program_id" class="lucille-product-field w-full">
            <option value="">-- none --</option>
            @foreach ($programs as $program)
                <option value="{{ $program->id }}" @selected((string) old('program_id', $song->program_id) === (string) $program->id)>{{ $program->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Duration Seconds</label>
        <input type="number" name="duration_seconds" min="0" value="{{ old('duration_seconds', $song->duration_seconds) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Sort Order</label>
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $song->sort_order) }}" class="lucille-product-field w-full">
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Audio URL</label>
        <input name="audio_url" value="{{ old('audio_url', $song->audio_url) }}" class="lucille-product-field w-full">
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Cover Image</label>
        <input name="cover_image" value="{{ old('cover_image', $song->cover_image) }}" class="lucille-product-field w-full">
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Lyrics</label>
        <textarea name="lyrics" rows="5" class="lucille-product-field w-full">{{ old('lyrics', $song->lyrics) }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Band Info</label>
        <textarea name="band_info" rows="5" class="lucille-product-field w-full">{{ old('band_info', $song->band_info) }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Band Members</label>
        <textarea name="band_members_text" rows="4" class="lucille-product-field w-full" placeholder="One member per line">{{ old('band_members_text', $bandMembersText ?? '') }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Social Links</label>
        <textarea name="social_links_text" rows="4" class="lucille-product-field w-full" placeholder="Label|https://example.com">{{ old('social_links_text', $socialLinksText ?? '') }}</textarea>
    </div>
    <div class="md:col-span-2 flex items-center gap-3">
        <input type="checkbox" name="is_live" value="1" @checked(old('is_live', $song->is_live)) class="h-4 w-4">
        <label class="text-sm text-[#7b7b7b]">Live track</label>
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit" class="lucille-button-solid">{{ $isEdit ? 'Update Song' : 'Create Song' }}</button>
    <a href="{{ route('admin.songs.index') }}" class="lucille-button">Back to Songs</a>
</div>
