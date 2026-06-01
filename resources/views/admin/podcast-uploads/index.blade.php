<x-layouts.admin :title="'Podcast Uploads - '.config('app.name')">
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

    <section class="border border-[#2b2b2b] bg-[linear-gradient(180deg,rgba(16,16,18,.96),rgba(12,12,13,.92))] p-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-[10px] uppercase tracking-[.28em] text-[#8b8b8b]">Podcast pipeline</p>
                <h1 class="mt-3 font-display text-4xl uppercase tracking-[.12em] text-[#f0f0f0]">Podcast uploads</h1>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-[#b4b4b4]">
                    Sube un MP3, crea el episodio en la base, envíalo a RadioBOSS, sincroniza Archive.org si corresponde y dispara el correo de notificación.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.podcast-uploads.manual') }}" class="lucille-button">Ver manual interno</a>
                <a href="{{ route('admin.master-programs.index') }}" class="lucille-button">Administrar programas maestros</a>
                <a href="{{ route('admin.dashboard') }}" class="lucille-button">Volver al dashboard</a>
            </div>
        </div>
    </section>

    <form
        action="{{ route('admin.podcast-uploads.store') }}"
        method="POST"
        enctype="multipart/form-data"
        class="mt-8 space-y-6"
        x-data="podcastUploadForm({ initialDay: @js($initialDay), initialTab: @js($initialTab) })"
        @submit="submit($event)"
    >
        @csrf

        @if ($totalErrorCount > 0)
            <div class="border border-[#7a2b2b] bg-[rgba(195,39,32,.12)] px-4 py-3 text-sm text-[#ffd3d3]">
                Hay {{ $totalErrorCount }} campo{{ $totalErrorCount === 1 ? '' : 's' }} con errores. Se abrió automáticamente la pestaña correspondiente.
            </div>
        @endif

        <div
            x-show="errorMessages.length > 0 || statusMessage"
            x-cloak
            class="border border-[#2b2b2b] bg-[rgba(0,0,0,.18)] p-4"
        >
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-[10px] uppercase tracking-[.28em] text-[#8b8b8b]">Estado del formulario</p>
                    <p class="mt-2 text-sm text-[#e6e6e6]" x-text="statusMessage || 'Listo para crear el episodio.'"></p>
                </div>
                <div class="text-right text-[10px] uppercase tracking-[.18em] text-[#7b7b7b]" x-text="phaseLabel"></div>
            </div>

            <template x-if="errorMessages.length > 0">
                <ul class="mt-3 space-y-2 text-sm text-[#ffd3d3]">
                    <template x-for="(message, index) in errorMessages" :key="`${index}-${message}`">
                        <li class="border border-[#7a2b2b] bg-[rgba(195,39,32,.12)] px-3 py-2" x-text="message"></li>
                    </template>
                </ul>
            </template>
        </div>

        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-4">
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="lucille-button flex flex-col items-start gap-1 py-3 text-left leading-none" :class="activeTab === 'editorial' ? 'lucille-button-solid' : ''" @click="activeTab = 'editorial'">
                    <span class="flex items-center gap-2">
                        <span>Datos editoriales</span>
                        @if ($tab1HasErrors)
                            <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full border border-[#ff9e9e] bg-[rgba(195,39,32,.28)] px-1 text-[10px] font-bold text-white">{{ $tab1ErrorCount }}</span>
                        @endif
                    </span>
                    <span class="text-[10px] uppercase tracking-[.18em] text-[#8b8b8b]">Programa, fecha y título</span>
                </button>
                <button type="button" class="lucille-button flex flex-col items-start gap-1 py-3 text-left leading-none" :class="activeTab === 'multimedia' ? 'lucille-button-solid' : ''" @click="activeTab = 'multimedia'">
                    <span class="flex items-center gap-2">
                        <span>Multimedia pesada</span>
                        @if ($tab2HasErrors)
                            <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full border border-[#ff9e9e] bg-[rgba(195,39,32,.28)] px-1 text-[10px] font-bold text-white">{{ $tab2ErrorCount }}</span>
                        @endif
                    </span>
                    <span class="text-[10px] uppercase tracking-[.18em] text-[#8b8b8b]">MP3 y carátula</span>
                </button>
                <button type="button" class="lucille-button flex flex-col items-start gap-1 py-3 text-left leading-none" :class="activeTab === 'distribution' ? 'lucille-button-solid' : ''" @click="activeTab = 'distribution'">
                    <span class="flex items-center gap-2">
                        <span>Distribución técnica</span>
                        @if ($tab3HasErrors)
                            <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full border border-[#ff9e9e] bg-[rgba(195,39,32,.28)] px-1 text-[10px] font-bold text-white">{{ $tab3ErrorCount }}</span>
                        @endif
                    </span>
                    <span class="text-[10px] uppercase tracking-[.18em] text-[#8b8b8b]">Copia y sincronización</span>
                </button>

                <div class="ml-auto flex flex-wrap gap-3">
                    <button type="submit" name="pipeline_action" value="save" class="lucille-button" :disabled="uploading">
                        Crear episodio
                    </button>
                    <button type="submit" name="pipeline_action" value="process" class="lucille-button-solid" :disabled="uploading" x-text="uploading ? 'Procesando...' : 'Crear y procesar'"></button>
                </div>
            </div>
        </section>

        <section x-cloak x-show="activeTab === 'editorial'" x-transition class="space-y-6">
            @include('admin.podcast-uploads.partials.form.editorial')
        </section>

        <section x-cloak x-show="activeTab === 'multimedia'" x-transition class="space-y-6">
            @include('admin.podcast-uploads.partials.form.media')
        </section>

        <section x-cloak x-show="activeTab === 'distribution'" x-transition class="space-y-6">
            @include('admin.podcast-uploads.partials.form.distribution')
        </section>

        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" name="pipeline_action" value="save" class="lucille-button" :disabled="uploading">
                    Crear episodio
                </button>
                <button type="submit" name="pipeline_action" value="process" class="lucille-button-solid" :disabled="uploading" x-text="uploading ? 'Procesando...' : 'Crear y procesar'"></button>
                <a href="{{ route('admin.master-programs.index') }}" class="lucille-button">Administrar programas maestros</a>
                <a href="{{ route('admin.dashboard') }}" class="lucille-button">Volver al dashboard</a>
            </div>
        </section>
    </form>

    <section
        class="mt-8 border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8"
        x-data="podcastUploadsDashboard({ recentUrl: '{{ route('admin.podcast-uploads.recent') }}', refreshInterval: 15000 })"
        x-init="init()"
        @podcast-upload-complete.window="handleUploadSuccess()"
    >
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Últimos episodios</h2>
                <p class="mt-2 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">
                    Se actualiza automáticamente y muestra todo lo creado, incluidos borradores.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('admin.podcast-uploads.published') }}" class="lucille-button">Ver publicados</a>
                <a href="{{ route('admin.podcast-uploads.published.print') }}" target="_blank" class="lucille-button-solid">Imprimir publicados</a>
                <div class="flex items-center gap-3 text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">
                    <svg x-show="isRefreshing" x-cloak class="h-3.5 w-3.5 animate-spin text-[var(--color-lucille-accent)]" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                        <path class="opacity-90" fill="currentColor" d="M12 2a10 10 0 0 1 10 10h-3a7 7 0 0 0-7-7V2Z"></path>
                    </svg>
                    <span x-text="isRefreshing ? 'Actualizando estados...' : 'Auto-refresh inteligente'"></span>
                </div>
            </div>
        </div>

        <div class="mt-4 flex items-center justify-between gap-3 text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">
            <span x-text="lastUpdatedLabel"></span>
            <span x-show="isRefreshing" x-cloak>Refrescando lista...</span>
        </div>

        <div class="mt-6" x-ref="recentUploads">
            @include('admin.podcast-uploads.partials.recent-uploads', ['recentUploads' => $recentUploads])
        </div>
    </section>
</x-layouts.admin>
