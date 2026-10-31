<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostTaxonomy;
use App\Services\FileUploadService;
use App\Support\WordPressContent;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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
            'posts' => Post::query()->orderByDesc('published_at')->paginate(15),
        ]);
    }

    public function create(): View
    {
        $latestPost = Post::query()
            ->whereNotNull('author_email')
            ->where('author_email', '!=', '')
            ->orderByDesc('id')
            ->first();

        $post = new Post([
            'published_at' => now(),
            'categories' => [],
            'tags' => [],
            'content' => [],
            'is_published' => true,
            'author_email' => $latestPost?->author_email,
            'notification_sender' => $latestPost?->notification_sender,
            'timezone' => $latestPost?->timezone ?? 'Europe/Madrid',
        ]);

        return view('admin.posts.create', [
            'post' => $post,
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

            // Validar seguridad editorial si el post simulado o manual trae procedencia de correo
            $tempPost = new Post($data);
            if ($tempPost->isFromEmailPipeline()) {
                $this->validateEditorialRestrictions($tempPost, $data);
            }

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
                ->withErrors(['general' => 'No se pudo crear el post: ' . $exception->getMessage()]);
        }
    }

    public function edit(Post $post): View
    {
        if ($post->published_at) {
            $post->published_at = $post->published_at->timezone($post->timezone ?? 'Europe/Madrid');
        }

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
        return DB::transaction(function () use ($request, $post) {
            // Bloqueo pesimista para evitar condiciones de carrera en concurrencia
            $lockedPost = Post::lockForUpdate()->findOrFail($post->id);

            $data = $this->validated($request, $lockedPost->id);
            $categories = $data['categories'];
            $tags = $data['tags'];
            unset($data['categories'], $data['tags']);
            $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
            $data['featured_image'] = $this->resolveImage($request, $lockedPost->featured_image, $data['featured_image'] ?? null);
            $data = $this->persistableAttributes($data);

            // Si el post procede de la ingesta editorial de correo, exigir las compuertas editoriales
            if ($lockedPost->isFromEmailPipeline()) {
                $this->validateEditorialRestrictions($lockedPost, $data);
            }

            $lockedPost->update($data);
            $this->syncTaxonomies($lockedPost, $categories, $tags);

            return redirect()->route('admin.posts.index')->with('status', 'Post updated.');
        });
    }

    /**
     * Aplica a posts de correo las mismas restricciones de seguridad, embargo e incidencias que la bandeja.
     *
     * @param array<string, mixed> $proposedAttributes
     * @throws ValidationException
     */
    protected function validateEditorialRestrictions(Post $post, array $proposedAttributes): void
    {
        $targetStatus = $proposedAttributes['status'] ?? $post->status;
        $targetIsPublished = filter_var($proposedAttributes['is_published'] ?? $post->is_published, FILTER_VALIDATE_BOOLEAN);

        // Si el post ni está publicado ni se propone para publicarse o programarse, no se bloquea como borrador
        $isAttemptingPublishOrSchedule = $targetIsPublished || in_array($targetStatus, ['published', 'scheduled'], true);
        if (!$isAttemptingPublishOrSchedule) {
            return;
        }

        // 1. Incidencias editoriales pendientes de resolución
        if ($post->exists && $post->incidents()->where('resolution_status', 'pending_review')->exists()) {
            throw ValidationException::withMessages([
                'status' => 'No se puede publicar ni programar: tiene incidencias o rectificaciones editoriales pendientes de resolución.',
            ]);
        }

        // 2. Determinar la fecha objetivo de publicación
        // Regla: Publicación inmediata o mantenimiento de publicación actual evalúa con now().
        // Programación evalúa con la fecha futura propuesta y validada.
        $targetEvalDate = now();
        if ($targetStatus === 'scheduled') {
            if (empty($proposedAttributes['published_at'])) {
                throw ValidationException::withMessages([
                    'published_at' => 'Se requiere una fecha y hora futura para programar la publicación.',
                ]);
            }
            $targetEvalDate = $proposedAttributes['published_at'] instanceof Carbon
                ? $proposedAttributes['published_at']
                : Carbon::parse($proposedAttributes['published_at']);
        }

        // 3. Verificación de Estado de Seguridad en Metadata
        $meta = $post->sanitizedMeta;
        if ($meta) {
            // Retenciones técnicas / incidencias no son embargos temporales y no se liberan por fecha
            if (in_array($meta->security_status, ['held_security', 'rejected'], true)) {
                throw ValidationException::withMessages([
                    'status' => "Publicación bloqueada: el contenido se encuentra retenido cautelarmente por seguridad editorial ({$meta->security_status}). Requiere resolución técnica o editorial expresa.",
                ]);
            }

            // Verificación de Embargo Activo
            if ($meta->is_embargo_active) {
                // Embargo sin fecha precisa (indefinido/centinela)
                if ($meta->embargo_until === null) {
                    throw ValidationException::withMessages([
                        'status' => 'Publicación bloqueada: el material está bajo embargo indefinido o sin fecha confirmada. Requiere resolución expresa.',
                    ]);
                }

                if ($targetStatus === 'published' && now()->lt($meta->embargo_until)) {
                    $embargoStr = $meta->embargo_until->timezone(config('app.timezone', 'Europe/Madrid'))->format('d/m/Y H:i');
                    throw ValidationException::withMessages([
                        'status' => "Publicación bloqueada: el material está bajo embargo hasta el {$embargoStr}. Debe programarse para una fecha posterior.",
                    ]);
                }

                if ($targetStatus === 'scheduled' && $targetEvalDate->lt($meta->embargo_until)) {
                    $embargoStr = $meta->embargo_until->timezone(config('app.timezone', 'Europe/Madrid'))->format('d/m/Y H:i');
                    throw ValidationException::withMessages([
                        'published_at' => "La fecha programada no puede ser anterior al levantamiento del embargo ({$embargoStr}).",
                    ]);
                }
            }
        }

        // 4. Validación del estado propuesto en memoria con EmailSafetyValidator
        // Creamos una copia en memoria con los atributos propuestos para validar el contenido FINAL
        $evalPost = clone $post;
        $evalPost->fill($proposedAttributes);

        $safetyValidator = app(\App\Services\EmailSafetyValidator::class);
        $safetyReport = $safetyValidator->validatePost($evalPost, $targetEvalDate);

        if (!$safetyReport['is_safe']) {
            $violations = implode('; ', $safetyReport['violations']);
            throw ValidationException::withMessages([
                'content_text' => "Publicación rechazada por motivos de seguridad editorial: {$violations}",
            ]);
        }
    }
    public function destroy(Post $post): RedirectResponse
    {
        $this->deleteUploaded($post->featured_image);
        $post->delete();

        return redirect()->route('admin.posts.index')->with('status', 'Post deleted.');
    }

    public function retryImage(Post $post, \App\Services\PostImageResolver $resolver): RedirectResponse
    {
        $result = $resolver->retryPostImage($post);

        if ($result['success']) {
            return back()->with('status', $result['message']);
        }

        return back()->withErrors(['image_retry' => $result['message']]);
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
        $slug = $request->filled('slug')
            ? Str::slug($request->input('slug'))
            : Str::slug($request->input('title') ?? '');
        $request->merge(['slug' => $slug]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('posts', 'slug')->ignore($ignoreId),
            ],
            'author' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'content_text' => ['nullable', 'string'],
            'content_blocks' => ['nullable', 'string'],
            'content' => ['nullable'],
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
            'status' => ['nullable', 'string', Rule::in(['draft', 'published', 'scheduled'])],
            'categories_text' => ['nullable', 'string'],
            'tags_text' => ['nullable', 'string'],
            'is_published' => ['nullable', 'boolean'],
            'author_email' => ['nullable', 'email', 'max:255'],
            'notification_sender' => ['nullable', 'email', 'max:255'],
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'en_memoria' => ['nullable', 'boolean'],
            'en_memoria_nombre' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['en_memoria'] = $request->boolean('en_memoria');
        $validated['en_memoria_nombre'] = trim((string) ($validated['en_memoria_nombre'] ?? '')) ?: null;

        $validated['author'] = trim((string) ($validated['author'] ?? '')) !== ''
            ? trim((string) $validated['author'])
            : 'admin';

        $tz = $validated['timezone'] ?? 'Europe/Madrid';
        $validated['published_at'] = ! empty($validated['published_at'])
            ? Carbon::parse($validated['published_at'], $tz)->utc()
            : null;

        $contentSource = $validated['content_text'] ?? $validated['content'] ?? '';

        // If content_blocks JSON is provided (from Alpine editor with links), use it directly
        if (! empty($validated['content_blocks'])) {
            $decoded = json_decode($validated['content_blocks'], true);
            if (is_array($decoded)) {
                $contentSource = $decoded;
            }
        }

        $validated['content'] = WordPressContent::toRenderableBlocks($contentSource);
        $validated['categories'] = $this->splitCsv((string) ($validated['categories_text'] ?? ''));
        $validated['tags'] = $this->splitCsv((string) ($validated['tags_text'] ?? ''));
        $action = $request->input('_action', 'publish');

        switch ($action) {
            case 'schedule':
                $validated['is_published'] = false;
                $validated['status'] = 'scheduled';
                break;
            case 'draft':
                $validated['is_published'] = false;
                $validated['status'] = 'draft';
                break;
            case 'publish':
            default:
                $validated['is_published'] = true;
                $validated['status'] = 'published';
                break;
        }

        unset(
            $validated['content_text'],
            $validated['categories_text'],
            $validated['tags_text'],
            $validated['featured_image_file']
        );

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
            'status',
            'author_email',
            'notification_sender',
            'timezone',
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

            return app(FileUploadService::class)->upload($request->file('featured_image_file'), 'catalog/posts')['url'];
        }

        return trim((string) $input) !== '' ? trim((string) $input) : (string) $current;
    }

    private function deleteUploaded(?string $path): void
    {
        if (! $path || str_starts_with($path, 'assets/') || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        app(FileUploadService::class)->delete($path);
    }

    private function storeContentImage(\Illuminate\Http\UploadedFile $file): string
    {
        $result = app(FileUploadService::class)->upload($file, 'catalog/posts/content-images');

        return $result['url'];
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
