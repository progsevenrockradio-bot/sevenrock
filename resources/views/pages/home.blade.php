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

    <x-sections.hero-rocks :slides="$themeAppearance['hero_slides']" :interval="$themeAppearance['hero_slides_interval']" :transition="$themeAppearance['hero_slides_transition']" />

    <x-home.headline-ticker :ticker="$headlineTicker" />

    @if (!empty($noticiasRock) && $noticiasRock->count() > 0)
    <x-sections.background-band class="home-section-texture home-section-gray">
        <div class="pt-[100px] pb-[80px]">
            <x-ui.section-heading title="Noticias" accent="Rock" subtitle="Lo último en el mundo del rock y metal" />
            
            <div class="mx-auto max-w-[1200px] px-6 mt-10">
                <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($noticiasRock as $post)
                        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.8)] p-4 flex flex-col justify-between transition-all duration-300 hover:-translate-y-2 hover:border-[#c32720]/40 group relative cursor-pointer">
                            <a href="{{ route('posts.single', ['year' => $post->published_at->format('Y'), 'month' => $post->published_at->format('m'), 'day' => $post->published_at->format('d'), 'slug' => $post->slug]) }}" class="absolute inset-0 z-30" aria-label="Leer más sobre {{ $post->title }}"></a>
                            <div>
                                <div class="relative aspect-[4/3] overflow-hidden border border-[#2b2b2b] bg-[#111]">
                                    <img src="{{ $post->featured_image_url ?: asset('assets/lucille/logo.png') }}" alt="{{ $post->title }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy" decoding="async">
                                </div>
                                <h4 class="mt-4 font-display text-[16px] uppercase tracking-[.08em] text-[#dcdcdc] line-clamp-2 group-hover:text-[#c32720] transition-colors relative z-20">
                                    {{ $post->title }}
                                </h4>
                                @if($post->published_at)
                                    <p class="text-[10px] uppercase tracking-[.12em] text-[#555] mt-2 relative z-20">{{ $post->published_at->translatedFormat('d M, Y') }}</p>
                                @endif
                                @if($post->excerpt)
                                    <p class="mt-3 text-xs leading-5 text-[#7b7b7b] line-clamp-3 relative z-20">{{ $post->excerpt }}</p>
                                @endif
                            </div>
                            <div class="mt-4 border-t border-[#222] pt-3 text-right relative z-20">
                                <span class="text-[11px] uppercase tracking-[.18em] text-[#dcdcdc] group-hover:text-[#c32720] transition-colors">Leer más &rarr;</span>
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
        <div class="pt-[100px] pb-[80px]">
            <x-ui.section-heading :title="$homeHeadings['featured_stories']['title']" :subtitle="$homeHeadings['featured_stories']['subtitle']" />
            <x-home.featured-stories :stories="$featuredStories" />
        </div>
    </x-sections.background-band>
    @endif

    @if ($nextProgram)
    <x-sections.background-band class="home-section-texture home-section-cool">
        <div class="pt-[100px] pb-[80px]">
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

                    {{-- Etiqueta "HOY EN EL ROCK" --}}
                    <div class="relative w-full py-2.5 flex flex-col items-center justify-center bg-[#c32720] px-5 shadow-[0_4px_15px_rgba(195,39,32,.4)] md:absolute md:left-0 md:top-0 md:h-full md:w-auto md:z-20 md:shadow-[4px_0_20px_rgba(195,39,32,.5)]">
                        <div class="flex flex-col items-center justify-center">
                            <span class="whitespace-nowrap font-display text-[10px] uppercase tracking-[.22em] text-white">
                                🎸 Hoy en el Rock
                            </span>
                            <span class="mt-[2px] whitespace-nowrap font-sans text-[7px] uppercase tracking-[.15em] text-white/80">
                                {{ \Carbon\Carbon::now()->locale('es')->isoFormat('D \d\e MMMM YYYY') }}
                            </span>
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

            {{-- Doble pasada para loop continuo --}}
            @foreach([0,1] as $_)
                @foreach($efemItems as $idx => $post)
                    @php $postTags = $efemTagsByPost[$post->id] ?? []; @endphp
                    <button
                        type="button"
                        data-efem-idx="{{ $idx }}"
                        class="efem-pill group inline-flex shrink-0 cursor-pointer items-center gap-2 border-r border-[#1e1e1e] px-6 py-4 text-left transition-colors duration-200 hover:bg-[#c32720]/10 focus:outline-none"
                        onclick="openEfemModal({{ $idx }})"
                        aria-label="Leer: {{ $post->title }}"
                    >
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-[#c32720] opacity-70 group-hover:opacity-100 transition-opacity"></span>
                        <span class="whitespace-nowrap text-[12px] tracking-wide text-[#9a9a9a] group-hover:text-[#e0e0e0] transition-colors">
                            {{ $post->title }}
                        </span>
                        @if(!empty($postTags[0]))
                            <span class="whitespace-nowrap text-[10px] tracking-wider text-[#c32720]/70 group-hover:text-[#c32720] transition-colors font-medium">
                                {{ $postTags[0] }}
                            </span>
                        @endif
                    </button>
                @endforeach
            @endforeach
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
            <div class="absolute left-0 top-0 h-[2px] w-full bg-gradient-to-r from-[#c32720] via-[#ff4a42] to-transparent rounded-t-sm"></div>

            {{-- Cerrar --}}
            <button onclick="closeEfemModal()" class="absolute right-4 top-4 text-[#555] hover:text-[#e0e0e0] transition-colors focus:outline-none" aria-label="Cerrar">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            {{-- Icono musical --}}
            <div class="mb-5 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-[#c32720]/15 text-[#c32720]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 3v10.55A4 4 0 1 0 11 17V7h4V3H9z"/>
                    </svg>
                </span>
                <p class="text-[10px] uppercase tracking-[.2em] text-[#c32720]">Hoy en el Rock</p>
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
            'content' => $p->excerpt ?: $p->title,
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
        #efemerides-ticker:hover .efem-track { animation-play-state: paused; }
    </style>

    <script>
        (function () {
            const data   = JSON.parse(document.getElementById('efem-data').textContent || '[]');
            const modal  = document.getElementById('efem-modal');
            const box    = document.getElementById('efem-modal-box');
            const title  = document.getElementById('efem-modal-title');
            const body   = document.getElementById('efem-modal-body');

            window.openEfemModal = function (idx) {
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
                        pill.className = 'inline-block rounded-full border border-[#c32720]/40 bg-[#c32720]/10 px-3 py-1 text-[10px] font-semibold uppercase tracking-[.16em] text-[#c32720]';
                        pill.textContent = tag;
                        tagsEl.appendChild(pill);
                    });
                }

                modal.classList.remove('opacity-0', 'pointer-events-none');
                modal.classList.add('opacity-100');
                box.classList.remove('scale-95');
                box.classList.add('scale-100');
                document.body.style.overflow = 'hidden';
            };

            window.closeEfemModal = function () {
                modal.classList.add('opacity-0', 'pointer-events-none');
                modal.classList.remove('opacity-100');
                box.classList.add('scale-95');
                box.classList.remove('scale-100');
                document.body.style.overflow = '';
            };

            // Cerrar al hacer clic fuera del box
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeEfemModal();
            });

            // Cerrar con Escape
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeEfemModal();
            });
        })();
    </script>
    @endpush
    @endif


    @if (!empty($latestPodcasts) && !empty($latestPodcasts['episodes']))
    <x-sections.background-band class="home-section-texture home-section-black">
        <div class="pt-[100px] pb-[80px]">
            <x-ui.section-heading :title="$homeHeadings['latest_podcasts']['title']" :subtitle="$homeHeadings['latest_podcasts']['subtitle']" />
            <x-home.latest-podcasts :podcasts="$latestPodcasts" />
        </div>
    </x-sections.background-band>
    @endif

    @if (!empty($events))
    <x-sections.background-band class="home-section-texture home-section-cool">
        <div class="pt-[100px] pb-[80px]">
            <x-ui.section-heading :title="$homeHeadings['upcoming_shows']['title']" :accent="$homeHeadings['upcoming_shows']['accent']" :subtitle="$homeHeadings['upcoming_shows']['subtitle']" />
            <x-ui.event-list :events="$events" />
        </div>
    </x-sections.background-band>
    @endif



    @if ($album)
    <x-sections.background-band class="home-section-texture home-section-gray">
        <div class="pt-[100px] pb-[80px]">
            <x-ui.section-heading :title="$homeHeadings['new_album_release']['title']" :accent="$homeHeadings['new_album_release']['accent']" :subtitle="$homeHeadings['new_album_release']['subtitle'] ?: (data_get($album, 'artist', '') . ' - ' . data_get($album, 'title', ''))" />
            <x-ui.album-feature :album="$album" :cover-image="$themeAppearance['home_album_cover_url']" />
        </div>
    </x-sections.background-band>
    @endif

    @if (!empty($galleryImages))
    <x-sections.background-band class="home-section-texture home-section-gray">
        <div class="pt-[100px] pb-[80px]">
            <x-ui.section-heading :title="$homeHeadings['featured_gallery_images']['title']" :accent="$homeHeadings['featured_gallery_images']['accent']" :subtitle="$homeHeadings['featured_gallery_images']['subtitle']" />
            <x-ui.gallery-strip :images="$galleryImages" />
            <div class="mt-9 text-center">
                <a href="{{ route('gallery') }}" class="lucille-button">{{ $ui['more_images'] }}</a>
            </div>
        </div>
    </x-sections.background-band>
    @endif

    <x-sections.video-feature :videos="$featuredVideos" :image="$themeAppearance['home_video_image_url']" />

    @if (!empty($posts))
    <x-sections.background-band class="home-section-texture home-section-gray">
        <div class="pt-[100px] pb-[80px]">
            <x-ui.section-heading :title="$homeHeadings['latest_news']['title']" :accent="$homeHeadings['latest_news']['accent']" :subtitle="$homeHeadings['latest_news']['subtitle']" />
            <x-ui.post-grid :posts="$posts" />
        </div>
    </x-sections.background-band>
    @endif

    <x-sections.background-band class="home-section-texture home-section-black">
        <div class="pt-[100px] pb-[80px]">
            <x-ui.section-heading :title="$homeHeadings['send_message']['title']" :accent="$homeHeadings['send_message']['accent']" :subtitle="$homeHeadings['send_message']['subtitle']" />
            <div class="mt-[80px]">
                <form method="POST" action="{{ route('home.contact.send') }}" class="grid gap-x-4 gap-y-8" x-data="{ subject: 'general', dropdownOpen: false, selectedLabel: 'Consulta general / Otro' }">
                @csrf
                    <div class="hidden" style="display:none !important" aria-hidden="true">
                        <input type="text" name="user_website" tabindex="-1" autocomplete="off">
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        <input type="text" name="name" placeholder="{{ $ui['your_name'] }}" class="lucille-home-input" required>
                        <input type="email" name="email" placeholder="{{ $ui['email_address'] }}" class="lucille-home-input" required>
                        <input type="tel" name="phone" placeholder="{{ $ui['phone'] }}" class="lucille-home-input">
                    </div>
                    
                    <div class="grid gap-4" :class="subject === 'join_radio' ? 'md:grid-cols-2' : 'md:grid-cols-1'">
                        <div class="relative w-full">
                            <input type="hidden" name="subject" :value="subject">
                            
                            <button type="button" @click="dropdownOpen = !dropdownOpen" @click.away="dropdownOpen = false" 
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
                            <input type="text" name="band_name" placeholder="Nombre de la banda o artista" class="lucille-home-input w-full" :required="subject === 'join_radio'">
                        </div>
                    </div>

                    <textarea name="message" placeholder="{{ $ui['write_comment'] }}" rows="7" class="lucille-home-input lucille-home-textarea" required></textarea>
                    <div>
                        <button type="submit" class="lucille-button-solid">{{ $ui['send_email'] }}</button>
                    </div>
                </form>
            </div>
        </div>
    </x-sections.background-band>
</x-layouts.site>
