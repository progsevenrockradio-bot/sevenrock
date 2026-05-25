<x-layouts.site title="Seven Rock Radio - Inicio">
    @php
        $homeHeadings = $themeAppearance['home_headings'];
        $ui = $themeAppearance['ui_texts'];
    @endphp

    <x-sections.hero-rocks :slides="[
        ['image' => $themeAppearance['hero_slide_primary_url']],
        ['image' => $themeAppearance['hero_slide_secondary_url']],
    ]" />

    <x-home.headline-ticker :ticker="$headlineTicker" />

    <x-sections.background-band class="home-section-texture home-section-gray" style="margin-top: 100px; margin-bottom: 100px;">
        <div class="pt-[100px] pb-[80px]">
            <x-ui.section-heading :title="$homeHeadings['featured_stories']['title']" :subtitle="$homeHeadings['featured_stories']['subtitle']" />
            @php($featuredTalents = app(\App\Services\FeaturedTalentService::class)->getFeatured())
            @if ($featuredTalents->isNotEmpty())
            <div class="featured-carousel mt-10">
                @foreach ($featuredTalents as $talent)
                    <a href="{{ route('talents.show', ['bandName' => $talent->band_name]) }}" class="featured-card">
                        <div class="featured-rank">#{{ $loop->iteration }}</div>
                        <img src="{{ $talent->logoUrl() ?? asset('assets/lucille/beatles_t_shirt.jpeg') }}" alt="{{ $talent->band_name }}">
                        <h3>{{ $talent->band_name }}</h3>
                        <div class="featured-stats">
                            <span>❤️ {{ $talent->interactions()->where('type', 'like')->count() }}</span>
                            <span>👁️ {{ $talent->interactions()->where('type', 'view')->count() }}</span>
                        </div>
                        @if ($talent->plan === 'premium')
                            <span class="premium-badge">PREMIUM</span>
                        @endif
                    </a>
                @endforeach
            </div>
            <div class="featured-cta text-center mt-10">
                <a href="{{ route('talents.explore') }}" class="btn btn-outline">Ver todos los talentos →</a>
            </div>
            @endif
        </div>
    </x-sections.background-band>

    @if ($nextProgram)
    <x-sections.background-band class="home-section-texture home-section-cool" style="margin-bottom: 100px;">
        <div class="pt-[100px] pb-[80px]">
            <x-ui.section-heading :title="$homeHeadings['next_program']['title']" :subtitle="$homeHeadings['next_program']['subtitle']" />
            <x-home.next-program :program="$nextProgram" />
        </div>
    </x-sections.background-band>
    @endif

    @if (!empty($latestPodcasts) && !empty($latestPodcasts['episodes']))
    <x-sections.background-band class="home-section-texture home-section-black" style="margin-bottom: 100px;">
        <div class="pt-[100px] pb-[80px]">
            <x-ui.section-heading :title="$homeHeadings['latest_podcasts']['title']" :subtitle="$homeHeadings['latest_podcasts']['subtitle']" />
            <x-home.latest-podcasts :podcasts="$latestPodcasts" />
        </div>
    </x-sections.background-band>
    @endif

    @if ($events && $events->isNotEmpty())
    <x-sections.background-band class="home-section-texture home-section-cool" style="margin-bottom: 100px;">
        <div class="pt-[100px] pb-[80px]">
            <x-ui.section-heading :title="$homeHeadings['upcoming_shows']['title']" :accent="$homeHeadings['upcoming_shows']['accent']" :subtitle="$homeHeadings['upcoming_shows']['subtitle']" />
            <x-ui.event-list :events="$events" />
        </div>
    </x-sections.background-band>
    @endif

    @if ($album)
    <x-sections.background-band class="home-section-texture home-section-gray" style="margin-bottom: 100px;">
        <div class="pt-[100px] pb-[80px]">
            <x-ui.section-heading :title="$homeHeadings['new_album_release']['title']" :accent="$homeHeadings['new_album_release']['accent']" :subtitle="$homeHeadings['new_album_release']['subtitle'] ?: ($album?->artist . ' - ' . $album?->title)" />
            <x-ui.album-feature :album="$album" :cover-image="$themeAppearance['home_album_cover_url']" />
        </div>
    </x-sections.background-band>
    @endif

    @if (!empty($galleryImages))
    <x-sections.background-band class="home-section-texture home-section-gray" style="margin-bottom: 100px;">
        <div class="pt-[100px] pb-[80px]">
            <x-ui.section-heading :title="$homeHeadings['featured_gallery_images']['title']" :accent="$homeHeadings['featured_gallery_images']['accent']" :subtitle="$homeHeadings['featured_gallery_images']['subtitle']" />
            <x-ui.gallery-strip :images="$galleryImages" />
            <div class="mt-9 text-center">
                <a href="{{ route('gallery') }}" class="lucille-button">{{ $ui['more_images'] }}</a>
            </div>
        </div>
    </x-sections.background-band>
    @endif

    <x-sections.video-feature :video="$video" :image="$themeAppearance['home_video_image_url']" />

    @if (!empty($posts))
    <x-sections.background-band class="home-section-texture home-section-gray" style="margin-bottom: 100px;">
        <div class="pt-[100px] pb-[80px]">
            <x-ui.section-heading :title="$homeHeadings['latest_news']['title']" :accent="$homeHeadings['latest_news']['accent']" :subtitle="$homeHeadings['latest_news']['subtitle']" />
            <x-ui.post-grid :posts="$posts" />
        </div>
    </x-sections.background-band>
    @endif

    <x-sections.background-band class="home-section-texture home-section-black" style="margin-bottom: 100px;">
        <div class="pt-[100px] pb-[80px]">
            <x-ui.section-heading :title="$homeHeadings['send_message']['title']" :accent="$homeHeadings['send_message']['accent']" :subtitle="$homeHeadings['send_message']['subtitle']" />
            <div class="mt-[80px]">
                <form method="POST" action="{{ route('home.contact.send') }}" class="grid gap-x-4 gap-y-8">
                @csrf
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