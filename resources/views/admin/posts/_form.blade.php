@php
    $isEdit = $post->exists;
    $admin = $themeAppearance['admin_texts'];
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    <!-- Columna Principal (2/3 de ancho) -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Título y Enlace permanente -->
        <div class="rounded-xl border border-white/10 bg-[#101012] p-5 sm:p-6 shadow-xl space-y-5">
            <div>
                <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Título del artículo</label>
                <input name="title" value="{{ old('title', $post->title) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2.5 text-base font-semibold text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720] transition-colors" placeholder="Escribe el título aquí...">
            </div>

            <div>
                <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Enlace permanente (Slug)</label>
                <input name="slug" value="{{ old('slug', $post->slug) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-xs font-mono text-gray-300 placeholder-gray-600 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720] transition-colors" placeholder="enlace-permanente-automatico">
            </div>
        </div>

        <!-- Editor de Contenido -->
        <div class="rounded-xl border border-white/10 bg-[#101012] p-5 sm:p-6 shadow-xl">
            <div
                class="md:col-span-2"
                x-data="postContentEditor({
                    initialBlocks: @js(old('content_text') ? \App\Support\WordPressContent::toEditorBlocks(old('content_text')) : ($editorBlocks ?? [])),
                    uploadUrl: @js(route('admin.posts.media.store'))
                })"
                x-init="init()"
            >
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300">Contenido del artículo</label>
                        <p class="text-xs text-gray-400 mt-0.5">
                            Editor estructurado por bloques. Añade párrafos, citas, imágenes o código HTML según tus necesidades.
                        </p>
                    </div>
                </div>

                <textarea name="content_text" class="hidden" x-ref="contentText" x-model="serialized"></textarea>
                <textarea name="content_blocks" class="hidden" x-ref="contentBlocks" x-model="serializedBlocks"></textarea>

                <div class="mb-4 flex flex-wrap gap-2 rounded-lg border border-white/10 bg-white/[0.02] p-2.5">
                    <button type="button" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all" @click="addBlock('paragraph')">¶ Párrafo</button>
                    <button type="button" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all" @click="addBlock('heading')">H2 Encabezado</button>
                    <button type="button" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all" @click="addBlock('quote')">❝ Cita</button>
                    <button type="button" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all" @click="addBlock('image')">🖼️ Imagen</button>
                    <button type="button" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all" @click="addBlock('gallery')">📷 Galería</button>
                    <button type="button" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all" @click="addBlock('raw')">&lt;&gt; HTML libre</button>
                </div>

                <div class="space-y-4">
                    <template x-for="(block, index) in blocks" :key="block.id">
                        <div class="rounded-xl border border-white/10 bg-black/30 p-4 transition-all">
                            <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-white/5">
                                <div class="flex items-center gap-2">
                                    <select class="rounded-lg border border-white/10 bg-black/60 px-2.5 py-1 text-xs text-gray-200 font-medium focus:border-[#c32720] focus:outline-none" x-model="block.type" @change="sync()">
                                        <option value="paragraph">Párrafo</option>
                                        <option value="heading">Encabezado</option>
                                        <option value="quote">Cita</option>
                                        <option value="image">Imagen</option>
                                        <option value="gallery">Galería</option>
                                        <option value="raw">HTML libre</option>
                                    </select>
                                    <span class="text-xs text-gray-500">Bloque #<span x-text="index + 1"></span></span>
                                </div>

                                <div class="flex flex-wrap gap-1.5">
                                    <button type="button" class="inline-flex items-center justify-center h-7 w-7 rounded-md border border-white/10 bg-white/5 hover:bg-white/10 text-xs text-gray-300 disabled:opacity-30" @click="moveBlock(index, -1)" :disabled="index === 0" title="Subir">↑</button>
                                    <button type="button" class="inline-flex items-center justify-center h-7 w-7 rounded-md border border-white/10 bg-white/5 hover:bg-white/10 text-xs text-gray-300 disabled:opacity-30" @click="moveBlock(index, 1)" :disabled="index === blocks.length - 1" title="Bajar">↓</button>
                                    <button type="button" class="inline-flex items-center justify-center h-7 px-2.5 rounded-md border border-rose-900/40 bg-rose-950/20 hover:bg-rose-900/30 text-rose-300 text-xs font-medium" @click="removeBlock(index)">Eliminar</button>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-5 lg:grid-cols-[1.2fr_.8fr]">
                                <div class="space-y-4">
                                    <template x-if="block.type === 'image'">
                                        <div class="rounded-lg border border-white/5 bg-white/[0.02] p-4">
                                            <div class="flex items-center justify-between gap-3 mb-3">
                                                <h4 class="text-xs font-medium uppercase tracking-wider text-gray-300">Bloque de imagen</h4>
                                                <label class="inline-flex items-center gap-1.5 h-7 px-2.5 rounded-md border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs cursor-pointer">
                                                    Subir archivo
                                                    <input class="hidden" type="file" accept="image/*" @change="uploadImageForBlock(block, $event)">
                                                </label>
                                            </div>
                                            <div class="grid gap-3 md:grid-cols-2">
                                                <div>
                                                    <label class="block text-xs text-gray-400 mb-1">URL de la imagen</label>
                                                    <input class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-1.5 text-xs text-gray-200 focus:border-[#c32720] focus:outline-none" type="text" x-model="block.src" @input="sync()" placeholder="https://...">
                                                </div>
                                                <div>
                                                    <label class="block text-xs text-gray-400 mb-1">Texto alternativo (alt)</label>
                                                    <input class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-1.5 text-xs text-gray-200 focus:border-[#c32720] focus:outline-none" type="text" x-model="block.alt" @input="sync()" placeholder="Describe la imagen">
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="block.type === 'gallery'">
                                        <div class="rounded-lg border border-white/5 bg-white/[0.02] p-4">
                                            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                                                <h4 class="text-xs font-medium uppercase tracking-wider text-gray-300">Bloque de galería</h4>
                                                <label class="inline-flex items-center gap-1.5 h-7 px-2.5 rounded-md border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs cursor-pointer">
                                                    Subir fotos
                                                    <input class="hidden" type="file" accept="image/*" multiple @change="uploadGalleryImages(block, $event)">
                                                </label>
                                            </div>
                                            <div class="mb-3">
                                                <label class="block text-xs text-gray-400 mb-1">Título / Pie de galería</label>
                                                <input class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-1.5 text-xs text-gray-200 focus:border-[#c32720] focus:outline-none" type="text" x-model="block.caption" @input="sync()" placeholder="Título opcional">
                                            </div>
                                            <div class="space-y-2">
                                                <template x-for="(item, itemIndex) in block.items" :key="item.id">
                                                    <div class="grid gap-2 rounded-lg border border-white/5 bg-black/40 p-2.5 md:grid-cols-[80px_1fr_auto] items-center">
                                                        <div class="h-14 w-20 overflow-hidden rounded border border-white/10 bg-black">
                                                            <img :src="item.src" alt="" class="h-full w-full object-cover" x-show="item.src" loading="lazy">
                                                            <div class="flex h-full items-center justify-center text-[10px] text-gray-600" x-show="! item.src">Sin foto</div>
                                                        </div>
                                                        <div class="space-y-1">
                                                            <input class="w-full rounded border border-white/10 bg-black/60 px-2 py-1 text-xs text-gray-200" type="text" x-model="item.src" @input="sync()" placeholder="URL de imagen">
                                                            <input class="w-full rounded border border-white/10 bg-black/60 px-2 py-1 text-xs text-gray-200" type="text" x-model="item.alt" @input="sync()" placeholder="Texto alternativo">
                                                        </div>
                                                        <button type="button" class="inline-flex items-center justify-center h-7 px-2 rounded border border-rose-900/40 bg-rose-950/20 text-rose-300 text-xs" @click="removeGalleryItem(block, itemIndex)">✕</button>
                                                    </div>
                                                </template>
                                            </div>
                                            <div class="mt-3">
                                                <button type="button" class="inline-flex items-center gap-1 h-7 px-2.5 rounded border border-white/10 bg-white/5 text-gray-300 text-xs" @click="addGalleryItem(block)">+ Añadir imagen vacía</button>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="block.type === 'heading'">
                                        <div class="rounded-lg border border-white/5 bg-white/[0.02] p-4">
                                            <label class="block text-xs text-gray-400 mb-1">Texto del encabezado</label>
                                            <input class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-sm font-semibold text-white focus:border-[#c32720] focus:outline-none" type="text" x-model="block.value" @input="sync()" placeholder="Encabezado de sección">
                                        </div>
                                    </template>

                                    <template x-if="block.type === 'paragraph'">
                                        <div class="rounded-lg border border-white/5 bg-white/[0.02] p-4">
                                            <label class="block text-xs text-gray-400 mb-1">Texto del párrafo</label>
                                            <textarea class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2.5 text-sm text-gray-200 focus:border-[#c32720] focus:outline-none" rows="7" x-model="block.value" @input="sync()" placeholder="Escribe el texto aquí..."></textarea>
                                            
                                            <div class="mt-3 pt-3 border-t border-white/5">
                                                <div class="flex items-center justify-between mb-2">
                                                    <span class="text-xs text-gray-400">🔗 Enlaces en el texto</span>
                                                    <button type="button" class="inline-flex items-center h-6 px-2 rounded border border-white/10 bg-white/5 text-[11px] text-gray-300 hover:bg-white/10" @click="addLink(block)">+ Añadir enlace</button>
                                                </div>
                                                <template x-for="(link, linkIndex) in block.links" :key="linkIndex">
                                                    <div class="mt-2 grid grid-cols-[1fr_1fr_auto] gap-2">
                                                        <input class="rounded border border-white/10 bg-black/60 px-2 py-1 text-xs text-gray-200" type="text" x-model="link.word" @input="sync()" placeholder="Palabra a enlazar">
                                                        <input class="rounded border border-white/10 bg-black/60 px-2 py-1 text-xs text-gray-200" type="url" x-model="link.url" @input="sync()" placeholder="https://...">
                                                        <button type="button" class="inline-flex items-center justify-center h-7 px-2 rounded border border-rose-900/40 bg-rose-950/20 text-rose-300 text-xs" @click="removeLink(block, linkIndex)">✕</button>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="block.type === 'quote'">
                                        <div class="rounded-lg border border-white/5 bg-white/[0.02] p-4">
                                            <label class="block text-xs text-gray-400 mb-1">Texto de la cita</label>
                                            <textarea class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-sm italic text-gray-200 focus:border-[#c32720] focus:outline-none" rows="4" x-model="block.value" @input="sync()" placeholder="Frase destacada..."></textarea>
                                            <div class="mt-2">
                                                <label class="block text-xs text-gray-400 mb-1">Autor / Fuente</label>
                                                <input class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-1.5 text-xs text-gray-200 focus:border-[#c32720] focus:outline-none" type="text" x-model="block.cite" @input="sync()" placeholder="Ej. Robert Plant">
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="block.type === 'raw'">
                                        <div class="rounded-lg border border-white/5 bg-white/[0.02] p-4">
                                            <label class="block text-xs text-gray-400 mb-1">Código HTML directo</label>
                                            <textarea
                                                class="w-full rounded-lg border border-white/10 bg-black/60 px-3 py-2 font-mono text-xs text-gray-300 focus:border-[#c32720] focus:outline-none"
                                                rows="8"
                                                x-model="block.value"
                                                @input="sync()"
                                                placeholder="<p>HTML personalizado</p>"
                                            ></textarea>
                                        </div>
                                    </template>
                                </div>

                                <div class="rounded-lg border border-white/5 bg-black/40 p-3.5">
                                    <div class="flex items-center justify-between gap-3 pb-2 border-b border-white/5">
                                        <span class="text-xs font-medium uppercase tracking-wider text-gray-400">Previsualización</span>
                                        <span class="text-[10px] uppercase tracking-wider text-gray-500">En vivo</span>
                                    </div>
                                    <div class="mt-3 min-h-[100px] text-xs text-gray-300 leading-relaxed overflow-hidden" x-html="previewHtml(block)"></div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Extracto / Excerpt -->
        <div class="rounded-xl border border-white/10 bg-[#101012] p-5 sm:p-6 shadow-xl">
            <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Extracto / Resumen corto</label>
            <p class="text-xs text-gray-400 mb-2">Se muestra en las tarjetas de la portada y en los resultados de búsqueda.</p>
            <textarea name="excerpt" rows="3" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720] transition-colors" placeholder="Escribe un resumen o extracto corto aquí...">{{ old('excerpt', $post->excerpt) }}</textarea>
        </div>

        <!-- Cita Destacada / Quote -->
        <div class="rounded-xl border border-white/10 bg-[#101012] p-5 sm:p-6 shadow-xl">
            <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Cita destacada (Opcional)</label>
            <textarea name="quote" rows="2" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720] transition-colors" placeholder="Escribe una frase célebre o cita aquí...">{{ old('quote', $post->quote) }}</textarea>
        </div>
    </div>

    <!-- Columna Lateral / Configuración (1/3 de ancho) -->
    <div class="space-y-6">
        <!-- Caja 1: Estado y Publicación -->
        <div class="rounded-xl border border-white/10 bg-[#101012] p-5 shadow-xl">
            <h3 class="font-display text-sm uppercase tracking-wider text-[#dcdcdc] border-b border-white/10 pb-2.5 mb-4">📢 Publicación</h3>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Autor</label>
                    <input name="author" value="{{ old('author', $post->author) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                </div>

                <div>
                    <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Fecha y hora de publicación</label>
                    <input type="datetime-local" name="published_at" value="{{ old('published_at', optional($post->published_at)->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                </div>

                <div>
                    <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Zona horaria</label>
                    @php
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
                    <select name="timezone" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
                        @foreach ($timezones as $tzKey => $tzLabel)
                            <option value="{{ $tzKey }}" {{ $currentTimezone === $tzKey ? 'selected' : '' }}>
                                {{ $tzLabel }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Botones de Acción de Publicación -->
            <div class="mt-6 pt-4 border-t border-white/10 flex flex-col gap-2.5" x-data="postActionButtons()">
                <button type="submit" name="_action" value="publish" class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg bg-[#c32720] hover:bg-[#a8201a] text-white text-xs font-semibold tracking-wide shadow-md shadow-red-950/40 transition-all focus:outline-none focus:ring-2 focus:ring-[#c32720]/50 w-full text-center">
                    📢 Publicar ahora
                </button>
                <button type="submit" name="_action" value="draft" class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all w-full text-center">
                    📝 Guardar borrador
                </button>
                <button type="submit" name="_action" value="schedule" class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg border border-amber-500/30 bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 text-xs font-medium transition-all w-full text-center" x-bind:class="scheduleBtnClass" x-bind:disabled="!canSchedule">
                    ⏳ Programar fecha
                </button>
                <a href="{{ route('admin.posts.index') }}" class="inline-flex items-center justify-center gap-2 h-9 px-4 rounded-lg border border-white/5 bg-transparent hover:bg-white/5 text-gray-400 hover:text-white text-xs font-medium transition-all w-full text-center">
                    Volver a noticias
                </a>
                <span class="text-center text-[11px] text-amber-400/80 mt-1 block" x-show="!canSchedule && publishedAt !== ''">
                    (Selecciona una fecha futura para programar)
                </span>
            </div>
        </div>

        <!-- Caja 2: Imagen Destacada -->
        <div class="rounded-xl border border-white/10 bg-[#101012] p-5 shadow-xl">
            <h3 class="font-display text-sm uppercase tracking-wider text-[#dcdcdc] border-b border-white/10 pb-2.5 mb-4">📸 Imagen Destacada</h3>
            
            @if ($post->featured_image_url)
                <div class="mb-4 overflow-hidden rounded-lg border border-white/10 bg-black">
                    <img src="{{ $post->featured_image_url }}" alt="Vista previa de imagen destacada" class="w-full h-auto object-cover max-h-48" loading="lazy">
                </div>
            @endif

            @if ($post->image_fetch_error)
                <div class="mb-4 rounded-lg border border-rose-900/40 bg-rose-950/30 p-3 text-xs text-rose-300">
                    <div class="font-semibold flex items-center gap-1 mb-1">
                        ⚠️ Fallo al obtener imagen automática:
                    </div>
                    <div class="mb-2 text-gray-300">{{ $post->image_fetch_error }}</div>
                    @if ($post->source_url)
                        <div class="text-[11px] text-gray-400 mb-2 truncate">Fuente: <a href="{{ $post->source_url }}" target="_blank" rel="noopener noreferrer" class="underline hover:text-white">{{ $post->source_url }}</a></div>
                        <button type="submit" formaction="{{ route('admin.posts.retry-image', $post) }}" formmethod="POST" class="inline-flex items-center justify-center gap-1 h-7 px-2.5 rounded border border-white/10 bg-white/5 text-xs text-gray-200">
                            🔄 Reintentar descarga de imagen
                        </button>
                    @endif
                </div>
            @endif

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Ruta o URL de imagen</label>
                    <input name="featured_image" value="{{ old('featured_image', $post->featured_image) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 placeholder-gray-500 focus:border-[#c32720] focus:outline-none" placeholder="https://... o ruta relativa">
                </div>

                <div>
                    <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Subir archivo de imagen</label>
                    <input type="file" name="featured_image_file" class="block w-full text-xs text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-white/10 file:text-gray-200 hover:file:bg-white/20">
                </div>
            </div>
        </div>

        <!-- Caja 3: Taxonomías -->
        <div class="rounded-xl border border-white/10 bg-[#101012] p-5 shadow-xl">
            <h3 class="font-display text-sm uppercase tracking-wider text-[#dcdcdc] border-b border-white/10 pb-2.5 mb-4">🏷️ Categorías y Etiquetas</h3>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Categorías</label>
                    <input
                        name="categories_text"
                        value="{{ old('categories_text', $categoriesText ?? '') }}"
                        class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 placeholder-gray-500 focus:border-[#c32720] focus:outline-none"
                        placeholder="Rock, Heavy Metal, Noticias"
                        list="post-category-suggestions"
                    >
                    <datalist id="post-category-suggestions">
                        @foreach (($categoriesSuggestions ?? []) as $categorySuggestion)
                            <option value="{{ $categorySuggestion }}"></option>
                        @endforeach
                    </datalist>
                </div>

                <div>
                    <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Etiquetas</label>
                    <input
                        name="tags_text"
                        value="{{ old('tags_text', $tagsText ?? '') }}"
                        class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 placeholder-gray-500 focus:border-[#c32720] focus:outline-none"
                        placeholder="gira, disco, nuevo tema"
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

        <!-- Caja 4: En Memoria -->
        <div class="rounded-xl border border-white/10 bg-[#101012] p-5 shadow-xl">
            <h3 class="font-display text-sm uppercase tracking-wider text-[#dcdcdc] border-b border-white/10 pb-2.5 mb-4">🖤 En Memoria</h3>
            <p class="text-xs text-gray-400 mb-3">Marca este artículo si es en homenaje a un artista fallecido. Mostrará la distinción en la web.</p>
            
            <div class="space-y-3">
                <label class="flex items-center gap-3 text-sm text-gray-300 cursor-pointer">
                    <input type="checkbox" name="en_memoria" value="1" @checked(old('en_memoria', $post->en_memoria)) class="h-4 w-4 rounded border-white/20 bg-black/40 text-[#c32720] focus:ring-[#c32720]">
                    <span>Activar sección "En memoria"</span>
                </label>

                <div>
                    <label class="block text-xs text-gray-400 mb-1">Nombre del artista homenajeado</label>
                    <input type="text" name="en_memoria_nombre" value="{{ old('en_memoria_nombre', $post->en_memoria_nombre) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-1.5 text-xs text-gray-100 placeholder-gray-500 focus:border-[#c32720] focus:outline-none" placeholder="Ej. Lemmy Kilmister">
                </div>
            </div>
        </div>

        <!-- Caja 5: Notificación al Autor -->
        <div class="rounded-xl border border-white/10 bg-[#101012] p-5 shadow-xl">
            <h3 class="font-display text-sm uppercase tracking-wider text-[#dcdcdc] border-b border-white/10 pb-2.5 mb-4">📧 Notificación al Autor</h3>
            <p class="text-xs text-gray-400 mb-3">Envía un aviso automático por correo cuando el artículo sea publicado.</p>
            
            <div class="space-y-3">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Correo del autor</label>
                    <input type="email" name="author_email" value="{{ old('author_email', $post->author_email) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-1.5 text-xs text-gray-100 placeholder-gray-500 focus:border-[#c32720] focus:outline-none" placeholder="autor@ejemplo.com">
                </div>

                <div>
                    <label class="block text-xs text-gray-400 mb-1">Correo remitente (Opcional)</label>
                    <input type="email" name="notification_sender" value="{{ old('notification_sender', $post->notification_sender) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-1.5 text-xs text-gray-100 placeholder-gray-500 focus:border-[#c32720] focus:outline-none" placeholder="prensa@sevenrockradio.com">
                </div>
            </div>
        </div>

        <!-- Caja 6: Redes Sociales y Enlaces Externos -->
        <div class="rounded-xl border border-white/10 bg-[#101012] p-5 shadow-xl">
            <h3 class="font-display text-sm uppercase tracking-wider text-[#dcdcdc] border-b border-white/10 pb-2.5 mb-4">🌐 Redes y Enlaces</h3>
            
            <div class="space-y-3">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Fuente / Agencia</label>
                    <input name="source_name" value="{{ old('source_name', $post->source_name) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-1.5 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="Ej. Agencia EFE">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">URL de la fuente original</label>
                    <input name="source_url" value="{{ old('source_url', $post->source_url) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-1.5 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="https://...">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Instagram URL</label>
                    <input name="instagram_url" value="{{ old('instagram_url', $post->instagram_url) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-1.5 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">YouTube URL</label>
                    <input name="youtube_url" value="{{ old('youtube_url', $post->youtube_url) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-1.5 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none">
                </div>
            </div>
        </div>

        <!-- Caja 7: SEO Meta -->
        <div class="rounded-xl border border-white/10 bg-[#101012] p-5 shadow-xl">
            <h3 class="font-display text-sm uppercase tracking-wider text-[#dcdcdc] border-b border-white/10 pb-2.5 mb-4">🔍 Motores de Búsqueda (SEO)</h3>
            
            <div class="space-y-3">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Meta title</label>
                    <input name="meta_title" value="{{ old('meta_title', $post->meta_title) }}" class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-1.5 text-xs text-gray-100 placeholder-gray-500 focus:border-[#c32720] focus:outline-none" placeholder="Título para buscadores...">
                </div>

                <div>
                    <label class="block text-xs text-gray-400 mb-1">Meta description</label>
                    <textarea name="meta_description" rows="3" class="w-full rounded-lg border border-white/10 bg-black/40 px-3 py-1.5 text-xs text-gray-100 placeholder-gray-500 focus:border-[#c32720] focus:outline-none" placeholder="Breve resumen para los resultados de Google...">{{ old('meta_description', $post->meta_description) }}</textarea>
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
            return this.canSchedule ? '' : 'opacity-40 cursor-not-allowed';
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
