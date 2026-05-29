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
        <select name="band_profile_id" class="lucille-product-field w-full">
            <option value="">-- none --</option>
            @foreach ($radioArtists as $artist)
                <option value="{{ $artist->id }}" @selected((string) old('band_profile_id', $song->band_profile_id) === (string) $artist->id)>{{ $artist->name }}</option>
            @endforeach
        </select>
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
