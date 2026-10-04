@props(['tipo', 'enlace' => null, 'hora' => null, 'absolute' => false])

@if(in_array($tipo, ['en_vivo', 'retransmision']))
<div class="{{ $absolute ? 'absolute left-3 top-3' : 'mb-1.5' }} pointer-events-none z-10 flex flex-wrap items-center gap-1.5 drop-shadow-[0_1px_3px_rgba(0,0,0,0.9)] drop-shadow-[0_0_10px_rgba(0,0,0,0.7)] {{ $attributes->get('class') }}">
    @if($tipo === 'en_vivo')
        <span class="relative flex h-2 w-2">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-error opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2 w-2 bg-error"></span>
        </span>
        <span class="text-[10px] font-bold uppercase tracking-[0.14em] text-error leading-none">
            En vivo{{ $hora ? ' ' . $hora : '' }}
        </span>
        @if(filled($enlace))
            <a href="{{ $enlace }}" target="_blank" class="pointer-events-auto bg-error/20 text-error hover:bg-error hover:text-white transition-colors px-1.5 py-0.5 rounded text-[9px] font-bold tracking-[0.1em] uppercase ml-1 border border-error/50">
                Link
            </a>
        @endif
    @else
        <span class="text-[10px] font-bold uppercase tracking-[0.14em] text-primary leading-none">
            Retransmisión{{ $hora ? ' ' . $hora : '' }}
        </span>
    @endif
</div>
@endif
