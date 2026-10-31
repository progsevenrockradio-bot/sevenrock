<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewRelease;
use App\Models\RadioArtist;
use App\Services\FileUploadService;
use App\Services\EmailSafetyValidator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewReleaseController extends Controller
{
    public function index(): View
    {
        return view('admin.new-releases.index', [
            'newReleases' => NewRelease::query()
                ->with('radioArtist')
                ->orderByDesc('released_at')
                ->orderBy('title')
                ->get(),
        ]);
    }

    public function create(): View
    {
        $lastRelease = NewRelease::query()
            ->whereNotNull('author_email')
            ->where('author_email', '!=', '')
            ->orderByDesc('id')
            ->first();

        return view('admin.new-releases.create', [
            'newRelease' => new NewRelease([
                'released_at' => now(),
                'is_active' => true,
                'author_email' => $lastRelease?->author_email,
                'notification_sender' => $lastRelease?->notification_sender,
            ]),
            'radioArtists' => RadioArtist::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $slug = ($data['slug'] ?? null) ?: Str::slug($data['title'] . '-' . $data['artist_name']);
        $originalSlug = $slug;
        $counter = 1;
        while (NewRelease::query()->where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        $data['slug'] = $slug;
        
        $data['cover_image'] = $this->resolveFile(
            $request,
            'cover_image_file',
            $data['cover_image'] ?? null,
            null,
            'catalog/releases/covers'
        );

        $data['audio_path'] = $this->resolveFile(
            $request,
            'audio_file',
            $data['audio_path'] ?? null,
            null,
            'catalog/releases/audio'
        );

        $data['origin'] = 'manual';
        NewRelease::query()->create($data);

        return redirect()->route('admin.new-releases.index')->with('status', 'Lanzamiento creado con éxito.');
    }

    public function edit(NewRelease $newRelease): View
    {
        return view('admin.new-releases.edit', [
            'newRelease' => $newRelease,
            'radioArtists' => RadioArtist::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, NewRelease $newRelease): RedirectResponse
    {
        return DB::transaction(function () use ($request, $newRelease) {
            $lockedRelease = NewRelease::lockForUpdate()->findOrFail($newRelease->id);

            $data = $this->validated($request, $lockedRelease->id);
            $slug = ($data['slug'] ?? null) ?: Str::slug($data['title'] . '-' . $data['artist_name']);
            $originalSlug = $slug;
            $counter = 1;
            while (NewRelease::query()->where('slug', $slug)->where('id', '!=', $lockedRelease->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            $data['slug'] = $slug;

            $data['cover_image'] = $this->resolveFile(
                $request,
                'cover_image_file',
                $data['cover_image'] ?? null,
                $lockedRelease->cover_image,
                'catalog/releases/covers'
            );

            $data['audio_path'] = $this->resolveFile(
                $request,
                'audio_file',
                $data['audio_path'] ?? null,
                $lockedRelease->audio_path,
                'catalog/releases/audio'
            );

            // Si el lanzamiento procede de correo y se intenta activar, exigir compuertas de seguridad y embargo
            if ($lockedRelease->isFromEmailPipeline() && ($data['is_active'] ?? false)) {
                $this->validateReleaseEditorialRestrictions($lockedRelease, $data);
            }

            // Preservar estrictamente el origen existente (no sobreescribir 'unknown' ni 'email' a 'manual')
            unset($data['origin']);
            $lockedRelease->update($data);

            return redirect()->route('admin.new-releases.index')->with('status', 'Lanzamiento actualizado con éxito.');
        });
    }

    /**
     * Aplica a lanzamientos procedentes de correo las restricciones de seguridad y embargo editorial.
     *
     * @param array<string, mixed> $proposedAttributes
     * @throws ValidationException
     */
    protected function validateReleaseEditorialRestrictions(NewRelease $release, array $proposedAttributes): void
    {
        $targetDate = !empty($proposedAttributes['released_at'])
            ? ($proposedAttributes['released_at'] instanceof Carbon ? $proposedAttributes['released_at'] : Carbon::parse($proposedAttributes['released_at']))
            : now();

        $description = (string) ($proposedAttributes['description'] ?? $release->description ?? '');
        $title = (string) ($proposedAttributes['title'] ?? $release->title ?? '');

        // 1. Verificación de restricciones persistentes del modelo original si procede de correo
        $isEmbargoActive = (bool) $release->is_embargo_active;
        $embargoUntil = $release->embargo_until;

        // Comprobar también el texto del formulario por si se añade una nueva restricción en la edición
        $embargoDetector = app(\App\Services\ReleaseScheduleManager::class);
        $schedule = $embargoDetector->evaluateSchedule($title, $description, now());

        if ($schedule['is_embargo_active']) {
            $isEmbargoActive = true;
            if ($schedule['embargo_until']) {
                $embargoUntil = $schedule['embargo_until'];
            }
        }

        if ($isEmbargoActive) {
            if ($embargoUntil === null) {
                throw ValidationException::withMessages([
                    'is_active' => 'Activación bloqueada: el lanzamiento está bajo embargo indefinido o sin fecha confirmada. Requiere resolución expresa.',
                ]);
            }

            $embargoCarbon = $embargoUntil instanceof Carbon ? $embargoUntil : Carbon::parse($embargoUntil);
            if (now()->lt($embargoCarbon)) {
                $embargoStr = $embargoCarbon->timezone(config('app.timezone', 'Europe/Madrid'))->format('d/m/Y H:i');
                throw ValidationException::withMessages([
                    'is_active' => "Activación bloqueada: el material está bajo embargo hasta el {$embargoStr}.",
                ]);
            }
        }

        // Si el estado de seguridad persistente tiene retenciones activas
        if (in_array($release->security_status, ['held_security', 'rejected', 'failed_metadata'])) {
            throw ValidationException::withMessages([
                'is_active' => "Activación bloqueada: el lanzamiento tiene una retención técnica o de seguridad activa ({$release->incident_reason}).",
            ]);
        }

        // 2. Validación de seguridad con EmailSafetyValidator sobre el contenido propuesto
        $validator = app(EmailSafetyValidator::class);
        $safetyReport = $validator->validateEditorialSafety([
            'title'   => $title,
            'excerpt' => '',
            'content' => $description,
        ]);

        if (!$safetyReport['is_safe']) {
            $violations = implode('; ', $safetyReport['violations']);
            throw ValidationException::withMessages([
                'description' => "Activación rechazada por motivos de seguridad editorial: {$violations}",
            ]);
        }
    }

    public function destroy(NewRelease $newRelease): RedirectResponse
    {
        $this->deleteUploaded($newRelease->cover_image);
        $this->deleteUploaded($newRelease->audio_path);
        $newRelease->delete();

        return redirect()->route('admin.new-releases.index')->with('status', 'Lanzamiento eliminado con éxito.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('new_releases', 'slug')->ignore($ignoreId),
            ],
            'artist_name' => ['required', 'string', 'max:255'],
            'radio_artist_id' => ['nullable', 'integer', 'exists:radio_artists,id'],
            'released_at' => ['nullable', 'date'],
            'cover_image' => ['nullable', 'string', 'max:2048', 'required_without:cover_image_file'],
            'cover_image_file' => ['nullable', 'image', 'max:6144'],
            'audio_path' => ['nullable', 'string', 'max:2048'],
            'audio_file' => ['nullable', 'file', 'mimes:mp3,wav,ogg,mpga,mpeg', 'max:20480'],
            'youtube_url' => ['nullable', 'url', 'max:2048'],
            'spotify_url' => ['nullable', 'url', 'max:2048'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'author_email' => ['nullable', 'string', 'max:500'],
            'notification_sender' => ['nullable', 'email', 'max:255'],
        ]);

        $validated['released_at'] = ! empty($validated['released_at']) ? Carbon::parse($validated['released_at']) : null;
        $validated['is_active'] = $request->boolean('is_active', true);

        unset($validated['cover_image_file'], $validated['audio_file']);

        return $validated;
    }

    private function resolveFile(Request $request, string $fileKey, ?string $inputPath, ?string $currentPath, string $directory): ?string
    {
        if ($request->hasFile($fileKey)) {
            $this->deleteUploaded($currentPath);

            return app(FileUploadService::class)->upload($request->file($fileKey), $directory)['url'];
        }

        return trim((string) $inputPath) !== '' ? trim((string) $inputPath) : $currentPath;
    }

    private function deleteUploaded(?string $path): void
    {
        if (! $path || str_starts_with($path, 'assets/') || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        app(FileUploadService::class)->delete($path);
    }
}
