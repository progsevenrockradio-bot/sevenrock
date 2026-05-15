<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Event;
use App\Models\GalleryImage;
use App\Models\Product;
use App\Models\Post;
use App\Models\ThemeSetting;
use App\Models\Video;
use App\Support\PublicMediaUrl;
use App\Support\ProgramScheduleService;
use Illuminate\Contracts\View\View;

class SiteController extends Controller
{
    public function home(): View
    {
        $theme = ThemeSetting::current();

        return view('pages.home', [
            'events' => $this->safeValue(fn () => Event::query()->upcoming()->orderBy('starts_at')->limit(3)->get(), collect()),
            'album' => $this->safeValue(fn () => Album::query()->latest('released_at')->first(), null),
            'video' => $this->safeValue(fn () => Video::query()->latest()->first(), null),
            'galleryImages' => $this->safeValue(fn () => GalleryImage::query()->ordered()->limit(7)->get(), collect()),
            'posts' => $this->latestPosts(),
            'nextProgram' => $this->safeValue(fn () => app(ProgramScheduleService::class)->resolve(), app(ProgramScheduleService::class)->fallback()),
            'featuredStories' => $theme->featuredStories(),
            'latestPodcasts' => $theme->latestPodcasts(),
        ]);
    }

    public function events(): View
    {
        return view('pages.events', [
            'events' => Event::query()->orderBy('starts_at')->get(),
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
        $albums = Album::query()->orderByDesc('released_at')->orderBy('title')->get();

        if ($albums->count() < 2) {
            $albums = $albums->concat($this->discographyFallbackAlbums()->reject(function (Album $fallbackAlbum) use ($albums) {
                return $albums->contains('slug', $fallbackAlbum->slug);
            }));
        }

        return view('pages.discography', [
            'albums' => $albums->values(),
        ]);
    }

    public function albumSingle(string $slug): View
    {
        $album = Album::query()->where('slug', $slug)->first();

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
        return view('pages.videos', [
            'videos' => Video::query()->latest()->get(),
        ]);
    }

    public function videoSingle(string $slug): View
    {
        $video = Video::query()->where('slug', $slug)->first();

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
        return view('pages.gallery', [
            'images' => GalleryImage::query()->ordered()->get(),
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
            'posts' => Post::query()->published()->orderByDesc('published_at')->get(),
        ]);
    }

    public function blogStandard(): View
    {
        $posts = Post::query()->published()->orderByDesc('published_at')->get();
        $categories = $posts->flatMap(fn (Post $post) => $post->categories ?? [])->filter()->unique()->values()->all();
        $tags = $posts->flatMap(fn (Post $post) => $post->tags ?? [])->filter()->unique()->values()->all();

        return view('pages.blog-standard', [
            'posts' => $posts,
            'recentPosts' => $posts->take(5),
            'categories' => $categories ?: ['Design', 'Discussion', 'Music', 'Singles', 'Typography', 'Uncategorized'],
            'tags' => $tags ?: ['articles', 'concerts', 'live', 'music', 'news', 'on stage'],
        ]);
    }

    public function singlePost(string $year, string $month, string $day, string $slug): View
    {
        $post = Post::query()
            ->published()
            ->where('slug', $slug)
            ->whereDate('published_at', sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day))
            ->first();

        if (! $post) {
            $post = Post::query()->published()->orderByDesc('published_at')->first();
        }

        if ($post) {
            $allPosts = Post::query()->published()->latest('published_at')->get();

            return view('pages.single-post', [
                'post' => [
                    'title' => $post->title,
                    'date' => $post->published_at?->format('F j, Y') ?? trim("{$year}-{$month}-{$day}"),
                    'author' => $post->author,
                    'categories' => $post->categories ?? [],
                    'image' => $post->featured_image_url,
                    'content' => array_pad($post->content ?? [], 4, ''),
                    'quote' => $post->quote ?: '',
                ],
                'recentPosts' => $allPosts->take(5),
                'categories' => $allPosts->flatMap(fn (Post $item) => $item->categories ?? [])->filter()->unique()->values()->all() ?: ['Design', 'Discussion', 'Music', 'Singles', 'Typography', 'Uncategorized'],
                'archives' => ['November 2016', 'October 2016', 'September 2016', 'August 2016'],
                'comments' => ['admin on Landscape Post', 'A WordPress Commenter on Lucille'],
            ]);
        }

        return view('pages.single-post', [
            'post' => $this->inspirationPost(),
            'recentPosts' => [],
            'categories' => ['Design', 'Discussion', 'Music', 'Singles', 'Typography', 'Uncategorized'],
            'archives' => ['November 2016', 'October 2016', 'September 2016', 'August 2016'],
            'comments' => ['admin on Landscape Post', 'A WordPress Commenter on Lucille'],
        ]);
    }

    public function shop(): View
    {
        return view('pages.shop', [
            'products' => $this->products(),
        ]);
    }

    public function productSingle(string $slug): View
    {
        $product = $this->productBySlug($slug);

        return view('pages.product-single', [
            'product' => $product,
            'relatedProducts' => $this->relatedProducts($product['slug']),
        ]);
    }

    public function contact(): View
    {
        return view('pages.contact');
    }

    private function latestPosts(): array
    {
        return $this->safeValue(fn () => Post::query()->published()->latest('published_at')->limit(3)->get()->map(function (Post $post): array {
            return [
                'title' => $post->title,
                'date' => $post->published_at?->format('F j, Y') ?? '',
                'category' => $post->categories[0] ?? 'Blog',
                'categories' => $post->categories ?? [],
                'excerpt' => $post->excerpt ?? '',
                'image' => $post->featured_image_url ?: $post->featured_image,
                'url' => route('posts.single', [
                    'year' => $post->published_at?->format('Y') ?? now()->format('Y'),
                    'month' => $post->published_at?->format('m') ?? now()->format('m'),
                    'day' => $post->published_at?->format('d') ?? now()->format('d'),
                    'slug' => $post->slug,
                ]),
            ];
        })->all(), []);
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
        $products = Product::query()->published()->ordered()->get();

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
        $product = Product::query()->published()->where('slug', $slug)->first();

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
        $currentProduct = Product::query()->published()->where('slug', $currentSlug)->first();

        if ($currentProduct) {
            $related = Product::query()
                ->published()
                ->where('id', '!=', $currentProduct->id)
                ->when($currentProduct->category, fn ($query) => $query->where('category', $currentProduct->category))
                ->ordered()
                ->limit(4)
                ->get();

            if ($related->isEmpty()) {
                $related = Product::query()
                    ->published()
                    ->where('id', '!=', $currentProduct->id)
                    ->ordered()
                    ->limit(4)
                    ->get();
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
