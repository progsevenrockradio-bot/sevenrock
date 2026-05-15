@php
    $isEdit = $video->exists;
    $admin = $themeAppearance['admin_texts'];
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['form_title_label'] }}</label>
        <input name="title" value="{{ old('title', $video->title) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['form_slug_label'] }}</label>
        <input name="slug" value="{{ old('slug', $video->slug) }}" class="lucille-product-field w-full">
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['image_path_label'] }}</label>
        <input name="image" value="{{ old('image', $video->image) }}" class="lucille-product-field w-full">
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['image_file_label'] }}</label>
        <input type="file" name="image_file" class="block w-full text-sm text-[#7b7b7b]">
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['youtube_url_label'] }}</label>
        <input name="youtube_url" value="{{ old('youtube_url', $video->youtube_url) }}" class="lucille-product-field w-full">
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['summary_label'] }}</label>
        <textarea name="summary" rows="5" class="lucille-product-field w-full">{{ old('summary', $video->summary) }}</textarea>
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit" class="lucille-button-solid">{{ $isEdit ? $admin['edit_video'] : $admin['new_video'] }}</button>
    <a href="{{ route('admin.videos.index') }}" class="lucille-button">{{ $admin['back_to_videos'] }}</a>
</div>
