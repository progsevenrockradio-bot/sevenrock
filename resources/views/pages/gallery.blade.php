<x-layouts.site title="Seven Rock Radio - Galería" description="Galería de fotos de Seven Rock Radio. Imagenes de conciertos, bandas y eventos rockeros.">
    @php
        $lightboxImages = collect($images)
            ->map(fn ($img) => ['src' => $img->url, 'caption' => ($img->title ?? $img->filename) . ' — ' . ($img->talent->band_name ?? 'Talento')])
            ->values();
    @endphp

    <x-sections.page-heading
        title="Galería"
        subtitle="Fotos de nuestros talentos"
    />

    <section>
        <div
            class="lucille-content-box"
            x-data="galleryLightbox({{ Js::from($lightboxImages) }})"
            @keydown.escape.window="close()"
            @keydown.arrow-right.window="open && next()"
            @keydown.arrow-left.window="open && prev()"
        >
            @if ($images->isEmpty())
                <div class="py-16 text-center text-sm text-[#7b7b7b]">
                    No hay imágenes publicadas todavía.
                </div>
            @else
                <div class="columns-1 gap-[5px] md:columns-2 lg:columns-3">
                    @foreach ($images as $image)
                        @php $talent = $image->talent; @endphp
                        <a
                            href="{{ $image->url }}"
                            data-lightbox="gallery"
                            data-title="{{ $image->title ?? $image->filename }}"
                            class="lucille-gallery-tile group relative mb-[5px] block overflow-hidden bg-[#1d1d1d]"
                            @click.prevent="show({{ $loop->index }})"
                        >
                            <img src="{{ $image->url }}" alt="{{ $image->title ?? $image->filename }}" loading="lazy" class="w-full opacity-50 transition duration-500 ease-out group-hover:scale-[1.03] group-hover:opacity-100">
                            <span class="absolute inset-0 bg-[rgba(7,16,33,.4)] opacity-0 transition duration-300 group-hover:opacity-100"></span>
                            <span class="absolute left-1/2 top-1/2 z-10 -translate-x-1/2 -translate-y-1/2 whitespace-nowrap font-display text-xs font-bold uppercase tracking-[5px] text-white opacity-0 transition duration-300 group-hover:opacity-100">
                                {{ $image->title ?? $image->filename }}
                                @if ($talent)
                                    — {{ $talent->band_name }}
                                @endif
                            </span>
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
                            aria-label="Galería"
                            @touchstart.passive="touchStartX = $event.touches[0].clientX"
                            @touchend.passive="swipeEnd($event)"
                        >
                            <button type="button" class="lucille-lightbox-close" @click="close()" aria-label="Cerrar imagen"></button>

                            <div class="lucille-lightbox-frame" @click.stop>
                                <img :src="current.src" :alt="current.caption" class="lucille-lightbox-image" x-transition.opacity.duration.300ms loading="lazy">

                                <button type="button" class="lucille-lightbox-nav lucille-lightbox-prev" @click="prev()" aria-label="Anterior">
                                    <span class="lucille-lightbox-arrow"></span>
                                </button>
                                <button type="button" class="lucille-lightbox-nav lucille-lightbox-next" @click="next()" aria-label="Siguiente">
                                    <span class="lucille-lightbox-arrow"></span>
                                </button>

                                <div class="lucille-lightbox-data">
                                    <span class="lucille-lightbox-caption" x-text="current.caption"></span>
                                    <span class="lucille-lightbox-number" x-text="`Imagen ${active + 1} de ${images.length}`"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            @endif
        </div>
    </section>
</x-layouts.site>