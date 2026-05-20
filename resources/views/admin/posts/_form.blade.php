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
    <div
        class="md:col-span-2"
        x-data="postContentEditor({
            initialBlocks: @js(old('content_text') ? \App\Support\WordPressContent::toEditorBlocks(old('content_text')) : ($editorBlocks ?? [])),
            uploadUrl: @js(route('admin.posts.media.store'))
        })"
        x-init="init()"
    >
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['content_paragraphs_label'] }}</label>
        <p class="mb-4 text-sm text-[#7b7b7b]">
            Editor por bloques para contenido importado de WordPress. Puedes reordenar, añadir o borrar bloques sin ver comentarios internos.
        </p>

        <textarea name="content_text" class="hidden" x-ref="contentText" x-model="serialized"></textarea>

        <div class="mb-4 flex flex-wrap gap-2 border border-[#2b2b2b] bg-[rgba(0,0,0,.14)] p-3">
            <button type="button" class="lucille-button" @click="addBlock('paragraph')">Paragraph</button>
            <button type="button" class="lucille-button" @click="addBlock('heading')">Heading</button>
            <button type="button" class="lucille-button" @click="addBlock('quote')">Quote</button>
            <button type="button" class="lucille-button" @click="addBlock('image')">Image</button>
            <button type="button" class="lucille-button" @click="addBlock('gallery')">Gallery</button>
            <button type="button" class="lucille-button" @click="addBlock('raw')">Raw HTML</button>
        </div>

        <div class="space-y-4">
            <template x-for="(block, index) in blocks" :key="block.id">
                <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.12)] p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <select class="lucille-product-field min-w-[8rem]" x-model="block.type" @change="sync()">
                                <option value="paragraph">Paragraph</option>
                                <option value="heading">Heading</option>
                                <option value="quote">Quote</option>
                                <option value="image">Image</option>
                                <option value="gallery">Gallery</option>
                                <option value="raw">Raw HTML</option>
                            </select>
                            <span class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Block <span x-text="index + 1"></span></span>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="lucille-button" @click="moveBlock(index, -1)" :disabled="index === 0">↑</button>
                            <button type="button" class="lucille-button" @click="moveBlock(index, 1)" :disabled="index === blocks.length - 1">↓</button>
                            <button type="button" class="lucille-button-solid" @click="removeBlock(index)">Delete</button>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-5 lg:grid-cols-[1.2fr_.8fr]">
                        <div class="space-y-4">
                            <template x-if="block.type === 'image'">
                                <div class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <h4 class="font-display text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Image Block</h4>
                                        <label class="lucille-button">
                                            Upload image
                                            <input class="hidden" type="file" accept="image/*" @change="uploadImageForBlock(block, $event)">
                                        </label>
                                    </div>
                                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                                        <div>
                                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Image URL</label>
                                            <input class="lucille-product-field w-full" type="text" x-model="block.src" @input="sync()" placeholder="https://...">
                                        </div>
                                        <div>
                                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Alt text</label>
                                            <input class="lucille-product-field w-full" type="text" x-model="block.alt" @input="sync()" placeholder="Describe the image">
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <template x-if="block.type === 'gallery'">
                                <div class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <h4 class="font-display text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Gallery Block</h4>
                                        <label class="lucille-button">
                                            Upload images
                                            <input class="hidden" type="file" accept="image/*" multiple @change="uploadGalleryImages(block, $event)">
                                        </label>
                                    </div>
                                    <div class="mt-4">
                                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Gallery title</label>
                                        <input class="lucille-product-field w-full" type="text" x-model="block.caption" @input="sync()" placeholder="Optional caption">
                                    </div>
                                    <div class="mt-4 space-y-3">
                                        <template x-for="(item, itemIndex) in block.items" :key="item.id">
                                            <div class="grid gap-3 border border-[#2b2b2b] bg-[rgba(0,0,0,.12)] p-3 md:grid-cols-[110px_1fr_auto]">
                                                <div class="h-[96px] overflow-hidden border border-[#2b2b2b] bg-black">
                                                    <img :src="item.src" alt="" class="h-full w-full object-cover" x-show="item.src">
                                                    <div class="flex h-full items-center justify-center text-xs uppercase tracking-[.18em] text-[#7b7b7b]" x-show="! item.src">No image</div>
                                                </div>
                                                <div class="space-y-2">
                                                    <input class="lucille-product-field w-full" type="text" x-model="item.src" @input="sync()" placeholder="Image URL">
                                                    <input class="lucille-product-field w-full" type="text" x-model="item.alt" @input="sync()" placeholder="Alt text">
                                                </div>
                                                <button type="button" class="lucille-button-solid self-start" @click="removeGalleryItem(block, itemIndex)">Remove</button>
                                            </div>
                                        </template>
                                    </div>
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <button type="button" class="lucille-button" @click="addGalleryItem(block)">Add empty image</button>
                                    </div>
                                </div>
                            </template>

                            <template x-if="block.type === 'heading'">
                                <div class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] p-4">
                                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Heading text</label>
                                    <input class="lucille-product-field w-full" type="text" x-model="block.value" @input="sync()" placeholder="Heading">
                                </div>
                            </template>

                            <template x-if="block.type === 'paragraph'">
                                <div class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] p-4">
                                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Paragraph text</label>
                                    <textarea class="lucille-product-field w-full" rows="8" x-model="block.value" @input="sync()" placeholder="Write text here"></textarea>
                                </div>
                            </template>

                            <template x-if="block.type === 'quote'">
                                <div class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] p-4">
                                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Quote text</label>
                                    <textarea class="lucille-product-field w-full" rows="6" x-model="block.value" @input="sync()" placeholder="Write the quote"></textarea>
                                    <div class="mt-4">
                                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Citation</label>
                                        <input class="lucille-product-field w-full" type="text" x-model="block.cite" @input="sync()" placeholder="Author / source">
                                    </div>
                                </div>
                            </template>

                            <template x-if="block.type === 'raw'">
                                <div class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] p-4">
                                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Raw HTML</label>
                                    <textarea
                                        class="lucille-product-field w-full font-mono text-[12px]"
                                        rows="10"
                                        x-model="block.value"
                                        @input="sync()"
                                        placeholder="<p>Raw HTML</p>"
                                    ></textarea>
                                </div>
                            </template>
                        </div>

                        <div class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] p-4">
                            <div class="flex items-center justify-between gap-3">
                                <span class="font-display text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Preview</span>
                                <span class="text-[10px] uppercase tracking-[.18em] text-[#7b7b7b]">Live</span>
                            </div>
                            <div class="mt-4 min-h-[120px] border border-[#2b2b2b] bg-[#111] p-4 text-[#dcdcdc]" x-html="previewHtml(block)"></div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
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
                <input
                    name="categories_text"
                    value="{{ old('categories_text', $categoriesText ?? '') }}"
                    class="lucille-product-field w-full"
                    placeholder="Music, Discussion"
                    list="post-category-suggestions"
                >
                <datalist id="post-category-suggestions">
                    @foreach (($categoriesSuggestions ?? []) as $categorySuggestion)
                        <option value="{{ $categorySuggestion }}"></option>
                    @endforeach
                </datalist>
            </div>
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['tags_label'] }}</label>
                <input
                    name="tags_text"
                    value="{{ old('tags_text', $tagsText ?? '') }}"
                    class="lucille-product-field w-full"
                    placeholder="news, live, music"
                    list="post-tag-suggestions"
                >
                <datalist id="post-tag-suggestions">
                    @foreach (($tagsSuggestions ?? []) as $tagSuggestion)
                        <option value="{{ $tagSuggestion }}"></option>
                    @endforeach
                </datalist>
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
