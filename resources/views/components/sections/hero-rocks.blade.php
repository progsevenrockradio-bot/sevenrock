@props(['slides'])

<section
    x-data="{
        active: 0,
        slides: {{ Js::from($slides) }},
        interval: null,
        init() {
            if (this.slides.length < 2) return;
            this.interval = setInterval(() => this.next(), 6000);
        },
        next() {
            this.active = (this.active + 1) % this.slides.length;
        },
        go(index) {
            clearInterval(this.interval);
            this.active = index;
            this.interval = setInterval(() => this.next(), 6000);
        },
    }"
    x-init="init"
    class="relative min-h-[720px] overflow-hidden md:min-h-[960px] xl:min-h-[868px]"
>
    @foreach ($slides as $index => $slide)
        @php
            $slideImage = str_starts_with($slide['image'], 'http://') || str_starts_with($slide['image'], 'https://')
                ? $slide['image']
                : asset($slide['image']);
        @endphp
        <div
            x-show="active === {{ $index }}"
            x-transition.opacity.duration.1000ms
            class="absolute inset-0 lucille-card-image"
            style="background-image: url('{{ $slideImage }}');"
            aria-hidden="{{ $index === 0 ? 'false' : 'true' }}"
        ></div>
    @endforeach

    <div class="absolute inset-0 bg-[rgba(0,0,0,.24)]"></div>
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_72%_42%,rgba(195,39,32,.12),transparent_30%),linear-gradient(90deg,rgba(0,0,0,.26),transparent_54%)]"></div>

    <div class="relative z-10 mx-auto flex min-h-[720px] max-w-[1240px] items-center justify-end px-6 pt-[90px] text-right md:min-h-[960px] lg:px-10 xl:min-h-[868px]">
        <h1 class="max-w-[760px] font-sans text-[40px] font-bold uppercase leading-none text-white md:text-[55px] lg:text-[65px]">
            todas la <span class="text-lucille-accent">épocas del rock</span>,<br>
            están <span class="text-lucille-accent">aquí!</span>
        </h1>
    </div>

    <div class="absolute bottom-8 left-1/2 z-20 flex -translate-x-1/2 gap-3">
        @foreach ($slides as $index => $slide)
            <button type="button" class="h-2.5 w-2.5 rounded-full border border-white/70 transition" :class="active === {{ $index }} ? 'bg-lucille-accent border-lucille-accent' : 'bg-transparent'" @click="go({{ $index }})" aria-label="Show slide {{ $index + 1 }}"></button>
        @endforeach
    </div>
</section>
