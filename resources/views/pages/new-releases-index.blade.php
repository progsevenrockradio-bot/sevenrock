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

    <section class="py-16 bg-[#0a0a0c]" x-data="{ activeAudioId: null }">
        <div class="mx-auto max-w-[1200px] px-6">
            
            @if ($newReleases->isEmpty())
                <div class="border border-dashed border-white/10 rounded-[16px] py-16 text-center text-[#7b7b7b]">
                    <p class="text-sm">No hay lanzamientos publicados todavía. ¡Vuelve pronto!</p>
                </div>
            @else
                <!-- Mosaico Asimétrico Masonry Real (CSS Column Count) -->
                <div class="columns-1 sm:columns-2 md:columns-3 lg:columns-4 gap-6 space-y-6">
                    @foreach($newReleases as $release)
                        <div id="release-card-{{ $release->id }}"
                             :class="{
                                 'is-playing border-[#d946ef] ring-4 ring-[#d946ef]/50 shadow-[0_0_35px_rgba(217,70,239,0.6)] z-30 scale-[1.02]': activeAudioId === {{ $release->id }},
                                 'border-white/10 hover:border-[#d946ef]/60 hover:shadow-[0_0_25px_rgba(217,70,239,0.3)]': activeAudioId !== {{ $release->id }}
                             }"
                             class="break-inside-avoid relative bg-[#121215] border p-3.5 transition-all duration-500 rounded-[16px] overflow-hidden group flex flex-col justify-between">
                            
                            <!-- Indicador Sonando / Play (Fucsia Badge) -->
                            <div x-show="activeAudioId === {{ $release->id }}" x-cloak class="absolute top-5 left-5 z-30 bg-[#d946ef] text-white text-[9px] font-bold uppercase tracking-widest px-3 py-1 rounded-full shadow-xl flex items-center gap-1.5 animate-pulse">
                                <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
                                Sonando
                            </div>

                            <!-- Portada Limpia de Altura Variable / Aspect Ratio -->
                            <div class="relative w-full overflow-hidden border border-white/10 bg-black rounded-[12px] group/cover">
                                <img src="{{ $release->cover_image_url }}" alt="{{ $release->title }}" class="w-full h-auto object-cover transition-transform duration-700 group-hover:scale-105" loading="lazy">
                                
                                <!-- Overlay Interactivo de Reproducción -->
                                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center gap-3">
                                    @if($release->audio_url)
                                        <button type="button"
                                                @click="
                                                    if (activeAudioId === {{ $release->id }}) {
                                                        activeAudioId = null;
                                                    } else {
                                                        activeAudioId = {{ $release->id }};
                                                    }
                                                "
                                                class="w-12 h-12 rounded-full bg-[#d946ef] text-white flex items-center justify-center shadow-[0_0_20px_rgba(217,70,239,0.8)] hover:scale-110 transition-transform"
                                                title="Reproducir / Pausar">
                                            <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24">
                                                <path x-show="activeAudioId !== {{ $release->id }}" d="M8 5v14l11-7z"/>
                                                <path x-show="activeAudioId === {{ $release->id }}" x-cloak d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                                            </svg>
                                        </button>
                                    @endif

                                    @if($release->youtube_url)
                                        <a href="{{ $release->youtube_url }}" target="_blank" rel="noreferrer" class="w-10 h-10 rounded-full bg-black/80 border border-white/20 text-[#FF0000] flex items-center justify-center hover:scale-110 transition-transform" title="Ver en YouTube">
                                            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                                                <path d="M23.498 6.163a3.003 3.003 0 00-2.11-2.11C19.517 3.545 12 3.545 12 3.545s-7.517 0-9.388.508a3.003 3.003 0 00-2.11 2.11C0 8.033 0 12 0 12s0 3.967.502 5.837a3.003 3.003 0 002.11 2.11c1.871.508 9.388.508 9.388.508s7.517 0 9.388-.508a3.003 3.003 0 002.11-2.11C24 15.967 24 12 24 12s0-3.967-.502-5.837zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                            </svg>
                                        </a>
                                    @endif
                                </div>
                            </div>

                            <!-- Información Estética Limpia (Sin reproductores nativos pesados) -->
                            <div class="mt-3 flex items-start justify-between gap-2">
                                <div class="overflow-hidden">
                                    <h4 class="font-display text-[15px] uppercase tracking-[.06em] text-[#e4e4e7] truncate group-hover:text-[#d946ef] transition-colors" title="{{ $release->title }}">
                                        {{ $release->title }}
                                    </h4>
                                    <p class="text-[12px] uppercase tracking-[.15em] text-[#d946ef] truncate font-semibold mt-0.5" title="{{ $release->artist_name }}">
                                        {{ $release->artist_name }}
                                    </p>
                                </div>

                                <a href="{{ route('new-releases.single', $release->slug) }}" class="shrink-0 text-[10px] uppercase tracking-[.18em] text-gray-400 hover:text-[#d946ef] transition-colors font-medium border border-white/10 px-2 py-1 rounded">
                                    Ver &rarr;
                                </a>
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
