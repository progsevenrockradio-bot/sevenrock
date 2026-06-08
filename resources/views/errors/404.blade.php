<x-layouts.site title="Seven Rock Radio - 404 ¡Página no encontrada!" description="La página que buscas no existe en Seven Rock Radio. Vuelve al inicio o usa el buscador.">

    @php
        $theme = $themeAppearance;
        $siteUrl = config('app.url', 'https://sevenrockradio.com');
    @endphp

    <div class="relative flex min-h-[70vh] flex-col items-center justify-center overflow-hidden px-5 py-16 md:py-24">
        {{-- 404 gigante --}}
        <div class="relative z-10 text-center">
            <div class="font-display text-[130px] leading-none md:text-[180px]" style="
                background: linear-gradient(135deg, var(--lucille-accent, #d42426) 0%, #ff6b6b 50%, var(--lucille-accent, #d42426) 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            ">404</div>

            {{-- Icono --}}
            <div class="mb-4 text-5xl md:text-6xl">🎸</div>

            {{-- Título --}}
            <h1 class="font-display text-xl uppercase tracking-[.15em] text-[#dcdcdc] md:text-3xl">
                ¡Esta canción no está en el setlist!
            </h1>

            {{-- Texto --}}
            <p class="mx-auto mt-4 max-w-[500px] text-sm leading-7 text-[#7b7b7b] md:text-base">
                La página que buscas no existe, fue eliminada o cambió de frecuencia.
            </p>

            {{-- Buscador --}}
            <div class="mx-auto mt-8 max-w-[380px]">
                <form action="{{ route('search') }}" method="GET" class="relative">
                    <input 
                        type="text" 
                        name="q" 
                        placeholder="Buscar en Seven Rock Radio..."
                        class="w-full border border-[#2b2b2b] bg-[#111] px-4 py-3 text-sm text-[#dcdcdc] placeholder-[#5a5a5a] outline-none transition-colors focus:border-lucille-accent/60"
                        autocomplete="off"
                    >
                    <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 text-lucille-accent transition hover:text-white text-sm">
                        Buscar →
                    </button>
                </form>
            </div>

            {{-- Botones --}}
            <div class="mt-8 flex flex-wrap items-center justify-center gap-2">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 border border-lucille-accent bg-lucille-accent px-4 py-2.5 text-[10px] font-display uppercase tracking-[.18em] text-white transition-opacity hover:opacity-85 md:px-5 md:py-3 md:text-xs">
                    ⬅ Inicio
                </a>
                <a href="{{ route('discography') }}" class="inline-flex items-center gap-1.5 border border-[#2b2b2b] px-4 py-2.5 text-[10px] font-display uppercase tracking-[.18em] text-[#dcdcdc] transition-colors hover:border-lucille-accent hover:text-lucille-accent md:px-5 md:py-3 md:text-xs">
                    🎵 Discografía
                </a>
                <a href="{{ route('programs') }}" class="inline-flex items-center gap-1.5 border border-[#2b2b2b] px-4 py-2.5 text-[10px] font-display uppercase tracking-[.18em] text-[#dcdcdc] transition-colors hover:border-lucille-accent hover:text-lucille-accent md:px-5 md:py-3 md:text-xs">
                    📻 Programas
                </a>
                <a href="{{ route('events') }}" class="inline-flex items-center gap-1.5 border border-[#2b2b2b] px-4 py-2.5 text-[10px] font-display uppercase tracking-[.18em] text-[#dcdcdc] transition-colors hover:border-lucille-accent hover:text-lucille-accent md:px-5 md:py-3 md:text-xs">
                    📅 Eventos
                </a>
                <a href="{{ route('shop') }}" class="inline-flex items-center gap-1.5 border border-[#2b2b2b] px-4 py-2.5 text-[10px] font-display uppercase tracking-[.18em] text-[#dcdcdc] transition-colors hover:border-lucille-accent hover:text-lucille-accent md:px-5 md:py-3 md:text-xs">
                    🛒 Tienda
                </a>
                <a href="{{ route('contact') }}" class="inline-flex items-center gap-1.5 border border-[#2b2b2b] px-4 py-2.5 text-[10px] font-display uppercase tracking-[.18em] text-[#dcdcdc] transition-colors hover:border-lucille-accent hover:text-lucille-accent md:px-5 md:py-3 md:text-xs">
                    ✉ Contacto
                </a>
            </div>
        </div>
    </div>

</x-layouts.site>
