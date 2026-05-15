<x-layouts.site title="Seven Rock Radio - Home">
    @php
        $homeHeadings = $themeAppearance['home_headings'];
        $ui = $themeAppearance['ui_texts'];
    @endphp

    <x-sections.hero-rocks :slides="[
        ['image' => $themeAppearance['hero_slide_primary_url']],
        ['image' => $themeAppearance['hero_slide_secondary_url']],
    ]" />

    <x-sections.background-band class="home-section-texture home-section-gray">
        <div class="py-[90px]">
            <x-ui.section-heading :title="$homeHeadings['featured_stories']['title']" :subtitle="$homeHeadings['featured_stories']['subtitle']" />
            <x-home.featured-stories :stories="$featuredStories" />
        </div>
    </x-sections.background-band>

    <x-sections.background-band class="home-section-texture home-section-cool">
        <div class="py-[90px]">
            <x-ui.section-heading :title="$homeHeadings['next_program']['title']" :subtitle="$homeHeadings['next_program']['subtitle']" />
            <x-home.next-program :program="$nextProgram" />
        </div>
    </x-sections.background-band>

    <x-sections.background-band class="home-section-texture home-section-black">
        <div class="py-[90px]">
            <x-ui.section-heading :title="$homeHeadings['latest_podcasts']['title']" :subtitle="$homeHeadings['latest_podcasts']['subtitle']" />
            <x-home.latest-podcasts :podcasts="$latestPodcasts" />
        </div>
    </x-sections.background-band>

    <x-sections.background-band class="home-section-texture home-section-cool">
        <div class="py-[90px]">
            <x-ui.section-heading :title="$homeHeadings['upcoming_shows']['title']" :accent="$homeHeadings['upcoming_shows']['accent']" :subtitle="$homeHeadings['upcoming_shows']['subtitle']" />
            <x-ui.event-list :events="$events" />
        </div>
    </x-sections.background-band>

    <x-sections.background-band class="home-section-texture home-section-gray">
        <div class="py-[90px]">
            <x-ui.section-heading :title="$homeHeadings['new_album_release']['title']" :accent="$homeHeadings['new_album_release']['accent']" :subtitle="$homeHeadings['new_album_release']['subtitle'] ?: ($album?->artist . ' - ' . $album?->title)" />
            <x-ui.album-feature :album="$album" :cover-image="$themeAppearance['home_album_cover_url']" />
        </div>
    </x-sections.background-band>

    <x-sections.background-band class="home-section-texture home-section-gray">
        <div class="py-[90px]">
            <x-ui.section-heading :title="$homeHeadings['featured_gallery_images']['title']" :accent="$homeHeadings['featured_gallery_images']['accent']" :subtitle="$homeHeadings['featured_gallery_images']['subtitle']" />
            <x-ui.gallery-strip :images="$galleryImages" />
            <div class="mt-9 text-center">
                <a href="{{ route('gallery') }}" class="lucille-button">{{ $ui['more_images'] }}</a>
            </div>
        </div>
    </x-sections.background-band>

    <x-sections.video-feature :video="$video" :image="$themeAppearance['home_video_image_url']" />

    <x-sections.background-band class="home-section-texture home-section-gray">
        <div class="py-[90px]">
            <x-ui.section-heading :title="$homeHeadings['latest_news']['title']" :accent="$homeHeadings['latest_news']['accent']" :subtitle="$homeHeadings['latest_news']['subtitle']" />
            <x-ui.post-grid :posts="$posts" />
        </div>
    </x-sections.background-band>

    <x-sections.background-band class="home-section-texture home-section-black">
        <div class="py-[90px]">
            <x-ui.section-heading :title="$homeHeadings['send_message']['title']" :accent="$homeHeadings['send_message']['accent']" :subtitle="$homeHeadings['send_message']['subtitle']" />
            <div class="mt-[80px]">
                <form class="grid gap-x-4 gap-y-8">
                    <div class="grid gap-4 md:grid-cols-3">
                        <input type="text" placeholder="{{ $ui['your_name'] }}" class="lucille-home-input">
                        <input type="email" placeholder="{{ $ui['email_address'] }}" class="lucille-home-input">
                        <input type="tel" placeholder="{{ $ui['phone'] }}" class="lucille-home-input">
                    </div>
                    <textarea placeholder="{{ $ui['write_comment'] }}" rows="7" class="lucille-home-input lucille-home-textarea"></textarea>
                    <div>
                        <button type="button" class="lucille-button-solid">{{ $ui['send_email'] }}</button>
                    </div>
                </form>
            </div>
        </div>
    </x-sections.background-band>
</x-layouts.site>
