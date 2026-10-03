@props(['nombre' => null])
<div class="pointer-events-none absolute left-3 top-3 z-10 flex items-center gap-2 rounded-full bg-black/70 px-3 py-1.5 backdrop-blur-sm">
    <svg class="luto-lazo h-7 w-7 text-error" viewBox="0 0 64 64" fill="none"
         stroke="currentColor" stroke-width="4.2" stroke-linejoin="round" stroke-linecap="round"
         aria-hidden="true">
        <path d="M32 7.5c4.8 0 8 3.1 8 7.4 0 3.1-1.6 5.8-4 7.6L50 56.5H40.6L32 35.8 23.4 56.5H14L28 22.5c-2.4-1.8-4-4.5-4-7.6 0-4.3 3.2-7.4 8-7.4z"/>
    </svg>
    <span class="text-[11px] font-bold uppercase tracking-[0.14em] text-error">
        En memoria{{ $nombre ? ' - '.$nombre : '' }}
    </span>
</div>

<style>
.luto-lazo path {
    stroke-dasharray: 200;
    stroke-dashoffset: 200;
    animation: luto-dibujar 1.3s ease-in-out infinite;
}
@keyframes luto-dibujar {
    0%   { stroke-dashoffset: 200; }
    55%  { stroke-dashoffset: 0; }
    100% { stroke-dashoffset: 0; }
}
@media (prefers-reduced-motion: reduce) {
    .luto-lazo path { animation: none; stroke-dashoffset: 0; }
}
</style>
