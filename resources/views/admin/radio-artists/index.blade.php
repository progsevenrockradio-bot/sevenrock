<x-layouts.admin :title="'Perfiles de artistas'">
    @php
        $hasActiveFilters = filled(request('search'));
    @endphp

    <div class="mx-auto max-w-6xl space-y-5 font-sans">
        {{-- Cabecera compacta --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Bandas y comunidad · Catálogo editorial del reproductor</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">Perfiles de artistas</h1>
                <p class="mt-1 text-xs text-gray-400">Fichas biográficas y recursos visuales consultados por el reproductor de radio (independientes de las cuentas de bandas del Muro).</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.radio-artists.create') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#c32720] px-3.5 text-xs font-semibold text-white hover:bg-[#a8201a] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Nuevo perfil</span>
                </a>
            </div>
        </header>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-xs text-emerald-200" role="status">
                {{ session('status') }}
            </div>
        @endif

        {{-- Barra de búsqueda y filtro --}}
        <form action="{{ route('admin.radio-artists.index') }}" method="GET" class="rounded-xl border border-white/10 bg-[#101012] p-3.5">
            <div class="grid gap-3 sm:grid-cols-[1fr_auto] items-end">
                <div>
                    <label for="filter-artist-search" class="mb-1 block text-xs font-medium text-gray-300">Buscar por nombre de artista o banda</label>
                    <input
                        id="filter-artist-search"
                        type="search"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Ej: Iron Maiden, Metallica, Soda Stereo..."
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    >
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="inline-flex h-9 flex-1 sm:flex-initial items-center justify-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#a8201a] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                        Filtrar
                    </button>
                    @if ($hasActiveFilters)
                        <a href="{{ route('admin.radio-artists.index') }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-300 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                            Restablecer filtros
                        </a>
                    @endif
                </div>
            </div>
        </form>

        {{-- Vista Móvil (390 px) --}}
        <div class="space-y-3 md:hidden">
            @forelse ($bandProfiles as $profile)
                @php
                    $imageUrl = $profile->normalizedImageUrl();
                    $summaryText = trim(strip_tags((string) ($profile->editorial_summary ?: $profile->biography)));
                @endphp
                <article class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0">
                            @if ($imageUrl)
                                <img
                                    src="{{ $imageUrl }}"
                                    alt="{{ $profile->name }}"
                                    loading="lazy"
                                    class="h-12 w-12 rounded-lg border border-white/10 bg-[#151518] object-cover"
                                    onerror="this.onerror=null; this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');"
                                >
                                <div class="hidden h-12 w-12 rounded-lg border border-white/10 bg-[#151518] text-gray-400 flex items-center justify-center">
                                    <span class="font-display text-xs uppercase">{{ mb_substr($profile->name, 0, 2) }}</span>
                                </div>
                            @else
                                <div class="h-12 w-12 rounded-lg border border-white/10 bg-[#151518] text-gray-400 flex items-center justify-center">
                                    <span class="font-display text-xs uppercase">{{ mb_substr($profile->name, 0, 2) }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <h2 class="text-sm font-semibold text-white truncate">{{ $profile->name }}</h2>
                            <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px] text-gray-400">
                                <span>{{ $profile->country ?: 'País no especificado' }}</span>
                                @if ($profile->genre)
                                    <span>·</span>
                                    <span class="rounded border border-white/10 bg-white/[0.04] px-1.5 py-0.5 text-gray-200">{{ $profile->genre }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <p class="text-xs text-gray-300 line-clamp-2 leading-relaxed">
                        {{ $summaryText !== '' ? \Illuminate\Support\Str::limit($summaryText, 130) : 'Sin resumen biográfico registrado.' }}
                    </p>

                    <div class="flex flex-wrap items-center gap-2 text-[11px] border-t border-white/5 pt-2">
                        @if ($imageUrl)
                            <a href="{{ $imageUrl }}" target="_blank" rel="noreferrer" class="inline-flex items-center gap-1 font-medium text-sky-300 hover:underline">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>Ver imagen</span>
                            </a>
                        @else
                            <span class="text-gray-500">Sin imagen</span>
                        @endif

                        @if (filled($profile->logo_path))
                            <span>·</span>
                            <a href="{{ $profile->logo_path }}" target="_blank" rel="noreferrer" class="inline-flex items-center gap-1 font-medium text-gray-300 hover:text-white hover:underline">
                                <span>Ver logo</span>
                            </a>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <a href="{{ route('admin.radio-artists.edit', $profile) }}" class="inline-flex h-9 flex-1 items-center justify-center rounded-lg bg-[#c32720] px-3 text-xs font-semibold text-white hover:bg-[#a8201a] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                            Editar
                        </a>
                        <form
                            action="{{ route('admin.radio-artists.destroy', $profile) }}"
                            method="POST"
                            data-confirm="¿Eliminar el perfil editorial de {{ $profile->name }}?"
                            data-confirm-title="Eliminar perfil de artista"
                            data-confirm-action="Eliminar"
                            data-confirm-tone="danger"
                        >
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg border border-red-500/30 bg-red-500/10 px-3 text-xs font-medium text-red-300 hover:bg-red-500/20 focus:outline-none focus:ring-2 focus:ring-red-500">
                                Eliminar
                            </button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-white/10 bg-[#101012] p-8 text-center text-xs text-gray-400">
                    No se encontraron perfiles de artistas con el filtro actual.
                </div>
            @endforelse
        </div>

        {{-- Vista Escritorio --}}
        <div class="hidden md:block rounded-xl border border-white/10 bg-[#101012] overflow-hidden">
            <table class="min-w-full divide-y divide-white/10 text-left text-xs">
                <thead class="bg-white/[0.02] text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                    <tr>
                        <th scope="col" class="px-4 py-3">Artista</th>
                        <th scope="col" class="px-3 py-3">Resumen biográfico</th>
                        <th scope="col" class="px-3 py-3">Imagen y logo</th>
                        <th scope="col" class="px-3 py-3">País y género</th>
                        <th scope="col" class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($bandProfiles as $profile)
                        @php
                            $imageUrl = $profile->normalizedImageUrl();
                            $summaryText = trim(strip_tags((string) ($profile->editorial_summary ?: $profile->biography)));
                        @endphp
                        <tr class="hover:bg-white/[0.02] transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="shrink-0">
                                        @if ($imageUrl)
                                            <img
                                                src="{{ $imageUrl }}"
                                                alt="{{ $profile->name }}"
                                                loading="lazy"
                                                class="h-10 w-10 rounded-lg border border-white/10 bg-[#151518] object-cover"
                                                onerror="this.onerror=null; this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');"
                                            >
                                            <div class="hidden h-10 w-10 rounded-lg border border-white/10 bg-[#151518] text-gray-400 flex items-center justify-center">
                                                <span class="font-display text-xs uppercase">{{ mb_substr($profile->name, 0, 2) }}</span>
                                            </div>
                                        @else
                                            <div class="h-10 w-10 rounded-lg border border-white/10 bg-[#151518] text-gray-400 flex items-center justify-center">
                                                <span class="font-display text-xs uppercase">{{ mb_substr($profile->name, 0, 2) }}</span>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-semibold text-white text-sm truncate max-w-[200px]">{{ $profile->name }}</div>
                                        <div class="text-[11px] text-gray-400">Fuente: {{ $profile->source ?: 'Seven Rock Radio' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3 max-w-md">
                                @if ($summaryText !== '')
                                    <p class="text-xs text-gray-300 line-clamp-2 leading-relaxed">
                                        {{ \Illuminate\Support\Str::limit($summaryText, 120) }}
                                    </p>
                                @else
                                    <span class="text-gray-500">Sin biografía registrada</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <div class="flex flex-col items-start gap-1">
                                    @if ($imageUrl)
                                        <a href="{{ $imageUrl }}" target="_blank" rel="noreferrer" class="inline-flex items-center gap-1 rounded-md border border-sky-500/30 bg-sky-500/10 px-2 py-0.5 text-[11px] font-medium text-sky-300 hover:bg-sky-500/20">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            <span>Ver imagen</span>
                                        </a>
                                    @else
                                        <span class="inline-flex items-center rounded-md border border-white/10 bg-white/[0.03] px-2 py-0.5 text-[11px] text-gray-400">
                                            Sin imagen
                                        </span>
                                    @endif

                                    @if (filled($profile->logo_path))
                                        <a href="{{ $profile->logo_path }}" target="_blank" rel="noreferrer" class="inline-flex items-center gap-1 text-[11px] text-gray-300 hover:text-white hover:underline">
                                            <span>Ver logo</span>
                                        </a>
                                    @else
                                        <span class="text-[10px] text-gray-500">Sin logo separado</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <div class="text-white font-medium">{{ $profile->country ?: 'No especificado' }}</div>
                                <div class="mt-0.5 text-[11px] text-gray-400">{{ $profile->genre ?: 'Sin género' }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.radio-artists.edit', $profile) }}" class="inline-flex h-8 items-center rounded-lg border border-white/10 bg-white/[0.04] px-2.5 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                                        Editar
                                    </a>
                                    <form
                                        action="{{ route('admin.radio-artists.destroy', $profile) }}"
                                        method="POST"
                                        data-confirm="¿Eliminar el perfil editorial de {{ $profile->name }}?"
                                        data-confirm-title="Eliminar perfil de artista"
                                        data-confirm-action="Eliminar"
                                        data-confirm-tone="danger"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-red-500/30 bg-red-500/10 px-2.5 text-xs font-medium text-red-300 hover:bg-red-500/20 focus:outline-none focus:ring-2 focus:ring-red-500">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-xs text-gray-400">
                                No se encontraron perfiles de artistas con el filtro actual.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($bandProfiles->hasPages())
            <div class="pt-2">
                {{ $bandProfiles->links() }}
            </div>
        @endif
    </div>
</x-layouts.admin>
