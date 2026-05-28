@props(['stories'])

<div class="mt-[60px] grid gap-6 xl:grid-cols-[1.05fr_.95fr]">
    <article class="home-panel overflow-hidden">
        <div class="home-story-hero" style="background-image: url('{{ asset($stories['featured']['image']) }}');">
            <div class="home-story-overlay"></div>
            <div class="home-story-content">
                <div class="home-badge">Perfil monitorizado</div>
                <div class="mt-3 text-xs uppercase tracking-[.24em] text-[#cfcfcf]">{{ $stories['featured']['type'] }}</div>
                <h3 class="mt-3 font-display text-[34px] uppercase leading-none tracking-[.12em] md:text-[46px]">{!! formatear_titulo($stories['featured']['title']) !!}</h3>
                <div class="mt-4 text-sm uppercase tracking-[.18em] text-[#dcdcdc]">{{ $stories['featured']['location'] }}</div>
                <p class="mt-5 max-w-xl text-[15px] leading-8 text-[#d8d8d8]">{{ $stories['featured']['summary'] }}</p>

                <div class="mt-7 grid gap-3 sm:grid-cols-2">
                    <div class="home-stat">{{ $stories['featured']['plays'] }}</div>
                    <div class="home-stat">{{ $stories['featured']['searches'] }}</div>
                </div>
            </div>
        </div>
    </article>

    <div class="grid gap-4">
        @foreach ($stories['stories'] as $story)
            <article class="home-story-row group flex-col md:flex-row">
                <div class="home-story-row-media">
                    <img src="{{ asset($story['image']) }}" alt="{{ $story['title'] }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]" loading="lazy">
                </div>
                <div class="min-w-0 flex-1 px-5 py-5">
                    <div class="text-xs uppercase tracking-[.22em] text-[#7b7b7b]">{{ $story['type'] }}</div>
                    <h4 class="mt-2 font-display text-[24px] uppercase leading-none tracking-[.12em] text-[#dcdcdc] transition group-hover:text-lucille-accent">{!! formatear_titulo($story['title']) !!}</h4>
                    <p class="mt-2 text-sm text-[#7b7b7b]">{{ $story['location'] }}</p>
                    <div class="mt-4 flex flex-wrap gap-3 text-xs uppercase tracking-[.18em] text-[#bdbdbd]">
                        <span>{{ $story['signal'] }}</span>
                        <span>{{ $story['searches'] }}</span>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
</div>
