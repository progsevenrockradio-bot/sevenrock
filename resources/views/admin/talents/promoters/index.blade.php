<x-layouts.admin :title="'Códigos de Promotor'">
    <section class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="font-display text-xs uppercase tracking-[.18em] text-[#9a9a9a]">Marketing</div>
                <h1 class="mt-2 font-display text-2xl sm:text-3xl uppercase tracking-[.12em] text-white">Códigos de Promotor</h1>
            </div>
            <a href="{{ route('admin.talents.promoters.create') }}" class="lucille-button-solid text-center">Nuevo Código</a>
        </div>

        @if (session('success'))
            <div class="border border-[#1e4d2b] bg-[rgba(16,64,30,.18)] px-4 py-3 text-sm text-[#b8e6c3]">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="border border-[#721c24] bg-[rgba(114,28,36,.18)] px-4 py-3 text-sm text-[#f5c6cb]">
                {{ session('error') }}
            </div>
        @endif

        <div class="border border-white/10 bg-[#10161b] p-4 sm:p-6">
            <form method="GET" action="{{ route('admin.talents.promoters.index') }}" class="grid gap-3 md:grid-cols-[1fr_auto_auto]">
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Buscar por código, nombre o email" class="lucille-product-field w-full">
                <select name="status" class="lucille-product-field w-full md:w-auto">
                    <option value="">Todos los estados</option>
                    <option value="active" @selected(request('status') === 'active')>Activos</option>
                    <option value="archived" @selected(request('status') === 'archived')>Archivados</option>
                </select>
                <button type="submit" class="lucille-button-solid w-full md:w-auto text-center">Filtrar</button>
            </form>
        </div>

        <!-- Vista Móvil (Tarjetas) -->
        <div class="space-y-3 sm:hidden">
            @foreach ($promoters as $promoter)
                @php
                    $activeCount = $promoter->activeBandsCount();
                    $hasReachedLimit = $promoter->hasReachedLimit();
                @endphp
                <div class="border border-white/10 bg-[#10161b] p-4 text-sm text-[#d8d8d8]">
                    <div class="flex items-center justify-between">
                        <div class="font-display text-xl text-white">{{ $promoter->code }}</div>
                        @if ($promoter->is_archived)
                            <span class="px-2 py-1 bg-gray-800 text-gray-400 text-xs rounded uppercase tracking-wider">Archivado</span>
                        @elseif ($hasReachedLimit)
                            <span class="px-2 py-1 bg-red-900/50 text-red-400 text-xs rounded uppercase tracking-wider border border-red-500/30">Tope Alcanzado</span>
                        @else
                            <span class="px-2 py-1 bg-green-900/50 text-green-400 text-xs rounded uppercase tracking-wider border border-green-500/30">Activo</span>
                        @endif
                    </div>
                    <div class="mt-3">
                        <span class="text-xs text-[#8b8b8b]">Dueño:</span> <span class="text-white font-semibold">{{ $promoter->owner_name }}</span>
                        <br><span class="text-xs text-[#8b8b8b] break-all">{{ $promoter->owner_email }}</span>
                    </div>
                    <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                        <div><span class="text-[#8b8b8b]">Tipo:</span> {{ ucfirst($promoter->owner_type) }}</div>
                        <div><span class="text-[#8b8b8b]">Referidos:</span> {{ $promoter->referrals()->count() }}</div>
                        <div class="col-span-2">
                            <span class="text-[#8b8b8b]">Activadas:</span> 
                            <span class="{{ $hasReachedLimit ? 'text-red-400 font-bold' : 'text-white' }}">
                                {{ $activeCount }} / {{ $promoter->max_bands ?? '∞' }}
                            </span>
                        </div>
                    </div>

                    @if (!$promoter->is_archived)
                        <div class="mt-4 flex flex-col gap-2">
                            <form method="POST" action="{{ route('admin.talents.promoters.send-email', $promoter) }}">
                                @csrf
                                <button type="submit" class="lucille-button w-full text-center">Enviar Código por Correo</button>
                            </form>

                            <form method="POST" action="{{ route('admin.talents.promoters.expand', $promoter) }}" class="flex gap-2">
                                @csrf
                                <input type="number" name="new_limit" value="{{ ($promoter->max_bands ?? 18) + 1 }}" min="{{ max($activeCount, 1) }}" class="lucille-product-field w-20 text-center !p-2" required>
                                <button type="submit" class="lucille-button flex-1 text-center">Ampliar Tope</button>
                            </form>

                            <form method="POST" action="{{ route('admin.talents.promoters.renew', $promoter) }}">
                                @csrf
                                <button type="submit" class="lucille-button-solid w-full text-center" data-confirm="¿Renovar código? El anterior quedará archivado y el contador se reiniciará con un nuevo código." data-confirm-action="Renovar" data-confirm-tone="warning">Renovar Código</button>
                            </form>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- Vista Escritorio (Tabla) -->
        <div class="hidden sm:block overflow-x-auto border border-white/10 bg-[#10161b]">
            <table class="min-w-full divide-y divide-white/10 text-left text-sm">
                <thead class="bg-black/20 text-xs uppercase tracking-[.18em] text-[#9a9a9a]">
                    <tr>
                        <th class="px-4 py-3">Código</th>
                        <th class="px-4 py-3">Dueño</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3 text-center">Activadas / Tope</th>
                        <th class="px-4 py-3 text-center">Referidos</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @foreach ($promoters as $promoter)
                        @php
                            $activeCount = $promoter->activeBandsCount();
                            $hasReachedLimit = $promoter->hasReachedLimit();
                        @endphp
                        <tr class="text-[#d8d8d8] {{ $promoter->is_archived ? 'opacity-60' : '' }}">
                            <td class="px-4 py-4 font-display text-lg text-white whitespace-nowrap">{{ $promoter->code }}</td>
                            <td class="px-4 py-4">
                                <div class="font-semibold text-white">{{ $promoter->owner_name }}</div>
                                <div class="text-xs text-[#8b8b8b]">{{ $promoter->owner_email }}</div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">{{ ucfirst($promoter->owner_type) }}</td>
                            <td class="px-4 py-4 text-center whitespace-nowrap">
                                <span class="{{ $hasReachedLimit && !$promoter->is_archived ? 'text-red-400 font-bold' : 'text-white' }}">
                                    {{ $activeCount }} / {{ $promoter->max_bands ?? '∞' }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-center">{{ $promoter->referrals()->count() }}</td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                @if ($promoter->is_archived)
                                    <span class="px-2 py-1 bg-gray-800 text-gray-400 text-xs rounded uppercase tracking-wider">Archivado</span>
                                @elseif ($hasReachedLimit)
                                    <span class="px-2 py-1 bg-red-900/50 text-red-400 text-xs rounded uppercase tracking-wider border border-red-500/30">Tope Alcanzado</span>
                                @else
                                    <span class="px-2 py-1 bg-green-900/50 text-green-400 text-xs rounded uppercase tracking-wider border border-green-500/30">Activo</span>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                @if (!$promoter->is_archived)
                                    <div class="flex items-center gap-2">
                                        <form method="POST" action="{{ route('admin.talents.promoters.send-email', $promoter) }}" title="Enviar Código por Correo">
                                            @csrf
                                            <button type="submit" class="lucille-button !p-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.talents.promoters.expand', $promoter) }}" class="flex items-center">
                                            @csrf
                                            <input type="number" name="new_limit" value="{{ ($promoter->max_bands ?? 18) + 1 }}" min="{{ max($activeCount, 1) }}" class="lucille-product-field w-16 text-center !p-1 !h-auto text-sm border-r-0 rounded-r-none" required title="Nuevo Tope">
                                            <button type="submit" class="lucille-button rounded-l-none !p-2" title="Ampliar Tope">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.talents.promoters.renew', $promoter) }}" title="Renovar Código">
                                            @csrf
                                            <button type="submit" class="lucille-button-solid !p-2" data-confirm="¿Renovar código? El anterior quedará archivado y el contador se reiniciará con un nuevo código." data-confirm-action="Renovar" data-confirm-tone="warning">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div>
            {{ $promoters->links() }}
        </div>
    </section>
</x-layouts.admin>
