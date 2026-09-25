<section class="space-y-6">
    <!-- Encabezado de la pestaña -->
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <div class="flex items-center gap-4">
            <div class="text-3xl">🛡️</div>
            <div>
                <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Moderación Global</h2>
                <p class="mt-1 text-sm leading-7 text-[#9a9a9a]">Controla qué tipos de contenido generado por usuarios deben pasar por moderación antes de ser públicos. Cuando está activo, se enviará un correo para su aprobación.</p>
            </div>
        </div>
    </div>

    <!-- Primera Fila: Correos -->
    <div class="grid gap-6">
        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc] border-b border-[#2b2b2b] pb-3 mb-6">Correos de Moderación Adicionales</h3>
            <div class="space-y-5">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Correos Extras (separados por coma)</label>
                    <input type="text" name="moderation_extra_emails"
                           value="{{ old('moderation_extra_emails', $settings->moderation_extra_emails) }}"
                           class="w-full border border-[#2b2b2b] bg-[#1a1a1c] px-4 py-3 text-sm text-[#dcdcdc] focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                           placeholder="ejemplo@correo.com, admin@correo.com">
                    <p class="mt-2 text-xs text-[#7b7b7b]">Además de los correos por defecto, las alertas de moderación llegarán a estas direcciones.</p>
                </div>
            </div>
        </section>
    </div>

    <!-- Segunda Fila: Interruptores -->
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        @php
            $moderationTypes = [
                'submission' => 'Subida de Maquetas (Submissions)',
                'talent_registration' => 'Registro de Talentos',
                'media' => 'Contenido Multimedia (Talento)',
                'album' => 'Álbumes / EP (Talento)',
                'product' => 'Productos de Tienda',
                'wall_post' => 'Publicaciones en el Muro',
                'comment' => 'Comentarios (Noticias y Perfiles)',
                'contact' => 'Formularios de Contacto',
                'affiliate' => 'Registro de Afiliados (Fans)',
                'agency_band' => 'Registro de Bandas (Agencias)',
                'contract' => 'Firmas de Contratos',
                'event' => 'Publicación de Eventos',
            ];
        @endphp

        @foreach($moderationTypes as $key => $label)
            <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6 flex flex-col justify-between">
                <div>
                    <h3 class="font-display text-lg tracking-[.12em] text-[#dcdcdc] mb-2">{{ $label }}</h3>
                    <p class="text-xs text-[#7b7b7b] mb-4">Requerir aprobación de un administrador antes de que este contenido esté activo.</p>
                </div>
                <div class="flex items-center">
                    <input type="hidden" name="moderation_require_{{ $key }}" value="0">
                    <label class="flex items-center cursor-pointer">
                        <div class="relative">
                            <input type="checkbox" class="sr-only" name="moderation_require_{{ $key }}" value="1" @if(old('moderation_require_'.$key, $settings->{'moderation_require_'.$key})) checked @endif>
                            <div class="block bg-[#1a1a1c] border border-[#2b2b2b] w-10 h-6 rounded-full transition"></div>
                            <div class="dot absolute left-1 top-1 bg-[#7b7b7b] w-4 h-4 rounded-full transition"></div>
                        </div>
                        <div class="ml-3 text-sm text-[#dcdcdc] font-bold uppercase">
                            Requerir Moderación
                        </div>
                    </label>
                </div>
            </section>
        @endforeach
    </div>
    
    <style>
        input:checked ~ .block {
            background-color: #c32720;
            border-color: #c32720;
        }
        input:checked ~ .dot {
            transform: translateX(100%);
            background-color: #ffffff;
        }
    </style>
</section>
