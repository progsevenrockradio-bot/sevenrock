@props(['program'])

<div class="mt-[60px] grid gap-6 lg:grid-cols-[1.15fr_.85fr]">
    <article class="home-panel group overflow-hidden">
        <div class="home-program-hero" style="background-image: url('{{ asset($program['image']) }}');">
            <div class="home-program-hero-overlay"></div>
            <div class="home-program-hero-content">
                <span class="home-badge">{{ $program['badge'] }}</span>
                <h3 class="mt-3 font-display text-[30px] uppercase leading-none tracking-[.12em] md:text-[42px]">{!! formatear_titulo($program['title']) !!}</h3>
                <div class="mt-4 text-sm uppercase tracking-[.22em] text-[#dcdcdc]">{{ $program['schedule'] }}</div>
                <div class="mt-3 font-display text-sm uppercase tracking-[.16em] text-lucille-accent">{{ $program['host'] }}</div>
                <p class="mt-5 max-w-2xl text-[15px] leading-8 text-[#d8d8d8]">{{ $program['summary'] }}</p>
                <a href="{{ $program['button']['url'] }}" class="lucille-button-solid mt-7">{{ $program['button']['label'] }}</a>
            </div>
        </div>
    </article>

    <aside class="home-panel p-0">
        <div class="border-b border-[#2b2b2b] px-6 py-5">
            <div class="font-display text-sm uppercase tracking-[.22em] text-[#dcdcdc]">On deck</div>
            <div class="mt-2 text-sm text-[#7b7b7b]">{{ $program['timezone'] }}</div>
        </div>

        <div class="divide-y divide-[#2b2b2b]">
            @foreach ($program['upcoming'] as $slot)
                <article class="flex gap-4 px-6 py-5 transition-colors duration-300 hover:bg-[rgba(255,255,255,.02)]">
                    <div class="h-20 w-20 shrink-0 overflow-hidden border border-[#2b2b2b] bg-[#111]">
                        <img src="{{ asset($slot['image']) }}" alt="{{ $slot['title'] }}" class="h-full w-full object-cover">
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-xs uppercase tracking-[.2em] text-[#7b7b7b]">{{ $slot['time'] }}</div>
                        <div class="mt-1 font-display text-[18px] uppercase tracking-[.1em] text-[#dcdcdc]">{{ $slot['title'] }}</div>
                        <div class="mt-1 text-sm text-[#7b7b7b]">{{ $slot['host'] }}</div>
                    </div>
                </article>
            @endforeach
        </div>
    </aside>
</div>
