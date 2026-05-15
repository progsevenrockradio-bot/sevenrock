<x-layouts.site title="Seven Rock Radio - Blog Standard">
    <x-sections.page-heading title="Blog Standard" />
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
                                    <img src="{{ str_starts_with($image, 'http') ? $image : asset($image) }}" alt="{{ $title }}">
                                </a>
                            @endif

                            <a href="{{ $url }}">
                                <h2 class="lucille-standard-title">{{ $title }}</h2>
                            </a>

                            <div class="lucille-standard-meta">
                                <span>Posted at&nbsp;{{ $date }}</span>
                                <span>by <a href="#">admin</a></span>
                                <span>in&nbsp;</span>
                                @foreach ($categories ?: [data_get($post, 'category')] as $category)
                                    <a href="#">{{ $category }}</a>@if (! $loop->last) <span>·</span> @endif
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
                            @foreach ($categories as $category)
                                <li><a href="#">{{ $category }}</a></li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="lucille-sidebar-widget">
                        <h3 class="lucille-sidebar-title">{{ $ui['tags'] }}</h3>
                        <div>
                            @foreach ($tags as $tag)
                                <a href="#" class="lucille-tag">{{ $tag }}</a>
                            @endforeach
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</x-layouts.site>
