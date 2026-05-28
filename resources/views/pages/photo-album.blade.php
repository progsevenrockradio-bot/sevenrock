<x-layouts.site title="Seven Rock Radio - {{ $album['title'] }}">
    @php
        $lightboxImages = collect($images)
            ->map(fn ($image) => ['src' => asset($image['image']), 'caption' => $image['caption']])
            ->values();
    @endphp

    <x-sections.page-heading
        :title="$album['title']"
        subtitle="Galería de Fotos"
        overlay="rgba(0,0,0,0)"
        :categories="$album['categories']"
    />

    <section>
        <div
            class="lucille-content-box"
            x-data="galleryLightbox({{ Js::from($lightboxImages) }})"
            @keydown.escape.window="close()"
            @keydown.arrow-right.window="open && next()"
            @keydown.arrow-left.window="open && prev()"
        >
            <article class="mx-auto max-w-[1200px]">
                <div class="grid gap-10 lg:grid-cols-[1.05fr_0.95fr]">
                    <div class="space-y-6 text-[#7b7b7b]">
                        <h3 class="font-display text-[18px] font-bold uppercase tracking-[.12em] text-[#dcdcdc]">Galería Destacada</h3>
                        <p>This was a phenomenal performance. Auerbach radiated energy and the the audience responded in kind. It was the perfect balance of performance and appreciation. The music was terrific and the visuals were delightful. The many familiar favorites were played with inventiveness and spontaneity. Nothing was stale.</p>
                        <p>This was a phenomenal performance. Auerbach radiated energy and the the audience responded in kind. It was the perfect balance of performance and appreciation. The music was terrific and the visuals were delightful. The many familiar favorites were played with inventiveness and spontaneity. Nothing was stale.</p>
                        <p>This was a phenomenal performance. Auerbach radiated energy and the the audience responded in kind. It was the perfect balance of performance and appreciation. The music was terrific and the visuals were delightful. The many familiar favorites were played with inventiveness and spontaneity. Nothing was stale.</p>

                        <div class="lucille-share-row pt-2">
                            <span>Share:</span>
                            <a href="#" aria-label="Share on Twitter">T</a>
                            <a href="#" aria-label="Share on Facebook">F</a>
                            <a href="#" aria-label="Share on Pinterest">P</a>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-[5px] sm:grid-cols-3">
                            @foreach ($images as $image)
                                <a
                                    href="{{ asset($image['image']) }}"
                                    data-lightbox="photo_album"
                                    data-title="{{ $image['caption'] }}"
                                    class="img_box group relative block overflow-hidden bg-[#1d1d1d]"
                                    @click.prevent="show({{ $loop->index }})"
                                >
                                    <img src="{{ asset($image['image']) }}" alt="{{ $image['caption'] }}" loading="lazy" class="aspect-square w-full object-cover opacity-50 transition duration-500 ease-out group-hover:scale-[1.03] group-hover:opacity-100">
                                    <span class="absolute inset-0 bg-[rgba(7,16,33,.4)] opacity-0 transition duration-300 group-hover:opacity-100"></span>
                                    <span class="absolute left-1/2 top-1/2 z-10 -translate-x-1/2 -translate-y-1/2 font-display text-xs font-bold uppercase tracking-[5px] text-white opacity-0 transition duration-300 group-hover:opacity-100">{{ $image['caption'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </article>

            <template x-teleport="body">
                <div x-cloak x-show="open">
                    <div class="lucille-lightbox-overlay" x-transition.opacity @click="close()"></div>
                    <div
                        class="lucille-lightbox"
                        x-transition.opacity
                        role="dialog"
                        aria-modal="true"
                        aria-label="Photo gallery"
                        @touchstart.passive="touchStartX = $event.touches[0].clientX"
                        @touchend.passive="swipeEnd($event)"
                    >
                        <button type="button" class="lucille-lightbox-close" @click="close()" aria-label="Close image"></button>

                        <div class="lucille-lightbox-frame" @click.stop>
                            <img :src="current.src" :alt="current.caption" class="lucille-lightbox-image" x-transition.opacity.duration.300ms loading="lazy">

                            <button type="button" class="lucille-lightbox-nav lucille-lightbox-prev" @click="prev()" aria-label="Previous image">
                                <span class="lucille-lightbox-arrow"></span>
                            </button>
                            <button type="button" class="lucille-lightbox-nav lucille-lightbox-next" @click="next()" aria-label="Next image">
                                <span class="lucille-lightbox-arrow"></span>
                            </button>

                            <div class="lucille-lightbox-data">
                                <span class="lucille-lightbox-caption" x-text="current.caption"></span>
                                <span class="lucille-lightbox-number" x-text="`Image ${active + 1} of ${images.length}`"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </section>
</x-layouts.site>
