<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Event;
use App\Models\GalleryImage;
use App\Models\Product;
use App\Models\Post;
use App\Models\PostTaxonomy;
use App\Models\ThemeSetting;
use App\Models\Video;
use App\Services\ArchiveOrgService;
use App\Support\HeadlineTickerService;
use App\Support\PublicMediaUrl;
use App\Support\ProgramScheduleService;
use App\Support\WordPressContent;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class SiteController extends Controller
{
    public function home(ArchiveOrgService $archiveOrgService): View
    {
        $theme = ThemeSetting::current();
        $latestPodcasts = $this->safeValue(fn () => $archiveOrgService->homePodcastPayload(20), []);

        if (! is_array($latestPodcasts) || empty($latestPodcasts['episodes'] ?? [])) {
            $latestPodcasts = $theme->latestPodcasts();
        }

        return view('pages.home', [
            'events' => $this->safeValue(fn () => Event::query()->upcoming()->orderBy('starts_at')->limit(3)->get(), collect()),
            'album' => $this->safeValue(fn () => Album::query()->latest('released_at')->first(), null),
            'video' => $this->safeValue(fn () => Video::query()->latest()->first(), null),
            'galleryImages' => $this->safeValue(fn () => GalleryImage::query()->ordered()->limit(7)->get(), collect()),
            'posts' => $this->latestPosts(),
            'nextProgram' => $this->safeValue(
                fn () => app(ProgramScheduleService::class)->resolve(5),
                app(ProgramScheduleService::class)->fallback()
            ),
            'headlineTicker' => $this->headlineTicker(),
            'featuredStories' => $this->safeValue(
                fn () => $theme->featuredStories(),
                ThemeSetting::defaults()['featured_stories'] ?? []
            ),
            'latestPodcasts' => $latestPodcasts,
        ]);
    }

    public function events(): View
    {
        return view('pages.events', [
            'events' => $this->safeValue(fn () => Event::query()->orderBy('starts_at')->get(), collect()),
        ]);
    }

    public function eventSingle(string $slug): View
    {
        return view('pages.event-single', [
            'event' => $this->singleEvent($slug),
        ]);
    }

    public function discography(): View
    {
        $albums = \App\Models\TalentAlbum::query()
            ->where('is_published', true)
            ->with('talent')
            ->orderByDesc('release_date')
            ->get();

        return view('pages.discography', [
            'albums' => $albums,
        ]);
    }

    public function albumSingle(string $slug): View
    {
        $album = $this->safeValue(fn () => Album::query()->where('slug', $slug)->first(), null);

        if ($album) {
            return view('pages.album-single', [
                'album' => [
                    'title' => $album->title,
                    'artist' => $album->artist,
                    'cover' => $album->cover_image_url,
                    'date' => $album->released_at?->format('F j, Y') ?? 'N/A',
                    'label' => 'Seven Rock Radio',
                    'producer' => 'Admin',
                    'discs' => '1',
                    'categories' => ['new album', 'official release'],
                    'tracks' => $album->tracks ?? [],
                    'buttons' => $this->albumButtons($album),
                    'content' => array_values(array_filter([
                        $album->summary,
                        'This album is managed from the admin panel and is now part of the public catalog.',
                    ])),
                ],
            ]);
        }

        return view('pages.album-single', [
            'album' => $this->nightrideAlbum(),
        ]);
    }

    public function videos(): View
    {
        $videos = \App\Models\TalentMedia::query()
            ->where('type', 'video')
            ->whereHas('talent', fn ($q) => $q->where('subscription_status', 'active'))
            ->with('talent')
            ->latest()
            ->get();

        return view('pages.videos', [
            'videos' => $videos,
        ]);
    }

    public function videoSingle(string $slug): View
    {
        $video = $this->safeValue(fn () => Video::query()->where('slug', $slug)->first(), null);

        if ($video) {
            return view('pages.video-single', [
                'video' => [
                    'title' => $video->title,
                    'artist' => $video->summary ?: 'Featured video',
                    'image' => $video->image_url,
                    'categories' => ['video'],
                    'embed' => $this->youtubeEmbedUrl((string) $video->youtube_url),
                    'content' => array_values(array_filter([
                        $video->summary,
                        'This video entry is managed from the admin panel and now powers the public catalog.',
                    ])),
                ],
            ]);
        }

        return view('pages.video-single', [
            'video' => $this->singleVideo(),
        ]);
    }

    public function gallery(): View
    {
        $images = \App\Models\TalentMedia::query()
            ->where('type', 'photo')
            ->whereHas('talent', fn ($q) => $q->where('subscription_status', 'active'))
            ->with('talent')
            ->latest()
            ->get();

        return view('pages.gallery', [
            'images' => $images,
        ]);
    }

        public function talentAlbumSingle(string $id, string $slug): View
    {
        $album = \App\Models\TalentAlbum::query()
            ->whereKey((int) $id)
            ->where('is_published', true)
            ->with('talent.media')
            ->firstOrFail();

        if ($album->slug !== $slug) {
            abort(404);
        }

        // Get talent's MP3 media for preview URLs
        $talentMp3s = $album->talent?->media()
            ->where('type', 'mp3')
            ->latest()
            ->get() ?? collect();

        return view('pages.talent-album-single', [
            'album' => $album,
            'talent' => $album->talent,
            'talentMp3s' => $talentMp3s,
        ]);
    }

    public function photoAlbum(): View
    {
        return view('pages.photo-album', [
            'album' => [
                'title' => 'Green Day',
                'artist' => 'Bad Company',
                'image' => 'assets/lucille/man-597179_1920.jpg',
                'categories' => ['Music festivals', 'On tour'],
            ],
            'images' => $this->photoAlbumImages(),
        ]);
    }

    public function blog(): View
    {
        return view('pages.blog', [
            'posts' => $this->safeValue(fn () => Post::query()->published()->orderByDesc('published_at')->get(), collect()),
        ]);
    }

    public function blogStandard(): View
    {
        $posts = $this->safeValue(fn () => Post::query()->published()->orderByDesc('published_at')->get(), collect());
        $categories = $this->mergeTaxonomyValues(
            $posts->flatMap(fn (Post $post) => $post->categoryNames())->all(),
            PostTaxonomy::TYPE_CATEGORY,
            ['Design', 'Discussion', 'Music', 'Singles', 'Typography', 'Uncategorized']
        );
        $tags = $this->mergeTaxonomyValues(
            $posts->flatMap(fn (Post $post) => $post->tagNames())->all(),
            PostTaxonomy::TYPE_TAG,
            ['articles', 'concerts', 'live', 'music', 'news', 'on stage']
        );

        return view('pages.blog-standard', [
                'posts' => $posts,
                'recentPosts' => $posts->take(5),
                'categories' => $categories,
                'tags' => $tags,
            ]);
    }

    public function singlePost(string $year, string $month, string $day, string $slug): View
    {
        $post = $this->safeValue(function () use ($slug, $year, $month, $day) {
            return Post::query()
                ->published()
                ->where('slug', $slug)
                ->whereDate('published_at', sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day))
                ->first();
        }, null);

        if (! $post) {
            $post = $this->safeValue(fn () => Post::query()->published()->orderByDesc('published_at')->first(), null);
        }

        if ($post) {
            $allPosts = $this->safeValue(fn () => Post::query()->published()->latest('published_at')->get(), collect());

            return view('pages.single-post', [
                'post' => [
                    'title' => $post->title,
                    'date' => $post->published_at?->format('F j, Y') ?? trim("{$year}-{$month}-{$day}"),
                    'author' => $post->author,
                    'categories' => $post->categoryNames(),
                    'image' => $post->featured_image_url,
                    'facebook_url' => $post->facebook_url,
                    'instagram_url' => $post->instagram_url,
                    'twitter_url' => $post->twitter_url,
                    'youtube_url' => $post->youtube_url,
                    'external_link_url' => $post->external_link_url,
                    'external_link_label' => $post->external_link_label,
                    'source_name' => $post->source_name,
                    'source_url' => $post->source_url,
                    'meta_title' => $post->meta_title,
                    'meta_description' => $post->meta_description,
                    'content' => WordPressContent::toRenderableBlocks($post->content ?? []),
                    'quote' => $post->quote ?: '',
                ],
                'recentPosts' => $allPosts->take(5),
                'categories' => $this->mergeTaxonomyValues(
                    $allPosts->flatMap(fn (Post $item) => $item->categoryNames())->all(),
                    PostTaxonomy::TYPE_CATEGORY,
                    ['Design', 'Discussion', 'Music', 'Singles', 'Typography', 'Uncategorized']
                ),
                'archives' => ['November 2016', 'October 2016', 'September 2016', 'August 2016'],
                'comments' => ['admin on Landscape Post', 'A WordPress Commenter on Lucille'],
            ]);
        }

        return view('pages.single-post', [
            'post' => $this->inspirationPost(),
            'recentPosts' => [],
            'categories' => $this->mergeTaxonomyValues([], PostTaxonomy::TYPE_CATEGORY, ['Design', 'Discussion', 'Music', 'Singles', 'Typography', 'Uncategorized']),
            'archives' => ['November 2016', 'October 2016', 'September 2016', 'August 2016'],
            'comments' => ['admin on Landscape Post', 'A WordPress Commenter on Lucille'],
        ]);
    }

    public function shop(): View
    {
        return view('pages.shop', [
            'products' => $this->safeValue(fn () => $this->products(), $this->fallbackProducts()),
        ]);
    }

    public function productSingle(string $slug): View
    {
        $product = $this->safeValue(fn () => $this->productBySlug($slug), $this->fallbackProducts()[0]);

        return view('pages.product-single', [
            'product' => $product,
            'relatedProducts' => $this->safeValue(fn () => $this->relatedProducts($product['slug']), $this->fallbackProducts()),
        ]);
    }

    public function contact(): View
    {
        return view('pages.contact');
    }

    private function latestPosts(): array
    {
        try {
            if (! DB::connection()->getSchemaBuilder()->hasTable('posts')) {
                return [];
            }

            return DB::table('posts')
                ->where('status', 'published')
                ->orderByDesc('published_at')
                ->limit(3)
                ->get()
                ->map(function (object $post): array {
                    $publishedAt = ! empty($post->published_at)
                        ? \Illuminate\Support\Carbon::parse((string) $post->published_at)
                        : null;
                    $image = $this->resolvePublicImage((string) ($post->featured_image_path ?? ''));
                    $excerpt = $this->excerptFromContent((string) ($post->content ?? ''), (string) ($post->meta_description ?? ''));

                    return [
                        'title' => (string) $post->title,
                        'date' => $publishedAt?->format('F j, Y') ?? '',
                        'category' => 'Blog',
                        'categories' => ['Blog'],
                        'excerpt' => $excerpt,
                        'image' => $image,
                        'url' => route('posts.single', [
                            'year' => $publishedAt?->format('Y') ?? now()->format('Y'),
                            'month' => $publishedAt?->format('m') ?? now()->format('m'),
                            'day' => $publishedAt?->format('d') ?? now()->format('d'),
                            'slug' => (string) $post->slug,
                        ]),
                    ];
                })
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<int, string> $values
     * @param array<int, string> $fallback
     * @return array<int, string>
     */
    private function mergeTaxonomyValues(array $values, string $type, array $fallback = []): array
    {
        $terms = array_values(array_filter(array_map('trim', $values)));

        if (DB::connection()->getSchemaBuilder()->hasTable('post_taxonomies')) {
            $terms = array_merge(
                $terms,
                PostTaxonomy::query()
                    ->where('type', $type)
                    ->orderBy('name')
                    ->pluck('name')
                    ->all()
            );
        }

        $terms = array_merge($terms, $fallback);

        return array_values(array_unique(array_filter(array_map('trim', $terms))));
    }

    private function headlineTicker(): array
    {
        try {
            return app(HeadlineTickerService::class)->resolve();
        } catch (\Throwable) {
            return [
                'label' => 'Editorial feed',
                'subtitle' => 'Latest headlines',
                'items' => [],
            ];
        }
    }

    private function excerptFromContent(string $content, string $fallback = ''): string
    {
        $fallback = trim($fallback);
        if ($fallback !== '') {
            return $fallback;
        }

        $content = trim($content);
        if ($content === '') {
            return '';
        }

        $blocks = json_decode($content, true);
        if (is_array($blocks)) {
            foreach ($blocks as $block) {
                $html = data_get($block, 'content.html');
                if (! is_string($html)) {
                    continue;
                }

                $text = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
                if ($text !== '') {
                    return \Illuminate\Support\Str::limit($text, 140, '');
                }
            }
        }

        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($content)) ?? '');

        return $text !== '' ? \Illuminate\Support\Str::limit($text, 140, '') : '';
    }



    public function contactSend(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'message' => 'required|string|max:5000',
        ]);

        try {
            \Illuminate\Support\Facades\Mail::to(config('mail.from.address', 'prog.sevenrockradio@gmail.com'))
                ->send(new \App\Mail\ContactMail(
                    senderName: $validated['name'],
                    senderEmail: $validated['email'],
                    senderPhone: $validated['phone'] ?? '',
                    messageBody: $validated['message'],
                    source: 'Contacto',
                ));

            return redirect()->route('contact')->with('success', 'Mensaje enviado correctamente. Nos pondremos en contacto pronto.');
        } catch (\Throwable $e) {
            return redirect()->route('contact')->with('error', 'Error al enviar el mensaje. Intenta de nuevo m\u00e1s tarde.');
        }
    }

    public function homeContactSend(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'message' => 'required|string|max:5000',
        ]);

        try {
            \Illuminate\Support\Facades\Mail::to(config('mail.from.address', 'prog.sevenrockradio@gmail.com'))
                ->send(new \App\Mail\ContactMail(
                    senderName: $validated['name'],
                    senderEmail: $validated['email'],
                    senderPhone: $validated['phone'] ?? '',
                    messageBody: $validated['message'],
                    source: 'Inicio',
                ));

            return redirect()->route('home')->with('success', 'Mensaje enviado correctamente. Nos pondremos en contacto pronto.');
        } catch (\Throwable $e) {
            return redirect()->route('home')->with('error', 'Error al enviar el mensaje. Intenta de nuevo m\u00e1s tarde.');
        }
    }

    /**
     * @template T
     * @param callable():T $callback
     * @param T $fallback
     * @return T
     */
    private function safeValue(callable $callback, mixed $fallback): mixed
    {
        if (! filter_var(env('LOCAL_DB_READS_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
            return $fallback;
        }

        try {
            return $callback();
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * @return array<int, array{label:string,url:string}>
     */
    private function albumButtons(Album $album): array
    {
        return collect($album->buy_links ?? [])
            ->filter(fn ($link) => is_array($link) && ! empty($link['label']) && ! empty($link['url']))
            ->map(fn (array $link): array => ['label' => (string) $link['label'], 'url' => (string) $link['url']])
            ->values()
            ->all();
    }

    private function youtubeEmbedUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        if (str_contains($url, 'youtube.com/embed/')) {
            return $url;
        }

        if (preg_match('~(?:v=|youtu\.be/)([A-Za-z0-9_-]{6,})~', $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1] . '?autoplay=0&enablejsapi=1&wmode=transparent&rel=0&showinfo=0';
        }

        return $url;
    }

    private function nightrideAlbum(): array
    {
        return [
            'title' => 'Nightride',
            'artist' => 'Tinashe',
            'cover' => 'assets/lucille/album1.jpg',
            'date' => 'November 4, 2016',
            'label' => 'RCA',
            'producer' => 'Allen Ritter',
            'discs' => '1',
            'categories' => ['new album', 'official release'],
            'tracks' => [
                ['title' => 'Key To The Highway', 'audio' => '#'],
                ['title' => 'Paint It Black', 'audio' => '#'],
            ],
            'buttons' => [
                ['label' => 'Buy It From Amazon', 'url' => 'https://www.amazon.com'],
                ['label' => 'Buy It From iTunes', 'url' => 'https://itunes.apple.com'],
            ],
            'content' => [
                'Nightride is the second studio album released by American singer Tinashe, released on November 4, 2016. The first single from the album, Company, was released on September 16, 2016.',
                'The album includes the promotional singles Ride of Your Life and Party Favors. The album is the first part of a double album completed with Joyride.',
            ],
        ];
    }

    private function singleEvent(string $slug): array
    {
        $event = $this->safeValue(function () use ($slug) {
            return Event::query()->where('slug', $slug)->first();
        }, null);

        if ($event instanceof Event) {
            $startsAt = $event->starts_at ?? now();

            return [
                'title' => $event->title,
                'categories' => $event->categories ?? [],
                'date' => $startsAt->format('F j, Y'),
                'time' => $startsAt->format('g:i a'),
                'location' => $event->location,
                'venue' => $event->venue,
                'venue_url' => $event->venue_url ?: '#',
                'ticket_url' => $event->ticket_url ?: '#',
                'ticket_label' => $event->ticket_label ?: 'Tickets',
                'facebook_url' => $event->facebook_url ?: '',
                'poster' => PublicMediaUrl::normalizePublicUrl($event->poster) ?: 'assets/lucille/ozzfest_poster.jpg',
                'embed' => $event->embed_url ?: 'https://www.youtube.com/embed/2Ob5y1YqWYg?autoplay=0&enablejsapi=1&wmode=transparent&rel=0&showinfo=0',
                'map' => $event->map_url ?: 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d17236.51886266146!2d-4.402682717825224!3d57.31528684031594!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x488f16c34da23729%3A0xd4d749cbf4fe912f!2sLoch+Ness%2C+Highland%2C+UK!5e0!3m2!1sen!2sro!4v1429172565870',
                'content' => array_values(array_filter(array_map('strval', $event->content ?? []))),
            ];
        }

        $events = [
            'rockness-festival' => [
                'title' => 'Rockness Festival',
                'categories' => ['Guest Appearance', 'Music Festivals'],
                'date' => 'March 21, 2026',
                'time' => '8:00 pm',
                'location' => 'Loch Ness, uk',
                'venue' => 'Rockness Festival',
                'venue_url' => 'http://www.rockness.co.uk/',
                'ticket_url' => 'http://www.ticketmaster.co.uk/',
                'facebook_url' => 'https://www.facebook.com/OfficialRockNess',
                'poster' => 'assets/lucille/ozzfest_poster.jpg',
                'embed' => 'https://www.youtube.com/embed/2Ob5y1YqWYg?autoplay=0&enablejsapi=1&wmode=transparent&rel=0&showinfo=0',
                'map' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d17236.51886266146!2d-4.402682717825224!3d57.31528684031594!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x488f16c34da23729%3A0xd4d749cbf4fe912f!2sLoch+Ness%2C+Highland%2C+UK!5e0!3m2!1sen!2sro!4v1429172565870',
                'content' => [
                    'Cloudhouses, Yurts and Squrts are back again with their ever popular unique festival accommodation options. Cosy, and watertight structures to sleep 2 to 8 people with lovely staff at hand to help make guests feel at home and offering an authentic, bohemian experience.',
                    'Please note that customers wishing to use these facilities must purchase a standard or VIP weekend camping ticket. Booking the boutique camping alone will not permit you entry to the festival.',
                    'In celebration of National Catch the Bus Week, RockNess is inviting you to catch the bus to the festival. There are loads of reasons to get on board: convenience, value for money and helping the environment.',
                ],
            ],
        ];

        return $events[$slug] ?? $events['rockness-festival'];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Album>
     */
    private function discographyFallbackAlbums()
    {
        return collect([
            ['title' => 'Nightride', 'slug' => 'nightride', 'artist' => 'Tinashe', 'cover_image' => 'assets/lucille/album1.jpg', 'released_at' => now()->subYear(3)],
            ['title' => 'Company', 'slug' => 'company', 'artist' => 'Tinashe', 'cover_image' => 'assets/lucille/album3.jpg', 'released_at' => now()->subYear(2)],
            ['title' => 'Stereotypes', 'slug' => 'stereotypes', 'artist' => 'Black Violin', 'cover_image' => 'assets/lucille/Stereotypes.jpg', 'released_at' => now()->subYear(2)->subMonths(3)],
            ['title' => 'Here', 'slug' => 'here', 'artist' => 'Alicia Keys', 'cover_image' => 'assets/lucille/album2.jpg', 'released_at' => now()->subYear(4)],
            ['title' => 'Because Of The Times', 'slug' => 'because-of-the-times', 'artist' => 'Kings of Leon', 'cover_image' => 'assets/lucille/becauseoftimes.jpg', 'released_at' => now()->subYear(5)],
            ['title' => 'Made Up Mind', 'slug' => 'made-up-mind', 'artist' => 'Tedeschi Trucks Band', 'cover_image' => 'assets/lucille/Made-Up-Mind.jpg', 'released_at' => now()->subYear(6)],
        ])->map(function (array $album): Album {
            return Album::make($album);
        });
    }

    private function videosCatalog(): array
    {
        return [
            ['title' => 'Green Day', 'slug' => 'green-day', 'image' => 'assets/lucille/pedalboard-1511069_1920.jpg', 'url' => route('videos.single', ['slug' => 'green-day'])],
            ['title' => 'Chemical Romance', 'slug' => 'chemical-romance', 'image' => 'assets/lucille/music-1284505_1920.jpg', 'url' => route('videos.single', ['slug' => 'chemical-romance'])],
            ['title' => 'Bad Company', 'slug' => 'bad-company', 'image' => 'assets/lucille/guitar-1245856_1920.jpg', 'url' => route('videos.single', ['slug' => 'bad-company'])],
            ['title' => 'Freedom At 21', 'slug' => 'gold-on-the-ceiling', 'image' => 'assets/lucille/freedom_at_21.jpg', 'url' => route('videos.single', ['slug' => 'gold-on-the-ceiling'])],
        ];
    }

    private function singleVideo(): array
    {
        return [
            'title' => 'Freedom At 21',
            'artist' => 'Jack White',
            'image' => 'assets/lucille/freedom-at-21-header.jpg',
            'categories' => ['new album', 'single'],
            'embed' => 'https://www.youtube.com/embed/s92smjLq_38?autoplay=0&enablejsapi=1&wmode=transparent&rel=0&showinfo=0',
            'content' => [
                'Gold on the Ceiling is a song by American rock band The Black Keys. It is the third track from their seventh studio album El Camino and was released as the record second single on February 25, 2012.',
                'Two videos were shot for the song. The first features footage from the band concerts, as well as candid shots of them on tour.',
                'The song moves with a serrated organ growl, hand claps and a sharp garage-pop pulse that fits the Lucille video layout.',
            ],
        ];
    }

    private function photoAlbums(): array
    {
        return [
            ['title' => 'Minor Threat', 'image' => 'assets/lucille/life-864364_1920.jpg', 'categories' => ['Backstage', 'On tour'], 'url' => route('gallery.green-day'), 'span' => 'wide'],
            ['title' => 'Bad Company', 'image' => 'assets/lucille/night-1209938_1920.jpg', 'categories' => ['Backstage', 'On tour'], 'url' => route('gallery.green-day'), 'span' => 'tall'],
            ['title' => 'The Clash', 'image' => 'assets/lucille/guitar-1758005_1920.jpg', 'categories' => ['Music festivals', 'On tour'], 'url' => route('gallery.green-day'), 'span' => 'tall'],
            ['title' => 'Chemical Romance', 'image' => 'assets/lucille/street-1026246_1920.jpg', 'categories' => ['Backstage', 'Music festivals'], 'url' => route('gallery.green-day'), 'span' => 'wide'],
            ['title' => 'Green Day', 'image' => 'assets/lucille/summerfield-336672_1920.jpg', 'categories' => ['Music festivals', 'On tour'], 'url' => route('gallery.green-day'), 'span' => 'wide'],
        ];
    }

    private function photoAlbumImages(): array
    {
        return [
            ['image' => 'assets/lucille/summerfield-336672_1920.jpg', 'caption' => 'Summerfield'],
            ['image' => 'assets/lucille/night-1209938_1920.jpg', 'caption' => 'Night'],
            ['image' => 'assets/lucille/landscape-615429_1920.jpg', 'caption' => 'Landscape'],
            ['image' => 'assets/lucille/sunrise-1239727.jpg', 'caption' => 'Sunrise'],
            ['image' => 'assets/lucille/wolf-1341881_1920.jpg', 'caption' => 'Wolves'],
            ['image' => 'assets/lucille/fashion-1636868_1920.jpg', 'caption' => 'Fashion'],
            ['image' => 'assets/lucille/guitarist-407212_1920.jpg', 'caption' => 'Guitarist'],
            ['image' => 'assets/lucille/street-1026246_1920.jpg', 'caption' => 'Street'],
        ];
    }

    private function blogPosts(): array
    {
        return [
            [
                'title' => 'Pagination Post',
                'date' => 'November 16, 2016',
                'category' => 'Singles',
                'categories' => ['Singles', 'Uncategorized'],
                'excerpt' => 'Jazz is rage. Jazz flows like water. Jazz never seems to begin or end.',
                'image' => 'assets/lucille/man-597179_1920.jpg',
            ],
            [
                'title' => 'Beautiful Typography',
                'date' => 'November 4, 2016',
                'category' => 'Typography',
                'categories' => ['Typography', 'Uncategorized'],
                'excerpt' => 'This is heading The bank manager long ago won the battle for the heart of...',
                'image' => null,
            ],
            [
                'title' => 'Portrait Post',
                'date' => 'October 13, 2016',
                'category' => 'Music',
                'categories' => ['Music', 'Uncategorized'],
                'excerpt' => 'Sometimes I will have sections that I am not quite sure how they fit in the puzzle...',
                'image' => 'assets/lucille/new-york-1209232_1920.jpg',
            ],
            [
                'title' => 'Sound Check',
                'date' => 'September 29, 2016',
                'category' => 'On Tour',
                'categories' => ['Discussion', 'Music', 'Uncategorized'],
                'excerpt' => 'The quiet minutes before doors open carry their own kind of rhythm.',
                'image' => 'assets/lucille/guitar-1245856_1920.jpg',
            ],
            [
                'title' => 'New design trends 2016',
                'date' => 'September 11, 2016',
                'category' => 'Design',
                'categories' => ['Design', 'Uncategorized'],
                'excerpt' => 'Jazz is rage. Jazz flows like water. Jazz never seems to begin or end.',
                'image' => 'assets/lucille/street-1026246_1920.jpg',
            ],
            [
                'title' => 'Lucille',
                'date' => 'September 6, 2016',
                'category' => 'Singles',
                'categories' => ['Discussion', 'Singles', 'Uncategorized'],
                'excerpt' => 'Remember the first time you went to a show and saw your favorite band.',
                'image' => 'assets/lucille/hipster-869222_1920.jpg',
            ],
            [
                'title' => 'Inspiration',
                'date' => 'September 6, 2016',
                'category' => 'Design',
                'categories' => ['Design', 'Uncategorized'],
                'excerpt' => 'Sometimes I will have sections that I am not quite sure how they fit in the puzzle.',
                'image' => 'assets/lucille/back-1822702_1920.jpg',
            ],
            [
                'title' => 'Music Industry',
                'date' => 'August 12, 2016',
                'category' => 'Music',
                'categories' => ['Music', 'Uncategorized'],
                'excerpt' => 'The music industry changes, but the pulse of a live room still matters.',
                'image' => 'assets/lucille/sunrise-1239727.jpg',
            ],
            [
                'title' => 'Fashion and Sound',
                'date' => 'July 20, 2016',
                'category' => 'Style',
                'categories' => ['Design', 'Music'],
                'excerpt' => 'The visual language of a band is part of the same performance.',
                'image' => 'assets/lucille/fashion-1636872_1920.jpg',
            ],
        ];
    }

    private function inspirationPost(): array
    {
        return [
            'title' => 'Inspiration',
            'date' => 'September 6, 2016',
            'author' => 'admin',
            'categories' => ['Design', 'Uncategorized'],
            'image' => 'assets/lucille/back-1822702_1920.jpg',
            'content' => [
                'Sometimes I will have sections that I am not quite sure how they fit in the puzzle of a tune, they will get moved around; what I think was originally a verse ends up becoming the chorus, or what is an intro gets dropped as a hook, things get shifted around a lot.',
                'Inspiration comes from so many sources. Music, other fiction, the non-fiction I read, TV shows, films, news reports, people I know, stories I hear, misheard words or lyrics, dreams.',
                'When you make music, you are forming these invisible vibrations in the air into different shapes and consistencies and speeds in order to create music.',
                'Sometimes I will have sections that I am not quite sure how they fit in the puzzle of a tune, they will get moved around; what I think was originally a verse ends up becoming the chorus, or what is an intro gets dropped as a hook, things get shifted around a lot.',
            ],
            'quote' => 'I think music is so diverse today, and bands are so diverse. In this age of Coachella and European festivals, anything goes, so that allowed us to try different things.',
        ];
    }

    private function products(): array
    {
        $products = $this->safeValue(fn () => Product::query()->published()->ordered()->get(), collect());

        if ($products->isNotEmpty()) {
            return $products->map(fn (Product $product): array => $product->toCatalogArray())->all();
        }

        return array_map(function (array $product): array {
            $product['image'] = $this->resolvePublicImage($product['image'] ?? '');

            return $product;
        }, [
            ['slug' => 'bring-me-the-horizon', 'title' => 'Bring Me The Horizon T-Shirt', 'price' => '£30.00', 'image' => 'assets/lucille/shop-1.jpg', 'category' => 'T-SHIRTS', 'description' => 'Each t-shirt is unique with vintage finish and mini ribbed neckline.'],
            ['slug' => 'guns-n-roses-civil-war', 'title' => 'Guns N Roses Civil War T-Shirt', 'price' => '£28.00', 'image' => 'assets/lucille/shop-4.jpg', 'category' => 'T-SHIRTS', 'description' => 'Soft cotton shirt with a distressed print and a classic fit.'],
            ['slug' => 'guns-n-roses-drum', 'title' => 'Guns N Roses Drum T-Shirt', 'price' => '£30.00', 'image' => 'assets/lucille/shop-5.jpg', 'category' => 'T-SHIRTS', 'description' => 'Drum artwork tee with a washed finish and raw edge detail.'],
            ['slug' => 'led-zeppelin', 'title' => 'Led Zeppelin T-Shirt', 'price' => '£30.00', 'image' => 'assets/lucille/shop-2.jpg', 'category' => 'T-SHIRTS', 'description' => 'A vintage-style Led Zeppelin shirt with a soft worn feel.'],
            ['slug' => 'slayer', 'title' => 'Slayer T-Shirt', 'price' => '£19.00', 'regular_price' => '£30.00', 'image' => 'assets/lucille/shop-9.jpg', 'category' => 'T-SHIRTS', 'description' => 'Limited sale tee with a reduced price and bold front print.', 'sale' => true],
            ['slug' => 'the-beatles', 'title' => 'The Beatles T-Shirt', 'price' => '£30.00', 'image' => 'assets/lucille/beatles_t_shirt.jpeg', 'category' => 'T-SHIRTS', 'description' => 'Classic Beatles tee with a soft hand feel and retro print.'],
            ['slug' => 'the-doors-tee-shirt', 'title' => 'The Doors Tee Shirt', 'price' => '£28.00', 'image' => 'assets/lucille/shop-8.jpg', 'category' => 'T-SHIRTS', 'description' => 'Faded rock tee with a clean neckline and relaxed fit.'],
            ['slug' => 'the-who', 'title' => 'The Who T-Shirt', 'price' => '£30.00', 'image' => 'assets/lucille/shop-6.jpg', 'category' => 'T-SHIRTS', 'description' => 'The Who shirt with a vintage wash and durable cotton fabric.'],
        ]);
    }

    private function productBySlug(string $slug): array
    {
        $product = $this->safeValue(fn () => Product::query()->published()->where('slug', $slug)->first(), null);

        if ($product) {
            return $product->toCatalogArray();
        }

        foreach ($this->fallbackProducts() as $fallback) {
            if ($fallback['slug'] === $slug) {
                return $fallback;
            }
        }

        return $this->fallbackProducts()[0];
    }

    private function relatedProducts(string $currentSlug): array
    {
        $currentProduct = $this->safeValue(fn () => Product::query()->published()->where('slug', $currentSlug)->first(), null);

        if ($currentProduct) {
            $related = $this->safeValue(function () use ($currentProduct) {
                return Product::query()
                    ->published()
                    ->where('id', '!=', $currentProduct->id)
                    ->when($currentProduct->category, fn ($query) => $query->where('category', $currentProduct->category))
                    ->ordered()
                    ->limit(4)
                    ->get();
            }, collect());

            if ($related->isEmpty()) {
                $related = $this->safeValue(function () use ($currentProduct) {
                    return Product::query()
                        ->published()
                        ->where('id', '!=', $currentProduct->id)
                        ->ordered()
                        ->limit(4)
                        ->get();
                }, collect());
            }

            if ($related->isNotEmpty()) {
                return $related->map(fn (Product $product): array => $product->toCatalogArray())->all();
            }
        }

        return array_values(array_filter($this->fallbackProducts(), static fn (array $product): bool => in_array($product['slug'], [
            'slayer',
            'guns-n-roses-civil-war',
            'led-zeppelin',
            'the-who',
        ], true) && $product['slug'] !== $currentSlug));
    }

    private function fallbackProducts(): array
    {
        return [
            ['slug' => 'bring-me-the-horizon', 'title' => 'Bring Me The Horizon T-Shirt', 'price' => '£30.00', 'image' => 'assets/lucille/shop-1.jpg', 'category' => 'T-SHIRTS', 'description' => 'Each t-shirt is unique with vintage finish and mini ribbed neckline.'],
            ['slug' => 'guns-n-roses-civil-war', 'title' => 'Guns N Roses Civil War T-Shirt', 'price' => '£28.00', 'image' => 'assets/lucille/shop-4.jpg', 'category' => 'T-SHIRTS', 'description' => 'Soft cotton shirt with a distressed print and a classic fit.'],
            ['slug' => 'guns-n-roses-drum', 'title' => 'Guns N Roses Drum T-Shirt', 'price' => '£30.00', 'image' => 'assets/lucille/shop-5.jpg', 'category' => 'T-SHIRTS', 'description' => 'Drum artwork tee with a washed finish and raw edge detail.'],
            ['slug' => 'led-zeppelin', 'title' => 'Led Zeppelin T-Shirt', 'price' => '£30.00', 'image' => 'assets/lucille/shop-2.jpg', 'category' => 'T-SHIRTS', 'description' => 'A vintage-style Led Zeppelin shirt with a soft worn feel.'],
            ['slug' => 'slayer', 'title' => 'Slayer T-Shirt', 'price' => '£19.00', 'regular_price' => '£30.00', 'image' => 'assets/lucille/shop-9.jpg', 'category' => 'T-SHIRTS', 'description' => 'Limited sale tee with a reduced price and bold front print.', 'sale' => true],
            ['slug' => 'the-beatles', 'title' => 'The Beatles T-Shirt', 'price' => '£30.00', 'image' => 'assets/lucille/beatles_t_shirt.jpeg', 'category' => 'T-SHIRTS', 'description' => 'Classic Beatles tee with a soft hand feel and retro print.'],
            ['slug' => 'the-doors-tee-shirt', 'title' => 'The Doors Tee Shirt', 'price' => '£28.00', 'image' => 'assets/lucille/shop-8.jpg', 'category' => 'T-SHIRTS', 'description' => 'Faded rock tee with a clean neckline and relaxed fit.'],
            ['slug' => 'the-who', 'title' => 'The Who T-Shirt', 'price' => '£30.00', 'image' => 'assets/lucille/shop-6.jpg', 'category' => 'T-SHIRTS', 'description' => 'The Who shirt with a vintage wash and durable cotton fabric.'],
        ];
    }

    private function resolvePublicImage(string $path): string
    {
        return PublicMediaUrl::normalizePublicUrl($path) ?: asset($path);
    }
}