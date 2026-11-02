<x-layouts.admin :title="'Códigos de promotor'">
    @php
        $hasActiveFilters = filled(request('search')) || filled(request('status'));
        $ownerTypeLabels = [
            'conductor' => 'Conductor',
            'persona' => 'Persona',
            'banda' => 'Banda',
        ];
    @endphp

    <div class="mx-auto max-w-6xl space-y-5 font-sans">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Bandas y comunidad · Referidos</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">Códigos de promotor</h1>
                <p class="mt-1 text-xs text-gray-400">Gestión de códigos de invitación y cupos de referidos para conductores, personas y bandas.</p>
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
                @endphp
                <article class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3 {{ $promoter->is_archived ? 'opacity-65' : '' }}">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-mono text-sm font-semibold text-white rounded-md border border-white/10 bg-[#151518] px-2.5 py-1">{{ $promoter->code }}</span>
                        @if ($promoter->is_archived)
                            <span class="inline-flex items-center rounded-md border border-white/10 bg-white/[0.04] px-2 py-0.5 text-[11px] font-medium text-gray-400">Archivado</span>
                        @elseif ($hasReachedLimit)
                            <span class="inline-flex items-center rounded-md border border-red-500/30 bg-red-500/10 px-2 py-0.5 text-[11px] font-medium text-red-300">Tope Alcanzado</span>
                        @else
                            <span class="inline-flex items-center rounded-md border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-300">Activo</span>
                        @endif
                    </div>

                    <div class="text-xs">
                        <div class="font-semibold text-white">{{ $promoter->owner_name }}</div>
                        <div class="text-gray-400 break-all">{{ $promoter->owner_email }}</div>
                    </div>

                    <dl class="grid grid-cols-3 gap-2 rounded-lg border border-white/5 bg-[#151518]/70 p-2.5 text-xs">
                        <div>
                            <dt class="text-[10px] text-gray-400">Tipo</dt>
                            <dd class="font-medium text-white">{{ $ownerTypeLabels[$promoter->owner_type] ?? ucfirst((string) $promoter->owner_type) }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] text-gray-400">Activadas / Tope</dt>
                            <dd class="font-semibold {{ $hasReachedLimit ? 'text-red-300' : 'text-white' }}">{{ $activeCount }} / {{ $promoter->max_bands ?? '∞' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] text-gray-400">Referidos</dt>
                            <dd class="font-medium text-white">{{ $promoter->referrals()->count() }}</dd>
                        </div>
                    </dl>

                    @if (!$promoter->is_archived)
                        <div class="space-y-2 pt-2 border-t border-white/5">
                            <form method="POST" action="{{ route('admin.talents.promoters.send-email', $promoter) }}">
                                @csrf
                                <button type="submit" class="inline-flex h-9 w-full items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                                    Enviar código por correo
                                </button>
                            </form>

                            <form method="POST" action="{{ route('admin.talents.promoters.expand', $promoter) }}" class="flex items-center gap-2">
                                @csrf
                                <input type="number" name="new_limit" value="{{ ($promoter->max_bands ?? 18) + 1 }}" min="{{ max($activeCount, 1) }}" aria-label="Nuevo tope de bandas" class="h-9 w-20 rounded-lg border border-white/10 bg-[#151518] px-2 text-center text-xs text-white" required>
                                <button type="submit" class="inline-flex h-9 flex-1 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                                    Ampliar tope
                                </button>
                            </form>

                            <form method="POST" action="{{ route('admin.talents.promoters.renew', $promoter) }}">
                                @csrf
                                <button type="submit" class="inline-flex h-9 w-full items-center justify-center rounded-lg bg-[#c32720] px-3 text-xs font-semibold text-white hover:bg-[#a8201a]" data-confirm="¿Renovar código? El anterior quedará archivado y el contador se reiniciará con un nuevo código." data-confirm-action="Renovar" data-confirm-tone="warning">
                                    Renovar código
                                </button>
                            </form>
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
                        <th scope="col" class="px-3 py-3">Titular y correo</th>
                        <th scope="col" class="px-3 py-3">Tipo</th>
                        <th scope="col" class="px-3 py-3 text-center">Activadas / Tope</th>
                        <th scope="col" class="px-3 py-3 text-center">Referidos</th>
                        <th scope="col" class="px-3 py-3">Estado</th>
                        <th scope="col" class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($promoters as $promoter)
                        @php
                            $activeCount = $promoter->activeBandsCount();
                            $hasReachedLimit = $promoter->hasReachedLimit();
                        @endphp
                        <tr class="hover:bg-white/[0.02] transition-colors {{ $promoter->is_archived ? 'opacity-60' : '' }}">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="font-mono text-xs font-semibold text-white rounded border border-white/10 bg-[#151518] px-2 py-1">{{ $promoter->code }}</span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="font-semibold text-white">{{ $promoter->owner_name }}</div>
                                <div class="text-[11px] text-gray-400">{{ $promoter->owner_email }}</div>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-300">
                                {{ $ownerTypeLabels[$promoter->owner_type] ?? ucfirst((string) $promoter->owner_type) }}
                            </td>
                            <td class="px-3 py-3 text-center whitespace-nowrap">
                                <span class="font-semibold {{ $hasReachedLimit && !$promoter->is_archived ? 'text-red-300' : 'text-white' }}">
                                    {{ $activeCount }} / {{ $promoter->max_bands ?? '∞' }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-center text-gray-300">{{ $promoter->referrals()->count() }}</td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                @if ($promoter->is_archived)
                                    <span class="inline-flex items-center rounded-md border border-white/10 bg-white/[0.04] px-2 py-0.5 text-[11px] font-medium text-gray-400">Archivado</span>
                                @elseif ($hasReachedLimit)
                                    <span class="inline-flex items-center rounded-md border border-red-500/30 bg-red-500/10 px-2 py-0.5 text-[11px] font-medium text-red-300">Tope Alcanzado</span>
                                @else
                                    <span class="inline-flex items-center rounded-md border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-300">Activo</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                @if (!$promoter->is_archived)
                                    <div class="inline-flex items-center justify-end gap-1.5">
                                        <form method="POST" action="{{ route('admin.talents.promoters.send-email', $promoter) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-white/10 bg-white/[0.04] px-2.5 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white" title="Enviar código por correo">
                                                Enviar correo
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.talents.promoters.expand', $promoter) }}" class="inline-flex items-center">
                                            @csrf
                                            <input type="number" name="new_limit" value="{{ ($promoter->max_bands ?? 18) + 1 }}" min="{{ max($activeCount, 1) }}" aria-label="Nuevo tope" class="h-8 w-14 rounded-l-lg border border-white/10 bg-[#151518] px-1.5 text-center text-xs text-white focus:border-[#c32720] focus:outline-none" required>
                                            <button type="submit" class="inline-flex h-8 items-center rounded-r-lg border border-l-0 border-white/10 bg-white/[0.04] px-2.5 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                                                Ampliar
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.talents.promoters.renew', $promoter) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-amber-500/30 bg-amber-500/10 px-2.5 text-xs font-medium text-amber-200 hover:bg-amber-500/20" data-confirm="¿Renovar código? El anterior quedará archivado y el contador se reiniciará con un nuevo código." data-confirm-action="Renovar" data-confirm-tone="warning">
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
