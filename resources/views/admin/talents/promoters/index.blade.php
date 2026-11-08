<x-layouts.admin :title="'Códigos de promotor · Piloto'">
    @php
        $hasActiveFilters = filled(request('search')) || filled(request('status'));
        $ownerTypeLabels = [
            'conductor' => 'Conductor / Locutor',
            'persona' => 'Colaborador',
            'banda' => 'Banda',
        ];
    @endphp

    <div class="mx-auto max-w-7xl space-y-5 font-sans">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Bandas y comunidad · Piloto de referidos y cortesías</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">Códigos de promotor</h1>
                <p class="mt-1 text-xs text-gray-400">Control exclusivo de códigos distribuidos por José, primeros pagos confirmados y recompensas del piloto.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.talents.index') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    <span>Volver a bandas</span>
                </a>
                <a href="{{ route('admin.talents.promoters.create') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#c32720] px-3.5 text-xs font-semibold text-white hover:bg-[#a8201a] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    <span>Nuevo código</span>
                </a>
            </div>
        </header>

        {{-- Alertas informativas --}}
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

        {{-- Tarjeta resumen de reglas del piloto --}}
        <div class="rounded-xl border border-white/10 bg-[#101012] p-4 text-xs space-y-2 text-gray-300">
            <div class="font-display text-xs uppercase tracking-wider text-white flex items-center gap-2">
                <span>📋 Reglas del Piloto 2026</span>
                <span class="rounded bg-white/10 px-1.5 py-0.5 text-[10px] text-gray-300">Exclusivo códigos de promotor</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-[11px] text-gray-400 pt-1 border-t border-white/5">
                <div>• <strong>Banda recomendadora:</strong> +1 mes de Básico por cada 3 bandas distintas con primer pago confirmado (máx. 2 meses).</div>
                <div>• <strong>Cortesía colaboradores:</strong> José puede conceder 2 meses iniciales de Básico para su proyecto/banda.</div>
                <div>• <strong>Locutores sin banda:</strong> Código y seguimiento de resultados sin dinero ni cuentas ficticias.</div>
            </div>
        </div>

        {{-- Filtros --}}
        <form method="GET" action="{{ route('admin.talents.promoters.index') }}" class="rounded-xl border border-white/10 bg-[#101012] p-3.5">
            <div class="grid gap-3 sm:grid-cols-[1.4fr_1fr_auto] items-end">
                <div>
                    <label for="filter-promoter-search" class="mb-1 block text-xs font-medium text-gray-300">Buscar código, titular o correo</label>
                    <input
                        id="filter-promoter-search"
                        type="search"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Código, nombre o correo electrónico..."
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    >
                </div>
                <div>
                    <label for="filter-promoter-status" class="mb-1 block text-xs font-medium text-gray-300">Estado del código</label>
                    <select
                        id="filter-promoter-status"
                        name="status"
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    >
                        <option value="">Todos los estados</option>
                        <option value="active" @selected(request('status') === 'active')>Activos</option>
                        <option value="archived" @selected(request('status') === 'archived')>Archivados</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="inline-flex h-9 flex-1 sm:flex-initial items-center justify-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#a8201a] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                        Filtrar
                    </button>
                    @if ($hasActiveFilters)
                        <a href="{{ route('admin.talents.promoters.index') }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-300 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                            Restablecer filtros
                        </a>
                    @endif
                </div>
            </div>
        </form>

        {{-- Vista Móvil (390 px) --}}
        <div class="space-y-3 md:hidden">
            @forelse ($promoters as $promoter)
                @php
                    $activeCount = $promoter->activeBandsCount();
                    $hasReachedLimit = $promoter->hasReachedLimit();
                    $metrics = $promoter->pilotMetrics();
                @endphp
                <article class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3 {{ $promoter->is_archived ? 'opacity-65' : '' }}">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-mono text-sm font-semibold text-white rounded-md border border-white/10 bg-[#151518] px-2.5 py-1">{{ $promoter->code }}</span>
                        <div class="flex items-center gap-1.5 flex-wrap justify-end">
                            <span class="rounded bg-white/10 px-2 py-0.5 text-[10px] text-gray-300">{{ $promoter->campaign ?: 'pilot_2026' }}</span>
                            @if ($promoter->is_archived)
                                <span class="rounded-md border border-white/10 bg-white/[0.04] px-2 py-0.5 text-[11px] font-medium text-gray-400">Archivado</span>
                            @elseif ($hasReachedLimit)
                                <span class="rounded-md border border-red-500/30 bg-red-500/10 px-2 py-0.5 text-[11px] font-medium text-red-300">Tope</span>
                            @else
                                <span class="rounded-md border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-300">Activo</span>
                            @endif
                        </div>
                    </div>

                    <div class="text-xs">
                        <div class="font-semibold text-white">{{ $promoter->owner_name }}</div>
                        <div class="text-gray-400 break-all">{{ $promoter->owner_email }}</div>
                        <div class="text-[11px] text-gray-400 mt-1">
                            Tipo: <span class="text-gray-200">{{ $ownerTypeLabels[$promoter->owner_type] ?? ucfirst((string) $promoter->owner_type) }}</span>
                        </div>
                    </div>

                    {{-- Banda asociada o locutor --}}
                    <div class="rounded-lg border border-white/5 bg-[#151518] p-2.5 text-xs">
                        <div class="text-[10px] text-gray-400 uppercase tracking-wider">Banda asociada para recompensas</div>
                        @if ($promoter->assignedTalent)
                            <div class="mt-1 flex items-center justify-between">
                                <a href="{{ route('admin.talents.edit', $promoter->assignedTalent) }}" class="font-medium text-white hover:underline">
                                    {{ $promoter->assignedTalent->band_name }}
                                </a>
                                <span class="rounded bg-white/10 px-1.5 py-0.5 text-[10px] text-gray-300">
                                    Plan {{ ucfirst($promoter->assignedTalent->plan) }}
                                </span>
                            </div>
                        @else
                            <div class="mt-1 text-gray-400 italic">Sin banda asociada (Locutor / Difusión)</div>
                        @endif
                    </div>

                    {{-- Métricas del Piloto --}}
                    <dl class="grid grid-cols-2 gap-2 rounded-lg border border-white/5 bg-[#151518]/70 p-2.5 text-xs">
                        <div>
                            <dt class="text-[10px] text-gray-400">Primeros pagos</dt>
                            <dd class="font-semibold text-white">{{ $metrics['paid_count'] }} bandas</dd>
                            <div class="text-[10px] text-gray-400">Progreso: {{ $metrics['paid_count'] % 3 }}/3</div>
                        </div>
                        <div>
                            <dt class="text-[10px] text-gray-400">Recompensas piloto</dt>
                            <dd class="font-semibold text-white">{{ $metrics['granted_rewards'] }} / {{ $metrics['max_rewards'] }} meses</dd>
                            <div class="text-[10px] text-gray-400">Restante: {{ $metrics['remaining_rewards'] }}</div>
                        </div>
                    </dl>

                    {{-- Badges de estado especial --}}
                    @if ($metrics['has_flagged_review'])
                        <div class="rounded-lg border border-red-500/30 bg-red-500/10 p-2 text-xs text-red-200">
                            ⚠️ <strong>Revisión requerida:</strong> Un pago de banda referida fue marcado como anulado/reembolsado.
                        </div>
                    @endif

                    @if ($metrics['has_pro_pending'])
                        <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-2 text-xs text-amber-200">
                            ⏸️ <strong>Pendiente de resolución:</strong> La banda asociada está en Plan Pro. Recompensa de 1 mes retenida sin degradar cuenta.
                        </div>
                    @endif

                    {{-- Acciones --}}
                    @if (!$promoter->is_archived)
                        <div class="space-y-2 pt-2 border-t border-white/5">
                            @if ($promoter->assigned_talent_id)
                                <form method="POST" action="{{ route('admin.talents.promoters.grant-courtesy', $promoter) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex h-9 w-full items-center justify-center rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 text-xs font-medium text-emerald-200 hover:bg-emerald-500/20" data-confirm="¿Conceder 2 meses de cortesía inicial de Básico a la banda asociada '{{ $promoter->assignedTalent?->band_name }}'?" data-confirm-action="Conceder" data-confirm-tone="primary">
                                        Conceder cortesía inicial (2 meses Básico)
                                    </button>
                                </form>
                            @endif

                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('admin.talents.promoters.send-email', $promoter) }}" class="flex-1">
                                    @csrf
                                    <button type="submit" class="inline-flex h-9 w-full items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                                        Enviar correo
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.talents.promoters.renew', $promoter) }}" class="flex-1">
                                    @csrf
                                    <button type="submit" class="inline-flex h-9 w-full items-center justify-center rounded-lg bg-[#c32720] px-3 text-xs font-semibold text-white hover:bg-[#a8201a]" data-confirm="¿Renovar código? El anterior quedará archivado y el contador se reiniciará con un nuevo código." data-confirm-action="Renovar" data-confirm-tone="warning">
                                        Renovar
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                </article>
            @empty
                <div class="rounded-xl border border-white/10 bg-[#101012] p-8 text-center text-xs text-gray-400">
                    No hay códigos de promotor registrados.
                </div>
            @endforelse
        </div>

        {{-- Vista Escritorio --}}
        <div class="hidden md:block rounded-xl border border-white/10 bg-[#101012] overflow-hidden">
            <table class="min-w-full divide-y divide-white/10 text-left text-xs">
                <thead class="bg-white/[0.02] text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                    <tr>
                        <th scope="col" class="px-4 py-3">Código</th>
                        <th scope="col" class="px-3 py-3">Titular y Campaña</th>
                        <th scope="col" class="px-3 py-3">Banda asociada</th>
                        <th scope="col" class="px-3 py-3 text-center">Primeros pagos</th>
                        <th scope="col" class="px-3 py-3 text-center">Recompensas</th>
                        <th scope="col" class="px-3 py-3">Estado / Avisos</th>
                        <th scope="col" class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($promoters as $promoter)
                        @php
                            $activeCount = $promoter->activeBandsCount();
                            $hasReachedLimit = $promoter->hasReachedLimit();
                            $metrics = $promoter->pilotMetrics();
                        @endphp
                        <tr class="hover:bg-white/[0.02] transition-colors {{ $promoter->is_archived ? 'opacity-60' : '' }}">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="font-mono text-xs font-semibold text-white rounded border border-white/10 bg-[#151518] px-2 py-1">{{ $promoter->code }}</span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="font-semibold text-white">{{ $promoter->owner_name }}</div>
                                <div class="text-[11px] text-gray-400">{{ $promoter->owner_email }}</div>
                                <div class="mt-0.5 flex items-center gap-1.5">
                                    <span class="rounded bg-white/5 px-1.5 py-0.2 text-[10px] text-gray-400">{{ $ownerTypeLabels[$promoter->owner_type] ?? ucfirst((string) $promoter->owner_type) }}</span>
                                    <span class="rounded bg-white/10 px-1.5 py-0.2 text-[10px] text-gray-300 font-mono">{{ $promoter->campaign ?: 'pilot_2026' }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                @if ($promoter->assignedTalent)
                                    <div>
                                        <a href="{{ route('admin.talents.edit', $promoter->assignedTalent) }}" class="font-medium text-white hover:underline">
                                            {{ $promoter->assignedTalent->band_name }}
                                        </a>
                                    </div>
                                    <div class="text-[10px] text-gray-400">Plan {{ ucfirst($promoter->assignedTalent->plan) }}</div>
                                @else
                                    <span class="text-gray-400 italic">Sin banda (Locutor)</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center whitespace-nowrap">
                                <div class="font-semibold text-white">{{ $metrics['paid_count'] }} bandas</div>
                                <div class="text-[10px] text-gray-400">
                                    @if ($metrics['paid_count'] >= 6)
                                        <span class="text-emerald-400 font-medium">Completado (6/6)</span>
                                    @else
                                        Progreso: {{ $metrics['paid_count'] % 3 }}/3 (faltan {{ $metrics['needed'] }})
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-3 text-center whitespace-nowrap">
                                <div class="font-semibold {{ $metrics['granted_rewards'] >= 2 ? 'text-emerald-400' : 'text-white' }}">
                                    {{ $metrics['granted_rewards'] }} / {{ $metrics['max_rewards'] }} meses
                                </div>
                                <div class="text-[10px] text-gray-400">
                                    Cupo bandas: {{ $activeCount }} / {{ $promoter->max_bands ?? '∞' }}
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex flex-col gap-1 items-start">
                                    @if ($promoter->is_archived)
                                        <span class="inline-flex items-center rounded-md border border-white/10 bg-white/[0.04] px-2 py-0.5 text-[10px] font-medium text-gray-400">Archivado</span>
                                    @elseif ($hasReachedLimit)
                                        <span class="inline-flex items-center rounded-md border border-red-500/30 bg-red-500/10 px-2 py-0.5 text-[10px] font-medium text-red-300">Tope Cupo</span>
                                    @else
                                        <span class="inline-flex items-center rounded-md border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-medium text-emerald-300">Activo</span>
                                    @endif

                                    @if ($metrics['has_flagged_review'])
                                        <span class="inline-flex items-center rounded-md border border-red-500/30 bg-red-500/20 px-1.5 py-0.5 text-[10px] font-semibold text-red-300" title="Pago anulado o reembolsado">
                                            ⚠️ Revisión requerida
                                        </span>
                                    @endif

                                    @if ($metrics['has_pro_pending'])
                                        <span class="inline-flex items-center rounded-md border border-amber-500/30 bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-semibold text-amber-300" title="Banda en Plan Pro pendiente de resolución">
                                            ⏸️ Pendiente (Plan Pro)
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                @if (!$promoter->is_archived)
                                    <div class="inline-flex items-center justify-end gap-1.5">
                                        @if ($promoter->assigned_talent_id)
                                            <form method="POST" action="{{ route('admin.talents.promoters.grant-courtesy', $promoter) }}">
                                                @csrf
                                                <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-2 text-[11px] font-medium text-emerald-200 hover:bg-emerald-500/20" title="Conceder 2 meses iniciales de cortesía" data-confirm="¿Conceder 2 meses de cortesía inicial a '{{ $promoter->assignedTalent?->band_name }}'?" data-confirm-action="Conceder" data-confirm-tone="primary">
                                                    +2m Cortesía
                                                </button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('admin.talents.promoters.send-email', $promoter) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-white/10 bg-white/[0.04] px-2 text-[11px] font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white" title="Enviar código por correo">
                                                Correo
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.talents.promoters.expand', $promoter) }}" class="inline-flex items-center">
                                            @csrf
                                            <input type="number" name="new_limit" value="{{ ($promoter->max_bands ?? 18) + 1 }}" min="{{ max($activeCount, 1) }}" aria-label="Nuevo tope" class="h-8 w-12 rounded-l-lg border border-white/10 bg-[#151518] px-1 text-center text-xs text-white focus:border-[#c32720] focus:outline-none" required>
                                            <button type="submit" class="inline-flex h-8 items-center rounded-r-lg border border-l-0 border-white/10 bg-white/[0.04] px-2 text-[11px] font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                                                Ampliar
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.talents.promoters.renew', $promoter) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-amber-500/30 bg-amber-500/10 px-2 text-[11px] font-medium text-amber-200 hover:bg-amber-500/20" data-confirm="¿Renovar código? El anterior quedará archivado y el contador se reiniciará con un nuevo código." data-confirm-action="Renovar" data-confirm-tone="warning">
                                                Renovar
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-[11px] text-gray-500">Sin acciones</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-xs text-gray-400">
                                No hay códigos de promotor registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($promoters->hasPages())
            <div class="pt-2">
                {{ $promoters->links() }}
            </div>
        @endif
    </div>
</x-layouts.admin>
