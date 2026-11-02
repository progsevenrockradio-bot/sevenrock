<div class="space-y-5 font-sans">
    {{-- Fila 1: Identidad de marca y Cabecera Hero --}}
    <div class="grid gap-5 xl:grid-cols-2">
        {{-- 1.1 Identidad y marca --}}
        <section class="rounded-xl border border-white/10 bg-[#101012] p-5 flex flex-col justify-between space-y-5">
            <div class="space-y-4">
                <div class="border-b border-white/10 pb-3">
                    <h2 class="font-display text-base font-bold uppercase tracking-wide text-[#dcdcdc]">Identidad y marca</h2>
                    <p class="mt-0.5 text-xs text-gray-400">Nombre de la emisora, logotipo y modo de visualización en la cabecera pública.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Nombre del sitio</label>
                        <input name="site_name" value="{{ old('site_name', $settings->site_name) }}"
                               class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                               placeholder="Nombre de la emisora">
                        @error('site_name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Texto de marca (Wordmark)</label>
                        <input name="brand_mark" value="{{ old('brand_mark', $settings->brand_mark) }}" x-model="brandMark"
                               class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                               placeholder="Texto del logo en cabecera">
                        @error('brand_mark')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Fuente de marca</label>
                        <select name="brand_mark_font" x-model="brandMarkFont"
                                class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                            @foreach ($brandMarkFonts as $value => $label)
                                <option value="{{ $value }}" @selected(old('brand_mark_font', $settings->brand_mark_font) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('brand_mark_font')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Altura del logo</label>
                        <select name="logo_height"
                                class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                            <option value="45" @selected(old('logo_height', $settings->logo_height) == 45)>Compacto (45 px)</option>
                            <option value="62" @selected(old('logo_height', $settings->logo_height) == 62)>Estándar (62 px)</option>
                            <option value="80" @selected(old('logo_height', $settings->logo_height) == 80)>Grande (80 px)</option>
                            <option value="100" @selected(old('logo_height', $settings->logo_height) == 100)>Extra grande (100 px)</option>
                        </select>
                        @error('logo_height')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Modo de marca</label>
                        <select name="brand_display_mode" x-model="brandDisplayMode"
                                class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                            <option value="mark" @selected(old('brand_display_mode', $settings->brand_display_mode) === 'mark')>Solo texto</option>
                            <option value="logo" @selected(old('brand_display_mode', $settings->brand_display_mode) === 'logo')>Solo imagen</option>
                            <option value="both" @selected(old('brand_display_mode', $settings->brand_display_mode) === 'both')>Logo y texto</option>
                        </select>
                        @error('brand_display_mode')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            {{-- Subida de logo --}}
            <div class="rounded-lg border border-white/5 bg-[#151518] p-3.5">
                <label class="mb-2 block text-xs font-medium text-[#dcdcdc]">Archivo de logotipo</label>
                <div class="flex flex-col sm:flex-row gap-3 items-center">
                    @if($settings->logo_path)
                        <div class="shrink-0 rounded border border-white/10 bg-black/40 p-2 text-center">
                            <img src="{{ \App\Support\PublicMediaUrl::normalizePublicUrl($settings->logo_path) }}" class="h-12 max-w-[110px] object-contain mx-auto" alt="Logo actual">
                            <div class="mt-1 max-w-[110px] truncate font-mono text-[10px] text-gray-400">{{ basename($settings->logo_path) }}</div>
                        </div>
                    @else
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded border border-white/10 bg-black/30 text-[10px] text-gray-500">Sin logo</div>
                    @endif
                    <div class="flex-1 w-full">
                        <input type="file" name="logo"
                               class="block w-full text-xs text-gray-300 file:mr-3 file:rounded-md file:border file:border-white/10 file:bg-white/5 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-gray-200 hover:file:bg-white/10">
                        <p class="mt-1 text-[11px] text-gray-400">PNG transparente o SVG recomendado. Máximo 4 MB.</p>
                    </div>
                </div>
                @error('logo')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
        </section>

        {{-- 1.2 Hero principal (Vídeo y texto flotante) --}}
        <section class="rounded-xl border border-white/10 bg-[#101012] p-5 flex flex-col justify-between space-y-5">
            <div class="space-y-4">
                <div class="border-b border-white/10 pb-3">
                    <h2 class="font-display text-base font-bold uppercase tracking-wide text-[#dcdcdc]">Cabecera principal (Hero y vídeo)</h2>
                    <p class="mt-0.5 text-xs text-gray-400">Prioridad de reproducción: 1.º URL externa, 2.º archivo de vídeo local, 3.º carrusel de diapositivas.</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">URL externa de vídeo Hero</label>
                    <input name="hero_video_url" value="{{ old('hero_video_url', $settings->hero_video_url) }}"
                           class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                           placeholder="https://www.youtube.com/... o enlace directo MP4/WebM">
                    @error('hero_video_url')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div class="rounded-lg border border-white/5 bg-[#151518] p-3.5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                        <label class="text-xs font-medium text-[#dcdcdc]">Archivo de vídeo local (MP4 / WebM)</label>
                        @if($settings->hero_video_path)
                            <span class="font-mono text-[11px] text-emerald-300 truncate">Activo: {{ basename($settings->hero_video_path) }}</span>
                        @else
                            <span class="text-[11px] text-gray-500">Sin archivo local</span>
                        @endif
                    </div>
                    <input type="file" name="hero_video"
                           class="block w-full text-xs text-gray-300 file:mr-3 file:rounded-md file:border file:border-white/10 file:bg-white/5 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-gray-200 hover:file:bg-white/10">
                    @error('hero_video')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <label class="flex items-center gap-2.5 cursor-pointer pt-1">
                    <input type="checkbox" name="hero_video_disabled" id="hero_video_disabled" value="1"
                           @checked(old('hero_video_disabled', $settings->hero_video_disabled))
                           class="h-4 w-4 rounded border-white/20 bg-black text-[#c32720] focus:ring-[#c32720]">
                    <span class="text-xs text-gray-200">Desactivar vídeo en el Hero (mostrar solo imágenes del carrusel)</span>
                </label>
                @error('hero_video_disabled')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror

                <div class="grid gap-4 sm:grid-cols-2 border-t border-white/10 pt-4">
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Texto flotante sobre el Hero</label>
                        <input name="hero_floating_text" value="{{ old('hero_floating_text', $settings->hero_floating_text) }}"
                               class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                               placeholder="Ej: Todas las épocas del *rock están aquí">
                        <p class="mt-1 text-[10px] text-gray-400">Usa <code>*</code> para resaltar en rojo la parte posterior.</p>
                        @error('hero_floating_text')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Posición del texto flotante</label>
                        <select name="hero_floating_text_position"
                                class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                            <option value="columna-izquierda" @selected(old('hero_floating_text_position', $settings->hero_floating_text_position) === 'columna-izquierda')>Columna izquierda (vertical)</option>
                            <option value="columna-derecha" @selected(old('hero_floating_text_position', $settings->hero_floating_text_position) === 'columna-derecha')>Columna derecha (vertical)</option>
                            <option value="columnas-laterales" @selected(old('hero_floating_text_position', $settings->hero_floating_text_position) === 'columnas-laterales')>Ambas columnas laterales (*)</option>
                            <option value="inferior-izquierda" @selected(old('hero_floating_text_position', $settings->hero_floating_text_position) === 'inferior-izquierda')>Inferior izquierda</option>
                            <option value="inferior-derecha" @selected(old('hero_floating_text_position', $settings->hero_floating_text_position) === 'inferior-derecha')>Inferior derecha</option>
                            <option value="inferior-centro" @selected(old('hero_floating_text_position', $settings->hero_floating_text_position) === 'inferior-centro')>Inferior centro</option>
                        </select>
                        @error('hero_floating_text_position')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- Fila 2: Tipografía y Paleta de colores --}}
    <div class="grid gap-5 xl:grid-cols-12">
        {{-- 2.1 Tipografía --}}
        <section class="xl:col-span-4 rounded-xl border border-white/10 bg-[#101012] p-5 space-y-4">
            <div class="border-b border-white/10 pb-3">
                <h2 class="font-display text-base font-bold uppercase tracking-wide text-[#dcdcdc]">Tipografía del sitio</h2>
                <p class="mt-0.5 text-xs text-gray-400">Fuentes para lectura general y titulares.</p>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Fuente de texto base (Cuerpo)</label>
                    <select name="body_font" x-model="bodyFont"
                            class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                        @foreach ($bodyFonts as $value => $label)
                            <option value="{{ $value }}" @selected(old('body_font', $settings->body_font) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('body_font')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Fuente de titulares (Encabezados)</label>
                    <select name="heading_font" x-model="headingFont"
                            class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                        @foreach ($headingFonts as $value => $label)
                            <option value="{{ $value }}" @selected(old('heading_font', $settings->heading_font) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('heading_font')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        {{-- 2.2 Paleta de colores --}}
        <section class="xl:col-span-8 rounded-xl border border-white/10 bg-[#101012] p-5 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-3 gap-2">
                <div>
                    <h2 class="font-display text-base font-bold uppercase tracking-wide text-[#dcdcdc]">Paleta de colores (Tema Lucille)</h2>
                    <p class="mt-0.5 text-xs text-gray-400">Variables cromáticas principales de la interfaz pública.</p>
                </div>
                <button type="button" @click="resetToDefaults()"
                        class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3 text-xs font-medium text-gray-200 transition-colors hover:bg-white/10 hover:text-white">
                    <span>Restaurar colores originales</span>
                </button>
            </div>

            <div class="grid gap-3 grid-cols-2 sm:grid-cols-3">
                {{-- Acento --}}
                <div class="rounded-lg border border-white/5 bg-[#151518] p-3 space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="text-[11px] font-medium text-gray-300">Color de acento</label>
                        <button type="button" @click="accentColor = '#c32720'" class="text-[10px] text-[#c32720] hover:underline">#c32720</button>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="color" name="accent_color" :value="accentColor" @input="accentColor = $event.target.value" class="h-8 w-9 cursor-pointer rounded border border-white/10 bg-transparent p-0.5 shrink-0">
                        <input type="text" x-model="accentColor" class="h-8 w-full rounded border border-white/10 bg-black/40 px-2 font-mono text-xs uppercase text-white text-center" pattern="^#[A-Fa-f0-9]{6}$">
                    </div>
                    @error('accent_color')<p class="text-[10px] text-red-400">{{ $message }}</p>@enderror
                </div>

                {{-- Navegación --}}
                <div class="rounded-lg border border-white/5 bg-[#151518] p-3 space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="text-[11px] font-medium text-gray-300">Barra de navegación</label>
                        <button type="button" @click="navColor = '#081a24'" class="text-[10px] text-[#c32720] hover:underline">#081a24</button>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="color" name="nav_color" :value="navColor" @input="navColor = $event.target.value" class="h-8 w-9 cursor-pointer rounded border border-white/10 bg-transparent p-0.5 shrink-0">
                        <input type="text" x-model="navColor" class="h-8 w-full rounded border border-white/10 bg-black/40 px-2 font-mono text-xs uppercase text-white text-center" pattern="^#[A-Fa-f0-9]{6}$">
                    </div>
                    @error('nav_color')<p class="text-[10px] text-red-400">{{ $message }}</p>@enderror
                </div>

                {{-- Superficie --}}
                <div class="rounded-lg border border-white/5 bg-[#151518] p-3 space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="text-[11px] font-medium text-gray-300">Superficies y tarjetas</label>
                        <button type="button" @click="surfaceColor = '#101012'" class="text-[10px] text-[#c32720] hover:underline">#101012</button>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="color" name="surface_color" :value="surfaceColor" @input="surfaceColor = $event.target.value" class="h-8 w-9 cursor-pointer rounded border border-white/10 bg-transparent p-0.5 shrink-0">
                        <input type="text" x-model="surfaceColor" class="h-8 w-full rounded border border-white/10 bg-black/40 px-2 font-mono text-xs uppercase text-white text-center" pattern="^#[A-Fa-f0-9]{6}$">
                    </div>
                    @error('surface_color')<p class="text-[10px] text-red-400">{{ $message }}</p>@enderror
                </div>

                {{-- Texto base --}}
                <div class="rounded-lg border border-white/5 bg-[#151518] p-3 space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="text-[11px] font-medium text-gray-300">Texto de párrafos</label>
                        <button type="button" @click="bodyColor = '#7b7b7b'" class="text-[10px] text-[#c32720] hover:underline">#7b7b7b</button>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="color" name="body_color" :value="bodyColor" @input="bodyColor = $event.target.value" class="h-8 w-9 cursor-pointer rounded border border-white/10 bg-transparent p-0.5 shrink-0">
                        <input type="text" x-model="bodyColor" class="h-8 w-full rounded border border-white/10 bg-black/40 px-2 font-mono text-xs uppercase text-white text-center" pattern="^#[A-Fa-f0-9]{6}$">
                    </div>
                    @error('body_color')<p class="text-[10px] text-red-400">{{ $message }}</p>@enderror
                </div>

                {{-- Títulos --}}
                <div class="rounded-lg border border-white/5 bg-[#151518] p-3 space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="text-[11px] font-medium text-gray-300">Texto de titulares</label>
                        <button type="button" @click="headingColor = '#dcdcdc'" class="text-[10px] text-[#c32720] hover:underline">#dcdcdc</button>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="color" name="heading_color" :value="headingColor" @input="headingColor = $event.target.value" class="h-8 w-9 cursor-pointer rounded border border-white/10 bg-transparent p-0.5 shrink-0">
                        <input type="text" x-model="headingColor" class="h-8 w-full rounded border border-white/10 bg-black/40 px-2 font-mono text-xs uppercase text-white text-center" pattern="^#[A-Fa-f0-9]{6}$">
                    </div>
                    @error('heading_color')<p class="text-[10px] text-red-400">{{ $message }}</p>@enderror
                </div>

                {{-- Líneas --}}
                <div class="rounded-lg border border-white/5 bg-[#151518] p-3 space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="text-[11px] font-medium text-gray-300">Líneas divisorias</label>
                        <button type="button" @click="lineColor = '#757575'" class="text-[10px] text-[#c32720] hover:underline">#757575</button>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="color" name="line_color" :value="lineColor" @input="lineColor = $event.target.value" class="h-8 w-9 cursor-pointer rounded border border-white/10 bg-transparent p-0.5 shrink-0">
                        <input type="text" x-model="lineColor" class="h-8 w-full rounded border border-white/10 bg-black/40 px-2 font-mono text-xs uppercase text-white text-center" pattern="^#[A-Fa-f0-9]{6}$">
                    </div>
                    @error('line_color')<p class="text-[10px] text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>
    </div>

    {{-- Fila 3: Multimedia principal, portadas y carrusel Hero --}}
    <section class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-5">
        <div class="border-b border-white/10 pb-3">
            <h2 class="font-display text-base font-bold uppercase tracking-wide text-[#dcdcdc]">Imágenes de respaldo y carrusel de portada</h2>
            <p class="mt-0.5 text-xs text-gray-400">Fondos globales, carátulas destacadas y diapositivas del banner principal.</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            {{-- Fondo del sitio --}}
            <div class="rounded-lg border border-white/5 bg-[#151518] p-3.5">
                <label class="mb-2 block text-xs font-medium text-[#dcdcdc]">Fondo general del sitio</label>
                <div class="flex flex-col sm:flex-row gap-3 items-center">
                    @if($settings->background_path)
                        <div class="shrink-0 rounded border border-white/10 bg-black/40 p-1.5">
                            <img src="{{ \App\Support\PublicMediaUrl::normalizePublicUrl($settings->background_path) }}" class="h-12 w-20 object-cover rounded-sm" alt="Fondo">
                        </div>
                    @else
                        <div class="flex h-12 w-20 shrink-0 items-center justify-center rounded border border-white/10 bg-black/30 text-[10px] text-gray-500">Sin fondo</div>
                    @endif
                    <div class="flex-1 w-full">
                        <input type="file" name="background" class="block w-full text-xs text-gray-300 file:mr-3 file:rounded-md file:border file:border-white/10 file:bg-white/5 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-gray-200 hover:file:bg-white/10">
                        <p class="mt-1 text-[11px] text-gray-400">Imagen de fondo global. Máx. 6 MB.</p>
                    </div>
                </div>
                @error('background')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            {{-- Portada álbum Home --}}
            <div class="rounded-lg border border-white/5 bg-[#151518] p-3.5">
                <label class="mb-2 block text-xs font-medium text-[#dcdcdc]">Portada destacada de álbum (Home)</label>
                <div class="flex flex-col sm:flex-row gap-3 items-center">
                    @if($settings->home_album_cover_path)
                        <div class="shrink-0 rounded border border-white/10 bg-black/40 p-1.5">
                            <img src="{{ \App\Support\PublicMediaUrl::normalizePublicUrl($settings->home_album_cover_path) }}" class="h-12 w-12 object-cover rounded-sm" alt="Portada álbum">
                        </div>
                    @else
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded border border-white/10 bg-black/30 text-[10px] text-gray-500">Vacío</div>
                    @endif
                    <div class="flex-1 w-full">
                        <input type="file" name="home_album_cover" class="block w-full text-xs text-gray-300 file:mr-3 file:rounded-md file:border file:border-white/10 file:bg-white/5 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-gray-200 hover:file:bg-white/10">
                        <p class="mt-1 text-[11px] text-gray-400">Carátula mostrada en el bloque de álbum de portada. Máx. 6 MB.</p>
                    </div>
                </div>
                @error('home_album_cover')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            {{-- Banner Muro del Rock --}}
            <div class="rounded-lg border border-white/5 bg-[#151518] p-3.5">
                <label class="mb-2 block text-xs font-medium text-[#dcdcdc]">Banner del Muro del Rock</label>
                <div class="flex flex-col sm:flex-row gap-3 items-center">
                    @if($settings->talents_banner_path)
                        <div class="shrink-0 rounded border border-white/10 bg-black/40 p-1.5">
                            <img src="{{ \App\Support\PublicMediaUrl::normalizePublicUrl($settings->talents_banner_path) }}" class="h-12 w-20 object-cover rounded-sm" alt="Banner Muro">
                        </div>
                    @else
                        <div class="flex h-12 w-20 shrink-0 items-center justify-center rounded border border-white/10 bg-black/30 text-[10px] text-gray-500">Sin banner</div>
                    @endif
                    <div class="flex-1 w-full">
                        <input type="file" name="talents_banner" class="block w-full text-xs text-gray-300 file:mr-3 file:rounded-md file:border file:border-white/10 file:bg-white/5 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-gray-200 hover:file:bg-white/10">
                        <p class="mt-1 text-[11px] text-gray-400">Imagen de cabecera para el directorio público de bandas. Máx. 6 MB.</p>
                    </div>
                </div>
                @error('talents_banner')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            {{-- Portada Vídeo Home --}}
            <div class="rounded-lg border border-white/5 bg-[#151518] p-3.5">
                <label class="mb-2 block text-xs font-medium text-[#dcdcdc]">Miniatura de vídeo en portada (Home)</label>
                <div class="flex flex-col sm:flex-row gap-3 items-center">
                    @if($settings->home_video_image_path)
                        <div class="shrink-0 rounded border border-white/10 bg-black/40 p-1.5">
                            <img src="{{ \App\Support\PublicMediaUrl::normalizePublicUrl($settings->home_video_image_path) }}" class="h-12 w-20 object-cover rounded-sm" alt="Vídeo Home">
                        </div>
                    @else
                        <div class="flex h-12 w-20 shrink-0 items-center justify-center rounded border border-white/10 bg-black/30 text-[10px] text-gray-500">Sin imagen</div>
                    @endif
                    <div class="flex-1 w-full">
                        <input type="file" name="home_video_image" class="block w-full text-xs text-gray-300 file:mr-3 file:rounded-md file:border file:border-white/10 file:bg-white/5 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-gray-200 hover:file:bg-white/10">
                        <p class="mt-1 text-[11px] text-gray-400">Imagen para el bloque de vídeo destacado en portada. Máx. 6 MB.</p>
                    </div>
                </div>
                @error('home_video_image')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            {{-- Álbum destacado del menú --}}
            <div class="lg:col-span-2 rounded-lg border border-white/5 bg-[#151518] p-3.5">
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Álbum destacado enlazado en el menú público</label>
                <select name="featured_album_slug"
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#101012] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                    <option value="">Seleccionar automáticamente el lanzamiento más reciente</option>
                    @foreach ($featuredAlbums ?? [] as $featuredAlbum)
                        <option value="{{ $featuredAlbum['slug'] }}" @selected(old('featured_album_slug', $settings->featured_album_slug) === $featuredAlbum['slug'])>
                            {{ $featuredAlbum['label'] }}
                        </option>
                    @endforeach
                </select>
                @error('featured_album_slug')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            {{-- Carrusel de diapositivas Hero --}}
            <div class="lg:col-span-2 rounded-lg border border-white/5 bg-[#151518] p-4"
                 x-data="heroSlidesEditor({ slides: {{ Js::from($settings->hero_slides ?? []) }} })">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/10 pb-3 mb-3">
                    <div>
                        <h3 class="font-display text-sm font-bold uppercase tracking-wide text-[#dcdcdc]">Carrusel de diapositivas (Hero Slides)</h3>
                        <p class="text-[11px] text-gray-400">Imágenes rotativas del banner principal cuando no hay vídeo activo.</p>
                    </div>
                    <button type="button" @click="addSlide()"
                            class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3 text-xs font-medium text-gray-200 hover:bg-white/10 hover:text-white">
                        <span>+ Añadir diapositiva</span>
                    </button>
                </div>

                <div class="space-y-2.5 max-h-72 overflow-y-auto pr-1">
                    <template x-for="(slide, index) in slides" :key="index">
                        <div class="flex flex-wrap sm:flex-nowrap items-center gap-3 rounded-lg border border-white/10 bg-[#101012] p-2.5">
                            <span class="w-6 text-center font-mono text-xs font-semibold text-gray-400" x-text="'#' + (index + 1)"></span>
                            <input type="file" :name="'hero_slides[' + index + '][file]'"
                                   class="flex-1 text-xs text-gray-300 file:mr-2 file:rounded file:border file:border-white/10 file:bg-white/5 file:px-2.5 file:py-1 file:text-xs file:text-gray-200">
                            <input type="hidden" :name="'hero_slides[' + index + '][image]'" :value="slide.image || ''">
                            <template x-if="slide.image">
                                <img :src="previewUrl(slide.image)" class="h-9 w-14 shrink-0 rounded border border-white/10 object-cover" alt="">
                            </template>
                            <button type="button" @click="removeSlide(index)" :disabled="slides.length <= 1"
                                    class="inline-flex h-8 items-center rounded-lg border border-red-500/25 bg-red-500/10 px-2.5 text-xs font-medium text-red-300 hover:bg-red-500/20 disabled:opacity-40">
                                Quitar
                            </button>
                        </div>
                    </template>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 border-t border-white/10 pt-3 mt-3">
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-white/5 bg-[#101012] px-3 py-2">
                        <div>
                            <label class="block text-xs font-medium text-[#dcdcdc]">Intervalo de transición</label>
                            <span class="text-[10px] text-gray-400">Segundos entre diapositivas</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="range" name="hero_slides_interval" min="2" max="30"
                                   value="{{ old('hero_slides_interval', ($settings->hero_slides_interval ?? 7000) / 1000) }}"
                                   class="lucille-range-slider w-24"
                                   oninput="this.nextElementSibling.textContent = this.value + 's'">
                            <span class="w-8 text-right font-mono text-xs font-semibold text-[#dcdcdc]">{{ old('hero_slides_interval', ($settings->hero_slides_interval ?? 7000) / 1000) }}s</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3 rounded-lg border border-white/5 bg-[#101012] px-3 py-2">
                        <div>
                            <label class="block text-xs font-medium text-[#dcdcdc]">Efecto de transición</label>
                            <span class="text-[10px] text-gray-400">Animación del carrusel</span>
                        </div>
                        <select name="hero_slides_transition"
                                class="h-8 rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-white focus:border-[#c32720] focus:outline-none">
                            <option value="fade" @selected(old('hero_slides_transition', $settings->hero_slides_transition ?? 'fade') === 'fade')>Desvanecer (Fade)</option>
                            <option value="slide" @selected(old('hero_slides_transition', $settings->hero_slides_transition ?? 'fade') === 'slide')>Deslizar (Slide)</option>
                            <option value="zoom" @selected(old('hero_slides_transition', $settings->hero_slides_transition ?? 'fade') === 'zoom')>Escala (Zoom)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('heroSlidesEditor', (config) => ({
                slides: config.slides || [],
                addSlide() {
                    this.slides.push({ image: '' });
                },
                removeSlide(index) {
                    if (this.slides.length > 1) {
                        this.slides.splice(index, 1);
                    }
                },
                previewUrl(image) {
                    if (!image) {
                        return '';
                    }
                    if (image.startsWith('http://') || image.startsWith('https://') || image.startsWith('/')) {
                        return image;
                    }
                    return '/' + image.replace(/^\/+/, '');
                },
            }));
        });
    </script>
</div>
