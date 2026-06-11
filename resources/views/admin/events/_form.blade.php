@php
    $isEdit = $event->exists;
    $admin = $themeAppearance['admin_texts'];
    $categoriesValue = old('categories_text', $categoriesText ?? implode(', ', $event->categories ?? []));
    $contentValue = old('content_text', $contentText ?? implode("\n\n", $event->content ?? []));
    $posterValue = old('poster', $event->poster);

    $startsAt = $event->starts_at;
    if (is_string($startsAt)) {
        $startsAt = \Illuminate\Support\Carbon::parse($startsAt);
    }
    $endsAt = $event->ends_at;
    if (is_string($endsAt)) {
        $endsAt = \Illuminate\Support\Carbon::parse($endsAt);
    }
@endphp

<div x-data="eventForm" class="grid gap-6 xl:grid-cols-[1.05fr_.95fr]">
    
    <!-- Left Column: Form Editor -->
    <div class="space-y-6">
        <!-- Tabs Buttons Navigation -->
        <div class="mb-4 border-b border-[#2b2b2b] flex gap-2 overflow-x-auto pb-px">
            <button type="button" @click="tab = 'general'" :class="tab === 'general' ? 'border-b-2 border-[var(--lucille-accent)] text-[#dcdcdc]' : 'text-[#7b7b7b] hover:text-[#bcbcbc]'" class="pb-3 px-4 text-xs font-display uppercase tracking-[.18em] transition-colors focus:outline-none whitespace-nowrap">
                Información General
            </button>
            <button type="button" @click="tab = 'details'" :class="tab === 'details' ? 'border-b-2 border-[var(--lucille-accent)] text-[#dcdcdc]' : 'text-[#7b7b7b] hover:text-[#bcbcbc]'" class="pb-3 px-4 text-xs font-display uppercase tracking-[.18em] transition-colors focus:outline-none whitespace-nowrap">
                Detalles & Enlaces
            </button>
            <button type="button" @click="tab = 'media'" :class="tab === 'media' ? 'border-b-2 border-[var(--lucille-accent)] text-[#dcdcdc]' : 'text-[#7b7b7b] hover:text-[#bcbcbc]'" class="pb-3 px-4 text-xs font-display uppercase tracking-[.18em] transition-colors focus:outline-none whitespace-nowrap">
                Póster & Multimedia
            </button>
        </div>

        <!-- Tab 1: General Info -->
        <div x-show="tab === 'general'" class="space-y-6" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            <section class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] p-6 space-y-5">
                <div>
                    <h2 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">Información General</h2>
                    <p class="mt-1 text-xs text-[#7b7b7b]">Datos esenciales y clasificación del evento.</p>
                </div>

                <div class="grid gap-5">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['form_title_label'] }} <span class="text-[var(--lucille-accent)]">*</span></label>
                        <input name="title" x-model="title" @input="updateSlug" class="lucille-product-field w-full @error('title') border-red-500/80 bg-red-950/10 @enderror">
                        @error('title')
                            <p class="mt-1.5 text-xs text-red-500 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['form_slug_label'] }}</label>
                        <input name="slug" x-model="slug" @input="slugManuallyEdited = true" class="lucille-product-field w-full @error('slug') border-red-500/80 bg-red-950/10 @enderror">
                        @error('slug')
                            <p class="mt-1.5 text-xs text-red-500 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Categorías</label>
                        <input
                            name="categories_text"
                            x-model="categories"
                            class="lucille-product-field w-full"
                            placeholder="Guest Appearance, Music Festivals"
                        >
                        <p class="mt-2 text-xs uppercase tracking-[.14em] text-[#7b7b7b]">Separa los nombres con comas.</p>
                    </div>
                </div>
            </section>

            <section class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] p-6">
                <div class="mb-5">
                    <h2 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">Descripción del Evento</h2>
                    <p class="mt-1 text-xs text-[#7b7b7b]">Cada párrafo se guarda de manera independiente para formatear la página pública del show.</p>
                </div>

                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Párrafos descriptivos</label>
                <textarea
                    name="content_text"
                    x-model="content"
                    rows="10"
                    class="lucille-product-field w-full font-body text-[15px] leading-7"
                    placeholder="Escribe la descripción aquí. Deja una línea en blanco entre párrafos para separarlos."
                ></textarea>
            </section>
        </div>

        <!-- Tab 2: Details & Links -->
        <div x-show="tab === 'details'" class="space-y-6" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" style="display: none;">
            <section class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] p-6 space-y-5">
                <div>
                    <h2 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">Fecha & Ubicación</h2>
                    <p class="mt-1 text-xs text-[#7b7b7b]">Detalles de horarios y lugar del espectáculo.</p>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['starts_at_label'] }} <span class="text-[var(--lucille-accent)]">*</span></label>
                        <input type="datetime-local" name="starts_at" x-model="starts_at" class="lucille-product-field w-full @error('starts_at') border-red-500/80 bg-red-950/10 @enderror">
                        @error('starts_at')
                            <p class="mt-1.5 text-xs text-red-500 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['ends_at_label'] }}</label>
                        <input type="datetime-local" name="ends_at" x-model="ends_at" class="lucille-product-field w-full @error('ends_at') border-red-500/80 bg-red-950/10 @enderror">
                        @error('ends_at')
                            <p class="mt-1.5 text-xs text-red-500 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['location_label'] }}</label>
                        <input name="location" x-model="location" class="lucille-product-field w-full" placeholder="Loch Ness, UK">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['venue_label'] }}</label>
                        <input name="venue" x-model="venue" class="lucille-product-field w-full" placeholder="Rockness Festival">
                    </div>
                </div>
            </section>

            <section class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] p-6 space-y-5">
                <div>
                    <h2 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">Enlaces del Evento</h2>
                    <p class="mt-1 text-xs text-[#7b7b7b]">Configuración de botones interactivos para el público.</p>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['ticket_label_label'] }}</label>
                        <input name="ticket_label" x-model="ticket_label" class="lucille-product-field w-full" placeholder="Tickets">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['ticket_url_label'] }}</label>
                        <input name="ticket_url" x-model="ticket_url" class="lucille-product-field w-full @error('ticket_url') border-red-500/80 bg-red-950/10 @enderror" placeholder="https://...">
                        @error('ticket_url')
                            <p class="mt-1.5 text-xs text-red-500 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Venue URL</label>
                        <input name="venue_url" x-model="venue_url" class="lucille-product-field w-full @error('venue_url') border-red-500/80 bg-red-950/10 @enderror" placeholder="https://...">
                        @error('venue_url')
                            <p class="mt-1.5 text-xs text-red-500 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Facebook URL</label>
                        <input name="facebook_url" x-model="facebook_url" class="lucille-product-field w-full @error('facebook_url') border-red-500/80 bg-red-950/10 @enderror" placeholder="https://www.facebook.com/...">
                        @error('facebook_url')
                            <p class="mt-1.5 text-xs text-red-500 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </section>
        </div>

        <!-- Tab 3: Poster & Media -->
        <div x-show="tab === 'media'" class="space-y-6" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" style="display: none;">
            <section class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] p-6 space-y-5">
                <div>
                    <h2 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">Póster Promocional</h2>
                    <p class="mt-1 text-xs text-[#7b7b7b]">Sube una imagen local o proporciona una ruta/URL existente <span class="text-[var(--lucille-accent)]">*</span></p>
                </div>

                <div class="grid gap-5">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Ruta o URL de Imagen</label>
                        <input name="poster" x-model="poster" class="lucille-product-field w-full @error('poster') border-red-500/80 bg-red-950/10 @enderror" placeholder="assets/lucille/ozzfest_poster.jpg">
                        @error('poster')
                            <p class="mt-1.5 text-xs text-red-500 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="border border-[#2b2b2b] bg-black/30 p-4">
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b] font-medium">Subir archivo de imagen local</label>
                        <input type="file" name="poster_file" @change="handlePosterUpload" class="block w-full text-xs text-[#7b7b7b] file:mr-4 file:py-2 file:px-4 file:border file:border-[#2b2b2b] file:bg-black/40 file:text-[#dcdcdc] file:text-[11px] file:uppercase file:tracking-wider hover:file:bg-black/60 file:cursor-pointer @error('poster_file') border border-red-500 bg-red-950/20 @enderror">
                        @error('poster_file')
                            <p class="mt-1.5 text-xs text-red-500 font-semibold">{{ $message }}</p>
                        @enderror
                        <p class="mt-2.5 text-[10px] uppercase tracking-wider text-[#666]">Límite de tamaño: 6MB. JPG, PNG o WebP recomendados.</p>
                    </div>
                </div>
            </section>

            <section class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] p-6 space-y-5">
                <div>
                    <h2 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">Video & Ubicación</h2>
                    <p class="mt-1 text-xs text-[#7b7b7b]">Inserta códigos embebidos para enriquecer la página del show.</p>
                </div>

                <div class="grid gap-5">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Video Embed URL</label>
                        <input name="embed_url" x-model="embed_url" class="lucille-product-field w-full @error('embed_url') border-red-500/80 bg-red-950/10 @enderror" placeholder="https://www.youtube.com/embed/...">
                        @error('embed_url')
                            <p class="mt-1.5 text-xs text-red-500 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Google Maps Embed URL</label>
                        <input name="map_url" x-model="map_url" class="lucille-product-field w-full @error('map_url') border-red-500/80 bg-red-950/10 @enderror" placeholder="https://www.google.com/maps/embed?...">
                        @error('map_url')
                            <p class="mt-1.5 text-xs text-red-500 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- Right Column: Live Interactive Preview -->
    <div class="space-y-6">
        <div class="sticky top-[100px] border border-[#2b2b2b] bg-[rgba(16,16,18,.62)] p-5 backdrop-blur-sm space-y-5">
            <div class="flex items-center justify-between border-b border-[#2b2b2b] pb-3">
                <span class="font-display text-[10px] uppercase tracking-[.25em] text-[#7b7b7b]">Vista Previa en Vivo</span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-1.5 w-1.5 rounded-full bg-[var(--lucille-accent)] animate-pulse"></span>
                    <span class="text-[9px] uppercase tracking-wider text-[#bcbcbc]">Actualizando</span>
                </span>
            </div>

            <!-- Page Header Mock -->
            <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.8)] p-6 text-center">
                <!-- Categories Badges -->
                <div class="mb-3 flex flex-wrap justify-center gap-1.5">
                    <template x-for="cat in categoriesArray" :key="cat">
                        <span class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] px-2.5 py-0.5 text-[9px] uppercase tracking-wider text-[#bcbcbc]" x-text="cat"></span>
                    </template>
                    <template x-if="categoriesArray.length === 0">
                        <span class="border border-dashed border-[#2b2b2b] px-2.5 py-0.5 text-[9px] uppercase tracking-wider text-[#555]">Sin categorías</span>
                    </template>
                </div>

                <!-- Event Title -->
                <h1 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc] break-words leading-tight" x-text="title || 'Título del Evento'"></h1>
                <p class="mt-1.5 text-[9px] uppercase tracking-[.36em] text-[#7b7b7b]">Upcoming shows 2026</p>

                <!-- Date & Time badges -->
                <div class="mt-4 flex flex-wrap justify-center gap-2 text-[10px] uppercase tracking-wider text-[#dcdcdc]">
                    <span class="border border-[#2b2b2b] px-3 py-1 bg-black/25" x-text="formattedDate"></span>
                    <span class="border border-[#2b2b2b] px-3 py-1 bg-black/25" x-text="formattedTime"></span>
                </div>
            </div>

            <!-- Event Split Layout Mock -->
            <div class="grid gap-4 sm:grid-cols-2">
                <!-- Info & Content -->
                <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.8)] p-4 space-y-4">
                    <!-- Venue details -->
                    <div class="grid gap-2 text-[11px] text-[#7b7b7b] border-l border-[#2b2b2b]/60 pl-3.5 space-y-1">
                        <div>
                            <span class="text-[9px] uppercase tracking-[.08em] text-[#dcdcdc] font-display block">Fecha:</span>
                            <span class="text-[#bcbcbc]" x-text="formattedDate"></span>
                        </div>
                        <div>
                            <span class="text-[9px] uppercase tracking-[.08em] text-[#dcdcdc] font-display block">Hora:</span>
                            <span class="text-[#bcbcbc]" x-text="formattedTime"></span>
                        </div>
                        <div>
                            <span class="text-[9px] uppercase tracking-[.08em] text-[#dcdcdc] font-display block">Ubicación:</span>
                            <span class="text-[#bcbcbc]" x-text="location || 'Ubicación no especificada'"></span>
                        </div>
                        <div>
                            <span class="text-[9px] uppercase tracking-[.08em] text-[#dcdcdc] font-display block">Lugar:</span>
                            <span class="text-[#bcbcbc]" x-text="venue || 'Lugar no especificado'"></span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-wrap gap-1.5 border-t border-[#2b2b2b]/60 pt-3">
                        <span class="border border-[#2b2b2b] px-2.5 py-1 text-[10px] uppercase tracking-wider text-[#bcbcbc] bg-black/20" x-text="ticket_label || 'Tickets'"></span>
                        <template x-if="facebook_url">
                            <span class="border border-[#2b2b2b] px-2.5 py-1 text-[10px] uppercase tracking-wider text-[#bcbcbc] bg-black/20">Facebook</span>
                        </template>
                    </div>

                    <!-- Content Paragraphs -->
                    <div class="border-t border-[#2b2b2b]/60 pt-3 space-y-2 text-[11px] text-[#7b7b7b] max-h-[140px] overflow-y-auto pr-1">
                        <template x-for="(para, idx) in contentParagraphs" :key="idx">
                            <p x-text="para" class="leading-relaxed"></p>
                        </template>
                        <template x-if="contentParagraphs.length === 0">
                            <p class="italic text-[#555] text-[10px]">Ingresa texto en la descripción del evento para previsualizar los párrafos...</p>
                        </template>
                    </div>
                </div>

                <!-- Poster & Video Preview -->
                <div class="space-y-3">
                    <!-- Poster Mock -->
                    <div class="border border-[#2b2b2b] bg-black/40 p-1.5">
                        <div class="relative aspect-[2/3] w-full overflow-hidden bg-[#111] flex items-center justify-center border border-[#2b2b2b]">
                            <img
                                :src="localPosterPreview || (poster ? (poster.startsWith('http') || poster.startsWith('/') || poster.startsWith('assets/') ? poster : '/' + poster) : '')"
                                x-show="localPosterPreview || poster"
                                class="absolute inset-0 h-full w-full object-cover"
                                alt="Póster"
                            >
                            <div class="text-center p-4" x-show="!localPosterPreview && !poster">
                                <span class="block text-2xl mb-1.5 text-[#333]">🖼️</span>
                                <span class="text-[9px] uppercase tracking-wider text-[#666]">Sin Póster</span>
                            </div>
                        </div>
                    </div>

                    <!-- Video Embed Mock -->
                    <div class="border border-[#2b2b2b] bg-black/40 p-1.5">
                        <div class="relative aspect-video w-full overflow-hidden bg-[#111] border border-[#2b2b2b] flex items-center justify-center">
                            <template x-if="embed_url">
                                <iframe :src="embed_url" class="absolute inset-0 h-full w-full" allowfullscreen></iframe>
                            </template>
                            <template x-if="!embed_url">
                                <span class="text-[9px] uppercase tracking-wider text-[#666]">Sin Video Embebido</span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Map Embed Mock -->
            <div class="border border-[#2b2b2b] bg-black/40 p-1.5">
                <div class="relative min-h-[100px] w-full overflow-hidden bg-[#111] border border-[#2b2b2b] flex items-center justify-center">
                    <template x-if="map_url">
                        <iframe :src="map_url" class="absolute inset-0 h-full w-full border-0" allowfullscreen></iframe>
                    </template>
                    <template x-if="!map_url">
                        <span class="text-[9px] uppercase tracking-wider text-[#666]">Sin Mapa de Ubicación</span>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Global form actions placement at the bottom (secondary) -->
    <div class="col-span-full border-t border-[#2b2b2b] pt-6 flex flex-wrap gap-3">
        <button type="submit" class="lucille-button-solid">
            {{ $isEdit ? (!empty($admin['edit_event']) ? $admin['edit_event'] : 'Guardar Evento') : (!empty($admin['new_event']) ? $admin['new_event'] : 'Crear Evento') }}
        </button>
        <a href="{{ route('admin.events.index') }}" class="lucille-button">
            {{ !empty($admin['back_to_events']) ? $admin['back_to_events'] : 'Volver a Eventos' }}
        </a>
    </div>
</div>

<script>
(function() {
    function initEventForm() {
        if (window.Alpine.components && window.Alpine.components['eventForm']) return;
        
        window.Alpine.data('eventForm', () => ({
            tab: 'general',
            title: {!! json_encode(old('title', $event->title)) !!},
            slug: {!! json_encode(old('slug', $event->slug)) !!},
            ticket_label: {!! json_encode(old('ticket_label', $event->ticket_label ?? 'Tickets')) !!},
            starts_at: {!! json_encode(old('starts_at', $startsAt ? $startsAt->format('Y-m-d\TH:i') : '')) !!},
            ends_at: {!! json_encode(old('ends_at', $endsAt ? $endsAt->format('Y-m-d\TH:i') : '')) !!},
            location: {!! json_encode(old('location', $event->location)) !!},
            venue: {!! json_encode(old('venue', $event->venue)) !!},
            categories: {!! json_encode($categoriesValue) !!},
            content: {!! json_encode($contentValue) !!},
            poster: {!! json_encode(old('poster', $event->poster)) !!},
            embed_url: {!! json_encode(old('embed_url', $event->embed_url)) !!},
            map_url: {!! json_encode(old('map_url', $event->map_url)) !!},
            ticket_url: {!! json_encode(old('ticket_url', $event->ticket_url)) !!},
            venue_url: {!! json_encode(old('venue_url', $event->venue_url)) !!},
            facebook_url: {!! json_encode(old('facebook_url', $event->facebook_url)) !!},
            slugManuallyEdited: {!! json_encode($isEdit) !!},
            localPosterPreview: {!! json_encode($posterPreview) !!},

            slugify(text) {
                if (!text) return '';
                return text.toString().toLowerCase().trim()
                    .normalize('NFD').replace(/[\u0300-\u036f]/g, '') // Remove accents
                    .replace(/\s+/g, '-')
                    .replace(/[^\w\-]+/g, '')
                    .replace(/\-\-+/g, '-')
                    .replace(/^-+/, '')
                    .replace(/-+$/, '');
            },
            updateSlug() {
                if (!this.slugManuallyEdited) {
                    this.slug = this.slugify(this.title);
                }
            },
            handlePosterUpload(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        this.localPosterPreview = e.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            },
            get categoriesArray() {
                if (!this.categories) return [];
                return this.categories.split(/[\r\n,]+/).map(c => c.trim()).filter(c => c.length > 0);
            },
            get contentParagraphs() {
                if (!this.content) return [];
                return this.content.split(/\n{2,}/).map(p => p.trim()).filter(p => p.length > 0);
            },
            get formattedDate() {
                if (!this.starts_at) return 'Date';
                const d = new Date(this.starts_at);
                const options = { month: 'long', day: 'numeric', year: 'numeric' };
                return d.toLocaleDateString('en-US', options);
            },
            get formattedTime() {
                if (!this.starts_at) return 'Time';
                const d = new Date(this.starts_at);
                let hours = d.getHours();
                let minutes = d.getMinutes();
                const ampm = hours >= 12 ? 'pm' : 'am';
                hours = hours % 12;
                hours = hours ? hours : 12;
                minutes = minutes < 10 ? '0'+minutes : minutes;
                return hours + ':' + minutes + ' ' + ampm;
            }
        }));
    }

    if (window.Alpine) {
        initEventForm();
    } else {
        document.addEventListener('alpine:init', initEventForm);
    }
})();
</script>
