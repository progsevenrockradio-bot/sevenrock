<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('post_taxonomy_post');

        Schema::create('post_taxonomy_post', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('post_taxonomy_id');
            $table->timestamps();

            $table->unique(['post_id', 'post_taxonomy_id']);
            $table->index('post_id');
            $table->index('post_taxonomy_id');
        });

        if (! Schema::hasTable('posts') || ! Schema::hasTable('post_taxonomies')) {
            return;
        }

        if (! Schema::hasColumn('posts', 'categories') || ! Schema::hasColumn('posts', 'tags')) {
            return;
        }

        $posts = DB::table('posts')->select('id', 'categories', 'tags')->get();

        foreach ($posts as $post) {
            $categories = $this->decodeJsonList($post->categories);
            $tags = $this->decodeJsonList($post->tags);

            foreach ($categories as $name) {
                $this->attachTaxonomy((int) $post->id, 'category', $name);
            }

            foreach ($tags as $name) {
                $this->attachTaxonomy((int) $post->id, 'tag', $name);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('post_taxonomy_post');
    }

    /**
     * @return array<int, string>
     */
    private function decodeJsonList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $decoded)));
    }

    private function attachTaxonomy(int $postId, string $type, string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }

        $taxonomyId = DB::table('post_taxonomies')->updateOrInsert(
            [
                'type' => $type,
                'slug' => Str::slug($name),
            ],
            [
                'name' => $name,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $taxonomy = DB::table('post_taxonomies')
            ->where('type', $type)
            ->where('slug', Str::slug($name))
            ->first();

        if ($taxonomy) {
            DB::table('post_taxonomy_post')->updateOrInsert(
                [
                    'post_id' => $postId,
                    'post_taxonomy_id' => $taxonomy->id,
                ],
                [
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
};
