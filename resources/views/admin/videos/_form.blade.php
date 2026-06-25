@php
    $isEdit = $video->exists;
    $admin = $themeAppearance['admin_texts'];
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['form_title_label'] }}</label>
        <input name="title" id="title-input" value="{{ old('title', $video->title) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['form_slug_label'] }}</label>
        <input name="slug" id="slug-input" value="{{ old('slug', $video->slug) }}" class="lucille-product-field w-full">
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
    <div class="md:col-span-2 mt-2 flex items-center gap-3">
        <input type="hidden" name="is_featured" value="0">
        <input type="checkbox" name="is_featured" id="is_featured" value="1" @checked(old('is_featured', $video->is_featured)) class="h-4 w-4 rounded border-[#2b2b2b] bg-[#151515] text-[#c32720] focus:ring-[#c32720]">
        <label for="is_featured" class="font-display text-xs uppercase tracking-[.18em] text-[#dcdcdc]">{{ $admin['featured_label'] ?? 'Destacado (Mostrar en Inicio)' }}</label>
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit" class="lucille-button-solid">{{ $isEdit ? $admin['edit_video'] : $admin['new_video'] }}</button>
    <a href="{{ route('admin.videos.index') }}" class="lucille-button">{{ $admin['back_to_videos'] }}</a>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const titleInput = document.getElementById('title-input');
        const slugInput = document.getElementById('slug-input');

        if (titleInput && slugInput) {
            titleInput.addEventListener('input', function () {
                let slug = titleInput.value.toString().toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '') // remove accent marks
                    .replace(/[^a-z0-9 -]/g, '')    // remove non-alphanumeric except space and dash
                    .replace(/\s+/g, '-')           // replace spaces with single dash
                    .replace(/-+/g, '-')            // collapse dashes
                    .replace(/^-+/, '')             // trim leading dash
                    .replace(/-+$/, '');            // trim trailing dash
                slugInput.value = slug;
            });
        }
    });
</script>
