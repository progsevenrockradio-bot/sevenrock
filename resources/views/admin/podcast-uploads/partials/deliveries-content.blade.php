@php
    $isAdminView = (bool) ($isAdminView ?? true);
    $indexRoute = $isAdminView ? route('admin.podcast-uploads.deliveries.index') : route('conductor.episodes.deliveries.index');
    $printRoute = $isAdminView ? route('admin.podcast-uploads.deliveries.print', $printQuery) : route('conductor.episodes.deliveries.print', $printQuery);
    $backRoute = $isAdminView ? route('admin.podcast-uploads.index') : route('conductor.episodes.index');
    $backLabel = $isAdminView ? 'Volver a Emisiones y podcasts' : 'Volver a mis entregas';

    $hasCustomFilters = ! empty($filters['master_program_id'])
        || ($isAdminView && $filters['submitted_by_user_id'] !== null)
        || ! empty($filters['uploaded_from'])
        || ! empty($filters['uploaded_to'])
        || ! empty($filters['emission_from'])
        || ! empty($filters['emission_to'])
        || ! empty($filters['distribution_status']);
@endphp

<div class="space-y-6">
    {{-- Cabecera --}}
    <header class="rounded-2xl border border-white/5 bg-[#101012] p-5 sm:p-6 shadow-xl">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-2 text-xs font-medium text-gray-400">
                    @if ($isAdminView)
                        <a href="{{ route('admin.podcast-uploads.index') }}" class="hover:text-white transition-colors">Emisiones y podcasts</a>
                        <span class="text-gray-600">/</span>
                    @endif
                    <span class="text-[#c32720]">Entregas y emisiones</span>
                </div>
                <h1 class="mt-1 font-display text-2xl sm:text-3xl font-bold tracking-wide uppercase text-white">
                    Entregas y Emisiones
                </h1>
                <p class="mt-1 max-w-3xl text-xs sm:text-sm text-gray-400 font-sans">
                    Consulta de programas subidos, responsable original, fecha real de subida y fecha prevista de emisión con filtros independientes.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                <a
                    href="{{ $printRoute }}"
                    target="_blank"
                    rel="noopener"
                    class="h-9 px-3.5 rounded-lg border border-[#c32720]/50 bg-[#c32720] hover:bg-[#a9201a] text-xs font-semibold text-white transition-colors inline-flex items-center gap-1.5"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    <span>Imprimir / Guardar como PDF</span>
                </a>
                <a
                    href="{{ $backRoute }}"
                    class="h-9 px-3.5 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-xs font-medium text-gray-200 hover:text-white transition-colors inline-flex items-center gap-1.5"
                >
                    <span>← {{ $backLabel }}</span>
                </a>
            </div>
        </div>
    </header>

    {{-- Filtros independientes --}}
    <section class="rounded-2xl border border-white/5 bg-[#101012] p-5 shadow-xl">
        <form method="GET" action="{{ $indexRoute }}" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {{-- Programa --}}
                <div>
                    <label for="filter_master_program_id" class="block text-[11px] font-semibold uppercase tracking-wider text-gray-400 mb-1.5">
                        Programa
                    </label>
                    <select
                        id="filter_master_program_id"
                        name="master_program_id"
                        class="w-full h-9 rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none"
                    >
                        <option value="">Todos los programas</option>
                        @foreach ($filterOptions['programs'] as $program)
                            <option value="{{ $program->id }}" @selected((int) ($filters['master_program_id'] ?? 0) === (int) $program->id)>
                                {{ $program->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Usuario que subió (solo admin) --}}
                @if ($isAdminView)
                    <div>
                        <label for="filter_submitted_by_user_id" class="block text-[11px] font-semibold uppercase tracking-wider text-gray-400 mb-1.5">
                            Subido por
                        </label>
                        <select
                            id="filter_submitted_by_user_id"
                            name="submitted_by_user_id"
                            class="w-full h-9 rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none"
                        >
                            <option value="">Todos los usuarios</option>
                            @if ($filterOptions['hasUnregistered'])
                                <option value="{{ \App\Services\PodcastDeliveryReportService::UNREGISTERED_USER_FILTER }}" @selected($filters['submitted_by_user_id'] === \App\Services\PodcastDeliveryReportService::UNREGISTERED_USER_FILTER)>
                                    Usuario no registrado
                                </option>
                            @endif
                            @foreach ($filterOptions['submitters'] as $submitter)
                                <option value="{{ $submitter->id }}" @selected((string) ($filters['submitted_by_user_id'] ?? '') === (string) $submitter->id)>
                                    {{ $submitter->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                {{-- Estado de distribución --}}
                <div>
                    <label for="filter_distribution_status" class="block text-[11px] font-semibold uppercase tracking-wider text-gray-400 mb-1.5">
                        Estado de distribución
                    </label>
                    <select
                        id="filter_distribution_status"
                        name="distribution_status"
                        class="w-full h-9 rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none"
                    >
                        <option value="">Todos los estados</option>
                        @foreach ($distributionStatusOptions as $statusKey => $statusLabel)
                            <option value="{{ $statusKey }}" @selected(($filters['distribution_status'] ?? '') === $statusKey)>
                                {{ $statusLabel }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Ordenación --}}
                <div>
                    <label for="filter_sort_by" class="block text-[11px] font-semibold uppercase tracking-wider text-gray-400 mb-1.5">
                        Ordenar por
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <select
                            id="filter_sort_by"
                            name="sort_by"
                            class="w-full h-9 rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none"
                        >
                            @foreach ($sortOptions as $sortKey => $sortLabel)
                                <option value="{{ $sortKey }}" @selected(($filters['sort_by'] ?? 'uploaded_at') === $sortKey)>
                                    {{ $sortLabel }}
                                </option>
                            @endforeach
                        </select>
                        <select
                            id="filter_sort_dir"
                            name="sort_dir"
                            aria-label="Dirección de orden"
                            class="w-full h-9 rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-gray-100 focus:border-[#c32720] focus:outline-none"
                        >
                            <option value="desc" @selected(($filters['sort_dir'] ?? 'desc') === 'desc')>Más reciente</option>
                            <option value="asc" @selected(($filters['sort_dir'] ?? 'desc') === 'asc')>Más antiguo</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Bloques separados visualmente: Intervalo de Subida vs Intervalo de Emisión --}}
            <div class="grid gap-4 md:grid-cols-2 pt-2 border-t border-white/5">
                <div class="rounded-xl border border-sky-500/20 bg-sky-950/15 p-3.5">
                    <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-sky-300 mb-2">
                        <span class="h-2 w-2 rounded-full bg-sky-400"></span>
                        <span>Fecha real de subida ({{ $appTimezone }})</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="filter_uploaded_from" class="block text-[11px] text-gray-400 mb-1">Subido desde</label>
                            <input
                                id="filter_uploaded_from"
                                type="date"
                                name="uploaded_from"
                                value="{{ $filters['uploaded_from'] ?? '' }}"
                                class="w-full h-9 rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-gray-100 focus:border-sky-400 focus:outline-none"
                            >
                        </div>
                        <div>
                            <label for="filter_uploaded_to" class="block text-[11px] text-gray-400 mb-1">Subido hasta</label>
                            <input
                                id="filter_uploaded_to"
                                type="date"
                                name="uploaded_to"
                                value="{{ $filters['uploaded_to'] ?? '' }}"
                                class="w-full h-9 rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-gray-100 focus:border-sky-400 focus:outline-none"
                            >
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-amber-500/20 bg-amber-950/15 p-3.5">
                    <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-amber-300 mb-2">
                        <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                        <span>Fecha prevista de emisión</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="filter_emission_from" class="block text-[11px] text-gray-400 mb-1">Emisión desde</label>
                            <input
                                id="filter_emission_from"
                                type="date"
                                name="emission_from"
                                value="{{ $filters['emission_from'] ?? '' }}"
                                class="w-full h-9 rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-gray-100 focus:border-amber-400 focus:outline-none"
                            >
                        </div>
                        <div>
                            <label for="filter_emission_to" class="block text-[11px] text-gray-400 mb-1">Emisión hasta</label>
                            <input
                                id="filter_emission_to"
                                type="date"
                                name="emission_to"
                                value="{{ $filters['emission_to'] ?? '' }}"
                                class="w-full h-9 rounded-lg border border-white/10 bg-[#151518] px-2.5 text-xs text-gray-100 focus:border-amber-400 focus:outline-none"
                            >
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                <div class="flex flex-wrap items-center gap-2 text-xs text-gray-400">
                    <span class="font-medium text-gray-200">{{ $paginator->total() }}</span>
                    <span>{{ $paginator->total() === 1 ? 'episodio encontrado' : 'episodios encontrados' }}</span>
                    @if ($paginator->total() > 0)
                        <span class="text-gray-600">·</span>
                        <span>Mostrando {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if ($hasCustomFilters)
                        <a
                            href="{{ $indexRoute }}"
                            class="h-9 px-3.5 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-xs font-medium text-gray-300 hover:text-white transition-colors inline-flex items-center"
                        >
                            Limpiar filtros
                        </a>
                    @endif
                    <button
                        type="submit"
                        class="h-9 px-4 rounded-lg bg-[#c32720] hover:bg-[#a9201a] text-xs font-semibold text-white transition-colors inline-flex items-center gap-1.5"
                    >
                        <span>Aplicar filtros</span>
                    </button>
                </div>
            </div>
        </form>
    </section>

    {{-- Leyenda de lectura --}}
    <div class="flex flex-wrap items-center justify-between gap-2 px-1 text-[11px] text-gray-400">
        <div class="flex flex-wrap items-center gap-4">
            <span class="inline-flex items-center gap-1.5">
                <span class="h-2 w-2 rounded-full bg-sky-400"></span>
                <span><strong class="text-sky-300">Subida real:</strong> momento exacto en que se registró la entrega ({{ $appTimezone }}).</span>
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                <span><strong class="text-amber-300">Emisión prevista:</strong> fecha programada al aire (nunca sustituye por fecha de subida).</span>
            </span>
        </div>
        @if (count($programTimezones) > 0)
            <div class="text-gray-500">
                Zonas horarias en vista: {{ implode(', ', $programTimezones) }}
            </div>
        @endif
    </div>

    {{-- Tabla de escritorio --}}
    <section class="hidden lg:block rounded-2xl border border-white/5 bg-[#101012] shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-xs">
                <thead>
                    <tr class="border-b border-white/10 bg-[#15151a] text-[11px] uppercase tracking-wider text-gray-400">
                        <th class="px-4 py-3.5 font-semibold">Programa</th>
                        <th class="px-4 py-3.5 font-semibold">Episodio</th>
                        <th class="px-4 py-3.5 font-semibold">Subido por</th>
                        <th class="px-4 py-3.5 font-semibold text-sky-300 bg-sky-950/20">Subida real</th>
                        <th class="px-4 py-3.5 font-semibold text-amber-300 bg-amber-950/20">Emisión prevista</th>
                        <th class="px-4 py-3.5 font-semibold">Zona horaria</th>
                        <th class="px-4 py-3.5 font-semibold text-right">Estado y detalle</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($rows as $row)
                        @php
                            $dist = $row['distribution'];
                            $badgeClasses = match ($dist['tone']) {
                                'success' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
                                'danger' => 'border-rose-500/30 bg-rose-500/10 text-rose-300',
                                'warning' => 'border-amber-500/30 bg-amber-500/10 text-amber-300',
                                'info' => 'border-sky-500/30 bg-sky-500/10 text-sky-300',
                                default => 'border-white/10 bg-white/5 text-gray-300',
                            };
                            $em = $row['emission'];
                            $rev = $row['revisions'];
                        @endphp
                        <tr class="hover:bg-white/[0.02] transition-colors align-top" x-data="{ showRevisions: false }">
                            {{-- Programa --}}
                            <td class="px-4 py-3.5 font-medium text-white">
                                <div class="max-w-[180px] break-words">{{ $row['program_name'] }}</div>
                            </td>

                            {{-- Episodio --}}
                            <td class="px-4 py-3.5">
                                <div class="flex items-start gap-2 max-w-[260px]">
                                    <span class="inline-flex shrink-0 items-center rounded bg-white/10 px-1.5 py-0.5 text-[11px] font-mono text-gray-200">
                                        Ep. {{ $row['episode_number'] ?? 'S/N' }}
                                    </span>
                                    <span class="text-gray-100 font-medium break-words">{{ $row['episode_title'] }}</span>
                                </div>
                            </td>

                            {{-- Subido por + Revisiones --}}
                            <td class="px-4 py-3.5">
                                <div class="{{ $row['submitted_by']['registered'] ? 'text-gray-100 font-medium' : 'text-gray-400 italic' }}">
                                    {{ $row['submitted_by']['name'] }}
                                </div>
                                @if ($rev['has_revisions'])
                                    <div class="mt-1.5">
                                        <button
                                            type="button"
                                            @click="showRevisions = !showRevisions"
                                            class="inline-flex items-center gap-1 rounded border border-white/10 bg-white/5 hover:bg-white/10 px-2 py-0.5 text-[10px] text-gray-300 transition-colors"
                                        >
                                            <span>v{{ $rev['version'] }}{{ $rev['has_audio_substitution'] ? ' · Audio sustituido' : '' }}</span>
                                            <span x-text="showRevisions ? '▲' : '▼'"></span>
                                        </button>

                                        <div
                                            x-show="showRevisions"
                                            x-cloak
                                            class="mt-2 rounded-lg border border-white/10 bg-[#15151a] p-2.5 text-[11px] text-gray-300 space-y-1.5 max-w-[240px]"
                                        >
                                            <div class="font-semibold text-[10px] uppercase tracking-wider text-gray-400">
                                                Revisiones disponibles
                                            </div>
                                            @forelse ($rev['items'] as $item)
                                                <div class="border-t border-white/5 pt-1 first:border-t-0 first:pt-0">
                                                    <div class="text-gray-200">
                                                        @if ($item['version'])
                                                            <span class="font-mono text-amber-300">v{{ $item['version'] }}</span> ·
                                                        @endif
                                                        {{ $item['label'] }}
                                                    </div>
                                                    @if ($item['timestamp'] || $item['detail'])
                                                        <div class="text-[10px] text-gray-400">
                                                            {{ $item['timestamp'] ?? '' }}{{ $item['timestamp'] && $item['detail'] ? ' · ' : '' }}{{ $item['detail'] ?? '' }}
                                                        </div>
                                                    @endif
                                                </div>
                                            @empty
                                                <div class="text-[10px] text-gray-400">Versión actual v{{ $rev['version'] }} sin eventos adicionales registrados.</div>
                                            @endforelse
                                        </div>
                                    </div>
                                @endif
                            </td>

                            {{-- Subida real --}}
                            <td class="px-4 py-3.5 bg-sky-950/10">
                                <div class="font-mono text-sky-200 font-medium">{{ $row['uploaded']['date'] }}</div>
                                @if ($row['uploaded']['time'])
                                    <div class="font-mono text-[11px] text-sky-300/80">{{ $row['uploaded']['time'] }}</div>
                                @endif
                                <div class="text-[10px] text-gray-500 mt-0.5">{{ $row['uploaded']['timezone'] }}</div>
                            </td>

                            {{-- Emisión prevista --}}
                            <td class="px-4 py-3.5 bg-amber-950/10">
                                @if ($em['date_missing'])
                                    <span class="inline-flex items-center rounded border border-rose-500/30 bg-rose-500/10 px-2 py-0.5 text-[11px] text-rose-300">
                                        Fecha no registrada
                                    </span>
                                @else
                                    <div class="font-mono text-amber-200 font-semibold">
                                        {{ $em['date'] }}
                                        @if ($em['weekday_label'])
                                            <span class="font-sans font-normal text-[11px] text-amber-300/80">({{ $em['weekday_label'] }})</span>
                                        @endif
                                    </div>

                                    @if (! $em['time_missing'])
                                        <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                                            <span class="font-mono text-[11px] text-amber-100">{{ $em['time'] }}</span>
                                            @if ($em['time_source'] === 'master_current_reference')
                                                <span
                                                    class="inline-flex items-center rounded border border-amber-400/25 bg-amber-500/10 px-1.5 py-0.5 text-[10px] text-amber-200"
                                                    title="El episodio no guarda hora histórica propia; se muestra la franja actual configurada en el programa maestro como referencia."
                                                >
                                                    Ref. actual
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <div class="mt-0.5 text-[11px] text-gray-400 italic">Hora no registrada</div>
                                    @endif

                                    @if (! empty($em['calendar_notice']))
                                        @php $notice = $em['calendar_notice']; @endphp
                                        @if ($notice['type'] === 'authorized_manual')
                                            <div
                                                class="mt-1.5 inline-flex items-center gap-1 rounded border border-sky-500/25 bg-sky-500/10 px-1.5 py-0.5 text-[10px] text-sky-200"
                                                title="{{ $notice['detail'] }}"
                                            >
                                                <span>{{ $notice['label'] }}</span>
                                            </div>
                                        @else
                                            <div
                                                class="mt-1.5 inline-flex items-center gap-1 rounded border border-amber-500/40 bg-amber-500/15 px-1.5 py-0.5 text-[10px] text-amber-200"
                                                title="{{ $notice['detail'] }}"
                                            >
                                                <span>⚠ {{ $notice['label'] }}</span>
                                            </div>
                                        @endif
                                    @endif
                                @endif
                            </td>

                            {{-- Zona horaria --}}
                            <td class="px-4 py-3.5">
                                <div class="font-mono text-[11px] {{ $row['timezone']['program'] ? 'text-gray-200' : 'text-gray-500 italic' }}">
                                    {{ $row['timezone']['program_label'] }}
                                </div>
                            </td>

                            {{-- Estado y detalle --}}
                            <td class="px-4 py-3.5 text-right">
                                <div class="inline-flex flex-col items-end gap-2">
                                    <span
                                        class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-medium {{ $badgeClasses }}"
                                        title="{{ $dist['detail'] }}"
                                    >
                                        {{ $dist['label'] }}
                                    </span>
                                    <a
                                        href="{{ $row['detail_url'] }}"
                                        class="inline-flex items-center gap-1 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 px-2.5 py-1 text-[11px] font-medium text-gray-200 hover:text-white transition-colors"
                                    >
                                        <span>Ver detalle</span>
                                        <span>→</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-400">
                                No se encontraron episodios con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Tarjetas móviles / tablet --}}
    <section class="lg:hidden space-y-3">
        @forelse ($rows as $row)
            @php
                $dist = $row['distribution'];
                $badgeClasses = match ($dist['tone']) {
                    'success' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
                    'danger' => 'border-rose-500/30 bg-rose-500/10 text-rose-300',
                    'warning' => 'border-amber-500/30 bg-amber-500/10 text-amber-300',
                    'info' => 'border-sky-500/30 bg-sky-500/10 text-sky-300',
                    default => 'border-white/10 bg-white/5 text-gray-300',
                };
                $em = $row['emission'];
                $rev = $row['revisions'];
            @endphp
            <article class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3" x-data="{ showRevisions: false }">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ $row['program_name'] }}</div>
                        <h2 class="mt-0.5 text-sm font-semibold text-white break-words">
                            <span class="font-mono text-gray-300">Ep. {{ $row['episode_number'] ?? 'S/N' }}</span> · {{ $row['episode_title'] }}
                        </h2>
                    </div>
                    <span class="shrink-0 inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-medium {{ $badgeClasses }}">
                        {{ $dist['label'] }}
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-2.5 text-xs">
                    <div class="rounded-lg border border-sky-500/20 bg-sky-950/15 p-2.5">
                        <div class="text-[10px] uppercase tracking-wider text-sky-300 font-semibold">Subida real</div>
                        <div class="mt-1 font-mono text-sky-100">{{ $row['uploaded']['full'] }}</div>
                        <div class="text-[10px] text-gray-400">{{ $row['uploaded']['timezone'] }}</div>
                    </div>

                    <div class="rounded-lg border border-amber-500/20 bg-amber-950/15 p-2.5">
                        <div class="text-[10px] uppercase tracking-wider text-amber-300 font-semibold">Emisión prevista</div>
                        @if ($em['date_missing'])
                            <div class="mt-1 text-rose-300">Fecha no registrada</div>
                        @else
                            <div class="mt-1 font-mono text-amber-100">{{ $em['date'] }} ({{ $em['weekday_label'] }})</div>
                            <div class="text-[11px] text-amber-200/90">
                                {{ $em['time'] ?? 'Hora no registrada' }}
                                @if ($em['time_source'] === 'master_current_reference')
                                    <span class="text-[10px] text-amber-300">· Ref. actual</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                @if (! empty($em['calendar_notice']))
                    @php $notice = $em['calendar_notice']; @endphp
                    <div class="rounded-lg border px-2.5 py-1.5 text-[11px] {{ $notice['type'] === 'authorized_manual' ? 'border-sky-500/25 bg-sky-500/10 text-sky-200' : 'border-amber-500/40 bg-amber-500/15 text-amber-200' }}">
                        <strong>{{ $notice['type'] === 'authorized_manual' ? '' : '⚠ ' }}{{ $notice['label'] }}:</strong> {{ $notice['detail'] }}
                    </div>
                @endif

                <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-white/5 text-xs">
                    <div class="text-gray-300">
                        <span class="text-gray-500">Subido por:</span>
                        <span class="{{ $row['submitted_by']['registered'] ? 'text-gray-100 font-medium' : 'text-gray-400 italic' }}">
                            {{ $row['submitted_by']['name'] }}
                        </span>
                        <span class="text-gray-600 mx-1">·</span>
                        <span class="font-mono text-[11px] text-gray-400">{{ $row['timezone']['program_label'] }}</span>
                    </div>

                    <a
                        href="{{ $row['detail_url'] }}"
                        class="inline-flex items-center gap-1 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 px-2.5 py-1 text-xs font-medium text-gray-200"
                    >
                        <span>Ver detalle →</span>
                    </a>
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-white/10 bg-[#101012] p-8 text-center text-sm text-gray-400">
                No se encontraron episodios con los filtros seleccionados.
            </div>
        @endforelse
    </section>

    {{-- Paginación estable --}}
    @if ($paginator->hasPages())
        <div class="pt-2">
            {{ $paginator->links() }}
        </div>
    @endif
</div>

