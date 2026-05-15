@php
    $isEdit = $post->exists;
    $admin = $themeAppearance['admin_texts'];
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['title_label'] }}</label>
        <input name="title" value="{{ old('title', $post->title) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['slug_label'] }}</label>
        <input name="slug" value="{{ old('slug', $post->slug) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['author_label'] }}</label>
        <input name="author" value="{{ old('author', $post->author) }}" class="lucille-product-field w-full">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['published_at_label'] }}</label>
        <input type="datetime-local" name="published_at" value="{{ old('published_at', optional($post->published_at)->format('Y-m-d\TH:i')) }}" class="lucille-product-field w-full">
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['excerpt_label'] }}</label>
        <textarea name="excerpt" rows="4" class="lucille-product-field w-full">{{ old('excerpt', $post->excerpt) }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['content_paragraphs_label'] }}</label>
        <textarea name="content_text" rows="8" class="lucille-product-field w-full" placeholder="Paragraph 1&#10;Paragraph 2">{{ old('content_text', $contentText ?? '') }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['quote_label'] }}</label>
        <textarea name="quote" rows="3" class="lucille-product-field w-full">{{ old('quote', $post->quote) }}</textarea>
    </div>
    <div class="md:col-span-2 border border-[#2b2b2b] bg-[rgba(0,0,0,.14)] p-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">{{ $admin['post_taxonomy'] }}</h3>
                <p class="mt-2 text-sm text-[#7b7b7b]">{{ $admin['taxonomy_description'] }}</p>
            </div>
        </div>
        <div class="mt-5 grid gap-5 md:grid-cols-2">
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['categories_label'] }}</label>
                <input name="categories_text" value="{{ old('categories_text', $categoriesText ?? '') }}" class="lucille-product-field w-full" placeholder="Music, Discussion">
            </div>
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['tags_label'] }}</label>
                <input name="tags_text" value="{{ old('tags_text', $tagsText ?? '') }}" class="lucille-product-field w-full" placeholder="news, live, music">
            </div>
        </div>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['featured_image_path_label'] }}</label>
        <input name="featured_image" value="{{ old('featured_image', $post->featured_image) }}" class="lucille-product-field w-full">
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['featured_image_file_label'] }}</label>
        <input type="file" name="featured_image_file" class="block w-full text-sm text-[#7b7b7b]">
    </div>
    <label class="flex items-center gap-3 text-sm text-[#dcdcdc] md:col-span-2">
        <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $post->is_published ?? true))>
        {{ $admin['published_label'] }}
    </label>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit" class="lucille-button-solid">{{ $isEdit ? $admin['edit_post'] : $admin['new_post'] }}</button>
    <a href="{{ route('admin.posts.index') }}" class="lucille-button">{{ $admin['back_to_posts'] }}</a>
</div>
