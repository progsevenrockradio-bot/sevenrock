<x-layouts.admin title="Configuración general - {{ $themeSettings->site_name ?? 'Seven Rock Radio' }}">
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
            'logo_height',
            'background',
            'hero_video',
            'hero_video_url',
            'hero_video_disabled',
            'hero_floating_text',
            'hero_floating_text_position',
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
            'hero_slides',
            'hero_slides_interval',
            'hero_slides_transition',
            'home_album_cover',
            'talents_banner',
            'featured_album_slug',
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
            'imap_username',
            'imap_password',
            'notification_mailer',
            'email_auto_publish',
            'email_processing_enabled',
            'email_min_importance',
            'email_whitelist_senders',
            'email_daily_posts_limit',
            'email_daily_releases_limit',
            'post_duplicate_similarity_threshold',
            'press_feeds_extra',
            'gemini_api_key',
            'openrouter_api_key',
            'ai_fallback_enabled',
            'ai_provider_chain',
            'ai_gemini_model',
            'ai_openrouter_model',
            'ai_daily_max_calls',
            'ai_prefilter_enabled',
            'ai_prefilter_keywords',
            'ai_prefilter_promo_domains',
            'archive_access_key',
            'archive_secret_key',
            'email_default_cover',
            'email_background_color',
            'email_title_verified_podcast',
            'email_label_streaming',
            'email_label_podcast',
            'email_footer_notification',
            'email_title_new_release_published',
            'email_heading_new_release_published',
            'email_title_post_published',
            'email_heading_post_published',
        ];

        $tab4Fields = [
            'moderation_extra_emails',
            'moderation_require_submission',
            'moderation_require_talent_registration',
            'moderation_require_media',
            'moderation_require_album',
            'moderation_require_product',
            'moderation_require_wall_post',
            'moderation_require_comment',
            'moderation_require_contact',
            'moderation_require_affiliate',
            'moderation_require_agency_band',
            'moderation_require_contract',
            'moderation_require_event',
        ];

        $tab1HasErrors = $errors->hasAny($tab1Fields);
        $tab2HasErrors = $errors->hasAny($tab2Fields);
        $tab3HasErrors = $errors->hasAny($tab3Fields);
        $tab4HasErrors = $errors->hasAny($tab4Fields);

        $initialTab = $tab1HasErrors ? 'tab1' : ($tab2HasErrors ? 'tab2' : ($tab3HasErrors ? 'tab3' : ($tab4HasErrors ? 'tab4' : 'tab1')));
    @endphp

    <div class="space-y-5 font-sans">
        {{-- Mensajes de estado y validación visibles sin superposición --}}
        @if (session('status'))
            <div class="flex items-center gap-2.5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-xs text-emerald-200" role="status">
                <svg class="h-4 w-4 shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span>{{ session('status') === 'Theme settings updated.' ? 'Configuración general actualizada correctamente.' : session('status') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="flex items-center gap-2.5 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-xs text-red-200" role="alert">
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-xs text-red-200" role="alert">
                <strong class="block font-semibold text-red-100 mb-1.5">Revisa los campos señalados antes de guardar:</strong>
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            action="{{ route('admin.settings.update') }}"
            method="POST"
            enctype="multipart/form-data"
            class="space-y-5"
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
                lineColor: {{ Js::from(old('line_color', $settings->line_color)) }},
                resetToDefaults() {
                    this.accentColor = '#c32720';
                    this.navColor = '#081a24';
                    this.surfaceColor = '#101012';
                    this.bodyColor = '#7b7b7b';
                    this.headingColor = '#dcdcdc';
                    this.lineColor = '#757575';
                }
            }"
        >
            @csrf
            @method('PUT')

            {{-- Cabecera compacta --}}
            <header class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between border-b border-white/10 pb-4">
                <div>
                    <div class="flex items-center gap-2 text-[11px] font-medium uppercase tracking-wider text-gray-400">
                        <span>Sistema</span>
                        <span class="text-white/20">/</span>
                        <span class="text-[#c32720]">Configuración general</span>
                    </div>
                    <h1 class="mt-1 font-display text-2xl font-bold uppercase tracking-wide text-[#dcdcdc]">Configuración general del sitio</h1>
                    <p class="mt-1 text-xs text-gray-400 max-w-3xl">
                        Gestiona la identidad visual, bloques editoriales JSON, buzón de automatización por IA, notificaciones de correo y reglas de moderación.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.settings.manual') }}"
                       class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3.5 text-xs font-medium text-gray-200 transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                        <span>Manual de configuración</span>
                    </a>
                    <button type="submit"
                            class="inline-flex h-9 items-center gap-2 rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-[#a61f19] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                        <span>Guardar configuración</span>
                    </button>
                </div>
            </header>

            {{-- Navegación ligera por pestañas (no flotante, no tapa campos ni alertas) --}}
            <nav aria-label="Secciones de configuración" class="flex flex-wrap items-center gap-1.5 rounded-xl border border-white/10 bg-[#101012] p-1.5">
                <button type="button"
                        @click="activeTab = 'tab1'"
                        :aria-selected="activeTab === 'tab1'"
                        class="inline-flex h-9 items-center gap-2 rounded-lg px-3.5 text-xs font-medium transition-colors focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                        :class="activeTab === 'tab1' ? 'bg-[#c32720] text-white font-semibold shadow-sm' : 'text-gray-300 hover:bg-white/5 hover:text-white'">
                    <span>1. Apariencia y multimedia</span>
                    @if ($tab1HasErrors)
                        <span class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500/20 border border-red-400 px-1 text-[10px] font-bold text-red-200">!</span>
                    @endif
                </button>

                <button type="button"
                        @click="activeTab = 'tab2'"
                        :aria-selected="activeTab === 'tab2'"
                        class="inline-flex h-9 items-center gap-2 rounded-lg px-3.5 text-xs font-medium transition-colors focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                        :class="activeTab === 'tab2' ? 'bg-[#c32720] text-white font-semibold shadow-sm' : 'text-gray-300 hover:bg-white/5 hover:text-white'">
                    <span>2. Contenido y textos (JSON)</span>
                    @if ($tab2HasErrors)
                        <span class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500/20 border border-red-400 px-1 text-[10px] font-bold text-red-200">!</span>
                    @endif
                </button>

                <button type="button"
                        @click="activeTab = 'tab3'"
                        :aria-selected="activeTab === 'tab3'"
                        class="inline-flex h-9 items-center gap-2 rounded-lg px-3.5 text-xs font-medium transition-colors focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                        :class="activeTab === 'tab3' ? 'bg-[#c32720] text-white font-semibold shadow-sm' : 'text-gray-300 hover:bg-white/5 hover:text-white'">
                    <span>3. Comunicaciones, redes e IA</span>
                    @if ($tab3HasErrors)
                        <span class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500/20 border border-red-400 px-1 text-[10px] font-bold text-red-200">!</span>
                    @endif
                </button>

                <button type="button"
                        @click="activeTab = 'tab4'"
                        :aria-selected="activeTab === 'tab4'"
                        class="inline-flex h-9 items-center gap-2 rounded-lg px-3.5 text-xs font-medium transition-colors focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                        :class="activeTab === 'tab4' ? 'bg-[#c32720] text-white font-semibold shadow-sm' : 'text-gray-300 hover:bg-white/5 hover:text-white'">
                    <span>4. Moderación global</span>
                    @if ($tab4HasErrors)
                        <span class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500/20 border border-red-400 px-1 text-[10px] font-bold text-red-200">!</span>
                    @endif
                </button>
            </nav>

            {{-- Paneles de configuración --}}
            <div x-cloak x-show="activeTab === 'tab1'">
                @include('admin.settings.partials.appearance')
            </div>

            <div x-cloak x-show="activeTab === 'tab2'">
                @include('admin.settings.partials.content')
            </div>

            <div x-cloak x-show="activeTab === 'tab3'">
                @include('admin.settings.partials.communications')
            </div>

            <div x-cloak x-show="activeTab === 'tab4'">
                @include('admin.settings.partials.moderation')
            </div>

            {{-- Pie de formulario estático (sin tapar campos ni mensajes al hacer scroll) --}}
            <footer class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-white/10 bg-[#101012] px-5 py-3.5">
                <p class="text-xs text-gray-400">
                    Los cambios de todas las pestañas se guardan conjuntamente al confirmar el formulario.
                </p>
                <div class="flex flex-wrap items-center gap-2.5">
                    <a href="{{ route('admin.settings.manual') }}"
                       class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/5 px-3.5 text-xs font-medium text-gray-300 transition-colors hover:bg-white/10 hover:text-white">
                        Abrir manual
                    </a>
                    <button type="submit"
                            class="inline-flex h-9 items-center rounded-lg bg-[#c32720] px-5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-[#a61f19] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                        Guardar configuración
                    </button>
                </div>
            </footer>
        </form>

        <form id="test-email-form" method="POST" action="{{ route('admin.settings.send-test-email') }}" class="hidden">
            @csrf
        </form>
    </div>
</x-layouts.admin>
