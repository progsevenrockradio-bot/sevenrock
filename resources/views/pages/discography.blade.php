<x-layouts.site title="Seven Rock Radio - Discografía">
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
                        <a href="{{ route('talents.album.show', ['id' => $album->id, 'slug' => $album->slug]) }}" class="lucille-album-card group">
                            <img src="{{ $album->coverUrl() ?? asset('assets/lucille/man-597179_1920.jpg') }}" alt="{{ $album->title }}">
                            <span class="lucille-album-overlay"></span>
                            <h3 class="lucille-album-title">{{ $album->title }}</h3>
                            <h3 class="lucille-album-artist">{{ $album->talent->band_name ?? 'Artista' }}</h3>
                            @if ($album->talent)
                                <span class="absolute bottom-4 right-4 z-10 text-[10px] uppercase tracking-[.15em] text-white/60 group-hover:text-lucille-accent">
                                    Escuchar preview →
                                </span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-layouts.site>