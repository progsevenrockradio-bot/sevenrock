<x-layouts.admin title="Historial de Avisos - Airplay">
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-800 pb-4">
            <div>
                <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                    <svg class="w-7 h-7 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    Historial de Avisos de Programación
                </h1>
                <p class="text-sm text-gray-400 mt-1">Registro de correos enviados a artistas y sellos discográficos.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('airplay.index') }}" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-200 text-sm font-semibold rounded-lg border border-gray-700 transition flex items-center gap-2">
                    <svg class="w-4 h-4 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Volver a la Programación
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-5">
            <form method="GET" action="{{ route('airplay.notices') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1">Estado</label>
                    <select name="estado" onchange="this.form.submit()" class="w-full bg-gray-950 border border-gray-700 text-gray-200 text-sm rounded-lg px-3 py-2">
                        <option value="">Todos los estados</option>
                        <option value="enviado" {{ $status === 'enviado' ? 'selected' : '' }}>Enviado</option>
                        <option value="pendiente" {{ $status === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                        <option value="error" {{ $status === 'error' ? 'selected' : '' }}>Error</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1">Buscar (Email / Artista / Título)</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Email o nombre..." class="w-full bg-gray-950 border border-gray-700 text-gray-200 text-sm rounded-lg px-3 py-2">
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="w-full px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white text-sm font-semibold rounded-lg transition">Filtrar</button>
                    <a href="{{ route('airplay.notices') }}" class="px-3 py-2 bg-gray-950 hover:bg-gray-800 text-gray-400 text-sm font-semibold rounded-lg border border-gray-800">Limpiar</a>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden shadow-lg">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-300">
                    <thead class="bg-gray-950 text-xs text-gray-400 uppercase tracking-wider border-b border-gray-800">
                        <tr>
                            <th class="px-4 py-3">Fecha Envío</th>
                            <th class="px-4 py-3">Destinatario</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Pista / Emisión</th>
                            <th class="px-4 py-3">Semana</th>
                            <th class="px-4 py-3 text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @forelse($notices as $notice)
                            <tr class="hover:bg-gray-800/50 transition">
                                <td class="px-4 py-3 text-gray-400 whitespace-nowrap text-xs">
                                    {{ $notice->enviado_en ? $notice->enviado_en->format('d/m/Y H:i') : '-' }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-200">
                                    {{ $notice->email }}
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <span class="px-2 py-0.5 rounded bg-gray-800 border border-gray-700 text-gray-300 capitalize">
                                        {{ $notice->tipo }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($notice->schedule)
                                        <div class="font-medium text-white">{{ $notice->schedule->artista }} - {{ $notice->schedule->titulo }}</div>
                                        <div class="text-xs text-gray-500">{{ $notice->schedule->dia_nombre }} {{ $notice->schedule->hora_formateada }} hs</div>
                                    @else
                                        <span class="text-gray-500 italic">Pista eliminada</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-400">
                                    {{ $notice->schedule?->semana ?: '-' }}
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    @if($notice->estado === 'enviado')
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-emerald-950 text-emerald-300 border border-emerald-700/60">
                                            Enviado
                                        </span>
                                    @elseif($notice->estado === 'error')
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-rose-950 text-rose-300 border border-rose-700/60" title="{{ $notice->error }}">
                                            Error
                                        </span>
                                    @else
                                        <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-amber-950 text-amber-300 border border-amber-700/60">
                                            Pendiente
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    No hay registros de avisos enviados aún.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($notices->hasPages())
                <div class="px-4 py-3 border-t border-gray-800 bg-gray-950">
                    {{ $notices->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
