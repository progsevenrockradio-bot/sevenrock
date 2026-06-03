<?php

namespace Database\Seeders;

use App\Models\PostTaxonomy;
use Illuminate\Database\Seeder;

class PostTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            PostTaxonomy::TYPE_CATEGORY => [
                'Blog',
                'Design',
                'Discussion',
                'Artistas',
                'Conciertos',
                'Music',
                'Rock',
                'Singles',
                'Typography',
                'Uncategorized',
            ],
            PostTaxonomy::TYPE_TAG => [
                'articles',
                'concerts',
                'live',
                'music',
                'news',
                'on stage',
                'rock',
            ],
        ];

        foreach ($items as $type => $names) {
            foreach ($names as $name) {
                PostTaxonomy::query()->updateOrCreate(
                    ['type' => $type, 'slug' => \Illuminate\Support\Str::slug($name)],
                    ['name' => $name]
                );
            }
        }
    }
}
