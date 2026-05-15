<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $data['featured_image'] = $this->resolveImage($request, null, $data['featured_image'] ?? null);

        Post::query()->create($data);

        return redirect()->route('admin.posts.index')->with('status', 'Post created.');
    }

    public function edit(Post $post): View
    {
        return view('admin.posts.edit', [
            'post' => $post,
            'contentText' => $this->linesToText($post->content ?? []),
            'categoriesText' => implode(', ', $post->categories ?? []),
            'tagsText' => implode(', ', $post->tags ?? []),
        ]);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $data = $this->validated($request, $post->id);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $data['featured_image'] = $this->resolveImage($request, $post->featured_image, $data['featured_image'] ?? null);

        $post->update($data);

        return redirect()->route('admin.posts.index')->with('status', 'Post updated.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $this->deleteUploaded($post->featured_image);
        $post->delete();

        return redirect()->route('admin.posts.index')->with('status', 'Post deleted.');
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
            'published_at' => ['nullable', 'date'],
            'categories_text' => ['nullable', 'string'],
            'tags_text' => ['nullable', 'string'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $validated['published_at'] = ! empty($validated['published_at']) ? Carbon::parse($validated['published_at']) : null;
        $validated['content'] = $this->splitLines((string) ($validated['content_text'] ?? ''));
        $validated['categories'] = $this->splitCsv((string) ($validated['categories_text'] ?? ''));
        $validated['tags'] = $this->splitCsv((string) ($validated['tags_text'] ?? ''));
        $validated['is_published'] = $request->boolean('is_published', true);

        unset($validated['content_text'], $validated['categories_text'], $validated['tags_text'], $validated['featured_image_file']);

        return $validated;
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

    /**
     * @return array<int, string>
     */
    private function splitLines(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', trim($text)) ?: [])));
    }

    /**
     * @return array<int, string>
     */
    private function splitCsv(string $text): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $text))));
    }

    /**
     * @param array<int, string> $lines
     */
    private function linesToText(array $lines): string
    {
        return implode("\n", array_map('strval', $lines));
    }
}
