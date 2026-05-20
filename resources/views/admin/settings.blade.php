<x-layouts.admin :title="($themeAppearance['admin_texts']['theme_settings'] ?? 'Theme settings').' - '.$themeSettings->site_name">
    @php
        $bodyFonts = $fonts['body'];
        $headingFonts = $fonts['heading'];
        $brandMarkFonts = $fonts['brand_mark'];
        $admin = $themeAppearance['admin_texts'];
    @endphp

    @if (session('status'))
        <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
            {{ session('status') }}
        </div>
    @endif

    <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data" class="space-y-8" x-data="{
            brandMark: @js(old('brand_mark', $settings->brand_mark)),
            brandMarkFont: @js(old('brand_mark_font', $settings->brand_mark_font)),
            brandDisplayMode: @js(old('brand_display_mode', $settings->brand_display_mode)),
            bodyFont: @js(old('body_font', $settings->body_font)),
            headingFont: @js(old('heading_font', $settings->heading_font)),
            accentColor: @js(old('accent_color', $settings->accent_color)),
            navColor: @js(old('nav_color', $settings->nav_color)),
            surfaceColor: @js(old('surface_color', $settings->surface_color)),
            bodyColor: @js(old('body_color', $settings->body_color)),
            headingColor: @js(old('heading_color', $settings->heading_color)),
        }">
        @csrf
        @method('PUT')

        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $admin['theme_settings'] }}</h1>
            <p class="mt-3 text-[#7b7b7b]">{{ $admin['theme_settings_copy'] }}</p>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
                <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $admin['branding_section'] }}</h2>
                <div class="mt-6 space-y-5">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['site_name_label'] }}</label>
                        <input name="site_name" value="{{ old('site_name', $settings->site_name) }}" class="lucille-product-field w-full">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['brand_mark_label'] }}</label>
                        <input name="brand_mark" value="{{ old('brand_mark', $settings->brand_mark) }}" x-model="brandMark" class="lucille-product-field w-full">
                        <p class="mt-2 text-xs text-[#7b7b7b]">Texto visible del logo del header público.</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['brand_mark_font_label'] }}</label>
                        <select name="brand_mark_font" x-model="brandMarkFont" class="lucille-product-field lucille-select-field w-full">
                            @foreach ($brandMarkFonts as $value => $label)
                                <option value="{{ $value }}" @selected(old('brand_mark_font', $settings->brand_mark_font) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['brand_display_mode_label'] }}</label>
                        <select name="brand_display_mode" x-model="brandDisplayMode" class="lucille-product-field lucille-select-field w-full">
                            <option value="mark" @selected(old('brand_display_mode', $settings->brand_display_mode) === 'mark')>Wordmark</option>
                            <option value="logo" @selected(old('brand_display_mode', $settings->brand_display_mode) === 'logo')>Logo image</option>
                        </select>
                        <p class="mt-2 text-xs text-[#7b7b7b]">{{ $admin['brand_display_mode_help'] }}</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['logo_label'] }}</label>
                        <input type="file" name="logo" class="block w-full text-sm text-[#7b7b7b]">
                        <p class="mt-2 text-xs text-[#7b7b7b]">{{ $admin['current_label'] }}: {{ $settings->logo_path }}</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['background_label'] }}</label>
                        <input type="file" name="background" class="block w-full text-sm text-[#7b7b7b]">
                        <p class="mt-2 text-xs text-[#7b7b7b]">{{ $admin['current_label'] }}: {{ $settings->background_path }}</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['hero_video_file_label'] }}</label>
                        <input type="file" name="hero_video" class="block w-full text-sm text-[#7b7b7b]">
                        <p class="mt-2 text-xs text-[#7b7b7b]">{{ $admin['current_label'] }}: {{ $settings->hero_video_path ?: $admin['not_set'] }}</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['hero_video_url_label'] }}</label>
                        <input name="hero_video_url" value="{{ old('hero_video_url', $settings->hero_video_url) }}" class="lucille-product-field w-full">
                    </div>
                    <label class="flex items-center gap-3 text-sm text-[#dcdcdc]">
                        <input type="checkbox" name="hero_video_disabled" value="1" @checked(old('hero_video_disabled', $settings->hero_video_disabled))>
                        {{ $admin['disable_hero_video_label'] }}
                    </label>
                    <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.2)] p-5">
                        <h3 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">{{ $admin['brand_preview'] }}</h3>
                        <div class="mt-4 space-y-4">
                            <div class="flex h-28 items-center justify-center border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] px-4">
                                <template x-if="brandDisplayMode === 'logo'">
                                    <img src="{{ $settings->logo_url }}" alt="{{ $settings->site_name }}" class="max-h-20 w-auto object-contain">
                                </template>
                                <template x-if="brandDisplayMode !== 'logo'">
                                    <span class="lucille-brand-mark text-[2rem]" :style="`font-family: '${brandMarkFont}', cursive; color: var(--color-lucille-accent);`" x-text="brandMark"></span>
                                </template>
                            </div>
                            <div class="grid grid-cols-2 gap-3 text-xs uppercase tracking-[.14em] text-[#7b7b7b]">
                                <div class="border border-[#2b2b2b] p-3">
                                    <div class="mb-2">{{ $admin['brand_preview_accent_label'] }}</div>
                                    <div class="h-8 border border-[#2b2b2b]" :style="`background:${accentColor};`"></div>
                                </div>
                                <div class="border border-[#2b2b2b] p-3">
                                    <div class="mb-2">{{ $admin['brand_preview_nav_label'] }}</div>
                                    <div class="h-8 border border-[#2b2b2b]" :style="`background:${navColor};`"></div>
                                </div>
                                <div class="border border-[#2b2b2b] p-3">
                                    <div class="mb-2">{{ $admin['brand_preview_surface_label'] }}</div>
                                    <div class="h-8 border border-[#2b2b2b]" :style="`background:${surfaceColor};`"></div>
                                </div>
                                <div class="border border-[#2b2b2b] p-3">
                                    <div class="mb-2">{{ $admin['brand_preview_body_head_label'] }}</div>
                                    <div class="flex h-8 overflow-hidden border border-[#2b2b2b]">
                                        <div class="w-1/2" :style="`background:${bodyColor};`"></div>
                                        <div class="w-1/2" :style="`background:${headingColor};`"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
                <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $admin['typography_section'] }}</h2>
                <div class="mt-6 grid gap-5">
                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['body_font_label'] }}</label>
                            <select name="body_font" x-model="bodyFont" class="lucille-product-field lucille-select-field w-full">
                                @foreach ($bodyFonts as $value => $label)
                                    <option value="{{ $value }}" @selected(old('body_font', $settings->body_font) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['heading_font_label'] }}</label>
                            <select name="heading_font" x-model="headingFont" class="lucille-product-field lucille-select-field w-full">
                                @foreach ($headingFonts as $value => $label)
                                    <option value="{{ $value }}" @selected(old('heading_font', $settings->heading_font) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['accent_color_label'] }}</label>
                            <input type="color" name="accent_color" value="{{ old('accent_color', $settings->accent_color) }}" x-model="accentColor" class="h-12 w-full bg-transparent">
                        </div>
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['nav_color_label'] }}</label>
                            <input type="color" name="nav_color" value="{{ old('nav_color', $settings->nav_color) }}" x-model="navColor" class="h-12 w-full bg-transparent">
                        </div>
                    </div>

                    <div class="grid gap-5 md:grid-cols-3">
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['surface_label'] }}</label>
                            <input type="color" name="surface_color" value="{{ old('surface_color', $settings->surface_color) }}" x-model="surfaceColor" class="h-12 w-full bg-transparent">
                        </div>
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['body_text_label'] }}</label>
                            <input type="color" name="body_color" value="{{ old('body_color', $settings->body_color) }}" x-model="bodyColor" class="h-12 w-full bg-transparent">
                        </div>
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['heading_text_label'] }}</label>
                            <input type="color" name="heading_color" value="{{ old('heading_color', $settings->heading_color) }}" x-model="headingColor" class="h-12 w-full bg-transparent">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $admin['main_media_section'] }}</h2>
            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['hero_slide_1_label'] }}</label>
                    <input type="file" name="hero_slide_primary" class="block w-full text-sm text-[#7b7b7b]">
                    <p class="mt-2 text-xs text-[#7b7b7b]">{{ $admin['current_label'] }}: {{ $settings->hero_slide_primary_path }}</p>
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['hero_slide_2_label'] }}</label>
                    <input type="file" name="hero_slide_secondary" class="block w-full text-sm text-[#7b7b7b]">
                    <p class="mt-2 text-xs text-[#7b7b7b]">{{ $admin['current_label'] }}: {{ $settings->hero_slide_secondary_path }}</p>
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['album_cover_label'] }}</label>
                    <input type="file" name="home_album_cover" class="block w-full text-sm text-[#7b7b7b]">
                    <p class="mt-2 text-xs text-[#7b7b7b]">{{ $admin['current_label'] }}: {{ $settings->home_album_cover_path }}</p>
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['featured_video_image_label'] }}</label>
                    <input type="file" name="home_video_image" class="block w-full text-sm text-[#7b7b7b]">
                    <p class="mt-2 text-xs text-[#7b7b7b]">{{ $admin['current_label'] }}: {{ $settings->home_video_image_path }}</p>
                </div>
            </div>
        </section>

        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $admin['home_editorial_section'] }}</h2>
            <p class="mt-3 text-sm leading-7 text-[#7b7b7b]">{{ $admin['json_edit_note'] }}</p>
            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['featured_stories_json_label'] }}</label>
                    <textarea name="featured_stories_json" rows="22" class="lucille-product-field w-full font-mono text-[12px] leading-6">{{ old('featured_stories_json', $featuredStoriesJson ?? '') }}</textarea>
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['latest_podcasts_json_label'] }}</label>
                    <textarea name="latest_podcasts_json" rows="22" class="lucille-product-field w-full font-mono text-[12px] leading-6">{{ old('latest_podcasts_json', $latestPodcastsJson ?? '') }}</textarea>
                </div>
            </div>
            <div class="mt-6">
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['home_headings_json_label'] }}</label>
                <textarea name="home_headings_json" rows="22" class="lucille-product-field w-full font-mono text-[12px] leading-6">{{ old('home_headings_json', $homeHeadingsJson ?? '') }}</textarea>
            </div>
            <div class="mt-6">
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['ui_texts_json_label'] }}</label>
                <textarea name="ui_texts_json" rows="24" class="lucille-product-field w-full font-mono text-[12px] leading-6">{{ old('ui_texts_json', $uiTextsJson ?? '') }}</textarea>
            </div>
            <div class="mt-6">
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['admin_texts_json_label'] }}</label>
                <textarea name="admin_texts_json" rows="22" class="lucille-product-field w-full font-mono text-[12px] leading-6">{{ old('admin_texts_json', $adminTextsJson ?? '') }}</textarea>
            </div>
        </section>

        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Notificaciones y contacto</h2>
            <p class="mt-3 text-sm leading-7 text-[#7b7b7b]">Aquí defines los valores globales por defecto. Los programas maestros pueden sobrescribir el correo principal y la copia de notificación cuando lo necesiten.</p>
            <div class="mt-6 border border-[#2b2b2b] bg-[rgba(0,0,0,.22)] p-5">
                <h3 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">Guía rápida</h3>
                <ol class="mt-4 space-y-2 text-sm leading-6 text-[#b8b8b8]">
                    <li>1. Completa <span class="text-[#e0e0e0]">notification_email</span> con el correo principal global.</li>
                    <li>2. Completa <span class="text-[#e0e0e0]">notification_copy_email</span> con la copia global de la radio.</li>
                    <li>3. Define <span class="text-[#e0e0e0]">notification_from_email</span> y <span class="text-[#e0e0e0]">notification_reply_to_email</span> con un remitente válido.</li>
                    <li>4. Si un programa maestro necesita otra dirección, edita <span class="text-[#e0e0e0]">email_notificacion</span> y <span class="text-[#e0e0e0]">email_copia_notificacion</span> en su ficha.</li>
                </ol>
            </div>
            <div class="mt-6 border border-[#2b2b2b] bg-[rgba(0,0,0,.22)] p-5">
                <h3 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">Estado actual</h3>
                <p class="mt-2 text-sm leading-6 text-[#7b7b7b]">Estos son los valores que el sistema usará ahora mismo como base para los episodios nuevos y los correos de notificación.</p>
                <dl class="mt-4 grid gap-4 md:grid-cols-2">
                    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.7)] p-4">
                        <dt class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Correo principal activo</dt>
                        <dd class="mt-2 break-all text-sm text-[#e0e0e0]">{{ $activeNotificationState['primary'] ?? 'No definido' }}</dd>
                    </div>
                    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.7)] p-4">
                        <dt class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Correo copia activo</dt>
                        <dd class="mt-2 break-all text-sm text-[#e0e0e0]">{{ $activeNotificationState['copy'] ?? 'No definido' }}</dd>
                    </div>
                    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.7)] p-4">
                        <dt class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Remitente activo</dt>
                        <dd class="mt-2 break-all text-sm text-[#e0e0e0]">{{ $activeNotificationState['from'] ?? 'No definido' }}</dd>
                    </div>
                    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.7)] p-4">
                        <dt class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Reply-to activo</dt>
                        <dd class="mt-2 break-all text-sm text-[#e0e0e0]">{{ $activeNotificationState['reply_to'] ?? 'No definido' }}</dd>
                    </div>
                    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.7)] p-4">
                        <dt class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Mailer activo</dt>
                        <dd class="mt-2 break-all text-sm text-[#e0e0e0]">{{ $activeNotificationState['mailer'] ?? 'No definido' }}</dd>
                    </div>
                    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.7)] p-4">
                        <dt class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Archive.org</dt>
                        <dd class="mt-2 text-sm {{ $archiveOrgState['configured'] ? 'text-[#b8e6c3]' : 'text-[#ff9e9e]' }}">
                            {{ $archiveOrgState['configured'] ? 'Credenciales detectadas' : 'Sin credenciales activas' }}
                        </dd>
                        <p class="mt-2 text-xs leading-5 text-[#7b7b7b]">
                            Endpoint: <span class="text-[#e0e0e0]">{{ $archiveOrgState['endpoint'] ?: 'No definido' }}</span><br>
                            Colección: <span class="text-[#e0e0e0]">{{ $archiveOrgState['collection'] ?: 'No definida' }}</span><br>
                            Sincronización por defecto: <span class="text-[#e0e0e0]">{{ $archiveOrgState['default_sync'] ? 'Sí' : 'No' }}</span>
                        </p>
                    </div>
                </dl>
            </div>
            <div class="mt-6 grid gap-5">
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['contact_form_title_label'] }}</label>
                        <input name="contact_form_title" value="{{ old('contact_form_title', $settings->contact_form_title) }}" class="lucille-product-field w-full">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['contact_info_title_label'] }}</label>
                        <input name="contact_info_title" value="{{ old('contact_info_title', $settings->contact_info_title) }}" class="lucille-product-field w-full">
                    </div>
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['contact_description_label'] }}</label>
                    <textarea name="contact_description" rows="4" class="lucille-product-field w-full">{{ old('contact_description', $settings->contact_description) }}</textarea>
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['address_label'] }}</label>
                    <textarea name="contact_address" rows="3" class="lucille-product-field w-full">{{ old('contact_address', $settings->contact_address) }}</textarea>
                </div>
                <div class="grid gap-5 md:grid-cols-3">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['contact_email_label'] }}</label>
                        <input name="contact_email" value="{{ old('contact_email', $settings->contact_email) }}" class="lucille-product-field w-full">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['notification_email_label'] }}</label>
                        <input name="notification_email" value="{{ old('notification_email', $settings->notification_email) }}" class="lucille-product-field w-full">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['notification_copy_email_label'] }}</label>
                        <input name="notification_copy_email" value="{{ old('notification_copy_email', $settings->notification_copy_email) }}" class="lucille-product-field w-full">
                        <p class="mt-2 text-xs text-[#7b7b7b]">Copia global usada por defecto en los programas maestros.</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['notification_from_email_label'] }}</label>
                        <input name="notification_from_email" value="{{ old('notification_from_email', $settings->notification_from_email) }}" class="lucille-product-field w-full">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['notification_reply_to_email_label'] }}</label>
                        <input name="notification_reply_to_email" value="{{ old('notification_reply_to_email', $settings->notification_reply_to_email) }}" class="lucille-product-field w-full">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['notification_mailer_label'] }}</label>
                        <select name="notification_mailer" class="lucille-product-field lucille-select-field w-full">
                            <option value="">Use mail defaults</option>
                            @foreach (array_keys(config('mail.mailers', [])) as $mailerName)
                                <option value="{{ $mailerName }}" @selected(old('notification_mailer', $settings->notification_mailer) === $mailerName)>{{ $mailerName }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs text-[#7b7b7b]">Override the mailer used by upload notifications.</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['contact_phone_primary_label'] }}</label>
                        <input name="contact_phone_primary" value="{{ old('contact_phone_primary', $settings->contact_phone_primary) }}" class="lucille-product-field w-full">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['contact_phone_secondary_label'] }}</label>
                        <input name="contact_phone_secondary" value="{{ old('contact_phone_secondary', $settings->contact_phone_secondary) }}" class="lucille-product-field w-full">
                    </div>
                </div>
            </div>
        </section>

        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $admin['social_links_section'] }}</h2>
            <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(280px,.8fr)]">
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['facebook_label'] }}</label>
                        <input name="social_facebook" value="{{ old('social_facebook', $settings->social_facebook) }}" class="lucille-product-field w-full">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['instagram_label'] }}</label>
                        <input name="social_instagram" value="{{ old('social_instagram', $settings->social_instagram) }}" class="lucille-product-field w-full">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['youtube_label'] }}</label>
                        <input name="social_youtube" value="{{ old('social_youtube', $settings->social_youtube) }}" class="lucille-product-field w-full">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['tiktok_label'] }}</label>
                        <input name="social_tiktok" value="{{ old('social_tiktok', $settings->social_tiktok) }}" class="lucille-product-field w-full">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['x_label'] }}</label>
                        <input name="social_x" value="{{ old('social_x', $settings->social_x) }}" class="lucille-product-field w-full">
                    </div>
                </div>

                <aside class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-5">
                    <h3 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">{{ $admin['brand_preview'] }}</h3>
                    <div class="mt-4 space-y-3 text-sm text-[#7b7b7b]">
                        <div class="flex items-center justify-between border-b border-[#2b2b2b] pb-3">
                            <span>{{ $admin['header_mode'] }}</span>
                            <span class="text-[#dcdcdc]">{{ strtoupper($settings->brand_display_mode ?? 'mark') }}</span>
                        </div>
                        <div class="flex items-center justify-between border-b border-[#2b2b2b] pb-3">
                            <span>{{ $admin['brand_mark_label'] }}</span>
                            <span class="text-[#dcdcdc]">{{ $settings->brand_mark }}</span>
                        </div>
                        <div class="flex items-center justify-between border-b border-[#2b2b2b] pb-3">
                            <span>{{ $admin['wordmark_font'] }}</span>
                            <span class="text-[#dcdcdc]">{{ $settings->brand_mark_font }}</span>
                        </div>
                        <p class="pt-2 leading-6">{{ $admin['brand_display_mode_help'] }}</p>
                    </div>
                </aside>
            </div>
        </section>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="lucille-button-solid">{{ $admin['save_settings'] }}</button>
            <a href="{{ route('admin.dashboard') }}" class="lucille-button">{{ $admin['back_to_dashboard'] }}</a>
        </div>
    </form>
</x-layouts.admin>
