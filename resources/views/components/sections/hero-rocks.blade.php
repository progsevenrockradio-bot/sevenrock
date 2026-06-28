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
    class="relative min-h-[340px] overflow-hidden sm:min-h-[70svh] md:min-h-[960px] xl:min-h-[868px] hero-rocks-section"
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
            class="absolute inset-0 hero-slide"
            style="background-image: url('{{ $slideImage }}');"
            aria-hidden="{{ $index === 0 ? 'false' : 'true' }}"
        >
            <img src="{{ $slideImage }}" alt="Slide {{ $index + 1 }}" class="hero-slide-img">
        </div>
    @endforeach

    <div class="absolute inset-0 bg-[rgba(0,0,0,.24)]"></div>
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_72%_42%,rgba(195,39,32,.12),transparent_30%),linear-gradient(90deg,rgba(0,0,0,.26),transparent_54%)]"></div>

    @if(!empty($themeAppearance['hero_floating_text']))
        @php
            $text = $themeAppearance['hero_floating_text'];
            if (str_contains($text, '*')) {
                $parts = explode('*', $text);
                $firstHalf = $parts[0];
                $secondHalf = $parts[1] ?? '';
            } else {
                $words = explode(' ', $text);
                $half = ceil(count($words) / 2);
                $firstHalf = implode(' ', array_slice($words, 0, $half));
                $secondHalf = implode(' ', array_slice($words, $half));
            }
        @endphp
        <div class="hero-floating-text whitespace-nowrap {{ $themeAppearance['hero_floating_text_position'] ?? 'inferior-centro' }}">
            {!! $firstHalf !!}@if($secondHalf) <span class="text-lucille-accent">{!! $secondHalf !!}</span>@endif
        </div>
    @endif

    <div class="absolute bottom-8 left-1/2 z-20 flex -translate-x-1/2 gap-3">
        @foreach ($slides as $index => $slide)
            <button type="button" class="h-2.5 w-2.5 rounded-full border border-white/70 transition" :class="active === {{ $index }} ? 'bg-lucille-accent border-lucille-accent' : 'bg-transparent'" @click="go({{ $index }})" aria-label="Show slide {{ $index + 1 }}"></button>
        @endforeach
    </div>

    {{-- Scroll Down Indicator --}}
    <div class="absolute bottom-4 left-1/2 -translate-x-1/2 z-20 hidden md:flex flex-col items-center gap-1 pointer-events-none">
        <span class="text-[8px] font-display text-white/30 uppercase tracking-[0.25em]">Desplazar</span>
        <div class="w-4 h-7 border border-white/20 rounded-full flex justify-center p-0.5">
            <div class="w-1 h-1.5 bg-lucille-accent rounded-full animate-scroll-bounce"></div>
        </div>
    </div>
</section>
