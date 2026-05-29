<x-layouts.site title="Seven Rock Radio - Discografía" description="Explora la discografía completa de Seven Rock Radio. Albumes, canciones y previews de 30 segundos de nuestros talentos y bandas favoritas.">
    <x-sections.page-heading
        title="Discografía"
        subtitle="Álbumes de nuestros talentos"
        image="assets/lucille/string-555070.jpg"
        overlay="rgba(25,25,25,.83)"
    />

    <section>
        <div class="lucille-content-box">
            @if ($albums->isEmpty())
                <div class="py-16 text-center text-sm text-[#7b7b7b]">
                    No hay álbumes publicados todavía.
                </div>
            @else
                <div class="lucille-albums-grid md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($albums as $album)
                        @php
                            $detailUrl = $album['type'] === 'admin'
                                ? route('albums.single', ['slug' => $album['slug']])
                                : '#' /* talent albums disabled */
                        @endphp
                        <a href="{{ $detailUrl }}" class="lucille-album-card group">
                            <img src="{{ $album['cover'] }}" alt="{{ $album['title'] }}" loading="lazy">
                            <span class="lucille-album-overlay"></span>
                            <h3 class="lucille-album-title">{{ $album['title'] }}</h3>
                            <h3 class="lucille-album-artist">{{ $album['artist'] }}</h3>
                            <span class="absolute bottom-4 right-4 z-10 text-[10px] uppercase tracking-[.15em] text-white/60 group-hover:text-lucille-accent">
                                Escuchar preview →
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-layouts.site>
