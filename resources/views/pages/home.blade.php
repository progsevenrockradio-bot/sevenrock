<x-layouts.site title="Seven Rock Radio - Inicio" description="Seven Rock Radio — Musica rock, entrevistas, eventos y la mejor vibra. Tu radio rock online. Escucha en vivo, descubre nuevos talentos y disfruta del mejor rock.">
    <h1 class="sr-only">Seven Rock Radio - Tu radio rock online</h1>
    @push('preloads')
        @if (!empty($themeAppearance['hero_slides']) && isset($themeAppearance['hero_slides'][0]['image']))
            @php
                $firstSlideImage = $themeAppearance['hero_slides'][0]['image'];
                $firstSlideUrl = str_starts_with($firstSlideImage, 'http://') || str_starts_with($firstSlideImage, 'https://')
                    ? $firstSlideImage
                    : asset($firstSlideImage);
            @endphp
            <link rel="preload" as="image" href="{{ $firstSlideUrl }}">
        @endif
    @endpush

    @php
        $homeHeadings = $themeAppearance['home_headings'];
        $ui = $themeAppearance['ui_texts'];
    @endphp

    <x-sections.hero-rocks :slides="$themeAppearance['hero_slides']" :auto-slides="$heroAutoSlides ?? []" :interval="$themeAppearance['hero_slides_interval']" :transition="$themeAppearance['hero_slides_transition']" />

    <x-home.headline-ticker :ticker="$headlineTicker" />

    @if (!empty($noticiasRock) && $noticiasRock->count() > 0)
    <x-sections.background-band class="home-section-texture home-section-gray">
        <div class="py-12 md:py-16">
            <x-ui.section-heading title="Noticias" accent="Rock" subtitle="Lo último en el mundo del rock y metal" />
            
            <div class="mx-auto max-w-[1200px] px-6 mt-8 md:mt-10">
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($noticiasRock as $post)
                        @php
                            $cleanExcerpt = $post->clean_excerpt;
                        @endphp
                        <div class="lucille-flat-surface p-4 flex flex-col justify-between transition-colors duration-200 hover:border-lucille-accent/40 group relative cursor-pointer">
                            <a href="{{ route('posts.single', ['year' => $post->published_at->format('Y'), 'month' => $post->published_at->format('m'), 'day' => $post->published_at->format('d'), 'slug' => $post->slug]) }}" class="absolute inset-0 z-30" aria-label="Leer más sobre {{ $post->title }}"></a>
                            <div>
                                <div class="relative aspect-[4/3] overflow-hidden bg-[#111]">
                                    <img src="{{ $post->featured_image_url ?: asset('assets/lucille/logo.png') }}"
                                         alt="{{ $post->title }}"
                                         onerror="if (!this.dataset.fallbackApplied) { this.dataset.fallbackApplied = '1'; this.src = '{{ asset('assets/lucille/logo.png') }}'; }"
                                         class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                         loading="lazy" decoding="async">
                                    @if ($post->en_memoria)
                                        <x-luto :nombre="$post->en_memoria_nombre" />
                                    @endif
                                </div>
                                <h3 class="mt-4 font-display text-[15px] sm:text-[16px] uppercase tracking-[.08em] text-lucille-heading line-clamp-2 group-hover:text-lucille-accent transition-colors relative z-20">
                                    {{ $post->title }}
                                </h3>
                                @if($post->published_at)
                                    <p class="text-[10px] uppercase tracking-[.12em] text-lucille-body mt-2 relative z-20">{{ $post->published_at->locale('es')->translatedFormat('d M, Y') }}</p>
                                @endif
                                @if($cleanExcerpt !== '')
                                    <p class="mt-2.5 text-xs leading-5 text-lucille-body line-clamp-3 relative z-20">{{ $cleanExcerpt }}</p>
                                @endif
                            </div>
                            <div class="mt-4 border-t border-lucille-line/20 pt-3 text-right relative z-20">
                                <span class="text-[11px] uppercase tracking-[.18em] text-lucille-heading group-hover:text-lucille-accent transition-colors">Leer más &rarr;</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </x-sections.background-band>
    @endif
    {{-- <x-home.partners-slider :agencies="$agencies" /> --}}

    @if (data_get($featuredStories, 'enabled', false))
    <x-sections.background-band class="home-section-texture home-section-gray">
        <div class="py-12 md:py-16">
            <x-ui.section-heading :title="$homeHeadings['featured_stories']['title']" :subtitle="$homeHeadings['featured_stories']['subtitle']" />
            <x-home.featured-stories :stories="$featuredStories" />
        </div>
    </x-sections.background-band>
    @endif

    @if ($nextProgram)
    <x-sections.background-band class="home-section-texture home-section-cool">
        <div class="py-12 md:py-16">
            <x-ui.section-heading :title="$homeHeadings['next_program']['title']" :subtitle="$homeHeadings['next_program']['subtitle']" />
            <x-home.next-program :program="$nextProgram" />
        </div>
    </x-sections.background-band>
    @endif

    @if (!empty($efemerides) && $efemerides->count() > 0)
    {{-- ── CINTILLO EFEMÉRIDES ──────────────────────────────────────────────── --}}
    <x-sections.background-band class="home-section-texture home-section-gray">
        <div class="py-8">
            <div class="mx-auto max-w-[1200px] px-6">
                <div class="relative w-full overflow-hidden rounded-sm border border-[#c32720]/30 bg-black shadow-lg flex flex-col md:block" id="efemerides-ticker">

                    {{-- Etiqueta "HOY EN EL ROCK" con control de pausa accesible --}}
                    <div class="relative w-full py-2.5 flex flex-col items-center justify-center bg-[#c32720] px-5 shadow-[0_4px_15px_rgba(195,39,32,.4)] md:absolute md:left-0 md:top-0 md:h-full md:w-auto md:z-20 md:shadow-[4px_0_20px_rgba(195,39,32,.5)]">
                        <div class="flex flex-col items-center justify-center text-center">
                            <span class="whitespace-nowrap font-display text-[10px] uppercase tracking-[.22em] text-white">
                                🎸 Hoy en el Rock
                            </span>
                            <span class="mt-[2px] whitespace-nowrap font-sans text-[7px] uppercase tracking-[.15em] text-white/80">
                                {{ \Carbon\Carbon::now(config('app.timezone', 'America/Caracas'))->locale('es')->isoFormat('D \d\e MMMM YYYY') }}
                            </span>
                            <button 
                                type="button"
                                id="efem-ticker-toggle"
                                onclick="toggleEfemTicker()"
                                aria-pressed="false"
                                aria-label="Pausar movimiento de efemérides"
                                class="mt-1 inline-flex h-6 w-6 items-center justify-center rounded text-white/80 transition hover:text-white hover:bg-black/25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                            >
                                <svg id="efem-pause-icon" class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <rect x="6" y="4" width="4" height="16" rx="1"></rect>
                                    <rect x="14" y="4" width="4" height="16" rx="1"></rect>
                                </svg>
                                <svg id="efem-play-icon" class="hidden h-3 w-3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <polygon points="5 3 19 12 5 21 5 3"></polygon>
                                </svg>
                            </button>
                        </div>
                    </div>

            {{-- Track animado --}}
            <div class="efem-track flex items-center gap-0 pl-0 md:pl-[170px]" style="animation: efem-scroll 60s alternate linear infinite;">
            @php
                $efemItems = $efemerides->values();
                // Pre-cargar los tags de cada post (una sola query por post)
                $efemTagsByPost = $efemItems->mapWithKeys(fn($p) => [
                    $p->id => method_exists($p, 'taxonomies')
                        ? $p->taxonomies->where('type', 'tag')->pluck('name')->map(fn($t) => '#'.$t)->values()->all()
                        : []
                ]);
            @endphp

            {{-- 1. Pista canónica accesible --}}
            <div class="flex items-center gap-0 shrink-0">
                @foreach($efemItems as $idx => $post)
                    @php $postTags = $efemTagsByPost[$post->id] ?? []; @endphp
                    <button
                        type="button"
                        data-efem-idx="{{ $idx }}"
                        class="efem-pill group inline-flex shrink-0 cursor-pointer items-center gap-2 border-r border-[#1e1e1e] px-6 py-4 text-left transition-colors duration-200 hover:bg-[#c32720]/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent focus-visible:ring-inset"
                        onclick="openEfemModal({{ $idx }})"
                        aria-label="Leer: {{ $post->title }}"
                    >
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-[#c32720] opacity-70 group-hover:opacity-100 transition-opacity" aria-hidden="true"></span>
                        <span class="whitespace-nowrap text-[12px] tracking-wide text-[#9a9a9a] group-hover:text-[#e0e0e0] transition-colors">
                            {{ $post->title }}
                        </span>
                        @if(!empty($postTags[0]))
                            <span class="whitespace-nowrap text-[10px] tracking-wider text-[var(--lucille-accent-readable,#ff5b52)] group-hover:text-[var(--lucille-accent-readable,#ff5b52)] transition-colors font-medium">
                                {{ $postTags[0] }}
                            </span>
                        @endif
                    </button>
                @endforeach
            </div>

            {{-- 2. Pista decorativa para continuidad visual (oculta a lectores y teclado) --}}
            <div class="pointer-events-none flex items-center gap-0 shrink-0 efem-track-clone" aria-hidden="true">
                @foreach($efemItems as $idx => $post)
                    @php $postTags = $efemTagsByPost[$post->id] ?? []; @endphp
                    <div class="efem-pill group inline-flex shrink-0 items-center gap-2 border-r border-[#1e1e1e] px-6 py-4 text-left transition-colors duration-200" aria-hidden="true">
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-[#c32720] opacity-70" aria-hidden="true"></span>
                        <span class="whitespace-nowrap text-[12px] tracking-wide text-[#9a9a9a]" aria-hidden="true">
                            {{ $post->title }}
                        </span>
                        @if(!empty($postTags[0]))
                            <span class="whitespace-nowrap text-[10px] tracking-wider text-[var(--lucille-accent-readable,#ff5b52)] font-medium" aria-hidden="true">
                                {{ $postTags[0] }}
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    </div>
    </div>
</x-sections.background-band>

    {{-- ── MODAL EFEMÉRIDES ────────────────────────────────────────────────── --}}
    @push('modals')
    <div id="efem-modal" role="dialog" aria-modal="true" aria-labelledby="efem-modal-title"
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4 opacity-0 pointer-events-none transition-opacity duration-300"
         style="background:rgba(0,0,0,.95); backdrop-filter:blur(10px);">

        <div id="efem-modal-box"
             class="relative w-full max-w-lg scale-95 rounded-sm border border-[#c32720]/30 bg-black p-8 shadow-[0_0_60px_rgba(195,39,32,.4)] transition-transform duration-300">

            {{-- Línea decorativa superior --}}
            <div class="absolute left-0 top-0 h-[2px] w-full bg-gradient-to-r from-[#c32720] via-[#ff4a42] to-transparent rounded-t-sm" aria-hidden="true"></div>

            {{-- Cerrar --}}
            <button onclick="closeEfemModal()" class="absolute right-4 top-4 p-1 rounded text-[#7b7b7b] hover:text-[#e0e0e0] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent" aria-label="Cerrar modal">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            {{-- Icono musical --}}
            <div class="mb-5 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-[#c32720]/15 text-[#c32720]" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 3v10.55A4 4 0 1 0 11 17V7h4V3H9z"/>
                    </svg>
                </span>
                <p class="text-[10px] uppercase tracking-[.2em] text-[var(--lucille-accent-readable,#ff5b52)]">Hoy en el Rock</p>
            </div>

            <h3 id="efem-modal-title" class="mb-4 font-display text-[18px] uppercase tracking-[.06em] leading-snug text-[#dcdcdc]"></h3>
            <p id="efem-modal-body" class="text-[14px] leading-7 text-[#7a7a7a]"></p>
            <div id="efem-modal-tags" class="mt-5 flex flex-wrap gap-2"></div>
        </div>
    </div>

    {{-- Datos JSON para el modal (incluye tags del post) --}}
    @php
        $modalData = $efemItems->map(fn($p) => [
            'title'   => $p->title,
            'content' => $p->clean_excerpt ?: $p->title,
            'tags'    => method_exists($p, 'taxonomies')
                ? $p->taxonomies->where('type', 'tag')->pluck('name')->map(fn($t) => '#'.$t)->values()->all()
                : [],
        ])->values();
    @endphp
    <script id="efem-data" type="application/json">
        {!! json_encode($modalData) !!}
    </script>

    <style>
        @keyframes efem-scroll {
            0%   { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        #efemerides-ticker:hover .efem-track,
        #efemerides-ticker:focus-within .efem-track {
            animation-play-state: paused;
        }
        .efem-track-paused {
            animation-play-state: paused !important;
        }
        @media (prefers-reduced-motion: reduce) {
            #efemerides-ticker {
                overflow-x: auto !important;
                scrollbar-width: thin;
            }
            .efem-track {
                animation: none !important;
            }
            .efem-track-clone {
                display: none !important;
            }
        }
    </style>

    <script>
        (function () {
            const data   = JSON.parse(document.getElementById('efem-data').textContent || '[]');
            const modal  = document.getElementById('efem-modal');
            const box    = document.getElementById('efem-modal-box');
            const title  = document.getElementById('efem-modal-title');
            const body   = document.getElementById('efem-modal-body');
            window._lastEfemTrigger = null;

            const setBackgroundInert = (isInert) => {
                if (!modal) return;
                if (isInert && modal.parentElement !== document.body) {
                    document.body.appendChild(modal);
                }
                const siblings = Array.from(document.body.children).filter(el => 
                    el !== modal && el.tagName !== 'SCRIPT' && el.tagName !== 'STYLE' && el.id !== 'efem-modal'
                );
                siblings.forEach(el => {
                    if (isInert) el.setAttribute('inert', '');
                    else el.removeAttribute('inert');
                });
            };

            window.toggleEfemTicker = function () {
                const track = document.querySelector('.efem-track');
                const btn = document.getElementById('efem-ticker-toggle');
                const pauseIcon = document.getElementById('efem-pause-icon');
                const playIcon = document.getElementById('efem-play-icon');
                if (!track || !btn) return;
                const isPaused = track.classList.toggle('efem-track-paused');
                btn.setAttribute('aria-pressed', isPaused.toString());
                btn.setAttribute('aria-label', isPaused ? 'Reanudar movimiento de efemérides' : 'Pausar movimiento de efemérides');
                if (pauseIcon && playIcon) {
                    pauseIcon.classList.toggle('hidden', isPaused);
                    playIcon.classList.toggle('hidden', !isPaused);
                }
            };

            window.openEfemModal = function (idx) {
                window._lastEfemTrigger = document.activeElement;
                const item = data[idx % data.length];
                if (!item) return;
                title.textContent = item.title;
                body.textContent  = item.content;

                // Renderizar hashtags como pills
                const tagsEl = document.getElementById('efem-modal-tags');
                tagsEl.innerHTML = '';
                if (item.tags && item.tags.length) {
                    item.tags.forEach(function(tag) {
                        const pill = document.createElement('span');
                        pill.className = 'inline-block rounded-full border border-[#c32720]/40 bg-[#c32720]/10 px-3 py-1 text-[10px] font-semibold uppercase tracking-[.16em] text-[var(--lucille-accent-readable,#ff5b52)]';
                        pill.textContent = tag;
                        tagsEl.appendChild(pill);
                    });
                }

                modal.classList.remove('opacity-0', 'pointer-events-none');
                modal.classList.add('opacity-100');
                box.classList.remove('scale-95');
                box.classList.add('scale-100');
                document.body.style.overflow = 'hidden';
                setBackgroundInert(true);

                setTimeout(() => {
                    const closeBtn = modal.querySelector('button[aria-label="Cerrar modal"]');
                    if (closeBtn) closeBtn.focus();
                }, 50);
            };

            window.closeEfemModal = function () {
                if (modal.classList.contains('opacity-0')) return;
                modal.classList.add('opacity-0', 'pointer-events-none');
                modal.classList.remove('opacity-100');
                box.classList.add('scale-95');
                box.classList.remove('scale-100');
                document.body.style.overflow = '';
                setBackgroundInert(false);

                if (window._lastEfemTrigger && typeof window._lastEfemTrigger.focus === 'function') {
                    window._lastEfemTrigger.focus();
                }
            };

            // Cerrar al hacer clic fuera del box
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeEfemModal();
            });

            // Atrapado de foco Tab / Shift+Tab y cierre con Escape
            document.addEventListener('keydown', function (e) {
                if (modal.classList.contains('opacity-0')) return;

                if (e.key === 'Escape') {
                    closeEfemModal();
                    return;
                }

                if (e.key === 'Tab') {
                    const focusables = modal.querySelectorAll('button:not([disabled]), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                    if (!focusables.length) return;
                    const first = focusables[0];
                    const last = focusables[focusables.length - 1];

                    if (e.shiftKey && document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    } else if (!e.shiftKey && document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                }
            });
        })();
    </script>
    @endpush
    @endif


    @if (!empty($latestPodcasts) && !empty($latestPodcasts['episodes']))
    <x-sections.background-band class="home-section-texture home-section-black">
        <div class="py-12 md:py-16">
            <x-ui.section-heading :title="$homeHeadings['latest_podcasts']['title']" :subtitle="$homeHeadings['latest_podcasts']['subtitle']" />
            <x-home.latest-podcasts :podcasts="$latestPodcasts" />
        </div>
    </x-sections.background-band>
    @endif

    @if (!empty($events))
    <x-sections.background-band class="home-section-texture home-section-cool">
        <div class="py-12 md:py-16">
            <x-ui.section-heading :title="$homeHeadings['upcoming_shows']['title']" :accent="$homeHeadings['upcoming_shows']['accent']" :subtitle="$homeHeadings['upcoming_shows']['subtitle']" />
            <div class="mt-6 sm:mt-8">
                <x-ui.event-list :events="$events" :emphasize-first="true" />
            </div>
        </div>
    </x-sections.background-band>
    @endif

    @if (!empty($newReleases) && $newReleases->count() > 0)
    <x-sections.background-band class="home-section-texture home-section-black">
        <div class="py-12 md:py-16">
            <x-ui.section-heading :title="$homeHeadings['new_album_release']['title'] ?? 'Nuevos'" :accent="$homeHeadings['new_album_release']['accent'] ?? 'Lanzamientos'" :subtitle="'Lo más reciente y destacado en la señal de Seven Rock Radio'" />
            
            <div class="mx-auto max-w-[1200px] px-6 mt-8 md:mt-10">
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($newReleases as $release)
                        @php
                            $cleanRelDesc = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode((string) $release->description, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
                        @endphp
                        <div class="lucille-flat-surface p-4 flex flex-col justify-between transition-colors duration-200 hover:border-lucille-accent/40 group">
                            <div>
                                <!-- Portada -->
                                <div class="relative aspect-square overflow-hidden bg-[#111]">
                                    <img src="{{ $release->cover_image_url ?: asset('assets/lucille/logo.png') }}"
                                         alt="{{ $release->title }}" width="400" height="400"
                                         onerror="if (!this.dataset.fallbackApplied) { this.dataset.fallbackApplied = '1'; this.src = '{{ asset('assets/lucille/logo.png') }}'; }"
                                         class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy" decoding="async">
                                    @if($release->youtube_url)
                                        <a href="{{ $release->youtube_url }}" target="_blank" rel="noreferrer" class="absolute inset-0 flex items-center justify-center bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                            <svg class="h-12 w-12 text-lucille-accent hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M23.498 6.163a3.003 3.003 0 00-2.11-2.11C19.517 3.545 12 3.545 12 3.545s-7.517 0-9.388.508a3.003 3.003 0 00-2.11 2.11C0 8.033 0 12 0 12s0 3.967.502 5.837a3.003 3.003 0 002.11 2.11c1.871.508 9.388.508 9.388.508s7.517 0 9.388-.508a3.003 3.003 0 002.11-2.11C24 15.967 24 12 24 12s0-3.967-.502-5.837zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                            </svg>
                                        </a>
                                    @endif
                                </div>

                                <!-- Meta -->
                                <h3 class="mt-4 font-display text-[15px] sm:text-[16px] uppercase tracking-[.08em] text-lucille-heading line-clamp-1 group-hover:text-lucille-accent transition-colors">{{ $release->title }}</h3>
                                <p class="text-[12px] uppercase tracking-[.18em] text-lucille-body line-clamp-1 mt-1">{{ $release->artist_name }}</p>

                                @if($release->released_at)
                                    <p class="text-[10px] uppercase tracking-[.12em] text-lucille-body/70 mt-1">{{ $release->released_at->locale('es')->translatedFormat('d M, Y') }}</p>
                                @endif

                                @if($cleanRelDesc !== '')
                                    <p class="mt-2.5 text-xs leading-5 text-lucille-body line-clamp-3">{{ $cleanRelDesc }}</p>
                                @endif
                            </div>

                            <div>
                                <!-- Audio Player -->
                                @if($release->audio_url)
                                    <div class="mt-4 border-t border-lucille-line/20 pt-4">
                                        <audio src="{{ $release->audio_url }}" controls class="w-full h-8 accent-lucille-accent dark-audio" controlsList="nodownload"></audio>
                                    </div>
                                @endif

                                <!-- Action Links -->
                                <div class="mt-4 flex items-center justify-between border-t border-lucille-line/20 pt-3">
                                    <div class="flex gap-3">
                                        @if($release->spotify_url)
                                            <a href="{{ $release->spotify_url }}" target="_blank" rel="noreferrer" class="text-[#1DB954] hover:scale-110 transition-transform" title="Escuchar en Spotify">
                                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.02.24-2.82-1.74-6.36-2.129-10.56-1.17-.419.09-.81-.179-.9-.6-.09-.42.18-.81.6-.9 4.62-1.051 8.58-.6 11.76 1.348.36.24.48.66.24 1.022zm1.44-3.3c-.3.42-.84.6-1.26.3-3.24-1.98-8.16-2.58-12-1.38-.479.12-.99-.12-1.11-.6-.12-.48.12-.99.6-1.11 4.38-1.32 9.78-.6 13.5 1.68.42.24.6.78.27 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.3c-.6.18-1.26-.18-1.44-.78-.18-.6.18-1.26.78-1.44 4.26-1.29 11.34-1.02 15.84 1.65.54.3.72 1.02.42 1.56-.3.48-1.02.72-1.56.42z"/>
                                                </svg>
                                            </a>
                                        @endif
                                        @if($release->youtube_url)
                                            <a href="{{ $release->youtube_url }}" target="_blank" rel="noreferrer" class="text-[#FF0000] hover:scale-110 transition-transform" title="Ver en YouTube">
                                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M23.498 6.163a3.003 3.003 0 00-2.11-2.11C19.517 3.545 12 3.545 12 3.545s-7.517 0-9.388.508a3.003 3.003 0 00-2.11 2.11C0 8.033 0 12 0 12s0 3.967.502 5.837a3.003 3.003 0 002.11 2.11c1.871.508 9.388.508 9.388.508s7.517 0 9.388-.508a3.003 3.003 0 002.11-2.11C24 15.967 24 12 24 12s0-3.967-.502-5.837zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                                </svg>
                                            </a>
                                        @endif
                                    </div>
                                    <a href="{{ route('new-releases.single', $release->slug) }}" class="text-[11px] uppercase tracking-[.18em] text-lucille-heading hover:text-lucille-accent transition-colors">Reseña &rarr;</a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </x-sections.background-band>
    @endif

    @if ($album)
    <x-sections.background-band class="home-section-texture home-section-gray">
        <div class="py-12 md:py-16">
            <x-ui.section-heading :title="$homeHeadings['new_album_release']['title']" :accent="$homeHeadings['new_album_release']['accent']" :subtitle="$homeHeadings['new_album_release']['subtitle'] ?: (data_get($album, 'artist', '') . ' - ' . data_get($album, 'title', ''))" />
            <x-ui.album-feature :album="$album" :cover-image="$themeAppearance['home_album_cover_url']" />
        </div>
    </x-sections.background-band>
    @endif

    @if (!empty($galleryImages))
    <x-sections.background-band class="home-section-texture home-section-gray">
        <div class="py-12 md:py-16">
            <x-ui.section-heading :title="$homeHeadings['featured_gallery_images']['title']" :accent="$homeHeadings['featured_gallery_images']['accent']" :subtitle="$homeHeadings['featured_gallery_images']['subtitle']" />
            <x-ui.gallery-strip :images="$galleryImages" />
            <div class="mt-8 text-center">
                <a href="{{ route('gallery') }}" class="lucille-button">{{ $ui['more_images'] }}</a>
            </div>
        </div>
    </x-sections.background-band>
    @endif

    <x-sections.video-feature :videos="$featuredVideos" :image="$themeAppearance['home_video_image_url']" />

    @if (!empty($posts))
    <x-sections.background-band class="home-section-texture home-section-gray">
        <div class="py-12 md:py-16">
            <x-ui.section-heading :title="$homeHeadings['latest_news']['title']" :accent="$homeHeadings['latest_news']['accent']" :subtitle="$homeHeadings['latest_news']['subtitle']" />
            <x-ui.post-grid :posts="$posts" />
        </div>
    </x-sections.background-band>
    @endif

    <x-sections.background-band class="home-section-texture home-section-black">
        <div class="py-12 md:py-16">
            <x-ui.section-heading :title="$homeHeadings['send_message']['title']" :accent="$homeHeadings['send_message']['accent']" :subtitle="$homeHeadings['send_message']['subtitle']" />
            <div class="mt-8 md:mt-12">
                <form method="POST" action="{{ route('home.contact.send') }}" class="grid gap-x-4 gap-y-6" x-data="{ subject: 'general', dropdownOpen: false, selectedLabel: 'Consulta general / Otro' }">
                @csrf
                    <div class="hidden" style="display:none !important" aria-hidden="true">
                        <input type="text" name="user_website" tabindex="-1" autocomplete="off">
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <label for="contact-name" class="block font-display text-[11px] uppercase tracking-[.14em] text-[#dcdcdc] mb-1.5">{{ $ui['your_name'] ?? 'Tu nombre *' }}</label>
                            <input type="text" id="contact-name" name="name" placeholder="{{ $ui['your_name'] }}" class="lucille-home-input w-full" required>
                        </div>
                        <div>
                            <label for="contact-email" class="block font-display text-[11px] uppercase tracking-[.14em] text-[#dcdcdc] mb-1.5">{{ $ui['email_address'] ?? 'Correo electrónico *' }}</label>
                            <input type="email" id="contact-email" name="email" placeholder="{{ $ui['email_address'] }}" class="lucille-home-input w-full" required>
                        </div>
                        <div>
                            <label for="contact-phone" class="block font-display text-[11px] uppercase tracking-[.14em] text-[#dcdcdc] mb-1.5">{{ $ui['phone'] ?? 'Teléfono' }}</label>
                            <input type="tel" id="contact-phone" name="phone" placeholder="{{ $ui['phone'] }}" class="lucille-home-input w-full">
                        </div>
                    </div>
                    
                    <div class="grid gap-4" :class="subject === 'join_radio' ? 'md:grid-cols-2' : 'md:grid-cols-1'">
                        <div class="relative w-full">
                            <label id="contact-subject-label" class="block font-display text-[11px] uppercase tracking-[.14em] text-[#dcdcdc] mb-1.5">Asunto / Motivo</label>
                            <input type="hidden" name="subject" :value="subject">
                            
                            <button type="button" aria-labelledby="contact-subject-label" @click="dropdownOpen = !dropdownOpen" @click.away="dropdownOpen = false" 
                                class="lucille-home-input w-full flex items-center justify-between text-left" 
                                style="color: #dcdcdc; background-color: #111113; cursor: pointer; height: 50px; border: 1px solid rgba(255,255,255,0.06); padding: 0 16px; font-size: 14px;">
                                <span x-text="selectedLabel"></span>
                                <svg class="w-4 h-4 ml-2 transition-transform duration-200" :class="dropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="14" height="14">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            
                            <ul x-show="dropdownOpen" x-cloak x-transition.opacity 
                                class="absolute left-0 z-30 mt-1 w-full border border-white/10 bg-[#111113] rounded-[8px] py-1 shadow-2xl" 
                                style="max-height: 250px; overflow-y: auto; list-style: none; padding: 0; margin: 0;">
                                <li>
                                    <button type="button" @click="subject = 'general'; selectedLabel = 'Consulta general / Otro'; dropdownOpen = false" 
                                        class="w-full text-left px-4 py-3 text-sm transition-colors duration-150"
                                        :class="subject === 'general' ? 'bg-[var(--lucille-accent)] text-white' : 'text-[#dcdcdc] hover:bg-white/5'">
                                        Consulta general / Otro
                                    </button>
                                </li>
                                <li>
                                    <button type="button" @click="subject = 'join_radio'; selectedLabel = 'Quiero pertenecer a la radio (Banda / Artista)'; dropdownOpen = false" 
                                        class="w-full text-left px-4 py-3 text-sm transition-colors duration-150"
                                        :class="subject === 'join_radio' ? 'bg-[var(--lucille-accent)] text-white' : 'text-[#dcdcdc] hover:bg-white/5'">
                                        Quiero pertenecer a la radio (Banda / Artista)
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <div x-show="subject === 'join_radio'" x-cloak x-transition.opacity>
                            <label for="contact-band-name" class="block font-display text-[11px] uppercase tracking-[.14em] text-[#dcdcdc] mb-1.5">Nombre de la banda o artista *</label>
                            <input type="text" id="contact-band-name" name="band_name" placeholder="Nombre de la banda o artista" class="lucille-home-input w-full" :required="subject === 'join_radio'">
                        </div>
                    </div>

                    <div>
                        <label for="contact-message" class="block font-display text-[11px] uppercase tracking-[.14em] text-[#dcdcdc] mb-1.5">{{ $ui['write_comment'] ?? 'Tu mensaje *' }}</label>
                        <textarea id="contact-message" name="message" placeholder="{{ $ui['write_comment'] }}" rows="6" class="lucille-home-input lucille-home-textarea w-full" required></textarea>
                    </div>
                    <div>
                        <button type="submit" class="lucille-button-solid">{{ $ui['send_email'] }}</button>
                    </div>
                </form>
            </div>
        </div>
    </x-sections.background-band>
</x-layouts.site>
