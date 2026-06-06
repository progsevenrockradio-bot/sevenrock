<x-layouts.site :title="'Seven Rock Radio - '.($pageTitle ?? 'Blog')" :description="$pageDescription ?? 'Blog de Seven Rock Radio. Noticias, entrevistas, lanzamientos y articulos sobre rock y metal.'">
    <x-sections.page-heading :title="$pageTitle ?? 'Blog'" :subtitle="$pageSubtitle ?? null">
        {{ $pageDescription ?? 'Blog de Seven Rock Radio. Noticias, entrevistas, lanzamientos y articulos sobre rock y metal.' }}
    </x-sections.page-heading>
    @php $ui = $themeAppearance['ui_texts']; @endphp

    <section>
        <div class="lucille-blog-standard-wrap">
            <div class="flex flex-col lg:flex-row">
                <main class="lucille-blog-standard-main">
                    @foreach ($posts as $post)
                        @php
                            $title = data_get($post, 'title');
                            $image = data_get($post, 'featured_image_url') ?: data_get($post, 'featured_image_path');
                            $publishedAt = data_get($post, 'published_at');
                            if (is_string($publishedAt)) {
                                $publishedAt = \Carbon\Carbon::parse($publishedAt);
                            }
                            $date = $publishedAt?->format('F j, Y');
                            $categories = data_get($post, 'categories', []);
                            $excerpt = data_get($post, 'excerpt');
                            $slug = data_get($post, 'slug', 'inspiration');
                            $url = route('posts.single', [
                                'year' => $publishedAt?->format('Y') ?? now()->format('Y'),
                                'month' => $publishedAt?->format('m') ?? now()->format('m'),
                                'day' => $publishedAt?->format('d') ?? now()->format('d'),
                                'slug' => $slug,
                            ]);
                        @endphp
                        <article class="lucille-standard-post">
                            @if ($image)
                                <a href="{{ $url }}" class="mb-0 block">
                                    <img
                                        src="{{ str_starts_with($image, 'http') ? $image : asset($image) }}"
                                        alt="{{ $title }}"
                                        width="1200"
                                        height="675"
                                        class="aspect-video"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                </a>
                            @endif

                            <a href="{{ $url }}">
                                <h2 class="lucille-standard-title">{{ $title }}</h2>
                            </a>

                            <div class="lucille-standard-meta">
                                <span>Publicado el {{ $date }}</span>
                                <span>por <a href="#">admin</a></span>
                                <span>in&nbsp;</span>
                                @foreach ($categories ?: [data_get($post, 'category')] as $category)
                                    @if ($category)
                                    <a href="{{ route('blog.category', ['slug' => \Illuminate\Support\Str::slug($category)]) }}">{{ $category }}</a>@if (! $loop->last) <span>·</span> @endif
                                    @endif
                                @endforeach
                            </div>

                            <div class="mt-4 inline-flex items-center gap-2">
                                <span class="content-reaction-count">♥ {{ (int) data_get($post, 'likes_count', 0) }}</span>
                                <span class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Me gusta</span>
                            </div>

                            <div class="lucille-standard-excerpt">
                                <p>{{ $excerpt }}...</p>
                            </div>

                            <div class="lucille-button">
                                <a href="{{ $url }}">{{ $ui['read_more'] }}</a>
                            </div>
                        </article>
                    @endforeach
                </main>
                <aside class="lucille-blog-sidebar lucille-sidebar">
                    <div class="lucille-sidebar-widget">
                        <form class="lucille-sidebar-search">
                            <input type="search" placeholder="{{ $ui['search_placeholder'] }}" aria-label="{{ $ui['search_button_label'] }}">
                            <button type="button" aria-label="{{ $ui['search_button_label'] }}">⌕</button>
                        </form>
                    </div>

                    <div class="lucille-sidebar-widget">
                        <h3 class="lucille-sidebar-title">{{ $ui['recent_posts'] }}</h3>
                        <ul class="lucille-sidebar-list">
                            @foreach ($recentPosts as $recent)
                                @php
                                    $recentPublished = data_get($recent, 'published_at');
                                    if (is_string($recentPublished)) {
                                        $recentPublished = \Carbon\Carbon::parse($recentPublished);
                                    }
                                    $recentUrl = route('posts.single', [
                                        'year' => $recentPublished?->format('Y') ?? now()->format('Y'),
                                        'month' => $recentPublished?->format('m') ?? now()->format('m'),
                                        'day' => $recentPublished?->format('d') ?? now()->format('d'),
                                        'slug' => data_get($recent, 'slug', 'inspiration'),
                                    ]);
                                @endphp
                                <li><a href="{{ $recentUrl }}">{{ data_get($recent, 'title') }}</a></li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="lucille-sidebar-widget">
                        <h3 class="lucille-sidebar-title">{{ $ui['categories'] }}</h3>
                        <ul class="lucille-sidebar-list">
                            @foreach ($blogCategories as $blogCategory)
                                <li><a href="{{ route('blog.category', ['slug' => \Illuminate\Support\Str::slug($blogCategory)]) }}">{{ $blogCategory }}</a></li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="lucille-sidebar-widget">
                        <h3 class="lucille-sidebar-title">{{ $ui['tags'] }}</h3>
                        <div>
                            @foreach ($blogTags as $blogTag)
                                <a href="{{ route('blog.tag', ['slug' => \Illuminate\Support\Str::slug($blogTag)]) }}" class="lucille-tag">{{ $blogTag }}</a>
                            @endforeach
                        </div>
                    </div>
                </aside>
            </div>

            @if ($posts instanceof \Illuminate\Pagination\LengthAwarePaginator && $posts->lastPage() > 1)
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

                <nav class="mt-12 flex flex-wrap items-center justify-center gap-3 border border-white/10 bg-[#070707f2] px-5 py-5 shadow-[0_28px_70px_rgba(0,0,0,.55)] md:px-10 md:py-6" aria-label="Paginación del blog">
                    @php $previousUrl = $posts->previousPageUrl(); @endphp
                    <a
                        href="{{ $previousUrl ?: '#' }}"
                        class="inline-flex items-center gap-3 rounded-none border border-white/10 bg-black/20 px-4 py-3 font-display text-xs uppercase tracking-[.2em] text-[#7b7b7b] transition hover:border-white/20 hover:bg-white/5 hover:text-lucille-accent {{ $previousUrl ? '' : 'pointer-events-none opacity-40' }}"
                        aria-label="Página anterior"
                        @if (! $previousUrl) aria-disabled="true" tabindex="-1" @endif
                    >
                        <span class="text-xl font-bold leading-none text-lucille-accent">←</span>
                        <span>Más antiguos</span>
                    </a>

                    <div class="flex flex-wrap items-center justify-center gap-2">
                        @foreach ($pages as $item)
                            @if ($item['type'] === 'ellipsis')
                                <span class="px-2 text-[#7b7b7b]">…</span>
                            @else
                                <a
                                    href="{{ $item['url'] }}"
                                    class="inline-flex h-11 min-w-11 items-center justify-center border px-3 font-display text-sm uppercase tracking-[.18em] transition {{ $item['current'] ? 'border-lucille-accent bg-lucille-accent text-black' : 'border-white/10 bg-black/20 text-[#d8d1c6] hover:border-white/20 hover:bg-white/5 hover:text-white' }}"
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
                        class="inline-flex items-center gap-3 rounded-none border border-white/10 bg-black/20 px-4 py-3 font-display text-xs uppercase tracking-[.2em] text-[#7b7b7b] transition hover:border-white/20 hover:bg-white/5 hover:text-lucille-accent {{ $nextUrl ? '' : 'pointer-events-none opacity-40' }}"
                        aria-label="Página siguiente"
                        @if (! $nextUrl) aria-disabled="true" tabindex="-1" @endif
                    >
                        <span>Más recientes</span>
                        <span class="text-xl font-bold leading-none text-lucille-accent">→</span>
                    </a>
                </nav>
            @endif
        </div>
    </section>
</x-layouts.site>
