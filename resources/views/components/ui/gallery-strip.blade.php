@props(['images'])

@php
    $lightboxImages = collect($images)
        ->map(fn ($image) => ['src' => $image->image_url, 'caption' => $image->caption])
        ->values();
@endphp

<div
    class="mt-[60px]"
    x-data="galleryLightbox({{ Js::from($lightboxImages) }})"
    @keydown.escape.window="close()"
    @keydown.arrow-right.window="open && next()"
    @keydown.arrow-left.window="open && prev()"
>
    <div class="grid auto-rows-[150px] grid-cols-2 gap-[5px] md:auto-rows-[190px] md:grid-cols-4 lg:auto-rows-[170px] lg:grid-cols-6">
        @foreach ($images as $image)
            @php
                $spanClass = match ($loop->index % 7) {
                    0 => 'md:col-span-2 md:row-span-2',
                    3 => 'lg:col-span-2',
                    5 => 'md:row-span-2',
                    default => '',
                };
            @endphp
            <a
                href="{{ $image->image_url }}"
                data-lightbox="gallery"
                data-title="{{ $image->caption }}"
                class="lucille-gallery-tile group relative block overflow-hidden bg-[#1d1d1d] {{ $spanClass }}"
                @click.prevent="show({{ $loop->index }})"
            >
                <img src="{{ $image->image_url }}" alt="{{ $image->caption }}" loading="lazy" class="h-full w-full object-cover transition duration-500 ease-out group-hover:scale-[1.045]">
                <span class="absolute inset-0 bg-[rgba(7,16,33,.4)] opacity-0 transition duration-300 group-hover:opacity-100"></span>
                <span class="absolute inset-x-0 bottom-0 z-10 translate-y-3 bg-gradient-to-t from-black/75 to-transparent px-4 pb-4 pt-10 font-display text-sm uppercase tracking-[.08em] text-white opacity-0 transition duration-300 group-hover:translate-y-0 group-hover:opacity-100">{{ $image->caption }}</span>
            </a>
        @endforeach
    </div>

    <template x-teleport="body">
        <div x-cloak x-show="open">
            <div class="lucille-lightbox-overlay" x-transition.opacity @click="close()"></div>
            <div
                class="lucille-lightbox"
                x-transition.opacity
                role="dialog"
                aria-modal="true"
                aria-label="Gallery"
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
