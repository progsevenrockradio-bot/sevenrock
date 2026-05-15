@php
    $isEdit = $product->exists;
    $admin = $themeAppearance['admin_texts'];
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['form_title_label'] }}</label>
        <input name="title" value="{{ old('title', $product->title) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['form_slug_label'] }}</label>
        <input name="slug" value="{{ old('slug', $product->slug) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['price_label'] }}</label>
        <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $product->price) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['regular_price_label'] }}</label>
        <input type="number" step="0.01" min="0" name="regular_price" value="{{ old('regular_price', $product->regular_price) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['category_field_label'] }}</label>
        <input name="category" value="{{ old('category', $product->category) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['sort_order_label'] }}</label>
        <input type="number" step="1" min="0" name="sort_order" value="{{ old('sort_order', $product->sort_order ?? 0) }}" class="lucille-product-field w-full">
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['image_path_label'] }}</label>
        <input name="image" value="{{ old('image', $product->image) }}" class="lucille-product-field w-full">
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['image_file_label'] }}</label>
        <input type="file" name="image_file" class="block w-full text-sm text-[#7b7b7b]">
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['description_label'] }}</label>
        <textarea name="description" rows="6" class="lucille-product-field w-full">{{ old('description', $product->description) }}</textarea>
    </div>
    <label class="flex items-center gap-3 text-sm text-[#dcdcdc] md:col-span-2">
        <input type="checkbox" name="is_sale" value="1" @checked(old('is_sale', $product->is_sale ?? false))>
        {{ $admin['sale_label'] }}
    </label>
    <label class="flex items-center gap-3 text-sm text-[#dcdcdc] md:col-span-2">
        <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $product->is_published ?? true))>
        {{ $admin['published_state_label'] }}
    </label>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit" class="lucille-button-solid">{{ $isEdit ? $admin['edit_product'] : $admin['new_product'] }}</button>
    <a href="{{ route('admin.products.index') }}" class="lucille-button">{{ $admin['back_to_products'] }}</a>
</div>
