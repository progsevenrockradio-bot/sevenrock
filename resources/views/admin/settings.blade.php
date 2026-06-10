<x-layouts.admin :title="($themeAppearance['admin_texts']['theme_settings'] ?? 'Theme settings').' - '.$themeSettings->site_name">
    @php
        $bodyFonts = $fonts['body'];
        $headingFonts = $fonts['heading'];
        $brandMarkFonts = $fonts['brand_mark'];
        $admin = $themeAppearance['admin_texts'];

        $tab1Fields = [
            'site_name',
            'brand_mark',
            'brand_mark_font',
            'brand_display_mode',
            'logo',
            'background',
            'hero_video',
            'hero_video_url',
            'hero_video_disabled',
            'body_font',
            'heading_font',
            'accent_color',
            'nav_color',
            'surface_color',
            'body_color',
            'heading_color',
            'line_color',
            'hero_slide_primary',
            'hero_slide_secondary',
            'home_album_cover',
            'home_video_image',
        ];

        $tab2Fields = [
            'featured_stories_json',
            'latest_podcasts_json',
            'home_headings_json',
            'ui_texts_json',
            'admin_texts_json',
        ];

        $tab3Fields = [
            'contact_form_title',
            'contact_info_title',
            'contact_description',
            'contact_address',
            'contact_phone_primary',
            'contact_phone_secondary',
            'social_facebook',
            'social_instagram',
            'social_youtube',
            'social_tiktok',
            'social_x',
            'contact_email',
            'notification_email',
            'notification_copy_email',
            'notification_from_email',
            'notification_reply_to_email',
            'notification_mailer',
        ];

        $tab1HasErrors = $errors->hasAny($tab1Fields);
        $tab2HasErrors = $errors->hasAny($tab2Fields);
        $tab3HasErrors = $errors->hasAny($tab3Fields);

        $initialTab = $tab1HasErrors ? 'tab1' : ($tab2HasErrors ? 'tab2' : ($tab3HasErrors ? 'tab3' : 'tab1'));
    @endphp

    @if (session('status'))
        <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
            {{ session('status') }}
        </div>
    @endif

    <form
        action="{{ route('admin.settings.update') }}"
        method="POST"
        enctype="multipart/form-data"
        class="space-y-6"
        x-data="{
            activeTab: {{ Js::from($initialTab) }},
            brandMark: {{ Js::from(old('brand_mark', $settings->brand_mark)) }},
            brandMarkFont: {{ Js::from(old('brand_mark_font', $settings->brand_mark_font)) }},
            brandDisplayMode: {{ Js::from(old('brand_display_mode', $settings->brand_display_mode)) }},
            bodyFont: {{ Js::from(old('body_font', $settings->body_font)) }},
            headingFont: {{ Js::from(old('heading_font', $settings->heading_font)) }},
            accentColor: {{ Js::from(old('accent_color', $settings->accent_color)) }},
            navColor: {{ Js::from(old('nav_color', $settings->nav_color)) }},
            surfaceColor: {{ Js::from(old('surface_color', $settings->surface_color)) }},
            bodyColor: {{ Js::from(old('body_color', $settings->body_color)) }},
            headingColor: {{ Js::from(old('heading_color', $settings->heading_color)) }},
            lineColor: {{ Js::from(old('line_color', $settings->line_color)) }}
        }"
    >
        @csrf
        @method('PUT')

        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $admin['theme_settings'] }}</h1>
                    <p class="mt-3 text-sm leading-7 text-[#7b7b7b]">{{ $admin['theme_settings_copy'] }}</p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('admin.settings.manual') }}" class="lucille-button">Ver manual interno</a>
                    <a href="{{ route('admin.dashboard') }}" class="lucille-button">{{ $admin['back_to_dashboard'] }}</a>
                </div>
            </div>
        </section>

        <section class="sticky top-0 z-20 border border-[#2b2b2b] bg-[rgba(10,10,11,.96)] px-4 py-4 shadow-[0_18px_40px_rgba(0,0,0,.35)] backdrop-blur">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="lucille-button flex items-center gap-2" :class="activeTab === 'tab1' ? 'lucille-button-solid' : ''" @click="activeTab = 'tab1'">
                        <span>🎨</span> Apariencia y Multimedia
                        @if ($tab1HasErrors)
                            <span class="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full border border-[#7a2b2b] bg-[rgba(195,39,32,.15)] px-1 text-[10px] text-[#ff9e9e] font-bold">!</span>
                        @endif
                    </button>
                    <button type="button" class="lucille-button flex items-center gap-2" :class="activeTab === 'tab2' ? 'lucille-button-solid' : ''" @click="activeTab = 'tab2'">
                        <span>📝</span> Contenido y Textos
                        @if ($tab2HasErrors)
                            <span class="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full border border-[#7a2b2b] bg-[rgba(195,39,32,.15)] px-1 text-[10px] text-[#ff9e9e] font-bold">!</span>
                        @endif
                    </button>
                    <button type="button" class="lucille-button flex items-center gap-2" :class="activeTab === 'tab3' ? 'lucille-button-solid' : ''" @click="activeTab = 'tab3'">
                        <span>📡</span> Comunicaciones y Redes
                        @if ($tab3HasErrors)
                            <span class="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full border border-[#7a2b2b] bg-[rgba(195,39,32,.15)] px-1 text-[10px] text-[#ff9e9e] font-bold">!</span>
                        @endif
                    </button>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="lucille-button-solid bg-[#c32720] border-[#c32720] hover:bg-[#a61f19] hover:border-[#a61f19] transition-all">
                        💾 {{ $admin['save_settings'] }}
                    </button>
                </div>
            </div>
        </section>

        <section x-cloak x-show="activeTab === 'tab1'" x-transition>
            @include('admin.settings.partials.appearance')
        </section>

        <section x-cloak x-show="activeTab === 'tab2'" x-transition>
            @include('admin.settings.partials.content')
        </section>

        <section x-cloak x-show="activeTab === 'tab3'" x-transition>
            @include('admin.settings.partials.communications')
        </section>

        <section class="sticky bottom-0 z-10 border border-[#2b2b2b] bg-[rgba(10,10,11,.96)] px-4 py-4 shadow-[0_-18px_40px_rgba(0,0,0,.35)] backdrop-blur">
            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="lucille-button-solid">{{ $admin['save_settings'] }}</button>
                <a href="{{ route('admin.settings.manual') }}" class="lucille-button">Abrir manual</a>
                <a href="{{ route('admin.dashboard') }}" class="lucille-button">{{ $admin['back_to_dashboard'] }}</a>
            </div>
        </section>
    </form>
</x-layouts.admin>
