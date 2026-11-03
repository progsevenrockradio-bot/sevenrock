<x-layouts.admin :title="'Programas de radio - '.(optional($themeSettings)->site_name ?? config('app.name'))">
    @php $admin = $themeAppearance['admin_texts'] ?? []; @endphp

    <div class="space-y-6">
        {{-- Cabecera principal --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="font-display text-2xl font-bold uppercase tracking-wider text-white">Programas de radio</h1>
                <p class="mt-1 text-sm text-zinc-400">Gestión del catálogo de programas base, horarios de emisión, códigos e invitaciones a productores.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.master-programs.grid') }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3.5 text-xs font-medium text-zinc-200 transition hover:border-white/20 hover:bg-white/10 hover:text-white">
                    <svg class="h-4 w-4 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    <span>Ver parrilla</span>
                </a>
                <a href="{{ route('admin.master-programs.report') }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3.5 text-xs font-medium text-zinc-200 transition hover:border-white/20 hover:bg-white/10 hover:text-white">
                    <svg class="h-4 w-4 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <span>Reporte de horarios</span>
                </a>
                <a href="{{ route('admin.programs.index') }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3.5 text-xs font-medium text-zinc-200 transition hover:border-white/20 hover:bg-white/10 hover:text-white">
                    <svg class="h-4 w-4 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                    <span>Invitaciones a programas</span>
                </a>
                <a href="{{ route('admin.master-programs.create') }}" class="inline-flex h-9 items-center gap-2 rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white shadow-sm transition hover:bg-[#d62d26]">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <span>Nuevo programa</span>
                </a>
            </div>
        </div>

        {{-- Barra de búsqueda --}}
        <form method="GET" class="flex flex-wrap items-center gap-2.5 rounded-xl border border-white/10 bg-[#101012] p-3">
            <div class="relative min-w-[240px] flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input
                    name="search"
                    value="{{ $search }}"
                    class="h-9 w-full rounded-lg border border-white/10 bg-[#161619] pl-9 pr-3 text-sm text-zinc-100 placeholder-zinc-500 focus:border-[#c32720] focus:outline-none focus:ring-2 focus:ring-[#c32720]/30"
                    placeholder="Buscar por nombre de programa, código, conductor o correo..."
                >
            </div>
            <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-white/10 px-4 text-xs font-medium text-white transition hover:bg-white/15">
                Filtrar
            </button>
            @if ($search)
                <a href="{{ route('admin.master-programs.index') }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-white/10 px-3.5 text-xs font-medium text-zinc-400 transition hover:bg-white/5 hover:text-zinc-200">
                    Limpiar
                </a>
            @endif
        </form>

        {{-- Navegación por días y listado --}}
        <div class="space-y-4" x-data='{"activeDay": "{{ $activeDay }}"}'>
            <div class="flex flex-wrap items-center gap-1.5 rounded-xl border border-white/10 bg-[#101012] p-1.5">
                @foreach ($dayTabs as $dayKey => $dayLabel)
                    @php $dayPrograms = $programsByDay->get($dayKey, collect()); @endphp
                    <button
                        type="button"
                        @click="activeDay = '{{ $dayKey }}'"
                        data-day-tab="{{ $dayKey }}"
                        class="inline-flex h-9 items-center gap-2.5 rounded-lg px-3.5 text-xs font-medium transition-colors"
                        :class="activeDay === '{{ $dayKey }}'
                            ? 'bg-[#c32720] text-white shadow-sm'
                            : 'text-zinc-400 hover:bg-white/5 hover:text-zinc-200'"
                        aria-label="Ver programas de {{ $dayLabel }}"
                    >
                        <span>{{ $dayLabel }}</span>
                        <span
                            class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full px-1.5 text-[11px] font-semibold"
                            :class="activeDay === '{{ $dayKey }}' ? 'bg-black/25 text-white' : 'bg-white/5 text-zinc-400'"
                        >
                            {{ $dayPrograms->count() }}
                        </span>
                    </button>
                @endforeach
            </div>

            @foreach ($dayTabs as $dayKey => $dayLabel)
                @php $dayPrograms = $programsByDay->get($dayKey, collect()); @endphp
                <section
                    x-cloak
                    x-show="activeDay === '{{ $dayKey }}'"
                    x-transition.opacity.duration.150ms
                    data-day-panel="{{ $dayKey }}"
                    class="space-y-3"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2 px-1">
                        <div class="flex items-center gap-2.5">
                            <h2 class="font-display text-lg font-bold uppercase tracking-wider text-zinc-100">{{ $dayLabel }}</h2>
                            <span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-0.5 text-xs font-medium text-zinc-400">
                                {{ $dayPrograms->count() }} programa{{ $dayPrograms->count() === 1 ? '' : 's' }}
                            </span>
                        </div>
                        <span class="text-xs text-zinc-500">Ordenado por hora de transmisión</span>
                    </div>

                    @if ($dayPrograms->isEmpty())
                        <div class="rounded-xl border border-white/10 bg-[#101012] px-6 py-12 text-center">
                            <p class="text-sm text-zinc-400">No hay programas asignados para este día.</p>
                        </div>
                    @else
                        {{-- Vista móvil (tarjetas compactas) --}}
                        <div class="space-y-3 md:hidden">
                            @foreach ($dayPrograms as $masterProgram)
                                @php
                                    $emisionesActivas = $masterProgram->relationLoaded('emisiones')
                                        ? $masterProgram->emisiones->where('activo', true)->values()
                                        : $masterProgram->emisiones()->activas()->get();
                                    $emisionesDelDia = $emisionesActivas->filter(fn ($em) => strtoupper(trim((string) $em->dia_semana)) === $dayKey)->values();
                                @endphp
                                <div class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            @if ($masterProgram->cover_url)
                                                <img src="{{ $masterProgram->cover_url }}" loading="lazy" alt="{{ $masterProgram->name }}" class="h-12 w-12 shrink-0 rounded-lg border border-white/10 object-cover bg-zinc-900">
                                            @else
                                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border border-white/10 bg-zinc-900 text-[10px] font-medium text-zinc-500">Sin foto</div>
                                            @endif
                                            <div class="min-w-0">
                                                <div class="font-semibold text-zinc-100 truncate">{{ $masterProgram->name }}</div>
                                                <div class="text-xs text-zinc-400 truncate">{{ $masterProgram->conductor ?: 'Sin conductor asignado' }}</div>
                                            </div>
                                        </div>
                                        @if ($masterProgram->activo)
                                            <span class="inline-flex shrink-0 items-center gap-1 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-400">Activo</span>
                                        @else
                                            <span class="inline-flex shrink-0 items-center gap-1 rounded-full border border-white/10 bg-white/5 px-2 py-0.5 text-[11px] font-medium text-zinc-400">Inactivo</span>
                                        @endif
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-300 border-t border-white/5 pt-2.5">
                                        <span class="rounded border border-white/10 bg-white/5 px-2 py-0.5 font-mono text-[11px] text-zinc-200">{{ $masterProgram->program_code ?: 'Sin código' }}</span>
                                        <span>·</span>
                                        @if ($emisionesDelDia->isNotEmpty())
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                @foreach ($emisionesDelDia as $em)
                                                    <span class="font-semibold text-zinc-100">
                                                        {{ $em->horaFormateada() }}
                                                        @if ($em->tipo === 'retransmision')
                                                            <span class="text-[10px] text-amber-400/90 font-normal">(Podcast)</span>
                                                        @elseif ($em->tipo === 'en_vivo')
                                                            <span class="text-[10px] text-red-400 font-normal">(En vivo)</span>
                                                        @endif
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span>{{ $masterProgram->dia_transmision }} {{ $masterProgram->hora_transmision ? substr((string) $masterProgram->hora_transmision, 0, 5) : 'Sin hora' }}</span>
                                        @endif
                                        <span class="text-zinc-500">({{ $masterProgram->timezone }})</span>
                                    </div>

                                    <div class="flex items-center justify-end gap-2 border-t border-white/5 pt-2.5">
                                        <a href="{{ route('admin.master-programs.edit', $masterProgram) }}" class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3 text-xs font-medium text-zinc-200">
                                            Editar
                                        </a>
                                        <button type="button" @click="$dispatch('open-invitation', { id: {{ $masterProgram->id }}, name: '{{ addslashes($masterProgram->name) }}', email: '{{ addslashes((string)($masterProgram->email_notificacion ?? '')) }}' })" class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3 text-xs font-medium text-zinc-200">
                                            Invitar
                                        </button>
                                        <form action="{{ route('admin.master-programs.destroy', $masterProgram) }}" method="POST" data-confirm="¿Eliminar este programa de radio?" data-confirm-title="Eliminar programa" data-confirm-action="Eliminar" data-confirm-tone="danger">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-red-500/25 bg-red-500/10 px-2.5 text-xs font-medium text-red-400">
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Vista escritorio (tabla) --}}
                        <div class="hidden md:block overflow-hidden rounded-xl border border-white/10 bg-[#101012]">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-white/10 text-left text-sm">
                                    <thead class="bg-white/[0.02] text-xs font-semibold text-zinc-400">
                                        <tr>
                                            <th class="px-4 py-3">Programa</th>
                                            <th class="px-4 py-3">Código</th>
                                            <th class="px-4 py-3">Horario ({{ $dayLabel }})</th>
                                            <th class="px-4 py-3">Emisiones activas</th>
                                            <th class="px-4 py-3">Estado</th>
                                            <th class="px-4 py-3 text-right">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/5">
                                        @foreach ($dayPrograms as $masterProgram)
                                            <tr class="align-middle transition hover:bg-white/[0.02]">
                                                <td class="px-4 py-3.5">
                                                    <div class="flex items-center gap-3.5">
                                                        @if ($masterProgram->cover_url)
                                                            <img
                                                                src="{{ $masterProgram->cover_url }}"
                                                                loading="lazy"
                                                                alt="{{ $masterProgram->name }}"
                                                                class="h-12 w-12 shrink-0 rounded-lg border border-white/10 object-cover bg-zinc-900"
                                                            >
                                                        @else
                                                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border border-white/10 bg-zinc-900 text-[10px] font-medium text-zinc-500">
                                                                Sin foto
                                                            </div>
                                                        @endif
                                                        <div class="min-w-0">
                                                            <div class="font-semibold text-zinc-100">{{ $masterProgram->name }}</div>
                                                            <div class="mt-0.5 text-xs text-zinc-400">{{ $masterProgram->conductor ?: 'Sin conductor asignado' }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3.5">
                                                    <span class="inline-flex items-center rounded-md border border-white/10 bg-white/5 px-2 py-0.5 font-mono text-xs text-zinc-200">
                                                        {{ $masterProgram->program_code ?: 'Sin código' }}
                                                    </span>
                                                    @if ($masterProgram->code_prefix)
                                                        <div class="mt-1 text-[11px] text-zinc-500">Prefijo: {{ $masterProgram->code_prefix }}</div>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3.5 text-xs">
                                                    @php
                                                        $emisionesActivas = $masterProgram->relationLoaded('emisiones')
                                                            ? $masterProgram->emisiones->where('activo', true)->values()
                                                            : $masterProgram->emisiones()->activas()->get();
                                                        $emisionesDelDia = $emisionesActivas->filter(fn ($em) => strtoupper(trim((string) $em->dia_semana)) === $dayKey)->values();
                                                    @endphp
                                                    @if ($emisionesDelDia->isNotEmpty())
                                                        <div class="space-y-1">
                                                            @foreach ($emisionesDelDia as $em)
                                                                <div class="flex items-center gap-1.5">
                                                                    <span class="font-semibold text-zinc-100">{{ $em->horaFormateada() }}</span>
                                                                    @if ($em->tipo === 'retransmision')
                                                                        <span class="inline-flex items-center rounded border border-white/15 bg-white/5 px-1 py-0.2 text-[9px] font-medium text-zinc-300">Podcast</span>
                                                                    @elseif ($em->tipo === 'en_vivo')
                                                                        <span class="inline-flex items-center rounded border border-red-500/30 bg-red-500/10 px-1 py-0.2 text-[9px] font-semibold text-red-400">En vivo</span>
                                                                    @else
                                                                        <span class="inline-flex items-center rounded border border-white/10 bg-white/[0.03] px-1 py-0.2 text-[9px] text-zinc-400">Normal</span>
                                                                    @endif
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <div class="font-medium text-zinc-200">
                                                            {{ $masterProgram->dia_transmision }} · {{ $masterProgram->hora_transmision ? substr((string) $masterProgram->hora_transmision, 0, 5) : 'Sin hora' }}
                                                        </div>
                                                    @endif
                                                    <div class="mt-0.5 text-zinc-500">{{ $masterProgram->timezone }}</div>
                                                </td>
                                                <td class="px-4 py-3.5">
                                                    @if ($emisionesActivas->isEmpty())
                                                        <span class="text-xs text-zinc-500">—</span>
                                                    @else
                                                        <div class="space-y-1.5">
                                                            @foreach ($emisionesActivas as $em)
                                                                @php $isSameDay = strtoupper(trim((string) $em->dia_semana)) === $dayKey; @endphp
                                                                <div class="flex flex-wrap items-center gap-1.5 text-xs {{ $isSameDay ? 'font-medium' : 'opacity-70' }}">
                                                                    @if ($em->tipo === 'en_vivo')
                                                                        <span class="inline-flex items-center rounded border border-red-500/30 bg-red-500/10 px-1.5 py-0.5 text-[10px] font-semibold text-red-400">En vivo</span>
                                                                    @elseif ($em->tipo === 'retransmision')
                                                                        <span class="inline-flex items-center rounded border border-white/15 bg-white/5 px-1.5 py-0.5 text-[10px] font-medium text-zinc-300">Podcast</span>
                                                                    @else
                                                                        <span class="inline-flex items-center rounded border border-white/10 bg-white/[0.03] px-1.5 py-0.5 text-[10px] text-zinc-400">Normal</span>
                                                                    @endif
                                                                    <span class="{{ $isSameDay ? 'text-white underline decoration-[#c32720] decoration-2 underline-offset-2' : 'text-zinc-300' }}">{{ ucfirst(substr(strtolower($em->dia_semana), 0, 3)) }} {{ $em->horaFormateada() }}</span>
                                                                    @if ($em->duracionRealFormateada())
                                                                        <span class="text-[11px] text-amber-400" title="Duración real del archivo">⏱ {{ $em->duracionRealFormateada() }}</span>
                                                                    @endif
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3.5">
                                                    @if ($masterProgram->activo)
                                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-400">
                                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                                            Activo
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-2.5 py-0.5 text-xs font-medium text-zinc-400">
                                                            <span class="h-1.5 w-1.5 rounded-full bg-zinc-500"></span>
                                                            Inactivo
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3.5 text-right">
                                                    <div class="inline-flex items-center justify-end gap-1.5">
                                                        <a
                                                            href="{{ route('admin.master-programs.edit', $masterProgram) }}"
                                                            class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-2.5 text-xs font-medium text-zinc-200 transition hover:border-white/20 hover:bg-white/10 hover:text-white"
                                                            title="Editar programa"
                                                        >
                                                            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 text-zinc-400" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" /></svg>
                                                            <span>Editar</span>
                                                        </a>
                                                        <button
                                                            type="button"
                                                            @click="$dispatch('open-invitation', { id: {{ $masterProgram->id }}, name: '{{ addslashes($masterProgram->name) }}', email: '{{ addslashes((string)($masterProgram->email_notificacion ?? '')) }}' })"
                                                            class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-2.5 text-xs font-medium text-zinc-200 transition hover:border-white/20 hover:bg-white/10 hover:text-white"
                                                            title="Generar enlace de invitación temporal"
                                                        >
                                                            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 text-zinc-400" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                                            <span>Invitar</span>
                                                        </button>
                                                        <form
                                                            action="{{ route('admin.master-programs.destroy', $masterProgram) }}"
                                                            method="POST"
                                                            data-confirm="¿Eliminar este programa de radio?"
                                                            data-confirm-title="Eliminar programa"
                                                            data-confirm-action="Eliminar"
                                                            data-confirm-tone="danger"
                                                        >
                                                            @csrf
                                                            @method('DELETE')
                                                            <button
                                                                type="submit"
                                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-white/10 bg-white/[0.02] text-zinc-400 transition hover:border-red-500/30 hover:bg-red-500/10 hover:text-red-400"
                                                                title="Eliminar programa"
                                                                aria-label="Eliminar programa"
                                                            >
                                                                <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18" /><path d="M8 6V4h8v2" /><path d="M19 6l-1 14H6L5 6" /><path d="M10 11v6" /><path d="M14 11v6" /></svg>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    </div>

    {{-- Modal Invitación Temporal --}}
    <div x-data="{
        show: false,
        loading: false,
        sendingEmail: false,
        openInvitation: false,
        programId: null,
        programName: '',
        programEmail: '',
        invitationId: null,
        expiresIn: 3,
        url: '',
        alertOpen: false,
        alertMessage: '',
        alertType: 'error',
        fields: {
            nombre: true,
            conductor: true,
            genero: true,
            descripcion: true,
            red_social1_url: false,
            red_social2_url: false,
            dia_transmision: true,
            hora_transmision: true,
            caratula_url: false
        },
        init() {
            window.addEventListener('open-invitation', (e) => {
                this.programId = e.detail.id;
                this.programName = e.detail.name;
                this.programEmail = e.detail.email || '';
                this.invitationId = null;
                this.url = '';
                this.openInvitation = true;
            });
        },
        async generate() {
            this.loading = true;
            this.url = '';
            let requestedFields = [];
            for (const [key, val] of Object.entries(this.fields)) {
                if (val) requestedFields.push(key);
            }
            try {
                const res = await fetch('/admin/master-programs/' + this.programId + '/invitations', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        expires_in_days: this.expiresIn,
                        requested_fields: requestedFields
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.url = data.url;
                    this.invitationId = data.invitation_id;
                } else {
                    this.showError('Error: ' + data.message);
                }
            } catch (err) {
                this.showError('Ocurrió un error al generar la invitación.');
            }
            this.loading = false;
        },
        copyUrl() {
            const input = document.getElementById('invitation-url');
            input.select();
            document.execCommand('copy');
            this.showError('¡Enlace copiado al portapapeles!', 'success');
        },
        async sendEmail() {
            if (!this.invitationId || !this.programEmail) return;
            this.sendingEmail = true;
            try {
                const res = await fetch('/admin/master-programs/' + this.programId + '/invitations/' + this.invitationId + '/send', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ email: this.programEmail })
                });
                const data = await res.json();
                if (data.success) {
                    this.showError('Correo enviado exitosamente.', 'success');
                } else {
                    this.showError('Error: ' + (data.message || 'No se encontró la ruta o recurso.'));
                }
            } catch (err) {
                this.showError('Ocurrió un error al enviar el correo.');
            }
            this.sendingEmail = false;
        },
        showError(message, type = 'error') {
            this.alertMessage = message;
            this.alertType = type;
            this.alertOpen = true;
        }
    }">
        <div x-show="openInvitation" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 px-4 py-6 backdrop-blur-sm">
            <div @click.away="openInvitation = false" class="w-full max-w-lg rounded-xl border border-white/10 bg-[#141417] p-6 shadow-2xl">
                <div class="mb-4 flex items-center justify-between border-b border-white/10 pb-3">
                    <h3 class="font-display text-lg font-bold uppercase tracking-wider text-white">Generar invitación temporal</h3>
                    <button @click="openInvitation = false" class="rounded-lg p-1 text-zinc-400 transition hover:bg-white/5 hover:text-white">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <p class="mb-4 text-sm text-zinc-400">Crea un enlace de un solo uso para que el productor actualice la ficha de: <strong class="font-semibold text-white" x-text="programName"></strong>.</p>

                <div class="mb-4">
                    <label class="mb-1.5 block text-xs font-medium text-zinc-300">Vigencia del enlace (días)</label>
                    <input type="number" x-model.number="expiresIn" min="1" max="30" class="h-10 w-full rounded-lg border border-white/10 bg-[#18181c] px-3 text-sm text-zinc-100 focus:border-[#c32720] focus:outline-none focus:ring-2 focus:ring-[#c32720]/30">
                </div>

                <div class="mb-5">
                    <label class="mb-2 block text-xs font-medium text-zinc-300">Campos habilitados para edición</label>
                    <div class="grid grid-cols-2 gap-2.5 rounded-lg border border-white/10 bg-[#101012] p-3.5 text-xs text-zinc-200">
                        <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" x-model="fields.nombre" class="h-4 w-4 rounded border-white/20 bg-zinc-900 accent-[#c32720]"> Nombre</label>
                        <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" x-model="fields.conductor" class="h-4 w-4 rounded border-white/20 bg-zinc-900 accent-[#c32720]"> Conductor</label>
                        <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" x-model="fields.genero" class="h-4 w-4 rounded border-white/20 bg-zinc-900 accent-[#c32720]"> Género</label>
                        <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" x-model="fields.descripcion" class="h-4 w-4 rounded border-white/20 bg-zinc-900 accent-[#c32720]"> Descripción</label>
                        <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" x-model="fields.caratula_url" class="h-4 w-4 rounded border-white/20 bg-zinc-900 accent-[#c32720]"> Carátula URL</label>
                        <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" x-model="fields.red_social1_url" class="h-4 w-4 rounded border-white/20 bg-zinc-900 accent-[#c32720]"> Red social 1</label>
                        <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" x-model="fields.red_social2_url" class="h-4 w-4 rounded border-white/20 bg-zinc-900 accent-[#c32720]"> Red social 2</label>
                        <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" x-model="fields.dia_transmision" class="h-4 w-4 rounded border-white/20 bg-zinc-900 accent-[#c32720]"> Día de emisión</label>
                        <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" x-model="fields.hora_transmision" class="h-4 w-4 rounded border-white/20 bg-zinc-900 accent-[#c32720]"> Hora de emisión</label>
                    </div>
                </div>

                <div x-show="!url" class="flex justify-end gap-2">
                    <button type="button" @click="openInvitation = false" class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/5 px-4 text-xs font-medium text-zinc-300 hover:bg-white/10">Cancelar</button>
                    <button type="button" @click="generate()" class="inline-flex h-9 items-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#d62d26]" :disabled="loading">
                        <span x-show="!loading">Generar enlace</span>
                        <span x-show="loading">Generando...</span>
                    </button>
                </div>

                <template x-if="url">
                    <div class="mt-4 border-t border-white/10 pt-4 space-y-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-zinc-300">Enlace generado</label>
                            <div class="flex items-center gap-2">
                                <input type="text" readonly :value="url" class="h-9 flex-1 rounded-lg border border-white/10 bg-[#0d0d0f] px-3 text-xs text-zinc-200" id="invitation-url">
                                <button type="button" @click="copyUrl" class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/10 px-3.5 text-xs font-medium text-white hover:bg-white/15">
                                    Copiar
                                </button>
                            </div>
                        </div>
                        <template x-if="programEmail">
                            <button type="button" :disabled="sendingEmail" @click="sendEmail" class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#d62d26]">
                                <span x-show="!sendingEmail">Enviar por correo a <span x-text="programEmail"></span></span>
                                <span x-show="sendingEmail">Enviando...</span>
                            </button>
                        </template>
                        <template x-if="!programEmail">
                            <p class="text-xs text-amber-400">Este programa no tiene correo configurado. Copia el enlace manualmente.</p>
                        </template>
                    </div>
                </template>
                <p class="mt-3 text-[11px] text-zinc-500">El enlace caduca en <span x-text="expiresIn"></span> días y es de un solo uso.</p>
            </div>
        </div>

        {{-- Alerta de estado --}}
        <div x-show="alertOpen" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 px-4 py-6 backdrop-blur-sm">
            <div @click.away="alertOpen = false" class="w-full max-w-sm rounded-xl border border-white/10 bg-[#141417] p-5 shadow-2xl">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="font-display text-base font-bold uppercase tracking-wider" :class="alertType === 'error' ? 'text-red-400' : 'text-emerald-400'" x-text="alertType === 'error' ? 'Aviso de error' : 'Operación completada'"></h3>
                    <button @click="alertOpen = false" class="text-zinc-400 hover:text-white">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <p class="mb-5 text-sm text-zinc-300" x-text="alertMessage"></p>
                <div class="flex justify-end">
                    <button type="button" @click="alertOpen = false" class="inline-flex h-8 items-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#d62d26]">
                        Aceptar
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
