@php $admin = $themeAppearance['admin_texts']; @endphp
<x-layouts.admin :title="'Conciertos y agenda' . ' - ' . $themeSettings->site_name">
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
            <h1 class="font-display text-2xl sm:text-3xl uppercase tracking-wider text-[#dcdcdc]">Conciertos y agenda</h1>
            <p class="mt-1 text-sm text-gray-400">Agenda de conciertos, festivales y directos destacados en la web.</p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="{{ route('admin.events.single') }}" class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all" title="Previsualizar evento público en la web">
                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <span>Vista previa</span>
            </a>
            <a href="{{ route('admin.events.create') }}" class="inline-flex items-center justify-center gap-2 h-9 px-4 rounded-lg bg-[#c32720] hover:bg-[#a8201a] text-white text-xs font-semibold tracking-wide shadow-md shadow-red-950/40 transition-all focus:outline-none focus:ring-2 focus:ring-[#c32720]/50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Nuevo evento</span>
            </a>
        </div>
    </div>

    <div class="rounded-xl border border-white/10 bg-[#101012] shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-white/10 bg-white/[0.02] text-xs font-medium uppercase tracking-wider text-gray-400">
                    <tr>
                        <th class="px-5 py-3.5">Título del evento</th>
                        <th class="px-5 py-3.5 whitespace-nowrap">Fecha y hora</th>
                        <th class="px-5 py-3.5">Ciudad</th>
                        <th class="px-5 py-3.5">Sala / Recinto</th>
                        <th class="px-5 py-3.5 text-right whitespace-nowrap">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-gray-300">
                    @forelse ($events as $event)
                        <tr class="hover:bg-white/[0.02] transition-colors">
                            <td class="px-5 py-4 min-w-[200px]">
                                <div class="font-medium text-white text-sm">
                                    {{ $event->title }}
                                </div>
                                @if ($event->is_cancelled)
                                    <div class="mt-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                            CANCELADO
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-xs text-gray-400">
                                @php
                                    $startsAt = $event->starts_at;
                                    if (is_string($startsAt)) {
                                        $startsAt = \Illuminate\Support\Carbon::parse($startsAt);
                                    }
                                @endphp
                                {{ $startsAt?->format('d M Y H:i') ?? '—' }}
                            </td>
                            <td class="px-5 py-4 text-xs text-gray-300">{{ $event->location ?: '—' }}</td>
                            <td class="px-5 py-4 text-xs text-gray-300">{{ $event->venue ?: '—' }}</td>
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.events.edit', $event) }}" class="inline-flex items-center justify-center gap-1.5 h-8 px-3 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 text-gray-200 text-xs font-medium transition-all">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        <span>Editar</span>
                                    </a>
                                    <form
                                        action="{{ route('admin.events.destroy', $event) }}"
                                        method="POST"
                                        class="inline"
                                        data-confirm="¿Eliminar este evento definitivamente?"
                                        data-confirm-title="Eliminar evento"
                                        data-confirm-action="Eliminar"
                                        data-confirm-tone="danger"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center justify-center gap-1.5 h-8 px-3 rounded-lg border border-rose-900/40 bg-rose-950/20 hover:bg-rose-900/30 text-rose-300 text-xs font-medium transition-all">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            <span>Eliminar</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-gray-500">No hay eventos en la agenda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
