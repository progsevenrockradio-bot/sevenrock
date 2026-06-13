<section class="space-y-6">
    <!-- Encabezado de la pestaña -->
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <div class="flex items-center gap-4">
            <div class="text-3xl">📡</div>
            <div>
                <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Comunicaciones, Redes y Automatización</h2>
                <p class="mt-1 text-sm leading-7 text-[#7b7b7b]">Aquí se agrupan los textos de contacto, redes sociales, ajustes de notificaciones activas y automatización de correo por IA.</p>
            </div>
        </div>
    </div>

    <!-- Primera Fila: Textos e Información de Contacto -->
    <div class="grid gap-6 xl:grid-cols-2">
        <!-- SECCIÓN: TEXTOS DE CONTACTO -->
        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-3 mb-6">📝 Textos de la Sección de Contacto</h3>
            <div class="space-y-5">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['contact_form_title_label'] }}</label>
                    <input name="contact_form_title" value="{{ old('contact_form_title', $settings->contact_form_title) }}" class="lucille-product-field w-full" placeholder="Título sobre el formulario">
                    @error('contact_form_title')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['contact_info_title_label'] }}</label>
                    <input name="contact_info_title" value="{{ old('contact_info_title', $settings->contact_info_title) }}" class="lucille-product-field w-full" placeholder="Título sobre la información de contacto">
                    @error('contact_info_title')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['contact_description_label'] }}</label>
                    <textarea name="contact_description" rows="4" class="lucille-product-field w-full" placeholder="Descripción breve explicativa">{{ old('contact_description', $settings->contact_description) }}</textarea>
                    @error('contact_description')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <!-- SECCIÓN: DATOS DE CONTACTO -->
        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-3 mb-6">📞 Datos de Contacto Físicos</h3>
            <div class="space-y-5">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['address_label'] }}</label>
                    <textarea name="contact_address" rows="4" class="lucille-product-field w-full" placeholder="Dirección de la radio">{{ old('contact_address', $settings->contact_address) }}</textarea>
                    @error('contact_address')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['contact_phone_primary_label'] }}</label>
                        <input name="contact_phone_primary" value="{{ old('contact_phone_primary', $settings->contact_phone_primary) }}" class="lucille-product-field w-full" placeholder="+123456789">
                        @error('contact_phone_primary')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['contact_phone_secondary_label'] }}</label>
                        <input name="contact_phone_secondary" value="{{ old('contact_phone_secondary', $settings->contact_phone_secondary) }}" class="lucille-product-field w-full" placeholder="+987654321">
                        @error('contact_phone_secondary')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Segunda Fila: Redes Sociales y Notificaciones -->
    <div class="grid gap-6 xl:grid-cols-2">
        <!-- SECCIÓN: REDES SOCIALES -->
        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-3 mb-6">📱 Enlaces de Redes Sociales</h3>
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['facebook_label'] }}</label>
                    <input name="social_facebook" value="{{ old('social_facebook', $settings->social_facebook) }}" class="lucille-product-field w-full" placeholder="https://facebook.com/...">
                    @error('social_facebook')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['instagram_label'] }}</label>
                    <input name="social_instagram" value="{{ old('social_instagram', $settings->social_instagram) }}" class="lucille-product-field w-full" placeholder="https://instagram.com/...">
                    @error('social_instagram')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['youtube_label'] }}</label>
                    <input name="social_youtube" value="{{ old('social_youtube', $settings->social_youtube) }}" class="lucille-product-field w-full" placeholder="https://youtube.com/...">
                    @error('social_youtube')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['tiktok_label'] }}</label>
                    <input name="social_tiktok" value="{{ old('social_tiktok', $settings->social_tiktok) }}" class="lucille-product-field w-full" placeholder="https://tiktok.com/@...">
                    @error('social_tiktok')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2 border-t border-[#2b2b2b] pt-4 mt-2">
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['x_label'] }}</label>
                    <input name="social_x" value="{{ old('social_x', $settings->social_x) }}" class="lucille-product-field w-full" placeholder="https://x.com/...">
                    @error('social_x')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <!-- SECCIÓN: NOTIFICACIONES -->
        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8 flex flex-col justify-between">
            <div>
                <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-3 mb-6">📬 Configuración de Notificaciones (Email)</h3>
                
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['contact_email_label'] }}</label>
                        <input name="contact_email" value="{{ old('contact_email', $settings->contact_email) }}" class="lucille-product-field w-full" placeholder="contacto@sevenrockradio.com">
                        @error('contact_email')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['notification_email_label'] }}</label>
                        <input name="notification_email" value="{{ old('notification_email', $settings->notification_email) }}" class="lucille-product-field w-full" placeholder="notificaciones@sevenrockradio.com">
                        @error('notification_email')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['notification_copy_email_label'] }}</label>
                        <input name="notification_copy_email" value="{{ old('notification_copy_email', $settings->notification_copy_email) }}" class="lucille-product-field w-full" placeholder="copias@sevenrockradio.com">
                        <p class="mt-1 text-[10px] text-[#7b7b7b]">Copia global usada por defecto en los programas.</p>
                        @error('notification_copy_email')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['notification_from_email_label'] }}</label>
                        <input name="notification_from_email" value="{{ old('notification_from_email', $settings->notification_from_email) }}" class="lucille-product-field w-full" placeholder="remitente@sevenrockradio.com">
                        @error('notification_from_email')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['notification_reply_to_email_label'] }}</label>
                        <input name="notification_reply_to_email" value="{{ old('notification_reply_to_email', $settings->notification_reply_to_email) }}" class="lucille-product-field w-full" placeholder="reply-to@sevenrockradio.com">
                        @error('notification_reply_to_email')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['notification_mailer_label'] }}</label>
                        <select name="notification_mailer" class="lucille-product-field lucille-select-field w-full">
                            <option value="">Use mail defaults</option>
                            @foreach (array_keys(config('mail.mailers', [])) as $mailerName)
                                <option value="{{ $mailerName }}" @selected(old('notification_mailer', $settings->notification_mailer) === $mailerName)>{{ $mailerName }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[10px] text-[#7b7b7b]">Sobrescribe el mailer predeterminado.</p>
                        @error('notification_mailer')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <!-- Estado de notificaciones -->
            <div class="mt-6 border border-[#2b2b2b] bg-[rgba(0,0,0,.2)] p-4 rounded-lg">
                <h4 class="font-display text-xs uppercase tracking-[.12em] text-[#c32720] border-b border-[#2b2b2b] pb-2 mb-3">🛠️ Estado activo de notificaciones</h4>
                <div class="grid gap-3 grid-cols-2 md:grid-cols-3 text-xs">
                    <div class="p-2 bg-[rgba(16,16,18,.5)] border border-[#2b2b2b] rounded">
                        <span class="block text-[9px] uppercase tracking-wider text-[#7b7b7b]">Destinatario Activo</span>
                        <span class="font-mono text-[#e0e0e0] break-all block mt-1">{{ $activeNotificationState['primary'] ?? 'No definido' }}</span>
                    </div>
                    <div class="p-2 bg-[rgba(16,16,18,.5)] border border-[#2b2b2b] rounded">
                        <span class="block text-[9px] uppercase tracking-wider text-[#7b7b7b]">Copia Activa</span>
                        <span class="font-mono text-[#e0e0e0] break-all block mt-1">{{ $activeNotificationState['copy'] ?? 'No definido' }}</span>
                    </div>
                    <div class="p-2 bg-[rgba(16,16,18,.5)] border border-[#2b2b2b] rounded">
                        <span class="block text-[9px] uppercase tracking-wider text-[#7b7b7b]">Remitente Activo</span>
                        <span class="font-mono text-[#e0e0e0] break-all block mt-1">{{ $activeNotificationState['from'] ?? 'No definido' }}</span>
                    </div>
                    <div class="p-2 bg-[rgba(16,16,18,.5)] border border-[#2b2b2b] rounded">
                        <span class="block text-[9px] uppercase tracking-wider text-[#7b7b7b]">Reply-To Activo</span>
                        <span class="font-mono text-[#e0e0e0] break-all block mt-1">{{ $activeNotificationState['reply_to'] ?? 'No definido' }}</span>
                    </div>
                    <div class="p-2 bg-[rgba(16,16,18,.5)] border border-[#2b2b2b] rounded md:col-span-2">
                        <span class="block text-[9px] uppercase tracking-wider text-[#7b7b7b]">Mailer Configurado</span>
                        <span class="font-mono text-[#e0e0e0] break-all block mt-1">{{ $activeNotificationState['mailer'] ?? 'No definido' }}</span>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Tercera Fila: Automatización de Publicaciones (Gmail + Gemini) -->
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-3 mb-6">🤖 Automatización de Publicaciones vía Correo</h3>
        <p class="text-sm text-[#7b7b7b] mb-6">Configura las opciones inteligentes y credenciales de IA para procesar los lanzamientos recibidos por email.</p>
        
        <!-- Toggle Switches premium -->
        <div class="grid gap-6 md:grid-cols-2 mb-6">
            <!-- Toggle 1: Procesamiento habilitado -->
            <div class="flex items-center justify-between p-4 border border-[#2b2b2b] bg-[rgba(0,0,0,.15)] rounded-lg">
                <div class="pr-4">
                    <label class="block text-xs uppercase tracking-[.18em] text-[#dcdcdc] font-bold">Procesamiento Automático</label>
                    <span class="text-[10px] text-[#7b7b7b] block mt-1">Habilita o pausa la lectura automática de la bandeja de entrada IMAP.</span>
                </div>
                <div x-data="{ enabled: {{ old('email_processing_enabled', $settings->email_processing_enabled) ? 'true' : 'false' }} }" class="flex items-center shrink-0">
                    <input type="hidden" name="email_processing_enabled" :value="enabled ? '1' : '0'">
                    <button type="button" @click="enabled = !enabled" 
                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                        :class="enabled ? 'bg-[#c32720]' : 'bg-[#2b2b2b]'">
                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                            :class="enabled ? 'translate-x-5' : 'translate-x-0'"></span>
                    </button>
                </div>
            </div>

            <!-- Toggle 2: Auto publicación -->
            <div class="flex items-center justify-between p-4 border border-[#2b2b2b] bg-[rgba(0,0,0,.15)] rounded-lg">
                <div class="pr-4">
                    <label class="block text-xs uppercase tracking-[.18em] text-[#dcdcdc] font-bold">Estado de Publicación</label>
                    <span class="text-[10px] text-[#7b7b7b] block mt-1">Publica directamente en "Activo" o guarda en "Borrador" para revisión manual.</span>
                </div>
                <div x-data="{ enabled: {{ old('email_auto_publish', $settings->email_auto_publish) ? 'true' : 'false' }} }" class="flex items-center shrink-0">
                    <input type="hidden" name="email_auto_publish" :value="enabled ? '1' : '0'">
                    <button type="button" @click="enabled = !enabled" 
                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                        :class="enabled ? 'bg-[#1e4d2b]' : 'bg-[#2b2b2b]'">
                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                            :class="enabled ? 'translate-x-5' : 'translate-x-0'"></span>
                    </button>
                </div>
            </div>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <!-- Filtro de importancia -->
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Relevancia Mínima de Correo (Filtro Inteligente Gemini)</label>
                <select name="email_min_importance" class="lucille-product-field lucille-select-field w-full text-xs">
                    <option value="1" @selected(old('email_min_importance', $settings->email_min_importance) == 1)>1 - Procesar todo (sin filtros de relevancia)</option>
                    <option value="2" @selected(old('email_min_importance', $settings->email_min_importance) == 2)>2 - Relevancia baja o superior</option>
                    <option value="3" @selected(old('email_min_importance', $settings->email_min_importance) == 3)>3 - Relevancia media o superior (Recomendado)</option>
                    <option value="4" @selected(old('email_min_importance', $settings->email_min_importance) == 4)>4 - Relevancia alta o superior</option>
                    <option value="5" @selected(old('email_min_importance', $settings->email_min_importance) == 5)>5 - Solo noticias o lanzamientos muy importantes</option>
                </select>
                <p class="mt-2 text-[10px] text-[#7b7b7b]">Elige el umbral de descarte automático que realiza Gemini IA (1 a 5).</p>
                @error('email_min_importance')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
            </div>

            <!-- Whitelist de remitentes -->
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Lista Blanca de Remitentes (Whitelist)</label>
                <textarea name="email_whitelist_senders" rows="2" class="lucille-product-field w-full text-xs" placeholder="ejemplo@correo.com, @dominio.com, metaldevastationpr.com">{{ old('email_whitelist_senders', $settings->email_whitelist_senders) }}</textarea>
                <p class="mt-2 text-[10px] text-[#7b7b7b]">Ingresa correos o dominios separados por comas. Evadirán el filtro de relevancia mínima.</p>
                @error('email_whitelist_senders')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
            </div>

            <!-- API Keys -->
            <div class="border-t border-[#2b2b2b] pt-5 mt-3 md:col-span-2 grid gap-5 md:grid-cols-3">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Google Gemini API Key</label>
                    <input type="password" name="gemini_api_key" value="{{ old('gemini_api_key', $settings->gemini_api_key) }}" class="lucille-product-field w-full text-xs font-mono" placeholder="AI API Key de Google">
                    @error('gemini_api_key')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Archive.org Access Key</label>
                    <input name="archive_access_key" value="{{ old('archive_access_key', $settings->archive_access_key) }}" class="lucille-product-field w-full text-xs font-mono" placeholder="Access Key">
                    @error('archive_access_key')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Archive.org Secret Key</label>
                    <input type="password" name="archive_secret_key" value="{{ old('archive_secret_key', $settings->archive_secret_key) }}" class="lucille-product-field w-full text-xs font-mono" placeholder="Secret Key">
                    @error('archive_secret_key')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
            </div>

            <!-- Portada predeterminada de respaldo -->
            <div class="md:col-span-2 border-t border-[#2b2b2b] pt-5 mt-3">
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Portada Predeterminada de Respaldo (Backup)</label>
                <div class="flex flex-col sm:flex-row gap-4 items-center p-4 border border-[#2b2b2b] bg-[rgba(0,0,0,.15)] rounded">
                    @if($settings->email_default_cover_path)
                        <div class="relative group shrink-0 border border-[#3b3b3b] p-2 bg-[rgba(0,0,0,.4)] rounded">
                            <img src="{{ \App\Support\PublicMediaUrl::normalizePublicUrl($settings->email_default_cover_path) }}" class="h-16 w-16 object-cover">
                            <div class="text-[9px] text-center mt-1 text-[#7b7b7b] font-mono truncate max-w-[64px]">{{ basename($settings->email_default_cover_path) }}</div>
                        </div>
                    @else
                        <div class="h-16 w-16 bg-[#101012] border border-[#2b2b2b] flex items-center justify-center text-[10px] text-[#7b7b7b] uppercase text-center p-1 leading-tight rounded shrink-0">Sin imagen</div>
                    @endif
                    <div class="flex-1 w-full">
                        <input type="file" name="email_default_cover" class="lucille-product-field w-full text-xs file:bg-[#16161a] file:border-[#2b2b2b] file:text-[#dcdcdc] file:text-xs">
                        <p class="mt-2 text-[10px] text-[#7b7b7b]">Se usa de respaldo si el email del artista no tiene adjuntos grandes de portada. Máx 4MB.</p>
                    </div>
                </div>
                @error('email_default_cover')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    <!-- SECCIÓN: PERSONALIZACIÓN DE NOTIFICACIONES DE PODCASTS / EMAIL -->
    <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-3 mb-6">📧 Personalización de Plantillas de Email (Podcasts)</h3>
        
        @php
            $ui = $settings->uiTexts();
        @endphp

        <div class="space-y-5">
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Color de Fondo del Correo</label>
                    <div class="flex gap-3 items-center">
                        <input name="email_background_color" type="color" value="{{ old('email_background_color', $ui['email_background_color'] ?? '#0c0c0e') }}" class="lucille-product-field h-10 w-20 p-1 border-0 rounded cursor-pointer bg-transparent">
                        <span class="text-xs font-mono text-[#dcdcdc]">{{ old('email_background_color', $ui['email_background_color'] ?? '#0c0c0e') }}</span>
                    </div>
                    <p class="mt-2 text-[10px] text-[#7b7b7b]">Fondo del email enviado. Por defecto oscuro: #0c0c0e.</p>
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Título de Cabecera (Servidor de Podcast)</label>
                    <input name="email_title_verified_podcast" value="{{ old('email_title_verified_podcast', $ui['email_title_verified_podcast'] ?? 'Servidor de podcast') }}" class="lucille-product-field w-full" placeholder="Servidor de podcast">
                    <p class="mt-2 text-[10px] text-[#7b7b7b]">Sustituye el texto "Archive.org verificado" en los asuntos/cabeceras.</p>
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Etiqueta de Destino: Streaming (RB)</label>
                    <input name="email_label_streaming" value="{{ old('email_label_streaming', $ui['email_label_streaming'] ?? 'Servidor streaming') }}" class="lucille-product-field w-full" placeholder="Servidor streaming">
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Etiqueta de Destino: Podcast (Archive)</label>
                    <input name="email_label_podcast" value="{{ old('email_label_podcast', $ui['email_label_podcast'] ?? 'Servidor de podcast') }}" class="lucille-product-field w-full" placeholder="Servidor de podcast">
                </div>
            </div>

            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Texto de Cierre (Footer)</label>
                <textarea name="email_footer_notification" rows="3" class="lucille-product-field w-full text-xs" placeholder="Notificación de que su programa ha sido puesto en la parrilla de la radio.">{{ old('email_footer_notification', $ui['email_footer_notification'] ?? 'Notificación de que su programa ha sido puesto en la parrilla de la radio.') }}</textarea>
                <p class="mt-2 text-[10px] text-[#7b7b7b]">Reemplaza el texto final que invitaba a revisar el episodio en archive.org.</p>
            </div>

            <!-- LANZAMIENTOS MUSICALES Y POSTS -->
            <div class="border-t border-[#2b2b2b] pt-5 mt-5">
                <h4 class="font-display text-md uppercase tracking-[.12em] text-[#dcdcdc] mb-4">🎵 Lanzamientos Musicales y Entradas de Blog</h4>
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Asunto (Nuevo Lanzamiento)</label>
                        <input name="email_title_new_release_published" value="{{ old('email_title_new_release_published', $ui['email_title_new_release_published'] ?? '¡Nuevo lanzamiento publicado! - Seven Rock Radio') }}" class="lucille-product-field w-full" placeholder="¡Nuevo lanzamiento publicado! - Seven Rock Radio">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Cabecera (Nuevo Lanzamiento)</label>
                        <input name="email_heading_new_release_published" value="{{ old('email_heading_new_release_published', $ui['email_heading_new_release_published'] ?? '¡Tu lanzamiento ha sido publicado!') }}" class="lucille-product-field w-full" placeholder="¡Tu lanzamiento ha sido publicado!">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Asunto (Entrada de Blog)</label>
                        <input name="email_title_post_published" value="{{ old('email_title_post_published', $ui['email_title_post_published'] ?? '¡Tu contenido ya ha sido publicado! - Seven Rock Radio') }}" class="lucille-product-field w-full" placeholder="¡Tu contenido ya ha sido publicado! - Seven Rock Radio">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Cabecera (Entrada de Blog)</label>
                        <input name="email_heading_post_published" value="{{ old('email_heading_post_published', $ui['email_heading_post_published'] ?? '¡Tu contenido ha sido publicado!') }}" class="lucille-product-field w-full" placeholder="¡Tu contenido ha sido publicado!">
                    </div>
                </div>
            </div>

            <!-- ENVIAR CORREO DE PRUEBA -->
            <div class="border-t border-[#2b2b2b] pt-5 mt-5 bg-[rgba(0,0,0,0.15)] p-4 rounded border border-[#222]">
                <label class="mb-3 block text-xs uppercase tracking-[.18em] text-[#7b7b7b] font-bold text-lucille-accent">✉️ Probar Plantilla de Email</label>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-[9px] uppercase tracking-[.14em] text-[#7b7b7b]">Seleccionar Plantilla</label>
                        <select name="template_type" form="test-email-form" class="lucille-product-field lucille-select-field w-full text-xs" style="height: 38px;">
                            <option value="podcast_uploaded">Distribución completa (Verificado)</option>
                            <option value="podcast_archive">Servidor respaldo verificado</option>
                            <option value="podcast_radioboss">Servidor streaming verificado</option>
                            <option value="new_release">Nuevo lanzamiento publicado</option>
                            <option value="post_published">Entrada de blog publicada</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-[9px] uppercase tracking-[.14em] text-[#7b7b7b]">Destinatario de Prueba</label>
                        <div class="flex gap-2">
                            <input type="email" name="test_email" form="test-email-form" class="lucille-product-field flex-1 text-xs" style="height: 38px;" placeholder="ingresa-un-correo@ejemplo.com" required>
                            <button type="submit" form="test-email-form" class="lucille-button-solid text-xs py-2 px-4 whitespace-nowrap bg-lucille-accent/20 hover:bg-lucille-accent border-lucille-accent/40 text-white font-bold transition-all" style="height: 38px;">
                                Enviar Prueba
                            </button>
                        </div>
                    </div>
                </div>
                <p class="mt-2.5 text-[10px] text-[#7b7b7b]">Elige la plantilla de notificación y el correo a donde deseas que llegue para comprobar los asuntos, colores y cabeceras dinámicas.</p>
            </div>
        </div>
    </section>
</section>
