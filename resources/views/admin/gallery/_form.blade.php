@php
    $isEdit = $image->exists;
    $admin = $themeAppearance['admin_texts'];
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['sort_order_label'] }}</label>
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $image->sort_order) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['caption_label'] }}</label>
        <input name="caption" value="{{ old('caption', $image->caption) }}" class="lucille-product-field w-full">
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['image_path_label'] }}</label>
        <input name="image" value="{{ old('image', $image->image) }}" class="lucille-product-field w-full">
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['image_file_label'] }}</label>
        <input type="file" name="image_file" class="block w-full text-sm text-[#7b7b7b]">
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit" class="lucille-button-solid">{{ $isEdit ? $admin['edit_image'] : $admin['new_image'] }}</button>
    <a href="{{ route('admin.gallery.index') }}" class="lucille-button">{{ $admin['back_to_gallery'] }}</a>
</div>
