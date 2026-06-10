<section class="space-y-6">
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Comunicaciones y Redes</h2>
        <p class="mt-3 text-sm leading-7 text-[#7b7b7b]">Aquí se agrupan los textos de contacto, teléfonos, correos, redes sociales y el estado de notificaciones activas.</p>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Textos de contacto</h3>
            <div class="mt-6 space-y-5">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['contact_form_title_label'] }}</label>
                    <input name="contact_form_title" value="{{ old('contact_form_title', $settings->contact_form_title) }}" class="lucille-product-field w-full">
                    @error('contact_form_title')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['contact_info_title_label'] }}</label>
                    <input name="contact_info_title" value="{{ old('contact_info_title', $settings->contact_info_title) }}" class="lucille-product-field w-full">
                    @error('contact_info_title')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['contact_description_label'] }}</label>
                    <textarea name="contact_description" rows="4" class="lucille-product-field w-full">{{ old('contact_description', $settings->contact_description) }}</textarea>
                    @error('contact_description')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Datos de contacto</h3>
            <div class="mt-6 space-y-5">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['address_label'] }}</label>
                    <textarea name="contact_address" rows="4" class="lucille-product-field w-full">{{ old('contact_address', $settings->contact_address) }}</textarea>
                    @error('contact_address')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['contact_phone_primary_label'] }}</label>
                        <input name="contact_phone_primary" value="{{ old('contact_phone_primary', $settings->contact_phone_primary) }}" class="lucille-product-field w-full">
                        @error('contact_phone_primary')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['contact_phone_secondary_label'] }}</label>
                        <input name="contact_phone_secondary" value="{{ old('contact_phone_secondary', $settings->contact_phone_secondary) }}" class="lucille-product-field w-full">
                        @error('contact_phone_secondary')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Redes</h3>
            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['facebook_label'] }}</label>
                    <input name="social_facebook" value="{{ old('social_facebook', $settings->social_facebook) }}" class="lucille-product-field w-full">
                    @error('social_facebook')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['instagram_label'] }}</label>
                    <input name="social_instagram" value="{{ old('social_instagram', $settings->social_instagram) }}" class="lucille-product-field w-full">
                    @error('social_instagram')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['youtube_label'] }}</label>
                    <input name="social_youtube" value="{{ old('social_youtube', $settings->social_youtube) }}" class="lucille-product-field w-full">
                    @error('social_youtube')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['tiktok_label'] }}</label>
                    <input name="social_tiktok" value="{{ old('social_tiktok', $settings->social_tiktok) }}" class="lucille-product-field w-full">
                    @error('social_tiktok')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['x_label'] }}</label>
                    <input name="social_x" value="{{ old('social_x', $settings->social_x) }}" class="lucille-product-field w-full">
                    @error('social_x')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Notificaciones</h3>
            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['contact_email_label'] }}</label>
                    <input name="contact_email" value="{{ old('contact_email', $settings->contact_email) }}" class="lucille-product-field w-full">
                    @error('contact_email')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['notification_email_label'] }}</label>
                    <input name="notification_email" value="{{ old('notification_email', $settings->notification_email) }}" class="lucille-product-field w-full">
                    @error('notification_email')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['notification_copy_email_label'] }}</label>
                    <input name="notification_copy_email" value="{{ old('notification_copy_email', $settings->notification_copy_email) }}" class="lucille-product-field w-full">
                    <p class="mt-2 text-xs text-[#7b7b7b]">Copia global usada por defecto en los programas maestros.</p>
                    @error('notification_copy_email')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['notification_from_email_label'] }}</label>
                    <input name="notification_from_email" value="{{ old('notification_from_email', $settings->notification_from_email) }}" class="lucille-product-field w-full">
                    @error('notification_from_email')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $admin['notification_reply_to_email_label'] }}</label>
                    <input name="notification_reply_to_email" value="{{ old('notification_reply_to_email', $settings->notification_reply_to_email) }}" class="lucille-product-field w-full">
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
                    <p class="mt-2 text-xs text-[#7b7b7b]">Override the mailer used by upload notifications.</p>
                    @error('notification_mailer')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-6 border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-5">
                <h4 class="font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">Estado activo de notificaciones</h4>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
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
                    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.7)] p-4 md:col-span-2">
                        <dt class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">Mailer activo</dt>
                        <dd class="mt-2 break-all text-sm text-[#e0e0e0]">{{ $activeNotificationState['mailer'] ?? 'No definido' }}</dd>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Automatización de Publicaciones vía Correo</h3>
        <p class="mt-2 text-sm text-[#7b7b7b]">Configura las credenciales necesarias para procesar correos recibidos y publicarlos automáticamente.</p>
        
        <div class="mt-6 grid gap-5 md:grid-cols-2">
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Procesamiento Automático de Correos</label>
                <select name="email_processing_enabled" class="lucille-product-field lucille-select-field w-full">
                    <option value="1" @selected(old('email_processing_enabled', $settings->email_processing_enabled) == true)>Habilitado (Procesar automáticamente)</option>
                    <option value="0" @selected(old('email_processing_enabled', $settings->email_processing_enabled) == false)>Deshabilitado (Pausar todo el procesamiento)</option>
                </select>
                <p class="mt-2 text-xs text-[#7b7b7b]">Si se deshabilita, la tarea en segundo plano no se conectará a Gmail ni procesará nuevos correos.</p>
            </div>

            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Estado de Publicación de Correos</label>
                <select name="email_auto_publish" class="lucille-product-field lucille-select-field w-full">
                    <option value="1" @selected(old('email_auto_publish', $settings->email_auto_publish) == true)>Auto-publicar en activo</option>
                    <option value="0" @selected(old('email_auto_publish', $settings->email_auto_publish) == false)>Guardar como borrador (Revisión manual)</option>
                </select>
                <p class="mt-2 text-xs text-[#7b7b7b]">Si está en borrador, podrás revisar el contenido procesado por Gemini antes de hacerlo visible en la web.</p>
            </div>

            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Gemini API Key</label>
                <input type="password" name="gemini_api_key" value="{{ old('gemini_api_key', $settings->gemini_api_key) }}" class="lucille-product-field w-full" placeholder="API Key de Google Gemini">
                <p class="mt-2 text-xs text-[#7b7b7b]">Clave de acceso de Google AI para procesar, limpiar y redactar correos.</p>
                @error('gemini_api_key')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Archive.org Access Key</label>
                <input name="archive_access_key" value="{{ old('archive_access_key', $settings->archive_access_key) }}" class="lucille-product-field w-full" placeholder="Clave de Acceso (Access Key)">
                @error('archive_access_key')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Archive.org Secret Key</label>
                <input type="password" name="archive_secret_key" value="{{ old('archive_secret_key', $settings->archive_secret_key) }}" class="lucille-product-field w-full" placeholder="Clave Secreta (Secret Key)">
                @error('archive_secret_key')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
            </div>

            <div class="md:col-span-2 border-t border-[#2b2b2b] pt-5 mt-3">
                <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Portada Predeterminada de Respaldo (Backup)</label>
                @if($settings->email_default_cover_path)
                    <div class="mb-3 flex items-center gap-4">
                        <img src="{{ \App\Support\PublicMediaUrl::normalizePublicUrl($settings->email_default_cover_path) }}" class="h-20 w-20 object-cover border border-[#2b2b2b]">
                        <span class="text-xs text-[#7b7b7b]">Esta imagen se usará automáticamente cuando los correos no contengan portadas o fotos grandes.</span>
                    </div>
                @else
                    <div class="mb-3 flex items-center gap-4">
                        <div class="h-20 w-20 bg-[#16161a] border border-[#2b2b2b] flex items-center justify-center text-[10px] text-[#7b7b7b] uppercase text-center p-1 font-display leading-tight">Backup General</div>
                        <span class="text-xs text-[#7b7b7b]">Actualmente usando la portada genérica de la web (assets/lucille/album3.jpg). Sube una imagen personalizada para cambiarla.</span>
                    </div>
                @endif
                <input type="file" name="email_default_cover" class="lucille-product-field w-full file:bg-[#16161a] file:border-[#2b2b2b] file:text-[#dcdcdc]">
                @error('email_default_cover')<p class="mt-2 text-xs text-[#ff9e9e]">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>
</section>
