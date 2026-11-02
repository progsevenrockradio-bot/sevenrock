<x-layouts.admin :title="'Bandas del Muro'">
    @php
        $planLabels = [
            'free' => 'Gratuito (Free)',
            'basic' => 'Básico',
            'pro' => 'Pro',
            'premium' => 'Premium',
        ];
        $statusLabels = [
            'active' => 'Activa',
            'pending' => 'Pendiente',
            'expired' => 'Vencida',
            'inactive' => 'Inactiva',
            'cancelled' => 'Cancelada',
        ];
        $statusStyles = [
            'active' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
            'pending' => 'border-amber-500/30 bg-amber-500/10 text-amber-300',
            'expired' => 'border-orange-500/30 bg-orange-500/10 text-orange-300',
            'inactive' => 'border-white/15 bg-white/[0.04] text-gray-300',
            'cancelled' => 'border-red-500/30 bg-red-500/10 text-red-300',
        ];
        $hasActiveFilters = filled($filters['search'] ?? '') || filled($filters['plan'] ?? '') || filled($filters['state'] ?? '');
    @endphp

    <div class="mx-auto max-w-6xl space-y-5 font-sans">
        {{-- Cabecera compacta --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Bandas y comunidad · Muro del Rock</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">Bandas del Muro</h1>
                <p class="mt-1 text-xs text-gray-400">Gestión de cuentas de bandas registradas, planes contratados, estado de suscripción y visibilidad pública.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.talents.media') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                    <span>Archivos de bandas</span>
                </a>
                <a href="{{ route('admin.talents.promoters.index') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                    <span>Códigos de promotor</span>
                </a>
            </div>
        </header>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-xs text-emerald-200" role="status">
                {{ session('status') }}
            </div>
        @endif

        {{-- Resumen de métricas reales --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-xl border border-white/10 bg-[#101012] p-3.5">
                <div class="text-[11px] font-medium text-gray-400">Total de bandas</div>
                <div class="mt-1 font-display text-2xl text-white">{{ $stats['total'] ?? 0 }}</div>
                <div class="mt-1 text-[11px] text-gray-400">Activas: <span class="text-emerald-300 font-medium">{{ $stats['active'] ?? 0 }}</span> · Inactivas: <span class="text-gray-300 font-medium">{{ $stats['inactive'] ?? 0 }}</span></div>
            </div>
            <div class="rounded-xl border border-white/10 bg-[#101012] p-3.5">
                <div class="text-[11px] font-medium text-gray-400">Ingresos mensuales</div>
                <div class="mt-1 font-display text-2xl text-white">€{{ number_format((float) ($stats['monthly_revenue'] ?? 0), 2) }}</div>
                <div class="mt-1 text-[11px] text-gray-400">Suscripciones activas registradas</div>
            </div>
            <div class="rounded-xl border border-white/10 bg-[#101012] p-3.5">
                <div class="text-[11px] font-medium text-gray-400">Plan más frecuente</div>
                <div class="mt-1 font-display text-2xl text-white">{{ $stats['most_popular_plan'] ?? 'N/D' }}</div>
                <div class="mt-1 text-[11px] text-gray-400">Destacadas: <span class="text-white font-medium">{{ $stats['featured'] ?? 0 }}</span> · Interacciones: <span class="text-white font-medium">{{ $stats['interactions'] ?? 0 }}</span></div>
            </div>
            <div class="rounded-xl border border-white/10 bg-[#101012] p-3.5">
                <div class="text-[11px] font-medium text-gray-400">Tamaño registrado en archivos</div>
                <div class="mt-1 font-display text-2xl text-white">{{ number_format((float) ($stats['storage_mb'] ?? 0), 2) }} MB</div>
                <div class="mt-1 text-[11px] text-gray-400">Suma de registros en base de datos</div>
            </div>
        </div>

        {{-- Barra de filtros --}}
        <form method="GET" action="{{ route('admin.talents.index') }}" class="rounded-xl border border-white/10 bg-[#101012] p-3.5">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_auto] items-end">
                <div>
                    <label for="filter-talent-search" class="mb-1 block text-xs font-medium text-gray-300">Buscar banda o correo</label>
                    <input
                        id="filter-talent-search"
                        type="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="Nombre de la banda o correo electrónico..."
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    >
                </div>
                <div>
                    <label for="filter-talent-plan" class="mb-1 block text-xs font-medium text-gray-300">Plan contratado</label>
                    <select
                        id="filter-talent-plan"
                        name="plan"
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    >
                        <option value="">Todos los planes</option>
                        @foreach ($planLabels as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['plan'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filter-talent-state" class="mb-1 block text-xs font-medium text-gray-300">Estado de suscripción</label>
                    <select
                        id="filter-talent-state"
                        name="state"
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    >
                        <option value="">Todos los estados</option>
                        <option value="active" @selected(($filters['state'] ?? '') === 'active')>Activa</option>
                        <option value="inactive" @selected(($filters['state'] ?? '') === 'inactive')>Inactiva o cancelada</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="inline-flex h-9 flex-1 sm:flex-initial items-center justify-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#a8201a] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                        Filtrar
                    </button>
                    @if ($hasActiveFilters)
                        <a href="{{ route('admin.talents.index') }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-300 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                            Restablecer filtros
                        </a>
                    @endif
                </div>
            </div>
        </form>

        {{-- Vista Móvil (390 px) --}}
        <div class="space-y-3 md:hidden">
            @forelse ($talents as $talent)
                @php
                    $isPubliclyVisible = !$talent->is_hidden && $talent->subscription_status === 'active';
                    $planLabel = $planLabels[$talent->plan] ?? ucfirst((string) $talent->plan);
                    $subLabel = $statusLabels[$talent->subscription_status] ?? ucfirst((string) $talent->subscription_status);
                    $subStyle = $statusStyles[$talent->subscription_status] ?? 'border-white/15 bg-white/[0.04] text-gray-300';
                @endphp
                <article class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3">
                    <div class="flex items-start gap-3">
                        <div class="h-11 w-11 shrink-0 overflow-hidden rounded-lg border border-white/10 bg-[#151518] flex items-center justify-center">
                            @if ($talent->logoUrl())
                                <img src="{{ $talent->logoUrl() }}" alt="{{ $talent->band_name }}" loading="lazy" class="h-full w-full object-cover">
                            @else
                                <span class="font-display text-xs uppercase text-gray-400">{{ mb_substr($talent->band_name, 0, 2) }}</span>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <h2 class="text-sm font-semibold text-white truncate">{{ $talent->band_name }}</h2>
                                @if ($talent->is_featured)
                                    <span class="inline-flex items-center rounded-md border border-amber-500/30 bg-amber-500/10 px-1.5 py-0.5 text-[10px] font-medium text-amber-300">Destacada</span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-400 break-all">{{ $talent->email }}</p>
                        </div>
                    </div>

                    {{-- Tres conceptos diferenciados: Plan · Estado de suscripción · Visibilidad pública --}}
                    <dl class="grid grid-cols-2 gap-2 rounded-lg border border-white/5 bg-[#151518]/70 p-2.5 text-xs">
                        <div>
                            <dt class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Plan</dt>
                            <dd class="mt-0.5 font-semibold text-white">{{ $planLabel }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Suscripción</dt>
                            <dd class="mt-0.5">
                                <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-medium {{ $subStyle }}">
                                    {{ $subLabel }}
                                </span>
                            </dd>
                        </div>
                        <div class="col-span-2 pt-1 border-t border-white/5 flex items-center justify-between">
                            <dt class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Visibilidad pública</dt>
                            <dd>
                                @if ($isPubliclyVisible)
                                    <span class="inline-flex items-center gap-1.5 rounded-md border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-300">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                        Visible en el Muro
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-md border border-white/10 bg-white/[0.04] px-2 py-0.5 text-[11px] font-medium text-gray-300">
                                        <span class="h-1.5 w-1.5 rounded-full bg-gray-500"></span>
                                        Oculta ({{ $talent->is_hidden ? 'ficha oculta' : 'suscripción no activa' }})
                                    </span>
                                @endif
                            </dd>
                        </div>
                    </dl>

                    <div class="flex items-center justify-between text-[11px] text-gray-400">
                        <span>Interacciones: <strong class="text-gray-200">{{ $talent->interacts }}</strong> · Archivos: <strong class="text-gray-200">{{ $talent->media_count ?? 0 }}</strong></span>
                        <span>Alta: {{ $talent->created_at?->format('d/m/Y') ?? 'N/D' }}</span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-white/5">
                        <a href="{{ route('admin.talents.edit', $talent) }}" class="inline-flex h-9 flex-1 items-center justify-center rounded-lg bg-[#c32720] px-3 text-xs font-semibold text-white hover:bg-[#a8201a] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                            Editar
                        </a>
                        <a href="{{ route('talents.show', ['bandName' => $talent->band_name]) }}" target="_blank" rel="noreferrer" class="inline-flex h-9 flex-1 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                            Ver perfil
                        </a>
                        <details class="relative w-full">
                            <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.03] px-3 text-xs font-medium text-gray-300 hover:bg-white/[0.07] hover:text-white">
                                <span>Más acciones de estado</span>
                                <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </summary>
                            <div class="mt-2 space-y-1.5 rounded-lg border border-white/10 bg-[#151518] p-2">
                                <form method="POST" action="{{ route('admin.talents.toggle-featured', $talent) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="flex h-8 w-full items-center justify-between rounded-md px-2.5 text-xs text-gray-200 hover:bg-white/[0.06]">
                                        <span>{{ $talent->is_featured ? 'Quitar destacado en el Muro' : 'Destacar en el Muro' }}</span>
                                        <span class="text-[10px] text-amber-300">{{ $talent->is_featured ? 'Activo' : '' }}</span>
                                    </button>
                                </form>
                                @if ($talent->subscription_status === 'active')
                                    <form method="POST" action="{{ route('admin.talents.suspend', $talent) }}" data-confirm="¿Suspender la suscripción de {{ $talent->band_name }}?" data-confirm-title="Suspender banda" data-confirm-action="Suspender" data-confirm-tone="danger">
                                        @csrf
                                        <button type="submit" class="flex h-8 w-full items-center rounded-md px-2.5 text-xs text-red-300 hover:bg-red-500/10">
                                            Suspender suscripción
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.talents.activate', $talent) }}">
                                        @csrf
                                        <button type="submit" class="flex h-8 w-full items-center rounded-md px-2.5 text-xs text-emerald-300 hover:bg-emerald-500/10">
                                            Activar suscripción
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </details>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-white/10 bg-[#101012] p-8 text-center text-xs text-gray-400">
                    No se encontraron bandas con los filtros seleccionados.
                </div>
            @endforelse
        </div>

        {{-- Vista Escritorio --}}
        <div class="hidden md:block rounded-xl border border-white/10 bg-[#101012] overflow-visible">
            <table class="min-w-full divide-y divide-white/10 text-left text-xs">
                <thead class="bg-white/[0.02] text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                    <tr>
                        <th scope="col" class="px-4 py-3">Banda</th>
                        <th scope="col" class="px-3 py-3">Plan</th>
                        <th scope="col" class="px-3 py-3">Estado de suscripción</th>
                        <th scope="col" class="px-3 py-3">Visibilidad pública</th>
                        <th scope="col" class="px-3 py-3">Actividad</th>
                        <th scope="col" class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($talents as $talent)
                        @php
                            $isPubliclyVisible = !$talent->is_hidden && $talent->subscription_status === 'active';
                            $planLabel = $planLabels[$talent->plan] ?? ucfirst((string) $talent->plan);
                            $subLabel = $statusLabels[$talent->subscription_status] ?? ucfirst((string) $talent->subscription_status);
                            $subStyle = $statusStyles[$talent->subscription_status] ?? 'border-white/15 bg-white/[0.04] text-gray-300';
                        @endphp
                        <tr class="hover:bg-white/[0.02] transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg border border-white/10 bg-[#151518] flex items-center justify-center">
                                        @if ($talent->logoUrl())
                                            <img src="{{ $talent->logoUrl() }}" alt="{{ $talent->band_name }}" loading="lazy" class="h-full w-full object-cover">
                                        @else
                                            <span class="font-display text-xs uppercase text-gray-400">{{ mb_substr($talent->band_name, 0, 2) }}</span>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-semibold text-white text-sm truncate">{{ $talent->band_name }}</span>
                                            @if ($talent->is_featured)
                                                <span class="inline-flex items-center rounded border border-amber-500/30 bg-amber-500/10 px-1.5 py-0.5 text-[10px] font-medium text-amber-300">Destacada</span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] text-gray-400 truncate max-w-[230px]">{{ $talent->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-md border border-white/10 bg-white/[0.04] px-2 py-0.5 text-xs font-medium text-gray-200">
                                    {{ $planLabel }}
                                </span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-medium {{ $subStyle }}">
                                    {{ $subLabel }}
                                </span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                @if ($isPubliclyVisible)
                                    <span class="inline-flex items-center gap-1.5 rounded-md border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-300">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                        Visible en el Muro
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-md border border-white/10 bg-white/[0.04] px-2 py-0.5 text-[11px] font-medium text-gray-300" title="{{ $talent->is_hidden ? 'Ficha marcada como oculta' : 'Requiere suscripción activa para mostrarse en el Muro' }}">
                                        <span class="h-1.5 w-1.5 rounded-full bg-gray-500"></span>
                                        Oculta ({{ $talent->is_hidden ? 'ficha oculta' : 'no activa' }})
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-300">
                                <div><span class="text-white font-medium">{{ $talent->interacts }}</span> interacciones · <span class="text-white font-medium">{{ $talent->media_count ?? 0 }}</span> archivos</div>
                                <div class="text-[11px] text-gray-400">Alta: {{ $talent->created_at?->format('d/m/Y') ?? 'N/D' }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.talents.edit', $talent) }}" class="inline-flex h-8 items-center rounded-lg border border-white/10 bg-white/[0.04] px-2.5 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                                        Editar
                                    </a>
                                    <a href="{{ route('talents.show', ['bandName' => $talent->band_name]) }}" target="_blank" rel="noreferrer" class="inline-flex h-8 items-center rounded-lg border border-white/10 bg-white/[0.03] px-2.5 text-xs font-medium text-gray-300 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                                        Ver perfil
                                    </a>
                                    <details class="relative inline-block text-left">
                                        <summary class="inline-flex h-8 cursor-pointer list-none items-center gap-1 rounded-lg border border-white/10 bg-white/[0.03] px-2.5 text-xs font-medium text-gray-300 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]" aria-label="Más acciones para {{ $talent->band_name }}">
                                            <span>Más</span>
                                            <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </summary>
                                        <div class="absolute right-0 z-30 mt-1.5 w-48 rounded-xl border border-white/10 bg-[#151518] p-1.5 shadow-xl">
                                            <form method="POST" action="{{ route('admin.talents.toggle-featured', $talent) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-left text-xs text-gray-200 hover:bg-white/[0.06]">
                                                    <span>{{ $talent->is_featured ? 'Quitar destacado' : 'Destacar en el Muro' }}</span>
                                                    @if ($talent->is_featured)
                                                        <span class="text-[10px] text-amber-300">Sí</span>
                                                    @endif
                                                </button>
                                            </form>
                                            <div class="my-1 border-t border-white/5"></div>
                                            @if ($talent->subscription_status === 'active')
                                                <form method="POST" action="{{ route('admin.talents.suspend', $talent) }}" data-confirm="¿Suspender la suscripción de {{ $talent->band_name }}?" data-confirm-title="Suspender suscripción" data-confirm-action="Suspender" data-confirm-tone="danger">
                                                    @csrf
                                                    <button type="submit" class="flex w-full items-center rounded-lg px-2.5 py-1.5 text-left text-xs text-red-300 hover:bg-red-500/10">
                                                        Suspender suscripción
                                                    </button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('admin.talents.activate', $talent) }}">
                                                    @csrf
                                                    <button type="submit" class="flex w-full items-center rounded-lg px-2.5 py-1.5 text-left text-xs text-emerald-300 hover:bg-emerald-500/10">
                                                        Activar suscripción
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </details>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-xs text-gray-400">
                                No se encontraron bandas con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($talents->hasPages())
            <div class="pt-2">
                {{ $talents->links() }}
            </div>
        @endif
    </div>
</x-layouts.admin>
