<x-layouts.site 
    title="Seven Rock Radio - Lanzamientos de Rock" 
    description="Explora todos los nuevos lanzamientos y canciones destacadas de bandas independientes y emergentes en la señal de Seven Rock Radio."
>
    <x-sections.page-heading
        title="Nuevos Lanzamientos"
        subtitle="Música fresca de la escena independiente"
        image="assets/lucille/guitar-1758005_1920.jpg"
        overlay="rgba(16,16,18,.85)"
    />

    <section class="py-16 bg-[#0a0a0c]" x-data="{ activeAudioId: null, activeAudioEl: null }">
        <div class="mx-auto max-w-[1200px] px-6">
            
            @if ($newReleases->isEmpty())
                <div class="border border-dashed border-white/10 rounded-[16px] py-16 text-center text-[#7b7b7b]">
                    <p class="text-sm">No hay lanzamientos publicados todavía. ¡Vuelve pronto!</p>
                </div>
            @else
                <!-- Mosaico Asimétrico Masonry Grid (grid-auto-flow: dense) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 auto-rows-[280px] grid-flow-row-dense">
                    @foreach($newReleases as $index => $release)
                        @php
                            // Patrón asimétrico de celdas (celdas destacadas 2x2, 2x1 o 1x2)
                            $spanClass = 'col-span-1 row-span-1';
                            if ($index % 5 === 0) {
                                $spanClass = 'sm:col-span-2 sm:row-span-2'; // Tarjeta Grande
                            } elseif ($index % 5 === 2) {
                                $spanClass = 'sm:col-span-2 sm:row-span-1'; // Tarjeta Ancha
                            } elseif ($index % 5 === 4) {
                                $spanClass = 'sm:col-span-1 sm:row-span-2'; // Tarjeta Alta
                            }
                        @endphp

                        <div id="release-card-{{ $release->id }}"
                             :class="{
                                 'border-[#d946ef] ring-4 ring-[#d946ef]/40 shadow-[0_0_30px_rgba(217,70,239,0.5)] z-30 font-bold scale-[1.02]': activeAudioId === {{ $release->id }},
                                 'border-[#2b2b2b] hover:border-[#d946ef]/60 hover:shadow-[0_0_20px_rgba(217,70,239,0.25)]': activeAudioId !== {{ $release->id }}
                             }"
                             class="{{ $spanClass }} relative bg-[#121215] border p-4 flex flex-col justify-between transition-all duration-500 rounded-[14px] overflow-hidden group">
                            
                            <!-- Indicador Sonando (Fucsia Badge) -->
                            <div x-show="activeAudioId === {{ $release->id }}" x-cloak class="absolute top-3 left-3 z-30 bg-[#d946ef] text-white text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full shadow-lg flex items-center gap-1.5 animate-pulse">
                                <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
                                Sonando Ahora
                            </div>

                            <div class="relative h-full flex flex-col justify-between z-20">
                                <!-- Portada e Imagen con Glow Fucsia en Hover -->
                                <div class="relative w-full h-full min-h-[140px] overflow-hidden border border-white/10 bg-[#000] rounded-[10px]">
                                    <img src="{{ $release->cover_image_url }}" alt="{{ $release->title }}" class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105" loading="lazy">
                                    
                                    @if($release->youtube_url)
                                        <a href="{{ $release->youtube_url }}" target="_blank" rel="noreferrer" class="absolute inset-0 flex items-center justify-center bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300 rounded-[10px]">
                                            <svg class="h-12 w-12 text-[#d946ef] hover:scale-110 transition-transform filter drop-shadow-[0_0_10px_rgba(217,70,239,0.8)]" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M23.498 6.163a3.003 3.003 0 00-2.11-2.11C19.517 3.545 12 3.545 12 3.545s-7.517 0-9.388.508a3.003 3.003 0 00-2.11 2.11C0 8.033 0 12 0 12s0 3.967.502 5.837a3.003 3.003 0 002.11 2.11c1.871.508 9.388.508 9.388.508s7.517 0 9.388-.508a3.003 3.003 0 002.11-2.11C24 15.967 24 12 24 12s0-3.967-.502-5.837zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                            </svg>
                                        </a>
                                    @endif
                                </div>

                                <!-- Metadata -->
                                <div class="mt-3">
                                    <h4 class="font-display text-[16px] uppercase tracking-[.08em] text-[#e4e4e7] line-clamp-1 group-hover:text-[#d946ef] transition-colors">{{ $release->title }}</h4>
                                    <p class="text-[12px] uppercase tracking-[.18em] text-[#d946ef] line-clamp-1 mt-0.5 font-semibold">{{ $release->artist_name }}</p>

                                    @if($release->released_at)
                                        <p class="text-[10px] uppercase tracking-[.12em] text-gray-500 mt-0.5">{{ $release->released_at->translatedFormat('d M, Y') }}</p>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-3 z-20">
                                <!-- Audio Player Conectado a Eventos y Anclaje -->
                                @if($release->audio_url)
                                    <div class="border-t border-white/10 pt-3">
                                        <audio id="audio-rel-{{ $release->id }}"
                                               src="{{ $release->audio_url }}" 
                                               controls 
                                               class="w-full h-8 accent-[#d946ef] dark-audio" 
                                               controlsList="nodownload"
                                               @play="
                                                    if (activeAudioEl && activeAudioEl !== $el) { activeAudioEl.pause(); }
                                                    activeAudioId = {{ $release->id }};
                                                    activeAudioEl = $el;
                                               "
                                               @pause="if (activeAudioId === {{ $release->id }}) { activeAudioId = null; }"
                                               @ended="if (activeAudioId === {{ $release->id }}) { activeAudioId = null; }">
                                        </audio>
                                    </div>
                                @endif

                                <!-- Action Links -->
                                <div class="mt-3 flex items-center justify-between border-t border-white/10 pt-2.5">
                                    <div class="flex gap-3">
                                        @if($release->spotify_url)
                                            <a href="{{ $release->spotify_url }}" target="_blank" rel="noreferrer" class="text-[#1DB954] hover:scale-110 transition-transform" title="Escuchar en Spotify">
                                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.02.24-2.82-1.74-6.36-2.129-10.56-1.17-.419.09-.81-.179-.9-.6-.09-.42.18-.81.6-.9 4.62-1.051 8.58-.6 11.76 1.348.36.24.48.66.24 1.022zm1.44-3.3c-.3.42-.84.6-1.26.3-3.24-1.98-8.16-2.58-12-1.38-.479.12-.99-.12-1.11-.6-.12-.48.12-.99.6-1.11 4.38-1.32 9.78-.6 13.5 1.68.42.24.6.78.27 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.3c-.6.18-1.26-.18-1.44-.78-.18-.6.18-1.26.78-1.44 4.26-1.29 11.34-1.02 15.84 1.65.54.3.72 1.02.42 1.56-.3.48-1.02.72-1.56.42z"/>
                                                </svg>
                                            </a>
                                        @endif
                                        @if($release->youtube_url)
                                            <a href="{{ $release->youtube_url }}" target="_blank" rel="noreferrer" class="text-[#FF0000] hover:scale-110 transition-transform" title="Ver en YouTube">
                                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M23.498 6.163a3.003 3.003 0 00-2.11-2.11C19.517 3.545 12 3.545 12 3.545s-7.517 0-9.388.508a3.003 3.003 0 00-2.11 2.11C0 8.033 0 12 0 12s0 3.967.502 5.837a3.003 3.003 0 002.11 2.11c1.871.508 9.388.508 9.388.508s7.517 0 9.388-.508a3.003 3.003 0 002.11-2.11C24 15.967 24 12 24 12s0-3.967-.502-5.837zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                                </svg>
                                            </a>
                                        @endif
                                    </div>
                                    <a href="{{ route('new-releases.single', $release->slug) }}" class="text-[11px] uppercase tracking-[.18em] text-gray-300 hover:text-[#d946ef] transition-colors font-medium">Ver Detalles &rarr;</a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Paginación -->
                <div class="mt-12 font-mono text-xs text-[#7b7b7b]">
                    {{ $newReleases->links() }}
                </div>
            @endif

        </div>
    </section>
</x-layouts.site>

