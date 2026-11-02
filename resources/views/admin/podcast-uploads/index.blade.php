<x-layouts.admin :title="'Emisiones y podcasts - '.config('app.name')">
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

        $tab1Fields = ['master_program_id', 'numero_episodio', 'live_title', 'fecha_emision', 'biografia_invitado', 'resena'];
        $tab2Fields = ['archivo_mp3', 'imagen_episodio_url', 'imagen_episodio_file'];
        $tab3Fields = ['sync_archive_org', 'download_processed_mp3'];

        $tab1HasErrors = $errors->hasAny($tab1Fields);
        $tab2HasErrors = $errors->hasAny($tab2Fields);
        $tab3HasErrors = $errors->hasAny($tab3Fields);
        $tab1ErrorCount = collect($tab1Fields)->filter(fn (string $field) => $errors->has($field))->count();
        $tab2ErrorCount = collect($tab2Fields)->filter(fn (string $field) => $errors->has($field))->count();
        $tab3ErrorCount = collect($tab3Fields)->filter(fn (string $field) => $errors->has($field))->count();
        $totalErrorCount = $tab1ErrorCount + $tab2ErrorCount + $tab3ErrorCount;

        $initialTab = $tab1HasErrors ? 'editorial' : ($tab2HasErrors ? 'multimedia' : ($tab3HasErrors ? 'distribution' : 'editorial'));
    @endphp

    {{-- Cabecera estilizada, compacta y moderna --}}
    <header class="rounded-2xl border border-white/5 bg-[#101012] p-5 sm:p-6 shadow-xl" x-data="{ helpOpen: false }">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2 text-xs font-medium text-gray-400">
                    <span>Panel de control</span>
                    <span class="text-gray-600">/</span>
                    <span class="text-[#c32720]">Emisiones</span>
                </div>
                <h1 class="mt-1 font-display text-2xl sm:text-3xl font-bold tracking-wide uppercase text-white">
                    Emisiones y Podcasts
                </h1>
                <p class="mt-1 max-w-2xl text-xs sm:text-sm text-gray-400 font-sans">
                    Sube episodios, distribuye a RadioBOSS y sincroniza con Archive.org en un flujo unificado.
                </p>
            </div>

            <div class="flex items-center gap-2.5 shrink-0">
                <a
                    href="{{ route('admin.master-programs.index') }}"
                    class="h-9 px-3.5 rounded-lg border border-white/10 bg-white/5 text-xs font-medium text-gray-200 hover:bg-white/10 hover:text-white transition-colors inline-flex items-center gap-1.5"
                >
                    <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                    </svg>
                    <span>Programas</span>
                </a>
                <button
                    type="button"
                    @click="helpOpen = true"
                    class="h-9 px-3.5 rounded-lg border border-white/10 bg-white/5 text-xs font-medium text-gray-200 hover:bg-white/10 hover:text-white transition-colors inline-flex items-center gap-1.5"
                    aria-haspopup="dialog"
                    :aria-expanded="helpOpen"
                >
                    <svg class="h-3.5 w-3.5 text-[#c32720]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Ayuda</span>
                </button>
            </div>
        </div>

        {{-- Modal de Ayuda Contextual --}}
        <div
            x-show="helpOpen"
            x-cloak
            class="fixed inset-0 z-[150] flex items-center justify-center p-4 bg-black/85 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            aria-labelledby="help-modal-title"
            @keydown.escape.window="helpOpen = false"
        >
            <div
                class="relative w-full max-w-xl rounded-2xl border border-white/10 bg-[#121215] p-6 shadow-2xl"
                @click.outside="helpOpen = false"
            >
                <div class="flex items-start justify-between border-b border-white/10 pb-4">
                    <div>
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Guía rápida</span>
                        <h2 id="help-modal-title" class="mt-1 font-display text-xl font-bold uppercase tracking-wide text-white">
                            Flujo de Trabajo de Emisiones
                        </h2>
                    </div>
                    <button
                        type="button"
                        @click="helpOpen = false"
                        class="h-8 w-8 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 flex items-center justify-center transition-colors"
                        aria-label="Cerrar ayuda"
                    >
                        ✕
                    </button>
                </div>

                <div class="mt-5 space-y-3 text-xs sm:text-sm text-gray-300 max-h-[60vh] overflow-y-auto pr-1">
                    <div class="rounded-xl border border-white/5 bg-white/[0.03] p-4">
                        <h3 class="font-semibold text-white flex items-center gap-2">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-white/10 text-xs font-bold text-gray-300">1</span>
                            Crear episodio (sin procesar)
                        </h3>
                        <p class="mt-1.5 text-xs text-gray-400 leading-relaxed font-sans">
                            Registra la ficha y el archivo en el sistema sin disparar los trabajos de fondo de RadioBOSS ni Archive.org. Ideal para guardar la emisión con antelación o preparar contenidos.
                        </p>
                    </div>

                    <div class="rounded-xl border border-white/5 bg-white/[0.03] p-4">
                        <h3 class="font-semibold text-white flex items-center gap-2">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-[#c32720] text-xs font-bold text-white">2</span>
                            Crear y procesar
                        </h3>
                        <p class="mt-1.5 text-xs text-gray-400 leading-relaxed font-sans">
                            Sube el audio a Cloudflare R2, normaliza etiquetas, entrega por FTP a RadioBOSS, archiva en Archive.org y emite la notificación automática por correo.
                        </p>
                    </div>

                    <div class="rounded-xl border border-white/5 bg-white/[0.03] p-4">
                        <h3 class="font-semibold text-white flex items-center gap-2">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-white/10 text-xs font-bold text-gray-300">3</span>
                            Monitoreo de estados
                        </h3>
                        <p class="mt-1.5 text-xs text-gray-400 leading-relaxed font-sans">
                            La lista inferior indica en tiempo real el progreso de cada servicio: <strong class="text-amber-300">Pendiente</strong>, <strong class="text-amber-300">Procesando</strong>, <strong class="text-emerald-400">Verificado</strong> o <strong class="text-rose-400">Error</strong>.
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end border-t border-white/10 pt-4">
                    <button
                        type="button"
                        @click="helpOpen = false"
                        class="h-9 px-4 rounded-lg bg-[#c32720] hover:bg-[#d63028] text-white text-xs font-semibold shadow-md shadow-red-950/40 transition-colors"
                    >
                        Entendido
                    </button>
                </div>
            </div>
        </div>
    </header>

    {{-- Formulario Principal --}}
    <form
        action="{{ route('admin.podcast-uploads.store') }}"
        method="POST"
        enctype="multipart/form-data"
        class="mt-6 space-y-6"
        x-data="podcastUploadForm({ initialDay: @js($initialDay), initialTab: @js($initialTab), initialDate: @js(old('fecha_emision', now()->toDateString())) })"
        @submit="submit($event)"
    >
        @csrf

        {{-- Botón submit por defecto para la tecla Enter --}}
        <button type="submit" name="pipeline_action" value="process" class="hidden" aria-hidden="true" tabindex="-1"></button>

        @if ($totalErrorCount > 0)
            <div class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-xs sm:text-sm text-rose-200 flex items-center gap-2.5">
                <svg class="h-4 w-4 text-rose-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>Hay <strong>{{ $totalErrorCount }} campo{{ $totalErrorCount === 1 ? '' : 's' }} con observaciones</strong>. Se activó automáticamente el paso correspondiente.</span>
            </div>
        @endif

        {{-- Estado y Progreso del Formulario --}}
        <div
            x-show="errorMessages.length > 0 || statusMessage"
            x-cloak
            class="rounded-xl border border-white/5 bg-[#141418] p-4"
        >
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-2 text-xs text-gray-300">
                    <span class="inline-block h-2 w-2 rounded-full bg-[#c32720] animate-pulse"></span>
                    <span x-text="statusMessage || 'Listo para registrar emisión.'"></span>
                </div>
                <span class="text-[11px] font-mono uppercase tracking-wider text-gray-500" x-text="phaseLabel"></span>
            </div>

            <template x-if="errorMessages.length > 0">
                <ul class="mt-3 space-y-1.5 text-xs text-rose-300">
                    <template x-for="(message, index) in errorMessages" :key="`${index}-${message}`">
                        <li class="rounded-lg border border-rose-900/30 bg-rose-950/20 px-3 py-2" x-text="message"></li>
                    </template>
                </ul>
            </template>
        </div>

        {{-- Navegación Ligera por Pasos (Stepper Moderno) --}}
        <nav class="rounded-xl border border-white/5 bg-[#101012] p-1.5 shadow-md" aria-label="Pasos del formulario">
            <div class="flex items-center gap-1 sm:gap-2">
                {{-- Paso 1: Datos --}}
                <button
                    type="button"
                    class="flex-1 flex items-center justify-center gap-2 py-2.5 px-3 rounded-lg text-xs font-medium transition-all"
                    :class="activeTab === 'editorial'
                        ? 'bg-[#c32720] text-white shadow-md shadow-red-950/40'
                        : 'text-gray-400 hover:text-white hover:bg-white/5'"
                    @click="activeTab = 'editorial'"
                >
                    <span
                        class="flex items-center justify-center h-5 w-5 rounded-full text-[11px] font-bold"
                        :class="activeTab === 'editorial' ? 'bg-white/20 text-white' : 'bg-white/10 text-gray-400'"
                    >1</span>
                    <span class="font-sans">Datos</span>
                    @if ($tab1HasErrors)
                        <span class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-white text-rose-600 px-1 text-[10px] font-bold">{{ $tab1ErrorCount }}</span>
                    @endif
                </button>

                {{-- Paso 2: Audio y Portada --}}
                <button
                    type="button"
                    class="flex-1 flex items-center justify-center gap-2 py-2.5 px-3 rounded-lg text-xs font-medium transition-all"
                    :class="activeTab === 'multimedia'
                        ? 'bg-[#c32720] text-white shadow-md shadow-red-950/40'
                        : 'text-gray-400 hover:text-white hover:bg-white/5'"
                    @click="activeTab = 'multimedia'"
                >
                    <span
                        class="flex items-center justify-center h-5 w-5 rounded-full text-[11px] font-bold"
                        :class="activeTab === 'multimedia' ? 'bg-white/20 text-white' : 'bg-white/10 text-gray-400'"
                    >2</span>
                    <span class="font-sans">Audio y portada</span>
                    @if ($tab2HasErrors)
                        <span class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-white text-rose-600 px-1 text-[10px] font-bold">{{ $tab2ErrorCount }}</span>
                    @endif
                </button>

                {{-- Paso 3: Entrega --}}
                <button
                    type="button"
                    class="flex-1 flex items-center justify-center gap-2 py-2.5 px-3 rounded-lg text-xs font-medium transition-all"
                    :class="activeTab === 'distribution'
                        ? 'bg-[#c32720] text-white shadow-md shadow-red-950/40'
                        : 'text-gray-400 hover:text-white hover:bg-white/5'"
                    @click="activeTab = 'distribution'"
                >
                    <span
                        class="flex items-center justify-center h-5 w-5 rounded-full text-[11px] font-bold"
                        :class="activeTab === 'distribution' ? 'bg-white/20 text-white' : 'bg-white/10 text-gray-400'"
                    >3</span>
                    <span class="font-sans">Entrega</span>
                    @if ($tab3HasErrors)
                        <span class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-white text-rose-600 px-1 text-[10px] font-bold">{{ $tab3ErrorCount }}</span>
                    @endif
                </button>
            </div>
        </nav>

        {{-- Paneles del Formulario (Tarjetas Limpias sin Cajas Anidadas Excesivas) --}}
        <div class="rounded-2xl border border-white/5 bg-[#101012] p-5 sm:p-7 shadow-xl">
            <section x-cloak x-show="activeTab === 'editorial'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                @include('admin.podcast-uploads.partials.form.editorial')
            </section>

            <section x-cloak x-show="activeTab === 'multimedia'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                @include('admin.podcast-uploads.partials.form.media')
            </section>

            <section x-cloak x-show="activeTab === 'distribution'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                @include('admin.podcast-uploads.partials.form.distribution')
            </section>

            {{-- Barra de Acciones Unificada: Una Principal por Zona, Secundarias de Menor Peso --}}
            <div class="mt-8 pt-5 border-t border-white/10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        x-show="activeTab !== 'editorial'"
                        x-cloak
                        @click="activeTab = activeTab === 'distribution' ? 'multimedia' : 'editorial'"
                        class="h-10 px-4 rounded-lg border border-white/10 bg-white/5 text-gray-300 hover:text-white hover:bg-white/10 text-xs font-medium transition-colors inline-flex items-center gap-1.5"
                    >
                        <span>← Anterior</span>
                    </button>
                    <button
                        type="button"
                        x-show="activeTab !== 'distribution'"
                        @click="activeTab = activeTab === 'editorial' ? 'multimedia' : 'distribution'"
                        class="h-10 px-4 rounded-lg border border-white/10 bg-white/5 text-gray-300 hover:text-white hover:bg-white/10 text-xs font-medium transition-colors inline-flex items-center gap-1.5"
                    >
                        <span>Siguiente →</span>
                    </button>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <button
                        type="submit"
                        name="pipeline_action"
                        value="save"
                        class="h-10 px-4 rounded-lg border border-white/10 bg-white/5 text-gray-300 hover:text-white hover:bg-white/10 text-xs font-medium transition-colors"
                        :disabled="uploading"
                    >
                        Crear episodio
                    </button>
                    <button
                        type="submit"
                        name="pipeline_action"
                        value="process"
                        class="h-10 px-5 rounded-lg bg-[#c32720] hover:bg-[#d63028] text-white text-xs font-semibold shadow-md shadow-red-950/40 transition-colors inline-flex items-center gap-2"
                        :disabled="uploading"
                    >
                        <span x-text="uploading ? 'Procesando...' : 'Crear y procesar'"></span>
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </form>

    {{-- Lista de Emisiones Recientes con Auto-refresh --}}
    <section
        class="mt-8 rounded-2xl border border-white/5 bg-[#101012] p-5 sm:p-7 shadow-xl"
        x-data="podcastUploadsDashboard({ recentUrl: '{{ route('admin.podcast-uploads.recent') }}', refreshInterval: 15000 })"
        x-init="init()"
        @podcast-upload-complete.window="handleUploadSuccess()"
    >
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between pb-4 border-b border-white/5">
            <div>
                <h2 class="font-display text-xl sm:text-2xl font-bold uppercase tracking-wide text-white">
                    Emisiones Recientes
                </h2>
                <p class="mt-1 text-xs text-gray-400 font-sans">
                    Supervisa el estado del pipeline, almacenamiento R2, entrega a RadioBOSS y Archive.org.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <a
                    href="{{ route('admin.podcast-uploads.published') }}"
                    class="h-8 px-3 rounded-lg border border-white/10 bg-white/5 text-xs font-medium text-gray-300 hover:text-white hover:bg-white/10 transition-colors inline-flex items-center gap-1.5"
                >
                    Ver publicados
                </a>
                <a
                    href="{{ route('admin.podcast-uploads.published.print') }}"
                    target="_blank"
                    class="h-8 px-3 rounded-lg border border-white/10 bg-white/5 text-xs font-medium text-gray-300 hover:text-white hover:bg-white/10 transition-colors inline-flex items-center gap-1.5"
                >
                    Imprimir
                </a>
                <div class="flex items-center gap-1.5 text-xs text-gray-400 font-sans ml-1">
                    <svg x-show="isRefreshing" x-cloak class="h-3 w-3 animate-spin text-[#c32720]" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                        <path class="opacity-90" fill="currentColor" d="M12 2a10 10 0 0 1 10 10h-3a7 7 0 0 0-7-7V2Z"></path>
                    </svg>
                    <span x-text="isRefreshing ? 'Actualizando...' : 'Auto-refresh activo'"></span>
                </div>
            </div>
        </div>

        {{-- Filtro de fecha y estado de actualización --}}
        <div class="mt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
            <form action="{{ route('admin.podcast-uploads.index') }}" method="GET" class="flex flex-wrap items-center gap-2">
                <span class="text-gray-400 font-medium">Filtrar fecha:</span>
                <input
                    type="date"
                    name="date"
                    id="filter_date"
                    value="{{ request('date') }}"
                    class="h-8 rounded-lg border border-white/10 bg-black/40 px-2.5 text-xs text-gray-200 focus:border-[#c32720] focus:outline-none"
                >
                <button
                    type="submit"
                    class="h-8 px-3 rounded-lg border border-white/10 bg-white/5 text-xs font-medium text-gray-200 hover:bg-white/10 hover:text-white transition-colors"
                >
                    Filtrar
                </button>
                @if(request('date'))
                    <a href="{{ route('admin.podcast-uploads.index') }}" class="text-xs text-gray-400 hover:text-[#c32720] underline ml-1">Limpiar</a>
                @endif
            </form>

            <div class="text-xs text-gray-500 font-sans">
                <span x-text="lastUpdatedLabel"></span>
            </div>
        </div>

        <div class="mt-4" x-ref="recentUploads">
            @include('admin.podcast-uploads.partials.recent-uploads', ['recentUploads' => $recentUploads])
        </div>
    </section>
</x-layouts.admin>
