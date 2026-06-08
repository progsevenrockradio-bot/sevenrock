<x-layouts.site title="Seven Rock Radio - {{ $post['title'] }}">
@php
    $ui = $themeAppearance['ui_texts'];
    $shareBaseUrl = request()->url();
    $updatedAt = data_get($post, 'updated_at');
    if (is_string($updatedAt) && $updatedAt !== '') {
        $updatedAt = \Illuminate\Support\Carbon::parse($updatedAt);
    }
    $shareVersion = $updatedAt instanceof \Illuminate\Support\Carbon ? $updatedAt->timestamp : now()->timestamp;
    $shareUrl = $shareBaseUrl . '?v=' . $shareVersion;
    $shareTitle = trim((string) ($post['title'] ?? ''));
    $shareTitleEncoded = urlencode($shareTitle);
    $shareUrlEncoded = urlencode($shareUrl);
    $shareImage = trim((string) ($post['image'] ?? ''));
    $shareImage = $shareImage !== '' ? (str_starts_with($shareImage, 'http') ? $shareImage : asset($shareImage)) : '';
    $twitterShareUrl = 'https://twitter.com/intent/tweet?text=' . $shareTitleEncoded . '&url=' . $shareUrlEncoded;
    $facebookShareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' . $shareUrlEncoded;
    $whatsappShareUrl = 'https://api.whatsapp.com/send?text=' . $shareTitleEncoded . '%20' . $shareUrlEncoded;
    $telegramShareUrl = 'https://t.me/share/url?url=' . $shareUrlEncoded . '&text=' . $shareTitleEncoded;
    $linkedinShareUrl = 'https://www.linkedin.com/sharing/share-offsite/?url=' . $shareUrlEncoded;
    $pinterestShareUrl = 'https://pinterest.com/pin/create/button/?url=' . $shareUrlEncoded
        . ($shareImage !== '' ? '&media=' . urlencode($shareImage) : '')
        . '&description=' . $shareTitleEncoded;
    $postLikeUrl = filled(data_get($post, 'id')) ? route('posts.like', ['post' => data_get($post, 'id')]) : '';
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
                            <nav class="mt-14 flex min-h-[84px] items-center justify-between gap-5 border border-white/10 bg-[#050505f5] px-4 py-6 shadow-[0_28px_70px_rgba(0,0,0,.58)] md:px-8 md:py-7">
                                <div class="min-w-0 flex-1 self-center">
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
                                            <span class="min-w-0 truncate font-display text-sm uppercase tracking-[.18em] md:max-w-[32vw] md:whitespace-normal md:overflow-visible md:text-clip md:text-base">{{ $prevPost->title }}</span>
                                        </a>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1 self-center text-right">
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
                                            <span class="min-w-0 truncate text-right font-display text-sm uppercase tracking-[.18em] md:max-w-[32vw] md:whitespace-normal md:overflow-visible md:text-clip md:text-base">{{ $nextPost->title }}</span>
                                            <span class="text-2xl font-bold leading-none text-lucille-accent md:text-3xl">→</span>
                                        </a>
                                    @endif
                                </div>
                            </nav>
                        @endif

                        <div class="mt-8"></div>

                        <div
                            x-data="{
                                shareOpen: false,
                                likeCount: {{ Js::from((int) data_get($post, 'likes_count', 0)) }},
                                liked: {{ Js::from((bool) data_get($post, 'liked', false)) }},
                                likeBusy: false,
                                likeUrl: {{ Js::from($postLikeUrl) }},
                                shareTitle: {{ Js::from($shareTitle) }},
                                shareUrl: {{ Js::from($shareUrl) }},
                                shareImage: {{ Js::from($shareImage) }},
                                async nativeShare() {
                                    const text = `Estoy leyendo \"${this.shareTitle}\" en Seven Rock Radio.`;
                                    try {
                                        if (navigator.share) {
                                            await navigator.share({ title: this.shareTitle || 'Seven Rock Radio', text, url: this.shareUrl });
                                        } else {
                                            await navigator.clipboard.writeText(`${text} ${this.shareUrl}`.trim());
                                        }
                                    } catch (error) {
                                        try {
                                            await navigator.clipboard.writeText(`${text} ${this.shareUrl}`.trim());
                                        } catch (clipboardError) {
                                            console.warn(clipboardError);
                                        }
                                    }
                                },
                                async toggleLike() {
                                    if (!this.likeUrl) {
                                        return;
                                    }

                                    if (this.likeBusy) {
                                        return;
                                    }

                                    this.likeBusy = true;
                                    try {
                                        const response = await fetch(this.likeUrl, {
                                            method: 'POST',
                                            headers: {
                                                'Accept': 'application/json',
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                'X-Requested-With': 'XMLHttpRequest',
                                            },
                                            body: JSON.stringify({
                                                post_id: {{ Js::from(data_get($post, 'id')) }},
                                            }),
                                        });
                                        const payload = await response.json();

                                        if (!payload?.success) {
                                            throw new Error('Invalid like payload');
                                        }

                                        const data = payload.data || {};
                                        this.liked = Boolean(data.liked);
                                        this.likeCount = Number(data.likes_count ?? this.likeCount);
                                    } catch (error) {
                                        console.warn(error);
                                    } finally {
                                        this.likeBusy = false;
                                    }
                                }
                            }"
                            class="space-y-3"
                        >
                            <div class="content-reactions-wrapper">
                                <button type="button" class="btn-like content-reaction-button" :class="{ 'is-active': liked }" @click="toggleLike()" :aria-pressed="liked" :disabled="likeBusy">
                                    <span>Me gusta</span>
                                    <span class="content-reaction-switch">
                                        <span class="content-reaction-switch-knob"></span>
                                    </span>
                                    <span class="like-count content-reaction-count" x-text="likeCount">0</span>
                                </button>
                                <button type="button" class="content-share-button" :class="{ 'is-active': shareOpen }" @click="shareOpen = !shareOpen" :aria-expanded="shareOpen">
                                    <span>{{ str_replace(':', '', $ui['share'] ?? 'Compartir') }}</span>
                                    <span class="content-share-icon">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 10.742l5.26 2.868m0 0a3 3 0 110 4.924m-5.26-2.868a3 3 0 110-4.924m5.26-2.868A3 3 0 1114 8.684z"></path></svg>
                                    </span>
                                </button>
                                <span class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Redes</span>
                            </div>

                            <div x-show="shareOpen" x-cloak @click.outside="shareOpen = false" class="radio-player-share-panel radio-player-share-panel--post" aria-label="Compartir artículo">
                                <span class="radio-player-share-panel-label">Compartir</span>
                                <a href="{{ $facebookShareUrl }}" target="_blank" rel="noopener noreferrer" class="radio-player-share-link" aria-label="Share on Facebook" title="Facebook">
                                    <span class="radio-player-share-link-code">FB</span>
                                    <span class="radio-player-share-link-text">Facebook</span>
                                </a>
                                <a href="{{ $whatsappShareUrl }}" target="_blank" rel="noopener noreferrer" class="radio-player-share-link" aria-label="Share on WhatsApp" title="WhatsApp">
                                    <span class="radio-player-share-link-code">WA</span>
                                    <span class="radio-player-share-link-text">WhatsApp</span>
                                </a>
                                <a href="{{ $telegramShareUrl }}" target="_blank" rel="noopener noreferrer" class="radio-player-share-link" aria-label="Share on Telegram" title="Telegram">
                                    <span class="radio-player-share-link-code">TG</span>
                                    <span class="radio-player-share-link-text">Telegram</span>
                                </a>
                                <a href="{{ $twitterShareUrl }}" target="_blank" rel="noopener noreferrer" class="radio-player-share-link" aria-label="Share on X" title="X">
                                    <span class="radio-player-share-link-code">X</span>
                                    <span class="radio-player-share-link-text">X</span>
                                </a>
                                <a href="{{ $linkedinShareUrl }}" target="_blank" rel="noopener noreferrer" class="radio-player-share-link" aria-label="Share on LinkedIn" title="LinkedIn">
                                    <span class="radio-player-share-link-code">IN</span>
                                    <span class="radio-player-share-link-text">LinkedIn</span>
                                </a>
                                <a href="{{ $pinterestShareUrl }}" target="_blank" rel="noopener noreferrer" class="radio-player-share-link" aria-label="Share on Pinterest" title="Pinterest">
                                    <span class="radio-player-share-link-code">P</span>
                                    <span class="radio-player-share-link-text">Pinterest</span>
                                </a>
                                <button type="button" class="radio-player-share-link radio-player-share-link--native" @click="nativeShare()" aria-label="Share nativo">
                                    <span class="radio-player-share-link-code">N</span>
                                    <span class="radio-player-share-link-text">Nativo</span>
                                </button>
                            </div>
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
                                    <div class="hidden" style="display:none !important" aria-hidden="true">
                                        <input type="text" name="user_website" tabindex="-1" autocomplete="off">
                                    </div>
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
                        <div class="space-y-4">
                            @foreach ($archives as $archiveYear)
                                @php
                                    $isActiveYear = (string) data_get($archiveYear, 'year') === (string) ($archiveActiveYear ?? '');
                                @endphp
                                <div class="space-y-2">
                                    <a
                                        href="{{ data_get($archiveYear, 'url', '#') }}"
                                        class="flex items-center gap-2 font-display text-sm uppercase tracking-[.16em] transition {{ $isActiveYear ? 'pl-1 text-lucille-accent' : 'text-[#ece4d8] hover:text-lucille-accent' }}"
                                    >
                                        @if ($isActiveYear)
                                            <span class="inline-flex h-2.5 w-2.5 shrink-0 rounded-full bg-lucille-accent shadow-[0_0_0_3px_rgba(195,39,32,.14)]" aria-hidden="true"></span>
                                        @endif
                                        <span>{{ data_get($archiveYear, 'label', data_get($archiveYear, 'year')) }}</span>
                                    </a>

                                    @if (! empty(data_get($archiveYear, 'months', [])))
                                        <ul class="space-y-1 border-l border-white/10 pl-4">
                                            @foreach (data_get($archiveYear, 'months', []) as $archiveMonth)
                                                @php
                                                    $isActiveMonth = $isActiveYear
                                                        && (string) data_get($archiveMonth, 'month') === (string) ($archiveActiveMonth ?? '');
                                                @endphp
                                                <li>
                                                    <a
                                                        href="{{ data_get($archiveMonth, 'url', '#') }}"
                                                        class="block text-sm transition {{ $isActiveMonth ? 'font-semibold text-lucille-accent' : 'text-[#8f8a82] hover:text-lucille-accent' }}"
                                                    >
                                                        {{ data_get($archiveMonth, 'label', data_get($archiveMonth, 'month')) }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endforeach
                        </div>
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
