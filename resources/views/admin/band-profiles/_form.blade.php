@php $isEdit = $bandProfile->exists; @endphp

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Name</label>
        <input name="name" value="{{ old('name', $bandProfile->name) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Source</label>
        <input name="source" value="{{ old('source', $bandProfile->source) }}" class="lucille-product-field w-full">
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Image Path</label>
        <input name="image_path" value="{{ old('image_path', $bandProfile->image_path) }}" class="lucille-product-field w-full">
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Biography</label>
        <textarea name="biography" rows="5" class="lucille-product-field w-full">{{ old('biography', $bandProfile->biography) }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Editorial Summary</label>
        <textarea name="editorial_summary" rows="5" class="lucille-product-field w-full">{{ old('editorial_summary', $bandProfile->editorial_summary) }}</textarea>
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
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit" class="lucille-button-solid">{{ $isEdit ? 'Update Band Profile' : 'Create Band Profile' }}</button>
    <a href="{{ route('admin.band-profiles.index') }}" class="lucille-button">Back to Band Profiles</a>
</div>
