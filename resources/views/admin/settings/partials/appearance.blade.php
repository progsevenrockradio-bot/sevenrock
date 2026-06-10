<section class="space-y-6">
    <!-- Encabezado de la pestaña -->
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <div class="flex items-center gap-4">
            <div class="text-3xl">🎨</div>
            <div>
                <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Apariencia y Multimedia</h2>
                <p class="mt-1 text-sm leading-7 text-[#7b7b7b]">Aquí se controla la identidad visual del sitio: marca, fuentes, colores y media principal.</p>
            </div>
        </div>
    </div>

    <!-- Secciones en Grid -->
    <div class="grid gap-6 xl:grid-cols-2">
        <!-- SECCIÓN: BRANDING -->
        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8 flex flex-col justify-between">
            <div>
                <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-3 mb-6">📢 Branding e Identidad</h3>
                <div class="space-y-5">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['site_name_label'] }}</label>
                        <input name="site_name" value="{{ old('site_name', $settings->site_name) }}" class="lucille-product-field w-full" placeholder="Nombre de tu emisora">
                        @error('site_name')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['brand_mark_label'] }}</label>
                        <input name="brand_mark" value="{{ old('brand_mark', $settings->brand_mark) }}" x-model="brandMark" class="lucille-product-field w-full" placeholder="Texto de la marca (Header)">
                        <p class="mt-2 text-[11px] text-[#7b7b7b]">Texto visible del logo del header público.</p>
                        @error('brand_mark')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['brand_mark_font_label'] }}</label>
                            <select name="brand_mark_font" x-model="brandMarkFont" class="lucille-product-field lucille-select-field w-full">
                                @foreach ($brandMarkFonts as $value => $label)
                                    <option value="{{ $value }}" @selected(old('brand_mark_font', $settings->brand_mark_font) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('brand_mark_font')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Altura del Logo (Cabecera)</label>
                            <select name="logo_height" class="lucille-product-field lucille-select-field w-full">
                                <option value="45" @selected(old('logo_height', $settings->logo_height) == 45)>Compacto (45px)</option>
                                <option value="62" @selected(old('logo_height', $settings->logo_height) == 62)>Estándar (62px)</option>
                                <option value="80" @selected(old('logo_height', $settings->logo_height) == 80)>Grande (80px)</option>
                                <option value="100" @selected(old('logo_height', $settings->logo_height) == 100)>Extra Grande (100px)</option>
                            </select>
                            @error('logo_height')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['brand_display_mode_label'] }}</label>
                        <select name="brand_display_mode" x-model="brandDisplayMode" class="lucille-product-field lucille-select-field w-full">
                            <option value="mark" @selected(old('brand_display_mode', $settings->brand_display_mode) === 'mark')>Wordmark (Solo texto)</option>
                            <option value="logo" @selected(old('brand_display_mode', $settings->brand_display_mode) === 'logo')>Logo image (Solo imagen)</option>
                            <option value="both" @selected(old('brand_display_mode', $settings->brand_display_mode) === 'both')>Logo & Wordmark (Ambos)</option>
                        </select>
                        <p class="mt-2 text-[11px] text-[#7b7b7b]">{{ $admin['brand_display_mode_help'] }}</p>
                        @error('brand_display_mode')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <!-- LOGO UPLOAD COMPONENT -->
            <div class="mt-6 border border-[#2b2b2b] bg-[rgba(0,0,0,.15)] p-5 rounded-lg">
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['logo_label'] }}</label>
                <div class="flex flex-col sm:flex-row gap-4 items-center">
                    @if($settings->logo_path)
                        <div class="relative group shrink-0 border border-[#3b3b3b] p-3 bg-[rgba(0,0,0,.4)] rounded">
                            <img src="{{ \App\Support\PublicMediaUrl::normalizePublicUrl($settings->logo_path) }}" class="h-14 max-w-[120px] object-contain">
                            <div class="text-[9px] text-center mt-1 text-[#7b7b7b] font-mono truncate max-w-[120px]">{{ basename($settings->logo_path) }}</div>
                        </div>
                    @else
                        <div class="h-16 w-16 bg-[#101012] border border-[#2b2b2b] flex items-center justify-center text-[10px] text-[#7b7b7b] uppercase text-center p-1 leading-tight rounded shrink-0">Sin logo</div>
                    @endif
                    <div class="flex-1 w-full">
                        <input type="file" name="logo" class="lucille-product-field w-full text-xs file:bg-[#16161a] file:border-[#2b2b2b] file:text-[#dcdcdc] file:text-xs">
                        <p class="mt-2 text-[10px] text-[#7b7b7b]">Formato recomendado: PNG transparente o SVG. Máx 4MB.</p>
                    </div>
                </div>
                @error('logo')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
            </div>
        </section>

        <!-- SECCIÓN: HERO Y VIDEO -->
        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8 flex flex-col justify-between">
            <div>
                <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-3 mb-6">🎥 Hero principal (Video / Fondo)</h3>
                
                <div class="space-y-5">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['hero_video_url_label'] }}</label>
                        <input name="hero_video_url" value="{{ old('hero_video_url', $settings->hero_video_url) }}" class="lucille-product-field w-full" placeholder="https://www.youtube.com/... o enlace de video directo">
                        @error('hero_video_url')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                    </div>

                    <!-- VIDEO UPLOAD COMPONENT -->
                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.15)] p-4 rounded">
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['hero_video_file_label'] }}</label>
                        <div class="flex flex-col sm:flex-row gap-4 items-center">
                            <div class="h-16 w-16 bg-[#101012] border border-[#2b2b2b] flex flex-col items-center justify-center text-[#7b7b7b] rounded shrink-0">
                                <span class="text-2xl">📽️</span>
                                @if($settings->hero_video_path)
                                    <span class="text-[9px] text-[#b8e6c3] uppercase mt-1">Activo</span>
                                @else
                                    <span class="text-[9px] uppercase mt-1">Vacío</span>
                                @endif
                            </div>
                            <div class="flex-1 w-full">
                                <input type="file" name="hero_video" class="lucille-product-field w-full text-xs file:bg-[#16161a] file:border-[#2b2b2b] file:text-[#dcdcdc] file:text-xs">
                                @if($settings->hero_video_path)
                                    <p class="mt-1 text-[10px] text-[#7b7b7b] font-mono truncate">Actual: {{ basename($settings->hero_video_path) }}</p>
                                @endif
                            </div>
                        </div>
                        @error('hero_video')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex items-center gap-3 p-2">
                        <input type="checkbox" name="hero_video_disabled" id="hero_video_disabled" value="1" @checked(old('hero_video_disabled', $settings->hero_video_disabled)) class="rounded border-[#2b2b2b] bg-[#101012] text-[#c32720] focus:ring-0">
                        <label for="hero_video_disabled" class="text-xs uppercase tracking-wider text-[#dcdcdc]">{{ $admin['disable_hero_video_label'] }}</label>
                    </div>
                    @error('hero_video_disabled')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-6 border border-[#2b2b2b] bg-[rgba(195,39,32,.06)] p-4 text-xs leading-5 text-[#ffb8b8] rounded">
                💡 El header hero usa primero el enlace de video externo, en su defecto el archivo de video local y, si no hay ninguno, usará la portada/imagen de respaldo.
            </div>
        </section>
    </div>

    <!-- SECCIÓN: COLORES Y TIPOGRAFÍAS -->
    <div class="grid gap-6 xl:grid-cols-2">
        <!-- TIPOGRAFÍA -->
        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-3 mb-6">🔤 Tipografía</h3>
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['body_font_label'] }}</label>
                    <select name="body_font" x-model="bodyFont" class="lucille-product-field lucille-select-field w-full">
                        @foreach ($bodyFonts as $value => $label)
                            <option value="{{ $value }}" @selected(old('body_font', $settings->body_font) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('body_font')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['heading_font_label'] }}</label>
                    <select name="heading_font" x-model="headingFont" class="lucille-product-field lucille-select-field w-full">
                        @foreach ($headingFonts as $value => $label)
                            <option value="{{ $value }}" @selected(old('heading_font', $settings->heading_font) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('heading_font')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <!-- PALETA DE COLORES -->
        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-3 mb-6">🎨 Paleta de Colores</h3>
            <div class="grid gap-5 grid-cols-2 md:grid-cols-3">
                <!-- Acentuado -->
                <div class="space-y-1">
                    <label class="block text-[11px] uppercase tracking-[.12em] text-[#7b7b7b]">{{ $admin['accent_color_label'] }}</label>
                    <div class="flex items-center gap-2">
                        <div class="relative w-9 h-9 overflow-hidden border border-[#2b2b2b] rounded shrink-0">
                            <input type="color" name="accent_color" value="{{ old('accent_color', $settings->accent_color) }}" x-model="accentColor" class="absolute inset-0 cursor-pointer w-[200%] h-[200%] translate-x-[-25%] translate-y-[-25%] border-0 p-0 bg-transparent">
                        </div>
                        <input type="text" x-model="accentColor" class="lucille-product-field uppercase font-mono text-center w-full px-2 py-1.5 text-[11px]" pattern="^#[A-Fa-f0-9]{6}$" placeholder="#FFFFFF">
                    </div>
                    @error('accent_color')<p class="mt-1 text-[10px] text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>

                <!-- Nav -->
                <div class="space-y-1">
                    <label class="block text-[11px] uppercase tracking-[.12em] text-[#7b7b7b]">{{ $admin['nav_color_label'] }}</label>
                    <div class="flex items-center gap-2">
                        <div class="relative w-9 h-9 overflow-hidden border border-[#2b2b2b] rounded shrink-0">
                            <input type="color" name="nav_color" value="{{ old('nav_color', $settings->nav_color) }}" x-model="navColor" class="absolute inset-0 cursor-pointer w-[200%] h-[200%] translate-x-[-25%] translate-y-[-25%] border-0 p-0 bg-transparent">
                        </div>
                        <input type="text" x-model="navColor" class="lucille-product-field uppercase font-mono text-center w-full px-2 py-1.5 text-[11px]" pattern="^#[A-Fa-f0-9]{6}$" placeholder="#FFFFFF">
                    </div>
                    @error('nav_color')<p class="mt-1 text-[10px] text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>

                <!-- Superficie -->
                <div class="space-y-1">
                    <label class="block text-[11px] uppercase tracking-[.12em] text-[#7b7b7b]">{{ $admin['surface_label'] }}</label>
                    <div class="flex items-center gap-2">
                        <div class="relative w-9 h-9 overflow-hidden border border-[#2b2b2b] rounded shrink-0">
                            <input type="color" name="surface_color" value="{{ old('surface_color', $settings->surface_color) }}" x-model="surfaceColor" class="absolute inset-0 cursor-pointer w-[200%] h-[200%] translate-x-[-25%] translate-y-[-25%] border-0 p-0 bg-transparent">
                        </div>
                        <input type="text" x-model="surfaceColor" class="lucille-product-field uppercase font-mono text-center w-full px-2 py-1.5 text-[11px]" pattern="^#[A-Fa-f0-9]{6}$" placeholder="#FFFFFF">
                    </div>
                    @error('surface_color')<p class="mt-1 text-[10px] text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>

                <!-- Texto base -->
                <div class="space-y-1">
                    <label class="block text-[11px] uppercase tracking-[.12em] text-[#7b7b7b]">{{ $admin['body_text_label'] }}</label>
                    <div class="flex items-center gap-2">
                        <div class="relative w-9 h-9 overflow-hidden border border-[#2b2b2b] rounded shrink-0">
                            <input type="color" name="body_color" value="{{ old('body_color', $settings->body_color) }}" x-model="bodyColor" class="absolute inset-0 cursor-pointer w-[200%] h-[200%] translate-x-[-25%] translate-y-[-25%] border-0 p-0 bg-transparent">
                        </div>
                        <input type="text" x-model="bodyColor" class="lucille-product-field uppercase font-mono text-center w-full px-2 py-1.5 text-[11px]" pattern="^#[A-Fa-f0-9]{6}$" placeholder="#FFFFFF">
                    </div>
                    @error('body_color')<p class="mt-1 text-[10px] text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>

                <!-- Texto títulos -->
                <div class="space-y-1">
                    <label class="block text-[11px] uppercase tracking-[.12em] text-[#7b7b7b]">{{ $admin['heading_text_label'] }}</label>
                    <div class="flex items-center gap-2">
                        <div class="relative w-9 h-9 overflow-hidden border border-[#2b2b2b] rounded shrink-0">
                            <input type="color" name="heading_color" value="{{ old('heading_color', $settings->heading_color) }}" x-model="headingColor" class="absolute inset-0 cursor-pointer w-[200%] h-[200%] translate-x-[-25%] translate-y-[-25%] border-0 p-0 bg-transparent">
                        </div>
                        <input type="text" x-model="headingColor" class="lucille-product-field uppercase font-mono text-center w-full px-2 py-1.5 text-[11px]" pattern="^#[A-Fa-f0-9]{6}$" placeholder="#FFFFFF">
                    </div>
                    @error('heading_color')<p class="mt-1 text-[10px] text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>

                <!-- Línea de división -->
                <div class="space-y-1">
                    <label class="block text-[11px] uppercase tracking-[.12em] text-[#7b7b7b]">Líneas de división</label>
                    <div class="flex items-center gap-2">
                        <div class="relative w-9 h-9 overflow-hidden border border-[#2b2b2b] rounded shrink-0">
                            <input type="color" name="line_color" value="{{ old('line_color', $settings->line_color) }}" x-model="lineColor" class="absolute inset-0 cursor-pointer w-[200%] h-[200%] translate-x-[-25%] translate-y-[-25%] border-0 p-0 bg-transparent">
                        </div>
                        <input type="text" x-model="lineColor" class="lucille-product-field uppercase font-mono text-center w-full px-2 py-1.5 text-[11px]" pattern="^#[A-Fa-f0-9]{6}$" placeholder="#FFFFFF">
                    </div>
                    @error('line_color')<p class="mt-1 text-[10px] text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>
    </div>

    <!-- SECCIÓN: MEDIA PRINCIPAL Y MULTIMEDIA -->
    <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-3 mb-6">🏞️ Multimedia Principal y Portadas</h3>
        
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- BACKGROUND DEL SITIO -->
            <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.15)] p-5 rounded">
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['background_label'] }}</label>
                <div class="flex flex-col sm:flex-row gap-4 items-center">
                    @if($settings->background_path)
                        <div class="relative group shrink-0 border border-[#3b3b3b] p-2 bg-[rgba(0,0,0,.4)] rounded">
                            <img src="{{ \App\Support\PublicMediaUrl::normalizePublicUrl($settings->background_path) }}" class="h-14 w-24 object-cover">
                            <div class="text-[9px] text-center mt-1 text-[#7b7b7b] font-mono truncate max-w-[96px]">{{ basename($settings->background_path) }}</div>
                        </div>
                    @else
                        <div class="h-16 w-16 bg-[#101012] border border-[#2b2b2b] flex items-center justify-center text-[10px] text-[#7b7b7b] uppercase text-center p-1 leading-tight rounded shrink-0">Sin fondo</div>
                    @endif
                    <div class="flex-1 w-full">
                        <input type="file" name="background" class="lucille-product-field w-full text-xs file:bg-[#16161a] file:border-[#2b2b2b] file:text-[#dcdcdc] file:text-xs">
                        <p class="mt-2 text-[10px] text-[#7b7b7b]">Imagen principal de fondo de toda la web. Máx 6MB.</p>
                    </div>
                </div>
                @error('background')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
            </div>

            <!-- PORTADA ÁLBUM HOME -->
            <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.15)] p-5 rounded">
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['album_cover_label'] }}</label>
                <div class="flex flex-col sm:flex-row gap-4 items-center">
                    @if($settings->home_album_cover_path)
                        <div class="relative group shrink-0 border border-[#3b3b3b] p-2 bg-[rgba(0,0,0,.4)] rounded">
                            <img src="{{ \App\Support\PublicMediaUrl::normalizePublicUrl($settings->home_album_cover_path) }}" class="h-14 w-14 object-cover">
                            <div class="text-[9px] text-center mt-1 text-[#7b7b7b] font-mono truncate max-w-[56px]">{{ basename($settings->home_album_cover_path) }}</div>
                        </div>
                    @else
                        <div class="h-16 w-16 bg-[#101012] border border-[#2b2b2b] flex items-center justify-center text-[10px] text-[#7b7b7b] uppercase text-center p-1 leading-tight rounded shrink-0">Sin portada</div>
                    @endif
                    <div class="flex-1 w-full">
                        <input type="file" name="home_album_cover" class="lucille-product-field w-full text-xs file:bg-[#16161a] file:border-[#2b2b2b] file:text-[#dcdcdc] file:text-xs">
                        <p class="mt-2 text-[10px] text-[#7b7b7b]">Carátula del álbum mostrada en la sección principal del Home. Máx 6MB.</p>
                    </div>
                </div>
                @error('home_album_cover')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
            </div>

            <!-- ÁLBUM DESTACADO SELECT -->
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Álbum destacado del menú</label>
                <select name="featured_album_slug" class="lucille-product-field lucille-select-field w-full">
                    <option value="">Usar el más reciente automáticamente</option>
                    @foreach ($featuredAlbums ?? [] as $featuredAlbum)
                        <option value="{{ $featuredAlbum['slug'] }}" @selected(old('featured_album_slug', $settings->featured_album_slug) === $featuredAlbum['slug'])>
                            {{ $featuredAlbum['label'] }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs text-[#7b7b7b]">Si eliges uno, el bloque "Álbum" del menú irá siempre a ese detalle.</p>
                @error('featured_album_slug')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
            </div>

            <!-- PORTADA VIDEO HOME -->
            <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.15)] p-5 rounded">
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['featured_video_image_label'] }}</label>
                <div class="flex flex-col sm:flex-row gap-4 items-center">
                    @if($settings->home_video_image_path)
                        <div class="relative group shrink-0 border border-[#3b3b3b] p-2 bg-[rgba(0,0,0,.4)] rounded">
                            <img src="{{ \App\Support\PublicMediaUrl::normalizePublicUrl($settings->home_video_image_path) }}" class="h-14 w-24 object-cover">
                            <div class="text-[9px] text-center mt-1 text-[#7b7b7b] font-mono truncate max-w-[96px]">{{ basename($settings->home_video_image_path) }}</div>
                        </div>
                    @else
                        <div class="h-16 w-16 bg-[#101012] border border-[#2b2b2b] flex items-center justify-center text-[10px] text-[#7b7b7b] uppercase text-center p-1 leading-tight rounded shrink-0">Sin fondo video</div>
                    @endif
                    <div class="flex-1 w-full">
                        <input type="file" name="home_video_image" class="lucille-product-field w-full text-xs file:bg-[#16161a] file:border-[#2b2b2b] file:text-[#dcdcdc] file:text-xs">
                        <p class="mt-2 text-[10px] text-[#7b7b7b]">Imagen miniatura de portada para el bloque de Video del Home. Máx 6MB.</p>
                    </div>
                </div>
                @error('home_video_image')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
            </div>

            <!-- HERO SLIDES EDITOR -->
            <div class="lg:col-span-2 mt-4 border border-[#2b2b2b] bg-[rgba(0,0,0,.15)] p-6 rounded">
                <div x-data="heroSlidesEditor({ slides: {{ Js::from($settings->hero_slides ?? []) }} })" class="space-y-4">
                    <div class="flex items-center justify-between border-b border-[#2b2b2b] pb-3">
                        <div>
                            <h4 class="font-display text-sm uppercase tracking-[.18em] text-[#dcdcdc]">🌄 Carrusel de Diapositivas (Hero Slides)</h4>
                            <p class="text-[11px] text-[#7b7b7b] mt-1">Sube y organiza las imágenes del banner principal giratorio de tu emisora.</p>
                        </div>
                        <button type="button" @click="addSlide()" class="lucille-button-solid text-xs py-1 px-3 bg-[#1e4d2b] border-[#1e4d2b] hover:bg-[#15361e] hover:border-[#15361e]">+ Agregar Slide</button>
                    </div>

                    <div class="space-y-3 max-h-[350px] overflow-y-auto pr-2">
                        <template x-for="(slide, index) in slides" :key="index">
                            <div class="flex items-center gap-3 border border-[#2b2b2b] bg-[#101012] p-4 rounded hover:border-[#3b3b3b] transition-all">
                                <span class="w-8 font-bold text-xs text-[#7b7b7b] font-mono text-center" x-text="'#' + (index + 1)"></span>
                                <input type="file" :name="'hero_slides[' + index + '][file]'" class="lucille-product-field flex-1 text-xs file:bg-[#16161a] file:border-[#2b2b2b] file:text-[#dcdcdc] file:text-xs">
                                <input type="hidden" :name="'hero_slides[' + index + '][image]'" :value="slide.image || ''">
                                <template x-if="slide.image">
                                    <div class="relative group shrink-0 border border-[#2b2b2b] rounded overflow-hidden">
                                        <img :src="previewUrl(slide.image)" class="h-10 w-16 object-cover" alt="">
                                    </div>
                                </template>
                                <button type="button" @click="removeSlide(index)" class="lucille-button text-xs text-[#ff9e9e] border-[#7a2b2b] hover:bg-[rgba(195,39,32,.1)] focus:outline-none" :disabled="slides.length <= 1">✕ Eliminar</button>
                            </div>
                        </template>
                    </div>

                    {{-- Hero Slides Controls --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-[#2b2b2b] pt-4 mt-2">
                        <div class="flex items-center gap-3 justify-between bg-[rgba(0,0,0,.2)] p-3 border border-[#2b2b2b] rounded">
                            <div class="flex-1">
                                <label class="block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Intervalo de Giro</label>
                                <span class="text-[10px] text-[#555]">Segundos entre transiciones automáticas</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="range" name="hero_slides_interval" min="2" max="30" value="{{ old('hero_slides_interval', ($settings->hero_slides_interval ?? 7000) / 1000) }}"
                                    class="w-24 accent-[#c32720]" oninput="this.nextElementSibling.textContent = this.value + 's'">
                                <span class="text-xs font-mono font-bold text-[#dcdcdc] w-8 text-right">{{ old('hero_slides_interval', ($settings->hero_slides_interval ?? 7000) / 1000) }}s</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 justify-between bg-[rgba(0,0,0,.2)] p-3 border border-[#2b2b2b] rounded">
                            <div>
                                <label class="block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Efecto de Transición</label>
                                <span class="text-[10px] text-[#555]">Estilo visual al cambiar la diapositiva</span>
                            </div>
                            <select name="hero_slides_transition" class="lucille-product-field text-xs py-1.5">
                                <option value="fade" {{ old('hero_slides_transition', $settings->hero_slides_transition ?? 'fade') === 'fade' ? 'selected' : '' }}>Fade (desvanecer)</option>
                                <option value="slide" {{ old('hero_slides_transition', $settings->hero_slides_transition ?? 'fade') === 'slide' ? 'selected' : '' }}>Slide (deslizar)</option>
                                <option value="zoom" {{ old('hero_slides_transition', $settings->hero_slides_transition ?? 'fade') === 'zoom' ? 'selected' : '' }}>Zoom (escala)</option>
                            </select>
                        </div>
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
</section>
