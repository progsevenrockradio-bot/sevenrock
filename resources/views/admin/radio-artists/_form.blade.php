@php $isEdit = $bandProfile->exists; @endphp

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Name</label>
        <input name="name" value="{{ old('name', $bandProfile->name) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Image Path</label>
        <input name="image_path" value="{{ old('image_path', $bandProfile->image_path) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Founded Date</label>
        <input type="date" name="founded_date" value="{{ old('founded_date', $bandProfile->founded_date?->format('Y-m-d')) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Logo URL</label>
        <input name="logo_path" value="{{ old('logo_path', $bandProfile->logo_path) }}" class="lucille-product-field w-full" placeholder="https://example.com/logo.png">
        @if($bandProfile->logo_path)
            <div class="mt-2"><img src="{{ $bandProfile->logo_path }}" class="h-16 w-auto object-contain" alt="{{ $bandProfile->name }} logo"></div>
        @endif
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Country</label>
        <input name="country" value="{{ old('country', $bandProfile->country) }}" class="lucille-product-field w-full" placeholder="Ej: Germany">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Genre</label>
        <input name="genre" value="{{ old('genre', $bandProfile->genre) }}" class="lucille-product-field w-full" placeholder="Ej: Heavy Metal">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Members Count</label>
        <input type="number" name="members_count" value="{{ old('members_count', $bandProfile->members_count) }}" class="lucille-product-field w-full" min="0">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Status</label>
        <select name="status" class="lucille-product-field w-full">
            <option value="">Select...</option>
            <option value="active" {{ old('status', $bandProfile->status) === 'active' ? 'selected' : '' }}>Active</option>
            <option value="on_hold" {{ old('status', $bandProfile->status) === 'on_hold' ? 'selected' : '' }}>On Hold</option>
            <option value="disbanded" {{ old('status', $bandProfile->status) === 'disbanded' ? 'selected' : '' }}>Disbanded</option>
            <option value="unknown" {{ old('status', $bandProfile->status) === 'unknown' ? 'selected' : '' }}>Unknown</option>
        </select>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Biography</label>
        <textarea name="biography" rows="6" class="lucille-product-field w-full">{{ old('biography', $bandProfile->biography) }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Editorial Summary</label>
        <textarea name="editorial_summary" rows="6" class="lucille-product-field w-full">{{ old('editorial_summary', $bandProfile->editorial_summary) }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Featured Facts</label>
        <textarea name="featured_facts_text" rows="5" class="lucille-product-field w-full" placeholder="One fact per line">{{ old('featured_facts_text', $featuredFactsText ?? '') }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Official Links</label>
        <textarea name="official_links_text" rows="5" class="lucille-product-field w-full" placeholder="Label|https://example.com">{{ old('official_links_text', $officialLinksText ?? '') }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Related Artists</label>
        <textarea name="related_artists_text" rows="4" class="lucille-product-field w-full" placeholder="One artist per line">{{ old('related_artists_text', $relatedArtistsText ?? '') }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Source</label>
        <input name="source" value="{{ old('source', $bandProfile->source ?: 'Seven Rock Radio') }}" class="lucille-product-field w-full">
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Labels</label>
        <textarea name="labels" rows="3" class="lucille-product-field w-full" placeholder="One label per line">{{ old('labels', $labelsText ?? $bandProfile->labels) }}</textarea>
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit" class="lucille-button-solid">{{ $isEdit ? 'Update Radio Artist' : 'Create Radio Artist' }}</button>
    <a href="{{ route('admin.radio-artists.index') }}" class="lucille-button">Back to Radio Artists</a>
</div>
