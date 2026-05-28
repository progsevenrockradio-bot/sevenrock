<?php

namespace Database\Seeders;

use App\Models\Album;
use App\Models\Event;
use App\Models\GalleryImage;
use App\Models\Product;
use App\Models\Post;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Models\Video;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@sevenrockradio.local'],
            [
                'name' => 'Seven Rock Admin',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        ThemeSetting::query()->firstOrCreate([], ThemeSetting::defaults());
        $this->call(TalentPlanSeeder::class);
        $this->call(MasterProgramsSeeder::class);
        $this->call(OutreachTemplateSeeder::class);
        $this->call(PostTaxonomySeeder::class);

        Event::query()->delete();
        Album::query()->delete();
        Video::query()->delete();
        GalleryImage::query()->delete();
        Post::query()->delete();
        Product::query()->delete();

        Event::query()->insert([
            [
                'title' => 'Wakestock Festival',
                'slug' => 'wakestock-festival',
                'starts_at' => CarbonImmutable::create(2026, 8, 12, 20, 0),
                'location' => 'Abersoch, Gwynedd, UK',
                'venue' => 'Wakestock Festival',
                'ticket_url' => 'https://www.wakestock.co.uk/buy-tickets/',
                'ticket_label' => 'Tickets',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Rockness Festival',
                'slug' => 'rockness-festival',
                'starts_at' => CarbonImmutable::create(2026, 10, 21, 20, 0),
                'location' => 'Loch Ness, uk',
                'venue' => 'Rockness Festival',
                'ticket_url' => 'https://www.ticketmaster.co.uk/',
                'ticket_label' => 'Tickets',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Coachella Music Festival',
                'slug' => 'coachella-music-festival',
                'starts_at' => CarbonImmutable::create(2026, 10, 24, 21, 0),
                'location' => 'Indio, California',
                'venue' => 'Coachella Music Festival',
                'ticket_url' => 'https://www.ticketmaster.co.uk/',
                'ticket_label' => 'Tickets',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        foreach ([
            [
                'title' => 'Nightride',
                'slug' => 'nightride',
                'artist' => 'Tinashe',
                'cover_image' => 'assets/lucille/album1.jpg',
                'released_at' => CarbonImmutable::create(2026, 11, 4),
                'summary' => 'Nightride is a nocturnal R&B record with sleek production and a cold neon palette.',
                'tracks' => [
                    ['title' => 'Lucid', 'duration' => '3:28'],
                    ['title' => 'On A Wave', 'duration' => '4:01'],
                ],
                'buy_links' => [
                    ['label' => 'Buy Album From iTunes', 'url' => 'https://itunes.apple.com/'],
                ],
            ],
            [
                'title' => 'Company',
                'slug' => 'company',
                'artist' => 'Tinashe',
                'cover_image' => 'assets/lucille/album3.jpg',
                'released_at' => CarbonImmutable::create(2026, 4, 21),
                'summary' => 'Tinashe is an American singer-songwriter, dancer, actress, and former model. Her sound moves between late-night R&B, polished pop hooks and club-ready production.',
                'tracks' => [
                    ['title' => 'Company', 'duration' => '3:58'],
                    ['title' => 'Secret', 'duration' => '4:12'],
                ],
                'buy_links' => [
                    ['label' => 'Buy Album From iTunes', 'url' => 'https://itunes.apple.com/'],
                ],
            ],
            [
                'title' => 'Stereotypes',
                'slug' => 'stereotypes',
                'artist' => 'Black Violin',
                'cover_image' => 'assets/lucille/Stereotypes.jpg',
                'released_at' => CarbonImmutable::create(2025, 8, 12),
                'summary' => 'A hybrid string-led record that pulls classical motifs into modern hip hop energy.',
                'tracks' => [
                    ['title' => 'Stereotypes', 'duration' => '3:42'],
                    ['title' => 'Miracle', 'duration' => '4:08'],
                ],
                'buy_links' => [
                    ['label' => 'Buy Album From iTunes', 'url' => 'https://itunes.apple.com/'],
                ],
            ],
            [
                'title' => 'Here',
                'slug' => 'here',
                'artist' => 'Alicia Keys',
                'cover_image' => 'assets/lucille/album2.jpg',
                'released_at' => CarbonImmutable::create(2025, 2, 18),
                'summary' => 'Here balances soul, piano and direct lyricism in a stripped back studio setting.',
                'tracks' => [
                    ['title' => 'Here', 'duration' => '3:33'],
                    ['title' => 'Holy War', 'duration' => '4:21'],
                ],
                'buy_links' => [
                    ['label' => 'Buy Album From iTunes', 'url' => 'https://itunes.apple.com/'],
                ],
            ],
            [
                'title' => 'Because Of The Times',
                'slug' => 'because-of-the-times',
                'artist' => 'Kings of Leon',
                'cover_image' => 'assets/lucille/becauseoftimes.jpg',
                'released_at' => CarbonImmutable::create(2024, 6, 10),
                'summary' => 'Dusty guitars and wide-open choruses shape a record built for large rooms.',
                'tracks' => [
                    ['title' => 'Knocked Up', 'duration' => '7:18'],
                    ['title' => 'Charmer', 'duration' => '2:57'],
                ],
                'buy_links' => [
                    ['label' => 'Buy Album From iTunes', 'url' => 'https://itunes.apple.com/'],
                ],
            ],
            [
                'title' => 'Made Up Mind',
                'slug' => 'made-up-mind',
                'artist' => 'Tedeschi Trucks Band',
                'cover_image' => 'assets/lucille/Made-Up-Mind.jpg',
                'released_at' => CarbonImmutable::create(2023, 9, 3),
                'summary' => 'Blues-rock with a dense live feel and a warm, analog mix.',
                'tracks' => [
                    ['title' => 'Made Up Mind', 'duration' => '5:07'],
                    ['title' => 'Do I Look Worried', 'duration' => '4:46'],
                ],
                'buy_links' => [
                    ['label' => 'Buy Album From iTunes', 'url' => 'https://itunes.apple.com/'],
                ],
            ],
        ] as $album) {
            Album::query()->create($album);
        }

        Video::query()->create([
            'title' => 'Freedom at 21',
            'slug' => 'freedom-at-21',
            'image' => 'assets/lucille/freedom-at-21-header.jpg',
            'youtube_url' => 'https://www.youtube.com/watch?v=gEPmA3USJdI',
            'summary' => 'A high-contrast live cut built around smoke, stage lights and a heavy rhythm section.',
        ]);

        foreach ([
            [
                'title' => 'Bring Me The Horizon T-Shirt',
                'slug' => 'bring-me-the-horizon',
                'image' => 'assets/lucille/shop-1.jpg',
                'price' => 30.00,
                'regular_price' => null,
                'category' => 'T-SHIRTS',
                'description' => 'Each t-shirt is unique with vintage finish and mini ribbed neckline.',
                'is_sale' => false,
                'sort_order' => 1,
            ],
            [
                'title' => 'Guns N Roses Civil War T-Shirt',
                'slug' => 'guns-n-roses-civil-war',
                'image' => 'assets/lucille/shop-4.jpg',
                'price' => 28.00,
                'regular_price' => null,
                'category' => 'T-SHIRTS',
                'description' => 'Soft cotton shirt with a distressed print and a classic fit.',
                'is_sale' => false,
                'sort_order' => 2,
            ],
            [
                'title' => 'Guns N Roses Drum T-Shirt',
                'slug' => 'guns-n-roses-drum',
                'image' => 'assets/lucille/shop-5.jpg',
                'price' => 30.00,
                'regular_price' => null,
                'category' => 'T-SHIRTS',
                'description' => 'Drum artwork tee with a washed finish and raw edge detail.',
                'is_sale' => false,
                'sort_order' => 3,
            ],
            [
                'title' => 'Led Zeppelin T-Shirt',
                'slug' => 'led-zeppelin',
                'image' => 'assets/lucille/shop-2.jpg',
                'price' => 30.00,
                'regular_price' => null,
                'category' => 'T-SHIRTS',
                'description' => 'A vintage-style Led Zeppelin shirt with a soft worn feel.',
                'is_sale' => false,
                'sort_order' => 4,
            ],
            [
                'title' => 'Slayer T-Shirt',
                'slug' => 'slayer',
                'image' => 'assets/lucille/shop-9.jpg',
                'price' => 19.00,
                'regular_price' => 30.00,
                'category' => 'T-SHIRTS',
                'description' => 'Limited sale tee with a reduced price and bold front print.',
                'is_sale' => true,
                'sort_order' => 5,
            ],
            [
                'title' => 'The Beatles T-Shirt',
                'slug' => 'the-beatles',
                'image' => 'assets/lucille/beatles_t_shirt.jpeg',
                'price' => 30.00,
                'regular_price' => null,
                'category' => 'T-SHIRTS',
                'description' => 'Classic Beatles tee with a soft hand feel and retro print.',
                'is_sale' => false,
                'sort_order' => 6,
            ],
            [
                'title' => 'The Doors Tee Shirt',
                'slug' => 'the-doors-tee-shirt',
                'image' => 'assets/lucille/shop-8.jpg',
                'price' => 28.00,
                'regular_price' => null,
                'category' => 'T-SHIRTS',
                'description' => 'Faded rock tee with a clean neckline and relaxed fit.',
                'is_sale' => false,
                'sort_order' => 7,
            ],
            [
                'title' => 'The Who T-Shirt',
                'slug' => 'the-who',
                'image' => 'assets/lucille/shop-6.jpg',
                'price' => 30.00,
                'regular_price' => null,
                'category' => 'T-SHIRTS',
                'description' => 'The Who shirt with a vintage wash and durable cotton fabric.',
                'is_sale' => false,
                'sort_order' => 8,
            ],
        ] as $product) {
            Product::query()->create($product);
        }

        Post::query()->insert([
            [
                'title' => 'Pagination Post',
                'slug' => 'pagination-post',
                'author' => 'admin',
                'excerpt' => 'Jazz is rage. Jazz flows like water. Jazz never seems to begin or end.',
                'content' => json_encode([
                    'Jazz is rage. Jazz flows like water. Jazz never seems to begin or end.',
                    'The post uses the same editorial tone as the original Lucille demo.',
                    'This paragraph keeps the layout balanced and readable.',
                    'A closing note rounds out the single post content.',
                ], JSON_UNESCAPED_SLASHES),
                'quote' => 'Jazz is rage. Jazz flows like water.',
                'featured_image' => 'assets/lucille/man-597179_1920.jpg',
                'published_at' => CarbonImmutable::create(2016, 11, 16, 10, 0),
                'categories' => json_encode(['Singles', 'Uncategorized']),
                'tags' => json_encode(['jazz', 'music', 'news']),
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Beautiful Typography',
                'slug' => 'beautiful-typography',
                'author' => 'admin',
                'excerpt' => 'This is heading The bank manager long ago won the battle for the heart of...',
                'content' => json_encode([
                    'This is heading The bank manager long ago won the battle for the heart of...',
                    'A short editorial post keeps the standard blog layout populated.',
                    'Typography-focused content fits the Lucille-inspired skin well.',
                    'The closing paragraph keeps the article from feeling empty.',
                ], JSON_UNESCAPED_SLASHES),
                'quote' => 'This is heading The bank manager long ago won the battle for the heart of...',
                'featured_image' => null,
                'published_at' => CarbonImmutable::create(2016, 11, 4, 10, 0),
                'categories' => json_encode(['Typography', 'Uncategorized']),
                'tags' => json_encode(['typography', 'design']),
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Portrait Post',
                'slug' => 'portrait-post',
                'author' => 'admin',
                'excerpt' => 'Sometimes I’ll have sections that I’m not quite sure how they fit in the puzzle...',
                'content' => json_encode([
                    'Sometimes I’ll have sections that I’m not quite sure how they fit in the puzzle...',
                    'This entry is here to keep the public blog and standard listing alive.',
                    'The image and excerpt mirror the original theme structure.',
                    'A short ending paragraph finishes the post cleanly.',
                ], JSON_UNESCAPED_SLASHES),
                'quote' => 'Sometimes I’ll have sections that I’m not quite sure how they fit in the puzzle...',
                'featured_image' => 'assets/lucille/new-york-1209232_1920.jpg',
                'published_at' => CarbonImmutable::create(2016, 10, 13, 10, 0),
                'categories' => json_encode(['Music', 'Uncategorized']),
                'tags' => json_encode(['portrait', 'music']),
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        foreach ([
            'audience_opt.jpg' => 'Audience lights',
            'hipster-869222_1920.jpg' => 'Backstage portrait',
            'guitar-1245856_1920.jpg' => 'Guitar closeup',
            'music-1284505_1920.jpg' => 'Studio performance',
            'pedalboard-1511069_1920.jpg' => 'Pedalboard',
            'string-555070.jpg' => 'Strings',
            'new-york-1209232_1920.jpg' => 'City poster',
        ] as $index => $caption) {
            GalleryImage::query()->create([
                'image' => 'assets/lucille/'.$index,
                'caption' => $caption,
                'sort_order' => GalleryImage::query()->count() + 1,
            ]);
        }

        $this->call(SyncPostTaxonomiesSeeder::class);
    }
}
