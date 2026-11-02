@props(['slides' => [], 'autoSlides' => [], 'interval' => 7000, 'transition' => 'fade'])

@php
    $slides = is_array($slides) ? array_values(array_filter($slides, fn($s) => !empty($s['image'] ?? null))) : [];
    $autoSlides = is_array($autoSlides) ? array_values($autoSlides) : [];

    // Fallback defensivo para evitar portada vacía
    if (empty($slides) && empty($autoSlides)) {
        $slides = [
            ['image' => 'assets/lucille/pedalboard-1511069_1920.jpg']
        ];
    }

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
        userPaused: false,
        hovered: false,
        focused: false,
        reducedMotion: false,
        init() {
            const mq = window.matchMedia('(prefers-reduced-motion: reduce)');
            this.reducedMotion = mq.matches;
            mq.addEventListener('change', (e) => {
                this.reducedMotion = e.matches;
                this.syncAutoPlay();
            });
            this.syncAutoPlay();
        },
        get isPlaying() {
            return !this.userPaused && !this.reducedMotion && !this.hovered && !this.focused;
        },
        syncAutoPlay() {
            if (this.interval) {
                clearInterval(this.interval);
                this.interval = null;
            }
            if (this.total >= 2 && this.isPlaying) {
                this.interval = setInterval(() => {
                    this.next();
                }, this.delay);
            }
        },
        next() {
            this.active = (this.active + 1) % this.total;
        },
        go(index) {
            this.active = index;
            this.syncAutoPlay();
        },
        togglePlayPause() {
            this.userPaused = !this.userPaused;
            this.syncAutoPlay();
        },
        onHover(state) {
            this.hovered = state;
            this.syncAutoPlay();
        },
        onFocus(state) {
            this.focused = state;
            this.syncAutoPlay();
        },
    }"
    x-init="init"
    @mouseenter="onHover(true)"
    @mouseleave="onHover(false)"
    @focusin="onFocus(true)"
    @focusout="onFocus(false)"
    @hero-pause.window="onHover(true)"
    @hero-resume.window="onHover(false)"
    class="relative min-h-[340px] overflow-hidden sm:min-h-[70svh] md:min-h-[960px] xl:min-h-[868px] hero-rocks-section"
>
    {{-- ═══════════ Manual Slides (imágenes estáticas) ═══════════ --}}
    @foreach ($slides as $index => $slide)
        @php
            $slideImage = str_starts_with($slide['image'], 'http://') || str_starts_with($slide['image'], 'https://')
                ? $slide['image']
                : asset($slide['image']);
            $fallbackImage = asset('assets/lucille/pedalboard-1511069_1920.jpg');
        @endphp
        <div
            x-show="active === {{ $index }}"
            {!! $transitionModifiers !!}
            :class="active === {{ $index }} ? 'z-20' : 'z-10'"
            class="absolute inset-0 hero-slide"
            style="background-image: url('{{ $slideImage }}');"
            :aria-hidden="(active !== {{ $index }}).toString()"
            :inert="active !== {{ $index }}"
        >
            <img src="{{ $slideImage }}" alt="Slide {{ $index + 1 }}" class="hero-slide-img"
                 width="1920" height="1080"
                 loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                 fetchpriority="{{ $index === 0 ? 'high' : 'low' }}"
                 decoding="{{ $index === 0 ? 'sync' : 'async' }}"
                 onerror="if (!this.dataset.fallbackApplied) { this.dataset.fallbackApplied = '1'; this.src = '{{ $fallbackImage }}'; this.parentElement.style.backgroundImage = 'url(\'{{ $fallbackImage }}\')'; }">
        </div>
    @endforeach

    {{-- ═══════════ Auto Slides (scattered photos) ═══════════ --}}
    @foreach ($autoSlides as $autoIndex => $auto)
        @php $slideIndex = count($slides) + $autoIndex; @endphp

        @if (($auto['type'] ?? '') === 'scattered-grid')
            {{-- Filtro SVG para efecto de papel rasgado --}}
            <svg width="0" height="0" class="absolute pointer-events-none">
              <filter id="torn-paper">
                <feTurbulence type="fractalNoise" baseFrequency="0.04" numOctaves="3" result="noise" />
                <feDisplacementMap in="SourceGraphic" in2="noise" scale="5" xChannelSelector="R" yChannelSelector="G" />
              </filter>
            </svg>

            {{-- Grilla de tarjetas (Noticias, Lanzamientos, Programas) con fondo de acero oxidado --}}
            <div
                x-show="active === {{ $slideIndex }}"
                {!! $transitionModifiers !!}
                :class="active === {{ $slideIndex }} ? 'z-20' : 'z-10'"
                class="absolute inset-0 program-grid-slide bg-[#1a1512]"
                style="background-image: url('https://images.unsplash.com/photo-1501166222995-ff4133408a04?q=80&w=1920&auto=format&fit=crop'); background-size: cover; background-position: center;"
                :aria-hidden="(active !== {{ $slideIndex }}).toString()"
                :inert="active !== {{ $slideIndex }}"
            >
                <div class="program-grid-noise"></div>
                <div class="program-grid-vignette-left"></div>
                <div class="program-grid-vignette-right"></div>
                <div class="program-grid-overlay"></div>

                {{-- Contenido Collage --}}
                <div class="relative w-full h-full max-w-[1200px] mx-auto overflow-hidden flex flex-col justify-center items-center">
                    {{-- Encabezado Centrado --}}
                    <div class="absolute top-6 md:top-8 w-full text-center z-40 pointer-events-none px-4">
                        <span class="font-display text-[10px] uppercase tracking-[0.24em] text-[#c32720] block mb-1">📻 {{ $auto['label'] ?? 'Hoy en 7RR' }}</span>
                        <h2 class="font-display text-[clamp(16px,2.2vw,30px)] uppercase tracking-[0.06em] text-[#f0f0f0] drop-shadow-[0_2px_24px_rgba(0,0,0,0.9)] m-0 leading-tight">
                            {{ \Carbon\Carbon::now()->locale('es')->isoFormat('dddd D [de] MMMM') }}
                        </h2>
                    </div>

                    {{-- Overlay de textura global (manchas/grano) para ensuciar todo el collage --}}
                    <div class="absolute inset-0 pointer-events-none opacity-40 mix-blend-multiply z-10"
                         style="background-image: url('data:image/svg+xml,%3Csvg viewBox=%220 0 200 200%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cfilter id=%22noiseFilter%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%220.85%22 numOctaves=%223%22 stitchTiles=%22stitch%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23noiseFilter)%22/%3E%3C/svg%3E');">
                    </div>

                    {{-- Tarjetas caóticas --}}
                    <div class="relative w-full h-[65vh] md:h-[75vh] flex items-center justify-center overflow-visible">
                        <div class="relative w-full h-full scale-[0.55] sm:scale-75 md:scale-100 origin-center">
                            @foreach (($auto['programs'] ?? []) as $pi => $prog)
                            @php $st = $prog['styles']; @endphp
                            <div class="absolute group transition-transform duration-500 ease-out hover:scale-110 hover:!z-[999] pointer-events-none"
                                 style="
                                    width: {{ $st['size'] }}px;
                                    top: calc(50% + {{ $st['offset_y'] }}%);
                                    left: calc(50% + {{ $st['offset_x'] }}%);
                                    transform: translate(-50%, -50%) rotate({{ $st['rotation'] }}deg);
                                    z-index: {{ $st['z_index'] }};
                                 ">
                                 
                                {{-- Borde blanco rasgado (Torn Paper Effect) --}}
                                <div class="relative w-full aspect-square bg-gray-100 p-3 shadow-[0_20px_50px_rgba(0,0,0,0.9)] pointer-events-auto transition-all duration-300 {{ $prog['is_main'] ? 'bg-white' : '' }}"
                                     style="filter: url(#torn-paper);">
                                     
                                    <div class="relative w-full h-full bg-[#111] overflow-hidden" style="clip-path: {{ $st['clip'] }};">
                                        {{-- Textura de desgaste en las orillas (ruido y sombra interior) --}}
                                        <div class="absolute inset-0 pointer-events-none shadow-[inset_0_0_40px_rgba(0,0,0,0.8)] mix-blend-overlay z-10 transition-opacity duration-300 group-hover:opacity-0"
                                             style="background-image: url('data:image/svg+xml,%3Csvg viewBox=%220 0 200 200%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cfilter id=%22noise%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%221.5%22 numOctaves=%223%22 stitchTiles=%22stitch%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23noise)%22 opacity=%220.3%22/%3E%3C/svg%3E');"></div>

                                        <img src="{{ $prog['image'] }}" alt="{{ $prog['title'] }}" loading="{{ $pi === 0 ? 'eager' : 'lazy' }}"
                                             onerror="if (!this.dataset.fallbackApplied) { this.dataset.fallbackApplied = '1'; this.src = '{{ asset('assets/lucille/logo.png') }}'; }"
                                             class="w-full h-full object-cover transition-all duration-300 group-hover:opacity-100 group-hover:filter-none group-hover:mix-blend-normal group-hover:brightness-110"
                                             style="
                                                opacity: {{ $st['opacity'] }};
                                                filter: sepia({{ $st['sepia'] }}) grayscale({{ $st['grayscale'] }}) contrast({{ $st['contrast'] }}) brightness({{ $st['brightness'] }}) hue-rotate({{ $st['hue'] }}deg);
                                                mix-blend-mode: {{ $st['blend'] }};
                                             ">
                                    </div>
                                         
                                    <span class="absolute top-4 left-4 bg-[#c32720] text-white text-[10px] uppercase font-bold tracking-widest px-2 py-1 shadow-md z-20">
                                        {{ $prog['badge'] }}
                                    </span>
                                </div>
                                
                                {{-- Título y texto debajo --}}
                                <div class="mt-3 text-center opacity-90 transition-opacity duration-300 group-hover:opacity-100 bg-black/60 backdrop-blur-md p-2 rounded pointer-events-auto">
                                    <p class="font-display text-white text-sm md:text-base uppercase tracking-wider drop-shadow-md leading-tight">{{ $prog['title'] }}</p>
                                    @if ($prog['host'])
                                        <p class="font-sans text-gray-300 text-xs mt-1 uppercase truncate">{{ $prog['host'] }}</p>
                                    @endif
                                    @if ($prog['schedule'])
                                        <p class="font-sans text-[var(--lucille-accent-readable,#ff5b52)] text-[10px] mt-1 font-semibold tracking-wide">{{ $prog['schedule'] }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        </div>
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
                        bottom: 50px !important;
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


    @if ($totalSlideCount > 1)
        <div class="absolute bottom-4 left-1/2 z-30 flex -translate-x-1/2 items-center gap-1.5" role="region" aria-label="Controles del carrusel de portada">
            <button type="button"
                @click="togglePlayPause()"
                :aria-label="isPlaying ? 'Pausar pase automático de diapositivas' : 'Reanudar pase automático de diapositivas'"
                :aria-pressed="(!isPlaying).toString()"
                class="w-7 h-7 min-w-[28px] min-h-[28px] flex items-center justify-center rounded-full text-white/60 hover:text-white transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent"
                :title="isPlaying ? 'Pausar pase de diapositivas' : 'Reanudar pase de diapositivas'">
                <svg x-show="isPlaying" class="w-2.5 h-2.5 fill-current" viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                <svg x-show="!isPlaying" class="w-2.5 h-2.5 fill-current" viewBox="0 0 24 24" aria-hidden="true"><polygon points="5,3 19,12 5,21"/></svg>
                <span class="sr-only" x-text="isPlaying ? 'Pausar pase de diapositivas' : 'Reanudar pase de diapositivas'"></span>
            </button>

            @for ($i = 0; $i < $totalSlideCount; $i++)
                <button type="button" 
                    class="w-7 h-7 min-w-[28px] min-h-[28px] flex items-center justify-center p-0 bg-transparent rounded-full transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent" 
                    @click="go({{ $i }})" 
                    :aria-current="active === {{ $i }} ? 'true' : 'false'"
                    aria-label="Ir a la diapositiva {{ $i + 1 }} de {{ $totalSlideCount }}">
                    <span class="h-2 w-2 rounded-full border border-white/60 transition pointer-events-none" :class="active === {{ $i }} ? 'bg-lucille-accent border-lucille-accent scale-110' : 'bg-transparent'"></span>
                </button>
            @endfor
        </div>
    @endif

    {{-- Scroll Down Indicator --}}
    <div class="absolute bottom-4 left-1/2 -translate-x-1/2 z-20 hidden md:flex flex-col items-center gap-1 pointer-events-none">
        <span class="text-[8px] font-display text-white/30 uppercase tracking-[0.25em]">Desplazar</span>
        <div class="w-4 h-7 border border-white/20 rounded-full flex justify-center p-0.5">
            <div class="w-1 h-1.5 bg-lucille-accent rounded-full animate-scroll-bounce"></div>
        </div>
    </div>
</section>
