<x-layouts.site title="Seven Rock Radio - Discography">
    <x-sections.page-heading
        title="Discography"
        subtitle="Our Music"
        image="assets/lucille/string-555070.jpg"
        overlay="rgba(25,25,25,.83)"
    />

    <section>
        <div class="lucille-content-box">
            <div class="lucille-albums-grid md:grid-cols-2 lg:grid-cols-3">
                @foreach ($albums as $album)
                    <a href="{{ route('albums.single', ['slug' => $album->slug]) }}" class="lucille-album-card">
                        <img src="{{ $album->cover_image_url }}" alt="{{ $album->title }}">
                        <span class="lucille-album-overlay"></span>
                        <h3 class="lucille-album-title">{{ $album->title }}</h3>
                        <h3 class="lucille-album-artist">{{ $album->artist }}</h3>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
</x-layouts.site>
