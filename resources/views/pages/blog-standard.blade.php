<x-layouts.site :title="'Seven Rock Radio - '.($pageTitle ?? 'Blog')" :description="$pageDescription ?? 'Blog de Seven Rock Radio. Noticias, entrevistas, lanzamientos y articulos sobre rock y metal.'">
    <x-sections.page-heading :title="$pageTitle ?? 'Blog'" :subtitle="$pageSubtitle ?? null">
        {{ $pageDescription ?? 'Blog de Seven Rock Radio. Noticias, entrevistas, lanzamientos y articulos sobre rock y metal.' }}
    </x-sections.page-heading>
    @php $ui = $themeAppearance['ui_texts']; @endphp

    <section>
        <div class="lucille-blog-standard-wrap pb-12">
            <div class="flex flex-col lg:flex-row gap-8">
                <main class="lucille-blog-standard-main flex-1 w-full lg:w-3/4 pr-0 lg:pr-4">
                    @php
                        $hasHero = (!request()->has('page') || request()->integer('page') === 1) && $posts->count() > 0;
                        $gridOpened = false;
                    @endphp

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
                            $isHero = $loop->first && $hasHero;
                        @endphp

                        @if ($isHero)
                            {{-- Featured Hero Post Card --}}
                            <article class="blog-card-hero group">
                                @if ($image)
                                    <div class="hero-image-wrapper mb-6 md:mb-0 shrink-0 overflow-hidden rounded-xl border border-white/5 shadow-2xl aspect-video bg-[#0c0c0c]">
                                        <a href="{{ $url }}" class="block h-full">
                                            <img
                                                src="{{ str_starts_with($image, 'http') ? $image : asset($image) }}"
                                                alt="{{ $title }}"
                                                width="1200"
                                                height="675"
                                                class="w-full h-full object-cover transition duration-500 ease-out group-hover:scale-102"
                                                loading="lazy"
                                                decoding="async"
                                            >
                                        </a>
                                    </div>
                                @endif

                                <div class="hero-content-wrapper flex flex-col justify-center flex-1">
                                    <div class="flex flex-wrap items-center gap-3 text-[10px] font-mono text-[#888] uppercase tracking-[.08em] mb-3">
                                        <span>🕒 {{ $date }}</span>
                                        <span>👤 Admin</span>
                                        @if (!empty($categories ?: [data_get($post, 'category')]))
                                            <span class="text-white/20">·</span>
                                            <span class="text-lucille-accent font-medium">
                                                @foreach ($categories ?: [data_get($post, 'category')] as $category)
                                                    @if ($category)
                                                        {{ $category }}@if (!$loop->last), @endif
                                                    @endif
                                                @endforeach
                                            </span>
                                        @endif
                                    </div>

                                    <a href="{{ $url }}">
                                        <h2 class="font-display text-2xl md:text-3xl text-[#dcdcdc] font-light uppercase tracking-[.04em] leading-tight group-hover:text-lucille-accent transition-colors duration-300">
                                            {{ $title }}
                                        </h2>
                                    </a>

                                    <p class="mt-4 text-[#888] text-xs md:text-sm leading-relaxed line-clamp-3">
                                        {{ $excerpt }}...
                                    </p>

                                    <div class="mt-6 flex items-center justify-between gap-4 flex-wrap">
                                        {{-- Interactive Like Button --}}
                                        <button type="button"
                                            class="blog-reaction-button"
                                            :class="liked ? 'liked' : ''"
                                            @click.prevent="toggleLike()"
                                            x-data="{
                                                likesCount: {{ (int) data_get($post, 'likes_count', 0) }},
                                                liked: false,
                                                init() {
                                                    const likedIds = JSON.parse(localStorage.getItem('sr_liked_posts') || '[]');
                                                    if (likedIds.includes({{ $post['id'] }})) {
                                                        this.liked = true;
                                                    }
                                                },
                                                async toggleLike() {
                                                    try {
                                                        const response = await fetch('{{ route('posts.like', ['post' => $post['id']]) }}', {
                                                            method: 'POST',
                                                            headers: {
                                                                'Content-Type': 'application/json',
                                                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                                            }
                                                        });
                                                        const res = await response.json();
                                                        if (res.success) {
                                                            this.liked = res.data.liked;
                                                            this.likesCount = res.data.likes_count;
                                                            let likedIds = JSON.parse(localStorage.getItem('sr_liked_posts') || '[]');
                                                            if (this.liked) {
                                                                if (!likedIds.includes({{ $post['id'] }})) likedIds.push({{ $post['id'] }});
                                                            } else {
                                                                likedIds = likedIds.filter(id => id !== {{ $post['id'] }});
                                                            }
                                                            localStorage.setItem('sr_liked_posts', JSON.stringify(likedIds));
                                                        }
                                                    } catch (err) {
                                                        console.error('Error toggling reaction', err);
                                                    }
                                                }
                                            }"
                                        >
                                            <span class="text-xs">❤️</span>
                                            <span x-text="likesCount"></span>
                                        </button>

                                        <a href="{{ $url }}" class="inline-flex items-center gap-1.5 text-[10px] uppercase font-display tracking-[.18em] text-[#dcdcdc] hover:text-lucille-accent transition-colors group/link">
                                            {{ $ui['read_more'] }}
                                            <svg class="transition-transform duration-200 group-hover/link:translate-x-1" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M5 12h14M12 5l7 7-7 7"/>
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </article>
                        @else
                            @if (!$gridOpened)
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8">
                                @php $gridOpened = true; @endphp
                            @endif

                            {{-- Standard Post Card --}}
                            <article class="blog-card-premium group">
                                @if ($image)
                                    <div class="mb-5 overflow-hidden rounded-xl border border-white/5 aspect-video shrink-0 bg-[#0c0c0c]">
                                        <a href="{{ $url }}" class="block h-full">
                                            <img
                                                src="{{ str_starts_with($image, 'http') ? $image : asset($image) }}"
                                                alt="{{ $title }}"
                                                width="640"
                                                height="360"
                                                class="w-full h-full object-cover transition duration-500 ease-out group-hover:scale-102"
                                                loading="lazy"
                                                decoding="async"
                                            >
                                        </a>
                                    </div>
                                @endif

                                <div class="flex-1 flex flex-col">
                                    <div class="flex items-center gap-2 text-[9px] font-mono text-[#757575] uppercase tracking-[.08em] mb-2">
                                        <span>🕒 {{ $date }}</span>
                                        @if (!empty($categories ?: [data_get($post, 'category')]))
                                            <span>·</span>
                                            <span class="text-lucille-accent font-medium truncate">
                                                @foreach ($categories ?: [data_get($post, 'category')] as $category)
                                                    @if ($category)
                                                        {{ $category }}@if (!$loop->last), @endif
                                                    @endif
                                                @endforeach
                                            </span>
                                        @endif
                                    </div>

                                    <a href="{{ $url }}">
                                        <h3 class="font-display text-base text-[#dcdcdc] font-light uppercase tracking-[.04em] leading-snug group-hover:text-lucille-accent transition-colors duration-300 line-clamp-2">
                                            {{ $title }}
                                        </h3>
                                    </a>

                                    <p class="mt-3 text-[#7b7b7b] text-xs leading-relaxed line-clamp-3 flex-1">
                                        {{ $excerpt }}...
                                    </p>

                                    <div class="mt-5 pt-4 border-t border-[#1a1a1a] flex items-center justify-between gap-4">
                                        {{-- Interactive Like Button --}}
                                        <button type="button"
                                            class="blog-reaction-button"
                                            :class="liked ? 'liked' : ''"
                                            @click.prevent="toggleLike()"
                                            x-data="{
                                                likesCount: {{ (int) data_get($post, 'likes_count', 0) }},
                                                liked: false,
                                                init() {
                                                    const likedIds = JSON.parse(localStorage.getItem('sr_liked_posts') || '[]');
                                                    if (likedIds.includes({{ $post['id'] }})) {
                                                        this.liked = true;
                                                    }
                                                },
                                                async toggleLike() {
                                                    try {
                                                        const response = await fetch('{{ route('posts.like', ['post' => $post['id']]) }}', {
                                                            method: 'POST',
                                                            headers: {
                                                                'Content-Type': 'application/json',
                                                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                                            }
                                                        });
                                                        const res = await response.json();
                                                        if (res.success) {
                                                            this.liked = res.data.liked;
                                                            this.likesCount = res.data.likes_count;
                                                            let likedIds = JSON.parse(localStorage.getItem('sr_liked_posts') || '[]');
                                                            if (this.liked) {
                                                                if (!likedIds.includes({{ $post['id'] }})) likedIds.push({{ $post['id'] }});
                                                            } else {
                                                                likedIds = likedIds.filter(id => id !== {{ $post['id'] }});
                                                            }
                                                            localStorage.setItem('sr_liked_posts', JSON.stringify(likedIds));
                                                        }
                                                    } catch (err) {
                                                        console.error('Error toggling reaction', err);
                                                    }
                                                }
                                            }"
                                        >
                                            <span class="text-xs">❤️</span>
                                            <span x-text="likesCount"></span>
                                        </button>

                                        <a href="{{ $url }}" class="inline-flex items-center gap-1.5 text-[9px] uppercase font-display tracking-[.18em] text-[#888] hover:text-white transition-colors group/link">
                                            {{ $ui['read_more'] }}
                                            <svg class="transition-transform duration-200 group-hover/link:translate-x-1" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M5 12h14M12 5l7 7-7 7"/>
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </article>
                        @endif
                    @endforeach

                    @if ($gridOpened)
                        </div>
                    @endif
                </main>
                <aside class="lucille-blog-sidebar lucille-sidebar w-full lg:w-1/4 mt-12 lg:mt-0">
                    <div class="lucille-sidebar-widget">
                        <form method="GET" action="{{ route('search') }}" class="lucille-sidebar-search">
                            <input type="search" name="q" placeholder="{{ $ui['search_placeholder'] }}" aria-label="{{ $ui['search_button_label'] }}">
                            <button type="submit" aria-label="{{ $ui['search_button_label'] }}">⌕</button>
                        </form>
                    </div>

                    <div class="lucille-sidebar-widget">
                        <h3 class="lucille-sidebar-title">{{ $ui['recent_posts'] }}</h3>
                        <ul class="lucille-sidebar-list space-y-3">
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
                                <li>
                                    <a href="{{ $recentUrl }}" class="flex items-start gap-2 text-sm text-[#888] hover:text-white transition-colors duration-300 group">
                                        <span class="mt-1.5 w-1.5 h-1.5 bg-lucille-accent/40 group-hover:bg-lucille-accent rounded-full shrink-0 transition-colors"></span>
                                        <span class="leading-tight">{{ data_get($recent, 'title') }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="lucille-sidebar-widget">
                        <h3 class="lucille-sidebar-title">{{ $ui['categories'] }}</h3>
                        <ul class="lucille-sidebar-list space-y-2.5">
                            @foreach ($blogCategories as $blogCategory)
                                <li>
                                    <a href="{{ route('blog.category', ['slug' => \Illuminate\Support\Str::slug($blogCategory)]) }}" class="flex items-center gap-2 text-sm text-[#888] hover:text-white transition-colors duration-300 group">
                                        <span class="w-1.5 h-1.5 bg-lucille-accent/40 group-hover:bg-lucille-accent rounded-full transition-colors"></span>
                                        {{ $blogCategory }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="lucille-sidebar-widget">
                        <h3 class="lucille-sidebar-title">{{ $ui['tags'] }}</h3>
                        <div class="flex flex-wrap gap-1.5 mt-2">
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

                <nav class="mt-12 flex flex-wrap items-center justify-center gap-2 border border-white/10 bg-[#050505f5] px-3 py-3 shadow-[0_28px_70px_rgba(0,0,0,.55)] md:px-5 md:py-3.5" aria-label="Paginación del blog">
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
                                    class="inline-flex h-9 min-w-9 items-center justify-center border px-2 font-display text-[10px] uppercase tracking-[.18em] transition {{ $item['current'] ? 'border-2 border-lucille-accent bg-lucille-accent text-black shadow-[0_0_0_1px_rgba(195,39,32,.7)] -translate-y-[1px] scale-[1.04]' : 'border-white/10 bg-black/20 text-[#d8d1c6] hover:border-white/20 hover:bg-white/5 hover:text-white' }}"
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
