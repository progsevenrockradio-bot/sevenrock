<x-layouts.admin title="Páginas en Espera">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="font-display text-2xl uppercase tracking-[.08em] text-[#dcdcdc]">Páginas en Espera (Coming Soon)</h1>
            <p class="text-sm text-[#7b7b7b] mt-1">Configura qué rutas están bloqueadas temporalmente al público.</p>
        </div>
        <a href="{{ route('admin.page-countdowns.create') }}" class="lucille-button-solid">Agregar Página</a>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-[12px] bg-green-500/10 border border-green-500/20 p-4 text-sm text-green-400">
            {{ session('success') }}
        </div>
    @endif

    <div class="lucille-card">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-[#c3c3c3]">
                <thead class="border-b border-white/5 font-display text-[11px] uppercase tracking-[.15em] text-[#8a8a8a]">
                    <tr>
                        <th class="p-4 font-normal">Ruta (Path)</th>
                        <th class="p-4 font-normal">Estado</th>
                        <th class="p-4 font-normal">Fecha de Activación</th>
                        <th class="p-4 font-normal">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse($countdowns as $countdown)
                        <tr class="hover:bg-white/[0.02] transition-colors">
                            <td class="p-4">
                                <span class="font-medium text-[#dcdcdc]">{{ $countdown->route_path }}</span>
                                <div class="text-xs text-[#7b7b7b] mt-1">{{ $countdown->title }}</div>
                            </td>
                            <td class="p-4">
                                @if($countdown->is_enabled)
                                    <span class="px-2 py-1 bg-green-500/10 text-green-400 text-[10px] uppercase tracking-wider rounded border border-green-500/20">Activo</span>
                                @else
                                    <span class="px-2 py-1 bg-red-500/10 text-red-400 text-[10px] uppercase tracking-wider rounded border border-red-500/20">Inactivo</span>
                                @endif
                            </td>
                            <td class="p-4">
                                @if($countdown->active_at)
                                    @if($countdown->active_at->isFuture())
                                        <span class="text-[var(--lucille-accent)]">{{ $countdown->active_at->format('d/m/Y H:i') }}</span>
                                    @else
                                        <span class="text-green-400">Ya activa ({{ $countdown->active_at->format('d/m/Y') }})</span>
                                    @endif
                                @else
                                    <span class="text-[#7b7b7b]">Desconocida (Bloqueo indefinido)</span>
                                @endif
                            </td>
                            <td class="p-4 flex gap-3">
                                <a href="{{ route('admin.page-countdowns.edit', $countdown) }}" class="text-gray-400 hover:text-white transition-colors" title="Editar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </a>
                                <form action="{{ route('admin.page-countdowns.destroy', $countdown) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este contador?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-400/70 hover:text-red-400 transition-colors" title="Eliminar">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-8 text-center text-[#7b7b7b] text-sm">No hay páginas en espera configuradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
