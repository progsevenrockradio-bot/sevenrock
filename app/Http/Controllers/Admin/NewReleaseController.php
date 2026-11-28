<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewRelease;
use App\Models\RadioArtist;
use App\Models\ReleaseIntakeSender;
use App\Models\ReleaseIntakeSetting;
use App\Services\EmailSafetyValidator;
use App\Services\FileUploadService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewReleaseController extends Controller
{
    public function index(Request $request): View
    {
        $tab = trim((string) $request->query('tab', 'pending_review'));
        if (! in_array($tab, ['pending_review', 'published', 'rejected'], true)) {
            $tab = 'pending_review';
        }

        $q = trim((string) $request->query('q', ''));
        $artist = trim((string) $request->query('artist', ''));
        $sender = trim((string) $request->query('sender', ''));
        $releaseType = trim((string) $request->query('release_type', ''));
        $dateFrom = $this->parseDateFilter($request->query('date_from'));
        $dateTo = $this->parseDateFilter($request->query('date_to'));

        $counts = [
            'pending_review' => $this->applyTabScope(NewRelease::query(), 'pending_review')->count(),
            'published' => $this->applyTabScope(NewRelease::query(), 'published')->count(),
            'rejected' => $this->applyTabScope(NewRelease::query(), 'rejected')->count(),
        ];

        // Si no se especificó pestaña explícita y no hay pendientes, mostrar Publicados por comodidad
        if (! $request->has('tab') && $counts['pending_review'] === 0 && $counts['published'] > 0) {
            $tab = 'published';
        }

        $query = $this->applyTabScope(
            NewRelease::query()->with(['radioArtist', 'reviewer:id,name']),
            $tab
        );

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function (Builder $sub) use ($like): void {
                $sub->where('title', 'like', $like)
                    ->orWhere('artist_name', 'like', $like)
                    ->orWhere('author_email', 'like', $like)
                    ->orWhere('source_message_id', 'like', $like)
                    ->orWhere('source_subject', 'like', $like);
                if (Schema::hasColumn('new_releases', 'source_sender_email')) {
                    $sub->orWhere('source_sender_email', 'like', $like);
                }
            });
        }

        if ($artist !== '') {
            $query->where('artist_name', 'like', '%' . $artist . '%');
        }

        if ($sender !== '') {
            $likeSender = '%' . $sender . '%';
            $query->where(function (Builder $sub) use ($likeSender): void {
                $sub->where('author_email', 'like', $likeSender);
                if (Schema::hasColumn('new_releases', 'source_sender_email')) {
                    $sub->orWhere('source_sender_email', 'like', $likeSender);
                }
            });
        }

        if ($releaseType !== '' && array_key_exists($releaseType, NewRelease::RELEASE_TYPES) && Schema::hasColumn('new_releases', 'release_type')) {
            $query->where('release_type', $releaseType);
        }

        if ($dateFrom !== null) {
            $query->where(function (Builder $sub) use ($dateFrom): void {
                $sub->whereDate('released_at', '>=', $dateFrom)
                    ->orWhereDate('created_at', '>=', $dateFrom);
                if (Schema::hasColumn('new_releases', 'announced_at')) {
                    $sub->orWhereDate('announced_at', '>=', $dateFrom);
                }
            });
        }

        if ($dateTo !== null) {
            $query->where(function (Builder $sub) use ($dateTo): void {
                $sub->whereDate('released_at', '<=', $dateTo)
                    ->orWhereDate('created_at', '<=', $dateTo);
                if (Schema::hasColumn('new_releases', 'announced_at')) {
                    $sub->orWhereDate('announced_at', '<=', $dateTo);
                }
            });
        }

        if ($tab === 'published') {
            $query->orderByRaw('COALESCE(published_at, created_at) DESC')->orderByDesc('id');
        } elseif ($tab === 'rejected') {
            $query->orderByRaw('COALESCE(reviewed_at, updated_at) DESC')->orderByDesc('id');
        } else {
            $query->orderByRaw('COALESCE(received_at, created_at) DESC')->orderByDesc('id');
        }

        $newReleases = $query->paginate(25)->withQueryString();

        $intakeSettings = [
            'enabled' => ReleaseIntakeSetting::isEnabled(),
            'mailbox_email' => ReleaseIntakeSetting::getValue('mailbox_email', ''),
            'since_date' => ReleaseIntakeSetting::getValue('since_date', ''),
            'use_external_ai' => ReleaseIntakeSetting::useExternalAi(),
        ];

        $intakeSenders = Schema::hasTable('release_intake_senders')
            ? ReleaseIntakeSender::query()->orderByDesc('is_active')->orderBy('entity_name')->get()
            : collect();

        return view('admin.new-releases.index', [
            'newReleases' => $newReleases,
            'tab' => $tab,
            'counts' => $counts,
            'filters' => [
                'q' => $q,
                'artist' => $artist,
                'sender' => $sender,
                'release_type' => $releaseType,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'releaseTypes' => NewRelease::RELEASE_TYPES,
            'intakeSettings' => $intakeSettings,
            'intakeSenders' => $intakeSenders,
            'entityTypes' => ReleaseIntakeSender::ENTITY_TYPES,
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
                'announced_at' => now(),
                'release_type' => 'single',
                'is_active' => true,
                'review_status' => NewRelease::REVIEW_PUBLISHED,
                'author_email' => $lastRelease?->author_email,
                'notification_sender' => $lastRelease?->notification_sender,
            ]),
            'radioArtists' => RadioArtist::query()->orderBy('name')->get(),
            'missingFields' => [],
            'potentialDuplicates' => collect(),
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
        $data['review_status'] = $data['is_active'] ? NewRelease::REVIEW_PUBLISHED : NewRelease::REVIEW_PENDING;
        $data['published_at'] = $data['is_active'] ? now() : null;
        $data['security_status'] = $data['is_active'] ? 'passed' : 'needs_review';
        $data['reviewed_by'] = Auth::id();
        $data['reviewed_at'] = now();

        NewRelease::query()->create($data);

        return redirect()->route('admin.new-releases.index', ['tab' => $data['is_active'] ? 'published' : 'pending_review'])
            ->with('status', 'Lanzamiento creado con éxito.');
    }

    public function edit(NewRelease $newRelease): View
    {
        $newRelease->loadMissing('reviewer:id,name');

        return view('admin.new-releases.edit', [
            'newRelease' => $newRelease,
            'radioArtists' => RadioArtist::query()->orderBy('name')->get(),
            'missingFields' => $newRelease->missingFieldsSummary(),
            'potentialDuplicates' => $newRelease->findPotentialDuplicates(),
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

            if ($data['is_active']) {
                $data['review_status'] = NewRelease::REVIEW_PUBLISHED;
                $data['published_at'] = $lockedRelease->published_at ?? now();
                $data['reviewed_by'] = Auth::id();
                $data['reviewed_at'] = now();
            } elseif ($lockedRelease->effectiveReviewStatus() === NewRelease::REVIEW_PUBLISHED) {
                $data['review_status'] = NewRelease::REVIEW_PENDING;
            }

            // Preservar estrictamente el origen existente (no sobrescribir 'unknown' ni 'email' a 'manual')
            unset($data['origin']);
            $lockedRelease->update($data);

            return redirect()
                ->route('admin.new-releases.index', ['tab' => $lockedRelease->effectiveReviewStatus()])
                ->with('status', 'Lanzamiento actualizado con éxito.');
        });
    }

    /**
     * Aprobación y publicación manual desde la bandeja de revisión.
     * Pasa por validaciones de seguridad editorial, embargo y permisos.
     */
    public function approve(Request $request, NewRelease $newRelease): RedirectResponse
    {
        return DB::transaction(function () use ($newRelease): RedirectResponse {
            /** @var NewRelease $locked */
            $locked = NewRelease::query()->lockForUpdate()->findOrFail($newRelease->id);

            $this->validateReleaseEditorialRestrictions($locked, [
                'title' => $locked->title,
                'description' => $locked->description,
                'released_at' => $locked->released_at,
                'is_embargo_active' => $locked->is_embargo_active,
                'embargo_until' => $locked->embargo_until,
            ], allowClearingNeedsReview: true);

            $locked->forceFill([
                'is_active' => true,
                'show_in_feed' => true,
                'review_status' => NewRelease::REVIEW_PUBLISHED,
                'published_at' => $locked->published_at ?? now(),
                'security_status' => 'passed',
                'incident_reason' => null,
                'rejection_reason' => null,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ])->save();

            return redirect()
                ->route('admin.new-releases.index', ['tab' => 'published'])
                ->with('status', "Lanzamiento «{$locked->title}» aprobado y publicado en SevenRock.");
        });
    }

    /**
     * Rechazo con motivo conservando el registro y su trazabilidad (nunca elimina).
     */
    public function reject(Request $request, NewRelease $newRelease): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:4', 'max:1000'],
        ], [
            'rejection_reason.required' => 'Indica el motivo del rechazo para conservar trazabilidad.',
        ]);

        return DB::transaction(function () use ($newRelease, $validated): RedirectResponse {
            /** @var NewRelease $locked */
            $locked = NewRelease::query()->lockForUpdate()->findOrFail($newRelease->id);

            $locked->forceFill([
                'is_active' => false,
                'review_status' => NewRelease::REVIEW_REJECTED,
                'security_status' => 'rejected',
                'rejection_reason' => trim((string) $validated['rejection_reason']),
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ])->save();

            return redirect()
                ->route('admin.new-releases.index', ['tab' => 'rejected'])
                ->with('status', "Lanzamiento «{$locked->title}» rechazado y conservado en historial.");
        });
    }

    /**
     * Devuelve un lanzamiento publicado o rechazado a la bandeja «Por revisar».
     */
    public function returnToReview(NewRelease $newRelease): RedirectResponse
    {
        return DB::transaction(function () use ($newRelease): RedirectResponse {
            /** @var NewRelease $locked */
            $locked = NewRelease::query()->lockForUpdate()->findOrFail($newRelease->id);

            $locked->forceFill([
                'is_active' => false,
                'review_status' => NewRelease::REVIEW_PENDING,
                'security_status' => $locked->security_status === 'held_security' ? 'held_security' : 'needs_review',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ])->save();

            return redirect()
                ->route('admin.new-releases.index', ['tab' => 'pending_review'])
                ->with('status', "Lanzamiento «{$locked->title}» devuelto a «Por revisar».");
        });
    }

    /**
     * Actualiza la configuración del piloto independiente de recepción por correo.
     */
    public function updateIntakeSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'mailbox_email' => ['nullable', 'email', 'max:255'],
            'since_date' => ['nullable', 'date'],
            'use_external_ai' => ['nullable', 'boolean'],
        ]);

        $enabled = $request->boolean('enabled', false);
        $mailbox = strtolower(trim((string) ($data['mailbox_email'] ?? '')));
        $sinceDate = ! empty($data['since_date']) ? Carbon::parse((string) $data['since_date'])->toDateString() : '';

        if ($enabled && ($mailbox === '' || $sinceDate === '')) {
            return redirect()
                ->route('admin.new-releases.index', ['intake_panel' => 1])
                ->with('error', 'Para activar la recepción piloto debes indicar el buzón receptor y la fecha de inicio de consulta.');
        }

        ReleaseIntakeSetting::setValue('enabled', $enabled ? '1' : '0');
        ReleaseIntakeSetting::setValue('mailbox_email', $mailbox !== '' ? $mailbox : null);
        ReleaseIntakeSetting::setValue('since_date', $sinceDate !== '' ? $sinceDate : null);
        ReleaseIntakeSetting::setValue('use_external_ai', $request->boolean('use_external_ai', false) ? '1' : '0');

        return redirect()
            ->route('admin.new-releases.index', ['intake_panel' => 1])
            ->with('status', 'Configuración de recepción de lanzamientos guardada correctamente.');
    }

    public function storeIntakeSender(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('release_intake_senders', 'email')],
            'entity_name' => ['required', 'string', 'max:255'],
            'entity_type' => ['required', Rule::in(array_keys(ReleaseIntakeSender::ENTITY_TYPES))],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        ReleaseIntakeSender::query()->create([
            'email' => strtolower(trim((string) $data['email'])),
            'entity_name' => trim((string) $data['entity_name']),
            'entity_type' => (string) $data['entity_type'],
            'is_active' => $request->boolean('is_active', true),
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('admin.new-releases.index', ['intake_panel' => 1])
            ->with('status', 'Remitente autorizado añadido a la recepción de lanzamientos.');
    }

    public function toggleIntakeSender(ReleaseIntakeSender $sender): RedirectResponse
    {
        $sender->update(['is_active' => ! $sender->is_active]);

        return redirect()
            ->route('admin.new-releases.index', ['intake_panel' => 1])
            ->with('status', 'Estado del remitente actualizado.');
    }

    public function destroyIntakeSender(ReleaseIntakeSender $sender): RedirectResponse
    {
        $sender->delete();

        return redirect()
            ->route('admin.new-releases.index', ['intake_panel' => 1])
            ->with('status', 'Remitente eliminado de la lista de autorizados.');
    }

    public function approveBroadcast(Request $request, NewRelease $newRelease, \App\Services\NewReleaseBroadcastApprover $approver): RedirectResponse
    {
        $outcome = $approver->approve($newRelease, $request->user()?->id);

        $ok = in_array($outcome['result'], [\App\Services\NewReleaseBroadcastApprover::APPROVED, \App\Services\NewReleaseBroadcastApprover::ALREADY_APPROVED], true);

        return redirect()->route('admin.new-releases.index', ['tab' => $newRelease->effectiveReviewStatus()])
            ->with($ok ? 'status' : 'error', $outcome['message']);
    }

    /**
     * Aplica restricciones de seguridad, embargo y contenido editorial antes de activar/aprobar.
     *
     * @param  array<string, mixed>  $proposedAttributes
     *
     * @throws ValidationException
     */
    protected function validateReleaseEditorialRestrictions(
        NewRelease $release,
        array $proposedAttributes,
        bool $allowClearingNeedsReview = false
    ): void {
        $description = (string) ($proposedAttributes['description'] ?? $release->description ?? '');
        $title = (string) ($proposedAttributes['title'] ?? $release->title ?? '');

        $isEmbargoActive = array_key_exists('is_embargo_active', $proposedAttributes)
            ? (bool) $proposedAttributes['is_embargo_active']
            : (bool) $release->is_embargo_active;
        $embargoUntil = array_key_exists('embargo_until', $proposedAttributes)
            ? $proposedAttributes['embargo_until']
            : $release->embargo_until;

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

            $embargoCarbon = $embargoUntil instanceof Carbon ? $embargoUntil : Carbon::parse((string) $embargoUntil);
            if (now()->lt($embargoCarbon)) {
                $embargoStr = $embargoCarbon->timezone(config('app.timezone', 'Europe/Madrid'))->format('d/m/Y H:i');
                throw ValidationException::withMessages([
                    'is_active' => "Activación bloqueada: el material está bajo embargo hasta el {$embargoStr}.",
                ]);
            }
        }

        // Retenciones de seguridad (spoofing / held_security / failed_metadata siempre bloquean; needs_review solo se levanta por acción explícita approve())
        $blockingHold = $allowClearingNeedsReview
            ? in_array((string) $release->security_status, ['held_security', 'failed_metadata'], true)
            : $release->hasSecurityHold();

        if ($blockingHold) {
            throw ValidationException::withMessages([
                'is_active' => "Activación bloqueada: el lanzamiento tiene una retención técnica o de seguridad activa ({$release->incident_reason}).",
            ]);
        }

        $validator = app(EmailSafetyValidator::class);
        $safetyReport = $validator->validateEditorialSafety([
            'title'   => $title,
            'excerpt' => '',
            'content' => $description,
        ]);

        if (! $safetyReport['is_safe']) {
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

    private function applyTabScope(Builder $query, string $tab): Builder
    {
        $hasReviewStatus = Schema::hasColumn('new_releases', 'review_status');

        if (! $hasReviewStatus) {
            return match ($tab) {
                'published' => $query->where('is_active', true),
                'rejected' => $query->where('security_status', 'rejected'),
                default => $query->where('is_active', false)->where(function (Builder $q): void {
                    $q->whereNull('security_status')->orWhere('security_status', '!=', 'rejected');
                }),
            };
        }

        return match ($tab) {
            'published' => $query->where(function (Builder $q): void {
                $q->where('review_status', NewRelease::REVIEW_PUBLISHED)
                    ->orWhere(function (Builder $legacy): void {
                        $legacy->whereNull('review_status')->where('is_active', true);
                    });
            }),
            'rejected' => $query->where(function (Builder $q): void {
                $q->where('review_status', NewRelease::REVIEW_REJECTED)
                    ->orWhere(function (Builder $legacy): void {
                        $legacy->whereNull('review_status')->where('security_status', 'rejected');
                    });
            }),
            default => $query->where(function (Builder $q): void {
                $q->where('review_status', NewRelease::REVIEW_PENDING)
                    ->orWhere(function (Builder $legacy): void {
                        $legacy->whereNull('review_status')
                            ->where('is_active', false)
                            ->where(function (Builder $sec): void {
                                $sec->whereNull('security_status')->orWhere('security_status', '!=', 'rejected');
                            });
                    });
            }),
        };
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
            'release_type' => ['nullable', Rule::in(array_keys(NewRelease::RELEASE_TYPES))],
            'radio_artist_id' => ['nullable', 'integer', 'exists:radio_artists,id'],
            'released_at' => ['nullable', 'date'],
            'announced_at' => ['nullable', 'date'],
            'cover_image' => ['nullable', 'string', 'max:2048'],
            'cover_image_file' => ['nullable', 'image', 'max:6144'],
            'cover_source' => ['nullable', 'string', 'max:255'],
            'cover_permission_credit' => ['nullable', 'string', 'max:255'],
            'audio_path' => ['nullable', 'string', 'max:2048'],
            'audio_file' => ['nullable', 'file', 'mimes:mp3,wav,ogg,mpga,mpeg', 'max:20480'],
            'youtube_url' => ['nullable', 'url', 'max:2048'],
            'spotify_url' => ['nullable', 'url', 'max:2048'],
            'official_url' => ['nullable', 'url', 'max:1000'],
            'download_url' => ['nullable', 'url', 'max:1000'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'is_embargo_active' => ['nullable', 'boolean'],
            'embargo_until' => ['nullable', 'date'],
            'usage_conditions' => ['nullable', 'string', 'max:2000'],
            'author_email' => ['nullable', 'string', 'max:500'],
            'notification_sender' => ['nullable', 'email', 'max:255'],
        ]);

        $validated['released_at'] = ! empty($validated['released_at']) ? Carbon::parse((string) $validated['released_at']) : null;
        $validated['announced_at'] = ! empty($validated['announced_at']) ? Carbon::parse((string) $validated['announced_at']) : null;
        $validated['embargo_until'] = ! empty($validated['embargo_until']) ? Carbon::parse((string) $validated['embargo_until']) : null;
        $validated['is_active'] = $request->boolean('is_active', false);
        $validated['is_embargo_active'] = $request->boolean('is_embargo_active', false);

        unset($validated['cover_image_file'], $validated['audio_file']);

        return $validated;
    }

    private function parseDateFilter(mixed $raw): ?string
    {
        $val = trim((string) ($raw ?? ''));
        if ($val === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $val)->toDateString();
        } catch (\Throwable) {
            return null;
        }
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
