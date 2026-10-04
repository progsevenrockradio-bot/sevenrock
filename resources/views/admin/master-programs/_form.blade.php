@php
    $days = [
        'LUNES' => 'Lunes',
        'MARTES' => 'Martes',
        'MIERCOLES' => 'Miércoles',
        'JUEVES' => 'Jueves',
        'VIERNES' => 'Viernes',
        'SABADO' => 'Sábado',
        'DOMINGO' => 'Domingo',
    ];
@endphp

<section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $masterProgram->exists ? 'Editar programa maestro' : 'Nuevo programa maestro' }}</h1>
            <p class="mt-2 max-w-2xl text-[#9a9a9a]">
                Este formulario define el programa base que usa la grilla, el upload y la sincronización con RadioBOSS y Archive.org.
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            @if ($masterProgram->exists && filled($generateCodeAction ?? null))
                <form action="{{ $generateCodeAction }}" method="POST">
                    @csrf
                    <button type="submit" class="lucille-button">Generar código automático</button>
                </form>
            @endif
            <a href="{{ route('admin.programs.index') }}" class="lucille-button">Panel de códigos</a>
            <a href="{{ route('admin.master-programs.index') }}" class="lucille-button">Volver</a>
        </div>
    </div>

    <form action="{{ $formAction }}" method="POST" class="mt-8 space-y-8">
        @csrf
        @if ($formMethod !== 'POST')
            @method($formMethod)
        @endif

        <section class="space-y-5">
            <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Información base</h2>
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Nombre</label>
                    <input name="nombre" value="{{ old('nombre', $masterProgram->nombre) }}" class="lucille-product-field w-full" required>
                    @error('nombre')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Conductor</label>
                    <input name="conductor" value="{{ old('conductor', $masterProgram->conductor) }}" class="lucille-product-field w-full" required>
                    @error('conductor')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Día de transmisión</label>
                    <select name="dia_transmision" class="lucille-product-field lucille-select-field w-full" required>
                        <option value="">-- seleccionar --</option>
                        @foreach ($days as $value => $label)
                            <option value="{{ $value }}" @selected(old('dia_transmision', $masterProgram->dia_transmision) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('dia_transmision')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Hora de transmisión</label>
                    <input type="time" step="1" name="hora_transmision" value="{{ old('hora_transmision', $masterProgram->hora_transmision ? substr((string) $masterProgram->hora_transmision, 0, 8) : '') }}" class="lucille-product-field w-full">
                    @error('hora_transmision')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Zona horaria</label>
                    <input name="timezone" value="{{ old('timezone', $masterProgram->timezone) }}" class="lucille-product-field w-full" required>
                    @error('timezone')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Duración estimada (min)</label>
                    <input type="number" min="1" max="1440" name="duracion_minutos" value="{{ old('duracion_minutos', $masterProgram->duracion_minutos ?? 120) }}" class="lucille-product-field w-full" required>
                    @error('duracion_minutos')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Género</label>
                    <input name="genero" value="{{ old('genero', $masterProgram->genero) }}" class="lucille-product-field w-full" required>
                    @error('genero')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Código de programa</label>
                    <input name="program_code" value="{{ old('program_code', $masterProgram->program_code) }}" class="lucille-product-field w-full" maxlength="12" placeholder="ROCKNOCHE24">
                    @error('program_code')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Prefijo base</label>
                    <input name="code_prefix" value="{{ old('code_prefix', $masterProgram->code_prefix) }}" class="lucille-product-field w-full" maxlength="12" placeholder="ROCKNOCHE">
                    @error('code_prefix')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex flex-col gap-3">
                <label class="flex items-center gap-3 text-sm text-[#9a9a9a]">
                    <input type="checkbox" name="activo" value="1" @checked(old('activo', $masterProgram->activo ?? true)) class="h-4 w-4">
                    Programa activo
                </label>
                
                <label class="flex items-center gap-3 text-sm text-[#9a9a9a]">
                    <input type="checkbox" name="sync_archive_org" value="1" @checked(old('sync_archive_org', $masterProgram->sync_archive_org ?? true)) class="h-4 w-4">
                    Subir episodios automáticamente a Archive.org (Bucket Público)
                </label>
            </div>
        </section>

        <section class="space-y-5">
            <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Presentación</h2>
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Carátula URL</label>
                    <input name="caratula_url" value="{{ old('caratula_url', $masterProgram->caratula_url) }}" class="lucille-product-field w-full">
                    @error('caratula_url')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Imagen en curso</label>
                    <input name="live_image_url" value="{{ old('live_image_url', $masterProgram->live_image_url) }}" class="lucille-product-field w-full">
                    @error('live_image_url')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Título en curso</label>
                    <input name="live_title" value="{{ old('live_title', $masterProgram->live_title) }}" class="lucille-product-field w-full">
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Inicio en curso</label>
                    <input type="datetime-local" name="live_starts_at" value="{{ old('live_starts_at', optional($masterProgram->live_starts_at)->format('Y-m-d\TH:i')) }}" class="lucille-product-field w-full">
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Fin en curso</label>
                    <input type="datetime-local" name="live_ends_at" value="{{ old('live_ends_at', optional($masterProgram->live_ends_at)->format('Y-m-d\TH:i')) }}" class="lucille-product-field w-full">
                </div>

                <div class="md:col-span-2">
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Descripción</label>
                    <textarea name="descripcion" rows="5" class="lucille-product-field w-full">{{ old('descripcion', $masterProgram->descripcion) }}</textarea>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Descripción en curso</label>
                    <textarea name="live_description" rows="5" class="lucille-product-field w-full">{{ old('live_description', $masterProgram->live_description) }}</textarea>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Comentario predeterminado</label>
                    <textarea name="comentario_predeterminado" rows="4" class="lucille-product-field w-full">{{ old('comentario_predeterminado', $masterProgram->comentario_predeterminado) }}</textarea>
                </div>
            </div>
        </section>

        <section class="space-y-5">
            <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Programación editorial</h2>
            <div class="grid gap-5 md:grid-cols-3">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Noticias base</label>
                    <textarea name="default_news_ids_text" rows="6" class="lucille-product-field w-full font-mono text-[12px] leading-6" placeholder="Uno por línea">{{ old('default_news_ids_text', $defaultNewsIdsText) }}</textarea>
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Noticias en curso</label>
                    <textarea name="live_news_ids_text" rows="6" class="lucille-product-field w-full font-mono text-[12px] leading-6" placeholder="Uno por línea">{{ old('live_news_ids_text', $liveNewsIdsText) }}</textarea>
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Noticias previas</label>
                    <textarea name="preview_news_ids_text" rows="6" class="lucille-product-field w-full font-mono text-[12px] leading-6" placeholder="Uno por línea">{{ old('preview_news_ids_text', $previewNewsIdsText) }}</textarea>
                </div>
            </div>
        </section>

        <section class="space-y-5">
            <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">RadioBOSS y Archive.org</h2>
            <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.22)] p-5">
                <p class="text-sm leading-6 text-[#b8b8b8]">Si dejas vacío el correo del programa maestro, el sistema tomará los valores globales definidos en <span class="text-[#e0e0e0]">Theme Settings</span>. Usa esta pantalla solo cuando ese programa necesite una excepción.</p>
            </div>
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Ruta FTP</label>
                    <input name="ruta_ftp" value="{{ old('ruta_ftp', $masterProgram->ruta_ftp) }}" class="lucille-product-field w-full">
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Archive.org Identifier</label>
                    <input name="archive_identifier" value="{{ old('archive_identifier', $masterProgram->archive_identifier) }}" class="lucille-product-field w-full">
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Correo de notificación</label>
                    <input name="email_notificacion" value="{{ old('email_notificacion', $masterProgram->email_notificacion) }}" class="lucille-product-field w-full">
                    <p class="mt-2 text-xs text-[#9a9a9a]">Destino principal del programa maestro. Si se deja vacío, usa el valor global del panel.</p>
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Correo copia</label>
                    <input name="email_copia_notificacion" value="{{ old('email_copia_notificacion', $masterProgram->email_copia_notificacion) }}" class="lucille-product-field w-full">
                    <p class="mt-2 text-xs text-[#9a9a9a]">Copia editable por programa maestro. Si se deja vacío, usa la copia global del panel.</p>
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Red social 1</label>
                    <input name="red_social1_url" value="{{ old('red_social1_url', $masterProgram->red_social1_url) }}" class="lucille-product-field w-full">
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Red social 2</label>
                    <input name="red_social2_url" value="{{ old('red_social2_url', $masterProgram->red_social2_url) }}" class="lucille-product-field w-full">
                </div>
            </div>
        </section>

        <section class="space-y-5">
            <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Estadísticas</h2>
            <div class="grid gap-5 md:grid-cols-4">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Archive views</label>
                    <input type="number" min="0" name="vistas_archive" value="{{ old('vistas_archive', $masterProgram->vistas_archive ?? 0) }}" class="lucille-product-field w-full">
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Escuchas locales</label>
                    <input type="number" min="0" name="escuchas_locales" value="{{ old('escuchas_locales', $masterProgram->escuchas_locales ?? 0) }}" class="lucille-product-field w-full">
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Vistas totales</label>
                    <input type="number" min="0" name="vistas_totales" value="{{ old('vistas_totales', $masterProgram->vistas_totales ?? 0) }}" class="lucille-product-field w-full">
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Stats updated</label>
                    <input type="datetime-local" name="stats_updated_at" value="{{ old('stats_updated_at', optional($masterProgram->stats_updated_at)->format('Y-m-d\TH:i')) }}" class="lucille-product-field w-full">
                </div>
            </div>
        </section>

        {{-- ── BLOQUE EMISIONES ─────────────────────────────────────────────── --}}
        <section class="space-y-5"
            @php
                $emisionesArray = $emisiones->map(function($e) {
                    return [
                        'id' => $e->id,
                        'tipo' => $e->tipo,
                        'etiqueta' => (string) $e->etiqueta,
                        'dia_semana' => $e->dia_semana,
                        'hora_inicio' => substr((string) $e->hora_inicio, 0, 5),
                        'duracion_minutos' => $e->duracion_minutos,
                        'enlace' => (string) $e->enlace,
                        'url_podcast' => (string) $e->url_podcast,
                        'notas' => (string) $e->notas,
                        'activo' => (bool) $e->activo,
                    ];
                })->values()->all();
                $emisionesJson = json_encode($emisionesArray, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
            @endphp
            x-data="{
                emisiones: {{ $emisionesJson }},
                addEmision() {
                    this.emisiones.push({
                        id: 0, tipo: 'normal', etiqueta: '', dia_semana: 'LUNES',
                        hora_inicio: '', duracion_minutos: 120, enlace: '',
                        url_podcast: '', notas: '', activo: true
                    });
                },
                removeEmision(index) {
                    this.emisiones.splice(index, 1);
                }
            }"
        >
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Emisiones semanales</h2>
                <button type="button" @click="addEmision()" class="lucille-button-solid text-sm">+ Añadir emisión</button>
            </div>
            <div class="border border-[#2b2b2b] bg-[rgba(0,0,0,.22)] p-4 text-sm text-[#b8b8b8]">
                Define aquí todos los slots de este programa: su emisión normal, retransmisiones (podcast diferido) y emisiones en vivo con enlace de stream. La parrilla mostrará una entrada por cada emisión activa.
            </div>

            <template x-if="emisiones.length === 0">
                <p class="text-sm text-[#9a9a9a]">Sin emisiones configuradas. Pulsa "+ Añadir emisión" para crear la primera.</p>
            </template>

            <template x-for="(em, index) in emisiones" :key="index">
                <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.6)] p-5 space-y-4">
                    {{-- Campos ocultos --}}
                    <input type="hidden" :name="'emisiones[' + index + '][id]'" :value="em.id">

                    <div class="grid gap-4 md:grid-cols-3">
                        {{-- Tipo --}}
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Tipo</label>
                            <select :name="'emisiones[' + index + '][tipo]'" x-model="em.tipo"
                                class="lucille-product-field lucille-select-field w-full">
                                <option value="normal">Normal</option>
                                <option value="retransmision">Retransmisión / Podcast</option>
                                <option value="en_vivo">En Vivo</option>
                            </select>
                        </div>

                        {{-- Día --}}
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Día</label>
                            <select :name="'emisiones[' + index + '][dia_semana]'" x-model="em.dia_semana"
                                class="lucille-product-field lucille-select-field w-full">
                                <option value="LUNES">Lunes</option>
                                <option value="MARTES">Martes</option>
                                <option value="MIERCOLES">Miércoles</option>
                                <option value="JUEVES">Jueves</option>
                                <option value="VIERNES">Viernes</option>
                                <option value="SABADO">Sábado</option>
                                <option value="DOMINGO">Domingo</option>
                            </select>
                        </div>

                        {{-- Hora --}}
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Hora de inicio</label>
                            <input type="time" step="1" :name="'emisiones[' + index + '][hora_inicio]'" x-model="em.hora_inicio"
                                class="lucille-product-field w-full">
                        </div>

                        {{-- Duración --}}
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Duración (min)</label>
                            <input type="number" min="1" max="600" :name="'emisiones[' + index + '][duracion_minutos]'" x-model.number="em.duracion_minutos"
                                class="lucille-product-field w-full">
                        </div>

                        {{-- Etiqueta --}}
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Etiqueta en parrilla</label>
                            <input type="text" :name="'emisiones[' + index + '][etiqueta]'" x-model="em.etiqueta"
                                placeholder="Ej. Rock al Palo - Retransmisión"
                                class="lucille-product-field w-full">
                        </div>

                        {{-- Enlace en vivo (solo visible si tipo = en_vivo) --}}
                        <div x-show="em.tipo === 'en_vivo'">
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Enlace en vivo (URL)</label>
                            <input type="url" :name="'emisiones[' + index + '][enlace]'" x-model="em.enlace"
                                placeholder="https://stream.ejemplo.com/live"
                                class="lucille-product-field w-full">
                        </div>

                        {{-- URL Podcast --}}
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">URL Podcast</label>
                            <input type="url" :name="'emisiones[' + index + '][url_podcast]'" x-model="em.url_podcast"
                                placeholder="https://archive.org/..."
                                class="lucille-product-field w-full">
                        </div>
                    </div>

                    {{-- Notas --}}
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Notas internas</label>
                        <textarea rows="2" :name="'emisiones[' + index + '][notas]'" x-model="em.notas"
                            class="lucille-product-field w-full"></textarea>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <label class="flex items-center gap-3 text-sm text-[#9a9a9a]">
                            <input type="hidden" :name="'emisiones[' + index + '][activo]'" value="0">
                            <input type="checkbox" :name="'emisiones[' + index + '][activo]'" value="1"
                                x-model="em.activo" class="h-4 w-4">
                            Emisión activa
                        </label>
                        <button type="button" @click="removeEmision(index)"
                            class="text-xs uppercase tracking-[.15em] text-red-400 hover:text-red-300">
                            Eliminar esta emisión
                        </button>
                    </div>
                </div>
            </template>
        </section>
        {{-- ── / BLOQUE EMISIONES ───────────────────────────────────────────── --}}

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="lucille-button-solid">{{ $buttonLabel }}</button>
            <a href="{{ route('admin.master-programs.index') }}" class="lucille-button">Cancelar</a>
        </div>
    </form>
</section>
