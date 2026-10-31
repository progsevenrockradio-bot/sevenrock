@props(['variant' => 'dock', 'fallbackCover' => asset('assets/lucille/logo.png')])

<div class="radio-player-nexttrack-shell radio-player-nexttrack-shell--{{ $variant }}" x-cloak x-show="hasValidNextTrack">
    <div class="flex items-center justify-between mb-2">
        <span class="text-[10px] uppercase font-bold tracking-wider text-lucille-accent font-display">A continuación</span>
        <span class="text-[9px] text-neutral-400 font-mono">Próxima canción</span>
    </div>
    <div class="flex items-center gap-3 bg-[#141418] border border-white/10 rounded-lg p-2.5">
        <div class="w-11 h-11 rounded overflow-hidden bg-neutral-900 border border-white/10 shrink-0 flex items-center justify-center">
            <img :src="nextTrack?.cover || fallbackCover" alt="" class="w-full h-full object-cover" x-on:error="if ($el.src !== fallbackCover) $el.src = fallbackCover">
        </div>
        <div class="min-w-0 flex-1">
            <strong class="block text-xs font-bold text-white truncate" x-text="nextTrack?.title || ''"></strong>
            <small class="block text-[11px] text-neutral-400 truncate" x-text="nextTrack?.artist || 'Seven Rock Radio'"></small>
        </div>
    </div>
</div>
