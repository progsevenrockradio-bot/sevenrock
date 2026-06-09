<x-layouts.site title="Seven Rock Radio - 404 ¡Página no encontrada!" description="La página que buscas no existe en Seven Rock Radio. Vuelve al inicio o usa el buscador.">

    @php
        $theme = $themeAppearance;
        $siteUrl = config('app.url', 'https://sevenrockradio.com');
    @endphp

    <style>
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(-4deg); }
            50% { transform: translateY(-8px) rotate(4deg); }
        }
        @keyframes pulse-glow {
            0%, 100% {
                box-shadow: 0 25px 50px -12px rgba(0,0,0,0.7), 0 0 15px rgba(212, 36, 38, 0.05);
                border-color: rgba(255, 255, 255, 0.06);
            }
            50% {
                box-shadow: 0 25px 50px -12px rgba(0,0,0,0.75), 0 0 30px rgba(212, 36, 38, 0.2);
                border-color: rgba(212, 36, 38, 0.25);
            }
        }
        .floating-guitar {
            animation: float 4s ease-in-out infinite;
        }
        .glow-card {
            animation: pulse-glow 4s ease-in-out infinite;
        }
    </style>

    <div class="relative flex min-h-[75vh] flex-col items-center justify-center overflow-hidden px-5 py-12 md:py-20">
        {{-- Sutil resplandor de fondo --}}
        <div class="absolute inset-0 pointer-events-none flex items-center justify-center opacity-[0.08]" aria-hidden="true">
            <div class="w-[600px] h-[600px] rounded-full bg-lucille-accent filter blur-[100px]"></div>
        </div>

        {{-- Tarjeta Glassmorphic principal --}}
        <div class="relative z-10 w-full max-w-2xl rounded-2xl border border-white/[0.06] bg-[#10161b]/60 p-8 md:p-12 text-center backdrop-blur-md glow-card">
            
            {{-- Icono con insignia circular --}}
            <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-white/[0.02] border border-white/10 shadow-lg floating-guitar text-5xl mb-6 select-none">
                🎸
            </div>

            {{-- 404 gigante --}}
            <div class="relative text-center select-none mb-2">
                <div class="font-display text-[110px] font-bold leading-none md:text-[140px] tracking-tighter" style="
                    background: linear-gradient(135deg, var(--lucille-accent, #d42426) 0%, #ff6b6b 50%, var(--lucille-accent, #d42426) 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                    filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.5));
                ">404</div>
            </div>

            {{-- Título principal --}}
            <h1 class="font-display text-xl uppercase tracking-[.15em] text-white md:text-2xl mt-4">
                ¡Esta canción no está en el setlist!
            </h1>

            {{-- Texto descriptivo --}}
            <p class="mx-auto mt-3 max-w-[480px] text-sm leading-relaxed text-[#9aa7b1] md:text-base">
                La página que buscas no existe, fue eliminada de la programación o cambió de frecuencia en nuestro dial.
            </p>

            {{-- Buscador Premium --}}
            <div class="mx-auto mt-8 max-w-[420px]">
                <form action="{{ route('search') }}" method="GET" class="relative group">
                    <input 
                        type="text" 
                        name="q" 
                        placeholder="Buscar en Seven Rock Radio..."
                        class="w-full rounded-full border border-white/10 bg-black/40 px-5 py-3.5 pl-12 pr-24 text-sm text-[#dcdcdc] placeholder-[#5a5a5a] outline-none transition-all duration-300 focus:border-lucille-accent/60 focus:bg-black/60 focus:ring-2 focus:ring-lucille-accent/10"
                        autocomplete="off"
                        required
                    >
                    <div class="absolute left-4 top-1/2 -translate-y-1/2 text-white/30 group-focus-within:text-lucille-accent transition-colors">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-lucille-accent px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-white transition hover:bg-opacity-95 active:scale-95 shadow-md shadow-lucille-accent/20">
                        Buscar
                    </button>
                </form>
            </div>

            {{-- Botones de Navegación --}}
            <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 rounded-full border border-lucille-accent bg-lucille-accent px-5 py-3 text-[11px] font-display uppercase tracking-[.18em] text-white transition-all duration-300 hover:bg-transparent hover:text-lucille-accent hover:-translate-y-0.5 shadow-lg shadow-lucille-accent/15">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Inicio
                </a>
                <a href="{{ route('discography') }}" class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.02] px-5 py-3 text-[11px] font-display uppercase tracking-[.18em] text-[#dcdcdc] transition-all duration-300 hover:border-lucille-accent hover:text-lucille-accent hover:bg-lucille-accent/[0.02] hover:-translate-y-0.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                    </svg>
                    Discografía
                </a>
                <a href="{{ route('programs') }}" class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.02] px-5 py-3 text-[11px] font-display uppercase tracking-[.18em] text-[#dcdcdc] transition-all duration-300 hover:border-lucille-accent hover:text-lucille-accent hover:bg-lucille-accent/[0.02] hover:-translate-y-0.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                    </svg>
                    Programas
                </a>
                <a href="{{ route('events') }}" class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.02] px-5 py-3 text-[11px] font-display uppercase tracking-[.18em] text-[#dcdcdc] transition-all duration-300 hover:border-lucille-accent hover:text-lucille-accent hover:bg-lucille-accent/[0.02] hover:-translate-y-0.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    Eventos
                </a>
                <a href="{{ route('shop') }}" class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.02] px-5 py-3 text-[11px] font-display uppercase tracking-[.18em] text-[#dcdcdc] transition-all duration-300 hover:border-lucille-accent hover:text-lucille-accent hover:bg-lucille-accent/[0.02] hover:-translate-y-0.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    Tienda
                </a>
                <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.02] px-5 py-3 text-[11px] font-display uppercase tracking-[.18em] text-[#dcdcdc] transition-all duration-300 hover:border-lucille-accent hover:text-lucille-accent hover:bg-lucille-accent/[0.02] hover:-translate-y-0.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    Contacto
                </a>
            </div>
        </div>
    </div>

</x-layouts.site>
