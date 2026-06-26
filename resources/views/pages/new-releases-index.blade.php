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

    <section class="py-16 bg-[#0c0c0e]">
        <div class="mx-auto max-w-[1200px] px-6">
            
            @if ($newReleases->isEmpty())
                <div class="border border-dashed border-white/10 rounded-[16px] py-16 text-center text-[#7b7b7b]">
                    <p class="text-sm">No hay lanzamientos publicados todavía. ¡Vuelve pronto!</p>
                </div>
            @else
                <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach($newReleases as $release)
                        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.8)] p-4 flex flex-col justify-between transition-all duration-300 hover:-translate-y-2 hover:border-[#c32720]/40 group rounded-[12px] shadow-lg">
                            <div>
                                <!-- Portada -->
                                <div class="relative aspect-square overflow-hidden border border-[#2b2b2b] bg-[#111] rounded-[8px]">
                                    <img src="{{ $release->cover_image_url }}" alt="{{ $release->title }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy">
                                    @if($release->youtube_url)
                                        <a href="{{ $release->youtube_url }}" target="_blank" rel="noreferrer" class="absolute inset-0 flex items-center justify-center bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300 rounded-[8px]">
                                            <svg class="h-12 w-12 text-[#c32720] hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M23.498 6.163a3.003 3.003 0 00-2.11-2.11C19.517 3.545 12 3.545 12 3.545s-7.517 0-9.388.508a3.003 3.003 0 00-2.11 2.11C0 8.033 0 12 0 12s0 3.967.502 5.837a3.003 3.003 0 002.11 2.11c1.871.508 9.388.508 9.388.508s7.517 0 9.388-.508a3.003 3.003 0 002.11-2.11C24 15.967 24 12 24 12s0-3.967-.502-5.837zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                            </svg>
                                        </a>
                                    @endif
                                </div>

                                <!-- Meta -->
                                <h4 class="mt-4 font-display text-[16px] uppercase tracking-[.08em] text-[#dcdcdc] line-clamp-1 group-hover:text-[#c32720] transition-colors">{{ $release->title }}</h4>
                                <p class="text-[12px] uppercase tracking-[.18em] text-[#c32720] line-clamp-1 mt-1">{{ $release->artist_name }}</p>

                                @if($release->released_at)
                                    <p class="text-[10px] uppercase tracking-[.12em] text-[#555] mt-1">{{ $release->released_at->translatedFormat('d M, Y') }}</p>
                                @endif

                                @if($release->description)
                                    <p class="mt-3 text-xs leading-5 text-[#7b7b7b] line-clamp-3 select-text">{{ strip_tags(str_replace(['\r\n', '\r', '\n'], ' ', $release->description ?? '')) }}</p>
                                @endif
                            </div>

                            <div>
                                <!-- Audio Player -->
                                @if($release->audio_url)
                                    <div class="mt-4 border-t border-[#222] pt-4">
                                        <audio src="{{ $release->audio_url }}" controls class="w-full h-8 accent-[#c32720] dark-audio" controlsList="nodownload"></audio>
                                    </div>
                                @endif

                                <!-- Action Links -->
                                <div class="mt-4 flex items-center justify-between border-t border-[#222] pt-3">
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
                                    <a href="{{ route('new-releases.single', $release->slug) }}" class="text-[11px] uppercase tracking-[.18em] text-[#dcdcdc] hover:text-[#c32720] transition-colors">Ver Detalles &rarr;</a>
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
