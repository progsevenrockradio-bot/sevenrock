<x-layouts.site title="Seven Rock Radio - Blog" description="Blog de Seven Rock Radio. Noticias, entrevistas, lanzamientos y todo sobre el mundo del rock y el metal.">
    <x-sections.page-heading title="Blog" />

    <section>
        <div class="lucille-content-box">
            <div class="grid auto-rows-[260px] grid-cols-1 md:grid-cols-2 lg:grid-cols-4">
                @foreach ($posts as $post)
                    @php
                        $span = $loop->index % 5 === 0 ? 'lg:col-span-2 lg:row-span-2' : ($loop->index % 3 === 0 ? 'lg:col-span-2' : '');
                        $image = data_get($post, 'featured_image_url', data_get($post, 'featured_image'));
                        $url = data_get($post, 'url', route('posts.single', ['year' => data_get($post, 'published_at')?->format('Y') ?? now()->format('Y'), 'month' => data_get($post, 'published_at')?->format('m') ?? now()->format('m'), 'day' => data_get($post, 'published_at')?->format('d') ?? now()->format('d'), 'slug' => data_get($post, 'slug', 'inspiration')]));
                        $title = data_get($post, 'title');
                        $date = data_get($post, 'published_at')?->format('F j, Y');
                        $category = data_get($post, 'categories.0', 'Blog');
                        $excerpt = data_get($post, 'excerpt');
                    @endphp
                    <article class="lucille-masonry-card group relative overflow-hidden bg-[#1d1d1d] {{ $span }}">
                        @if ($image)
                            <img
                                src="{{ str_starts_with($image, 'http') ? $image : asset($image) }}"
                                alt="{{ $title }}"
                                width="1200"
                                height="800"
                                class="lucille-card-bg absolute inset-0 aspect-[3/2] h-full w-full object-cover opacity-55 transition duration-500 ease-out"
                                loading="lazy"
                                decoding="async"
                            >
                            <span class="absolute inset-0 bg-black/20"></span>
                        @else
                            <span class="absolute inset-0 bg-[rgba(7,16,33,.4)]"></span>
                        @endif

                        <div class="absolute inset-x-0 bottom-0 p-7">
                            <h2 class="font-display text-2xl font-light uppercase text-[#f9f9f9] transition duration-300 group-hover:text-lucille-accent">{{ $title }}</h2>
                            <p class="mt-2 text-sm italic text-[#cbcbcb]">{{ $date }} · {{ $category }}</p>
                            <p class="mt-4 line-clamp-3 text-[14px] leading-[26px] text-[#cbcbcb]">{{ $excerpt }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
</x-layouts.site>
