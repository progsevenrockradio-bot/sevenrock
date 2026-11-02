<div class="space-y-5 font-sans">
    {{-- Fila 1: Textos de contacto, Datos físicos y Redes sociales --}}
    <div class="grid gap-5 xl:grid-cols-3">
        {{-- 1.1 Textos de la página de contacto --}}
        <section class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-4">
            <div class="border-b border-white/10 pb-3">
                <h2 class="font-display text-base font-bold uppercase tracking-wide text-[#dcdcdc]">Textos de la página de contacto</h2>
                <p class="mt-0.5 text-xs text-gray-400">Encabezados y mensaje introductorio del formulario público.</p>
            </div>

            <div class="space-y-3.5">
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Título sobre el formulario</label>
                    <input name="contact_form_title" value="{{ old('contact_form_title', $settings->contact_form_title) }}"
                           placeholder="Título del formulario"
                           class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                    @error('contact_form_title')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Título de información de contacto</label>
                    <input name="contact_info_title" value="{{ old('contact_info_title', $settings->contact_info_title) }}"
                           placeholder="Título de información"
                           class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                    @error('contact_info_title')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Descripción introductoria</label>
                    <textarea name="contact_description" rows="3"
                              placeholder="Descripción breve en la página de contacto"
                              class="w-full rounded-lg border border-white/10 bg-[#151518] p-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">{{ old('contact_description', $settings->contact_description) }}</textarea>
                    @error('contact_description')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        {{-- 1.2 Dirección y teléfonos --}}
        <section class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-4">
            <div class="border-b border-white/10 pb-3">
                <h2 class="font-display text-base font-bold uppercase tracking-wide text-[#dcdcdc]">Dirección y teléfonos</h2>
                <p class="mt-0.5 text-xs text-gray-400">Datos visibles en el pie de página y sección de contacto.</p>
            </div>

            <div class="space-y-3.5">
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Dirección física o postal</label>
                    <textarea name="contact_address" rows="3"
                              placeholder="Dirección de la emisora"
                              class="w-full rounded-lg border border-white/10 bg-[#151518] p-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">{{ old('contact_address', $settings->contact_address) }}</textarea>
                    @error('contact_address')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Teléfono principal</label>
                    <input name="contact_phone_primary" value="{{ old('contact_phone_primary', $settings->contact_phone_primary) }}"
                           placeholder="+34 ..."
                           class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                    @error('contact_phone_primary')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Teléfono secundario</label>
                    <input name="contact_phone_secondary" value="{{ old('contact_phone_secondary', $settings->contact_phone_secondary) }}"
                           placeholder="+34 ..."
                           class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                    @error('contact_phone_secondary')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        {{-- 1.3 Enlaces de redes sociales --}}
        <section class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-4">
            <div class="border-b border-white/10 pb-3">
                <h2 class="font-display text-base font-bold uppercase tracking-wide text-[#dcdcdc]">Perfiles en redes sociales</h2>
                <p class="mt-0.5 text-xs text-gray-400">Enlaces oficiales en cabecera, pie de página y reproductor.</p>
            </div>

            <div class="space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-[#dcdcdc]">Facebook</label>
                    <input name="social_facebook" value="{{ old('social_facebook', $settings->social_facebook) }}"
                           placeholder="https://facebook.com/..."
                           class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                    @error('social_facebook')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-[#dcdcdc]">Instagram</label>
                    <input name="social_instagram" value="{{ old('social_instagram', $settings->social_instagram) }}"
                           placeholder="https://instagram.com/..."
                           class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                    @error('social_instagram')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-[#dcdcdc]">YouTube</label>
                    <input name="social_youtube" value="{{ old('social_youtube', $settings->social_youtube) }}"
                           placeholder="https://youtube.com/..."
                           class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                    @error('social_youtube')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[#dcdcdc]">TikTok</label>
                        <input name="social_tiktok" value="{{ old('social_tiktok', $settings->social_tiktok) }}"
                               placeholder="https://tiktok.com/@..."
                               class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                        @error('social_tiktok')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-[#dcdcdc]">X (Twitter)</label>
                        <input name="social_x" value="{{ old('social_x', $settings->social_x) }}"
                               placeholder="https://x.com/..."
                               class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                        @error('social_x')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- Fila 2: Configuración de correos y notificaciones del sistema --}}
    <section class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-5">
        <div class="border-b border-white/10 pb-3">
            <h2 class="font-display text-base font-bold uppercase tracking-wide text-[#dcdcdc]">Cuentas de correo y notificaciones del sistema</h2>
            <p class="mt-0.5 text-xs text-gray-400">Direcciones operativas de envío SMTP, copias de seguridad y buzón IMAP de entrada.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Correo público de contacto</label>
                <input name="contact_email" value="{{ old('contact_email', $settings->contact_email) }}"
                       placeholder="contacto@sevenrockradio.com"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                @error('contact_email')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Destinatario principal de alertas</label>
                <input name="notification_email" value="{{ old('notification_email', $settings->notification_email) }}"
                       placeholder="notificaciones@sevenrockradio.com"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                @error('notification_email')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Correo en copia global (CC)</label>
                <input name="notification_copy_email" value="{{ old('notification_copy_email', $settings->notification_copy_email) }}"
                       placeholder="copias@sevenrockradio.com"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                @error('notification_copy_email')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Remitente (From)</label>
                <input name="notification_from_email" value="{{ old('notification_from_email', $settings->notification_from_email) }}"
                       placeholder="remitente@sevenrockradio.com"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                @error('notification_from_email')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Responder a (Reply-To)</label>
                <input name="notification_reply_to_email" value="{{ old('notification_reply_to_email', $settings->notification_reply_to_email) }}"
                       placeholder="reply-to@sevenrockradio.com"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                @error('notification_reply_to_email')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Transporte de correo (Mailer)</label>
                <select name="notification_mailer"
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                    <option value="">Usar predeterminado del sistema</option>
                    @foreach (array_keys(config('mail.mailers', [])) as $mailerName)
                        <option value="{{ $mailerName }}" @selected(old('notification_mailer', $settings->notification_mailer) === $mailerName)>{{ $mailerName }}</option>
                    @endforeach
                </select>
                @error('notification_mailer')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Buzón de recepción de noticias (IMAP)</label>
                <input name="imap_username" value="{{ old('imap_username', $settings->imap_username) }}"
                       placeholder="prog.sevenrockradio@gmail.com"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                @error('imap_username')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Contraseña de aplicación IMAP</label>
                <input type="password" name="imap_password"
                       placeholder="{{ $settings->imap_password ? '******** (Guardada — vacía para mantener)' : 'Contraseña de aplicación' }}"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                @error('imap_password')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Resumen de estado efectivo de notificaciones --}}
        <div class="rounded-lg border border-white/5 bg-[#151518] p-3.5">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 mb-2">Valores efectivos activos en este entorno:</p>
            <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-5 text-xs">
                <div class="rounded border border-white/5 bg-black/30 px-2.5 py-2">
                    <span class="block text-[10px] text-gray-500">Destinatario principal</span>
                    <span class="font-mono text-gray-200 break-all">{{ $activeNotificationState['primary'] ?? 'No definido' }}</span>
                </div>
                <div class="rounded border border-white/5 bg-black/30 px-2.5 py-2">
                    <span class="block text-[10px] text-gray-500">Copia (CC)</span>
                    <span class="font-mono text-gray-200 break-all">{{ $activeNotificationState['copy'] ?? 'No definido' }}</span>
                </div>
                <div class="rounded border border-white/5 bg-black/30 px-2.5 py-2">
                    <span class="block text-[10px] text-gray-500">Remitente (From)</span>
                    <span class="font-mono text-gray-200 break-all">{{ $activeNotificationState['from'] ?? 'No definido' }}</span>
                </div>
                <div class="rounded border border-white/5 bg-black/30 px-2.5 py-2">
                    <span class="block text-[10px] text-gray-500">Responder a (Reply-To)</span>
                    <span class="font-mono text-gray-200 break-all">{{ $activeNotificationState['reply_to'] ?? 'No definido' }}</span>
                </div>
                <div class="rounded border border-white/5 bg-black/30 px-2.5 py-2">
                    <span class="block text-[10px] text-gray-500">Mailer en uso</span>
                    <span class="font-mono text-emerald-300 break-all">{{ $activeNotificationState['mailer'] ?? 'No definido' }}</span>
                </div>
            </div>
        </div>
    </section>

    {{-- Fila 3: Automatización editorial por correo, IA y Archive.org --}}
    <section class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-5">
        <div class="border-b border-white/10 pb-3">
            <h2 class="font-display text-base font-bold uppercase tracking-wide text-[#dcdcdc]">Automatización editorial vía correo, IA y Archive.org</h2>
            <p class="mt-0.5 text-xs text-gray-400">Control de lectura IMAP, filtros deterministas de relevancia, proveedores de IA y claves de almacenamiento.</p>
        </div>

        {{-- Interruptores principales --}}
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Procesamiento automático --}}
            <div class="rounded-lg border border-white/5 bg-[#151518] p-3.5 flex items-center justify-between gap-3">
                <div>
                    <span class="block text-xs font-semibold text-[#dcdcdc]">Lectura IMAP automática</span>
                    <span class="mt-0.5 block text-[11px] text-gray-400">Habilita o pausa la ingesta de correos entrantes.</span>
                </div>
                <div x-data="{ enabled: {{ old('email_processing_enabled', $settings->email_processing_enabled) ? 'true' : 'false' }} }" class="shrink-0">
                    <input type="hidden" name="email_processing_enabled" :value="enabled ? '1' : '0'">
                    <button type="button" @click="enabled = !enabled"
                            class="relative inline-flex h-6 w-11 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:outline-none"
                            :class="enabled ? 'bg-[#c32720]' : 'bg-white/15'">
                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition"
                              :class="enabled ? 'translate-x-5' : 'translate-x-0'"></span>
                    </button>
                </div>
            </div>

            {{-- Auto publicación --}}
            <div class="rounded-lg border border-white/5 bg-[#151518] p-3.5 flex items-center justify-between gap-3">
                <div>
                    <span class="block text-xs font-semibold text-[#dcdcdc]">Publicación directa</span>
                    <span class="mt-0.5 block text-[11px] text-gray-400">Publica al instante o conserva en borrador.</span>
                </div>
                <div x-data="{ enabled: {{ old('email_auto_publish', $settings->email_auto_publish) ? 'true' : 'false' }} }" class="shrink-0">
                    <input type="hidden" name="email_auto_publish" :value="enabled ? '1' : '0'">
                    <button type="button" @click="enabled = !enabled"
                            class="relative inline-flex h-6 w-11 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:outline-none"
                            :class="enabled ? 'bg-emerald-600' : 'bg-white/15'">
                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition"
                              :class="enabled ? 'translate-x-5' : 'translate-x-0'"></span>
                    </button>
                </div>
            </div>

            {{-- Rotación automática del vídeo destacado --}}
            <div class="rounded-lg border border-white/5 bg-[#151518] p-3.5 flex items-center justify-between gap-3">
                <div>
                    <span class="block text-xs font-semibold text-[#dcdcdc]">Rotación de vídeo semanal</span>
                    <span class="mt-0.5 block text-[11px] text-gray-400">Actualiza el vídeo en portada cada domingo.</span>
                </div>
                <div x-data="{ enabled: {{ old('featured_video_rotation_enabled', $settings->featured_video_rotation_enabled) ? 'true' : 'false' }} }" class="shrink-0">
                    <input type="hidden" name="featured_video_rotation_enabled" :value="enabled ? '1' : '0'">
                    <button type="button" @click="enabled = !enabled"
                            class="relative inline-flex h-6 w-11 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:outline-none"
                            :class="enabled ? 'bg-[#c32720]' : 'bg-white/15'">
                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition"
                              :class="enabled ? 'translate-x-5' : 'translate-x-0'"></span>
                    </button>
                </div>
            </div>

            {{-- Pre-filtro determinista --}}
            <div class="rounded-lg border border-white/5 bg-[#151518] p-3.5 flex items-center justify-between gap-3">
                <div>
                    <span class="block text-xs font-semibold text-[#dcdcdc]">Pre-filtro determinista</span>
                    <span class="mt-0.5 block text-[11px] text-gray-400">Descarta spam antes de consumir cuota de IA.</span>
                </div>
                <div x-data="{ enabled: {{ old('ai_prefilter_enabled', $settings->ai_prefilter_enabled ?? true) ? 'true' : 'false' }} }" class="shrink-0">
                    <input type="hidden" name="ai_prefilter_enabled" :value="enabled ? '1' : '0'">
                    <button type="button" @click="enabled = !enabled"
                            class="relative inline-flex h-6 w-11 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:outline-none"
                            :class="enabled ? 'bg-emerald-600' : 'bg-white/15'">
                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition"
                              :class="enabled ? 'translate-x-5' : 'translate-x-0'"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Parámetros numéricos y umbrales --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Relevancia mínima IA (1-5)</label>
                <select name="email_min_importance"
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-white focus:border-[#c32720] focus:outline-none">
                    <option value="1" @selected(old('email_min_importance', $settings->email_min_importance) == 1)>1 - Procesar todo</option>
                    <option value="2" @selected(old('email_min_importance', $settings->email_min_importance) == 2)>2 - Relevancia baja+</option>
                    <option value="3" @selected(old('email_min_importance', $settings->email_min_importance) == 3)>3 - Media+ (Recomendado)</option>
                    <option value="4" @selected(old('email_min_importance', $settings->email_min_importance) == 4)>4 - Relevancia alta+</option>
                    <option value="5" @selected(old('email_min_importance', $settings->email_min_importance) == 5)>5 - Solo muy importantes</option>
                </select>
                @error('email_min_importance')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Límite diario de noticias</label>
                <input type="number" name="email_daily_posts_limit" value="{{ old('email_daily_posts_limit', $settings->email_daily_posts_limit ?? 3) }}"
                       min="1" max="100"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white focus:border-[#c32720] focus:outline-none">
                @error('email_daily_posts_limit')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Límite diario lanzamientos</label>
                <input type="number" name="email_daily_releases_limit" value="{{ old('email_daily_releases_limit', $settings->email_daily_releases_limit ?? 3) }}"
                       min="1" max="100"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white focus:border-[#c32720] focus:outline-none">
                @error('email_daily_releases_limit')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Umbral deduplicado (0-1)</label>
                <input type="number" step="0.01" name="post_duplicate_similarity_threshold"
                       value="{{ old('post_duplicate_similarity_threshold', $settings->post_duplicate_similarity_threshold ?? 0.82) }}"
                       min="0" max="1"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white focus:border-[#c32720] focus:outline-none">
                @error('post_duplicate_similarity_threshold')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Tope diario llamadas IA</label>
                <input type="number" name="ai_daily_max_calls" value="{{ old('ai_daily_max_calls', $settings->ai_daily_max_calls ?? 30) }}"
                       min="1" max="500"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white focus:border-[#c32720] focus:outline-none">
                @error('ai_daily_max_calls')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Listas blancas, feeds y palabras clave --}}
        <div class="grid gap-4 lg:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Lista blanca de remitentes (Whitelist)</label>
                <textarea name="email_whitelist_senders" rows="2"
                          placeholder="ejemplo@correo.com, @dominio.com"
                          class="w-full rounded-lg border border-white/10 bg-[#151518] p-2.5 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none">{{ old('email_whitelist_senders', $settings->email_whitelist_senders) }}</textarea>
                @error('email_whitelist_senders')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Feeds RSS adicionales para imágenes (`dominio=url_feed`)</label>
                <textarea name="press_feeds_extra" rows="2"
                          placeholder="dominio.com=https://dominio.com/feed"
                          class="w-full rounded-lg border border-white/10 bg-[#151518] p-2.5 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none">{{ old('press_feeds_extra', $settings->press_feeds_extra) }}</textarea>
                @error('press_feeds_extra')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Palabras clave de señales musicales (Pre-filtro)</label>
                <textarea name="ai_prefilter_keywords" rows="2"
                          placeholder="single, álbum, nuevo disco, gira, concierto..."
                          class="w-full rounded-lg border border-white/10 bg-[#151518] p-2.5 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none">{{ old('ai_prefilter_keywords', $settings->ai_prefilter_keywords) }}</textarea>
                @error('ai_prefilter_keywords')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Dominios de sellos y agencias musicales (Pre-filtro)</label>
                <textarea name="ai_prefilter_promo_domains" rows="2"
                          placeholder="nuclearblast.de, napalmrecords.com, centurymedia.com..."
                          class="w-full rounded-lg border border-white/10 bg-[#151518] p-2.5 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none">{{ old('ai_prefilter_promo_domains', $settings->ai_prefilter_promo_domains) }}</textarea>
                @error('ai_prefilter_promo_domains')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Proveedores IA y Archive.org --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 border-t border-white/10 pt-4">
            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Google Gemini API Key</label>
                <input type="password" name="gemini_api_key"
                       placeholder="{{ $settings->gemini_api_key ? '********' . substr($settings->gemini_api_key, -4) . ' (Guardada)' : 'Clave API de Gemini' }}"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none">
                @error('gemini_api_key')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Modelo Gemini</label>
                <input type="text" name="ai_gemini_model" value="{{ old('ai_gemini_model', $settings->ai_gemini_model ?? '') }}"
                       placeholder="gemini-flash-latest"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none">
                @error('ai_gemini_model')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">OpenRouter API Key</label>
                <input type="password" name="openrouter_api_key"
                       placeholder="{{ $settings->openrouter_api_key ? '********' . substr($settings->openrouter_api_key, -4) . ' (Guardada)' : 'Clave API de OpenRouter' }}"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none">
                @error('openrouter_api_key')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Modelo OpenRouter</label>
                <input type="text" name="ai_openrouter_model" value="{{ old('ai_openrouter_model', $settings->ai_openrouter_model ?? '') }}"
                       placeholder="openrouter/free"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none">
                @error('ai_openrouter_model')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Cadena de proveedores IA</label>
                <select name="ai_provider_chain"
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-white focus:border-[#c32720] focus:outline-none">
                    <option value="gemini,openrouter" @selected(old('ai_provider_chain', $settings->ai_provider_chain ?? 'gemini,openrouter') == 'gemini,openrouter')>Gemini y respaldo en OpenRouter</option>
                    <option value="openrouter,gemini" @selected(old('ai_provider_chain', $settings->ai_provider_chain ?? '') == 'openrouter,gemini')>OpenRouter y respaldo en Gemini</option>
                    <option value="gemini" @selected(old('ai_provider_chain', $settings->ai_provider_chain ?? '') == 'gemini')>Solo Gemini</option>
                    <option value="openrouter" @selected(old('ai_provider_chain', $settings->ai_provider_chain ?? '') == 'openrouter')>Solo OpenRouter</option>
                </select>
                @error('ai_provider_chain')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Archive.org Access Key</label>
                <input name="archive_access_key" value="{{ old('archive_access_key', $settings->archive_access_key) }}"
                       placeholder="Access Key S3"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none">
                @error('archive_access_key')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Archive.org Secret Key</label>
                <input type="password" name="archive_secret_key"
                       placeholder="{{ $settings->archive_secret_key ? '******** (Guardada)' : 'Secret Key S3' }}"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none">
                @error('archive_secret_key')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-end pb-1">
                <label class="inline-flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" name="ai_fallback_enabled" value="1"
                           @checked(old('ai_fallback_enabled', $settings->ai_fallback_enabled ?? true))
                           class="h-4 w-4 rounded border-white/20 bg-black text-[#c32720] focus:ring-[#c32720]">
                    <span class="text-xs font-medium text-gray-200">Habilitar respaldo (Fallback IA)</span>
                </label>
            </div>
        </div>

        {{-- Estado efectivo IA y Portada de respaldo --}}
        <div class="grid gap-4 lg:grid-cols-2 border-t border-white/10 pt-4">
            <div class="rounded-lg border border-white/5 bg-[#151518] p-3.5 text-xs space-y-1">
                <span class="font-semibold text-[#dcdcdc] block">Estado efectivo de proveedores IA:</span>
                <p class="text-gray-400">Cadena activa: <code class="text-gray-200">{{ implode(' → ', app(\App\Services\AiParserManager::class)->resolveChain()) }}</code></p>
                <p class="text-gray-400">Modelo Gemini: <code class="text-gray-200">{{ app(\App\Services\GeminiContentParser::class)->resolveModel() }}</code> · OpenRouter: <code class="text-gray-200">{{ app(\App\Services\OpenRouterContentParser::class)->resolveModel() }}</code></p>
            </div>

            <div class="rounded-lg border border-white/5 bg-[#151518] p-3.5 flex flex-col sm:flex-row items-center gap-3">
                @if($settings->email_default_cover_path)
                    <img src="{{ \App\Support\PublicMediaUrl::normalizePublicUrl($settings->email_default_cover_path) }}" class="h-12 w-12 shrink-0 rounded border border-white/10 object-cover" alt="Portada respaldo">
                @else
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded border border-white/10 bg-black/30 text-[10px] text-gray-500">Sin img</div>
                @endif
                <div class="flex-1 w-full">
                    <label class="mb-1 block text-xs font-medium text-[#dcdcdc]">Portada predeterminada de respaldo para noticias por correo</label>
                    <input type="file" name="email_default_cover" class="block w-full text-xs text-gray-300 file:mr-2 file:rounded file:border file:border-white/10 file:bg-white/5 file:px-2.5 file:py-1 file:text-xs file:text-gray-200">
                </div>
                @error('email_default_cover')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    {{-- Fila 4: Personalización de plantillas de correo automáticas y envío de prueba --}}
    <section class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-5">
        <div class="border-b border-white/10 pb-3">
            <h2 class="font-display text-base font-bold uppercase tracking-wide text-[#dcdcdc]">Textos de correos automáticos (Podcasts y lanzamientos)</h2>
            <p class="mt-0.5 text-xs text-gray-400">Asuntos, etiquetas y cabeceras en los avisos automáticos enviados a conductores y bandas.</p>
        </div>

        @php
            $ui = $settings->uiTexts();
        @endphp

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Color de fondo del correo</label>
                <div class="flex items-center gap-2">
                    <input name="email_background_color" type="color"
                           value="{{ old('email_background_color', $ui['email_background_color'] ?? '#0c0c0e') }}"
                           class="h-9 w-12 cursor-pointer rounded border border-white/10 bg-transparent p-0.5">
                    <span class="font-mono text-xs text-gray-300">{{ old('email_background_color', $ui['email_background_color'] ?? '#0c0c0e') }}</span>
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Título cabecera (Servidor podcast)</label>
                <input name="email_title_verified_podcast"
                       value="{{ old('email_title_verified_podcast', $ui['email_title_verified_podcast'] ?? 'Servidor de podcast') }}"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none">
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Etiqueta destino: Streaming</label>
                <input name="email_label_streaming"
                       value="{{ old('email_label_streaming', $ui['email_label_streaming'] ?? 'Servidor streaming') }}"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none">
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Etiqueta destino: Podcast</label>
                <input name="email_label_podcast"
                       value="{{ old('email_label_podcast', $ui['email_label_podcast'] ?? 'Servidor de podcast') }}"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none">
            </div>

            <div class="sm:col-span-2 lg:col-span-4">
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Texto de cierre en notificación de programa</label>
                <textarea name="email_footer_notification" rows="2"
                          class="w-full rounded-lg border border-white/10 bg-[#151518] p-2.5 text-xs text-white focus:border-[#c32720] focus:outline-none">{{ old('email_footer_notification', $ui['email_footer_notification'] ?? 'Notificación de que su programa ha sido puesto en la parrilla de la radio.') }}</textarea>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Asunto (Nuevo lanzamiento)</label>
                <input name="email_title_new_release_published"
                       value="{{ old('email_title_new_release_published', $ui['email_title_new_release_published'] ?? '¡Nuevo lanzamiento publicado! - Seven Rock Radio') }}"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none">
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Cabecera (Nuevo lanzamiento)</label>
                <input name="email_heading_new_release_published"
                       value="{{ old('email_heading_new_release_published', $ui['email_heading_new_release_published'] ?? '¡Tu lanzamiento ha sido publicado!') }}"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none">
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Asunto (Artículo publicado)</label>
                <input name="email_title_post_published"
                       value="{{ old('email_title_post_published', $ui['email_title_post_published'] ?? '¡Tu contenido ya ha sido publicado! - Seven Rock Radio') }}"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none">
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">Cabecera (Artículo publicado)</label>
                <input name="email_heading_post_published"
                       value="{{ old('email_heading_post_published', $ui['email_heading_post_published'] ?? '¡Tu contenido ha sido publicado!') }}"
                       class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none">
            </div>
        </div>

        {{-- Enviar correo de prueba (acción separada) --}}
        <div class="rounded-lg border border-white/10 bg-[#151518] p-4">
            <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-3">
                <div>
                    <h3 class="text-xs font-semibold text-[#dcdcdc]">Enviar correo de prueba de notificación</h3>
                    <p class="text-[11px] text-gray-400">Envía una vista previa real a la dirección indicada para verificar el diseño y remitente configurado.</p>
                </div>
                <div class="grid gap-2.5 sm:grid-cols-3 w-full lg:max-w-xl">
                    <div>
                        <label for="test-template-type" class="mb-1 block text-[10px] font-medium text-gray-400">Plantilla</label>
                        <select id="test-template-type" name="template_type" form="test-email-form"
                                class="h-9 w-full rounded-lg border border-white/10 bg-[#101012] px-2.5 text-xs text-white focus:border-[#c32720] focus:outline-none">
                            <option value="podcast_uploaded">Distribución completa</option>
                            <option value="podcast_archive">Respaldo verificado</option>
                            <option value="podcast_radioboss">Streaming verificado</option>
                            <option value="new_release">Nuevo lanzamiento</option>
                            <option value="post_published">Artículo publicado</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="test-email-input" class="mb-1 block text-[10px] font-medium text-gray-400">Correo destinatario de prueba</label>
                        <div class="flex gap-2">
                            <input type="email" id="test-email-input" name="test_email" form="test-email-form" required
                                   placeholder="correo@ejemplo.com"
                                   class="h-9 flex-1 rounded-lg border border-white/10 bg-[#101012] px-3 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none">
                            <button type="submit" form="test-email-form"
                                    class="inline-flex h-9 items-center rounded-lg border border-white/15 bg-white/10 px-3.5 text-xs font-semibold text-white hover:bg-white/15 shrink-0">
                                Enviar prueba
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
