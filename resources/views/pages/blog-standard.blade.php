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
                            $image = data_get($post, 'featured_image_url', data_get($post, 'featured_image'));
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

                            <div class="lucille-standard-excerpt">
                                <p>{{ $excerpt }}...</p>
                            </div>

                            <div class="lucille-button">
                                <a href="{{ $url }}">{{ $ui['read_more'] }}</a>
                            </div>
                        </article>
                    @endforeach
                </main>

                        @if ($posts instanceof \Illuminate\Pagination\LengthAwarePaginator && $posts->hasPages())
                            <div class="mt-10 flex items-center justify-center gap-4">
                                @if ($posts->previousPageUrl())
                                    <a href="{{ $posts->previousPageUrl() }}" class="flex items-center gap-2 rounded-lg px-5 py-3 text-sm uppercase tracking-[.12em] text-[#7b7b7b] transition hover:bg-white/5 hover:text-lucille-accent">
                                        <span class="text-3xl font-bold text-lucille-accent leading-none">←</span>
                                        <span>Anterior</span>
                                    </a>
                                @endif
                                @if ($posts->nextPageUrl())
                                    <a href="{{ $posts->nextPageUrl() }}" class="flex items-center gap-2 rounded-lg px-5 py-3 text-sm uppercase tracking-[.12em] text-[#7b7b7b] transition hover:bg-white/5 hover:text-lucille-accent">
                                        <span>Siguiente</span>
                                        <span class="text-3xl font-bold text-lucille-accent leading-none">→</span>
                                    </a>
                                @endif
                            </div>
                        @endif


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
        </div>
    </section>
</x-layouts.site>
