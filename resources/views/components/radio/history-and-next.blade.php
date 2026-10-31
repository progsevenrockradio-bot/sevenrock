@props(['variant' => 'dock', 'fallbackCover' => asset('assets/lucille/logo.png')])

<div class="space-y-4">
    {{-- 1. Bloque: Próxima Canción («A continuación») --}}
    <div x-show="hasValidNextTrack" x-cloak class="rounded-xl border border-white/10 bg-[#141418] p-3 shadow-md">
        <div class="flex items-center justify-between gap-2 mb-2">
            <span class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-[#c32720] font-display">
                <span class="w-1.5 h-1.5 rounded-full bg-[#c32720] animate-pulse"></span>
                A continuación
            </span>
            <span class="text-[9px] text-neutral-400 font-mono">Próxima canción</span>
        </div>
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-lg overflow-hidden bg-neutral-900 border border-neutral-800 shrink-0 flex items-center justify-center">
                <img :src="nextTrack?.cover || fallbackCover" alt="" class="w-full h-full object-cover" x-on:error="if ($el.src !== fallbackCover) $el.src = fallbackCover">
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-xs font-bold text-white truncate" x-text="nextTrack?.title || ''"></div>
                <div class="text-[11px] text-neutral-400 truncate mt-0.5" x-text="nextTrack?.artist || 'Seven Rock Radio'"></div>
            </div>
        </div>
    </div>

    {{-- 2. Bloque: Últimas Canciones (Historial) --}}
    <div class="rounded-xl border border-white/10 bg-[#101013] p-3 shadow-md">
        <div class="flex items-center justify-between gap-2 mb-3 pb-2 border-b border-white/5">
            <div class="flex items-center gap-2">
                <h4 class="text-xs font-bold uppercase tracking-wider text-white font-display">Últimas canciones</h4>
                <span class="text-[10px] font-mono text-neutral-500" x-text="'(' + (history?.length || 0) + ')'"></span>
            </div>
            <div x-show="historyStale" x-cloak class="flex items-center gap-1 text-[10px] text-amber-400 bg-amber-500/10 border border-amber-500/20 px-2 py-0.5 rounded">
                <span>⚠️</span>
                <span>Último historial disponible</span>
            </div>
        </div>

        {{-- Estado Vacío --}}
        <div x-show="!history || history.length === 0" x-cloak class="text-center py-6 text-xs text-neutral-500 font-sans">
            No hay canciones recientes registradas en este momento.
        </div>

        {{-- Lista de pistas del historial --}}
        <div x-show="history && history.length > 0" class="space-y-2">
            <template x-for="(item, index) in visibleHistory" :key="index + '-' + (item.title || '') + '-' + (item.played_at || '')">
                <div class="flex items-center justify-between gap-3 p-2 rounded-lg bg-[#15151a] border border-white/5 hover:border-[#c32720]/40 transition">
                    <div class="flex items-center gap-2.5 min-w-0 flex-1">
                        <div class="w-9 h-9 rounded bg-neutral-900 border border-neutral-800 shrink-0 overflow-hidden flex items-center justify-center">
                            <img :src="item.cover || fallbackCover" alt="" class="w-full h-full object-cover" x-on:error="if ($el.src !== fallbackCover) $el.src = fallbackCover">
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-semibold text-white truncate" x-text="item.title || 'Título no disponible'"></div>
                            <div class="text-[11px] text-neutral-400 truncate" x-text="item.artist || 'Seven Rock Radio'"></div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <template x-if="item.is_program">
                            <span class="text-[9px] uppercase font-bold px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                Programa
                            </span>
                        </template>
                        <span x-show="item.played_at" class="text-[10px] font-mono text-neutral-500" x-text="formatPlayedAt(item.played_at)"></span>
                    </div>
                </div>
            </template>
        </div>

        {{-- Control Plegable (Toggle Expandir / Plegar) --}}
        <div x-show="history && history.length > 3" class="mt-3 pt-2 border-t border-white/5 flex justify-center">
            <button
                type="button"
                @click.stop="toggleHistoryExpanded()"
                class="px-3 py-1 rounded text-xs font-semibold text-[#c32720] hover:text-white bg-[#c32720]/10 hover:bg-[#c32720]/25 border border-[#c32720]/30 transition flex items-center gap-1.5"
            >
                <span x-text="historyExpanded ? '▲ Mostrar menos' : ('▼ Ver historial completo (' + history.length + ' canciones)')"></span>
            </button>
        </div>
    </div>
</div>
