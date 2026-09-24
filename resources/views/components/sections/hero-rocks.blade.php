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

        @if (($auto['type'] ?? '') === 'scattered-grid')
            @php $defaultBg = $auto['programs'][0]['image'] ?? ''; @endphp
            {{-- Grilla de tarjetas (Noticias, Lanzamientos, Programas) con fondo reactivo al hover --}}
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

                {{-- Contenido Collage --}}
                <div class="relative w-full h-full max-w-[1200px] mx-auto overflow-hidden flex flex-col justify-center items-center">
                    {{-- Encabezado Centrado --}}
                    <div class="absolute top-8 w-full text-center z-50 pointer-events-none">
                        <span class="font-display text-[10px] uppercase tracking-[0.24em] text-[#c32720] block mb-1">📻 {{ $auto['label'] ?? 'Hoy en 7RR' }}</span>
                        <h2 class="font-display text-[clamp(18px,2.4vw,30px)] uppercase tracking-[0.06em] text-[#f0f0f0] drop-shadow-[0_2px_24px_rgba(0,0,0,0.9)] m-0 leading-none">
                            {{ \Carbon\Carbon::now()->locale('es')->isoFormat('dddd D [de] MMMM') }}
                        </h2>
                    </div>

                    {{-- Overlay de textura global (manchas/grano) para ensuciar todo el collage --}}
                    <div class="absolute inset-0 pointer-events-none opacity-40 mix-blend-multiply z-10"
                         style="background-image: url('data:image/svg+xml,%3Csvg viewBox=%220 0 200 200%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cfilter id=%22noiseFilter%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%220.85%22 numOctaves=%223%22 stitchTiles=%22stitch%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23noiseFilter)%22/%3E%3C/svg%3E');">
                    </div>

                    {{-- Tarjetas caóticas --}}
                    <div class="relative w-full h-[65vh] md:h-[75vh]" @mouseleave="changeBg('{{ $defaultBg }}')">
                        @foreach (($auto['programs'] ?? []) as $pi => $prog)
                            @php $st = $prog['styles']; @endphp
                            <div class="absolute group transition-transform duration-500 ease-out hover:scale-110 hover:!z-[999]"
                                 style="
                                    width: {{ $st['size'] }}px;
                                    top: calc(50% + {{ $st['offset_y'] }}%);
                                    left: calc(50% + {{ $st['offset_x'] }}%);
                                    transform: translate(-50%, -50%) rotate({{ $st['rotation'] }}deg);
                                    z-index: {{ $st['z_index'] }};
                                 "
                                 @mouseenter="changeBg('{{ $prog['image'] }}')">
                                 
                                {{-- Cinta adhesiva aleatoria --}}
                                @if($st['tape'])
                                    <div class="absolute -top-4 -right-4 w-14 h-6 bg-white/30 backdrop-blur-sm shadow-sm transform rotate-45 z-30 pointer-events-none"></div>
                                    <div class="absolute -bottom-4 -left-4 w-14 h-6 bg-white/30 backdrop-blur-sm shadow-sm transform rotate-45 z-30 pointer-events-none"></div>
                                @endif

                                {{-- Cover Image --}}
                                <div class="relative w-full aspect-square bg-[#111] overflow-hidden shadow-[0_20px_50px_rgba(0,0,0,0.8)] border-4 transition-all duration-300 {{ $prog['is_main'] ? 'border-[#c32720] shadow-[#c32720]/20' : 'border-white/10' }}"
                                     style="clip-path: {{ $st['clip'] }};">
                                    <img src="{{ $prog['image'] }}" alt="{{ $prog['title'] }}" loading="{{ $pi === 0 ? 'eager' : 'lazy' }}"
                                         class="w-full h-full object-cover transition-all duration-300 group-hover:opacity-100 group-hover:filter-none group-hover:mix-blend-normal group-hover:brightness-110"
                                         style="
                                            opacity: {{ $st['opacity'] }};
                                            filter: sepia({{ $st['sepia'] }}) grayscale({{ $st['grayscale'] }}) contrast({{ $st['contrast'] }}) brightness({{ $st['brightness'] }}) hue-rotate({{ $st['hue'] }}deg);
                                            mix-blend-mode: {{ $st['blend'] }};
                                         ">
                                         
                                    <span class="absolute top-2 left-2 bg-[#c32720] text-white text-[10px] uppercase font-bold tracking-widest px-2 py-1 shadow-md z-20">
                                        {{ $prog['badge'] }}
                                    </span>
                                </div>
                                
                                {{-- Título y texto debajo --}}
                                <div class="mt-3 text-center opacity-90 transition-opacity duration-300 group-hover:opacity-100 bg-black/60 backdrop-blur-md p-2 rounded">
                                    <p class="font-display text-white text-sm md:text-base uppercase tracking-wider drop-shadow-md leading-tight">{{ $prog['title'] }}</p>
                                    @if ($prog['host'])
                                        <p class="font-sans text-gray-300 text-xs mt-1 uppercase truncate">{{ $prog['host'] }}</p>
                                    @endif
                                    @if ($prog['schedule'])
                                        <p class="font-sans text-[#c32720] text-[10px] mt-1 font-bold">{{ $prog['schedule'] }}</p>
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
    <div class="absolute inset-0 pointer-events-none bg-[rgba(0,0,0,.24)]"></div>
    <div class="absolute inset-0 pointer-events-none bg-[radial-gradient(circle_at_72%_42%,rgba(195,39,32,.12),transparent_30%),linear-gradient(90deg,rgba(0,0,0,.26),transparent_54%)]"></div>

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
