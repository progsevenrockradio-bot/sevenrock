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
            <p class="mt-2 max-w-2xl text-[#7b7b7b]">
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
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Nombre</label>
                    <input name="nombre" value="{{ old('nombre', $masterProgram->nombre) }}" class="lucille-product-field w-full" required>
                    @error('nombre')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Conductor</label>
                    <input name="conductor" value="{{ old('conductor', $masterProgram->conductor) }}" class="lucille-product-field w-full" required>
                    @error('conductor')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Día de transmisión</label>
                    <select name="dia_transmision" class="lucille-product-field lucille-select-field w-full" required>
                        <option value="">-- seleccionar --</option>
                        @foreach ($days as $value => $label)
                            <option value="{{ $value }}" @selected(old('dia_transmision', $masterProgram->dia_transmision) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('dia_transmision')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Hora de transmisión</label>
                    <input type="time" step="1" name="hora_transmision" value="{{ old('hora_transmision', $masterProgram->hora_transmision ? substr((string) $masterProgram->hora_transmision, 0, 8) : '') }}" class="lucille-product-field w-full">
                    @error('hora_transmision')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Zona horaria</label>
                    <input name="timezone" value="{{ old('timezone', $masterProgram->timezone) }}" class="lucille-product-field w-full" required>
                    @error('timezone')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Duración estimada (min)</label>
                    <input type="number" min="1" max="1440" name="duracion_minutos" value="{{ old('duracion_minutos', $masterProgram->duracion_minutos ?? 120) }}" class="lucille-product-field w-full" required>
                    @error('duracion_minutos')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Género</label>
                    <input name="genero" value="{{ old('genero', $masterProgram->genero) }}" class="lucille-product-field w-full" required>
                    @error('genero')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Código de programa</label>
                    <input name="program_code" value="{{ old('program_code', $masterProgram->program_code) }}" class="lucille-product-field w-full" maxlength="12" placeholder="ROCKNOCHE24">
                    @error('program_code')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Prefijo base</label>
                    <input name="code_prefix" value="{{ old('code_prefix', $masterProgram->code_prefix) }}" class="lucille-product-field w-full" maxlength="12" placeholder="ROCKNOCHE">
                    @error('code_prefix')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            <label class="flex items-center gap-3 text-sm text-[#7b7b7b]">
                <input type="checkbox" name="activo" value="1" @checked(old('activo', $masterProgram->activo ?? true)) class="h-4 w-4">
                Programa activo
            </label>
        </section>

        <section class="space-y-5">
            <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Presentación</h2>
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Carátula URL</label>
                    <input name="caratula_url" value="{{ old('caratula_url', $masterProgram->caratula_url) }}" class="lucille-product-field w-full">
                    @error('caratula_url')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Imagen en curso</label>
                    <input name="live_image_url" value="{{ old('live_image_url', $masterProgram->live_image_url) }}" class="lucille-product-field w-full">
                    @error('live_image_url')<p class="mt-2 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Título en curso</label>
                    <input name="live_title" value="{{ old('live_title', $masterProgram->live_title) }}" class="lucille-product-field w-full">
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Inicio en curso</label>
                    <input type="datetime-local" name="live_starts_at" value="{{ old('live_starts_at', optional($masterProgram->live_starts_at)->format('Y-m-d\TH:i')) }}" class="lucille-product-field w-full">
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Fin en curso</label>
                    <input type="datetime-local" name="live_ends_at" value="{{ old('live_ends_at', optional($masterProgram->live_ends_at)->format('Y-m-d\TH:i')) }}" class="lucille-product-field w-full">
                </div>

                <div class="md:col-span-2">
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Descripción</label>
                    <textarea name="descripcion" rows="5" class="lucille-product-field w-full">{{ old('descripcion', $masterProgram->descripcion) }}</textarea>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Descripción en curso</label>
                    <textarea name="live_description" rows="5" class="lucille-product-field w-full">{{ old('live_description', $masterProgram->live_description) }}</textarea>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Comentario predeterminado</label>
                    <textarea name="comentario_predeterminado" rows="4" class="lucille-product-field w-full">{{ old('comentario_predeterminado', $masterProgram->comentario_predeterminado) }}</textarea>
                </div>
            </div>
        </section>

        <section class="space-y-5">
            <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Programación editorial</h2>
            <div class="grid gap-5 md:grid-cols-3">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Noticias base</label>
                    <textarea name="default_news_ids_text" rows="6" class="lucille-product-field w-full font-mono text-[12px] leading-6" placeholder="Uno por línea">{{ old('default_news_ids_text', $defaultNewsIdsText) }}</textarea>
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Noticias en curso</label>
                    <textarea name="live_news_ids_text" rows="6" class="lucille-product-field w-full font-mono text-[12px] leading-6" placeholder="Uno por línea">{{ old('live_news_ids_text', $liveNewsIdsText) }}</textarea>
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Noticias previas</label>
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
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Ruta FTP</label>
                    <input name="ruta_ftp" value="{{ old('ruta_ftp', $masterProgram->ruta_ftp) }}" class="lucille-product-field w-full">
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Archive.org Identifier</label>
                    <input name="archive_identifier" value="{{ old('archive_identifier', $masterProgram->archive_identifier) }}" class="lucille-product-field w-full">
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Correo de notificación</label>
                    <input name="email_notificacion" value="{{ old('email_notificacion', $masterProgram->email_notificacion) }}" class="lucille-product-field w-full">
                    <p class="mt-2 text-xs text-[#7b7b7b]">Destino principal del programa maestro. Si se deja vacío, usa el valor global del panel.</p>
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Correo copia</label>
                    <input name="email_copia_notificacion" value="{{ old('email_copia_notificacion', $masterProgram->email_copia_notificacion) }}" class="lucille-product-field w-full">
                    <p class="mt-2 text-xs text-[#7b7b7b]">Copia editable por programa maestro. Si se deja vacío, usa la copia global del panel.</p>
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Red social 1</label>
                    <input name="red_social1_url" value="{{ old('red_social1_url', $masterProgram->red_social1_url) }}" class="lucille-product-field w-full">
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Red social 2</label>
                    <input name="red_social2_url" value="{{ old('red_social2_url', $masterProgram->red_social2_url) }}" class="lucille-product-field w-full">
                </div>
            </div>
        </section>

        <section class="space-y-5">
            <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Estadísticas</h2>
            <div class="grid gap-5 md:grid-cols-4">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Archive views</label>
                    <input type="number" min="0" name="vistas_archive" value="{{ old('vistas_archive', $masterProgram->vistas_archive ?? 0) }}" class="lucille-product-field w-full">
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Escuchas locales</label>
                    <input type="number" min="0" name="escuchas_locales" value="{{ old('escuchas_locales', $masterProgram->escuchas_locales ?? 0) }}" class="lucille-product-field w-full">
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Vistas totales</label>
                    <input type="number" min="0" name="vistas_totales" value="{{ old('vistas_totales', $masterProgram->vistas_totales ?? 0) }}" class="lucille-product-field w-full">
                </div>
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Stats updated</label>
                    <input type="datetime-local" name="stats_updated_at" value="{{ old('stats_updated_at', optional($masterProgram->stats_updated_at)->format('Y-m-d\TH:i')) }}" class="lucille-product-field w-full">
                </div>
            </div>
        </section>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="lucille-button-solid">{{ $buttonLabel }}</button>
            <a href="{{ route('admin.master-programs.index') }}" class="lucille-button">Cancelar</a>
        </div>
    </form>
</section>
