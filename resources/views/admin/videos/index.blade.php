@php $admin = $themeAppearance['admin_texts']; @endphp
<x-layouts.admin :title="'Vídeos' . ' - ' . $themeSettings->site_name">
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
            <h1 class="font-display text-2xl sm:text-3xl uppercase tracking-wider text-[#dcdcdc]">Vídeos y directos</h1>
            <p class="mt-1 text-sm text-gray-400">Vídeos destacados, directos y videoclips de la programación.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.videos.create') }}" class="inline-flex items-center justify-center gap-2 h-9 px-4 rounded-lg bg-[#c32720] hover:bg-[#a8201a] text-white text-xs font-semibold tracking-wide shadow-md shadow-red-950/40 transition-all focus:outline-none focus:ring-2 focus:ring-[#c32720]/50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Nuevo Vídeo</span>
            </a>
        </div>
    </div>

    <div class="rounded-xl border border-white/10 bg-[#101012] shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-white/10 bg-white/[0.02] text-xs font-medium uppercase tracking-wider text-gray-400">
                    <tr>
                        <th class="px-5 py-3.5">Título</th>
                        <th class="px-5 py-3.5">Slug</th>
                        <th class="px-5 py-3.5">Enlace YouTube</th>
                        <th class="px-5 py-3.5 whitespace-nowrap">Destacado</th>
                        <th class="px-5 py-3.5 text-right whitespace-nowrap">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-gray-300">
                    @forelse ($videos as $video)
                        <tr class="hover:bg-white/[0.02] transition-colors">
                            <td class="px-5 py-4 min-w-[200px]">
                                <div class="font-medium text-white text-sm">
                                    {{ $video->title }}
                                </div>
                            </td>
                            <td class="px-5 py-4 text-xs font-mono text-gray-400">{{ $video->slug }}</td>
                            <td class="px-5 py-4 text-xs">
                                @if ($video->youtube_url)
                                    <a href="{{ $video->youtube_url }}" target="_blank" class="text-gray-300 hover:text-white hover:underline truncate max-w-xs block">
                                        {{ $video->youtube_url }}
                                    </a>
                                @else
                                    <span class="text-gray-500">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap">
                                <div class="flex flex-col gap-1 items-start">
                                    @if ($video->is_featured)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border border-emerald-500/20 bg-emerald-500/10 text-emerald-400">Destacado</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border border-white/10 bg-white/5 text-gray-400">Normal</span>
                                    @endif
                                    
                                    @if ($video->is_manual)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border border-amber-500/20 bg-amber-500/10 text-amber-300">Fijado a mano</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border border-blue-500/20 bg-blue-500/10 text-blue-300">Automático</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.videos.edit', $video) }}" class="inline-flex items-center justify-center gap-1.5 h-8 px-3 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all">
                                        Editar
                                    </a>
                                    <form action="{{ route('admin.videos.toggle-manual', $video) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center justify-center h-8 px-2.5 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-300 text-xs font-medium transition-all" title="Alternar modo manual/automático">
                                            {{ $video->is_manual ? 'Automático' : 'Fijar' }}
                                        </button>
                                    </form>
                                    <form
                                        action="{{ route('admin.videos.destroy', $video) }}"
                                        method="POST"
                                        class="inline"
                                        data-confirm="¿Eliminar este vídeo definitivamente?"
                                        data-confirm-title="Eliminar vídeo"
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
                            <td colspan="5" class="px-5 py-12 text-center text-gray-500">No hay vídeos registrados aún.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
