<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\PostTaxonomy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SyncPostTaxonomiesSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('post_taxonomy_post') || ! Schema::hasTable('posts')) {
            return;
        }

        if (! Schema::hasColumn('posts', 'categories') || ! Schema::hasColumn('posts', 'tags')) {
            return;
        }

        Post::query()->select('id', 'categories', 'tags')->chunk(100, function ($posts): void {
            foreach ($posts as $post) {
                $taxonomyIds = [];

                foreach ($this->normalizeList($post->categories ?? []) as $name) {
                    $taxonomyIds[] = $this->ensureTaxonomy(PostTaxonomy::TYPE_CATEGORY, $name)->id;
                }

                foreach ($this->normalizeList($post->tags ?? []) as $name) {
                    $taxonomyIds[] = $this->ensureTaxonomy(PostTaxonomy::TYPE_TAG, $name)->id;
                }

                $post->taxonomies()->sync(array_values(array_unique($taxonomyIds)));
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function normalizeList(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $value)));
    }

    private function ensureTaxonomy(string $type, string $name): PostTaxonomy
    {
        return PostTaxonomy::query()->firstOrCreate(
            [
                'type' => $type,
                'slug' => Str::slug($name),
            ],
            [
                'name' => $name,
            ]
        );
    }
}
