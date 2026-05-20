<x-layouts.site title="Seven Rock Radio - {{ $post['title'] }}">
    @php $ui = $themeAppearance['ui_texts']; @endphp
    <x-sections.page-heading :title="$post['title']" overlay="rgba(0,0,0,0)">
        <span>{{ $post['date'] }}</span>
        <span class="mx-1">by</span>
        <a href="#" class="transition hover:text-lucille-accent">{{ $post['author'] }}</a>
        <span class="mx-1">in&nbsp;</span>
        @foreach ($post['categories'] as $category)
            <a href="#" class="transition hover:text-lucille-accent">{{ $category }}</a>@if (! $loop->last)<span class="mx-1">·</span>@endif
        @endforeach
    </x-sections.page-heading>

    <section>
        <div class="lucille-blog-standard-wrap">
            <div class="flex flex-col lg:flex-row">
                <main class="lucille-blog-standard-main">
                    <article>
                        <img src="{{ str_starts_with($post['image'], 'http') ? $post['image'] : asset($post['image']) }}" alt="{{ $post['title'] }}" class="mb-0 w-full">

                        <div class="lucille-single-post-body mt-0 space-y-5">
                            @foreach ($post['content'] as $block)
                                {!! $block !!}
                            @endforeach
                            @if (! empty($post['quote']))
                                <blockquote class="lucille-single-blockquote">
                                    <p>{{ $post['quote'] }}</p>
                                </blockquote>
                            @endif
                        </div>

                        <div class="lucille-share-row">
                            <span>{{ $ui['share'] }}</span>
                            <a href="#" aria-label="Share on Twitter">T</a>
                            <a href="#" aria-label="Share on Facebook">F</a>
                            <a href="#" aria-label="Share on Pinterest">P</a>
                        </div>

                        <div class="mt-8"></div>

                        <div id="comments">
                            <div id="respond" class="comment-respond">
                                <h3 class="lucille-comment-title">{{ $ui['leave_a_reply'] }}</h3>
                                <form class="mt-5 space-y-5">
                                    <div class="lucille-comment-inputs">
                                        <input type="text" placeholder="{{ $ui['your_name'] }}" class="lucille-comment-input">
                                        <input type="email" placeholder="{{ $ui['email_address'] }}" class="lucille-comment-input">
                                        <input type="url" placeholder="{{ $ui['website'] }}" class="lucille-comment-input">
                                    </div>
                                    <textarea placeholder="{{ $ui['write_comment'] }}" rows="8" class="lucille-comment-textarea"></textarea>
                                    <button type="button" class="lucille-button">{{ $ui['post_comment'] }}</button>
                                </form>
                            </div>
                        </div>
                    </article>
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
                        <h3 class="lucille-sidebar-title">{{ $ui['recent_comments'] }}</h3>
                        <ul class="lucille-sidebar-list">
                            @foreach ($comments as $comment)
                                <li><a href="#">{{ $comment }}</a></li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="lucille-sidebar-widget">
                        <h3 class="lucille-sidebar-title">{{ $ui['archives'] }}</h3>
                        <ul class="lucille-sidebar-list">
                            @foreach ($archives as $archive)
                                <li><a href="#">{{ $archive }}</a></li>
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
                        <h3 class="lucille-sidebar-title">{{ $ui['meta'] }}</h3>
                        <ul class="lucille-sidebar-list">
                            <li><a href="#">Log in</a></li>
                            <li><a href="#">Entries feed</a></li>
                            <li><a href="#">Comments feed</a></li>
                            <li><a href="#">WordPress.org</a></li>
                        </ul>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</x-layouts.site>
