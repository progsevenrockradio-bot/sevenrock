<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactFormRequest;
use App\Models\Album;
use App\Models\Event;
use App\Models\GalleryImage;
use App\Models\MasterProgram;
use App\Models\Product;
use App\Models\Post;
use App\Models\PostReaction;
use App\Models\PostTaxonomy;
use App\Models\TalentAlbum;
use App\Models\ThemeSetting;
use App\Models\Video;
use App\Services\ArchiveOrgService;
use App\Support\HeadlineTickerService;
use App\Support\PublicMediaUrl;
use App\Support\ProgramScheduleService;
use App\Support\WordPressContent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Mail\ContactMail;

class SiteController extends Controller
{
    public function home(ArchiveOrgService $archiveOrgService): View
    {
        $theme = ThemeSetting::current();
        $latestPodcasts = $this->safeValue(fn () => $archiveOrgService->homePodcastPayload(20), []);

        if (! is_array($latestPodcasts) || empty($latestPodcasts['episodes'] ?? [])) {
            $latestPodcasts = $theme->latestPodcasts();
        }

        $events = $this->cachedEvents('home-upcoming', fn () => Event::query()->upcoming()->orderBy('starts_at')->limit(3)->get(), 10);
        $galleryImages = $this->cachedGalleryImages(7, 15);
        $latestAlbum = $this->cachedLatestAlbum(15);

        return view('pages.home', [
            'events' => $events,
            'album' => $latestAlbum,
            'video' => $this->safeValue(fn () => Video::query()->latest()->first(), null),
            'galleryImages' => $galleryImages,
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
        return $this->eventsCatalog(
            title: 'Upcoming Shows',
            subtitle: 'Tour Dates 2026',
            description: 'Proximos eventos, conciertos y festivales de rock. Mantente al dia con la agenda musical de Seven Rock Radio.',
            events: $this->cachedEvents('upcoming', fn () => Event::query()->upcoming()->orderBy('starts_at')->get(), 15)
        );
    }

    public function upcomingEvents(): View
    {
        return $this->eventsCatalog(
            title: 'Próximos eventos',
            subtitle: 'Eventos futuros',
            description: 'Eventos futuros, conciertos y festivales de rock. Mantente al dia con la agenda musical de Seven Rock Radio.',
            events: $this->cachedEvents('upcoming', fn () => Event::query()->upcoming()->orderBy('starts_at')->get(), 15)
        );
    }

    public function pastEvents(): View
    {
        return $this->eventsCatalog(
            title: 'Eventos pasados',
            subtitle: 'Eventos ya ocurridos',
            description: 'Eventos ya ocurridos, conciertos y festivales de rock archivados por fecha.',
            events: $this->cachedEvents('past', fn () => Event::query()->where('starts_at', '<', now()->startOfDay())->orderByDesc('starts_at')->get(), 15)
        );
    }

    public function allEvents(): View
    {
        return $this->eventsCatalog(
            title: 'Todos los eventos',
            subtitle: 'Agenda completa',
            description: 'Todos los eventos, conciertos y festivales de rock listados por fecha.',
            events: $this->cachedEvents('all', fn () => Event::query()->orderByDesc('starts_at')->get(), 20)
        );
    }

    public function eventSingle(string $slug): View
    {
        return view('pages.event-single', [
            'event' => $this->singleEvent($slug),
        ]);
    }

    public function discography(): View
    {
        $allAlbums = $this->cachedDiscographyAlbums();

        return view('pages.discography', [
            'albums' => $allAlbums,
        ]);
    }

    public function albumSingle(string $slug): View
    {
        $album = $this->cachedAlbumBySlug($slug);

        if ($album) {
            return view('pages.album-single', [
                'album' => $this->adminAlbumViewData($album),
            ]);
        }

        $talentAlbum = $this->cachedTalentAlbumBySlug($slug);

        if ($talentAlbum) {
            return view('pages.album-single', [
                'album' => $this->talentAlbumViewData($talentAlbum),
            ]);
        }

        abort(404);
    }

    public function videos(): View
    {
        $videos = collect();

        if (Schema::hasTable('talent_media') && Schema::hasTable('talents')) {
            $videos = \App\Models\TalentMedia::query()
                ->where('type', 'video')
                ->whereHas('talent', fn ($q) => $q->where('subscription_status', 'active'))
                ->with('talent')
                ->latest()
                ->get();
        }

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
        $images = $this->cachedGalleryImages(20, 15);

        return view('pages.gallery', [
            'images' => $images,
        ]);
    }

    public function programs(ArchiveOrgService $archiveOrgService): View
    {
        $cacheVersion = $this->cacheVersion('programs');
        $cached = Cache::remember(
            "site.programs.catalog.v{$cacheVersion}",
            now()->addMinutes(10),
            function () use ($archiveOrgService): array {
                $programsByDay = [];
                $latestEpisodes = [];
                $groupedEpisodes = [];

                try {
                    $masterPrograms = MasterProgram::query()
                        ->where('activo', true)
                        ->orderBy('nombre')
                        ->get();

                    $dayOrder = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO'];
                    $dayLabels = [
                        'LUNES' => 'Lunes',
                        'MARTES' => 'Martes',
                        'MIERCOLES' => 'Miércoles',
                        'JUEVES' => 'Jueves',
                        'VIERNES' => 'Viernes',
                        'SABADO' => 'Sábado',
                        'DOMINGO' => 'Domingo',
                    ];

                    $grouped = [];
                    foreach ($masterPrograms as $program) {
                        $day = strtoupper(trim((string) $program->dia_transmision));
                        if (! isset($grouped[$day])) {
                            $grouped[$day] = [];
                        }
                        $grouped[$day][] = [
                            'id' => $program->id,
                            'title' => $program->nombre,
                            'name' => $program->nombre,
                            'cover' => $program->cover_url,
                            'host' => $program->host,
                            'conductor' => $program->conductor,
                            'schedule' => $program->schedule,
                            'description' => $program->description,
                            'genre' => $program->genero,
                            'hora' => $program->hora_transmision,
                            'archive_identifier' => $program->archive_identifier,
                            'slug' => $program->publicSlug(),
                        ];
                    }

                    foreach ($dayOrder as $day) {
                        if (! empty($grouped[$day])) {
                            usort($grouped[$day], fn ($a, $b) => ($a['hora'] ?? '99:99') <=> ($b['hora'] ?? '99:99'));
                            $programsByDay[] = [
                                'day' => $day,
                                'label' => $dayLabels[$day] ?? $day,
                                'programs' => $grouped[$day],
                            ];
                        }
                    }

                    $payload = $archiveOrgService->homePodcastPayload(20);
                    $latestEpisodes = $payload['episodes'] ?? [];
                    if (! empty($payload['featured']['src'] ?? '')) {
                        array_unshift($latestEpisodes, $payload['featured']);
                    }

                    foreach ($latestEpisodes as $ep) {
                        $progName = trim((string) ($ep['program'] ?? $ep['title'] ?? 'Sin programa'));
                        if (! isset($groupedEpisodes[$progName])) {
                            $groupedEpisodes[$progName] = [];
                        }

                        $src = $ep['src'] ?? '';
                        $exists = false;
                        foreach ($groupedEpisodes[$progName] as $existing) {
                            if (($existing['src'] ?? '') === $src) {
                                $exists = true;
                                break;
                            }
                        }

                        if (! $exists) {
                            $groupedEpisodes[$progName][] = $ep;
                        }
                    }

                    foreach ($groupedEpisodes as $name => &$eps) {
                        usort($eps, function ($a, $b) {
                            $da = $a['date'] ?? '';
                            $db = $b['date'] ?? '';
                            $tsA = $da ? (\DateTimeImmutable::createFromFormat('d/m/Y', $da)?->getTimestamp() ?? 0) : 0;
                            $tsB = $db ? (\DateTimeImmutable::createFromFormat('d/m/Y', $db)?->getTimestamp() ?? 0) : 0;
                            return $tsB - $tsA;
                        });
                    }
                    unset($eps);

                    uasort($groupedEpisodes, function ($a, $b) {
                        $dateA = $a[0]['date'] ?? '';
                        $dateB = $b[0]['date'] ?? '';
                        $tsA = $dateA ? (\DateTimeImmutable::createFromFormat('d/m/Y', $dateA)?->getTimestamp() ?? 0) : 0;
                        $tsB = $dateB ? (\DateTimeImmutable::createFromFormat('d/m/Y', $dateB)?->getTimestamp() ?? 0) : 0;
                        return $tsB - $tsA;
                    });
                } catch (Throwable) {
                }

                return [
                    'programsByDay' => $programsByDay,
                    'latestEpisodes' => $latestEpisodes,
                    'groupedEpisodes' => $groupedEpisodes,
                ];
            }
        );

        return view('pages.programs', [
            'programsByDay' => $cached['programsByDay'],
            'latestEpisodes' => $cached['latestEpisodes'],
            'groupedEpisodes' => $cached['groupedEpisodes'],
        ]);
    }

    public function programDetail(string $identifier, ArchiveOrgService $archiveOrgService): View
    {
        $program = null;
        $episodes = [];
        try {
            $masterProgram = MasterProgram::query()
                ->where('archive_identifier', $identifier)
                ->first();

            // Intentar obtener episodios desde archive.org via HTTP directo
            try {
                $json = @file_get_contents('https://archive.org/details/' . rawurlencode($identifier) . '?output=json', false, stream_context_create([
                    'http' => ['timeout' => 8, 'method' => 'GET'],
                    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
                ]));
                if ($json !== false) {
                    $data = json_decode($json, true);
                    if (is_array($data) && !empty($data['files'])) {
                        $files = $data['files'];
                        $metadata = $data['metadata'] ?? [];
                        $parsedEpisodes = [];
                        foreach ($files as $key => $file) {
                            $name = $file['name'] ?? ltrim($key, '/');
                            $format = strtolower($file['format'] ?? '');
                            if (str_ends_with($name, '.mp3') || str_contains($format, 'mp3')) {
                                $parsedEpisodes[] = [
                                    'id' => $name,
                                    'title' => trim($file['title'] ?? $name),
                                    'src' => 'https://archive.org/download/' . rawurlencode($identifier) . '/' . implode('/', array_map('rawurlencode', explode('/', ltrim($name, '/')))),
                                    'published_at' => $file['mtime'] ?? 0,
                                    'views' => $file['downloads'] ?? null,
                                    'duration' => trim($file['length'] ?? ''),
                                    'size' => $file['size'] ?? null,
                                ];
                            }
                        }
                        usort($parsedEpisodes, fn($a, $b) => ($b['published_at'] ?? 0) <=> ($a['published_at'] ?? 0));
                        $episodes = $parsedEpisodes;
                        
                        $desc = $metadata['description'] ?? '';
                        if (is_array($desc)) $desc = $desc[0] ?? '';
                        
                        $program = [
                            'id' => $identifier,
                            'title' => $masterProgram?->nombre ?? ($metadata['title'][0] ?? $metadata['title'] ?? 'Programa'),
                            'name' => $masterProgram?->nombre ?? ($metadata['title'][0] ?? $metadata['title'] ?? 'Programa'),
                            'cover' => $masterProgram?->cover_url ?: asset('assets/lucille/logo.png'),
                            'host' => $masterProgram?->host ?? ($metadata['creator'][0] ?? $metadata['creator'] ?? ''),
                            'conductor' => $masterProgram?->conductor ?? '',
                            'schedule' => $masterProgram?->schedule ?? '',
                            'description' => $masterProgram?->description ?: trim(strip_tags(is_string($desc) ? $desc : '')),
                        ];
                    }
                }
            } catch (Throwable) {
                // Fallback silencioso
            }

            // Si fallo la API, usar datos locales
            if (!$program) {
                $program = [
                    'id' => $identifier,
                    'title' => $masterProgram?->nombre ?? 'Programa',
                    'name' => $masterProgram?->nombre ?? 'Programa',
                    'cover' => $masterProgram?->cover_url ?: asset('assets/lucille/logo.png'),
                    'host' => $masterProgram?->host ?? '',
                    'conductor' => $masterProgram?->conductor ?? '',
                    'schedule' => $masterProgram?->schedule ?? '',
                    'description' => $masterProgram?->description ?? '',
                ];
            }
        } catch (Throwable) {
        }

        if (!$program) {
            abort(404);
        }

        return view('pages.program-detail', [
            'program' => $program,
            'episodes' => $episodes,
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

        return view('pages.album-single', [
            'album' => $this->talentAlbumViewData($album),
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
        return $this->blogListing(
            pageTitle: 'Blog',
            pageSubtitle: null,
            pageDescription: 'Blog de Seven Rock Radio. Noticias, entrevistas, lanzamientos y articulos sobre rock y metal.',
            posts: $this->cachedPublishedPostsPaginator(20)
        );
    }

    public function blogStandard(): View
    {
        return $this->blogListing(
            pageTitle: 'Blog',
            pageSubtitle: null,
            pageDescription: 'Blog de Seven Rock Radio. Noticias, entrevistas, lanzamientos y articulos sobre rock y metal.',
            posts: $this->cachedPublishedPostsPaginator(20)
        );
    }

    public function blogCategory(string $slug): View
    {
        return $this->blogArchive(
            type: PostTaxonomy::TYPE_CATEGORY,
            slug: $slug,
            pageTitle: 'Categoría',
            pageSubtitle: 'Artículos filtrados por categoría'
        );
    }

    public function blogTag(string $slug): View
    {
        return $this->blogArchive(
            type: PostTaxonomy::TYPE_TAG,
            slug: $slug,
            pageTitle: 'Etiqueta',
            pageSubtitle: 'Artículos filtrados por etiqueta'
        );
    }

    public function blogDateArchive(string $year, ?string $month = null): View
    {
        $page = max(1, (int) request()->integer('page', 1));
        $version = $this->cacheVersion('posts');
        $monthValue = $month !== null ? str_pad((string) ((int) $month), 2, '0', STR_PAD_LEFT) : null;
        $monthLabel = $monthValue ? \DateTime::createFromFormat('!m', $monthValue)?->format('F') : null;
        $archiveLabel = $monthLabel ? "{$monthLabel} {$year}" : (string) $year;
        $cacheKey = "site.blog.archives.date.{$year}." . ($monthValue ?? 'all') . ".v{$version}.page{$page}.per20";

        $cached = Cache::remember(
            $cacheKey,
            now()->addMinutes(10),
            function () use ($year, $monthValue, $page): array {
                $query = Post::query()
                    ->published()
                    ->whereYear('published_at', (int) $year);

                if ($monthValue !== null) {
                    $query->whereMonth('published_at', (int) $monthValue);
                }

                $query->orderByDesc('published_at');

                if (Schema::hasTable('post_reactions')) {
                    $query->withCount([
                        'reactions as likes_count' => fn ($reactionQuery) => $reactionQuery->where('reaction_type', 'like'),
                    ]);
                }

                $paginator = $query->paginate(20, ['*'], 'page', $page);

                return [
                    'items' => $paginator->getCollection()->map(fn ($post) => $post->toArray())->all(),
                    'total' => $paginator->total(),
                    'lastPage' => $paginator->lastPage(),
                ];
            }
        );

        $posts = new \Illuminate\Pagination\LengthAwarePaginator(
            $cached['items'] ?? [],
            (int) ($cached['total'] ?? 0),
            20,
            $page,
            [
                'path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
                'lastPage' => (int) ($cached['lastPage'] ?? 1),
            ]
        );

        return $this->blogListing(
            pageTitle: 'Archivos',
            pageSubtitle: $archiveLabel,
            pageDescription: $monthLabel
                ? "Entradas publicadas en {$archiveLabel}."
                : "Entradas publicadas durante {$year}.",
            posts: $posts
        );
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
            $recentPosts = $this->safeValue(fn () => Post::query()->published()->latest('published_at')->limit(5)->get()->toArray(), collect());
            return view('pages.single-post', [
                'blogCategories' => $this->blogTaxonomyTerms(PostTaxonomy::TYPE_CATEGORY, ['Design', 'Discussion', 'Music', 'Singles', 'Typography', 'Uncategorized']),
                'blogTags' => $this->blogTaxonomyTerms(PostTaxonomy::TYPE_TAG, ['articles', 'concerts', 'live', 'music', 'news', 'on stage']),
                'post' => [
                    'id' => $post->id,
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
                    'content' => WordPressContent::toRenderableBlocks($post->content),
                    'quote' => $post->quote ?: '',
                    'likes_count' => $this->postLikesCount($post->id),
                    'liked' => $this->hasPostLiked($post->id),
                ],
                'prevPost' => $this->safeValue(fn () => Post::query()->published()
                    ->where(function ($q) use ($post) {
                        $q->where('published_at', '<', $post->published_at)
                          ->orWhere(function ($q) use ($post) {
                              $q->where('published_at', '=', $post->published_at)
                                ->where('id', '<', $post->id);
                          });
                    })
                    ->orderByDesc('published_at')
                    ->orderByDesc('id')
                    ->first(['title', 'slug', 'published_at']), null),
                'nextPost' => $this->safeValue(fn () => Post::query()->published()
                    ->where(function ($q) use ($post) {
                        $q->where('published_at', '>', $post->published_at)
                          ->orWhere(function ($q) use ($post) {
                              $q->where('published_at', '=', $post->published_at)
                                ->where('id', '>', $post->id);
                          });
                    })
                    ->orderBy('published_at')
                    ->orderBy('id')
                    ->first(['title', 'slug', 'published_at']), null),
                'recentPosts' => $recentPosts,
                'blogCategories' => $this->blogTaxonomyTerms(PostTaxonomy::TYPE_CATEGORY, ['Design', 'Discussion', 'Music', 'Singles', 'Typography', 'Uncategorized']),
                'blogTags' => $this->blogTaxonomyTerms(PostTaxonomy::TYPE_TAG, ['articles', 'concerts', 'live', 'music', 'news', 'on stage']),
                'archives' => $this->cachedPostArchives(),
                'comments' => ['admin on Landscape Post', 'A WordPress Commenter on Lucille'],
            ]);
        }

        return view('pages.single-post', [
            'post' => $this->inspirationPost(),
            'recentPosts' => [],
            'prevPost' => null,
            'nextPost' => null,
            'blogCategories' => $this->blogTaxonomyTerms(PostTaxonomy::TYPE_CATEGORY, ['Design', 'Discussion', 'Music', 'Singles', 'Typography', 'Uncategorized']),
            'blogTags' => $this->blogTaxonomyTerms(PostTaxonomy::TYPE_TAG, ['articles', 'concerts', 'live', 'music', 'news', 'on stage']),
            'archives' => $this->cachedPostArchives(),
            'comments' => ['admin on Landscape Post', 'A WordPress Commenter on Lucille'],
        ]);
    }

    private function cachedPostArchives(): array
    {
        $version = $this->cacheVersion('posts');

        return Cache::remember(
            "site.posts.archives.v{$version}",
            now()->addHours(24),
            function (): array {
                if (! Schema::hasTable('posts')) {
                    return [];
                }

                return Post::query()
                    ->published()
                    ->whereNotNull('published_at')
                    ->selectRaw('YEAR(published_at) as yr, MONTH(published_at) as mo')
                    ->groupByRaw('YEAR(published_at), MONTH(published_at)')
                    ->orderByDesc('yr')
                    ->orderByDesc('mo')
                    ->get()
                    ->map(function ($row): array {
                        $year = trim((string) $row->yr);
                        $month = \DateTime::createFromFormat('!m', (string) $row->mo)?->format('F') ?? '';
                        $monthValue = str_pad((string) ((int) $row->mo), 2, '0', STR_PAD_LEFT);
                        $url = route('blog.archives', [
                            'year' => $year,
                            'month' => $monthValue,
                        ]);

                        return [
                            'year' => $year,
                            'month' => $monthValue,
                            'label' => trim($month . ' ' . $year),
                            'url' => $url,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();
            }
        );
    }

    private function postLikesCount(int $postId): int
    {
        return (int) PostReaction::query()
            ->where('post_id', $postId)
            ->where('reaction_type', 'like')
            ->count();
    }

    private function hasPostLiked(int $postId): bool
    {
        $ownerKey = $this->contentReactionOwnerKey();
        if ($ownerKey === '') {
            return false;
        }

        return PostReaction::query()
            ->where('post_id', $postId)
            ->where('reaction_type', 'like')
            ->where('owner_key', $ownerKey)
            ->exists();
    }

    private function contentReactionOwnerKey(): string
    {
        if (auth()->check()) {
            return 'user:' . (string) auth()->id();
        }

        $cookieValue = trim((string) request()->cookie('sr_content_reactions_owner', ''));

        return $cookieValue !== '' ? 'visitor:' . $cookieValue : '';
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
        $version = $this->cacheVersion('posts');

        return Cache::remember("site.home.latest_posts.v{$version}", now()->addMinutes(10), function (): array {
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
        });
    }

    private function cacheVersion(string $scope, int $fallback = 1): int
    {
        return (int) Cache::rememberForever("cache.version.{$scope}", static fn () => $fallback);
    }

    private function cachedPublishedPostsPaginator(int $perPage = 20)
    {
        $page = max(1, (int) request()->integer('page', 1));
        $version = $this->cacheVersion('posts');

        $cached = Cache::remember(
            "site.posts.paginator.safe.v{$version}.page{$page}.per{$perPage}",
            now()->addMinutes(10),
            function () use ($perPage, $page) {
                $query = Post::query()->published()->orderByDesc('published_at');

                if (Schema::hasTable('post_reactions')) {
                    $query->withCount([
                        'reactions as likes_count' => fn ($reactionQuery) => $reactionQuery->where('reaction_type', 'like'),
                    ]);
                }

                $paginator = $query->paginate($perPage, ['*'], 'page', $page);

                return [
                    'items' => $paginator->getCollection()->map(fn ($post) => $post->toArray())->all(),
                    'total' => $paginator->total(),
                    'lastPage' => $paginator->lastPage(),
                ];
            }
        );

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $cached['items'] ?? [],
            (int) ($cached['total'] ?? 0),
            $perPage,
            $page,
            [
                'path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
                'lastPage' => (int) ($cached['lastPage'] ?? 1),
            ]
        );
    }

    /**
     * @template T
     * @param callable():T $resolver
     * @return T
     */
    private function cachedEvents(string $scope, callable $resolver, int $minutes = 15): mixed
    {
        $version = $this->cacheVersion('events');

        return Cache::remember(
            "site.events.{$scope}.v{$version}",
            now()->addMinutes($minutes),
            function () use ($resolver) {
                $result = $resolver();

                if ($result instanceof \Illuminate\Database\Eloquent\Collection) {
                    return $result->toArray();
                }

                return collect($result)
                    ->map(function ($event): array {
                        $startsAt = data_get($event, 'starts_at');

                        return [
                            'slug' => (string) data_get($event, 'slug', ''),
                            'title' => (string) data_get($event, 'title', ''),
                            'starts_at' => $startsAt instanceof \DateTimeInterface
                                ? $startsAt->format('Y-m-d H:i:s')
                                : (string) $startsAt,
                            'location' => data_get($event, 'location'),
                            'venue' => (string) data_get($event, 'venue', ''),
                            'ticket_label' => (string) data_get($event, 'ticket_label', 'Details'),
                        ];
                    })
                    ->values()
                    ->all();
            }
        );
    }

    private function cachedGalleryImages(int $limit = 20, int $minutes = 15): array
    {
        $version = $this->cacheVersion('gallery');

        return Cache::remember(
            "site.gallery.images.v{$version}.limit{$limit}",
            now()->addMinutes($minutes),
            function () use ($limit) {
                if (! Schema::hasTable('talent_media') || ! Schema::hasTable('talents')) {
                    return [];
                }

                return \App\Models\TalentMedia::query()
                    ->where('type', 'photo')
                    ->whereHas('talent', fn ($q) => $q->where('subscription_status', 'active'))
                    ->with('talent')
                    ->latest()
                    ->limit($limit)
                    ->get()
                    ->toArray();
            }
        );
    }

    private function cachedLatestAlbum(int $minutes = 15): ?array
    {
        $version = $this->cacheVersion('albums');

        return Cache::remember(
            "site.albums.latest.v{$version}",
            now()->addMinutes($minutes),
            fn () => Album::query()->latest('released_at')->first()?->toArray()
        );
    }

    private function cachedAlbumBySlug(string $slug): ?array
    {
        $version = $this->cacheVersion('albums');

        return Cache::remember(
            "site.albums.single.admin.{$slug}.v{$version}",
            now()->addMinutes(20),
            fn () => Album::query()->where('slug', $slug)->first()?->toArray()
        );
    }

    private function cachedTalentAlbumBySlug(string $slug): ?array
    {
        $version = $this->cacheVersion('albums');

        return Cache::remember(
            "site.albums.single.talent.{$slug}.v{$version}",
            now()->addMinutes(20),
            fn () => TalentAlbum::query()->where('slug', $slug)->with('talent.media')->first()?->toArray()
        );
    }

    private function cachedDiscographyAlbums(): array
    {
        $version = $this->cacheVersion('albums');

        return Cache::remember(
            "site.albums.discography.v{$version}",
            now()->addMinutes(20),
            function (): array {
                $talentAlbums = \App\Models\TalentAlbum::query()
                    ->where('is_published', true)
                    ->with('talent')
                    ->orderByDesc('release_date')
                    ->get()
                    ->map(fn ($a) => [
                        'id' => $a->id,
                        'title' => $a->title,
                        'slug' => $a->slug,
                        'artist' => $a->talent->band_name ?? 'Artista',
                        'cover' => $a->coverUrl() ?? asset('assets/lucille/man-597179_1920.jpg'),
                        'date' => $a->release_date?->format('F j, Y') ?? '',
                        'sort' => $a->release_date?->timestamp ?? 0,
                        'type' => 'talent',
                        'url' => route('albums.single', ['slug' => $a->slug]),
                    ]);

                $adminAlbums = \App\Models\Album::query()
                    ->whereNotNull('title')
                    ->orderByDesc('released_at')
                    ->get()
                    ->map(fn ($a) => [
                        'id' => $a->id,
                        'title' => $a->title,
                        'slug' => $a->slug,
                        'artist' => $a->artist ?? 'Artista',
                        'cover' => $a->cover_image_url,
                        'date' => $a->released_at?->format('F j, Y') ?? '',
                        'sort' => $a->released_at?->timestamp ?? 0,
                        'type' => 'admin',
                        'url' => route('albums.single', ['slug' => $a->slug]),
                    ]);

                return $talentAlbums->concat($adminAlbums)->sortByDesc('sort')->values()->all();
            }
        );
    }

    /**
     * @param array<int, string> $values
     * @param array<int, string> $fallback
     * @return array<int, string>
     */
    private function blogListing(string $pageTitle, ?string $pageSubtitle, string $pageDescription, mixed $posts): View
    {
        $version = $this->cacheVersion('posts');

        return view('pages.blog-standard', [
            'pageTitle' => $pageTitle,
            'pageSubtitle' => $pageSubtitle,
            'pageDescription' => $pageDescription,
            'posts' => $posts,
            'recentPosts' => Cache::remember(
                "site.posts.recent.v{$version}",
                now()->addMinutes(10),
                function () {
                    $query = Post::query()->published()->latest('published_at')->limit(5);

                    if (Schema::hasTable('post_reactions')) {
                        $query->withCount([
                            'reactions as likes_count' => fn ($reactionQuery) => $reactionQuery->where('reaction_type', 'like'),
                        ]);
                    }

                    return $query->get()->toArray();
                }
            ),
            'blogCategories' => $this->blogTaxonomyTerms(PostTaxonomy::TYPE_CATEGORY, ['Design', 'Discussion', 'Music', 'Singles', 'Typography', 'Uncategorized']),
            'blogTags' => $this->blogTaxonomyTerms(PostTaxonomy::TYPE_TAG, ['articles', 'concerts', 'live', 'music', 'news', 'on stage']),
        ]);
    }

    private function blogArchive(string $type, string $slug, string $pageTitle, string $pageSubtitle): View
    {
        $taxonomy = PostTaxonomy::query()
            ->where('type', $type)
            ->where('slug', $slug)
            ->first();

        abort_if(! $taxonomy, 404);

        $version = $this->cacheVersion('posts');
        $page = max(1, (int) request()->integer('page', 1));
        $cached = Cache::remember(
            "site.blog.archive.safe.{$type}.{$slug}.v{$version}.page{$page}.per20",
            now()->addMinutes(10),
            function () use ($type, $slug, $page): array {
                $query = Post::query()
                    ->published()
                    ->whereHas('taxonomies', function ($query) use ($type, $slug): void {
                        $query->where('type', $type)->where('slug', $slug);
                    })
                    ->orderByDesc('published_at');

                if (Schema::hasTable('post_reactions')) {
                    $query->withCount([
                        'reactions as likes_count' => fn ($reactionQuery) => $reactionQuery->where('reaction_type', 'like'),
                    ]);
                }

                $paginator = $query->paginate(20, ['*'], 'page', $page);

                return [
                    'items' => $paginator->getCollection()->map(fn ($post) => $post->toArray())->all(),
                    'total' => $paginator->total(),
                    'lastPage' => $paginator->lastPage(),
                ];
            }
        );

        $posts = new \Illuminate\Pagination\LengthAwarePaginator(
            $cached['items'] ?? [],
            (int) ($cached['total'] ?? 0),
            20,
            $page,
            [
                'path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
                'lastPage' => (int) ($cached['lastPage'] ?? 1),
            ]
        );

        return $this->blogListing(
            pageTitle: $taxonomy->name,
            pageSubtitle: $pageSubtitle,
            pageDescription: $this->blogArchiveDescription($taxonomy->name, $type),
            posts: $posts
        );
    }

    /**
     * @param array<int, string> $fallback
     * @return array<int, string>
     */
    private function blogTaxonomyTerms(string $type, array $fallback = []): array
    {
        $version = $this->cacheVersion('posts');
        $cacheKey = "site.blog.taxonomy.{$type}.v{$version}";

        return Cache::remember($cacheKey, now()->addMinutes(20), function () use ($type, $fallback): array {
            if (DB::connection()->getSchemaBuilder()->hasTable('post_taxonomies') && DB::connection()->getSchemaBuilder()->hasTable('post_taxonomy_post')) {
                $terms = PostTaxonomy::query()
                    ->where('type', $type)
                    ->whereHas('posts', fn ($query) => $query->published())
                    ->orderBy('name')
                    ->pluck('name')
                    ->all();

                $result = array_values(array_unique(array_filter(array_map('trim', $terms))));

                if (! empty($result)) {
                    return $result;
                }
            }

            return array_values(array_unique(array_filter(array_map('trim', $fallback))));
        });
    }

    private function blogArchiveDescription(string $term, string $type): string
    {
        $label = $type === PostTaxonomy::TYPE_TAG ? 'etiqueta' : 'categoría';

        return "Entradas del blog filtradas por la {$label} {$term}.";
    }

    /**
     * @param array<int, string> $values
     * @param array<int, string> $fallback
     * @return array<int, string>
     */
    private function mergeTaxonomyValues(array $values, string $type, array $fallback = []): array
    {
        $terms = array_values(array_filter(array_map('trim', $values)));

        $terms = array_merge($terms, $this->blogTaxonomyTerms($type, $fallback));

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





    public function contactSend(ContactFormRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Mail::to('prog.sevenrockradio@gmail.com')->send(new ContactMail(
            senderName: $validated['name'],
            senderEmail: $validated['email'],
            senderPhone: $validated['phone'] ?? '',
            messageBody: $validated['message'],
            source: 'Contacto',
        ));

        return redirect()->back()->with('success', '¡Mensaje enviado correctamente!');
    }
    public function homeContactSend(ContactFormRequest $request): RedirectResponse
    {
        $validated = $request->validated();

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
                ['title' => 'Key To The Highway', 'audio' => asset('assets/lucille/preview_key_to_the_highway.mp3')],
                ['title' => 'Paint It Black', 'audio' => asset('assets/lucille/preview_paint_it_black.mp3')],
            ],
            'buttons' => [
                ['label' => 'Ver Artistas', 'url' => '/talentos'],
                ['label' => 'Buscar en Tienda', 'url' => '/shop'],
            ],
            'content' => [
                'Nightride is the second studio album released by American singer Tinashe, released on November 4, 2016. The first single from the album, Company, was released on September 16, 2016.',
                'The album includes the promotional singles Ride of Your Life and Party Favors. The album is the first part of a double album completed with Joyride.',
            ],
        ];
    }

    private function singleEvent(string $slug): array
    {
        $version = $this->cacheVersion('events');
        $event = Cache::remember(
            "site.events.single.{$slug}.v{$version}",
            now()->addMinutes(20),
            fn () => Event::query()->where('slug', $slug)->first()
        );

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
                'embed' => $event->embed_url ?: '',
                'map' => $event->map_url ?: '',
                'content' => array_values(array_filter(array_map('strval', $event->content ?? []))),
            ];
        }

        $events = [
            'rockness-festival' => [
                'title' => 'Rockness Festival',
                'categories' => ['Guest Appearance', 'Music Festivals'],
                'date' => 'March 21, 2026',
                'time' => '8:00 pm',
                'location' => null,
                'venue' => 'Rockness Festival',
                'venue_url' => 'http://www.rockness.co.uk/',
                'ticket_url' => 'http://www.ticketmaster.co.uk/',
                'facebook_url' => 'https://www.facebook.com/OfficialRockNess',
                'poster' => 'assets/lucille/ozzfest_poster.jpg',
                'embed' => '',
                'map' => '',
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

    /**
     * @return array{
     *     title:string,
     *     artist:string,
     *     cover:string,
     *     date:string,
     *     label:string,
     *     producer:string,
     *     discs:string,
     *     categories:array<int,string>,
     *     tracks:array<int,array{title:string,duration:string,audio:string}>,
     *     buttons:array<int,array{label:string,url:string}>,
     *     content:array<int,string>
     * }
     */
    private function adminAlbumViewData(Album $album): array
    {
        return [
            'title' => $album->title,
            'artist' => $album->artist,
            'cover' => $album->cover_image_url,
            'date' => $album->released_at?->format('F j, Y') ?? 'N/A',
            'label' => 'Seven Rock Radio',
            'producer' => 'Admin',
            'discs' => '1',
            'categories' => ['new album', 'official release'],
            'tracks' => array_values(array_map(static function ($track): array {
                $track = is_array($track) ? $track : [];

                return [
                    'title' => (string) ($track['title'] ?? ''),
                    'duration' => (string) ($track['duration'] ?? ''),
                    'audio' => (string) ($track['audio'] ?? ''),
                ];
            }, is_array($album->tracks ?? null) ? $album->tracks : [])),
            'buttons' => $this->albumButtons($album),
            'content' => array_values(array_filter([
                $album->summary,
                'This album is managed from the admin panel and is now part of the public catalog.',
            ])),
        ];
    }

    /**
     * @return array{
     *     title:string,
     *     artist:string,
     *     cover:string,
     *     date:string,
     *     label:string,
     *     producer:string,
     *     discs:string,
     *     categories:array<int,string>,
     *     tracks:array<int,array{title:string,duration:string,audio:string}>,
     *     buttons:array<int,array{label:string,url:string}>,
     *     content:array<int,string>
     * }
     */
    private function talentAlbumViewData(array|TalentAlbum $album): array
    {
        $talent = data_get($album, 'talent');
        $audioMedia = collect(data_get($album, 'talent.media', []));

        $tracks = collect(data_get($album, 'tracks', []))
            ->values()
            ->map(function ($track, int $index) use ($audioMedia): array {
                $media = $audioMedia[$index] ?? null;
                $directAudio = trim((string) data_get($track, 'audio', ''));
                $linkedAudio = trim((string) data_get($track, 'url', ''));
                $mediaAudio = trim((string) data_get($media, 'url', ''));
                $audio = $directAudio !== '' ? $directAudio : ($linkedAudio !== '' ? $linkedAudio : $mediaAudio);

                return [
                    'title' => trim((string) (data_get($track, 'title') ?? data_get($track, 'name') ?? 'Track ' . ($index + 1))),
                    'duration' => (string) data_get($track, 'duration', ''),
                    'audio' => $audio,
                    'audio_source' => $directAudio !== '' ? 'direct' : ($linkedAudio !== '' ? 'linked' : ($mediaAudio !== '' ? 'talent-media' : 'none')),
                ];
            })
            ->all();

        $releaseDate = data_get($album, 'release_date');
        $releaseDateLabel = $releaseDate instanceof \DateTimeInterface
            ? $releaseDate->format('F j, Y')
            : (filled($releaseDate) ? \Illuminate\Support\Carbon::parse($releaseDate)->format('F j, Y') : 'N/A');
        $cover = PublicMediaUrl::normalizePublicUrl(data_get($album, 'cover_url'))
            ?: PublicMediaUrl::normalizePublicUrl(data_get($album, 'cover_image'))
            ?: asset('assets/lucille/man-597179_1920.jpg');

        return [
            'title' => (string) data_get($album, 'title', ''),
            'artist' => (string) data_get($talent, 'band_name', 'Artista'),
            'cover' => $cover,
            'date' => $releaseDateLabel,
            'label' => (string) data_get($talent, 'band_name', 'Seven Rock Radio'),
            'producer' => (string) data_get($talent, 'band_name', 'Talent'),
            'discs' => '1',
            'categories' => ['new album', 'official release'],
            'tracks' => $tracks,
            'buttons' => [],
            'content' => array_values(array_filter([
                data_get($album, 'description'),
                'This album is managed from the talent panel and is now part of the public catalog.',
            ])),
        ];
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

    private function eventsCatalog(string $title, string $subtitle, string $description, $events): View
    {
        return view('pages.events', [
            'pageTitle' => $title,
            'pageSubtitle' => $subtitle,
            'description' => $description,
            'events' => $events,
        ]);
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
            'likes_count' => 0,
            'liked' => false,
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
