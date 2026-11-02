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
        <div class="flex gap-2 border-b border-white/10 pb-2 text-xs font-medium">
            <button type="button" @click="tab = 'general'" :class="tab === 'general' ? 'bg-[#c32720] text-white font-semibold' : 'border border-white/10 bg-white/5 text-gray-300 hover:text-white hover:bg-white/10'" class="px-3.5 py-2 rounded-lg transition-colors whitespace-nowrap">
                Información General
            </button>
            <button type="button" @click="tab = 'details'" :class="tab === 'details' ? 'bg-[#c32720] text-white font-semibold' : 'border border-white/10 bg-white/5 text-gray-300 hover:text-white hover:bg-white/10'" class="px-3.5 py-2 rounded-lg transition-colors whitespace-nowrap">
                Detalles & Enlaces
            </button>
            <button type="button" @click="tab = 'media'" :class="tab === 'media' ? 'bg-[#c32720] text-white font-semibold' : 'border border-white/10 bg-white/5 text-gray-300 hover:text-white hover:bg-white/10'" class="px-3.5 py-2 rounded-lg transition-colors whitespace-nowrap">
                Póster & Multimedia
            </button>
        </div>

        <!-- Tab 1: General Info -->
        <div x-show="tab === 'general'" class="space-y-6">
            <section class="rounded-xl border border-white/10 bg-white/[0.02] p-5 sm:p-6 space-y-5">
                <div>
                    <h2 class="font-display text-sm uppercase tracking-wider text-white">Datos Principales</h2>
                    <p class="mt-0.5 text-xs text-gray-400">Datos esenciales y clasificación del evento.</p>
                </div>

                <div class="grid gap-4">
                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Título del evento <span class="text-[#c32720]">*</span></label>
                        <input name="title" x-model="title" @input="updateSlug" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2.5 text-base font-semibold text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none">
                        @error('title')
                            <p class="mt-1 text-xs text-rose-400 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Slug / Enlace permanente</label>
                        <input name="slug" x-model="slug" @input="slugManuallyEdited = true" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-xs font-mono text-gray-300 placeholder-gray-600 focus:border-[#c32720] focus:outline-none">
                        @error('slug')
                            <p class="mt-1 text-xs text-rose-400 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-1">
                        <label class="flex items-center gap-3 text-sm text-gray-300 cursor-pointer">
                            <input type="checkbox" name="is_cancelled" value="1" x-model="is_cancelled" class="h-4 w-4 rounded border-white/20 bg-black/40 text-[#c32720] focus:ring-[#c32720]">
                            <span>Marcar evento como CANCELADO</span>
                        </label>
                        <p class="mt-1 text-xs text-gray-500 pl-7">Muestra la insignia destacada de "Cancelado" en la agenda.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Categorías</label>
                        <input
                            name="categories_text"
                            x-model="categories"
                            class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 placeholder-gray-500 focus:border-[#c32720] focus:outline-none"
                            placeholder="Festival, Hard Rock, Gira Europea"
                        >
                        <p class="mt-1 text-xs text-gray-500">Separa las categorías con comas.</p>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-white/10 bg-white/[0.02] p-5 sm:p-6 space-y-4">
                <div>
                    <h2 class="font-display text-sm uppercase tracking-wider text-white">Descripción del Concierto</h2>
                    <p class="mt-0.5 text-xs text-gray-400">Información sobre horarios de apertura, teloneros y sinopsis.</p>
                </div>

                <div>
                    <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Texto informativo</label>
                    <textarea
                        name="content_text"
                        x-model="content"
                        rows="8"
                        class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2.5 text-sm text-gray-100 placeholder-gray-500 focus:border-[#c32720] focus:outline-none"
                        placeholder="Escribe la descripción aquí. Deja una línea en blanco entre párrafos."
                    ></textarea>
                </div>
            </section>
        </div>

        <!-- Tab 2: Details & Links -->
        <div x-show="tab === 'details'" class="space-y-6" style="display: none;">
            <section class="rounded-xl border border-white/10 bg-white/[0.02] p-5 sm:p-6 space-y-5">
                <div>
                    <h2 class="font-display text-sm uppercase tracking-wider text-white">Fecha & Ubicación</h2>
                    <p class="mt-0.5 text-xs text-gray-400">Detalles de horarios y lugar del espectáculo.</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Fecha y hora de inicio <span class="text-[#c32720]">*</span></label>
                        <input type="datetime-local" name="starts_at" x-model="starts_at" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
                        @error('starts_at')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Fecha y hora de fin (Opcional)</label>
                        <input type="datetime-local" name="ends_at" x-model="ends_at" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none">
                        @error('ends_at')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Ciudad / País</label>
                        <input name="location" x-model="location" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="Madrid, España">
                    </div>
                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Sala / Recinto</label>
                        <input name="venue" x-model="venue" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="WiZink Center">
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-white/10 bg-white/[0.02] p-5 sm:p-6 space-y-5">
                <div>
                    <h2 class="font-display text-sm uppercase tracking-wider text-white">Venta de Entradas & Enlaces</h2>
                    <p class="mt-0.5 text-xs text-gray-400">Configuración de botones de compra y redes.</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Texto del botón de entradas</label>
                        <input name="ticket_label" x-model="ticket_label" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="Comprar Entradas">
                    </div>
                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Enlace de venta de entradas</label>
                        <input name="ticket_url" x-model="ticket_url" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="https://ticketmaster.es/...">
                        @error('ticket_url')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Web de la Sala (Venue URL)</label>
                        <input name="venue_url" x-model="venue_url" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="https://...">
                    </div>
                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Evento en Facebook / Redes</label>
                        <input name="facebook_url" x-model="facebook_url" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="https://facebook.com/events/...">
                    </div>
                </div>
            </section>
        </div>

        <!-- Tab 3: Poster & Media -->
        <div x-show="tab === 'media'" class="space-y-6" style="display: none;">
            <section class="rounded-xl border border-white/10 bg-white/[0.02] p-5 sm:p-6 space-y-5">
                <div>
                    <h2 class="font-display text-sm uppercase tracking-wider text-white">Cartel / Póster Oficial</h2>
                    <p class="mt-0.5 text-xs text-gray-400">Sube una imagen o proporciona una URL externa.</p>
                </div>

                <div class="grid gap-4">
                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Ruta o URL de Imagen</label>
                        <input name="poster" x-model="poster" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="https://... o ruta relativa">
                    </div>

                    <div class="rounded-xl border border-white/10 bg-black/40 p-4">
                        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-2">Subir archivo de imagen local</label>
                        <input type="file" name="poster_file" @change="handlePosterUpload" class="block w-full text-xs text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-white/10 file:text-gray-200 hover:file:bg-white/20">
                        <p class="mt-2 text-[11px] text-gray-500">Formatos recomendados: JPG, PNG o WebP hasta 6MB.</p>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-white/10 bg-white/[0.02] p-5 sm:p-6 space-y-5">
                <div>
                    <h2 class="font-display text-sm uppercase tracking-wider text-white">Vídeo Embebido & Mapa</h2>
                    <p class="mt-0.5 text-xs text-gray-400">Inserta códigos embebidos para enriquecer la ficha del concierto.</p>
                </div>

                <div class="grid gap-4">
                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Vídeo Embed URL (YouTube)</label>
                        <input name="embed_url" x-model="embed_url" @input="embed_url = cleanIframeSrc(embed_url)" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="https://www.youtube.com/embed/...">
                    </div>

                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wider text-gray-300 mb-1.5">Google Maps Embed URL</label>
                        <input name="map_url" x-model="map_url" @input="map_url = cleanIframeSrc(map_url)" class="w-full rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 text-sm text-gray-100 focus:border-[#c32720] focus:outline-none" placeholder="https://www.google.com/maps/embed?...">
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- Right Column: Live Interactive Preview -->
    <div class="space-y-6">
        <div class="sticky top-[100px] rounded-xl border border-white/10 bg-black/40 p-5 space-y-5 shadow-xl">
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-400">Vista Previa en Vivo</span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-2 w-2 rounded-full bg-[#c32720] animate-pulse"></span>
                    <span class="text-[10px] uppercase tracking-wider text-gray-400">Actualizando</span>
                </span>
            </div>

            <!-- Page Header Mock -->
            <div class="rounded-xl border border-white/10 bg-[#101012] p-5 text-center">
                <!-- Categories Badges -->
                <div class="mb-3 flex flex-wrap justify-center gap-1.5">
                    <template x-for="cat in categoriesArray" :key="cat">
                        <span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-0.5 text-[10px] font-medium text-gray-300" x-text="cat"></span>
                    </template>
                    <template x-if="categoriesArray.length === 0">
                        <span class="rounded-full border border-dashed border-white/10 px-2.5 py-0.5 text-[10px] text-gray-500">Sin categorías</span>
                    </template>
                </div>

                <!-- Event Title -->
                <div class="relative inline-block mt-2">
                    <h1 class="font-display text-xl sm:text-2xl uppercase tracking-wider break-words leading-tight" :class="is_cancelled ? 'text-gray-500 line-through' : 'text-white'" x-text="title || 'Título del Evento'"></h1>
                    <template x-if="is_cancelled">
                        <span class="absolute -top-3 -right-6 rotate-[12deg] rounded border border-rose-500 bg-rose-950 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-rose-200 shadow-md">Cancelado</span>
                    </template>
                </div>
                <p class="mt-1 text-[10px] uppercase tracking-widest text-[#c32720] font-semibold">Seven Rock Radio • Conciertos</p>

                <!-- Date & Time badges -->
                <div class="mt-3 flex flex-wrap justify-center gap-2 text-xs text-gray-300">
                    <span class="rounded-md border border-white/10 px-2.5 py-1 bg-white/5" x-text="formattedDate"></span>
                    <span class="rounded-md border border-white/10 px-2.5 py-1 bg-white/5" x-text="formattedTime"></span>
                </div>
            </div>

            <!-- Event Split Layout Mock -->
            <div class="grid gap-4 sm:grid-cols-2">
                <!-- Info & Content -->
                <div class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3">
                    <div class="space-y-2 text-xs text-gray-400">
                        <div>
                            <span class="text-[10px] uppercase tracking-wider text-gray-500 block">Ubicación:</span>
                            <span class="text-white font-medium" x-text="location || 'No especificada'"></span>
                        </div>
                        <div>
                            <span class="text-[10px] uppercase tracking-wider text-gray-500 block">Sala:</span>
                            <span class="text-white font-medium" x-text="venue || 'No especificada'"></span>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-white/10">
                        <span class="inline-flex items-center justify-center h-7 px-3 rounded bg-[#c32720] text-white text-[11px] font-semibold" x-text="ticket_label || 'Tickets'"></span>
                    </div>

                    <!-- Content Paragraphs -->
                    <div class="pt-2 border-t border-white/10 space-y-2 text-xs text-gray-400 max-h-[120px] overflow-y-auto">
                        <template x-for="(para, idx) in contentParagraphs" :key="idx">
                            <p x-text="para" class="leading-relaxed"></p>
                        </template>
                    </div>
                </div>

                <!-- Poster Mock -->
                <div class="space-y-3">
                    <div class="rounded-xl border border-white/10 bg-[#101012] p-2">
                        <div class="relative aspect-[3/4] w-full overflow-hidden rounded-lg bg-black flex items-center justify-center border border-white/5">
                            <img
                                :src="localPosterPreview || (poster ? (poster.startsWith('http') || poster.startsWith('/') || poster.startsWith('assets/') ? poster : '/' + poster) : '')"
                                x-show="localPosterPreview || poster"
                                class="absolute inset-0 h-full w-full object-cover"
                                alt="Póster"
                            >
                            <div class="text-center p-4" x-show="!localPosterPreview && !poster">
                                <span class="block text-2xl mb-1 text-gray-600">🖼️</span>
                                <span class="text-[10px] uppercase tracking-wider text-gray-500">Sin Póster</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Global form actions placement at the bottom -->
    <div class="col-span-full border-t border-white/10 pt-6 flex flex-wrap gap-3">
        <button type="submit" class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-lg bg-[#c32720] hover:bg-[#a8201a] text-white text-xs font-semibold tracking-wide shadow-md shadow-red-950/40 transition-all">
            {{ $isEdit ? 'Guardar Evento' : 'Crear Evento' }}
        </button>
        <a href="{{ route('admin.events.index') }}" class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all">
            Volver a Eventos
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
            localPosterPreview: {!! json_encode($posterPreview ?? null) !!},
            is_cancelled: {!! json_encode(old('is_cancelled', $event->is_cancelled)) !!},

            slugify(text) {
                if (!text) return '';
                return text.toString().toLowerCase().trim()
                    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
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
            cleanIframeSrc(value) {
                if (!value) return '';
                const trimmed = value.trim();
                if (trimmed.startsWith('<iframe')) {
                    const match = trimmed.match(/src=["']([^"']+)["']/i);
                    if (match && match[1]) {
                        return match[1];
                    }
                }
                return value;
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
                if (!this.starts_at) return 'Fecha';
                const d = new Date(this.starts_at);
                const options = { month: 'short', day: 'numeric', year: 'numeric' };
                return d.toLocaleDateString('es-ES', options);
            },
            get formattedTime() {
                if (!this.starts_at) return 'Hora';
                const d = new Date(this.starts_at);
                let hours = d.getHours().toString().padStart(2, '0');
                let minutes = d.getMinutes().toString().padStart(2, '0');
                return hours + ':' + minutes + ' h';
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
