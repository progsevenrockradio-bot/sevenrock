<x-layouts.admin title="Páginas en espera - {{ $themeSettings->site_name ?? 'Seven Rock Radio' }}">
    <div class="space-y-5 font-sans">
        {{-- Cabecera compacta --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="flex items-center gap-2 text-[11px] font-medium uppercase tracking-wider text-gray-400">
                    <span>Sistema</span>
                    <span class="text-white/20">/</span>
                    <span class="text-[#c32720]">Páginas en espera</span>
                </div>
                <h1 class="mt-1 font-display text-2xl font-bold uppercase tracking-wide text-[#dcdcdc]">Páginas en espera y cuentas regresivas</h1>
                <p class="mt-1 text-xs text-gray-400 max-w-3xl">
                    Programa pantallas temporales de espera («Próximamente») sobre rutas públicas específicas antes de su apertura oficial.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.page-countdowns.create') }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-[#a61f19] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Nueva regla de espera</span>
                </a>
            </div>
        </header>

        {{-- Alertas de sesión --}}
        @if(session('success'))
            <div class="flex items-center gap-2.5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-xs text-emerald-200" role="status">
                <svg class="h-4 w-4 shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        {{-- Explicación del comportamiento real del middleware CheckPageCountdown --}}
        <section aria-label="Funcionamiento de las reglas de espera" class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-[#c32720]/15 text-[#c32720]">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                    <h2 class="font-display text-xs uppercase tracking-wider text-[#dcdcdc] font-semibold">Comportamiento real del bloqueo público y acceso de administradores</h2>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-md border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-[11px] font-medium text-amber-300">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Acceso directo admin: los administradores con sesión iniciada siempre ven la página real
                </span>
            </div>

            <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-4 text-xs">
                <div class="rounded-lg border border-white/5 bg-[#151518] p-3">
                    <div class="flex items-center gap-2 font-semibold text-amber-300">
                        <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                        <span>En espera activa (Bloqueada)</span>
                    </div>
                    <p class="mt-1 text-[11px] leading-relaxed text-gray-400">
                        Regla <strong>habilitada</strong> con <strong>fecha futura</strong>. El visitante público ve la pantalla «Próximamente» con cuenta regresiva.
                    </p>
                </div>

                <div class="rounded-lg border border-white/5 bg-[#151518] p-3">
                    <div class="flex items-center gap-2 font-semibold text-emerald-300">
                        <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                        <span>Abierta por fecha cumplida</span>
                    </div>
                    <p class="mt-1 text-[11px] leading-relaxed text-gray-400">
                        Cuando llega o se supera la fecha y hora programada, el acceso público se abre automáticamente sin necesidad de borrar la regla.
                    </p>
                </div>

                <div class="rounded-lg border border-white/5 bg-[#151518] p-3">
                    <div class="flex items-center gap-2 font-semibold text-sky-300">
                        <span class="h-2 w-2 rounded-full bg-sky-400"></span>
                        <span>Sin fecha (No bloquea)</span>
                    </div>
                    <p class="mt-1 text-[11px] leading-relaxed text-gray-400">
                        El middleware exige una fecha futura para activar la espera. Si la fecha queda vacía, la ruta permanece abierta al público.
                    </p>
                </div>

                <div class="rounded-lg border border-white/5 bg-[#151518] p-3">
                    <div class="flex items-center gap-2 font-semibold text-gray-300">
                        <span class="h-2 w-2 rounded-full bg-gray-500"></span>
                        <span>Inactiva (Deshabilitada)</span>
                    </div>
                    <p class="mt-1 text-[11px] leading-relaxed text-gray-400">
                        Si el interruptor de la regla está desactivado, la ruta funciona con normalidad para todos los visitantes aunque tenga fecha futura.
                    </p>
                </div>
            </div>
        </section>

        {{-- Listado escritorio (tabla compacta) --}}
        <section class="hidden md:block rounded-xl border border-white/10 bg-[#101012] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-white/10 bg-white/[0.02] text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                            <th class="py-3 px-4">Estado efectivo</th>
                            <th class="py-3 px-4">Ruta objetivo</th>
                            <th class="py-3 px-4">Título y mensaje de espera</th>
                            <th class="py-3 px-4">Apertura programada</th>
                            <th class="py-3 px-4 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-xs">
                        @forelse($countdowns as $countdown)
                            @php
                                $hasDate = $countdown->active_at !== null;
                                $isFuture = $hasDate && $countdown->active_at->isFuture();
                                $isBlocking = $countdown->is_enabled && $isFuture;
                            @endphp
                            <tr class="hover:bg-white/[0.02] transition-colors">
                                <td class="py-3 px-4 align-middle whitespace-nowrap">
                                    @if(!$countdown->is_enabled)
                                        <span class="inline-flex items-center gap-1.5 rounded-md border border-white/10 bg-white/5 px-2.5 py-1 text-[11px] font-medium text-gray-400">
                                            <span class="h-1.5 w-1.5 rounded-full bg-gray-500"></span>
                                            Inactiva (Abierta)
                                        </span>
                                    @elseif($isBlocking)
                                        <span class="inline-flex items-center gap-1.5 rounded-md border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-[11px] font-medium text-amber-300">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                                            En espera activa (Bloqueada)
                                        </span>
                                    @elseif($hasDate && !$isFuture)
                                        <span class="inline-flex items-center gap-1.5 rounded-md border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-[11px] font-medium text-emerald-300">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                            Abierta (Fecha cumplida)
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-md border border-sky-500/30 bg-sky-500/10 px-2.5 py-1 text-[11px] font-medium text-sky-300">
                                            <span class="h-1.5 w-1.5 rounded-full bg-sky-400"></span>
                                            Sin fecha (No bloquea)
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 align-middle">
                                    <code class="rounded border border-white/10 bg-black/40 px-2 py-0.5 font-mono text-xs text-[#dcdcdc]">
                                        /{{ ltrim($countdown->route_path, '/') }}
                                    </code>
                                    @if(str_ends_with($countdown->route_path, '*'))
                                        <span class="ml-1.5 text-[10px] text-gray-400">(comodín de subrutas)</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 align-middle max-w-md">
                                    <div class="font-semibold text-[#dcdcdc] truncate">{{ $countdown->title }}</div>
                                    @if($countdown->description)
                                        <div class="text-[11px] text-gray-400 truncate mt-0.5">{{ $countdown->description }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-4 align-middle whitespace-nowrap">
                                    @if($countdown->active_at)
                                        <div class="text-[#dcdcdc] font-mono text-xs">{{ $countdown->active_at->format('d/m/Y H:i') }}</div>
                                        <div class="text-[11px] {{ $isFuture ? 'text-amber-300/90' : 'text-gray-500' }}">
                                            {{ $countdown->active_at->diffForHumans() }}
                                        </div>
                                    @else
                                        <span class="text-[11px] text-gray-400">Sin fecha definida</span>
                                        <span class="block text-[10px] text-gray-500">Requiere fecha futura para bloquear</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 align-middle text-right whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.page-countdowns.edit', $countdown) }}"
                                           class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3 text-xs font-medium text-gray-200 transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus:ring-1 focus:ring-[#c32720]">
                                            <span>Editar</span>
                                        </a>
                                        <span class="h-4 w-px bg-white/10" aria-hidden="true"></span>
                                        <form action="{{ route('admin.page-countdowns.destroy', $countdown) }}" method="POST"
                                              onsubmit="return confirm('¿Seguro que deseas eliminar esta regla de espera? La ruta quedará accesible inmediatamente si estaba bloqueada.');"
                                              class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex h-8 items-center gap-1 rounded-lg border border-red-500/25 bg-red-500/10 px-2.5 text-xs font-medium text-red-300 transition-colors hover:bg-red-500/20 hover:text-red-200 focus:outline-none focus:ring-1 focus:ring-red-500">
                                                <span>Eliminar</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-10 px-4 text-center">
                                    <p class="text-sm font-medium text-gray-300">No hay páginas en espera configuradas</p>
                                    <p class="mt-1 text-xs text-gray-500">Todas las secciones públicas están abiertas actualmente.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Listado móvil (390 px tarjetas legibles) --}}
        <section class="md:hidden space-y-3" aria-label="Lista de páginas en espera en móvil">
            @forelse($countdowns as $countdown)
                @php
                    $hasDate = $countdown->active_at !== null;
                    $isFuture = $hasDate && $countdown->active_at->isFuture();
                    $isBlocking = $countdown->is_enabled && $isFuture;
                @endphp
                <article class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <code class="rounded border border-white/10 bg-black/40 px-2 py-0.5 font-mono text-xs text-[#dcdcdc] break-all">
                            /{{ ltrim($countdown->route_path, '/') }}
                        </code>
                        @if(!$countdown->is_enabled)
                            <span class="shrink-0 inline-flex items-center gap-1 rounded-md border border-white/10 bg-white/5 px-2 py-0.5 text-[11px] font-medium text-gray-400">
                                Inactiva
                            </span>
                        @elseif($isBlocking)
                            <span class="shrink-0 inline-flex items-center gap-1 rounded-md border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[11px] font-medium text-amber-300">
                                En espera activa
                            </span>
                        @elseif($hasDate && !$isFuture)
                            <span class="shrink-0 inline-flex items-center gap-1 rounded-md border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-300">
                                Abierta (Cumplida)
                            </span>
                        @else
                            <span class="shrink-0 inline-flex items-center gap-1 rounded-md border border-sky-500/30 bg-sky-500/10 px-2 py-0.5 text-[11px] font-medium text-sky-300">
                                Sin fecha (No bloquea)
                            </span>
                        @endif
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-[#dcdcdc]">{{ $countdown->title }}</h3>
                        @if($countdown->description)
                            <p class="mt-0.5 text-xs text-gray-400 line-clamp-2">{{ $countdown->description }}</p>
                        @endif
                    </div>

                    <div class="rounded-lg border border-white/5 bg-[#151518] px-3 py-2 text-xs flex items-center justify-between">
                        <span class="text-gray-400">Apertura:</span>
                        @if($countdown->active_at)
                            <span class="font-mono text-[#dcdcdc]">{{ $countdown->active_at->format('d/m/Y H:i') }} ({{ $countdown->active_at->diffForHumans() }})</span>
                        @else
                            <span class="text-gray-400">Sin fecha (no bloquea)</span>
                        @endif
                    </div>

                    <div class="flex items-center justify-between gap-2 pt-1 border-t border-white/5">
                        <a href="{{ route('admin.page-countdowns.edit', $countdown) }}"
                           class="inline-flex h-8 items-center justify-center rounded-lg border border-white/10 bg-white/5 px-3 text-xs font-medium text-gray-200 hover:bg-white/10">
                            Editar regla
                        </a>
                        <form action="{{ route('admin.page-countdowns.destroy', $countdown) }}" method="POST"
                              onsubmit="return confirm('¿Seguro que deseas eliminar esta regla de espera?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="inline-flex h-8 items-center justify-center rounded-lg border border-red-500/25 bg-red-500/10 px-3 text-xs font-medium text-red-300 hover:bg-red-500/20">
                                Eliminar
                            </button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-white/10 bg-[#101012] p-6 text-center">
                    <p class="text-sm font-medium text-gray-300">No hay páginas en espera configuradas</p>
                    <p class="mt-1 text-xs text-gray-500">Todas las secciones públicas están abiertas actualmente.</p>
                </div>
            @endforelse
        </section>
    </div>
</x-layouts.admin>
