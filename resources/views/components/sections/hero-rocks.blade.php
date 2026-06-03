@props(['slides', 'interval' => 7000, 'transition' => 'fade'])

@php
    $transitionModifiers = match ($transition) {
        'slide' => 'x-transition:enter="transition ease-out duration-700" x-transition:enter-start="translate-x-full opacity-0" x-transition:enter-end="translate-x-0 opacity-100" x-transition:leave="transition ease-in duration-500" x-transition:leave-start="translate-x-0 opacity-100" x-transition:leave-end="-translate-x-full opacity-0"',
        'zoom' => 'x-transition.scale.duration.1000ms',
        default => 'x-transition.opacity.duration.2000ms',
    };
@endphp

<section
    x-data="{
        active: 0,
        slides: {{ Js::from($slides) }},
        interval: null,
        delay: {{ (int) $interval }},
        init() {
            if (this.slides.length < 2) return;
            this.interval = setInterval(() => this.next(), this.delay);
        },
        next() {
            this.active = (this.active + 1) % this.slides.length;
        },
        go(index) {
            clearInterval(this.interval);
            this.active = index;
            this.interval = setInterval(() => this.next(), this.delay);
        },
    }"
    x-init="init"
    class="relative min-h-[340px] overflow-hidden sm:min-h-[70svh] md:min-h-[960px] xl:min-h-[868px]"
>
    @foreach ($slides as $index => $slide)
        @php
            $slideImage = str_starts_with($slide['image'], 'http://') || str_starts_with($slide['image'], 'https://')
                ? $slide['image']
                : asset($slide['image']);
        @endphp
        <div
            x-show="active === {{ $index }}"
            {!! $transitionModifiers !!}
            class="absolute inset-0 bg-cover bg-top bg-no-repeat lucille-card-image hero-rocks-bg"
            style="background-image: url('{{ $slideImage }}'); background-repeat: no-repeat;"
            aria-hidden="{{ $index === 0 ? 'false' : 'true' }}"
        ></div>
    @endforeach

    <div class="absolute inset-0 bg-[rgba(0,0,0,.24)]"></div>
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_72%_42%,rgba(195,39,32,.12),transparent_30%),linear-gradient(90deg,rgba(0,0,0,.26),transparent_54%)]"></div>

    <div class="relative z-10 mx-auto flex min-h-[340px] max-w-[1240px] items-end justify-end px-6 pt-[10dvh] pb-[4dvh] text-right sm:min-h-[70svh] md:min-h-[960px] md:pt-0 md:pb-6 lg:px-10 xl:min-h-[868px]">
        <h1 class="hidden max-w-[760px] font-sans text-[20px] font-bold uppercase leading-none text-white sm:text-[22px] md:block md:text-[38px] lg:text-[48px]">
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
