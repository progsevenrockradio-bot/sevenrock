@php
    $isEdit = $post->exists;
    $admin = $themeAppearance['admin_texts'];
@endphp

<style>
    main.max-w-6xl {
        max-width: 90vw !important;
    }
    .sidebar-card {
        border: 1px solid #2b2b2b;
        background: rgba(16, 16, 18, 0.6);
        padding: 1.25rem;
        border-radius: 0.125rem;
    }
</style>

<div class="grid gap-6 lg:grid-cols-3">
    <!-- Columna Principal (2/3 de ancho) -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Título -->
        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['title_label'] }}</label>
            <input name="title" value="{{ old('title', $post->title) }}" class="lucille-product-field w-full text-lg font-bold" placeholder="Escribe el título aquí...">
        </div>

        <!-- Enlace permanente / Slug -->
        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['slug_label'] }}</label>
            <input name="slug" value="{{ old('slug', $post->slug) }}" class="lucille-product-field w-full text-xs font-mono" placeholder="enlace-permanente-automatico">
        </div>

        <!-- Editor de Contenido -->
        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
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
                <textarea name="content_blocks" class="hidden" x-ref="contentBlocks" x-model="serializedBlocks"></textarea>

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
                                                            <img :src="item.src" alt="" class="h-full w-full object-cover" x-show="item.src" loading="lazy">
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
                                            
                                            <div class="mt-4 border-t border-[#2b2b2b] pt-4">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">🔗 Inline Links</span>
                                                    <button type="button" class="lucille-button text-xs" @click="addLink(block)">+ Add Link</button>
                                                </div>
                                                <template x-for="(link, linkIndex) in block.links" :key="linkIndex">
                                                    <div class="mt-3 grid grid-cols-[1fr_1fr_auto] gap-2">
                                                        <input class="lucille-product-field w-full text-xs" type="text" x-model="link.word" @input="sync()" placeholder="Word to link">
                                                        <input class="lucille-product-field w-full text-xs" type="url" x-model="link.url" @input="sync()" placeholder="https://...">
                                                        <button type="button" class="lucille-button-solid text-xs px-2" @click="removeLink(block, linkIndex)">✕</button>
                                                    </div>
                                                </template>
                                                <p class="mt-2 text-[10px] text-[#555]" x-show="block.links.length === 0">Select a word from the text and add a link. The first occurrence of the word will be linked.</p>
                                            </div>
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
        </div>

        <!-- Extracto / Excerpt -->
        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['excerpt_label'] }}</label>
            <textarea name="excerpt" rows="4" class="lucille-product-field w-full" placeholder="Escribe un resumen o extracto corto aquí...">{{ old('excerpt', $post->excerpt) }}</textarea>
        </div>

        <!-- Cita Destacada / Quote -->
        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['quote_label'] }}</label>
            <textarea name="quote" rows="3" class="lucille-product-field w-full" placeholder="Escribe una frase célebre o cita aquí...">{{ old('quote', $post->quote) }}</textarea>
        </div>
    </div>

    <!-- Columna Lateral / Configuración (1/3 de ancho) -->
    <div class="space-y-6">
        <!-- Caja 1: Estado y Publicación -->
        <div class="sidebar-card">
            <h3 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-2 mb-4">📢 Publicación</h3>
            
            <div class="space-y-4">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['author_label'] }}</label>
                    <input name="author" value="{{ old('author', $post->author) }}" class="lucille-product-field w-full">
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['published_at_label'] }}</label>
                    <input type="datetime-local" name="published_at" value="{{ old('published_at', optional($post->published_at)->format('Y-m-d\TH:i')) }}" class="lucille-product-field w-full">
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Zona horaria</label>
                    @php
                        // List of standard common timezones for selection
                        $timezones = [
                            'Europe/Madrid' => 'Europa/Madrid (Madrid, Barcelona, Paris)',
                            'UTC' => 'UTC (Tiempo Universal Coordinado)',
                            'America/New_York' => 'América/New York (EST)',
                            'America/Chicago' => 'América/Chicago (CST)',
                            'America/Denver' => 'América/Denver (MST)',
                            'America/Los_Angeles' => 'América/Los Angeles (PST)',
                            'America/Mexico_City' => 'América/Ciudad de México',
                            'America/Bogota' => 'América/Bogotá',
                            'America/Santiago' => 'América/Santiago',
                            'America/Argentina/Buenos_Aires' => 'América/Buenos Aires',
                            'America/Caracas' => 'América/Caracas',
                            'America/Lima' => 'América/Lima',
                        ];
                        $currentTimezone = old('timezone', $post->timezone ?? 'Europe/Madrid');
                    @endphp
                    <select name="timezone" class="lucille-product-field w-full">
                        @foreach ($timezones as $tzKey => $tzLabel)
                            <option value="{{ $tzKey }}" {{ $currentTimezone === $tzKey ? 'selected' : '' }}>
                                {{ $tzLabel }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Botones de Acción de Publicación -->
            <div class="mt-6 pt-4 border-t border-[#2b2b2b] flex flex-col gap-2" x-data="postActionButtons()">
                <button type="submit" name="_action" value="publish" class="lucille-button-solid w-full text-center">
                    📢 {{ $admin['publish_label'] ?? 'Publish' }}
                </button>
                <button type="submit" name="_action" value="draft" class="lucille-button w-full text-center">
                    📝 {{ $admin['draft_label'] ?? 'Save Draft' }}
                </button>
                <button type="submit" name="_action" value="schedule" class="lucille-button w-full text-center" x-bind:class="scheduleBtnClass" x-bind:disabled="!canSchedule">
                    ⏳ {{ $admin['schedule_label'] ?? 'Schedule' }}
                </button>
                <a href="{{ route('admin.posts.index') }}" class="lucille-button w-full text-center">{{ $admin['back_to_posts'] }}</a>
                <span class="text-center text-[10px] text-[#8a8a8a] mt-2 block" x-show="!canSchedule && publishedAt !== ''">
                    ({{ $admin['schedule_future_only'] ?? 'Selecciona una fecha futura para programar' }})
                </span>
            </div>
        </div>

        <!-- Caja 2: Notificación al Autor -->
        <div class="sidebar-card">
            <h3 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-2 mb-4">📧 Notificación al Autor</h3>
            <p class="mb-4 text-xs text-[#7b7b7b]">Configura un correo automático para notificar al autor cuando este post sea publicado (inmediatamente o programado).</p>
            
            <div class="space-y-4">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Correo Destinatario</label>
                    <input type="email" name="author_email" value="{{ old('author_email', $post->author_email) }}" class="lucille-product-field w-full" placeholder="ejemplo@autor.com">
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Correo Remitente</label>
                    <input type="email" name="notification_sender" value="{{ old('notification_sender', $post->notification_sender) }}" class="lucille-product-field w-full" placeholder="noreply@sevenrockradio.com">
                    <p class="mt-1 text-[10px] text-[#555]">Dejar en blanco para usar el correo por defecto de la radio.</p>
                </div>
            </div>
        </div>

        <!-- Caja 3: Imagen Destacada -->
        <div class="sidebar-card">
            <h3 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-2 mb-4">📸 Imagen Destacada</h3>
            
            @if ($post->featured_image_url)
                <div class="mb-4 border border-[#2b2b2b] bg-black p-1">
                    <img src="{{ $post->featured_image_url }}" alt="Vista previa de imagen destacada" class="w-full h-auto object-cover max-h-48" loading="lazy">
                </div>
            @endif

            <div class="space-y-4">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['featured_image_path_label'] }}</label>
                    <input name="featured_image" value="{{ old('featured_image', $post->featured_image) }}" class="lucille-product-field w-full" placeholder="Ruta o URL externa...">
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['featured_image_file_label'] }}</label>
                    <input type="file" name="featured_image_file" class="block w-full text-xs text-[#7b7b7b]">
                </div>
            </div>
        </div>

        <!-- Caja 4: Taxonomías -->
        <div class="sidebar-card">
            <h3 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-2 mb-4">🏷️ Categorías y Etiquetas</h3>
            
            <div class="space-y-4">
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

        <!-- Caja 5: Redes Sociales -->
        <div class="sidebar-card">
            <h3 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-2 mb-4">🌐 Redes del Artista / Fuente</h3>
            <p class="mb-4 text-[11px] text-[#7b7b7b]">Enlaces informativos sobre el artista o banda mencionada en el post.</p>
            
            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Facebook URL</label>
                    <input name="facebook_url" value="{{ old('facebook_url', $post->facebook_url) }}" class="lucille-product-field w-full text-xs">
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Instagram URL</label>
                    <input name="instagram_url" value="{{ old('instagram_url', $post->instagram_url) }}" class="lucille-product-field w-full text-xs">
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Twitter URL</label>
                    <input name="twitter_url" value="{{ old('twitter_url', $post->twitter_url) }}" class="lucille-product-field w-full text-xs">
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">YouTube URL</label>
                    <input name="youtube_url" value="{{ old('youtube_url', $post->youtube_url) }}" class="lucille-product-field w-full text-xs">
                </div>
            </div>
        </div>

        <!-- Caja 6: Enlace Externo y Créditos -->
        <div class="sidebar-card">
            <h3 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-2 mb-4">🔗 Enlaces y Créditos</h3>
            
            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">External link URL</label>
                    <input name="external_link_url" value="{{ old('external_link_url', $post->external_link_url) }}" class="lucille-product-field w-full text-xs">
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">External link label</label>
                    <input name="external_link_label" value="{{ old('external_link_label', $post->external_link_label) }}" class="lucille-product-field w-full text-xs">
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Source name</label>
                    <input name="source_name" value="{{ old('source_name', $post->source_name) }}" class="lucille-product-field w-full text-xs">
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Source URL</label>
                    <input name="source_url" value="{{ old('source_url', $post->source_url) }}" class="lucille-product-field w-full text-xs">
                </div>
            </div>
        </div>

        <!-- Caja 7: SEO Meta -->
        <div class="sidebar-card">
            <h3 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-2 mb-4">🔍 Motores de Búsqueda (SEO)</h3>
            
            <div class="space-y-4">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Meta title</label>
                    <input name="meta_title" value="{{ old('meta_title', $post->meta_title) }}" class="lucille-product-field w-full" placeholder="Título para buscadores...">
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Meta description</label>
                    <textarea name="meta_description" rows="4" class="lucille-product-field w-full" placeholder="Breve resumen para los resultados de búsqueda...">{{ old('meta_description', $post->meta_description) }}</textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function postActionButtons() {
    return {
        publishedAt: document.querySelector('[name="published_at"]')?.value ?? '',
        get canSchedule() {
            if (!this.publishedAt) return false;
            const d = new Date(this.publishedAt);
            return d > new Date();
        },
        get scheduleBtnClass() {
            return this.canSchedule ? 'lucille-button' : 'lucille-button opacity-40 cursor-not-allowed';
        },
        init() {
            const input = document.querySelector('[name="published_at"]');
            if (input) {
                input.addEventListener('change', () => { this.publishedAt = input.value; });
                input.addEventListener('input', () => { this.publishedAt = input.value; });
            }
        }
    };
}
</script>
