@php
    $isEdit = $bandProfile->exists;
    $agencies = \App\Models\Agency::query()->orderBy('name')->get();
@endphp

@if ($errors->any())
    <div class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-xs text-red-200" role="alert">
        <div class="font-semibold mb-1">Revisa los siguientes errores:</div>
        <ul class="list-disc list-inside space-y-0.5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Bloque 1: Identificación y recursos visuales --}}
<div class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-white/5 pb-3">
        <div>
            <h2 class="font-display text-sm uppercase tracking-wider text-white">Identificación e imágenes</h2>
            <p class="text-[11px] text-gray-400">Datos básicos del artista, fotografía principal y logotipo para el reproductor.</p>
        </div>
        @if ($isEdit)
            <button
                type="button"
                id="autoGenerateBtn"
                data-url="{{ route('admin.radio-artists.auto-generate', $bandProfile) }}"
                class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-white/15 bg-white/[0.06] px-3 text-xs font-medium text-white hover:bg-white/[0.12] focus:outline-none focus:ring-2 focus:ring-[#c32720]"
                title="Consulta Discogs, Wikipedia y Last.fm para autocompletar campos"
            >
                <svg class="h-3.5 w-3.5 text-[#c32720]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <span>Autocompletar desde fuentes externas</span>
            </button>
        @endif
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="artist-name" class="mb-1.5 block text-xs font-medium text-gray-300">Nombre del artista o banda</label>
            <input
                id="artist-name"
                name="name"
                value="{{ old('name', $bandProfile->name) }}"
                required
                class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
            >
        </div>

        <div>
            <label for="artist-founded-date" class="mb-1.5 block text-xs font-medium text-gray-300">Fecha de fundación</label>
            <input
                id="artist-founded-date"
                type="date"
                name="founded_date"
                value="{{ old('founded_date', $bandProfile->founded_date?->format('Y-m-d')) }}"
                class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
            >
        </div>

        <div x-data="imageHelper('{{ old('image_path', $bandProfile->image_path) }}')">
            <label for="artist-image-path" class="mb-1.5 block text-xs font-medium text-gray-300">Ruta o URL de la imagen principal</label>
            <input
                id="artist-image-path"
                name="image_path"
                x-model="url"
                @input="convert()"
                placeholder="Ej: catalog/releases/covers/foto.jpg o URL directa"
                class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
            >
            <p class="mt-1 text-[11px] text-gray-400">Usa enlaces directos (`.jpg`, `.png`, `.webp`) o rutas internas del catálogo.</p>
            <div x-show="url" class="mt-2 flex items-center gap-2.5" style="display: none;">
                <img :src="url" loading="lazy" class="h-12 w-12 rounded-lg object-cover border border-white/10 bg-[#151518]" alt="Vista previa de imagen" x-on:error="$el.style.display='none'">
                <a :href="url" target="_blank" rel="noreferrer" class="text-xs font-medium text-sky-300 hover:underline">Ver imagen completa</a>
            </div>
        </div>

        <div x-data="imageHelper('{{ old('logo_path', $bandProfile->logo_path) }}')">
            <label for="artist-logo-path" class="mb-1.5 block text-xs font-medium text-gray-300">URL del logotipo (opcional)</label>
            <input
                id="artist-logo-path"
                name="logo_path"
                x-model="url"
                @input="convert()"
                placeholder="https://ejemplo.com/logo.png"
                class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
            >
            <p class="mt-1 text-[11px] text-gray-400">Enlace directo al logotipo oficial de la banda.</p>
            <div x-show="url" class="mt-2 flex items-center gap-2.5" style="display: none;">
                <img :src="url" loading="lazy" class="h-12 w-auto max-w-[120px] rounded-lg object-contain border border-white/10 bg-[#151518] p-1" alt="Vista previa de logotipo" x-on:error="$el.style.display='none'">
                <a :href="url" target="_blank" rel="noreferrer" class="text-xs font-medium text-sky-300 hover:underline">Ver logotipo</a>
            </div>
        </div>
    </div>
</div>

{{-- Bloque 2: Clasificación y procedencia --}}
<div class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-4">
    <div class="border-b border-white/5 pb-2.5">
        <h2 class="font-display text-sm uppercase tracking-wider text-white">Clasificación, origen y estado</h2>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div x-data="countrySelector('{{ old('country', $bandProfile->country) }}')" class="relative sm:col-span-2">
            <input type="hidden" name="country" :value="getFinalCountry()">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="artist-country-search" class="mb-1.5 block text-xs font-medium text-gray-300">País de origen</label>
                    <div class="relative" @click.away="openCountry = false">
                        <input
                            id="artist-country-search"
                            type="text"
                            class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] pl-3 pr-8 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                            placeholder="Buscar o seleccionar país..."
                            x-model="searchCountry"
                            @focus="openCountry = true"
                            @input="openCountry = true"
                        >
                        <div class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>

                        <div
                            x-show="openCountry"
                            x-transition
                            class="absolute left-0 z-40 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-white/10 bg-[#151518] p-1 shadow-xl"
                            style="display: none;"
                        >
                            <template x-for="c in countries" :key="c">
                                <button
                                    type="button"
                                    class="w-full text-left px-3 py-1.5 text-xs rounded hover:bg-white/[0.08] text-gray-200 hover:text-white"
                                    x-show="matchesCountry(c)"
                                    @click="selectCountry(c)"
                                >
                                    <span x-text="c"></span>
                                </button>
                            </template>
                            <div class="border-t border-white/5 mt-1 pt-1">
                                <button
                                    type="button"
                                    class="w-full text-left px-3 py-1.5 text-xs rounded hover:bg-white/[0.08] text-[#c32720] font-semibold"
                                    x-show="matchesCountry('Otro / Personalizado')"
                                    @click="selectCountry('Otro / Personalizado')"
                                >
                                    <span>+ Otro / Personalizado</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <template x-if="selectedCountry && selectedCountry !== 'Otro / Personalizado' && hasPredefinedStates()">
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-300">Estado o provincia</label>
                            <div class="relative" @click.away="openState = false">
                                <input
                                    type="text"
                                    class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] pl-3 pr-8 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                                    placeholder="Seleccionar estado o región..."
                                    x-model="searchState"
                                    @focus="openState = true"
                                    @input="openState = true"
                                >
                                <div
                                    x-show="openState"
                                    x-transition
                                    class="absolute left-0 z-40 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-white/10 bg-[#151518] p-1 shadow-xl"
                                    style="display: none;"
                                >
                                    <template x-for="st in getStates()" :key="st">
                                        <button
                                            type="button"
                                            class="w-full text-left px-3 py-1.5 text-xs rounded hover:bg-white/[0.08] text-gray-200 hover:text-white"
                                            x-show="matchesState(st)"
                                            @click="selectState(st)"
                                        >
                                            <span x-text="st"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template x-if="selectedCountry && selectedCountry !== 'Otro / Personalizado' && !hasPredefinedStates()">
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-300">Ciudad o región</label>
                            <input
                                type="text"
                                class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                                placeholder="Ej: Londres, Hamburgo..."
                                x-model="selectedState"
                            >
                        </div>
                    </template>
                </div>
            </div>

            <div x-show="selectedCountry === 'Otro / Personalizado'" x-transition class="mt-3 grid gap-3 sm:grid-cols-2 rounded-lg border border-white/5 bg-[#151518] p-3">
                <div>
                    <label class="mb-1 block text-[11px] font-medium text-gray-400">País personalizado</label>
                    <input type="text" class="h-8 w-full rounded border border-white/10 bg-[#101012] px-2.5 text-xs text-white" placeholder="Ej: Italia" x-model="customCountry">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-medium text-gray-400">Ciudad o provincia</label>
                    <input type="text" class="h-8 w-full rounded border border-white/10 bg-[#101012] px-2.5 text-xs text-white" placeholder="Ej: Roma" x-model="customState">
                </div>
            </div>
        </div>

        <div x-data="genreSelector('{{ old('genre', $bandProfile->genre) }}')" class="relative">
            <input type="hidden" name="genre" :value="getFinalGenre()">
            <label for="artist-genre-search" class="mb-1.5 block text-xs font-medium text-gray-300">Género principal</label>
            <div class="relative" @click.away="open = false">
                <input
                    id="artist-genre-search"
                    type="text"
                    class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] pl-3 pr-8 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    placeholder="Buscar o seleccionar género..."
                    x-model="search"
                    @focus="open = true"
                    @input="open = true"
                >
                <div class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>

                <div
                    x-show="open"
                    x-transition
                    class="absolute left-0 z-40 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-white/10 bg-[#151518] p-1 shadow-xl"
                    style="display: none;"
                >
                    <template x-for="(options, group) in groupedGenres" :key="group">
                        <div>
                            <div class="px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-gray-400 bg-white/[0.03] rounded my-0.5" x-text="group"></div>
                            <template x-for="opt in options" :key="opt">
                                <button
                                    type="button"
                                    class="w-full text-left px-3 py-1.5 text-xs rounded hover:bg-white/[0.08] text-gray-200 hover:text-white"
                                    x-show="matches(opt)"
                                    @click="selectGenre(opt)"
                                >
                                    <span x-text="opt"></span>
                                </button>
                            </template>
                        </div>
                    </template>
                    <div class="border-t border-white/5 mt-1 pt-1">
                        <button
                            type="button"
                            class="w-full text-left px-3 py-1.5 text-xs rounded hover:bg-white/[0.08] text-[#c32720] font-semibold"
                            x-show="matches('Otro / Personalizado')"
                            @click="selectGenre('Otro / Personalizado')"
                        >
                            <span>+ Otro / Personalizado</span>
                        </button>
                    </div>
                </div>
            </div>

            <div x-show="selectedGenre === 'Otro / Personalizado'" x-transition class="mt-3 grid gap-3 grid-cols-2 rounded-lg border border-white/5 bg-[#151518] p-3">
                <div>
                    <label class="mb-1 block text-[11px] font-medium text-gray-400">Género</label>
                    <input type="text" class="h-8 w-full rounded border border-white/10 bg-[#101012] px-2.5 text-xs text-white" placeholder="Ej: Metal Gótico" x-model="customGenre">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-medium text-gray-400">Subgénero</label>
                    <input type="text" class="h-8 w-full rounded border border-white/10 bg-[#101012] px-2.5 text-xs text-white" placeholder="Ej: Sinfónico" x-model="customSubgenre">
                </div>
            </div>
        </div>

        <div>
            <label for="artist-members-count" class="mb-1.5 block text-xs font-medium text-gray-300">Número de integrantes</label>
            <input
                id="artist-members-count"
                type="number"
                name="members_count"
                value="{{ old('members_count', $bandProfile->members_count) }}"
                min="0"
                class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
            >
        </div>

        <div>
            <label for="artist-status" class="mb-1.5 block text-xs font-medium text-gray-300">Estado de actividad</label>
            <select
                id="artist-status"
                name="status"
                class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
            >
                <option value="">Sin especificar</option>
                <option value="active" {{ old('status', $bandProfile->status) === 'active' ? 'selected' : '' }}>En activo</option>
                <option value="on_hold" {{ old('status', $bandProfile->status) === 'on_hold' ? 'selected' : '' }}>En pausa</option>
                <option value="disbanded" {{ old('status', $bandProfile->status) === 'disbanded' ? 'selected' : '' }}>Separado / Disuelto</option>
                <option value="unknown" {{ old('status', $bandProfile->status) === 'unknown' ? 'selected' : '' }}>Desconocido</option>
            </select>
        </div>

        <div>
            <label for="artist-agency" class="mb-1.5 block text-xs font-medium text-gray-300">Agencia o colaborador asociado</label>
            <select
                id="artist-agency"
                name="agency_id"
                class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
            >
                <option value="">Ninguna</option>
                @foreach($agencies as $agency)
                    <option value="{{ $agency->id }}" {{ old('agency_id', $bandProfile->agency_id) == $agency->id ? 'selected' : '' }}>
                        {{ $agency->name }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
</div>

{{-- Bloque 3: Contenido biográfico y editorial completo --}}
<div class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-4">
    <div class="border-b border-white/5 pb-2.5">
        <h2 class="font-display text-sm uppercase tracking-wider text-white">Contenido biográfico y datos editoriales</h2>
        <p class="text-[11px] text-gray-400">Aquí se conserva el texto íntegro que alimenta la ventana y el panel del reproductor.</p>
    </div>

    <div class="space-y-4">
        <div>
            <label for="artist-editorial-summary" class="mb-1.5 block text-xs font-medium text-gray-300">Resumen editorial (reproductor)</label>
            <textarea
                id="artist-editorial-summary"
                name="editorial_summary"
                rows="4"
                class="w-full rounded-lg border border-white/10 bg-[#151518] p-3 text-xs leading-relaxed text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
            >{{ old('editorial_summary', $bandProfile->editorial_summary) }}</textarea>
        </div>

        <div>
            <label for="artist-biography" class="mb-1.5 block text-xs font-medium text-gray-300">Biografía completa</label>
            <textarea
                id="artist-biography"
                name="biography"
                rows="7"
                class="w-full rounded-lg border border-white/10 bg-[#151518] p-3 text-xs leading-relaxed text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
            >{{ old('biography', $bandProfile->biography) }}</textarea>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="artist-featured-facts" class="mb-1.5 block text-xs font-medium text-gray-300">Datos curiosos o hitos (uno por línea)</label>
                <textarea
                    id="artist-featured-facts"
                    name="featured_facts_text"
                    rows="4"
                    placeholder="Un dato destacado por línea..."
                    class="w-full rounded-lg border border-white/10 bg-[#151518] p-3 text-xs leading-relaxed text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                >{{ old('featured_facts_text', $featuredFactsText ?? '') }}</textarea>
            </div>

            <div>
                <label for="artist-official-links" class="mb-1.5 block text-xs font-medium text-gray-300">Enlaces oficiales (Etiqueta|URL por línea)</label>
                <textarea
                    id="artist-official-links"
                    name="official_links_text"
                    rows="4"
                    placeholder="Sitio oficial|https://ejemplo.com"
                    class="w-full rounded-lg border border-white/10 bg-[#151518] p-3 text-xs leading-relaxed text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                >{{ old('official_links_text', $officialLinksText ?? '') }}</textarea>
            </div>

            <div>
                <label for="artist-related-artists" class="mb-1.5 block text-xs font-medium text-gray-300">Artistas relacionados (uno por línea)</label>
                <textarea
                    id="artist-related-artists"
                    name="related_artists_text"
                    rows="3"
                    placeholder="Un artista o banda relacionada por línea..."
                    class="w-full rounded-lg border border-white/10 bg-[#151518] p-3 text-xs leading-relaxed text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                >{{ old('related_artists_text', $relatedArtistsText ?? '') }}</textarea>
            </div>

            <div>
                <label for="artist-labels" class="mb-1.5 block text-xs font-medium text-gray-300">Sellos discográficos</label>
                <textarea
                    id="artist-labels"
                    name="labels"
                    rows="3"
                    placeholder="Sellos discográficos asociados..."
                    class="w-full rounded-lg border border-white/10 bg-[#151518] p-3 text-xs leading-relaxed text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                >{{ old('labels', $labelsText ?? $bandProfile->labels) }}</textarea>
            </div>
        </div>

        <div>
            <label for="artist-source" class="mb-1.5 block text-xs font-medium text-gray-300">Fuente editorial</label>
            <input
                id="artist-source"
                name="source"
                value="{{ old('source', $bandProfile->source ?: 'Seven Rock Radio') }}"
                class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
            >
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2.5 pt-3 border-t border-white/5">
        <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#a8201a] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
            {{ $isEdit ? 'Guardar cambios' : 'Crear perfil de artista' }}
        </button>
        <a href="{{ route('admin.radio-artists.index') }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3.5 text-xs font-medium text-gray-300 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
            Cancelar
        </a>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    if (!Alpine.data('genreSelector')) {
        Alpine.data('genreSelector', (initialValue) => ({
            open: false,
            search: '',
            selectedGenre: '',
            customGenre: '',
            customSubgenre: '',
            genres: {
                'Rock': [
                    'Rock and Roll',
                    'Rock Clásico',
                    'Hard Rock',
                    'Rock Psicodélico',
                    'Punk Rock',
                    'Rock Progresivo',
                    'Grunge',
                    'Rock Alternativo / Indie'
                ],
                'Metal': [
                    'Heavy Metal',
                    'Thrash Metal',
                    'Death Metal',
                    'Black Metal',
                    'Power Metal',
                    'Doom Metal',
                    'Nu Metal',
                    'Metal Progresivo'
                ]
            },
            init() {
                const allStandard = Object.values(this.genres).flat();
                if (initialValue) {
                    if (allStandard.includes(initialValue)) {
                        this.selectedGenre = initialValue;
                        this.search = initialValue;
                    } else if (initialValue.includes(' - ')) {
                        this.selectedGenre = 'Otro / Personalizado';
                        this.search = 'Otro / Personalizado';
                        const parts = initialValue.split(' - ');
                        this.customGenre = parts[0] ? parts[0].trim() : '';
                        this.customSubgenre = parts[1] ? parts[1].trim() : '';
                    } else {
                        this.selectedGenre = 'Otro / Personalizado';
                        this.search = 'Otro / Personalizado';
                        this.customGenre = initialValue.trim();
                        this.customSubgenre = '';
                    }
                }
            },
            get groupedGenres() {
                return this.genres;
            },
            matches(name) {
                if (!this.search || this.search === this.selectedGenre) return true;
                return name.toLowerCase().includes(this.search.toLowerCase());
            },
            selectGenre(genre) {
                this.selectedGenre = genre;
                this.search = genre;
                this.open = false;
            },
            getFinalGenre() {
                if (this.selectedGenre === 'Otro / Personalizado') {
                    if (this.customGenre && this.customSubgenre) {
                        return `${this.customGenre.trim()} - ${this.customSubgenre.trim()}`;
                    }
                    return this.customGenre.trim();
                }
                return this.selectedGenre;
            }
        }));
    }

    if (!Alpine.data('imageHelper')) {
        Alpine.data('imageHelper', (initialValue) => ({
            url: initialValue || '',
            convert() {
                if (!this.url) return;
                let val = this.url.trim();
                if (val.includes('drive.google.com')) {
                    let id = '';
                    const fileDMatch = val.match(/\/file\/d\/([a-zA-Z0-9_-]+)/);
                    const idParamMatch = val.match(/[?&]id=([a-zA-Z0-9_-]+)/);
                    if (fileDMatch && fileDMatch[1]) {
                        id = fileDMatch[1];
                    } else if (idParamMatch && idParamMatch[1]) {
                        id = idParamMatch[1];
                    }
                    if (id) {
                        this.url = `https://drive.google.com/uc?export=view&id=${id}`;
                    }
                }
                if (val.includes('dropbox.com')) {
                    this.url = val.replace('www.dropbox.com', 'dl.dropboxusercontent.com').replace(/[?&]dl=[01]/, '');
                }
            }
        }));
    }

    if (!Alpine.data('countrySelector')) {
        Alpine.data('countrySelector', (initialValue) => ({
            openCountry: false,
            openState: false,
            searchCountry: '',
            searchState: '',
            selectedCountry: '',
            selectedState: '',
            customCountry: '',
            customState: '',
            countries: [
                'Estados Unidos', 'Canadá', 'México', 'Reino Unido', 'Alemania', 'Francia',
                'Países Bajos', 'Bélgica', 'Suiza', 'Austria', 'Finlandia', 'Suecia',
                'Noruega', 'Islandia', 'Dinamarca', 'España', 'Italia', 'Portugal',
                'Grecia', 'Polonia', 'República Checa', 'Hungría', 'Rusia', 'Ucrania',
                'Argentina', 'Brasil', 'Chile', 'Colombia', 'Perú', 'Uruguay',
                'Venezuela', 'Panamá', 'Japón', 'Corea del Sur', 'Indonesia', 'India',
                'Israel', 'Taiwán', 'Australia', 'Nueva Zelanda'
            ],
            states: {
                'España': ['Madrid', 'Barcelona', 'Valencia', 'Sevilla', 'Zaragoza', 'Málaga', 'Murcia', 'Palma', 'Las Palmas', 'Bilbao', 'Alicante', 'Córdoba', 'Valladolid', 'Vigo', 'Gijón', 'L\'Hospitalet', 'Vitoria', 'A Coruña', 'Elche', 'Granada', 'Terrassa', 'Badalona', 'Oviedo', 'Cartagena', 'Sabadell', 'Jerez', 'Móstoles', 'Santa Cruz de Tenerife', 'Pamplona', 'Almería'],
                'Venezuela': ['Caracas', 'Zulia', 'Miranda', 'Carabobo', 'Lara', 'Aragua', 'Bolívar', 'Anzoátegui', 'Táchira', 'Monagas', 'Falcón', 'Sucre', 'Portuguesa', 'Mérida', 'Yaracuy', 'Barinas', 'Guárico', 'Trujillo', 'Nueva Esparta', 'Apure', 'Cojedes', 'Amazonas', 'Delta Amacuro', 'Vargas (La Guaira)'],
                'Colombia': ['Bogotá', 'Medellín (Antioquia)', 'Cali (Valle del Cauca)', 'Barranquilla (Atlántico)', 'Cartagena (Bolívar)', 'Bucaramanga (Santander)', 'Ibagué (Tolima)', 'Manizales (Caldas)', 'Pereira (Risaralda)', 'Neiva (Huila)', 'Armenia (Quindío)', 'Cúcuta (Norte de Santander)'],
                'Estados Unidos': ['Alabama', 'Alaska', 'Arizona', 'Arkansas', 'California', 'Colorado', 'Connecticut', 'Delaware', 'Florida', 'Georgia', 'Hawaii', 'Idaho', 'Illinois', 'Indiana', 'Iowa', 'Kansas', 'Kentucky', 'Louisiana', 'Maine', 'Maryland', 'Massachusetts', 'Michigan', 'Minnesota', 'Mississippi', 'Missouri', 'Montana', 'Nebraska', 'Nevada', 'New Hampshire', 'New Jersey', 'New Mexico', 'New York', 'North Carolina', 'North Dakota', 'Ohio', 'Oklahoma', 'Oregon', 'Pennsylvania', 'Rhode Island', 'South Carolina', 'South Dakota', 'Tennessee', 'Texas', 'Utah', 'Vermont', 'Virginia', 'Washington', 'West Virginia', 'Wisconsin', 'Wyoming'],
                'México': ['Aguascalientes', 'Baja California', 'Baja California Sur', 'Campeche', 'Chiapas', 'Chihuahua', 'Coahuila', 'Colima', 'Ciudad de México', 'Durango', 'Guanajuato', 'Guerrero', 'Hidalgo', 'Jalisco', 'Estado de México', 'Michoacán', 'Morelos', 'Nayarit', 'Nuevo León', 'Oaxaca', 'Puebla', 'Querétaro', 'Quintana Roo', 'San Luis Potosí', 'Sinaloa', 'Sonora', 'Tabasco', 'Tamaulipas', 'Tlaxcala', 'Veracruz', 'Yucatán', 'Zacatecas'],
                'Argentina': ['Buenos Aires', 'Catamarca', 'Chaco', 'Chubut', 'Córdoba', 'Corrientes', 'Entre Ríos', 'Formosa', 'Jujuy', 'La Pampa', 'La Rioja', 'Mendoza', 'Misiones', 'Neuquén', 'Río Negro', 'Salta', 'San Juan', 'San Luis', 'Santa Cruz', 'Santa Fe', 'Santiago del Estero', 'Tierra del Fuego', 'Tucumán'],
                'Chile': ['Santiago', 'Valparaíso', 'Concepción', 'Coquimbo', 'Antofagasta', 'Temuco', 'Rancagua', 'Iquique', 'Talca', 'Puerto Montt', 'Chillán', 'Arica', 'Valdivia', 'Punta Arenas', 'Copiapó', 'Curicó', 'Osorno']
            },
            init() {
                if (initialValue) {
                    if (initialValue.includes(' - ')) {
                        const parts = initialValue.split(' - ');
                        const countryPart = parts[0].trim();
                        const statePart = parts[1].trim();
                        if (this.countries.includes(countryPart)) {
                            this.selectedCountry = countryPart;
                            this.searchCountry = countryPart;
                            this.selectedState = statePart;
                            this.searchState = statePart;
                        } else {
                            this.selectedCountry = 'Otro / Personalizado';
                            this.searchCountry = 'Otro / Personalizado';
                            this.customCountry = countryPart;
                            this.customState = statePart;
                        }
                    } else {
                        if (this.countries.includes(initialValue.trim())) {
                            this.selectedCountry = initialValue.trim();
                            this.searchCountry = initialValue.trim();
                        } else {
                            this.selectedCountry = 'Otro / Personalizado';
                            this.searchCountry = 'Otro / Personalizado';
                            this.customCountry = initialValue.trim();
                        }
                    }
                }
            },
            hasPredefinedStates() {
                return ['España', 'Venezuela', 'Colombia', 'Estados Unidos', 'México', 'Argentina', 'Chile'].includes(this.selectedCountry);
            },
            getStates() {
                return this.states[this.selectedCountry] || [];
            },
            matchesCountry(name) {
                if (!this.searchCountry || this.searchCountry === this.selectedCountry) return true;
                return name.toLowerCase().includes(this.searchCountry.toLowerCase());
            },
            matchesState(name) {
                if (!this.searchState || this.searchState === this.selectedState) return true;
                return name.toLowerCase().includes(this.searchState.toLowerCase());
            },
            selectCountry(country) {
                this.selectedCountry = country;
                this.searchCountry = country;
                this.selectedState = '';
                this.searchState = '';
                this.openCountry = false;
            },
            selectState(state) {
                this.selectedState = state;
                this.searchState = state;
                this.openState = false;
            },
            getFinalCountry() {
                if (this.selectedCountry === 'Otro / Personalizado') {
                    if (this.customCountry && this.customState) {
                        return `${this.customCountry.trim()} - ${this.customState.trim()}`;
                    }
                    return this.customCountry.trim();
                }
                if (this.selectedCountry && this.selectedState) {
                    return `${this.selectedCountry} - ${this.selectedState}`;
                }
                return this.selectedCountry;
            }
        }));
    }
});
</script>
@endpush
