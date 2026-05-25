<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostTaxonomy;
use App\Support\WordPressContent;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Database\Eloquent\Model;

class PostController extends Controller
{
    public function index(): View
    {
        return view('admin.posts.index', [
            'posts' => Post::query()->orderByDesc('published_at')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.posts.create', [
            'post' => new Post([
                'published_at' => now(),
                'categories' => [],
                'tags' => [],
                'content' => [],
                'is_published' => true,
            ]),
            'editorBlocks' => [
                ['type' => 'paragraph', 'value' => ''],
            ],
            'categoriesSuggestions' => $this->taxonomySuggestions(PostTaxonomy::TYPE_CATEGORY),
            'tagsSuggestions' => $this->taxonomySuggestions(PostTaxonomy::TYPE_TAG),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $data = $this->validated($request);
            $categories = $data['categories'];
            $tags = $data['tags'];
            unset($data['categories'], $data['tags']);
            $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
            $data['featured_image'] = $this->resolveImage($request, null, $data['featured_image'] ?? null);
            $data = $this->persistableAttributes($data);

            $post = Post::query()->create($data);
            $this->syncTaxonomies($post, $categories, $tags);

            return redirect()->route('admin.posts.index')->with('status', 'Post created.');
        } catch (\Throwable $exception) {
            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            Log::error('Error al crear post', [
                'exception' => $exception,
                'input' => $request->except(['featured_image_file']),
            ]);

            return back()
                ->withInput()
                ->withErrors(['general' => 'No se pudo crear el post. Inténtalo de nuevo.']);
        }
    }

    public function edit(Post $post): View
    {
        return view('admin.posts.edit', [
            'post' => $post,
            'editorBlocks' => WordPressContent::toEditorBlocks($post->content ?? []),
            'categoriesText' => implode(', ', $this->taxonomyNames($post, PostTaxonomy::TYPE_CATEGORY, 'categories')),
            'tagsText' => implode(', ', $this->taxonomyNames($post, PostTaxonomy::TYPE_TAG, 'tags')),
            'categoriesSuggestions' => $this->taxonomySuggestions(PostTaxonomy::TYPE_CATEGORY),
            'tagsSuggestions' => $this->taxonomySuggestions(PostTaxonomy::TYPE_TAG),
        ]);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        try {
            $data = $this->validated($request, $post->id);
            $categories = $data['categories'];
            $tags = $data['tags'];
            unset($data['categories'], $data['tags']);
            $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
            $data['featured_image'] = $this->resolveImage($request, $post->featured_image, $data['featured_image'] ?? null);
            $data = $this->persistableAttributes($data);

            $post->update($data);
            $this->syncTaxonomies($post, $categories, $tags);

            return redirect()->route('admin.posts.index')->with('status', 'Post updated.');
        } catch (\Throwable $exception) {
            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            Log::error('Error al actualizar post', [
                'post_id' => $post->id,
                'exception' => $exception,
                'input' => $request->except(['featured_image_file']),
            ]);

            return back()
                ->withInput()
                ->withErrors(['general' => 'No se pudo actualizar el post. Inténtalo de nuevo.']);
        }
    }

    public function destroy(Post $post): RedirectResponse
    {
        $this->deleteUploaded($post->featured_image);
        $post->delete();

        return redirect()->route('admin.posts.index')->with('status', 'Post deleted.');
    }

    public function uploadMedia(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['nullable', 'image', 'max:6144'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'max:6144'],
        ]);

        $urls = [];

        if ($request->hasFile('image')) {
            $urls[] = $this->storeContentImage($request->file('image'));
        }

        foreach ($request->file('images', []) as $file) {
            $urls[] = $this->storeContentImage($file);
        }

        return response()->json([
            'data' => [
                'url' => $urls[0] ?? null,
                'urls' => $urls,
            ],
        ]);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('posts', 'slug')->ignore($ignoreId),
            ],
            'author' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'content_text' => ['nullable', 'string'],
            'quote' => ['nullable', 'string'],
            'featured_image' => ['nullable', 'string', 'max:2048', 'required_without:featured_image_file'],
            'featured_image_file' => ['nullable', 'image', 'max:6144'],
            'facebook_url' => ['nullable', 'url', 'max:2048'],
            'instagram_url' => ['nullable', 'url', 'max:2048'],
            'twitter_url' => ['nullable', 'url', 'max:2048'],
            'youtube_url' => ['nullable', 'url', 'max:2048'],
            'external_link_url' => ['nullable', 'url', 'max:2048'],
            'external_link_label' => ['nullable', 'string', 'max:255'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'meta_title' => ['nullable', 'string', 'max:120'],
            'meta_description' => ['nullable', 'string'],
            'published_at' => ['nullable', 'date'],
            'categories_text' => ['nullable', 'string'],
            'tags_text' => ['nullable', 'string'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $validated['published_at'] = ! empty($validated['published_at']) ? Carbon::parse($validated['published_at']) : null;
        $validated['content'] = WordPressContent::toRenderableBlocks((string) ($validated['content_text'] ?? ''));
        $validated['categories'] = $this->splitCsv((string) ($validated['categories_text'] ?? ''));
        $validated['tags'] = $this->splitCsv((string) ($validated['tags_text'] ?? ''));
        $validated['is_published'] = $request->boolean('is_published', true);

        if (! Schema::hasColumn('posts', 'is_published') && Schema::hasColumn('posts', 'status')) {
            $validated['status'] = $validated['is_published'] ? 'published' : 'draft';
        }

        unset($validated['content_text'], $validated['categories_text'], $validated['tags_text'], $validated['featured_image_file']);

        return $validated;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function persistableAttributes(array $data): array
    {
        $table = (new Post())->getTable();

        if (Schema::hasColumn($table, 'featured_image_path') && ! Schema::hasColumn($table, 'featured_image')) {
            $data['featured_image_path'] = $data['featured_image'];
            unset($data['featured_image']);
        }

        if (! Schema::hasColumn($table, 'is_published')) {
            unset($data['is_published']);
        }

        if (! Schema::hasColumn($table, 'status')) {
            unset($data['status']);
        }

        if (! Schema::hasColumn($table, 'categories')) {
            unset($data['categories']);
        }

        if (! Schema::hasColumn($table, 'tags')) {
            unset($data['tags']);
        }

        foreach ([
            'facebook_url',
            'instagram_url',
            'twitter_url',
            'youtube_url',
            'external_link_url',
            'external_link_label',
            'source_name',
            'source_url',
            'meta_title',
            'meta_description',
        ] as $column) {
            if (! Schema::hasColumn($table, $column)) {
                unset($data[$column]);
            }
        }

        return $data;
    }

    private function resolveImage(Request $request, ?string $current, ?string $input): string
    {
        if ($request->hasFile('featured_image_file')) {
            $this->deleteUploaded($current);

            return $request->file('featured_image_file')->store('catalog/posts', 'public');
        }

        return trim((string) $input) !== '' ? trim((string) $input) : (string) $current;
    }

    private function deleteUploaded(?string $path): void
    {
        if (! $path || str_starts_with($path, 'assets/') || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    private function storeContentImage(\Illuminate\Http\UploadedFile $file): string
    {
        $path = $file->store('catalog/posts/content-images', 'public');

        return Storage::disk('public')->url($path);
    }

    private function splitCsv(string $text): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $text))));
    }

    /**
     * @param array<int, string> $categories
     * @param array<int, string> $tags
     */
    private function syncTaxonomies(Post $post, array $categories, array $tags): void
    {
        if (! Schema::hasTable('post_taxonomy_post')) {
            return;
        }

        try {
            $taxonomyIds = [];

            foreach ($categories as $name) {
                $taxonomyIds[] = $this->ensureTaxonomy(PostTaxonomy::TYPE_CATEGORY, $name)->id;
            }

            foreach ($tags as $name) {
                $taxonomyIds[] = $this->ensureTaxonomy(PostTaxonomy::TYPE_TAG, $name)->id;
            }

            $post->taxonomies()->sync(array_values(array_unique($taxonomyIds)));
        } catch (\Throwable $exception) {
            Log::warning('No se pudieron sincronizar las taxonomías del post.', [
                'post_id' => $post->id,
                'exception' => $exception,
            ]);
        }
    }

    private function ensureTaxonomy(string $type, string $name): PostTaxonomy
    {
        $name = trim($name);

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

    /**
     * @return array<int, string>
     */
    private function taxonomyNames(Post $post, string $type, string $attribute): array
    {
        if (Schema::hasTable('post_taxonomy_post')) {
            try {
                $names = $post->taxonomies()
                    ->where('type', $type)
                    ->orderBy('name')
                    ->pluck('name')
                    ->all();

                if ($names !== []) {
                    return $names;
                }
            } catch (\Throwable $exception) {
                Log::warning('No se pudieron leer las taxonomías del post.', [
                    'post_id' => $post->id,
                    'type' => $type,
                    'exception' => $exception,
                ]);
            }
        }

        return array_values(array_filter(array_map('strval', $post->{$attribute} ?? [])));
    }

    /**
     * @return array<int, string>
     */
    private function taxonomySuggestions(string $type): array
    {
        if (Schema::hasTable('post_taxonomies')) {
            return PostTaxonomy::query()
                ->where('type', $type)
                ->orderBy('name')
                ->pluck('name')
                ->all();
        }

        $values = Post::query()
            ->pluck($type === PostTaxonomy::TYPE_CATEGORY ? 'categories' : 'tags')
            ->filter()
            ->flatten()
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return array_map('strval', $values);
    }

}
