@php
    $isEdit = $album->exists;
    $admin = $themeAppearance['admin_texts'];
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['form_title_label'] }}</label>
        <input name="title" value="{{ old('title', $album->title) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['form_slug_label'] }}</label>
        <input name="slug" value="{{ old('slug', $album->slug) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['form_artist_label'] }}</label>
        <input name="artist" value="{{ old('artist', $album->artist) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['form_released_at_label'] }}</label>
        <input type="date" name="released_at" value="{{ old('released_at', optional($album->released_at)->format('Y-m-d')) }}" class="lucille-product-field w-full">
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['cover_image_path_label'] }}</label>
        <input name="cover_image" value="{{ old('cover_image', $album->cover_image) }}" class="lucille-product-field w-full">
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['cover_image_file_label'] }}</label>
        <input type="file" name="cover_image_file" class="block w-full text-sm text-[#7b7b7b]">
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['summary_label'] }}</label>
        <textarea name="summary" rows="5" class="lucille-product-field w-full">{{ old('summary', $album->summary) }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['tracks_label'] }}</label>
        <textarea name="tracks_text" rows="6" class="lucille-product-field w-full" placeholder="{{ $admin['tracks_placeholder'] }}">{{ old('tracks_text', $tracksText ?? '') }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['buy_links_label'] }}</label>
        <textarea name="buy_links_text" rows="5" class="lucille-product-field w-full" placeholder="{{ $admin['buy_links_placeholder'] }}">{{ old('buy_links_text', $buyLinksText ?? '') }}</textarea>
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit" class="lucille-button-solid">{{ $isEdit ? $admin['edit_album'] : $admin['new_album'] }}</button>
    <a href="{{ route('admin.albums.index') }}" class="lucille-button">{{ $admin['back_to_albums'] }}</a>
</div>
