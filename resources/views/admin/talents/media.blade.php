<x-layouts.admin :title="'Archivos de bandas'">
    @php
        $typeLabels = [
            'photo' => 'Fotografía',
            'mp3' => 'Audio MP3',
            'document' => 'Documento PDF',
            'video' => 'Vídeo',
        ];
        $typeStyles = [
            'photo' => 'border-sky-500/30 bg-sky-500/10 text-sky-300',
            'mp3' => 'border-amber-500/30 bg-amber-500/10 text-amber-300',
            'document' => 'border-blue-500/30 bg-blue-500/10 text-blue-300',
            'video' => 'border-purple-500/30 bg-purple-500/10 text-purple-300',
        ];
        $hasActiveFilters = filled($filters['search'] ?? '') || filled($filters['type'] ?? '');
    @endphp

    <div class="mx-auto max-w-6xl space-y-5 font-sans">
        {{-- Cabecera compacta --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Bandas y comunidad · Biblioteca multimedia</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">Archivos de bandas</h1>
                <p class="mt-1 text-xs text-gray-400">Supervisión de fotografías, pistas MP3, vídeos y documentos subidos por las bandas del Muro del Rock.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.talents.index') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    <span>Ver bandas del Muro</span>
                </a>
            </div>
        </header>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-xs text-emerald-200" role="status">
                {{ session('status') }}
            </div>
        @endif

        {{-- Barra de filtros --}}
        <form method="GET" action="{{ route('admin.talents.media') }}" class="rounded-xl border border-white/10 bg-[#101012] p-3.5">
            <div class="grid gap-3 sm:grid-cols-[1.4fr_1fr_auto] items-end">
                <div>
                    <label for="filter-media-search" class="mb-1 block text-xs font-medium text-gray-300">Buscar por banda propietaria</label>
                    <input
                        id="filter-media-search"
                        type="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="Nombre de la banda..."
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    >
                </div>
                <div>
                    <label for="filter-media-type" class="mb-1 block text-xs font-medium text-gray-300">Tipo de archivo</label>
                    <select
                        id="filter-media-type"
                        name="type"
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    >
                        <option value="">Todos los tipos</option>
                        @foreach ($typeLabels as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['type'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="inline-flex h-9 flex-1 sm:flex-initial items-center justify-center rounded-lg bg-[#c32720] px-4 text-xs font-semibold text-white hover:bg-[#a8201a] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                        Filtrar
                    </button>
                    @if ($hasActiveFilters)
                        <a href="{{ route('admin.talents.media') }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-300 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                            Restablecer filtros
                        </a>
                    @endif
                </div>
            </div>
        </form>

        {{-- Vista Móvil (390 px) --}}
        <div class="space-y-3 md:hidden">
            @forelse ($media as $item)
                @php
                    $typeLabel = $typeLabels[$item->type] ?? ucfirst((string) $item->type);
                    $typeStyle = $typeStyles[$item->type] ?? 'border-white/15 bg-white/[0.04] text-gray-300';
                    $isPrivateDocument = $item->type === 'document' || $item->disk === 'r2_private';
                    $sizeFormatted = $item->size > 0
                        ? ($item->size >= 1048576 ? number_format($item->size / 1048576, 2) . ' MB' : number_format($item->size / 1024, 1) . ' KB')
                        : null;
                @endphp
                <article class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3">
                    <div class="flex items-start gap-3">
                        {{-- Miniatura o icono por tipo --}}
                        <div class="shrink-0">
                            @if ($item->type === 'photo' && filled($item->url))
                                <img
                                    src="{{ $item->url }}"
                                    alt="{{ $item->title ?: $item->filename }}"
                                    loading="lazy"
                                    class="h-12 w-12 rounded-lg border border-white/10 bg-[#151518] object-cover"
                                    onerror="this.onerror=null; this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');"
                                >
                                <div class="hidden h-12 w-12 rounded-lg border border-white/10 bg-[#151518] text-sky-300 flex items-center justify-center">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                            @elseif ($item->type === 'mp3')
                                <div class="h-12 w-12 rounded-lg border border-amber-500/20 bg-amber-500/10 text-amber-300 flex items-center justify-center">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                                </div>
                            @elseif ($item->type === 'document')
                                <div class="h-12 w-12 rounded-lg border border-blue-500/20 bg-blue-500/10 text-blue-300 flex items-center justify-center">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                </div>
                            @else
                                <div class="h-12 w-12 rounded-lg border border-purple-500/20 bg-purple-500/10 text-purple-300 flex items-center justify-center">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <h2 class="text-sm font-semibold text-white break-words line-clamp-2">{{ $item->title ?: $item->filename }}</h2>
                            <p class="mt-0.5 text-xs text-gray-300">
                                Banda: <strong class="text-white">{{ $item->talent?->band_name ?? 'Sin banda asignada' }}</strong>
                            </p>
                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-medium {{ $typeStyle }}">
                                    {{ $typeLabel }}
                                </span>
                                @if ($isPrivateDocument)
                                    <span class="inline-flex items-center gap-1 rounded-md border border-white/10 bg-white/[0.04] px-2 py-0.5 text-[10px] font-medium text-gray-300">
                                        Documento privado
                                    </span>
                                @endif
                                @if ($sizeFormatted)
                                    <span class="text-[11px] text-gray-400">{{ $sizeFormatted }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-[11px] text-gray-400 border-t border-white/5 pt-2">
                        <span>Subido: {{ $item->created_at?->format('d/m/Y H:i') ?? 'N/D' }}</span>
                        <div class="flex items-center gap-2">
                            @if ($item->status === 'approved')
                                <span class="rounded border border-emerald-500/30 bg-emerald-500/10 px-1.5 py-0.5 text-[10px] text-emerald-300">Aprobado</span>
                            @elseif ($item->status === 'rejected')
                                <span class="rounded border border-red-500/30 bg-red-500/10 px-1.5 py-0.5 text-[10px] text-red-300">Rechazado</span>
                            @else
                                <span class="rounded border border-amber-500/30 bg-amber-500/10 px-1.5 py-0.5 text-[10px] text-amber-300">Pendiente</span>
                            @endif
                            @if ($item->is_exclusive)
                                <span class="text-[#c32720] font-semibold">Exclusivo afiliados</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                        @if ($item->status === 'pending')
                            <form method="POST" action="{{ route('admin.talents.media.approve', $item) }}" class="inline">
                                @csrf
                                <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 text-xs font-medium text-emerald-300 hover:bg-emerald-500/20 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                    Aprobar
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.talents.media.reject', $item) }}" class="inline">
                                @csrf
                                <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 text-xs font-medium text-amber-300 hover:bg-amber-500/20 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                    Rechazar
                                </button>
                            </form>
                        @endif

                        @if (!$isPrivateDocument && filled($item->url))
                            <a href="{{ $item->url }}" target="_blank" rel="noreferrer" class="inline-flex h-9 flex-1 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                                {{ $item->type === 'photo' ? 'Ver imagen' : 'Abrir archivo' }}
                            </a>
                        @else
                            <span class="inline-flex h-9 flex-1 items-center justify-center rounded-lg border border-white/5 bg-[#151518] px-3 text-[11px] text-gray-400">
                                Almacenamiento privado
                            </span>
                        @endif

                        <form method="POST" action="{{ route('admin.talents.media.destroy', $item) }}">
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                class="inline-flex h-9 items-center justify-center rounded-lg border border-red-500/30 bg-red-500/10 px-3 text-xs font-medium text-red-300 hover:bg-red-500/20 focus:outline-none focus:ring-2 focus:ring-red-500"
                                data-confirm="¿Eliminar este archivo de {{ $item->talent?->band_name ?? 'la banda' }}?"
                                data-confirm-title="Eliminar archivo de banda"
                                data-confirm-action="Eliminar"
                                data-confirm-tone="danger"
                            >
                                Eliminar
                            </button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-white/10 bg-[#101012] p-8 text-center text-xs text-gray-400">
                    No se encontraron archivos subidos por bandas con los filtros seleccionados.
                </div>
            @endforelse
        </div>

        {{-- Vista Escritorio --}}
        <div class="hidden md:block rounded-xl border border-white/10 bg-[#101012] overflow-hidden">
            <table class="min-w-full divide-y divide-white/10 text-left text-xs">
                <thead class="bg-white/[0.02] text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                    <tr>
                        <th scope="col" class="px-4 py-3">Vista previa y archivo</th>
                        <th scope="col" class="px-3 py-3">Banda propietaria</th>
                        <th scope="col" class="px-3 py-3">Tipo de archivo</th>
                        <th scope="col" class="px-3 py-3">Estado</th>
                        <th scope="col" class="px-3 py-3">Fecha de subida</th>
                        <th scope="col" class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($media as $item)
                        @php
                            $typeLabel = $typeLabels[$item->type] ?? ucfirst((string) $item->type);
                            $typeStyle = $typeStyles[$item->type] ?? 'border-white/15 bg-white/[0.04] text-gray-300';
                            $isPrivateDocument = $item->type === 'document' || $item->disk === 'r2_private';
                            $sizeFormatted = $item->size > 0
                                ? ($item->size >= 1048576 ? number_format($item->size / 1048576, 2) . ' MB' : number_format($item->size / 1024, 1) . ' KB')
                                : null;
                        @endphp
                        <tr class="hover:bg-white/[0.02] transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    {{-- Miniatura o icono --}}
                                    <div class="shrink-0">
                                        @if ($item->type === 'photo' && filled($item->url))
                                            <img
                                                src="{{ $item->url }}"
                                                alt="{{ $item->title ?: $item->filename }}"
                                                loading="lazy"
                                                class="h-10 w-10 rounded-lg border border-white/10 bg-[#151518] object-cover"
                                                onerror="this.onerror=null; this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');"
                                            >
                                            <div class="hidden h-10 w-10 rounded-lg border border-white/10 bg-[#151518] text-sky-300 flex items-center justify-center">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            </div>
                                        @elseif ($item->type === 'mp3')
                                            <div class="h-10 w-10 rounded-lg border border-amber-500/20 bg-amber-500/10 text-amber-300 flex items-center justify-center">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                                            </div>
                                        @elseif ($item->type === 'document')
                                            <div class="h-10 w-10 rounded-lg border border-blue-500/20 bg-blue-500/10 text-blue-300 flex items-center justify-center">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                            </div>
                                        @else
                                            <div class="h-10 w-10 rounded-lg border border-purple-500/20 bg-purple-500/10 text-purple-300 flex items-center justify-center">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="min-w-0">
                                        <div class="font-semibold text-white truncate max-w-[260px]" title="{{ $item->title ?: $item->filename }}">
                                            {{ $item->title ?: $item->filename }}
                                        </div>
                                        <div class="text-[11px] text-gray-400 flex items-center gap-2">
                                            @if ($sizeFormatted)
                                                <span>{{ $sizeFormatted }}</span>
                                            @endif
                                            @if ($item->is_exclusive)
                                                <span class="text-[#c32720] font-medium">Exclusivo afiliados</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                @if ($item->talent)
                                    <a href="{{ route('admin.talents.edit', $item->talent) }}" class="font-semibold text-white hover:text-[#c32720] transition-colors">
                                        {{ $item->talent->band_name }}
                                    </a>
                                    <div class="text-[11px] text-gray-400 truncate max-w-[200px]">{{ $item->talent->email }}</div>
                                @else
                                    <span class="text-gray-400">Sin banda asignada</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <div class="flex items-center gap-1.5">
                                    <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-medium {{ $typeStyle }}">
                                        {{ $typeLabel }}
                                    </span>
                                    @if ($isPrivateDocument)
                                        <span class="inline-flex items-center rounded-md border border-white/10 bg-white/[0.04] px-2 py-0.5 text-[10px] font-medium text-gray-300" title="Almacenado en bucket privado R2">
                                            Privado
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                @if ($item->status === 'approved')
                                    <span class="inline-flex items-center rounded-md border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-300">
                                        Aprobado
                                    </span>
                                @elseif ($item->status === 'rejected')
                                    <span class="inline-flex items-center rounded-md border border-red-500/30 bg-red-500/10 px-2 py-0.5 text-[11px] font-medium text-red-300">
                                        Rechazado
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-md border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[11px] font-medium text-amber-300">
                                        Pendiente
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-300">
                                {{ $item->created_at?->format('d/m/Y H:i') ?? 'N/D' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                <div class="inline-flex items-center justify-end gap-2">
                                    @if ($item->status === 'pending')
                                        <form method="POST" action="{{ route('admin.talents.media.approve', $item) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-2.5 text-xs font-medium text-emerald-300 hover:bg-emerald-500/20 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                                Aprobar
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.talents.media.reject', $item) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-amber-500/30 bg-amber-500/10 px-2.5 text-xs font-medium text-amber-300 hover:bg-amber-500/20 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                                Rechazar
                                            </button>
                                        </form>
                                    @endif

                                    @if (!$isPrivateDocument && filled($item->url))
                                        <a href="{{ $item->url }}" target="_blank" rel="noreferrer" class="inline-flex h-8 items-center rounded-lg border border-white/10 bg-white/[0.04] px-2.5 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                                            {{ $item->type === 'photo' ? 'Ver imagen' : 'Abrir' }}
                                        </a>
                                    @endif

                                    <form method="POST" action="{{ route('admin.talents.media.destroy', $item) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="inline-flex h-8 items-center rounded-lg border border-red-500/30 bg-red-500/10 px-2.5 text-xs font-medium text-red-300 hover:bg-red-500/20 focus:outline-none focus:ring-2 focus:ring-red-500"
                                            data-confirm="¿Eliminar este archivo de {{ $item->talent?->band_name ?? 'la banda' }}?"
                                            data-confirm-title="Eliminar archivo de banda"
                                            data-confirm-action="Eliminar"
                                            data-confirm-tone="danger"
                                        >
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-xs text-gray-400">
                                No se encontraron archivos subidos por bandas con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($media->hasPages())
            <div class="pt-2">
                {{ $media->links() }}
            </div>
        @endif
    </div>
</x-layouts.admin>
