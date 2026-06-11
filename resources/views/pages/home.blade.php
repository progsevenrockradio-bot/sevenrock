<x-layouts.site title="Seven Rock Radio - Inicio" description="Seven Rock Radio — Musica rock, entrevistas, eventos y la mejor vibra. Tu radio rock online. Escucha en vivo, descubre nuevos talentos y disfruta del mejor rock.">
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

    @if (!empty($newReleases) && $newReleases->count() > 0)
    <x-sections.background-band class="home-section-texture home-section-black">
        <div class="pt-[100px] pb-[80px]">
            <x-ui.section-heading title="Nuevos" accent="Lanzamientos" subtitle="Lo más reciente y destacado en la señal de Seven Rock Radio" />
            
            <div class="mx-auto max-w-[1200px] px-6 mt-10">
                <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($newReleases as $release)
                        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.8)] p-4 flex flex-col justify-between transition-all duration-300 hover:-translate-y-2 hover:border-[#c32720]/40 group">
                            <div>
                                <!-- Portada -->
                                <div class="relative aspect-square overflow-hidden border border-[#2b2b2b] bg-[#111]">
                                    <img src="{{ $release->cover_image_url }}" alt="{{ $release->title }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy">
                                    @if($release->youtube_url)
                                        <a href="{{ $release->youtube_url }}" target="_blank" rel="noreferrer" class="absolute inset-0 flex items-center justify-center bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                            <svg class="h-12 w-12 text-[#c32720] hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M23.498 6.163a3.003 3.003 0 00-2.11-2.11C19.517 3.545 12 3.545 12 3.545s-7.517 0-9.388.508a3.003 3.003 0 00-2.11 2.11C0 8.033 0 12 0 12s0 3.967.502 5.837a3.003 3.003 0 002.11 2.11c1.871.508 9.388.508 9.388.508s7.517 0 9.388-.508a3.003 3.003 0 002.11-2.11C24 15.967 24 12 24 12s0-3.967-.502-5.837zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                            </svg>
                                        </a>
                                    @endif
                                </div>

                                <!-- Meta -->
                                <h4 class="mt-4 font-display text-[16px] uppercase tracking-[.08em] text-[#dcdcdc] line-clamp-1 group-hover:text-[#c32720] transition-colors">{{ $release->title }}</h4>
                                <p class="text-[12px] uppercase tracking-[.18em] text-[#7b7b7b] line-clamp-1 mt-1">{{ $release->artist_name }}</p>

                                @if($release->released_at)
                                    <p class="text-[10px] uppercase tracking-[.12em] text-[#555] mt-1">{{ $release->released_at->translatedFormat('d M, Y') }}</p>
                                @endif

                                @if($release->description)
                                    <p class="mt-3 text-xs leading-5 text-[#7b7b7b] line-clamp-3">{{ $release->description }}</p>
                                @endif
                            </div>

                            <div>
                                <!-- Audio Player -->
                                @if($release->audio_url)
                                    <div class="mt-4 border-t border-[#222] pt-4">
                                        <audio src="{{ $release->audio_url }}" controls class="w-full h-8 accent-[#c32720] dark-audio" controlsList="nodownload"></audio>
                                    </div>
                                @endif

                                <!-- Action Links -->
                                <div class="mt-4 flex items-center justify-between border-t border-[#222] pt-3">
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
                                    <a href="{{ route('new-releases.single', $release->slug) }}" class="text-[11px] uppercase tracking-[.18em] text-[#dcdcdc] hover:text-[#c32720] transition-colors">Reseña &rarr;</a>
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
                <form method="POST" action="{{ route('home.contact.send') }}" class="grid gap-x-4 gap-y-8">
                @csrf
                    <div class="hidden" style="display:none !important" aria-hidden="true">
                        <input type="text" name="user_website" tabindex="-1" autocomplete="off">
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        <input type="text" name="name" placeholder="{{ $ui['your_name'] }}" class="lucille-home-input" required>
                        <input type="email" name="email" placeholder="{{ $ui['email_address'] }}" class="lucille-home-input" required>
                        <input type="tel" name="phone" placeholder="{{ $ui['phone'] }}" class="lucille-home-input">
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
