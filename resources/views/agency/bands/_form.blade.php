@php $isEdit = $bandProfile->exists; @endphp

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Nombre de la Banda</label>
        <input name="name" value="{{ old('name', $bandProfile->name) }}" class="lucille-product-field w-full" required>
        @error('name')
            <span class="mt-1 block text-xs text-red-400 font-mono uppercase">{{ $message }}</span>
        @enderror
    </div>
    <div x-data="imageHelper('{{ old('image_path', $bandProfile->image_path) }}')">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Ruta de la Imagen de Perfil</label>
        <input 
            name="image_path" 
            x-model="url"
            @input="convert()"
            class="lucille-product-field w-full" 
            placeholder="Ej: catalog/releases/covers/foto.jpg o enlace directo"
        >
        <div class="mt-1.5 p-3 rounded bg-[#16161a] border border-white/5 text-[11px] text-[#8f877d] space-y-1.5">
            <p><strong>¿Cómo subir la imagen de perfil?</strong> Sube tu archivo gratis en <a href="https://imgbb.com" target="_blank" rel="noreferrer" class="text-[#a855f7] hover:underline">ImgBB</a> o <a href="https://postimages.org" target="_blank" rel="noreferrer" class="text-[#a855f7] hover:underline">Postimages</a> y copia el <strong>Enlace directo</strong> (debe terminar en <code>.jpg</code>, <code>.png</code> o <code>.webp</code>).</p>
            <p class="text-amber-400/90 font-mono text-[10px]">⚠️ Google Drive, Facebook e Instagram no se recomiendan porque sus enlaces caducan o se bloquean.</p>
        </div>
        <div x-show="url" class="mt-2" style="display: none;">
            <img :src="url" loading="lazy" class="h-16 w-auto object-contain border border-white/10" alt="Vista previa" x-on:error="$el.style.display='none'">
        </div>
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Fecha de Fundación</label>
        <input type="date" name="founded_date" value="{{ old('founded_date', $bandProfile->founded_date?->format('Y-m-d')) }}" class="lucille-product-field w-full">
    </div>
    <div x-data="imageHelper('{{ old('logo_path', $bandProfile->logo_path) }}')">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">URL del Logotipo</label>
        <input 
            name="logo_path" 
            x-model="url"
            @input="convert()"
            class="lucille-product-field w-full" 
            placeholder="https://example.com/logo.png"
        >
        <div class="mt-1.5 p-3 rounded bg-[#16161a] border border-white/5 text-[11px] text-[#8f877d] space-y-1.5">
            <p><strong>¿Cómo subir el logotipo?</strong> Sube tu archivo gratis en <a href="https://imgbb.com" target="_blank" rel="noreferrer" class="text-[#a855f7] hover:underline">ImgBB</a> o <a href="https://postimages.org" target="_blank" rel="noreferrer" class="text-[#a855f7] hover:underline">Postimages</a> y copia el <strong>Enlace directo</strong> (debe terminar en <code>.jpg</code>, <code>.png</code> o <code>.webp</code>).</p>
            <p class="text-amber-400/90 font-mono text-[10px]">⚠️ Google Drive, Facebook e Instagram no se recomiendan porque sus enlaces caducan o se bloquean.</p>
        </div>
        <div x-show="url" class="mt-2" style="display: none;">
            <img :src="url" loading="lazy" class="h-16 w-auto object-contain border border-white/10" alt="Vista previa" x-on:error="$el.style.display='none'">
        </div>
    </div>
    <div x-data="countrySelector('{{ old('country', $bandProfile->country) }}')" class="relative md:col-span-2">
        <input type="hidden" name="country" :value="getFinalCountry()">
        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">País de Origen</label>
                <div class="relative" @click.away="openCountry = false">
                    <input 
                        type="text" 
                        class="lucille-product-field w-full pr-10 cursor-pointer" 
                        placeholder="Buscar o seleccionar país..."
                        x-model="searchCountry"
                        @focus="openCountry = true"
                        @input="openCountry = true"
                    >
                    <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-[#7b7b7b]">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>

                    <div 
                        x-show="openCountry" 
                        x-transition 
                        class="absolute left-0 z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-white/10 bg-[#141416] p-1 shadow-lg"
                        style="display: none;"
                    >
                        <template x-for="c in countries" :key="c">
                            <button 
                                type="button" 
                                class="w-full text-left px-3 py-1.5 text-sm rounded-sm hover:bg-[#a855f7]/20 text-white transition-colors duration-150"
                                x-show="matchesCountry(c)"
                                @click="selectCountry(c)"
                            >
                                <span x-text="c"></span>
                            </button>
                        </template>
                        <div class="border-t border-white/5 mt-1 pt-1">
                            <button 
                                type="button" 
                                class="w-full text-left px-3 py-1.5 text-sm rounded-sm hover:bg-[#a855f7]/20 text-[#a855f7] font-semibold transition-colors duration-150"
                                x-show="matchesCountry('Otro / Personalizado')"
                                @click="selectCountry('Otro / Personalizado')"
                            >
                                <span>➕ Otro / Personalizado</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <template x-if="selectedCountry && selectedCountry !== 'Otro / Personalizado'">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Estado / Provincia</label>
                        <div class="relative" @click.away="openState = false">
                            <input 
                                type="text" 
                                class="lucille-product-field w-full pr-10 cursor-pointer" 
                                placeholder="Seleccionar estado..."
                                x-model="searchState"
                                @focus="openState = true"
                                @input="openState = true"
                            >
                            <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-[#7b7b7b]">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>

                            <div 
                                x-show="openState" 
                                x-transition 
                                class="absolute left-0 z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-white/10 bg-[#141416] p-1 shadow-lg"
                                style="display: none;"
                            >
                                <template x-for="st in getStates()" :key="st">
                                    <button 
                                        type="button" 
                                        class="w-full text-left px-3 py-1.5 text-sm rounded-sm hover:bg-[#a855f7]/20 text-white transition-colors duration-150"
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
            </div>
        </div>

        <div x-show="selectedCountry === 'Otro / Personalizado'" x-transition class="mt-3 grid gap-3 grid-cols-2 p-3 bg-[#1a1a1e] rounded-md border border-white/5">
            <div>
                <label class="mb-1 block text-[10px] uppercase tracking-wider text-[#7b7b7b]">País Personalizado</label>
                <input 
                    type="text" 
                    class="lucille-product-field w-full text-sm" 
                    placeholder="Ej: Italia"
                    x-model="customCountry"
                >
            </div>
            <div>
                <label class="mb-1 block text-[10px] uppercase tracking-wider text-[#7b7b7b]">Estado / Provincia</label>
                <input 
                    type="text" 
                    class="lucille-product-field w-full text-sm" 
                    placeholder="Ej: Roma"
                    x-model="customState"
                >
            </div>
        </div>
    </div>
    <div x-data="genreSelector('{{ old('genre', $bandProfile->genre) }}')" class="relative">
        <input type="hidden" name="genre" :value="getFinalGenre()">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Género Musical</label>
        
        <div class="relative" @click.away="open = false">
            <div class="flex items-center">
                <input 
                    type="text" 
                    class="lucille-product-field w-full pr-10 cursor-pointer" 
                    placeholder="Buscar o seleccionar género..." 
                    x-model="search"
                    @focus="open = true"
                    @input="open = true"
                >
                <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-[#7b7b7b]">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            <!-- Dropdown -->
            <div 
                x-show="open" 
                x-transition 
                class="absolute left-0 z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-white/10 bg-[#141416] p-1 shadow-lg"
                style="display: none;"
            >
                <template x-for="(options, group) in groupedGenres" :key="group">
                    <div>
                        <div class="px-3 py-1 text-[10px] font-bold uppercase tracking-[.1em] text-[#7b7b7b] bg-white/5 rounded-sm my-1" x-text="group"></div>
                        <template x-for="opt in options" :key="opt">
                            <button 
                                type="button" 
                                class="w-full text-left px-3 py-1.5 text-sm rounded-sm hover:bg-[#a855f7]/20 text-white transition-colors duration-150"
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
                        class="w-full text-left px-3 py-1.5 text-sm rounded-sm hover:bg-[#a855f7]/20 text-[#a855f7] font-semibold transition-colors duration-150"
                        x-show="matches('Otro / Personalizado')"
                        @click="selectGenre('Otro / Personalizado')"
                    >
                        <span>➕ Otro / Personalizado</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Custom inputs -->
        <div x-show="selectedGenre === 'Otro / Personalizado'" x-transition class="mt-3 grid gap-3 grid-cols-2 p-3 bg-[#1a1a1e] rounded-md border border-white/5">
            <div>
                <label class="mb-1 block text-[10px] uppercase tracking-wider text-[#7b7b7b]">Género Personalizado</label>
                <input 
                    type="text" 
                    class="lucille-product-field w-full text-sm" 
                    placeholder="Ej: Gothic"
                    x-model="customGenre"
                >
            </div>
            <div>
                <label class="mb-1 block text-[10px] uppercase tracking-wider text-[#7b7b7b]">Subgénero Personalizado</label>
                <input 
                    type="text" 
                    class="lucille-product-field w-full text-sm" 
                    placeholder="Ej: Symphonic"
                    x-model="customSubgenre"
                >
            </div>
        </div>
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Cantidad de Miembros</label>
        <input type="number" name="members_count" value="{{ old('members_count', $bandProfile->members_count) }}" class="lucille-product-field w-full" min="0">
    </div>
    <div>
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Estado</label>
        <select name="status" class="lucille-product-field w-full">
            <option value="">Seleccionar...</option>
            <option value="active" {{ old('status', $bandProfile->status) === 'active' ? 'selected' : '' }}>Activo</option>
            <option value="on_hold" {{ old('status', $bandProfile->status) === 'on_hold' ? 'selected' : '' }}>En pausa</option>
            <option value="disbanded" {{ old('status', $bandProfile->status) === 'disbanded' ? 'selected' : '' }}>Separados</option>
            <option value="unknown" {{ old('status', $bandProfile->status) === 'unknown' ? 'selected' : '' }}>Desconocido</option>
        </select>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Biografía</label>
        <textarea name="biography" rows="6" class="lucille-product-field w-full" placeholder="Escribe aquí la historia o biografía de la banda...">{{ old('biography', $bandProfile->biography) }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Resumen Editorial</label>
        <textarea name="editorial_summary" rows="4" class="lucille-product-field w-full" placeholder="Breve resumen o slogan de presentación...">{{ old('editorial_summary', $bandProfile->editorial_summary) }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Datos Curiosos (Uno por línea)</label>
        <textarea name="featured_facts_text" rows="5" class="lucille-product-field w-full" placeholder="Ej: Fundada por ex-miembros de Iron Maiden&#10;Ganadores de premio revelación 2025">{{ old('featured_facts_text', $featuredFactsText ?? '') }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Enlaces Oficiales (Formato: Etiqueta|Enlace - uno por línea)</label>
        <textarea name="official_links_text" rows="5" class="lucille-product-field w-full" placeholder="Ej: Facebook|https://facebook.com/banda&#10;Spotify|https://spotify.com/...&#10;Instagram|https://instagram.com/...">{{ old('official_links_text', $officialLinksText ?? '') }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Artistas Relacionados (Uno por línea)</label>
        <textarea name="related_artists_text" rows="4" class="lucille-product-field w-full" placeholder="Ej: Metallica&#10;Megadeth">{{ old('related_artists_text', $relatedArtistsText ?? '') }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Sellos Discográficos (Uno por línea)</label>
        <textarea name="labels" rows="3" class="lucille-product-field w-full" placeholder="Ej: Nuclear Blast&#10;Sony Music">{{ old('labels', $labelsText ?? $bandProfile->labels) }}</textarea>
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit" class="lucille-button-solid">{{ $isEdit ? 'Actualizar Banda' : 'Registrar Banda' }}</button>
    <a href="{{ route('agency.bands') }}" class="lucille-button">Volver a Mis Bandas</a>
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

                // Google Drive View Link to Direct Image Link Conversion
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

                // Dropbox Link to Direct Link Conversion
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

            countries: ['España', 'Venezuela', 'Colombia', 'Estados Unidos', 'México', 'Argentina', 'Chile'],

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
