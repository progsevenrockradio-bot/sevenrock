@props(['agencies' => collect()])

@if($agencies && $agencies->isNotEmpty())
    <section class="py-12 bg-[#08080a] border-t border-white/5 overflow-hidden relative">
        <!-- Título de Sección -->
        <div class="mx-auto max-w-[1200px] px-6 mb-8 text-center">
            <h2 class="font-display text-[1.65rem] uppercase tracking-[.06em] text-white">
                AGENCIAS <span class="text-[var(--lucille-accent)]">COLABORADORAS</span>
            </h2>
            <p class="mt-2 text-[9px] uppercase tracking-[.3em] text-[#8a8a8a]">
                ALIANZAS Y REPRESENTACIONES
            </p>
        </div>

        <!-- Contenedor del Marquee -->
        <div class="relative w-full flex items-center justify-center py-4 select-none">
            <!-- Degradados de sombra lateral para efecto premium -->
            <div class="absolute inset-y-0 left-0 w-24 bg-gradient-to-r from-[#08080a] to-transparent z-10 pointer-events-none"></div>
            <div class="absolute inset-y-0 right-0 w-24 bg-gradient-to-l from-[#08080a] to-transparent z-10 pointer-events-none"></div>

            <div class="w-full overflow-hidden">
                <div class="flex gap-16 items-center animate-marquee">
                    <!-- Duplicamos los logos para lograr un scroll infinito fluido -->
                    @foreach($agencies->concat($agencies) as $agency)
                        <a href="{{ route('agency.public-profile', $agency->slug) }}" 
                           class="flex shrink-0 items-center justify-center h-16 w-36 px-4 group transition-all duration-300"
                           title="{{ $agency->name }}">
                            <img src="{{ $agency->logo_url }}" 
                                 alt="{{ $agency->name }}" 
                                 class="max-h-full max-w-full object-contain filter grayscale opacity-60 group-hover:grayscale-0 group-hover:opacity-100 group-hover:drop-shadow-[0_0_12px_rgba(195,39,32,0.5)] transition-all duration-500" 
                                 loading="lazy">
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    @once
        @push('styles')
            <style>
                @keyframes marquee {
                    0% {
                        transform: translateX(0);
                    }
                    100% {
                        transform: translateX(calc(-50% - 2rem)); /* Compensa la mitad de la lista duplicada + espaciado */
                    }
                }
                .animate-marquee {
                    display: flex;
                    width: max-content;
                    animation: marquee 20s linear infinite;
                }
                .animate-marquee:hover {
                    animation-play-state: paused;
                }
            </style>
        @endpush
    @endonce
@endif
