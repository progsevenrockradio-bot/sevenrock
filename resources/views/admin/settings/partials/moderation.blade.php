<div class="space-y-5 font-sans">
    {{-- Correo adicional de alertas de moderación --}}
    <section class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-4">
        <div class="border-b border-white/10 pb-3">
            <h2 class="font-display text-base font-bold uppercase tracking-wide text-[#dcdcdc]">Destinatarios adicionales de moderación</h2>
            <p class="mt-0.5 text-xs text-gray-400">
                Define qué contenidos enviados por bandas, agencias o oyentes requieren revisión previa de un administrador antes de publicarse.
            </p>
        </div>

        <div>
            <label for="moderation_extra_emails" class="mb-1.5 block text-xs font-medium text-[#dcdcdc]">
                Correos adicionales para alertas de moderación (separados por coma)
            </label>
            <input type="text" id="moderation_extra_emails" name="moderation_extra_emails"
                   value="{{ old('moderation_extra_emails', $settings->moderation_extra_emails) }}"
                   placeholder="moderacion@sevenrockradio.com, direccion@sevenrockradio.com"
                   class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 font-mono text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]">
            <p class="mt-1 text-[11px] text-gray-400">Recibirán copia de las solicitudes pendientes además del destinatario principal del sistema.</p>
        </div>
    </section>

    {{-- Reglas por tipo de contenido --}}
    <section class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-4">
        <div class="border-b border-white/10 pb-3">
            <h2 class="font-display text-base font-bold uppercase tracking-wide text-[#dcdcdc]">Reglas de aprobación previa por módulo</h2>
            <p class="mt-0.5 text-xs text-gray-400">Marca los circuitos que deben quedar retenidos hasta ser aprobados manualmente.</p>
        </div>

        @php
            $moderationTypes = [
                'submission' => ['label' => 'Envío de maquetas (Buzón)', 'desc' => 'Pistas enviadas por bandas para sonar en la emisora.'],
                'talent_registration' => ['label' => 'Registro de bandas (Muro)', 'desc' => 'Nuevas cuentas de bandas registradas en el Muro del Rock.'],
                'media' => ['label' => 'Archivos multimedia de bandas', 'desc' => 'Fotos, audios o vídeos subidos al perfil de una banda.'],
                'album' => ['label' => 'Álbumes y EP de bandas', 'desc' => 'Lanzamientos discográficos publicados desde el perfil de talento.'],
                'product' => ['label' => 'Productos de tienda', 'desc' => 'Artículos de merchandising añadidos por las bandas.'],
                'wall_post' => ['label' => 'Publicaciones en la comunidad', 'desc' => 'Mensajes publicados en el muro social de la comunidad.'],
                'comment' => ['label' => 'Comentarios públicos', 'desc' => 'Comentarios de lectores en noticias, artículos y perfiles.'],
                'contact' => ['label' => 'Formularios de contacto', 'desc' => 'Consultas generales recibidas desde la página de contacto.'],
                'affiliate' => ['label' => 'Registro de afiliados (Oyentes)', 'desc' => 'Altas de seguidores en el club de beneficios.'],
                'agency_band' => ['label' => 'Bandas registradas por agencias', 'desc' => 'Perfiles dados de alta a través del portal de agencias.'],
                'contract' => ['label' => 'Firmas de contratos', 'desc' => 'Acuerdos digitales firmados por representantes o artistas.'],
                'event' => ['label' => 'Publicación de conciertos', 'desc' => 'Eventos y fechas de gira enviados para la agenda.'],
            ];
        @endphp

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($moderationTypes as $key => $meta)
                @php
                    $fieldName = 'moderation_require_' . $key;
                    $isChecked = (bool) old($fieldName, $settings->{$fieldName});
                @endphp
                <div class="rounded-lg border border-white/5 bg-[#151518] p-3.5 flex flex-col justify-between gap-3">
                    <div>
                        <h3 class="text-xs font-semibold text-[#dcdcdc]">{{ $meta['label'] }}</h3>
                        <p class="mt-0.5 text-[11px] text-gray-400 leading-relaxed">{{ $meta['desc'] }}</p>
                    </div>
                    <div class="pt-2 border-t border-white/5">
                        <input type="hidden" name="{{ $fieldName }}" value="0">
                        <label class="inline-flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" name="{{ $fieldName }}" value="1"
                                   @checked($isChecked)
                                   class="h-4 w-4 rounded border-white/20 bg-black text-[#c32720] focus:ring-[#c32720]">
                            <span class="text-xs font-medium text-gray-200">Requerir aprobación previa</span>
                        </label>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</div>
