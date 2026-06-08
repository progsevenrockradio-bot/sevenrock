<section class="space-y-6">
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Apariencia y Multimedia</h2>
        <p class="mt-3 text-sm leading-7 text-[#7b7b7b]">Aquí se controla la identidad visual del sitio: marca, fuentes, colores y media principal.</p>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Branding</h3>
            <div class="mt-6 space-y-5">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['site_name_label'] }}</label>
                    <input name="site_name" value="{{ old('site_name', $settings->site_name) }}" class="lucille-product-field w-full">
                    @error('site_name')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['brand_mark_label'] }}</label>
                    <input name="brand_mark" value="{{ old('brand_mark', $settings->brand_mark) }}" x-model="brandMark" class="lucille-product-field w-full">
                    <p class="mt-2 text-xs text-[#7b7b7b]">Texto visible del logo del header público.</p>
                    @error('brand_mark')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
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
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['brand_display_mode_label'] }}</label>
                    <select name="brand_display_mode" x-model="brandDisplayMode" class="lucille-product-field lucille-select-field w-full">
                        <option value="mark" @selected(old('brand_display_mode', $settings->brand_display_mode) === 'mark')>Wordmark</option>
                        <option value="logo" @selected(old('brand_display_mode', $settings->brand_display_mode) === 'logo')>Logo image</option>
                        <option value="both" @selected(old('brand_display_mode', $settings->brand_display_mode) === 'both')>Logo & Wordmark</option>
                    </select>
                    <p class="mt-2 text-xs text-[#7b7b7b]">{{ $admin['brand_display_mode_help'] }}</p>
                    @error('brand_display_mode')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['logo_label'] }}</label>
                    <input type="file" name="logo" class="block w-full text-sm text-[#7b7b7b]">
                    <p class="mt-2 text-xs text-[#7b7b7b]">{{ $admin['current_label'] }}: {{ $settings->logo_path }}</p>
                    @error('logo')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Altura del Logo (Cabecera)</label>
                    <select name="logo_height" class="lucille-product-field lucille-select-field w-full">
                        <option value="45" @selected(old('logo_height', $settings->logo_height) == 45)>Compacto (45px)</option>
                        <option value="62" @selected(old('logo_height', $settings->logo_height) == 62)>Estándar (62px)</option>
                        <option value="80" @selected(old('logo_height', $settings->logo_height) == 80)>Grande (80px)</option>
                        <option value="100" @selected(old('logo_height', $settings->logo_height) == 100)>Extra Grande (100px)</option>
                    </select>
                    <p class="mt-2 text-xs text-[#7b7b7b]">Selecciona el tamaño de visualización de tu logotipo en la cabecera del sitio público.</p>
                    @error('logo_height')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['background_label'] }}</label>
                    <input type="file" name="background" class="block w-full text-sm text-[#7b7b7b]">
                    <p class="mt-2 text-xs text-[#7b7b7b]">{{ $admin['current_label'] }}: {{ $settings->background_path }}</p>
                    @error('background')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Hero</h3>
            <div class="mt-6 space-y-5">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['hero_video_file_label'] }}</label>
                    <input type="file" name="hero_video" class="block w-full text-sm text-[#7b7b7b]">
                    <p class="mt-2 text-xs text-[#7b7b7b]">{{ $admin['current_label'] }}: {{ $settings->hero_video_path ?: $admin['not_set'] }}</p>
                    @error('hero_video')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['hero_video_url_label'] }}</label>
                    <input name="hero_video_url" value="{{ old('hero_video_url', $settings->hero_video_url) }}" class="lucille-product-field w-full">
                    @error('hero_video_url')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-center gap-3 text-sm text-[#dcdcdc]">
                    <input type="checkbox" name="hero_video_disabled" value="1" @checked(old('hero_video_disabled', $settings->hero_video_disabled))>
                    {{ $admin['disable_hero_video_label'] }}
                </label>
                @error('hero_video_disabled')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.22)] p-4 text-xs leading-6 text-[#8b8b8b]">
                    El hero usa primero la URL externa válida, después el video local y, si no existe nada, cae al fallback de imagen.
                </div>
            </div>
        </section>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Tipografía</h3>
            <div class="mt-6 grid gap-5 md:grid-cols-2">
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

        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Colores</h3>
            <div class="mt-6 grid gap-5 md:grid-cols-3">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['accent_color_label'] }}</label>
                    <input type="color" name="accent_color" value="{{ old('accent_color', $settings->accent_color) }}" x-model="accentColor" class="h-12 w-full bg-transparent">
                    @error('accent_color')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['nav_color_label'] }}</label>
                    <input type="color" name="nav_color" value="{{ old('nav_color', $settings->nav_color) }}" x-model="navColor" class="h-12 w-full bg-transparent">
                    @error('nav_color')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['surface_label'] }}</label>
                    <input type="color" name="surface_color" value="{{ old('surface_color', $settings->surface_color) }}" x-model="surfaceColor" class="h-12 w-full bg-transparent">
                    @error('surface_color')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['body_text_label'] }}</label>
                    <input type="color" name="body_color" value="{{ old('body_color', $settings->body_color) }}" x-model="bodyColor" class="h-12 w-full bg-transparent">
                    @error('body_color')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['heading_text_label'] }}</label>
                    <input type="color" name="heading_color" value="{{ old('heading_color', $settings->heading_color) }}" x-model="headingColor" class="h-12 w-full bg-transparent">
                    @error('heading_color')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Line color</label>
                    <input type="color" name="line_color" value="{{ old('line_color', $settings->line_color) }}" class="h-12 w-full bg-transparent">
                    @error('line_color')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>
    </div>

    <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Media principal</h3>
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <div class="lg:col-span-2">
                <div x-data="heroSlidesEditor({ slides: {{ Js::from($settings->hero_slides ?? []) }} })" class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-5">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <label class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Hero Slides</label>
                        <button type="button" @click="addSlide()" class="lucille-button text-xs">+ Agregar slide</button>
                    </div>

                    <template x-for="(slide, index) in slides" :key="index">
                        <div class="mb-3 flex items-center gap-3 border border-[#242424] bg-[#131313] p-3">
                            <span class="w-8 text-xs text-[#7b7b7b]" x-text="index + 1"></span>
                            <input type="file" :name="'hero_slides[' + index + '][file]'" class="lucille-product-field flex-1 text-xs">
                            <input type="hidden" :name="'hero_slides[' + index + '][image]'" :value="slide.image || ''">
                            <template x-if="slide.image">
                                <img :src="previewUrl(slide.image)" class="h-12 w-20 border border-[#2b2b2b] object-cover" alt="">
                            </template>
                            <button type="button" @click="removeSlide(index)" class="text-xs text-[#ff9e9e]" :disabled="slides.length <= 1">✕</button>
                        </div>
                    </template>
                </div>

                {{-- Hero Slides Controls --}}
                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Intervalo (segundos)</label>
                        <input type="range" name="hero_slides_interval" min="2" max="30" value="{{ old('hero_slides_interval', ($settings->hero_slides_interval ?? 7000) / 1000) }}"
                            class="w-full accent-[#c32720]" oninput="this.nextElementSibling.textContent = this.value + 's'">
                        <span class="ml-2 text-xs text-[#7b7b7b]">{{ old('hero_slides_interval', ($settings->hero_slides_interval ?? 7000) / 1000) }}s</span>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Efecto de transición</label>
                        <select name="hero_slides_transition" class="lucille-product-field w-full text-xs">
                            <option value="fade" {{ old('hero_slides_transition', $settings->hero_slides_transition ?? 'fade') === 'fade' ? 'selected' : '' }}>Fade (desvanecer)</option>
                            <option value="slide" {{ old('hero_slides_transition', $settings->hero_slides_transition ?? 'fade') === 'slide' ? 'selected' : '' }}>Slide (deslizar)</option>
                            <option value="zoom" {{ old('hero_slides_transition', $settings->hero_slides_transition ?? 'fade') === 'zoom' ? 'selected' : '' }}>Zoom (escala)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['album_cover_label'] }}</label>
                <input type="file" name="home_album_cover" class="block w-full text-sm text-[#7b7b7b]">
                <p class="mt-2 text-xs text-[#7b7b7b]">{{ $admin['current_label'] }}: {{ $settings->home_album_cover_path }}</p>
                @error('home_album_cover')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
            </div>
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
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['featured_video_image_label'] }}</label>
                <input type="file" name="home_video_image" class="block w-full text-sm text-[#7b7b7b]">
                <p class="mt-2 text-xs text-[#7b7b7b]">{{ $admin['current_label'] }}: {{ $settings->home_video_image_path }}</p>
                @error('home_video_image')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
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
