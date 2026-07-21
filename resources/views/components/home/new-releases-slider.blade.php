@props(['releases' => collect()])

@if($releases && $releases->isNotEmpty())
<x-sections.background-band class="home-section-texture home-section-dark border-t border-[#222]">
    <div class="pt-[80px] pb-[80px]">
        <div class="mx-auto max-w-[1200px] px-6">
            <div class="flex items-center justify-between">
                <x-ui.section-heading title="Nuevos" accent="Lanzamientos" subtitle="Música fresca de la escena independiente y del rock" />
                <a href="{{ route('new-releases.index') }}" class="hidden sm:inline-flex items-center text-xs font-display uppercase tracking-[.18em] text-[#dcdcdc] hover:text-[#c32720] transition-colors">
                    Ver todos los lanzamientos &rarr;
                </a>
            </div>

            <!-- Slider Container (Alpine.js Carousel) -->
            <div class="relative mt-8 group/slider" 
                 x-data="{ 
                    scrollLeft() { 
                        $refs.carousel.scrollBy({ left: -320, behavior: 'smooth' }) 
                    },
                    scrollRight() { 
                        $refs.carousel.scrollBy({ left: 320, behavior: 'smooth' }) 
                    }
                 }">
                
                <!-- Flechas de navegación -->
                <button @click="scrollLeft()" 
                        type="button"
                        aria-label="Anterior"
                        class="absolute -left-4 top-1/2 -translate-y-1/2 z-30 hidden md:flex h-10 w-10 items-center justify-center rounded-full bg-[#101012]/90 border border-[#2b2b2b] text-white/80 opacity-0 group-hover/slider:opacity-100 transition-all hover:bg-[#c32720] hover:border-[#c32720] hover:text-white shadow-lg">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>

                <button @click="scrollRight()" 
                        type="button"
                        aria-label="Siguiente"
                        class="absolute -right-4 top-1/2 -translate-y-1/2 z-30 hidden md:flex h-10 w-10 items-center justify-center rounded-full bg-[#101012]/90 border border-[#2b2b2b] text-white/80 opacity-0 group-hover/slider:opacity-100 transition-all hover:bg-[#c32720] hover:border-[#c32720] hover:text-white shadow-lg">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>

                <!-- Track de Swiper/Scroll horizontal -->
                <div x-ref="carousel" 
                     class="flex gap-6 overflow-x-auto scroll-smooth no-scrollbar py-4 px-1 snap-x snap-mandatory">
                    @foreach($releases as $release)
                        <div class="snap-start shrink-0 w-[240px] sm:w-[260px] md:w-[270px] border border-[#2b2b2b] bg-[rgba(16,16,18,.9)] p-4 flex flex-col justify-between transition-all duration-300 hover:-translate-y-2 hover:border-[#c32720]/50 group rounded-[12px] shadow-[0_10px_30px_rgba(0,0,0,0.5)] relative">
                            <div>
                                <!-- Badge "NUEVO" -->
                                <div class="absolute top-6 right-6 z-20 bg-[#c32720] text-white font-display text-[9px] uppercase tracking-wider px-2 py-0.5 rounded shadow">
                                    Nuevo
                                </div>

                                <!-- Portada -->
                                <div class="relative aspect-square overflow-hidden border border-[#2b2b2b] bg-[#111] rounded-[8px]">
                                    <img src="{{ $release->cover_image_url }}" 
                                         alt="{{ $release->title }}" 
                                         class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" 
                                         loading="lazy">
                                    
                                    @if($release->youtube_url)
                                        <a href="{{ $release->youtube_url }}" target="_blank" rel="noreferrer" class="absolute inset-0 flex items-center justify-center bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300 rounded-[8px] z-10">
                                            <svg class="h-12 w-12 text-[#c32720] hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M23.498 6.163a3.003 3.003 0 00-2.11-2.11C19.517 3.545 12 3.545 12 3.545s-7.517 0-9.388.508a3.003 3.003 0 00-2.11 2.11C0 8.033 0 12 0 12s0 3.967.502 5.837a3.003 3.003 0 002.11 2.11c1.871.508 9.388.508 9.388.508s7.517 0 9.388-.508a3.003 3.003 0 002.11-2.11C24 15.967 24 12 24 12s0-3.967-.502-5.837zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                            </svg>
                                        </a>
                                    @endif
                                </div>

                                <!-- Metadata -->
                                <h4 class="mt-4 font-display text-[15px] uppercase tracking-[.08em] text-[#dcdcdc] line-clamp-1 group-hover:text-[#c32720] transition-colors" title="{{ $release->title }}">
                                    {{ $release->title }}
                                </h4>
                                <p class="text-[12px] uppercase tracking-[.18em] text-[#c32720] line-clamp-1 mt-1 font-semibold" title="{{ $release->artist_name }}">
                                    {{ $release->artist_name }}
                                </p>

                                @if($release->released_at)
                                    <p class="text-[10px] uppercase tracking-[.12em] text-[#666] mt-1">
                                        {{ $release->released_at->translatedFormat('d M, Y') }}
                                    </p>
                                @endif
                            </div>

                            <!-- Footer / Links -->
                            <div class="mt-4 pt-3 border-t border-[#222] flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    @if($release->spotify_url)
                                        <a href="{{ $release->spotify_url }}" target="_blank" rel="noreferrer" class="text-[#1DB954] hover:scale-110 transition-transform" title="Escuchar en Spotify">
                                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.02.24-2.82-1.74-6.36-2.129-10.56-1.17-.419.09-.81-.179-.9-.6-.09-.42.18-.81.6-.9 4.62-1.051 8.58-.6 11.76 1.348.36.24.48.66.24 1.022zm1.44-3.3c-.3.42-.84.6-1.26.3-3.24-1.98-8.16-2.58-12-1.38-.479.12-.99-.12-1.11-.6-.12-.48.12-.99.6-1.11 4.38-1.32 9.78-.6 13.5 1.68.42.24.6.78.27 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.3c-.6.18-1.26-.18-1.44-.78-.18-.6.18-1.26.78-1.44 4.26-1.29 11.34-1.02 15.84 1.65.54.3.72 1.02.42 1.56-.3.48-1.02.72-1.56.42z"/>
                                            </svg>
                                        </a>
                                    @endif
                                    @if($release->youtube_url)
                                        <a href="{{ $release->youtube_url }}" target="_blank" rel="noreferrer" class="text-[#FF0000] hover:scale-110 transition-transform" title="Ver en YouTube">
                                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M23.498 6.163a3.003 3.003 0 00-2.11-2.11C19.517 3.545 12 3.545 12 3.545s-7.517 0-9.388.508a3.003 3.003 0 00-2.11 2.11C0 8.033 0 12 0 12s0 3.967.502 5.837a3.003 3.003 0 002.11 2.11c1.871.508 9.388.508 9.388.508s7.517 0 9.388-.508a3.003 3.003 0 002.11-2.11C24 15.967 24 12 24 12s0-3.967-.502-5.837zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                            </svg>
                                        </a>
                                    @endif
                                </div>
                                <a href="{{ route('new-releases.single', $release->slug) }}" class="text-[11px] uppercase tracking-[.18em] text-[#dcdcdc] hover:text-[#c32720] transition-colors">
                                    Detalles &rarr;
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Botón responsive centrado en móvil -->
            <div class="mt-6 text-center sm:hidden">
                <a href="{{ route('new-releases.index') }}" class="inline-flex items-center text-xs font-display uppercase tracking-[.18em] text-[#c32720] hover:text-white transition-colors">
                    Ver todos los lanzamientos &rarr;
                </a>
            </div>
        </div>
    </div>
</x-sections.background-band>
@endif
