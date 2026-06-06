<x-layouts.site title="Seven Rock Radio - Blog" description="Blog de Seven Rock Radio. Noticias, entrevistas, lanzamientos y todo sobre el mundo del rock y el metal.">
    <x-sections.page-heading title="Blog" />

    <section>
        <div class="lucille-content-box">
            <div class="grid auto-rows-[260px] grid-cols-1 md:grid-cols-2 lg:grid-cols-4">
                @foreach ($posts as $post)
                    @php
                        $span = $loop->index % 5 === 0 ? 'lg:col-span-2 lg:row-span-2' : ($loop->index % 3 === 0 ? 'lg:col-span-2' : '');
                        $image = data_get($post, 'featured_image_url') ?: data_get($post, 'featured_image_path');
                        $publishedAt = data_get($post, 'published_at');
                        if (is_string($publishedAt)) {
                            $publishedAt = \Carbon\Carbon::parse($publishedAt);
                        }
                        $url = data_get($post, 'url', route('posts.single', ['year' => $publishedAt?->format('Y') ?? now()->format('Y'), 'month' => $publishedAt?->format('m') ?? now()->format('m'), 'day' => $publishedAt?->format('d') ?? now()->format('d'), 'slug' => data_get($post, 'slug', 'inspiration')]));
                        $title = data_get($post, 'title');
                        $date = $publishedAt?->format('F j, Y');
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
                            <div class="mt-3 inline-flex items-center gap-2">
                                <span class="content-reaction-count">♥ {{ (int) data_get($post, 'likes_count', 0) }}</span>
                                <span class="text-xs uppercase tracking-[.18em] text-[#cbcbcb]/80">Me gusta</span>
                            </div>
                            <p class="mt-4 line-clamp-3 text-[14px] leading-[26px] text-[#cbcbcb]">{{ $excerpt }}</p>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($posts instanceof \Illuminate\Pagination\LengthAwarePaginator)
                @php
                    $currentPage = $posts->currentPage();
                    $lastPage = $posts->lastPage();
                    $pages = [];

                    for ($page = 1; $page <= $lastPage; $page++) {
                        if ($page === 1 || $page === $lastPage || abs($page - $currentPage) <= 1) {
                            $pages[] = [
                                'type' => 'page',
                                'page' => $page,
                                'url' => $posts->url($page),
                                'current' => $page === $currentPage,
                            ];
                            continue;
                        }

                        $lastItem = end($pages);
                        if (! $lastItem || $lastItem['type'] !== 'ellipsis') {
                            $pages[] = ['type' => 'ellipsis'];
                        }
                    }
                @endphp

                <nav class="mt-14 flex flex-wrap items-center justify-center gap-2 border border-white/10 bg-[#050505f5] px-3 py-3.5 shadow-[0_28px_70px_rgba(0,0,0,.55)] md:px-5 md:py-4" aria-label="Paginación del blog">
                    @php $previousUrl = $posts->previousPageUrl(); @endphp
                    <a
                        href="{{ $previousUrl ?: '#' }}"
                        class="inline-flex items-center gap-2 rounded-none border border-white/10 bg-black/20 px-3 py-2 font-display text-[10px] uppercase tracking-[.22em] text-[#7b7b7b] transition hover:border-white/20 hover:bg-white/5 hover:text-lucille-accent {{ $previousUrl ? '' : 'pointer-events-none opacity-40' }}"
                        aria-label="Página anterior"
                        @if (! $previousUrl) aria-disabled="true" tabindex="-1" @endif
                    >
                        <span class="text-base font-bold leading-none text-lucille-accent">←</span>
                        <span>Más antiguos</span>
                    </a>

                    <div class="flex flex-wrap items-center justify-center gap-1.5">
                        @foreach ($pages as $item)
                            @if ($item['type'] === 'ellipsis')
                                <span class="px-2 text-[#7b7b7b]">…</span>
                            @else
                                <a
                                    href="{{ $item['url'] }}"
                                    class="inline-flex h-9 min-w-9 items-center justify-center border px-2 font-display text-[10px] uppercase tracking-[.18em] transition {{ $item['current'] ? 'border-2 border-lucille-accent bg-lucille-accent text-black shadow-[0_0_0_1px_rgba(195,39,32,.55)]' : 'border-white/10 bg-black/20 text-[#d8d1c6] hover:border-white/20 hover:bg-white/5 hover:text-white' }}"
                                    aria-current="{{ $item['current'] ? 'page' : 'false' }}"
                                >
                                    {{ $item['page'] }}
                                </a>
                            @endif
                        @endforeach
                    </div>

                    @php $nextUrl = $posts->nextPageUrl(); @endphp
                    <a
                        href="{{ $nextUrl ?: '#' }}"
                        class="inline-flex items-center gap-2 rounded-none border border-white/10 bg-black/20 px-3 py-2 font-display text-[10px] uppercase tracking-[.22em] text-[#7b7b7b] transition hover:border-white/20 hover:bg-white/5 hover:text-lucille-accent {{ $nextUrl ? '' : 'pointer-events-none opacity-40' }}"
                        aria-label="Página siguiente"
                        @if (! $nextUrl) aria-disabled="true" tabindex="-1" @endif
                    >
                        <span>Más recientes</span>
                        <span class="text-base font-bold leading-none text-lucille-accent">→</span>
                    </a>
                </nav>
            @endif
        </div>
    </section>
</x-layouts.site>
