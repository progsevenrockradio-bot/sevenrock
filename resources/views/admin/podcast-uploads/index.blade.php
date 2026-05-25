<x-layouts.admin :title="'Podcast Uploads - '.(optional($themeSettings)->site_name ?? config('app.name'))">
    @php
        $selectedProgramId = old('master_program_id');
        $selectedDay = null;

        if ($selectedProgramId) {
            foreach ($programsByDay as $dayKey => $dayPrograms) {
                if ($dayPrograms->contains('id', (int) $selectedProgramId)) {
                    $selectedDay = $dayKey;
                    break;
                }
            }
        }

        $initialDay = $selectedDay ?? $activeDay;
    @endphp

    <div class="grid gap-6 lg:grid-cols-[1.2fr_.8fr] 2xl:grid-cols-[1.3fr_.7fr]">
        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Podcast uploads</h1>
            <p class="mt-3 max-w-2xl text-[#7b7b7b]">
                Sube un MP3, crea el episodio en la base, envíalo a RadioBOSS, sincroniza Archive.org si corresponde y dispara el correo de notificación.
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('admin.master-programs.index') }}" class="lucille-button">Administrar programas maestros</a>
            </div>

            <form
                action="{{ route('admin.podcast-uploads.store') }}"
                method="POST"
                enctype="multipart/form-data"
                class="mt-8 space-y-5"
                x-data="podcastUploadForm({ initialDay: '{{ $initialDay }}' })"
                @submit="submit($event)"
            >
                @csrf

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Programa maestro</label>

                    <div class="flex flex-wrap gap-2 border border-[#242424] bg-[#131313] p-3">
                        @foreach ($dayTabs as $dayKey => $dayLabel)
                            @php $dayPrograms = $programsByDay->get($dayKey, collect()); @endphp
                            <button
                                type="button"
                                @click="activeDay = '{{ $dayKey }}'"
                                class="inline-flex min-w-[8rem] items-center justify-between gap-3 border px-4 py-3 text-sm uppercase tracking-[.18em] transition-colors"
                                :class="activeDay === '{{ $dayKey }}'
                                    ? 'border-[var(--color-lucille-accent)] bg-[rgba(255,255,255,.04)] text-[#f2f2f2]'
                                    : 'border-[#2b2b2b] text-[#7b7b7b] hover:border-[#505050] hover:text-[#dcdcdc]'"
                                aria-label="Ver programas de {{ $dayLabel }}"
                            >
                                <span>{{ $dayLabel }}</span>
                                <span class="text-[11px] tracking-[.2em] text-[#9d9d9d]">{{ $dayPrograms->count() }}</span>
                            </button>
                        @endforeach
                    </div>

                    @foreach ($dayTabs as $dayKey => $dayLabel)
                        @php $dayPrograms = $programsByDay->get($dayKey, collect()); @endphp
                        <div
                            x-cloak
                            x-show="activeDay === '{{ $dayKey }}'"
                            x-transition.opacity.duration.150ms
                            data-day-panel="{{ $dayKey }}"
                            class="mt-4 space-y-2"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $dayLabel }}</div>
                                <div class="text-[11px] uppercase tracking-[.18em] text-[#9d9d9d]">{{ $dayPrograms->count() }} programa{{ $dayPrograms->count() === 1 ? '' : 's' }}</div>
                            </div>

                            <select
                                name="master_program_id"
                                class="lucille-product-field lucille-select-field w-full"
                                :disabled="activeDay !== '{{ $dayKey }}'"
                            >
                                <option value="">-- seleccionar --</option>
                                @forelse ($dayPrograms as $masterProgram)
                                    <option value="{{ $masterProgram->id }}" @selected((string) old('master_program_id') === (string) $masterProgram->id)>
                                        {{ $masterProgram->name }}
                                    </option>
                                @empty
                                    <option value="" disabled>No hay programas para este día</option>
                                @endforelse
                            </select>
                        </div>
                    @endforeach

                    @error('master_program_id')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Capítulo / episodio</label>
                        <input
                            type="number"
                            min="1"
                            name="numero_episodio"
                            value="{{ old('numero_episodio') }}"
                            class="lucille-product-field w-full"
                            placeholder="Automático"
                        >
                        <p class="mt-2 text-xs uppercase tracking-[.16em] text-[#7b7b7b]">
                            Déjalo vacío para usar el siguiente correlativo automático.
                        </p>
                        @error('numero_episodio')
                            <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Título del episodio</label>
                        <input name="live_title" value="{{ old('live_title') }}" class="lucille-product-field w-full" placeholder="Episodio especial">
                        <p class="mt-2 text-xs uppercase tracking-[.16em] text-[#7b7b7b]">
                            Obligatorio. Este título se usa en la ficha, el archivo y la notificación.
                        </p>
                        @error('live_title')
                            <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Fecha de emisión</label>
                        <input type="date" name="fecha_emision" value="{{ old('fecha_emision', now()->toDateString()) }}" class="lucille-product-field w-full">
                        @error('fecha_emision')
                            <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Invitado</label>
                    <input name="biografia_invitado" value="{{ old('biografia_invitado') }}" class="lucille-product-field w-full" placeholder="Opcional">
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Reseña / descripción</label>
                    <textarea name="resena" rows="5" class="lucille-product-field w-full">{{ old('resena') }}</textarea>
                </div>

                <div class="space-y-5 border border-[#242424] bg-[#131313] p-5">
                    <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Imagen del episodio</div>
                    <p class="text-sm text-[#7b7b7b]">
                        Puedes usar una URL pública o subir el archivo directamente. Si completas ambos, el archivo local tiene prioridad y se guarda en la base para reutilizarlo después en podcasts y Archive.org.
                    </p>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">URL de imagen</label>
                            <input name="imagen_episodio_url" value="{{ old('imagen_episodio_url') }}" class="lucille-product-field w-full" placeholder="https://...">
                            @error('imagen_episodio_url')
                                <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Archivo de imagen</label>
                            <input type="file" name="imagen_episodio_file" accept="image/*" class="lucille-product-field w-full">
                            @error('imagen_episodio_file')
                                <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Archivo MP3</label>
                    <input type="file" name="archivo_mp3" accept="audio/mpeg,audio/mp3" class="lucille-product-field w-full">
                    @error('archivo_mp3')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                    @enderror

                    <div class="mt-3">
                        <div class="mb-2 inline-flex items-center rounded border px-2.5 py-1 text-[10px] uppercase tracking-[.18em]" :class="phaseClass()">
                            <span x-text="phaseLabel"></span>
                        </div>
                        <div class="mb-2 text-[11px] leading-5 text-[#8b8b8b]" x-text="phaseDetailLabel"></div>
                        <div class="h-1.5 overflow-hidden border border-[#242424] bg-[#101010]">
                            <div
                                class="h-full bg-[color:var(--color-lucille-accent)] transition-[width] duration-150"
                                :style="`width:${progress}%`"
                            ></div>
                        </div>
                        <div class="mt-1 flex items-center justify-between text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">
                            <span x-text="uploading ? 'Subiendo' : (statusMessage || 'Listo para subir')"></span>
                            <span x-text="progressLabel"></span>
                        </div>
                        <div class="mt-1 flex items-center justify-between text-[10px] uppercase tracking-[.18em] text-[#5f5f5f]">
                            <span x-text="fileSizeLabel ? `Peso: ${fileSizeLabel}` : ''"></span>
                            <span x-text="uploadEtaLabel"></span>
                        </div>
                    </div>
                </div>

                <label class="flex items-center gap-3 text-sm text-[#7b7b7b]">
                    <input type="checkbox" name="download_processed_mp3" value="1" @checked(old('download_processed_mp3')) class="h-4 w-4">
                    Conservar una copia local descargable del MP3 procesado
                </label>
                <p class="mt-[-.5rem] text-xs uppercase tracking-[.16em] text-[#7b7b7b]">
                    La descarga quedará disponible cuando termine el procesamiento.
                </p>

                <label class="flex items-center gap-3 text-sm text-[#7b7b7b]">
                    <input type="checkbox" name="sync_archive_org" value="1" @checked(old('sync_archive_org', true)) class="h-4 w-4">
                    Sincronizar también con Archive.org
                </label>

                <div x-show="errorMessages.length" x-cloak class="border border-[#4b2f2f] bg-[rgba(49,27,27,.42)] p-4 text-sm text-[#ffd7d7]">
                    <div class="mb-2 text-[11px] uppercase tracking-[.18em] text-[#ff9e9e]">
                        Faltan campos obligatorios
                    </div>
                    <template x-for="message in errorMessages" :key="message">
                        <div class="leading-6" x-text="message"></div>
                    </template>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="lucille-button-solid" :disabled="uploading" x-text="uploading ? 'Procesando...' : 'Crear y procesar'"></button>
                    <a href="{{ route('admin.dashboard') }}" class="lucille-button">Volver al dashboard</a>
                </div>
            </form>
        </section>

        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Últimos episodios</h2>
            <div class="mt-6 space-y-4">
                @forelse ($recentUploads as $upload)
                    <article class="border border-[#242424] bg-[#151515] p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-display text-sm uppercase tracking-[.12em] text-white">{{ $upload->live_title ?: $upload->titulo_programa }}</div>
                                <div class="mt-1 text-sm text-[#9d9d9d]">{{ $upload->masterProgram?->name ?? 'Sin programa maestro' }}</div>
                                <div class="mt-1 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">
                                    RadioBOSS: {{ $upload->enviado_radioboss ? 'enviado' : 'pendiente' }}
                                    · Archive.org: {{ $upload->archive_org_status ?: 'sin estado' }}
                                </div>
                                @if ($upload->status_message)
                                    <div class="mt-2 max-w-xl text-[11px] leading-5 text-[#c7c7c7]">
                                        Estado: {{ $upload->status_message }}
                                    </div>
                                @endif
                                @if ($upload->archive_org_last_error)
                                    <div class="mt-2 max-w-xl text-[11px] leading-5 text-[#ff9e9e]">
                                        Último error Archive.org: {{ \Illuminate\Support\Str::limit((string) $upload->archive_org_last_error, 180) }}
                                    </div>
                                @endif
                            </div>

                            <div class="flex flex-col items-end gap-2">
                                <span class="rounded border border-[#2b2b2b] px-3 py-1 text-[11px] uppercase tracking-[.18em] text-[#9d9d9d]">
                                    Ep. {{ $upload->numero_episodio }}
                                </span>
                                <span class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">
                                    {{ optional($upload->fecha_emision)->format('d/m/Y') }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <form action="{{ route('admin.podcast-uploads.retry', $upload) }}" method="POST">
                                @csrf
                                <button type="submit" class="lucille-button">Reprocesar</button>
                            </form>
                            <a href="{{ route('admin.podcast-uploads.download', $upload) }}" class="lucille-button">Descargar MP3</a>
                            <form
                                action="{{ route('admin.podcast-uploads.destroy', $upload->id) }}"
                                method="POST"
                                data-confirm="¿Eliminar este episodio?"
                                data-confirm-title="Eliminar episodio"
                                data-confirm-action="Eliminar"
                                data-confirm-tone="danger"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="lucille-button">Eliminar</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <p class="text-sm text-[#7b7b7b]">Todavía no hay episodios en esta sección.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.admin>
