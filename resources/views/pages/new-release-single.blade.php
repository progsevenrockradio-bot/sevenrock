@php
    $shareUrl = request()->fullUrl();
    $shareTitle = trim((string) ($newRelease->title . ' - ' . $newRelease->artist_name));
    $twitterShareUrl = 'https://twitter.com/intent/tweet?text=' . rawurlencode($shareTitle) . '&url=' . rawurlencode($shareUrl);
    $facebookShareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($shareUrl);
@endphp

<x-layouts.site 
    title="Seven Rock Radio - {{ $newRelease->title }} de {{ $newRelease->artist_name }}"
    :og-image="$newRelease->cover_image_url"
    :description="\Illuminate\Support\Str::limit(strip_tags($newRelease->description ?? 'Nuevo lanzamiento en Seven Rock Radio'), 150)"
>
    <section class="lucille-event-single-shell">
        <div class="mx-auto max-w-[1200px] px-6 pt-6">
            <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.62)] px-6 py-8 text-center md:px-10 md:py-10">
                <div class="mb-4 flex flex-wrap justify-center gap-2 text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">
                    <span class="border border-[#2b2b2b] bg-[rgba(255,255,255,.02)] px-3 py-1 text-[#bcbcbc]">Nuevo Lanzamiento</span>
                </div>

                <h1 class="font-display text-4xl uppercase tracking-[.12em] text-[#dcdcdc] md:text-[3.5rem]">{{ $newRelease->title }}</h1>
                <p class="mt-3 text-[11px] uppercase tracking-[.36em] text-[#c32720] font-semibold">{{ $newRelease->artist_name }}</p>
                
                @if($newRelease->released_at)
                    <div class="mt-5 flex flex-wrap justify-center gap-3 text-[12px] uppercase tracking-[.18em] text-[#dcdcdc]">
                        <span class="border border-[#2b2b2b] px-4 py-2">Lanzamiento: {{ $newRelease->released_at->translatedFormat('d F, Y') }}</span>
                    </div>
                @endif
            </div>

            <div class="mt-7 grid gap-8 lg:grid-cols-[minmax(0,.92fr)_minmax(400px,540px)]">
                <!-- Columna Izquierda: Detalles, Audio y Reseña -->
                <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.7)] p-5 md:p-7 flex flex-col justify-between">
                    <div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="lucille-event-detail-entry mb-0">
                                <span class="text-xs uppercase tracking-wider text-[#555] block">Artista</span>
                                <span class="text-white text-sm font-semibold">{{ $newRelease->artist_name }}</span>
                            </div>
                            
                            @if($newRelease->radioArtist)
                                <div class="lucille-event-detail-entry mb-0">
                                    <span class="text-xs uppercase tracking-wider text-[#555] block">Perfil de Banda</span>
                                    <span class="text-white text-sm font-semibold">{{ $newRelease->radioArtist->name }}</span>
                                </div>
                            @endif
                            
                            @if($newRelease->radioArtist && $newRelease->radioArtist->agency)
                                <div class="lucille-event-detail-entry mb-0">
                                    <span class="text-xs uppercase tracking-wider text-[#555] block">Representante</span>
                                    <span class="text-white text-sm font-semibold">
                                        <a href="{{ route('agency.public-profile', $newRelease->radioArtist->agency->slug) }}" class="hover:text-[var(--lucille-accent)] transition-colors underline">
                                            {{ $newRelease->radioArtist->agency->name }}
                                        </a>
                                    </span>
                                </div>
                            @endif
                            @if($newRelease->released_at)
                                <div class="lucille-event-detail-entry mb-0">
                                    <span class="text-xs uppercase tracking-wider text-[#555] block">Fecha</span>
                                    <span class="text-white text-sm">{{ $newRelease->released_at->translatedFormat('d F, Y') }}</span>
                                </div>
                            @endif
                        </div>

                        <!-- Audio Player -->
                        @if($newRelease->audio_url)
                            <div class="mt-6 border-t border-[#222] pt-6">
                                <span class="text-xs uppercase tracking-wider text-[#7b7b7b] block mb-2">Escuchar Pista de Audio</span>
                                <audio src="{{ $newRelease->audio_url }}" controls class="w-full h-10 accent-[#c32720] dark-audio" controlsList="nodownload"></audio>
                            </div>
                        @endif

                        <!-- Reseña / Contenido -->
                        <div class="mt-6 border-t border-[#222] pt-6">
                            <span class="text-xs uppercase tracking-wider text-[#7b7b7b] block mb-3">Reseña del Lanzamiento</span>
                            <div class="space-y-4 text-[14px] leading-6 text-lucille-body whitespace-pre-line">
                                @if($newRelease->description)
                                    {{ $newRelease->description }}
                                @else
                                    <p class="italic text-gray-600">No hay reseña escrita aún para este lanzamiento.</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Enlaces y Compartir -->
                    <div class="mt-8 pt-6 border-t border-[#222]">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <!-- Plataformas de música -->
                            <div class="flex items-center gap-3">
                                @if($newRelease->spotify_url)
                                    <a href="{{ $newRelease->spotify_url }}" target="_blank" rel="noreferrer" class="lucille-button flex items-center gap-2 text-[#1DB954] border-[#1DB954]/30 hover:bg-[#1DB954]/10">
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.02.24-2.82-1.74-6.36-2.129-10.56-1.17-.419.09-.81-.179-.9-.6-.09-.42.18-.81.6-.9 4.62-1.051 8.58-.6 11.76 1.348.36.24.48.66.24 1.022zm1.44-3.3c-.3.42-.84.6-1.26.3-3.24-1.98-8.16-2.58-12-1.38-.479.12-.99-.12-1.11-.6-.12-.48.12-.99.6-1.11 4.38-1.32 9.78-.6 13.5 1.68.42.24.6.78.27 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.3c-.6.18-1.26-.18-1.44-.78-.18-.6.18-1.26.78-1.44 4.26-1.29 11.34-1.02 15.84 1.65.54.3.72 1.02.42 1.56-.3.48-1.02.72-1.56.42z"/>
                                        </svg>
                                        Spotify
                                    </a>
                                @endif
                                @if($newRelease->youtube_url)
                                    <a href="{{ $newRelease->youtube_url }}" target="_blank" rel="noreferrer" class="lucille-button flex items-center gap-2 text-[#FF0000] border-[#FF0000]/30 hover:bg-[#FF0000]/10">
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M23.498 6.163a3.003 3.003 0 00-2.11-2.11C19.517 3.545 12 3.545 12 3.545s-7.517 0-9.388.508a3.003 3.003 0 00-2.11 2.11C0 8.033 0 12 0 12s0 3.967.502 5.837a3.003 3.003 0 002.11 2.11c1.871.508 9.388.508 9.388.508s7.517 0 9.388-.508a3.003 3.003 0 002.11-2.11C24 15.967 24 12 24 12s0-3.967-.502-5.837zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                        </svg>
                                        YouTube
                                    </a>
                                @endif
                            </div>

                            <!-- Redes sociales compartir -->
                            <div class="flex items-center gap-3 text-[#757575]">
                                <span class="font-display text-sm uppercase tracking-[.08em] text-[#dcdcdc]">Compartir:</span>
                                <a href="{{ $twitterShareUrl }}" target="_blank" rel="noopener noreferrer" class="transition duration-300 hover:text-[#c32720]">Twitter</a>
                                <a href="{{ $facebookShareUrl }}" target="_blank" rel="noopener noreferrer" class="transition duration-300 hover:text-[#c32720]">Facebook</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Portada e incrustación de video -->
                <div class="space-y-4 lg:-mt-4">
                    <!-- Portada -->
                    <div class="border border-[#2b2b2b] bg-black/40 p-2">
                        <img
                            src="{{ $newRelease->cover_image_url }}"
                            alt="{{ $newRelease->title }}"
                            class="event-single-poster h-auto w-full object-cover border border-[#2b2b2b]"
                            loading="lazy">
                    </div>

                    <!-- Video YouTube Incrustado -->
                    @if($newRelease->youtube_embed_url)
                        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.7)] p-2">
                            <div class="relative aspect-video overflow-hidden border border-[#2b2b2b] bg-[#111]">
                                <iframe
                                    src="{{ $newRelease->youtube_embed_url }}"
                                    title="{{ $newRelease->title }}"
                                    class="absolute inset-0 h-full w-full"
                                    allowfullscreen
                                ></iframe>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</x-layouts.site>
