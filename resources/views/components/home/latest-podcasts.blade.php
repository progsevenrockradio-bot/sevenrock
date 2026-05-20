@props(['podcasts'])

@php
    $featuredImage = \App\Support\PublicMediaUrl::normalizePublicUrl(data_get($podcasts, 'featured.image', ''));
    $featuredUrl = trim((string) data_get($podcasts, 'featured.url', ''));
@endphp

<div class="mt-[60px] grid gap-6 lg:grid-cols-[1fr_1fr]">
    <article class="home-panel overflow-hidden">
        <div class="home-podcast-feature">
            <div class="home-podcast-cover">
                <img src="{{ $featuredImage !== '' ? $featuredImage : asset('assets/lucille/logo.png') }}" alt="{{ $podcasts['featured']['title'] }}" class="h-full w-full object-cover">
            </div>

            <div class="home-podcast-copy">
                <div class="home-badge">Nuevo episodio</div>
                <h3 class="mt-3 font-display text-[34px] uppercase leading-none tracking-[.12em]">{!! formatear_titulo($podcasts['featured']['title']) !!}</h3>
                <div class="mt-4 text-xs uppercase tracking-[.24em] text-[#7b7b7b]">{{ $podcasts['featured']['episode'] }} · {{ $podcasts['featured']['date'] }}</div>
                <div class="mt-3 font-display text-sm uppercase tracking-[.16em] text-lucille-accent">{{ $podcasts['featured']['host'] }}</div>
                <p class="mt-5 max-w-xl text-[15px] leading-8 text-[#d8d8d8]">{{ $podcasts['featured']['summary'] }}</p>
                <a href="{{ $featuredUrl !== '' ? $featuredUrl : route('events') }}" target="_blank" rel="noopener" class="lucille-button-solid mt-7">Escuchar</a>
            </div>
        </div>
    </article>

    <div class="grid gap-4">
        @foreach ($podcasts['episodes'] as $episode)
            @php
                $episodeImage = \App\Support\PublicMediaUrl::normalizePublicUrl(data_get($episode, 'image', ''));
                $episodeUrl = trim((string) data_get($episode, 'url', ''));
            @endphp
            @if ($episodeUrl !== '')
                <a href="{{ $episodeUrl }}" target="_blank" rel="noopener" class="home-podcast-row group flex-col md:flex-row">
                    <div class="home-podcast-row-media">
                        <img src="{{ $episodeImage !== '' ? $episodeImage : asset('assets/lucille/logo.png') }}" alt="{{ $episode['title'] }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]">
                    </div>
                    <div class="min-w-0 flex-1 px-5 py-5">
                        <div class="text-xs uppercase tracking-[.22em] text-[#7b7b7b]">{{ $episode['episode'] }} · {{ $episode['date'] }}</div>
                        <h4 class="mt-2 font-display text-[20px] uppercase leading-none tracking-[.12em] text-[#dcdcdc] transition group-hover:text-lucille-accent">{!! formatear_titulo($episode['title']) !!}</h4>
                        <p class="mt-3 text-sm text-[#7b7b7b]">Episodio listo para escuchar desde la portada.</p>
                    </div>
                </a>
            @else
                <article class="home-podcast-row group flex-col md:flex-row">
                    <div class="home-podcast-row-media">
                        <img src="{{ $episodeImage !== '' ? $episodeImage : asset('assets/lucille/logo.png') }}" alt="{{ $episode['title'] }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]">
                    </div>
                    <div class="min-w-0 flex-1 px-5 py-5">
                        <div class="text-xs uppercase tracking-[.22em] text-[#7b7b7b]">{{ $episode['episode'] }} · {{ $episode['date'] }}</div>
                        <h4 class="mt-2 font-display text-[20px] uppercase leading-none tracking-[.12em] text-[#dcdcdc] transition group-hover:text-lucille-accent">{!! formatear_titulo($episode['title']) !!}</h4>
                        <p class="mt-3 text-sm text-[#7b7b7b]">Episodio listo para escuchar desde la portada.</p>
                    </div>
                </article>
            @endif
        @endforeach
    </div>
</div>
