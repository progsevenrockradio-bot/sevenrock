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
    $inputClass = 'h-10 w-full rounded-lg border border-white/10 bg-[#161619] px-3 text-sm text-zinc-100 placeholder-zinc-500 transition focus:border-[#c32720] focus:outline-none focus:ring-2 focus:ring-[#c32720]/30';
    $textareaClass = 'w-full rounded-lg border border-white/10 bg-[#161619] p-3 text-sm text-zinc-100 placeholder-zinc-500 transition focus:border-[#c32720] focus:outline-none focus:ring-2 focus:ring-[#c32720]/30';
    $labelClass = 'mb-1.5 block text-xs font-medium text-zinc-300';
@endphp

<div class="space-y-6">
    {{-- Cabecera del formulario --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="font-display text-2xl font-bold uppercase tracking-wider text-white">
                {{ $masterProgram->exists ? 'Editar programa de radio' : 'Nuevo programa de radio' }}
            </h1>
            <p class="mt-1 text-sm text-zinc-400">
                Configuración del programa base para la parrilla, portal de conductores y sincronización con RadioBOSS y Archive.org.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($masterProgram->exists && filled($generateCodeAction ?? null))
                <form action="{{ $generateCodeAction }}" method="POST">
                    @csrf
                    <button type="submit" class="inline-flex h-9 items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3.5 text-xs font-medium text-zinc-200 transition hover:border-white/20 hover:bg-white/10 hover:text-white">
                        Generar código automático
                    </button>
                </form>
            @endif
            <a href="{{ route('admin.programs.index') }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3.5 text-xs font-medium text-zinc-200 transition hover:border-white/20 hover:bg-white/10 hover:text-white">
                Invitaciones a programas
            </a>
            <a href="{{ route('admin.master-programs.index') }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3.5 text-xs font-medium text-zinc-300 transition hover:bg-white/10 hover:text-white">
                ← Volver a programas
            </a>
        </div>
    </div>

    <form action="{{ $formAction }}" method="POST" class="space-y-6">
        @csrf
        @if ($formMethod !== 'POST')
            @method($formMethod)
        @endif

        {{-- 1. Información base --}}
        <section class="rounded-xl border border-white/10 bg-[#101012] p-6 space-y-5">
            <div class="border-b border-white/10 pb-3">
                <h2 class="font-display text-lg font-bold uppercase tracking-wider text-zinc-100">Información base y horario</h2>
                <p class="mt-0.5 text-xs text-zinc-400">Datos principales de identificación del programa y franja horaria de referencia.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="{{ $labelClass }}">Nombre del programa *</label>
                    <input name="nombre" value="{{ old('nombre', $masterProgram->nombre) }}" class="{{ $inputClass }}" required>
                    @error('nombre')<p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="{{ $labelClass }}">Conductor / Locutor *</label>
                    <input name="conductor" value="{{ old('conductor', $masterProgram->conductor) }}" class="{{ $inputClass }}" required>
                    @error('conductor')<p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="{{ $labelClass }}">Género musical *</label>
                    <input name="genero" value="{{ old('genero', $masterProgram->genero) }}" class="{{ $inputClass }}" required>
                    @error('genero')<p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="{{ $labelClass }}">País</label>
                    <input name="pais" value="{{ old('pais', $masterProgram->pais) }}" class="{{ $inputClass }}" placeholder="Ej. España, Argentina, México...">
                    @error('pais')<p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="{{ $labelClass }}">Día de transmisión *</label>
                    <select name="dia_transmision" class="{{ $inputClass }}" required>
                        <option value="">Seleccionar día...</option>
                        @foreach ($days as $value => $label)
                            <option value="{{ $value }}" @selected(old('dia_transmision', $masterProgram->dia_transmision) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('dia_transmision')<p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="{{ $labelClass }}">Hora de transmisión</label>
                    <input type="time" step="1" name="hora_transmision" value="{{ old('hora_transmision', $masterProgram->hora_transmision ? substr((string) $masterProgram->hora_transmision, 0, 8) : '') }}" class="{{ $inputClass }}">
                    @error('hora_transmision')<p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="{{ $labelClass }}">Zona horaria *</label>
                    <input name="timezone" value="{{ old('timezone', $masterProgram->timezone) }}" class="{{ $inputClass }}" required>
                    @error('timezone')<p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="{{ $labelClass }}">Duración estimada (min) *</label>
                    <input type="number" min="1" max="1440" name="duracion_minutos" value="{{ old('duracion_minutos', $masterProgram->duracion_minutos ?? 120) }}" class="{{ $inputClass }}" required>
                    @error('duracion_minutos')<p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="{{ $labelClass }}">Margen de cierre antes de emisión (min) *</label>
                    <input type="number" min="15" max="10080" step="15" name="submission_cutoff_minutes" value="{{ old('submission_cutoff_minutes', $masterProgram->submission_cutoff_minutes ?? 90) }}" class="{{ $inputClass }}" required>
                    <p class="mt-1 text-[11px] text-zinc-500">Antelación obligatoria de entrega del audio (ej. 90 min o 1440 min).</p>
                    @error('submission_cutoff_minutes')<p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="{{ $labelClass }}">Código de programa</label>
                    <input name="program_code" value="{{ old('program_code', $masterProgram->program_code) }}" class="{{ $inputClass }} font-mono" maxlength="12" placeholder="ROCKNOCHE24">
                    @error('program_code')<p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="{{ $labelClass }}">Prefijo base</label>
                    <input name="code_prefix" value="{{ old('code_prefix', $masterProgram->code_prefix) }}" class="{{ $inputClass }} font-mono" maxlength="12" placeholder="ROCKNOCHE">
                    @error('code_prefix')<p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex flex-wrap gap-6 border-t border-white/10 pt-4">
                <label class="inline-flex items-center gap-2.5 text-sm text-zinc-200 cursor-pointer">
                    <input type="checkbox" name="activo" value="1" @checked(old('activo', $masterProgram->activo ?? true)) class="h-4 w-4 rounded border-white/20 bg-zinc-900 accent-[#c32720]">
                    <span>Programa activo en parrilla</span>
                </label>

                <label class="inline-flex items-center gap-2.5 text-sm text-zinc-200 cursor-pointer">
                    <input type="checkbox" name="sync_archive_org" value="1" @checked(old('sync_archive_org', $masterProgram->sync_archive_org ?? true)) class="h-4 w-4 rounded border-white/20 bg-zinc-900 accent-[#c32720]">
                    <span>Sincronizar episodios automáticamente con Archive.org</span>
                </label>
            </div>
        </section>

        {{-- 2. Presentación y datos en curso --}}
        <section class="rounded-xl border border-white/10 bg-[#101012] p-6 space-y-5">
            <div class="border-b border-white/10 pb-3">
                <h2 class="font-display text-lg font-bold uppercase tracking-wider text-zinc-100">Presentación y emisión en curso</h2>
                <p class="mt-0.5 text-xs text-zinc-400">Imágenes de portada, textos descriptivos y metadatos durante la transmisión activa.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="{{ $labelClass }}">URL de carátula principal</label>
                    <input name="caratula_url" value="{{ old('caratula_url', $masterProgram->caratula_url) }}" class="{{ $inputClass }}">
                    @error('caratula_url')<p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="{{ $labelClass }}">URL de imagen en curso</label>
                    <input name="live_image_url" value="{{ old('live_image_url', $masterProgram->live_image_url) }}" class="{{ $inputClass }}">
                    @error('live_image_url')<p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="{{ $labelClass }}">Título en curso</label>
                    <input name="live_title" value="{{ old('live_title', $masterProgram->live_title) }}" class="{{ $inputClass }}">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $labelClass }}">Inicio en curso</label>
                        <input type="datetime-local" name="live_starts_at" value="{{ old('live_starts_at', optional($masterProgram->live_starts_at)->format('Y-m-d\TH:i')) }}" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Fin en curso</label>
                        <input type="datetime-local" name="live_ends_at" value="{{ old('live_ends_at', optional($masterProgram->live_ends_at)->format('Y-m-d\TH:i')) }}" class="{{ $inputClass }}">
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Descripción general del programa</label>
                    <textarea name="descripcion" rows="4" class="{{ $textareaClass }}">{{ old('descripcion', $masterProgram->descripcion) }}</textarea>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Descripción en curso</label>
                    <textarea name="live_description" rows="4" class="{{ $textareaClass }}">{{ old('live_description', $masterProgram->live_description) }}</textarea>
                </div>

                <div class="md:col-span-2">
                    <label class="{{ $labelClass }}">Comentario predeterminado</label>
                    <textarea name="comentario_predeterminado" rows="2" class="{{ $textareaClass }}">{{ old('comentario_predeterminado', $masterProgram->comentario_predeterminado) }}</textarea>
                </div>
            </div>
        </section>

        {{-- 3. Guía Editorial y Portal de Conductores --}}
        <section class="rounded-xl border border-white/10 bg-[#101012] p-6 space-y-5">
            <input type="hidden" name="conductor_submissions_section_present" value="1">
            @php
                $guide = is_array($masterProgram->editorial_guide ?? null) ? $masterProgram->editorial_guide : [];
                $availableConductors = $availableConductors ?? collect();
                $authorizedConductorIds = $authorizedConductorIds ?? [];
            @endphp

            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-white/10 pb-3">
                <div>
                    <h2 class="font-display text-lg font-bold uppercase tracking-wider text-zinc-100">Guía editorial y portal de conductores</h2>
                    <p class="mt-0.5 text-xs text-zinc-400">Parámetros para revisión de episodios, propuestas de IA y acceso de locutores.</p>
                </div>
                @if ($masterProgram->exists)
                    <span class="rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 font-mono text-xs text-zinc-300">
                        R2: programas_procesados/{{ $masterProgram->resolveStorageFolder() }}/
                    </span>
                @endif
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="{{ $labelClass }}">Modo de entrega y continuidad</label>
                    <select name="editorial_mode" class="{{ $inputClass }}">
                        <option value="mandatory_review" @selected(old('editorial_mode', $masterProgram->editorial_mode ?? 'mandatory_review') === 'mandatory_review')>
                            A. Revisión obligatoria antes de distribuir (predeterminado)
                        </option>
                        <option value="autonomous" @selected(old('editorial_mode', $masterProgram->editorial_mode ?? 'mandatory_review') === 'autonomous')>
                            B. Entrega autónoma autorizada (retiene ante incidencias)
                        </option>
                    </select>
                    @error('editorial_mode')<p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-center pt-2">
                    <label class="flex items-start gap-2.5 text-xs text-zinc-300 cursor-pointer">
                        <input type="checkbox" name="confirm_autonomous_mode" value="1"
                            @checked(old('confirm_autonomous_mode', $masterProgram->isAutonomousDeliveryAuthorized()))
                            class="mt-0.5 h-4 w-4 shrink-0 rounded border-white/20 bg-zinc-900 accent-[#c32720]">
                        <span>
                            Autorizo expresamente la entrega autónoma cuando el administrador no esté disponible (cualquier incidencia técnica o editorial retendrá el episodio para revisión).
                        </span>
                    </label>
                </div>

                <div class="md:col-span-2">
                    <label class="{{ $labelClass }}">Descripción, identidad y público del programa</label>
                    <textarea name="editorial_guide[identity_and_audience]" rows="2" class="{{ $textareaClass }}" placeholder="Enfoque temático, identidad sonora y perfil de oyente...">{{ old('editorial_guide.identity_and_audience', $guide['identity_and_audience'] ?? '') }}</textarea>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Tono y estilo</label>
                    <textarea name="editorial_guide[tone_and_style]" rows="2" class="{{ $textareaClass }}" placeholder="Ej. Directo, documentado, cercano al rock clásico...">{{ old('editorial_guide.tone_and_style', $guide['tone_and_style'] ?? '') }}</textarea>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Estructura de título, resumen y descripción</label>
                    <textarea name="editorial_guide[structure_rules]" rows="2" class="{{ $textareaClass }}" placeholder="Reglas para título, resumen corto y reseña completa...">{{ old('editorial_guide.structure_rules', $guide['structure_rules'] ?? '') }}</textarea>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Requisitos de portada</label>
                    <textarea name="editorial_guide[cover_requirements]" rows="2" class="{{ $textareaClass }}" placeholder="Formato cuadrado, legibilidad, estética...">{{ old('editorial_guide.cover_requirements', $guide['cover_requirements'] ?? '') }}</textarea>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Reglas particulares</label>
                    <textarea name="editorial_guide[particular_rules]" rows="2" class="{{ $textareaClass }}" placeholder="Menciones requeridas, secciones fijas, restricciones...">{{ old('editorial_guide.particular_rules', $guide['particular_rules'] ?? '') }}</textarea>
                </div>

                <div class="md:col-span-2">
                    <label class="{{ $labelClass }}">Ejemplos opcionales de referencia</label>
                    <textarea name="editorial_guide[optional_examples]" rows="2" class="{{ $textareaClass }}" placeholder="Ejemplos de títulos o resúmenes aprobados...">{{ old('editorial_guide.optional_examples', $guide['optional_examples'] ?? '') }}</textarea>
                </div>
            </div>

            <div class="border-t border-white/10 pt-5 space-y-4">
                <h3 class="text-sm font-semibold text-zinc-200">Conductores autorizados para entregar episodios</h3>
                @if ($availableConductors->isNotEmpty())
                    <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($availableConductors as $conductorUser)
                            <label class="flex items-center gap-2.5 rounded-lg border border-white/5 bg-white/[0.02] px-3 py-2 text-xs text-zinc-200 cursor-pointer hover:border-white/10">
                                <input type="checkbox" name="conductor_user_ids[]" value="{{ $conductorUser->id }}"
                                    @checked(in_array((int) $conductorUser->id, old('conductor_user_ids', $authorizedConductorIds), true))
                                    class="h-4 w-4 rounded border-white/20 bg-zinc-900 accent-[#c32720]">
                                <span class="truncate">{{ $conductorUser->name }} <span class="text-zinc-500">({{ $conductorUser->email }})</span></span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-zinc-500">No hay cuentas de conductor registradas todavía. Puedes dar de alta y vincular una a continuación.</p>
                @endif

                <div class="grid gap-3 md:grid-cols-3 pt-2">
                    <div>
                        <label class="{{ $labelClass }}">Nuevo conductor (nombre)</label>
                        <input name="new_conductor_name" value="{{ old('new_conductor_name') }}" class="{{ $inputClass }}" placeholder="Nombre completo">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Nuevo conductor (correo)</label>
                        <input type="email" name="new_conductor_email" value="{{ old('new_conductor_email') }}" class="{{ $inputClass }}" placeholder="conductor@ejemplo.com">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Contraseña inicial</label>
                        <input type="password" name="new_conductor_password" class="{{ $inputClass }}" placeholder="Mínimo 8 caracteres">
                    </div>
                </div>
            </div>
        </section>

        {{-- 4. Emisiones semanales --}}
        <section class="rounded-xl border border-white/10 bg-[#101012] p-6 space-y-5"
            @php
                $emisionesArray = $emisiones->map(function($e) {
                    $seg = $e->duracion_segundos;
                    return [
                        'id' => $e->id,
                        'tipo' => $e->tipo,
                        'etiqueta' => (string) $e->etiqueta,
                        'dia_semana' => $e->dia_semana,
                        'hora_inicio' => substr((string) $e->hora_inicio, 0, 5),
                        'duracion_minutos' => $e->duracion_minutos,
                        'duracion_segundos' => $seg,
                        'duracion_real_min' => $seg !== null ? intdiv($seg, 60) : '',
                        'duracion_real_sec' => $seg !== null ? ($seg % 60) : '',
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
                        hora_inicio: '', duracion_minutos: 120, duracion_segundos: null,
                        duracion_real_min: '', duracion_real_sec: '', enlace: '',
                        url_podcast: '', notas: '', activo: true
                    });
                },
                removeEmision(index) {
                    this.emisiones.splice(index, 1);
                }
            }"
        >
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 pb-3">
                <div>
                    <h2 class="font-display text-lg font-bold uppercase tracking-wider text-zinc-100">Emisiones semanales</h2>
                    <p class="mt-0.5 text-xs text-zinc-400">Pases normales, retransmisiones y directos asociados a este programa.</p>
                </div>
                <button type="button" @click="addEmision()" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#c32720] px-3.5 text-xs font-semibold text-white transition hover:bg-[#d62d26]">
                    <span>+ Añadir emisión</span>
                </button>
            </div>

            <template x-if="emisiones.length === 0">
                <p class="py-4 text-center text-xs text-zinc-500">Sin emisiones configuradas. Pulsa «+ Añadir emisión» para registrar el primer pase.</p>
            </template>

            <div class="space-y-4">
                <template x-for="(em, index) in emisiones" :key="index">
                    <div class="rounded-lg border border-white/10 bg-[#141418] p-4 space-y-4">
                        <input type="hidden" :name="'emisiones[' + index + '][id]'" :value="em.id">
                        <input type="hidden" :name="'emisiones[' + index + '][duracion_segundos]'" x-model="em.duracion_segundos">

                        <div class="grid gap-3 md:grid-cols-3 lg:grid-cols-4">
                            <div>
                                <label class="{{ $labelClass }}">Tipo de pase</label>
                                <select :name="'emisiones[' + index + '][tipo]'" x-model="em.tipo" class="{{ $inputClass }}">
                                    <option value="normal">Normal</option>
                                    <option value="retransmision">Retransmisión / Podcast</option>
                                    <option value="en_vivo">En vivo</option>
                                </select>
                            </div>

                            <div>
                                <label class="{{ $labelClass }}">Día de la semana</label>
                                <select :name="'emisiones[' + index + '][dia_semana]'" x-model="em.dia_semana" class="{{ $inputClass }}">
                                    <option value="LUNES">Lunes</option>
                                    <option value="MARTES">Martes</option>
                                    <option value="MIERCOLES">Miércoles</option>
                                    <option value="JUEVES">Jueves</option>
                                    <option value="VIERNES">Viernes</option>
                                    <option value="SABADO">Sábado</option>
                                    <option value="DOMINGO">Domingo</option>
                                </select>
                            </div>

                            <div>
                                <label class="{{ $labelClass }}">Hora de inicio</label>
                                <input type="time" step="1" :name="'emisiones[' + index + '][hora_inicio]'" x-model="em.hora_inicio" class="{{ $inputClass }}">
                            </div>

                            <div>
                                <label class="{{ $labelClass }}">Duración (min)</label>
                                <input type="number" min="1" max="600" :name="'emisiones[' + index + '][duracion_minutos]'" x-model.number="em.duracion_minutos" class="{{ $inputClass }}">
                            </div>

                            <div>
                                <div class="mb-1.5 flex items-center justify-between">
                                    <label class="text-xs font-medium text-zinc-300">Duración real archivo</label>
                                    <template x-if="em.duracion_segundos">
                                        <label class="flex items-center gap-1 text-[11px] text-zinc-400 cursor-pointer hover:text-red-400">
                                            <input type="checkbox" :name="'emisiones[' + index + '][borrar_duracion_real]'" value="1" class="h-3 w-3 accent-red-600">
                                            Borrar ⏱
                                        </label>
                                    </template>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="flex items-center gap-1 flex-1">
                                        <input type="number" min="0" max="600" placeholder="Min" :name="'emisiones[' + index + '][duracion_real_min]'" x-model="em.duracion_real_min" class="{{ $inputClass }} text-center">
                                        <span class="text-xs text-zinc-500">m</span>
                                    </div>
                                    <div class="flex items-center gap-1 flex-1">
                                        <input type="number" min="0" max="59" placeholder="Seg" :name="'emisiones[' + index + '][duracion_real_sec]'" x-model="em.duracion_real_sec" class="{{ $inputClass }} text-center">
                                        <span class="text-xs text-zinc-500">s</span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="{{ $labelClass }}">Etiqueta en parrilla</label>
                                <input type="text" :name="'emisiones[' + index + '][etiqueta]'" x-model="em.etiqueta" placeholder="Ej. Retransmisión" class="{{ $inputClass }}">
                            </div>

                            <div x-show="em.tipo === 'en_vivo'">
                                <label class="{{ $labelClass }}">Enlace en vivo (URL)</label>
                                <input type="url" :name="'emisiones[' + index + '][enlace]'" x-model="em.enlace" placeholder="https://stream.ejemplo.com/live" class="{{ $inputClass }}">
                            </div>

                            <div>
                                <label class="{{ $labelClass }}">URL Podcast</label>
                                <input type="url" :name="'emisiones[' + index + '][url_podcast]'" x-model="em.url_podcast" placeholder="https://archive.org/..." class="{{ $inputClass }}">
                            </div>
                        </div>

                        <div class="grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
                            <div>
                                <label class="{{ $labelClass }}">Notas internas</label>
                                <input type="text" :name="'emisiones[' + index + '][notas]'" x-model="em.notas" placeholder="Observaciones internas sobre esta emisión..." class="{{ $inputClass }}">
                            </div>

                            <div class="flex items-center justify-between gap-4 pb-1">
                                <label class="inline-flex items-center gap-2 text-xs text-zinc-300 cursor-pointer">
                                    <input type="hidden" :name="'emisiones[' + index + '][activo]'" value="0">
                                    <input type="checkbox" :name="'emisiones[' + index + '][activo]'" value="1" x-model="em.activo" class="h-4 w-4 rounded border-white/20 bg-zinc-900 accent-[#c32720]">
                                    <span>Emisión activa</span>
                                </label>
                                <button type="button" @click="removeEmision(index)" class="inline-flex h-8 items-center rounded-lg border border-red-500/20 bg-red-500/10 px-2.5 text-xs font-medium text-red-400 transition hover:bg-red-500/20">
                                    Quitar emisión
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        {{-- 5. RadioBOSS, Archive.org, Programación editorial y Estadísticas --}}
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-white/10 bg-[#101012] p-6 space-y-4">
                <div class="border-b border-white/10 pb-3">
                    <h2 class="font-display text-lg font-bold uppercase tracking-wider text-zinc-100">RadioBOSS, Archive.org y redes</h2>
                    <p class="mt-0.5 text-xs text-zinc-400">Si los correos se dejan vacíos, se aplicarán los destinatarios globales del sistema.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="{{ $labelClass }}">Ruta FTP (RadioBOSS)</label>
                        <input name="ruta_ftp" value="{{ old('ruta_ftp', $masterProgram->ruta_ftp) }}" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Identificador Archive.org</label>
                        <input name="archive_identifier" value="{{ old('archive_identifier', $masterProgram->archive_identifier) }}" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Correo de notificación</label>
                        <input name="email_notificacion" value="{{ old('email_notificacion', $masterProgram->email_notificacion) }}" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Correo en copia (CC)</label>
                        <input name="email_copia_notificacion" value="{{ old('email_copia_notificacion', $masterProgram->email_copia_notificacion) }}" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Red social 1 (URL)</label>
                        <input name="red_social1_url" value="{{ old('red_social1_url', $masterProgram->red_social1_url) }}" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Red social 2 (URL)</label>
                        <input name="red_social2_url" value="{{ old('red_social2_url', $masterProgram->red_social2_url) }}" class="{{ $inputClass }}">
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-white/10 bg-[#101012] p-6 space-y-4">
                <div class="border-b border-white/10 pb-3">
                    <h2 class="font-display text-lg font-bold uppercase tracking-wider text-zinc-100">Noticias asociadas y estadísticas</h2>
                    <p class="mt-0.5 text-xs text-zinc-400">IDs de artículos vinculados (uno por línea) y contadores históricos.</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="{{ $labelClass }}">Noticias base</label>
                        <textarea name="default_news_ids_text" rows="3" class="{{ $textareaClass }} font-mono text-xs" placeholder="Uno por línea">{{ old('default_news_ids_text', $defaultNewsIdsText) }}</textarea>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Noticias en curso</label>
                        <textarea name="live_news_ids_text" rows="3" class="{{ $textareaClass }} font-mono text-xs" placeholder="Uno por línea">{{ old('live_news_ids_text', $liveNewsIdsText) }}</textarea>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Noticias previas</label>
                        <textarea name="preview_news_ids_text" rows="3" class="{{ $textareaClass }} font-mono text-xs" placeholder="Uno por línea">{{ old('preview_news_ids_text', $previewNewsIdsText) }}</textarea>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 border-t border-white/10 pt-3">
                    <div>
                        <label class="{{ $labelClass }}">Vistas Archive</label>
                        <input type="number" min="0" name="vistas_archive" value="{{ old('vistas_archive', $masterProgram->vistas_archive ?? 0) }}" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Escuchas locales</label>
                        <input type="number" min="0" name="escuchas_locales" value="{{ old('escuchas_locales', $masterProgram->escuchas_locales ?? 0) }}" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Vistas totales</label>
                        <input type="number" min="0" name="vistas_totales" value="{{ old('vistas_totales', $masterProgram->vistas_totales ?? 0) }}" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Actualización stats</label>
                        <input type="datetime-local" name="stats_updated_at" value="{{ old('stats_updated_at', optional($masterProgram->stats_updated_at)->format('Y-m-d\TH:i')) }}" class="{{ $inputClass }}">
                    </div>
                </div>
            </section>
        </div>

        {{-- Botonera final --}}
        <div class="flex flex-wrap items-center justify-end gap-3 rounded-xl border border-white/10 bg-[#101012] px-6 py-4">
            <a href="{{ route('admin.master-programs.index') }}" class="inline-flex h-10 items-center rounded-lg border border-white/10 bg-white/5 px-5 text-xs font-medium text-zinc-300 transition hover:bg-white/10 hover:text-white">
                Cancelar
            </a>
            <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-[#c32720] px-6 text-xs font-semibold text-white shadow-sm transition hover:bg-[#d62d26]">
                {{ $buttonLabel }}
            </button>
        </div>
    </form>
</div>
