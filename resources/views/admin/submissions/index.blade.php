<x-layouts.admin :title="'Buzón de maquetas'">
    @php
        $statusLabels = [
            'pending' => 'Pendiente',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
        ];
        $statusStyles = [
            'pending' => 'border-amber-500/30 bg-amber-500/10 text-amber-300',
            'approved' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
            'rejected' => 'border-red-500/30 bg-red-500/10 text-red-300',
        ];
        $searchFilter = $filters['search'] ?? request('search', '');
        $statusFilter = $filters['status'] ?? request('status', '');
        $hasActiveFilters = filled($searchFilter) || filled($statusFilter);
    @endphp

    <div
        x-data="{
            activeTab: '{{ request()->has('followups_page') ? 'seguimiento' : (request()->has('logs_page') ? 'correos' : 'maquetas') }}',
            showEmailModal: false,
            currentEmailContent: '',
            selectedMaquetas: [],
            openEmailModal(content) {
                this.currentEmailContent = content;
                this.showEmailModal = true;
            },
            toggleAll() {
                const checkboxes = document.querySelectorAll('.maqueta-checkbox');
                if (this.selectedMaquetas.length === checkboxes.length && checkboxes.length > 0) {
                    this.selectedMaquetas = [];
                } else {
                    this.selectedMaquetas = Array.from(checkboxes).map(cb => cb.value);
                }
            },
            toggleFeed(submissionId, checkboxElement) {
                const isChecked = checkboxElement.checked;
                fetch(`/admin/submissions/${submissionId}/toggle-feed`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ show_in_feed: isChecked ? 1 : 0 })
                })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        alert('Error: ' + data.message);
                        checkboxElement.checked = !isChecked;
                    } else {
                        const span = checkboxElement.nextElementSibling;
                        if (span) {
                            const oldText = span.innerText;
                            span.innerText = 'Guardado';
                            span.classList.add('text-emerald-300');
                            setTimeout(() => {
                                span.innerText = oldText;
                                span.classList.remove('text-emerald-300');
                            }, 2000);
                        }
                    }
                })
                .catch(err => {
                    console.error(err);
                    checkboxElement.checked = !isChecked;
                });
            }
        }"
        class="mx-auto max-w-6xl space-y-5 font-sans"
    >
        {{-- Cabecera compacta y pestañas --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Bandas y comunidad · Recepción musical</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">Buzón de maquetas</h1>
                <p class="mt-1 text-xs text-gray-400">Escucha, evaluación, publicación en el catálogo de novedades e historial de notificaciones enviadas.</p>
            </div>

            <div class="inline-flex items-center rounded-xl border border-white/10 bg-[#101012] p-1 self-start sm:self-auto" role="tablist" aria-label="Secciones del buzón de maquetas">
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'maquetas'"
                    @click="activeTab = 'maquetas'"
                    :class="activeTab === 'maquetas' ? 'bg-[#c32720] text-white font-semibold shadow-sm' : 'text-gray-400 hover:text-white'"
                    class="inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-xs transition-colors focus:outline-none focus:ring-2 focus:ring-[#c32720]"
                >
                    <span>Maquetas recibidas</span>
                    <span class="rounded bg-black/30 px-1.5 py-0.5 text-[10px]">{{ $submissions->total() }}</span>
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'correos'"
                    @click="activeTab = 'correos'"
                    :class="activeTab === 'correos' ? 'bg-[#c32720] text-white font-semibold shadow-sm' : 'text-gray-400 hover:text-white'"
                    class="inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-xs transition-colors focus:outline-none focus:ring-2 focus:ring-[#c32720]"
                >
                    <span>Historial de correos</span>
                    <span class="rounded bg-black/30 px-1.5 py-0.5 text-[10px]">{{ $emailLogs->total() }}</span>
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'seguimiento'"
                    @click="activeTab = 'seguimiento'"
                    :class="activeTab === 'seguimiento' ? 'bg-[#c32720] text-white font-semibold shadow-sm' : 'text-gray-400 hover:text-white'"
                    class="inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-xs transition-colors focus:outline-none focus:ring-2 focus:ring-[#c32720]"
                >
                    <span>Seguimiento Muro del Rock</span>
                    <span class="rounded bg-black/30 px-1.5 py-0.5 text-[10px]">{{ isset($muroFollowups) ? $muroFollowups->total() : 0 }}</span>
                </button>
            </div>
        </header>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-xs text-emerald-200" role="status">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-xs text-red-200" role="alert">
                {{ session('error') }}
            </div>
        @endif

        {{-- PESTAÑA 1: Maquetas recibidas --}}
        <div x-show="activeTab === 'maquetas'" x-cloak class="space-y-4">
            {{-- Filtros --}}
            <form method="GET" action="{{ route('admin.submissions.index') }}" class="rounded-xl border border-white/10 bg-[#101012] p-3.5">
                <div class="grid gap-3 sm:grid-cols-[1.4fr_1fr_auto] items-end">
                    <div>
                        <label for="filter-submission-search" class="mb-1 block text-xs font-medium text-gray-300">Buscar banda, tema o correo</label>
                        <input
                            id="filter-submission-search"
                            type="search"
                            name="search"
                            value="{{ $searchFilter }}"
                            placeholder="Nombre de la banda, canción o contacto..."
                            class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                        >
                    </div>
                    <div>
                        <label for="filter-submission-status" class="mb-1 block text-xs font-medium text-gray-300">Estado de evaluación</label>
                        <select
                            id="filter-submission-status"
                            name="status"
                            class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                        >
                            <option value="">Todos los estados</option>
                            @foreach ($statusLabels as $key => $label)
                                <option value="{{ $key }}" @selected($statusFilter === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="submit" class="inline-flex h-9 flex-1 sm:flex-initial items-center justify-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#a8201a] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                            Filtrar
                        </button>
                        @if ($hasActiveFilters)
                            <a href="{{ route('admin.submissions.index') }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-300 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                                Restablecer filtros
                            </a>
                        @endif
                    </div>
                </div>
            </form>

            {{-- Barra de acción masiva (cuando hay aprobadas seleccionadas) --}}
            <form
                action="{{ route('admin.submissions.bulkPublish') }}"
                method="POST"
                x-show="selectedMaquetas.length > 0"
                x-cloak
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#c32720]/40 bg-[#101012] px-4 py-3"
            >
                @csrf
                <template x-for="id in selectedMaquetas" :key="id">
                    <input type="hidden" name="submissions[]" :value="id">
                </template>
                <span class="text-xs font-semibold text-white">
                    <span x-text="selectedMaquetas.length"></span> maqueta(s) aprobada(s) seleccionada(s)
                </span>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" name="action" value="publish_hidden" class="inline-flex h-8 items-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                        Publicar en catálogo (ocultas en portada)
                    </button>
                    <button type="submit" name="action" value="publish_visible" class="inline-flex h-8 items-center rounded-lg bg-[#c32720] px-3 text-xs font-semibold text-white hover:bg-[#a8201a]">
                        Publicar y mostrar en portada
                    </button>
                </div>
            </form>

            {{-- Vista Móvil (390 px) --}}
            <div class="space-y-3 md:hidden">
                @forelse ($submissions as $submission)
                    @php
                        $statusLabel = $statusLabels[$submission->status] ?? 'Pendiente';
                        $statusStyle = $statusStyles[$submission->status] ?? $statusStyles['pending'];
                    @endphp
                    <article class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h2 class="text-sm font-semibold text-white truncate">{{ $submission->band_name }}</h2>
                                <p class="text-xs text-gray-300 truncate">Tema: <strong class="text-white">{{ $submission->song_title }}</strong></p>
                            </div>
                            <span class="shrink-0 inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-medium {{ $statusStyle }}">
                                {{ $statusLabel }}
                            </span>
                        </div>

                        {{-- Reproductor y descarga --}}
                        @if ($submission->file_path)
                            <div class="rounded-lg border border-white/5 bg-[#151518] p-2.5 space-y-2">
                                <audio controls preload="none" class="h-9 w-full">
                                    <source src="{{ \App\Support\PublicMediaUrl::normalizePublicUrl($submission->file_path) }}" type="audio/mpeg">
                                    Tu navegador no soporta la reproducción de audio.
                                </audio>
                                <div class="flex items-center justify-between">
                                    <span class="text-[11px] text-gray-400">Recibida: {{ $submission->created_at?->format('d/m/Y') ?? 'N/D' }}</span>
                                    <a href="{{ route('admin.submissions.download', $submission) }}" class="inline-flex items-center gap-1 text-xs font-medium text-emerald-300 hover:text-emerald-200">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        <span>Descargar MP3</span>
                                    </a>
                                </div>
                            </div>
                        @else
                            <div class="rounded-lg border border-red-500/20 bg-red-500/5 px-3 py-2 text-xs text-red-300">
                                Sin archivo de audio adjunto
                            </div>
                        @endif

                        {{-- Contacto --}}
                        <div class="text-xs text-gray-300 space-y-1">
                            <div class="break-all">
                                <span class="text-gray-400">Correo:</span>
                                <a href="mailto:{{ $submission->contact_email }}" class="text-white hover:underline">{{ $submission->contact_email }}</a>
                            </div>
                            @if ($submission->phone_number)
                                <div><span class="text-gray-400">Teléfono:</span> {{ $submission->phone_number }}</div>
                            @endif
                            @if ($submission->social_link)
                                <div>
                                    <a href="{{ $submission->social_link }}" target="_blank" rel="noreferrer" class="inline-flex items-center gap-1 text-[#c32720] hover:underline">
                                        <span>Abrir enlace externo de la banda</span>
                                    </a>
                                </div>
                            @endif
                        </div>

                        @if ($submission->message)
                            <details class="rounded-lg border border-white/5 bg-[#151518]/70 px-3 py-2 text-xs">
                                <summary class="cursor-pointer font-medium text-gray-300">Ver mensaje de la banda</summary>
                                <p class="mt-1.5 text-gray-400 leading-relaxed">{{ $submission->message }}</p>
                            </details>
                        @endif

                        {{-- Acciones --}}
                        <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-white/5">
                            @if ($submission->status !== 'approved')
                                <form action="{{ route('admin.submissions.updateStatus', $submission) }}" method="POST" class="flex-1">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" class="inline-flex h-9 w-full items-center justify-center rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 text-xs font-medium text-emerald-300 hover:bg-emerald-500/20">
                                        Aprobar
                                    </button>
                                </form>
                            @endif

                            @if ($submission->status !== 'rejected')
                                <form action="{{ route('admin.submissions.updateStatus', $submission) }}" method="POST" class="flex-1">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" class="inline-flex h-9 w-full items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-300 hover:bg-white/[0.08] hover:text-white">
                                        Rechazar
                                    </button>
                                </form>
                            @endif

                            <form
                                action="{{ route('admin.submissions.destroy', $submission) }}"
                                method="POST"
                                data-confirm="¿Seguro que quieres eliminar esta maqueta? Esto borrará el MP3 permanentemente."
                                data-confirm-title="Eliminar maqueta"
                                data-confirm-action="Eliminar"
                                data-confirm-tone="danger"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg border border-red-500/30 bg-red-500/10 px-3 text-xs font-medium text-red-300 hover:bg-red-500/20">
                                    Eliminar
                                </button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="rounded-xl border border-white/10 bg-[#101012] p-8 text-center text-xs text-gray-400">
                        No hay maquetas recibidas con los criterios seleccionados.
                    </div>
                @endforelse
            </div>

            {{-- Vista Escritorio --}}
            <div class="hidden md:block rounded-xl border border-white/10 bg-[#101012] overflow-visible">
                <table class="min-w-full divide-y divide-white/10 text-left text-xs">
                    <thead class="bg-white/[0.02] text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                        <tr>
                            <th scope="col" class="w-10 px-3 py-3 text-center">
                                @if ($submissions->where('status', 'approved')->where('published_to_hub', false)->count() > 0)
                                    <input
                                        type="checkbox"
                                        @change="toggleAll()"
                                        :checked="selectedMaquetas.length > 0 && selectedMaquetas.length === document.querySelectorAll('.maqueta-checkbox').length"
                                        aria-label="Seleccionar todas las maquetas aprobadas"
                                        class="h-4 w-4 rounded border-white/20 bg-[#151518] text-[#c32720] focus:ring-[#c32720] cursor-pointer"
                                    >
                                @endif
                            </th>
                            <th scope="col" class="px-3 py-3">Banda y tema</th>
                            <th scope="col" class="px-3 py-3">Audio y descarga</th>
                            <th scope="col" class="px-3 py-3">Contacto</th>
                            <th scope="col" class="px-3 py-3">Estado</th>
                            <th scope="col" class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse ($submissions as $submission)
                            @php
                                $statusLabel = $statusLabels[$submission->status] ?? 'Pendiente';
                                $statusStyle = $statusStyles[$submission->status] ?? $statusStyles['pending'];
                            @endphp
                            <tr class="hover:bg-white/[0.02] transition-colors align-top">
                                <td class="px-3 py-3.5 text-center">
                                    @if ($submission->status === 'approved' && !$submission->published_to_hub)
                                        <input
                                            type="checkbox"
                                            value="{{ $submission->id }}"
                                            x-model="selectedMaquetas"
                                            aria-label="Seleccionar maqueta de {{ $submission->band_name }}"
                                            class="maqueta-checkbox h-4 w-4 rounded border-white/20 bg-[#151518] text-[#c32720] focus:ring-[#c32720] cursor-pointer"
                                        >
                                    @endif
                                </td>
                                <td class="px-3 py-3.5">
                                    <div class="font-semibold text-white text-sm">{{ $submission->band_name }}</div>
                                    <div class="mt-0.5 text-xs text-gray-300">{{ $submission->song_title }}</div>
                                    <div class="mt-1 text-[11px] text-gray-400">Recibida: {{ $submission->created_at?->format('d/m/Y') ?? 'N/D' }}</div>
                                    @if ($submission->message)
                                        <details class="mt-1.5 text-[11px]">
                                            <summary class="cursor-pointer text-gray-400 hover:text-white">Ver nota de la banda</summary>
                                            <p class="mt-1 max-w-xs rounded border border-white/5 bg-[#151518] p-2 text-gray-300 leading-relaxed">{{ $submission->message }}</p>
                                        </details>
                                    @endif
                                </td>
                                <td class="px-3 py-3.5">
                                    @if ($submission->file_path)
                                        <div class="w-60 space-y-1.5 rounded-lg border border-white/5 bg-[#151518] p-2">
                                            <audio controls preload="none" class="h-8 w-full">
                                                <source src="{{ \App\Support\PublicMediaUrl::normalizePublicUrl($submission->file_path) }}" type="audio/mpeg">
                                            </audio>
                                            <a href="{{ route('admin.submissions.download', $submission) }}" class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-300 hover:text-emerald-200">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                <span>Descargar MP3</span>
                                            </a>
                                        </div>
                                    @else
                                        <span class="inline-flex items-center rounded-md border border-red-500/30 bg-red-500/10 px-2 py-0.5 text-[11px] text-red-300">
                                            Sin archivo adjunto
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-3.5">
                                    <a href="mailto:{{ $submission->contact_email }}" class="text-gray-200 hover:text-white hover:underline break-all block max-w-[200px]">{{ $submission->contact_email }}</a>
                                    @if ($submission->phone_number)
                                        <div class="mt-0.5 text-[11px] text-gray-400">{{ $submission->phone_number }}</div>
                                    @endif
                                    @if ($submission->social_link)
                                        <a href="{{ $submission->social_link }}" target="_blank" rel="noreferrer" class="mt-1 inline-flex items-center gap-1 text-[11px] font-medium text-[#c32720] hover:underline">
                                            <span>Enlace externo</span>
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                    @endif
                                </td>
                                <td class="px-3 py-3.5 whitespace-nowrap">
                                    <div class="flex flex-col items-start gap-1.5">
                                        <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-medium {{ $statusStyle }}">
                                            {{ $statusLabel }}
                                        </span>
                                        @if ($submission->published_to_hub)
                                            <span class="inline-flex items-center rounded-md border border-white/10 bg-white/[0.04] px-2 py-0.5 text-[10px] font-medium text-gray-300">
                                                Publicada en catálogo
                                            </span>
                                        @endif
                                        @if ($submission->hasValidMarketingConsent())
                                            <span class="inline-flex items-center rounded-md border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-medium text-emerald-300" title="Aceptó recibir información del Muro del Rock ({{ $submission->marketing_opt_in_at?->format('d/m/Y H:i') }} · {{ $submission->marketing_opt_in_version }})">
                                                Opt-in Muro: Sí
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-md border border-white/10 px-2 py-0.5 text-[10px] text-gray-400" title="Sin consentimiento comercial para el Muro del Rock">
                                                Opt-in Muro: No
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap text-right">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                        @if ($submission->status !== 'approved')
                                            <form action="{{ route('admin.submissions.updateStatus', $submission) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="approved">
                                                <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-2.5 text-xs font-medium text-emerald-300 hover:bg-emerald-500/20">
                                                    Aprobar
                                                </button>
                                            </form>
                                        @endif

                                        @if ($submission->status !== 'rejected')
                                            <form action="{{ route('admin.submissions.updateStatus', $submission) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="rejected">
                                                <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-white/10 bg-white/[0.04] px-2.5 text-xs font-medium text-gray-300 hover:bg-white/[0.08] hover:text-white">
                                                    Rechazar
                                                </button>
                                            </form>
                                        @endif

                                        @if ($submission->status === 'approved')
                                            @if (!$submission->published_to_hub)
                                                <form id="publish-form-{{ $submission->id }}" action="{{ route('admin.submissions.publish', $submission) }}" method="POST" class="inline-flex items-center gap-1.5">
                                                    @csrf
                                                    <label class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-white/10 bg-[#151518] px-2 text-[11px] text-gray-300 cursor-pointer" title="Mostrar en portada al publicar">
                                                        <input type="checkbox" name="show_in_feed" value="1" class="h-3.5 w-3.5 rounded border-white/20 bg-[#101012] text-[#c32720] focus:ring-[#c32720]">
                                                        <span>Portada</span>
                                                    </label>
                                                    <button type="submit" class="inline-flex h-8 items-center rounded-lg bg-[#c32720] px-2.5 text-xs font-semibold text-white hover:bg-[#a8201a]">
                                                        Publicar
                                                    </button>
                                                </form>
                                            @else
                                                @php
                                                    $hasActiveFeed = $submission->new_release_id
                                                        ? \App\Models\NewRelease::where('id', $submission->new_release_id)->where('show_in_feed', true)->exists()
                                                        : \App\Models\NewRelease::where('title', $submission->song_title)
                                                            ->where('artist_name', $submission->band_name)
                                                            ->where('show_in_feed', true)
                                                            ->exists();
                                                @endphp
                                                <label class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-white/10 bg-[#151518] px-2.5 text-[11px] text-gray-300 cursor-pointer">
                                                    <input type="checkbox" @change="toggleFeed({{ $submission->id }}, $event.target)" {{ $hasActiveFeed ? 'checked' : '' }} class="h-3.5 w-3.5 rounded border-white/20 bg-[#101012] text-[#c32720] focus:ring-[#c32720]">
                                                    <span>En portada</span>
                                                </label>
                                                @if ($submission->publication_notice_status === 'failed')
                                                    <form action="{{ route('admin.submissions.retryNotice', $submission) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-amber-500/30 bg-amber-500/10 px-2.5 text-xs font-medium text-amber-300 hover:bg-amber-500/20" title="El aviso falló. Reintenta solo el correo; no se republica.">
                                                            Reintentar aviso
                                                        </button>
                                                    </form>
                                                @elseif (in_array($submission->publication_notice_status, ['pending', 'unknown'], true))
                                                    <span class="inline-flex h-8 items-center rounded-lg border border-white/10 px-2.5 text-[11px] text-gray-400" title="Aviso en estado indeterminado: no se reenvía automáticamente. Revisa el historial.">Aviso por revisar</span>
                                                @endif
                                            @endif
                                        @endif

                                        <form
                                            action="{{ route('admin.submissions.destroy', $submission) }}"
                                            method="POST"
                                            data-confirm="¿Seguro que quieres eliminar esta maqueta? Esto borrará el MP3 permanentemente."
                                            data-confirm-title="Eliminar maqueta"
                                            data-confirm-action="Eliminar"
                                            data-confirm-tone="danger"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-red-500/30 bg-red-500/10 px-2.5 text-xs font-medium text-red-300 hover:bg-red-500/20">
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-xs text-gray-400">
                                    No hay maquetas recibidas con los criterios seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($submissions->hasPages())
                <div class="pt-2">
                    {{ $submissions->links() }}
                </div>
            @endif
        </div>

        {{-- PESTAÑA 2: Historial de correos --}}
        <div x-show="activeTab === 'correos'" x-cloak class="space-y-4">
            {{-- Móvil (390 px) --}}
            <div class="space-y-3 md:hidden">
                @forelse ($emailLogs as $log)
                    <article class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-2.5">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold text-white">{{ $log->trackSubmission?->band_name ?? 'Banda' }}</span>
                            <span class="text-[11px] text-gray-400">{{ $log->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                        <div class="text-xs text-gray-300 break-all">{{ $log->to_email }}</div>
                        <div class="text-xs text-gray-400">{{ $log->subject ?: 'Sin asunto' }}</div>
                        <div class="pt-1">
                            <button
                                type="button"
                                @click='openEmailModal(@json($log->body))'
                                class="inline-flex h-9 w-full items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white"
                            >
                                Ver correo enviado
                            </button>
                        </div>
                    </article>
                @empty
                    <div class="rounded-xl border border-white/10 bg-[#101012] p-8 text-center text-xs text-gray-400">
                        No se han registrado correos enviados sobre maquetas.
                    </div>
                @endforelse
            </div>

            {{-- Escritorio --}}
            <div class="hidden md:block rounded-xl border border-white/10 bg-[#101012] overflow-hidden">
                <table class="min-w-full divide-y divide-white/10 text-left text-xs">
                    <thead class="bg-white/[0.02] text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                        <tr>
                            <th scope="col" class="px-4 py-3">Fecha de envío</th>
                            <th scope="col" class="px-3 py-3">Banda y destinatario</th>
                            <th scope="col" class="px-3 py-3">Asunto</th>
                            <th scope="col" class="px-4 py-3 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse ($emailLogs as $log)
                            <tr class="hover:bg-white/[0.02] transition-colors">
                                <td class="px-4 py-3 whitespace-nowrap text-gray-300">
                                    <div>{{ $log->created_at->format('d/m/Y') }}</div>
                                    <div class="text-[11px] text-gray-500">{{ $log->created_at->format('H:i') }}</div>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($log->trackSubmission)
                                        <div class="font-semibold text-white">{{ $log->trackSubmission->band_name }}</div>
                                    @endif
                                    <div class="text-[11px] text-gray-400">{{ $log->to_email }}</div>
                                </td>
                                <td class="px-3 py-3 text-gray-200">
                                    {{ $log->subject ?: 'Sin asunto' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right">
                                    <button
                                        type="button"
                                        @click='openEmailModal(@json($log->body))'
                                        class="inline-flex h-8 items-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]"
                                    >
                                        Ver correo
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center text-xs text-gray-400">
                                    No se han registrado correos enviados sobre maquetas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($emailLogs->hasPages())
                <div class="pt-2">
                    {{ $emailLogs->links() }}
                </div>
            @endif
        </div>

        {{-- PESTAÑA 3: Seguimiento Muro del Rock --}}
        @php
            $sim = $followupSimulation ?? [
                'campaign_enabled' => false,
                'has_approved_cta_url' => false,
                'reply_to' => 'prog.sevenrockradio@gmail.com',
                'sent' => 0,
                'skipped' => 0,
                'failed' => 0,
                'blocked_or_waiting' => 0,
                'items' => [],
            ];
            $simById = collect($sim['items'] ?? [])->keyBy('id');
            $followupStatusStyles = [
                'scheduled' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
                'paused' => 'border-amber-500/30 bg-amber-500/10 text-amber-300',
                'cancelled' => 'border-white/10 bg-white/[0.04] text-gray-400',
                'skipped' => 'border-white/10 bg-white/[0.04] text-gray-400',
                'sending' => 'border-amber-500/30 bg-amber-500/10 text-amber-300',
                'sent' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
                'failed' => 'border-red-500/30 bg-red-500/10 text-red-300',
                'unknown' => 'border-amber-500/30 bg-amber-500/10 text-amber-300',
            ];
        @endphp
        <div x-show="activeTab === 'seguimiento'" x-cloak class="space-y-4">
            {{-- Estado de seguridad y vista previa --}}
            <div class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Invitación opcional · 3 días después de la primera publicación</div>
                        <h2 class="mt-0.5 font-display text-lg uppercase tracking-wider text-white">Estado del seguimiento comercial (Muro del Rock)</h2>
                        <p class="mt-1 text-xs text-gray-400">
                            Un único seguimiento por contacto y campaña. Solo para bandas con consentimiento explícito, sin baja y sin plan de pago activo.
                        </p>
                    </div>
                    @if (!empty($followupPreviewHtml))
                        <button
                            type="button"
                            @click='openEmailModal(@json($followupPreviewHtml))'
                            class="inline-flex h-9 items-center justify-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#a8201a]"
                        >
                            Ver borrador del correo de invitación
                        </button>
                    @endif
                </div>

                <div class="grid gap-3 sm:grid-cols-3 pt-2 border-t border-white/10 text-xs">
                    <div class="rounded-lg border border-white/5 bg-[#151518] p-3">
                        <div class="text-[11px] text-gray-400">Estado de la campaña</div>
                        <div class="mt-1 font-semibold {{ $sim['campaign_enabled'] ? 'text-emerald-300' : 'text-amber-300' }}">
                            {{ $sim['campaign_enabled'] ? 'Activa' : 'Desactivada por defecto (bloqueo activo)' }}
                        </div>
                        <div class="mt-0.5 text-[11px] text-gray-500">Configuración: MURO_FOLLOWUP_ENABLED</div>
                    </div>
                    <div class="rounded-lg border border-white/5 bg-[#151518] p-3">
                        <div class="text-[11px] text-gray-400">Destino comercial (Plan Básico 3,50 €/mes)</div>
                        <div class="mt-1 font-semibold break-all {{ $sim['has_approved_cta_url'] ? 'text-emerald-300' : 'text-amber-300' }}">
                            {{ $sim['has_approved_cta_url'] ? app(\App\Services\SubmissionFollowupService::class)->configuredCtaUrl() : 'Pendiente de URL aprobada (no enlaza al registro actual)' }}
                        </div>
                        <div class="mt-0.5 text-[11px] text-gray-500">Configuración: MURO_FOLLOWUP_CTA_URL</div>
                    </div>
                    <div class="rounded-lg border border-white/5 bg-[#151518] p-3">
                        <div class="text-[11px] text-gray-400">Buzón de respuesta (Reply-To) y Simulación</div>
                        <div class="mt-1 font-semibold text-white break-all">{{ $sim['reply_to'] }}</div>
                        <div class="mt-0.5 text-[11px] text-gray-400">
                            Simulación actual: {{ $sim['sent'] }} enviables ahora · {{ $sim['blocked_or_waiting'] }} en espera/retenidos · {{ $sim['skipped'] }} excluidos
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tabla de seguimientos --}}
            <div class="rounded-xl border border-white/10 bg-[#101012] overflow-hidden">
                <table class="min-w-full divide-y divide-white/10 text-left text-xs">
                    <thead class="bg-white/[0.02] text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                        <tr>
                            <th scope="col" class="px-4 py-3">Banda y contacto</th>
                            <th scope="col" class="px-3 py-3">Publicación y plazo (+3 días)</th>
                            <th scope="col" class="px-3 py-3">Estado</th>
                            <th scope="col" class="px-3 py-3">Motivo / Diagnóstico de simulación</th>
                            <th scope="col" class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse ($muroFollowups ?? [] as $followup)
                            @php
                                $fStyle = $followupStatusStyles[$followup->status] ?? 'border-white/10 bg-white/[0.04] text-gray-300';
                                $simItem = $simById->get($followup->id);
                            @endphp
                            <tr class="hover:bg-white/[0.02] transition-colors align-top">
                                <td class="px-4 py-3.5">
                                    <div class="font-semibold text-white text-sm">{{ $followup->band_name }}</div>
                                    <div class="text-[11px] text-gray-300 break-all">{{ $followup->contact_email }}</div>
                                    @if ($followup->trackSubmission)
                                        <div class="mt-1 text-[11px] text-gray-400">Tema: {{ $followup->trackSubmission->song_title }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-3.5 whitespace-nowrap text-gray-300">
                                    <div>1.ª pub.: {{ $followup->first_published_at?->format('d/m/Y H:i') ?? 'N/D' }}</div>
                                    <div class="mt-0.5 text-[11px] text-gray-400">
                                        Previsto: {{ $followup->scheduled_for?->format('d/m/Y H:i') ?? 'No programado' }}
                                    </div>
                                    @if ($followup->sent_at)
                                        <div class="mt-0.5 text-[11px] text-emerald-300">Enviado: {{ $followup->sent_at->format('d/m/Y H:i') }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-3.5 whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-medium {{ $fStyle }}">
                                        {{ $followup->statusLabel() }}
                                    </span>
                                    @if ($followup->attempts > 0)
                                        <div class="mt-1 text-[11px] text-gray-400">Intentos: {{ $followup->attempts }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-3.5 text-gray-300">
                                    <div>{{ $followup->reasonLabel() }}</div>
                                    @if ($simItem)
                                        <div class="mt-1 text-[11px] text-gray-400">
                                            Simulación: {{ $simItem['can_send_now'] ? 'Se enviaría ahora' : $simItem['reason_text'] }}
                                        </div>
                                    @endif
                                    @if ($followup->last_error)
                                        <div class="mt-1 text-[11px] text-red-300">Error: {{ $followup->last_error }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap text-right">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                        @if ($followup->status === 'scheduled')
                                            <form action="{{ route('admin.submissions.followups.pause', $followup) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-amber-500/30 bg-amber-500/10 px-2.5 text-xs font-medium text-amber-300 hover:bg-amber-500/20">
                                                    Pausar
                                                </button>
                                            </form>
                                        @endif

                                        @if ($followup->status === 'paused')
                                            <form action="{{ route('admin.submissions.followups.resume', $followup) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-2.5 text-xs font-medium text-emerald-300 hover:bg-emerald-500/20">
                                                    Reanudar
                                                </button>
                                            </form>
                                        @endif

                                        @if ($followup->status === 'failed')
                                            <form action="{{ route('admin.submissions.followups.retry', $followup) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-amber-500/30 bg-amber-500/10 px-2.5 text-xs font-medium text-amber-300 hover:bg-amber-500/20" title="Reintenta únicamente un fallo de envío confirmado">
                                                    Reintentar envío
                                                </button>
                                            </form>
                                        @endif

                                        @if (in_array($followup->status, ['scheduled', 'paused', 'failed'], true))
                                            <form action="{{ route('admin.submissions.followups.cancel', $followup) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-red-500/30 bg-red-500/10 px-2.5 text-xs font-medium text-red-300 hover:bg-red-500/20">
                                                    Cancelar
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-xs text-gray-400">
                                    No hay seguimientos registrados todavía. Se crean automáticamente cuando se publica por primera vez una maqueta.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (isset($muroFollowups) && $muroFollowups->hasPages())
                <div class="pt-2">
                    {{ $muroFollowups->links() }}
                </div>
            @endif
        </div>

        {{-- Modal de vista previa de correo --}}
        <div x-show="showEmailModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" x-cloak>
            <div
                x-show="showEmailModal"
                @click.away="showEmailModal = false"
                class="w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden rounded-xl border border-white/10 bg-[#101012] shadow-2xl"
            >
                <div class="flex items-center justify-between border-b border-white/10 px-5 py-3.5">
                    <h3 class="font-display text-sm uppercase tracking-wider text-white">Contenido del correo enviado</h3>
                    <button type="button" @click="showEmailModal = false" class="text-gray-400 hover:text-white" aria-label="Cerrar vista previa">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="flex-1 bg-white">
                    <iframe class="w-full h-[65vh] border-0" :srcdoc="currentEmailContent" title="Vista previa del correo"></iframe>
                </div>
                <div class="flex justify-end border-t border-white/10 px-5 py-3">
                    <button type="button" @click="showEmailModal = false" class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/[0.04] px-4 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
