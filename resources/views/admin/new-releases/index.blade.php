<x-layouts.admin :title="'Nuevos Lanzamientos - '.$themeSettings->site_name">
    @if (session('status') || session('success'))
        <div class="mb-6 flex items-center justify-between rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            <div class="flex items-center gap-2">
                <svg class="h-4 w-4 shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ session('status') ?? session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-400 hover:text-emerald-200 text-xs">✕</button>
        </div>
    @endif

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="font-display text-2xl sm:text-3xl uppercase tracking-wider text-[#dcdcdc]">Nuevos Lanzamientos</h1>
            <p class="mt-1 text-sm text-gray-400">Novedades discográficas y estrenos musicales destacados en la página principal.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.new-releases.create') }}" class="inline-flex items-center justify-center gap-2 h-9 px-4 rounded-lg bg-[#c32720] hover:bg-[#a8201a] text-white text-xs font-semibold tracking-wide shadow-md shadow-red-950/40 transition-all focus:outline-none focus:ring-2 focus:ring-[#c32720]/50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Nuevo Lanzamiento</span>
            </a>
        </div>
    </div>

    <div class="rounded-xl border border-white/10 bg-[#101012] shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-white/10 bg-white/[0.02] text-xs font-medium uppercase tracking-wider text-gray-400">
                    <tr>
                        <th class="px-5 py-3.5 w-16">Portada</th>
                        <th class="px-5 py-3.5">Título</th>
                        <th class="px-5 py-3.5">Artista</th>
                        <th class="px-5 py-3.5">Artista de Radio</th>
                        <th class="px-5 py-3.5 whitespace-nowrap">Fecha Lanzamiento</th>
                        <th class="px-5 py-3.5 whitespace-nowrap">Estado</th>
                        <th class="px-5 py-3.5 text-right whitespace-nowrap">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-gray-300">
                    @forelse ($newReleases as $release)
                        <tr class="hover:bg-white/[0.02] transition-colors">
                            <td class="px-5 py-3.5">
                                @if($release->cover_image)
                                    <img src="{{ $release->cover_image_url }}" alt="{{ $release->title }}" class="h-10 w-10 rounded-lg object-cover border border-white/10 bg-black">
                                @else
                                    <div class="h-10 w-10 rounded-lg border border-white/10 bg-white/5 flex items-center justify-center text-[10px] text-gray-500 font-mono">
                                        N/D
                                    </div>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 font-medium text-white text-sm">
                                {{ $release->title }}
                            </td>
                            <td class="px-5 py-3.5 text-xs text-gray-300">{{ $release->artist_name }}</td>
                            <td class="px-5 py-3.5 text-xs text-gray-400">{{ $release->radioArtist?->name ?? '—' }}</td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-xs text-gray-400">{{ $release->released_at ? $release->released_at->format('d M Y') : '—' }}</td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                @if ($release->is_active)
                                    <span class="inline-flex items-center rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-400 border border-emerald-500/20">Activo</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-white/5 px-2.5 py-0.5 text-xs font-medium text-gray-400 border border-white/10">Inactivo</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.new-releases.edit', $release) }}" class="inline-flex items-center justify-center gap-1.5 h-8 px-3 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all">
                                        Editar
                                    </a>
                                    <form
                                        action="{{ route('admin.new-releases.destroy', $release) }}"
                                        method="POST"
                                        class="inline"
                                        data-confirm="¿Eliminar este lanzamiento definitivamente?"
                                        data-confirm-title="Eliminar lanzamiento"
                                        data-confirm-action="Eliminar"
                                        data-confirm-tone="danger"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center justify-center gap-1.5 h-8 px-3 rounded-lg border border-rose-900/40 bg-rose-950/20 hover:bg-rose-900/30 text-rose-300 text-xs font-medium transition-all">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-gray-500">No hay lanzamientos creados aún.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
