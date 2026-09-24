@props(['slides', 'autoSlides' => [], 'interval' => 7000, 'transition' => 'fade'])

@php
    $transitionModifiers = match ($transition) {
        'slide' => 'x-transition:enter="transition ease-out duration-700" x-transition:enter-start="translate-x-full opacity-0" x-transition:enter-end="translate-x-0 opacity-100" x-transition:leave="transition ease-in duration-500" x-transition:leave-start="translate-x-0 opacity-100" x-transition:leave-end="-translate-x-full opacity-0"',
        'zoom' => 'x-transition.scale.duration.1000ms',
        default => 'x-transition.opacity.duration.2000ms',
    };

    // Total slide count = manual + auto
    $totalSlideCount = count($slides) + count($autoSlides);
@endphp

<section
    x-data="{
        active: 0,
        total: {{ $totalSlideCount }},
        interval: null,
        delay: {{ (int) $interval }},
        paused: false,
        init() {
            if (this.total < 2) return;
            this.interval = setInterval(() => { if (!this.paused) this.next(); }, this.delay);
        },
        next() {
            this.active = (this.active + 1) % this.total;
        },
        go(index) {
            clearInterval(this.interval);
            this.active = index;
            this.interval = setInterval(() => { if (!this.paused) this.next(); }, this.delay);
        },
        pause() { this.paused = true; },
        resume() { this.paused = false; },
    }"
    x-init="init"
    @hero-pause.window="pause()"
    @hero-resume.window="resume()"
    class="relative min-h-[340px] overflow-hidden sm:min-h-[70svh] md:min-h-[960px] xl:min-h-[868px] hero-rocks-section"
>
    {{-- ═══════════ Manual Slides (imágenes estáticas) ═══════════ --}}
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

    {{-- ═══════════ Auto Slides (scattered photos) ═══════════ --}}
    @foreach ($autoSlides as $autoIndex => $auto)
        @php $slideIndex = count($slides) + $autoIndex; @endphp

        @if (($auto['type'] ?? '') === 'scattered-collage')
            @php $defaultBg = $auto['items'][0]['image'] ?? ''; @endphp
            {{-- Collage: 3 fotos "tiradas" — fondo reactivo al hover --}}
            <div
                x-show="active === {{ $slideIndex }}"
                {!! $transitionModifiers !!}
                class="absolute inset-0 flex items-center justify-center scattered-slide-bg"
                x-data="{
                    bgImg: '{{ $defaultBg }}',
                    bgAlpha: 1,
                    changeBg(url) {
                        if (url === this.bgImg) return;
                        this.bgAlpha = 0;
                        setTimeout(() => { this.bgImg = url; this.bgAlpha = 1; }, 220);
                    }
                }"
                @mouseenter="$dispatch('hero-pause')"
                @mouseleave="$dispatch('hero-resume')"
            >
                {{-- Fondo blurred reactivo --}}
                @if ($defaultBg)
                    <div class="scattered-bg-blur"
                         :style="{ backgroundImage: 'url(' + bgImg + ')', opacity: bgAlpha }"></div>
                @endif

                <div class="scattered-container"
                     @mouseleave="changeBg('{{ $defaultBg }}')">
                    @foreach (($auto['items'] ?? []) as $i => $item)
                        <div class="scattered-photo scattered-pos-{{ $i }}"
                             @mouseenter="changeBg('{{ $item['image'] }}')">
                            <img src="{{ $item['image'] }}" alt="{{ $item['title'] ?? '' }}" loading="lazy">
                        </div>
                    @endforeach
                </div>
                <div class="scattered-label">
                    <span class="scattered-label-accent">{{ $auto['label'] ?? '' }}</span>
                </div>
            </div>

        @elseif (($auto['type'] ?? '') === 'scattered-featured')
            @php $defaultBg = $auto['items'][0]['image'] ?? ''; @endphp
            {{-- Featured: una grande + miniaturas — fondo reactivo al hover --}}
            <div
                x-show="active === {{ $slideIndex }}"
                {!! $transitionModifiers !!}
                class="absolute inset-0 flex items-center justify-center scattered-slide-bg"
                x-data="{
                    bgImg: '{{ $defaultBg }}',
                    bgAlpha: 1,
                    changeBg(url) {
                        if (url === this.bgImg) return;
                        this.bgAlpha = 0;
                        setTimeout(() => { this.bgImg = url; this.bgAlpha = 1; }, 220);
                    }
                }"
                @mouseenter="$dispatch('hero-pause')"
                @mouseleave="$dispatch('hero-resume')"
            >
                {{-- Fondo blurred reactivo --}}
                @if ($defaultBg)
                    <div class="scattered-bg-blur"
                         :style="{ backgroundImage: 'url(' + bgImg + ')', opacity: bgAlpha }"></div>
                @endif

                <div class="scattered-featured-container"
                     @mouseleave="changeBg('{{ $defaultBg }}')">
                    {{-- Imagen protagonista --}}
                    @if (!empty($auto['items'][0]))
                        <div class="scattered-photo scattered-main"
                             @mouseenter="changeBg('{{ $auto['items'][0]['image'] }}')">
                            <img src="{{ $auto['items'][0]['image'] }}" alt="{{ $auto['items'][0]['title'] ?? '' }}" loading="lazy">
                            @if (!empty($auto['items'][0]['artist']))
                                <div class="scattered-photo-caption">{{ $auto['items'][0]['artist'] }}</div>
                            @endif
                        </div>
                    @endif
                    {{-- Miniaturas "tiradas" --}}
                    <div class="scattered-thumbs">
                        @foreach (array_slice($auto['items'] ?? [], 1) as $ti => $thumb)
                            <div class="scattered-photo scattered-thumb-{{ $ti }}"
                                 @mouseenter="changeBg('{{ $thumb['image'] }}')">
                                <img src="{{ $thumb['image'] }}" alt="{{ $thumb['title'] ?? '' }}" loading="lazy">
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="scattered-label">
                    <span class="scattered-label-accent">{{ $auto['label'] ?? '' }}</span>
                </div>
            </div>

        @elseif (($auto['type'] ?? '') === 'scattered-program')
            @php $defaultBg = $auto['programs'][0]['image'] ?? ''; @endphp
            {{-- Grilla de Programas del Día con fondo reactivo al hover --}}
            <div
                x-show="active === {{ $slideIndex }}"
                {!! $transitionModifiers !!}
                class="absolute inset-0 program-grid-slide"
                x-data="{
                    bgImg: '{{ $defaultBg }}',
                    bgAlpha: 1,
                    changeBg(url) {
                        if (url === this.bgImg) return;
                        this.bgAlpha = 0;
                        setTimeout(() => { this.bgImg = url; this.bgAlpha = 1; }, 220);
                    }
                }"
                @mouseenter="$dispatch('hero-pause')"
                @mouseleave="$dispatch('hero-resume')"
            >
                {{-- Fondo blurred reactivo --}}
                @if ($defaultBg)
                    <div class="program-grid-bg-blur"
                         :style="{ backgroundImage: 'url(' + bgImg + ')', opacity: bgAlpha }"></div>
                @endif
                <div class="program-grid-noise"></div>
                <div class="program-grid-vignette-left"></div>
                <div class="program-grid-vignette-right"></div>
                <div class="program-grid-overlay"></div>

                {{-- Contenido --}}
                <div class="program-grid-content">
                    <div class="program-grid-header">
                        <span class="program-grid-eyebrow">📻 {{ $auto['label'] ?? 'Hoy en 7RR' }}</span>
                        <p class="program-grid-date">{{ \Carbon\Carbon::now()->locale('es')->isoFormat('dddd D [de] MMMM') }}</p>
                    </div>

                    {{-- Tarjetas con hover reactivo --}}
                    <div class="program-grid-cards"
                         @mouseleave="changeBg('{{ $defaultBg }}')">
                        @foreach (($auto['programs'] ?? []) as $pi => $prog)
                            <div class="pgc {{ $prog['is_main'] ? 'pgc--main' : 'pgc--next' }}"
                                 @mouseenter="changeBg('{{ $prog['image'] }}')">
                                <div class="pgc-cover">
                                    <img src="{{ $prog['image'] }}" alt="{{ $prog['title'] }}" loading="{{ $pi === 0 ? 'eager' : 'lazy' }}">
                                    <span class="pgc-badge pgc-badge--{{ $prog['is_main'] ? 'live' : 'next' }}">
                                        {{ $prog['badge'] }}
                                    </span>
                                </div>
                                <div class="pgc-info">
                                    <p class="pgc-title">{{ $prog['title'] }}</p>
                                    @if ($prog['host'])
                                        <p class="pgc-host">{{ $prog['host'] }}</p>
                                    @endif
                                    @if ($prog['schedule'])
                                        <p class="pgc-schedule">{{ $prog['schedule'] }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

        @endif
    @endforeach

    {{-- ═══════════ Overlays ═══════════ --}}
    <div class="absolute inset-0 bg-[rgba(0,0,0,.24)]"></div>
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_72%_42%,rgba(195,39,32,.12),transparent_30%),linear-gradient(90deg,rgba(0,0,0,.26),transparent_54%)]"></div>

    @if(!empty($themeAppearance['hero_floating_text']))
        @php
            $text = $themeAppearance['hero_floating_text'];
            $position = $themeAppearance['hero_floating_text_position'] ?? 'inferior-centro';
            
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

        @if ($position === 'columnas-laterales' || ($position === 'columna-izquierda' && $secondHalf))
            <style>
                .split-col-izq {
                    flex-direction: column !important;
                    align-items: flex-start !important;
                    gap: 12px !important;
                    left: 90px !important;
                }
                @media (max-width: 768px) {
                    .split-col-izq {
                        bottom: 80px !important;
                        align-items: center !important;
                    }
                }
            </style>
            <div class="hero-floating-text columna-izquierda split-col-izq">
                <div class="whitespace-nowrap">
                    {!! $firstHalf !!}
                </div>
                @if($secondHalf)
                    <div class="text-lucille-accent whitespace-nowrap">
                        {!! $secondHalf !!}
                    </div>
                @endif
            </div>
        @else
            <div class="hero-floating-text {{ $position }}">
                <div class="text-center">
                    {!! $firstHalf !!}@if($secondHalf) <span class="text-lucille-accent">{!! $secondHalf !!}</span>@endif
                </div>
            </div>
        @endif
    @endif


    <div class="absolute bottom-8 left-1/2 z-20 flex -translate-x-1/2 gap-3">
        @for ($i = 0; $i < $totalSlideCount; $i++)
            <button type="button" class="h-2.5 w-2.5 rounded-full border border-white/70 transition" :class="active === {{ $i }} ? 'bg-lucille-accent border-lucille-accent' : 'bg-transparent'" @click="go({{ $i }})" aria-label="Show slide {{ $i + 1 }}"></button>
        @endfor
    </div>

    {{-- Scroll Down Indicator --}}
    <div class="absolute bottom-4 left-1/2 -translate-x-1/2 z-20 hidden md:flex flex-col items-center gap-1 pointer-events-none">
        <span class="text-[8px] font-display text-white/30 uppercase tracking-[0.25em]">Desplazar</span>
        <div class="w-4 h-7 border border-white/20 rounded-full flex justify-center p-0.5">
            <div class="w-1 h-1.5 bg-lucille-accent rounded-full animate-scroll-bounce"></div>
        </div>
    </div>
</section>
