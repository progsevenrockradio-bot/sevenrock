<x-layouts.site title="Seven Rock Radio - {{ $post['title'] }}">
    @php
        $ui = $themeAppearance['ui_texts'];
        $shareUrl = request()->fullUrl();
        $shareTitle = trim((string) ($post['title'] ?? ''));
        $shareImage = trim((string) ($post['image'] ?? ''));
        $shareImage = $shareImage !== '' ? (str_starts_with($shareImage, 'http') ? $shareImage : asset($shareImage)) : '';
        $twitterShareUrl = 'https://twitter.com/intent/tweet?text=' . rawurlencode($shareTitle) . '&url=' . rawurlencode($shareUrl);
        $facebookShareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($shareUrl);
        $pinterestShareUrl = 'https://pinterest.com/pin/create/button/?url=' . rawurlencode($shareUrl)
            . ($shareImage !== '' ? '&media=' . rawurlencode($shareImage) : '')
            . '&description=' . rawurlencode($shareTitle);
    @endphp
    <x-sections.page-heading :title="$post['title']" overlay="rgba(0,0,0,0)">
        <span>{{ $post['date'] }}</span>
        <span class="mx-1">by</span>
        <a href="#" class="transition hover:text-lucille-accent">{{ $post['author'] }}</a>
        <span class="mx-1">in&nbsp;</span>
        @foreach ($post['categories'] as $category)
            <a href="{{ route('blog.category', ['slug' => \Illuminate\Support\Str::slug($category)]) }}" class="transition hover:text-lucille-accent">{{ $category }}</a>@if (! $loop->last)<span class="mx-1">·</span>@endif
        @endforeach
    </x-sections.page-heading>

    <section>
        <div class="lucille-blog-standard-wrap">
            <div class="flex flex-col lg:flex-row">
                <main class="lucille-blog-standard-main">
                    <article>
                        <img src="{{ str_starts_with($post['image'], 'http') ? $post['image'] : asset($post['image']) }}" alt="{{ $post['title'] }}" class="mb-0 w-full" loading="lazy">

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

                        @php
                            $socialLinks = array_filter([
                                ['label' => 'Facebook', 'url' => $post['facebook_url'] ?? ''],
                                ['label' => 'Instagram', 'url' => $post['instagram_url'] ?? ''],
                                ['label' => 'Twitter', 'url' => $post['twitter_url'] ?? ''],
                                ['label' => 'YouTube', 'url' => $post['youtube_url'] ?? ''],
                            ], static fn (array $item): bool => trim((string) ($item['url'] ?? '')) !== '');
                            $externalLinkUrl = trim((string) ($post['external_link_url'] ?? ''));
                            $externalLinkLabel = trim((string) ($post['external_link_label'] ?? '')) ?: 'Abrir enlace externo';
                            $sourceName = trim((string) ($post['source_name'] ?? ''));
                            $sourceUrl = trim((string) ($post['source_url'] ?? ''));
                        @endphp

                        @if ($socialLinks !== [])
                            <div class="mt-8">
                                <h3 class="lucille-sidebar-title mb-4">Sigue al artista</h3>
                                <div class="flex flex-wrap gap-3">
                                    @foreach ($socialLinks as $socialLink)
                                        <a href="{{ $socialLink['url'] }}" target="_blank" rel="noreferrer" class="lucille-button">{{ $socialLink['label'] }}</a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($externalLinkUrl !== '')
                            <div class="mt-6">
                                <a href="{{ $externalLinkUrl }}" target="_blank" rel="noreferrer" class="lucille-button-solid">{{ $externalLinkLabel }}</a>
                            </div>
                        @endif

                        @if ($sourceName !== '')
                            <div class="mt-6 text-sm text-[#dcdcdc]">
                                <span class="uppercase tracking-[.18em] text-[#7b7b7b]">Fuente:</span>
                                @if ($sourceUrl !== '')
                                    <a href="{{ $sourceUrl }}" target="_blank" rel="noreferrer" class="transition hover:text-lucille-accent">{{ $sourceName }}</a>
                                @else
                                    <span>{{ $sourceName }}</span>
                                @endif
                            </div>
                        @endif


                        @if ($prevPost || $nextPost)
                            <nav class="mt-14 flex min-h-[96px] items-stretch justify-between gap-6 border border-white/10 bg-[#070707f2] px-5 py-7 shadow-[0_28px_70px_rgba(0,0,0,.55)] md:px-10 md:py-8">
                                <div class="min-w-0 flex-1">
                                    @if ($prevPost)
                                        @php
                                            $prevUrl = route('posts.single', [
                                                'year' => $prevPost->published_at?->format('Y') ?? now()->format('Y'),
                                                'month' => $prevPost->published_at?->format('m') ?? now()->format('m'),
                                                'day' => $prevPost->published_at?->format('d') ?? now()->format('d'),
                                                'slug' => $prevPost->slug,
                                            ]);
                                        @endphp
                                        <a href="{{ $prevUrl }}" class="group inline-flex max-w-full items-center gap-3 text-[#7b7b7b] transition hover:text-lucille-accent">
                                            <span class="text-2xl font-bold leading-none text-lucille-accent md:text-3xl">←</span>
                                            <span class="min-w-0 truncate font-display text-sm uppercase tracking-[.18em] md:text-base">{{ $prevPost->title }}</span>
                                        </a>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1 text-right">
                                    @if ($nextPost)
                                        @php
                                            $nextUrl = route('posts.single', [
                                                'year' => $nextPost->published_at?->format('Y') ?? now()->format('Y'),
                                                'month' => $nextPost->published_at?->format('m') ?? now()->format('m'),
                                                'day' => $nextPost->published_at?->format('d') ?? now()->format('d'),
                                                'slug' => $nextPost->slug,
                                            ]);
                                        @endphp
                                        <a href="{{ $nextUrl }}" class="group inline-flex max-w-full items-center justify-end gap-3 text-[#7b7b7b] transition hover:text-lucille-accent">
                                            <span class="min-w-0 truncate text-right font-display text-sm uppercase tracking-[.18em] md:text-base">{{ $nextPost->title }}</span>
                                            <span class="text-2xl font-bold leading-none text-lucille-accent md:text-3xl">→</span>
                                        </a>
                                    @endif
                                </div>
                            </nav>
                        @endif

                        <div class="mt-8"></div>

                        <div class="lucille-share-row">
                            <span>{{ $ui['share'] }}</span>
                            <a href="{{ $twitterShareUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Share on Twitter">T</a>
                            <a href="{{ $facebookShareUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Share on Facebook">F</a>
                            <a href="{{ $pinterestShareUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Share on Pinterest">P</a>
                        </div>

                        <div class="mt-8"></div>

                        <div id="comments">
                        @if (session("status"))
                            <div class="mb-4 rounded-lg bg-green-900/30 px-4 py-3 text-sm text-green-300">{{ session("status") }}</div>
                        @endif
                            <div id="respond" class="comment-respond">
                                <h3 class="lucille-comment-title">{{ $ui['leave_a_reply'] }}</h3>
                                <form method="POST" action="{{ route('posts.comments.store', $post['id']) }}" class="mt-5 space-y-5">
                                    @csrf
                                    <div class="lucille-comment-inputs">
                                        <input type="text" name="author_name" placeholder="{{ $ui['your_name'] }}" class="lucille-comment-input">
                                        <input type="email" name="author_email" placeholder="{{ $ui['email_address'] }}" class="lucille-comment-input">
                                        <input type="url" name="author_website" placeholder="{{ $ui['website'] }}" class="lucille-comment-input">
                                    </div>
                                    <textarea name="content" placeholder="{{ $ui['write_comment'] }}" rows="8" class="lucille-comment-textarea" required minlength="5"></textarea>
                                    <button type="submit" class="lucille-button">{{ $ui['post_comment'] }}</button>
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
                                    if (is_string($recentPublished) && $recentPublished !== '') {
                                        $recentPublished = \Illuminate\Support\Carbon::parse($recentPublished);
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
                            @foreach ($blogCategories as $blogCategory)
                                <li><a href="{{ route('blog.category', ['slug' => \Illuminate\Support\Str::slug($blogCategory)]) }}">{{ $blogCategory }}</a></li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="lucille-sidebar-widget">
                        <h3 class="lucille-sidebar-title">{{ $ui['tags'] }}</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($blogTags as $blogTag)
                                <a href="{{ route('blog.tag', ['slug' => \Illuminate\Support\Str::slug($blogTag)]) }}" class="lucille-tag">{{ $blogTag }}</a>
                            @endforeach
                        </div>
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
