<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMp3Job;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\PodcastEditorialReviewService;
use App\Services\PodcastPipelineAuditService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminPodcastEditorialController extends Controller
{
    public function __construct(
        protected PodcastEditorialReviewService $editorialService,
        protected PodcastPipelineAuditService $auditService,
        protected FileUploadService $fileUploadService,
    ) {
    }

    /**
     * Pantalla de confirmación (GET): NUNCA aprueba automáticamente al abrir el enlace del correo.
     * Exige enlace firmado vigente y administrador autenticado.
     */
    public function confirmEmailApproval(Request $request, RadioProgram $radioProgram): View|Response
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'El enlace de aprobación ha caducado o no es válido.');
        }

        $this->requireAuthenticatedAdmin($request);

        $requestedVersion = (int) $request->query('version', 0);
        $requestedToken = trim((string) $request->query('vtoken', ''));
        $currentVersion = (int) ($radioProgram->editorial_version ?: 1);
        $currentToken = trim((string) ($radioProgram->editorial_version_token ?? ''));

        $radioProgram->loadMissing('masterProgram');

        if ($requestedVersion <= 0 || $requestedVersion !== $currentVersion || $requestedToken === '' || ! hash_equals($currentToken, $requestedToken)) {
            return response()->view('admin.podcast-uploads.editorial-email-confirm', [
                'episode' => $radioProgram,
                'radioProgram' => $radioProgram,
                'state' => 'superseded',
                'versionMatches' => false,
                'alreadyApprovedForThisVersion' => false,
                'requestedVersion' => $requestedVersion,
                'requestedToken' => $requestedToken,
                'currentVersion' => $currentVersion,
                'postApproveUrl' => null,
                'postActionUrl' => null,
            ], 409);
        }

        if ($radioProgram->editorial_status === RadioProgram::EDITORIAL_STATUS_APPROVED && (int) $radioProgram->editorial_approved_version === $currentVersion) {
            return response()->view('admin.podcast-uploads.editorial-email-confirm', [
                'episode' => $radioProgram,
                'radioProgram' => $radioProgram,
                'state' => 'already_approved',
                'versionMatches' => true,
                'alreadyApprovedForThisVersion' => true,
                'requestedVersion' => $requestedVersion,
                'requestedToken' => $requestedToken,
                'currentVersion' => $currentVersion,
                'postApproveUrl' => null,
                'postActionUrl' => null,
            ], 200);
        }

        $postApproveUrl = URL::temporarySignedRoute(
            'podcast-editorial.email.approve',
            now()->addMinutes(30),
            [
                'radioProgram' => $radioProgram->id,
                'version' => $currentVersion,
                'vtoken' => $currentToken,
            ]
        );

        return view('admin.podcast-uploads.editorial-email-confirm', [
            'episode' => $radioProgram,
            'radioProgram' => $radioProgram,
            'state' => 'ready',
            'versionMatches' => true,
            'alreadyApprovedForThisVersion' => false,
            'requestedVersion' => $requestedVersion,
            'requestedToken' => $currentToken,
            'currentVersion' => $currentVersion,
            'vtoken' => $currentToken,
            'postApproveUrl' => $postApproveUrl,
            'postActionUrl' => $postApproveUrl,
        ]);
    }

    /**
     * Acción POST protegida de aprobación desde correo para una versión concreta.
     * Exige enlace firmado vigente, administrador autenticado y coincidencia de versión y token.
     */
    public function approveFromEmail(Request $request, RadioProgram $radioProgram): Response|JsonResponse|RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'El enlace de aprobación ha caducado o no es válido.');
        }

        $adminUser = $this->requireAuthenticatedAdmin($request);

        $requestedVersion = (int) $request->input('version', $request->query('version', 0));
        $requestedToken = trim((string) $request->input('vtoken', $request->query('vtoken', '')));

        return DB::transaction(function () use ($request, $radioProgram, $requestedVersion, $requestedToken, $adminUser) {
            /** @var RadioProgram $locked */
            $locked = RadioProgram::query()->with('masterProgram')->lockForUpdate()->findOrFail($radioProgram->id);
            $currentVersion = (int) ($locked->editorial_version ?: 1);
            $currentToken = trim((string) ($locked->editorial_version_token ?? ''));

            if ($requestedVersion <= 0 || $requestedVersion !== $currentVersion || $requestedToken === '' || ! hash_equals($currentToken, $requestedToken)) {
                $message = sprintf(
                    'La versión v%d ya fue sustituida por una modificación posterior (versión actual v%d).',
                    $requestedVersion,
                    $currentVersion
                );

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'code' => 'version_superseded',
                        'message' => $message,
                    ], 409);
                }

                return response()->view('admin.podcast-uploads.editorial-email-confirm', [
                    'episode' => $locked,
                    'radioProgram' => $locked,
                    'state' => 'superseded',
                    'versionMatches' => false,
                    'alreadyApprovedForThisVersion' => false,
                    'requestedVersion' => $requestedVersion,
                    'requestedToken' => $requestedToken,
                    'currentVersion' => $currentVersion,
                    'message' => $message,
                    'postApproveUrl' => null,
                    'postActionUrl' => null,
                ], 409);
            }

            if ($locked->editorial_status === RadioProgram::EDITORIAL_STATUS_APPROVED && (int) $locked->editorial_approved_version === $currentVersion) {
                $message = sprintf('La versión v%d de este episodio ya fue aprobada anteriormente.', $currentVersion);

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'code' => 'already_approved',
                        'message' => $message,
                    ], 409);
                }

                return response()->view('admin.podcast-uploads.editorial-email-confirm', [
                    'episode' => $locked,
                    'radioProgram' => $locked,
                    'state' => 'already_approved',
                    'versionMatches' => true,
                    'alreadyApprovedForThisVersion' => true,
                    'requestedVersion' => $requestedVersion,
                    'requestedToken' => $requestedToken,
                    'currentVersion' => $currentVersion,
                    'message' => $message,
                    'postApproveUrl' => null,
                    'postActionUrl' => null,
                ], 409);
            }

            $this->executeEditorialApproval($locked, $adminUser, 'email_signed_post');

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'code' => 'approved',
                    'version' => $currentVersion,
                    'approved_by' => (int) $adminUser->id,
                ]);
            }

            $confirmRedirectUrl = URL::temporarySignedRoute(
                'podcast-editorial.email.confirm',
                now()->addMinutes(30),
                [
                    'radioProgram' => $locked->id,
                    'version' => $currentVersion,
                    'vtoken' => $currentToken,
                ]
            );

            return redirect()->to($confirmRedirectUrl)->with('status', sprintf('Episodio aprobado editorialmente (versión v%d).', $currentVersion));
        });
    }

    /**
     * Vista de revisión y edición editorial en el panel de administración.
     */
    public function show(RadioProgram $radioProgram): View
    {
        $radioProgram->loadMissing(['masterProgram', 'submittedBy']);
        $master = $radioProgram->masterProgram;
        $checks = is_array($radioProgram->editorial_checks) ? $radioProgram->editorial_checks : [];

        return view('admin.podcast-uploads.editorial-review', [
            'episode' => $radioProgram,
            'radioProgram' => $radioProgram,
            'masterProgram' => $master,
            'editorialGuide' => $master?->effectiveEditorialGuide() ?? \App\Models\ThemeSetting::currentPodcastEditorialGuide(),
            'originalPayload' => data_get($radioProgram->editorial_original_payload, 'current', $radioProgram->editorial_original_payload ?? []),
            'aiProposal' => is_array($radioProgram->editorial_ai_proposal) ? $radioProgram->editorial_ai_proposal : null,
            'checks' => $checks,
            'evaluation' => $checks,
            'operationalStates' => $radioProgram->operationalStates(),
        ]);
    }

    /**
     * Edición manual de textos, portada o sustitución controlada de audio por el administrador.
     */
    public function update(Request $request, RadioProgram $radioProgram): RedirectResponse
    {
        $radioProgram->loadMissing('masterProgram');
        $master = $radioProgram->masterProgram ?? MasterProgram::query()->findOrFail($radioProgram->master_program_id);

        $hasNewCoverFile = $request->hasFile('imagen_episodio_file') || $request->hasFile('imagen_portada');
        $incomingCoverUrl = trim((string) $request->input('imagen_episodio_url', ''));
        $coverChanged = $hasNewCoverFile || ($request->has('imagen_episodio_url') && $incomingCoverUrl !== '' && $incomingCoverUrl !== trim((string) $radioProgram->imagen_episodio));

        $hasNewAudio = $request->hasFile('archivo_mp3')
            || $request->filled('r2_key')
            || $request->boolean('apply_staged_audio');

        $hadPreparedAudio = $radioProgram->hasPreparedBroadcastAudio();

        $data = $request->validate([
            'numero_episodio' => ['nullable', 'integer', 'min:1'],
            'fecha_emision' => ['nullable', 'date'],
            'live_title' => ['required', 'string', 'max:255'],
            'editorial_summary' => ['nullable', 'string', 'max:1000'],
            'resena' => ['nullable', 'string'],
            'biografia_invitado' => ['nullable', 'string', 'max:500'],
            'imagen_episodio_url' => ['nullable', 'url', 'max:255'],
            'imagen_episodio_file' => ['nullable', 'file', 'image', 'max:10240'],
            'imagen_portada' => ['nullable', 'file', 'image', 'max:10240'],
            'cover_update_scope' => [
                Rule::requiredIf($coverChanged),
                'nullable',
                Rule::in(['sheet_only', 'reembed_and_redistribute']),
            ],
            'archivo_mp3' => ['nullable', 'file', 'max:512000'],
            'r2_key' => ['nullable', 'string', 'max:255'],
            'apply_staged_audio' => ['nullable', 'boolean'],
            'confirm_replace_prepared_audio' => ['nullable', 'boolean'],
            'schedule_inclusion_status' => ['nullable', Rule::in(['not_scheduled', 'pending_schedule', 'scheduled_verified', 'scheduled_in_radioboss', 'manual_schedule_required'])],
        ], [
            'cover_update_scope.required' => 'Al cambiar la portada debes indicar si solo modifica la ficha (sheet_only) o si también requiere actualizar la portada incrustada y redistribuir el MP3 (reembed_and_redistribute).',
        ]);

        $coverScope = (string) ($data['cover_update_scope'] ?? 'sheet_only');
        $requiresAudioReplacementOrReembed = $hasNewAudio || ($coverChanged && $coverScope === 'reembed_and_redistribute');

        if (($hasNewCoverFile || $hasNewAudio) && ! $this->fileUploadService->isR2Configured()) {
            return back()->withInput()->withErrors([
                'archivo_mp3' => 'El almacenamiento en Cloudflare R2 no está disponible. No se guardaron archivos nuevos en el disco local.',
            ]);
        }

        if ($hadPreparedAudio && $requiresAudioReplacementOrReembed && ! $request->boolean('confirm_replace_prepared_audio')) {
            return back()->withInput()->withErrors([
                'confirm_replace_prepared_audio' => 'Este episodio ya tiene un audio preparado para emisión. Debes confirmar explícitamente su sustitución o re-etiquetado antes de reemplazarlo.',
            ]);
        }

        // Asegurar que el texto original del conductor permanezca guardado antes de aplicar cambios del administrador
        if (empty($radioProgram->editorial_original_payload)) {
            $radioProgram->editorial_original_payload = $this->editorialService->buildOriginalPayloadSnapshot($radioProgram, 'preserved_before_admin_edit');
        }

        $storageFolder = $radioProgram->storageFolderSlug();
        $metadata = (array) ($radioProgram->delivery_metadata ?? []);

        if (isset($data['numero_episodio']) && $data['numero_episodio'] !== null) {
            $radioProgram->numero_episodio = (int) $data['numero_episodio'];
        }
        if (! empty($data['fecha_emision'])) {
            $radioProgram->fecha_emision = Carbon::parse((string) $data['fecha_emision'])->toDateString();
        }
        $radioProgram->live_title = trim((string) $data['live_title']);
        if (array_key_exists('editorial_summary', $data)) {
            $radioProgram->editorial_summary = trim((string) ($data['editorial_summary'] ?? '')) ?: null;
        }
        if (array_key_exists('resena', $data)) {
            $resena = trim((string) ($data['resena'] ?? '')) ?: null;
            $radioProgram->resena = $resena;
            $radioProgram->live_description = $resena;
            $radioProgram->comentario_episodio = $resena;
        }
        if (array_key_exists('biografia_invitado', $data)) {
            $radioProgram->biografia_invitado = trim((string) ($data['biografia_invitado'] ?? '')) ?: null;
        }
        if (! empty($data['schedule_inclusion_status'])) {
            $radioProgram->schedule_inclusion_status = (string) $data['schedule_inclusion_status'];
        }

        $shouldReprocessMp3 = false;
        $sourcePathForReprocess = (string) $radioProgram->archivo_mp3;

        if ($coverChanged) {
            if ($hasNewCoverFile) {
                /** @var UploadedFile $file */
                $file = $request->file('imagen_episodio_file') ?: $request->file('imagen_portada');
                $ext = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg'));
                $name = sprintf('%s-cover-%s.%s', now()->format('Y-m-d'), Str::lower(Str::uuid()->toString()), $ext);
                $stored = Storage::disk('r2')->putFileAs('podcast-inbox/covers/' . $storageFolder, $file, $name);
                if (is_string($stored) && $stored !== '') {
                    $radioProgram->imagen_episodio = trim($stored, '/');
                }
            } elseif ($incomingCoverUrl !== '') {
                $radioProgram->imagen_episodio = $incomingCoverUrl;
            }

            $metadata['last_cover_update_scope'] = $coverScope;
            if ($coverScope === 'sheet_only') {
                $metadata['cover_embedded_in_mp3_outdated'] = true;
            } elseif ($coverScope === 'reembed_and_redistribute') {
                $metadata['cover_embedded_in_mp3_outdated'] = false;
                $shouldReprocessMp3 = true;
            }
        }

        if ($hasNewAudio) {
            if ($request->boolean('apply_staged_audio') && ! empty($metadata['staged_replacement_mp3_path'])) {
                $sourcePathForReprocess = (string) $metadata['staged_replacement_mp3_path'];
                $radioProgram->archivo_mp3 = $sourcePathForReprocess;
                $radioProgram->archivo_mp3_disk = (string) ($metadata['staged_replacement_mp3_disk'] ?? 'r2');
                unset($metadata['staged_replacement_mp3_path'], $metadata['staged_replacement_mp3_disk']);
            } elseif ($request->filled('r2_key')) {
                $sourcePathForReprocess = ltrim(str_replace('\\', '/', trim((string) $request->input('r2_key'))), '/');
                $radioProgram->archivo_mp3 = $sourcePathForReprocess;
                $radioProgram->archivo_mp3_disk = 'r2';
            } elseif ($request->hasFile('archivo_mp3')) {
                /** @var UploadedFile $audioFile */
                $audioFile = $request->file('archivo_mp3');
                $fileName = sprintf('%s-admin-replace-%s.mp3', now()->format('Y-m-d'), Str::lower(Str::uuid()->toString()));
                $storedAudio = Storage::disk('r2')->putFileAs('podcast-inbox/' . $storageFolder, $audioFile, $fileName);
                if (is_string($storedAudio) && $storedAudio !== '') {
                    $sourcePathForReprocess = trim($storedAudio, '/');
                    $radioProgram->archivo_mp3 = $sourcePathForReprocess;
                    $radioProgram->archivo_mp3_disk = 'r2';
                }
            }
            $shouldReprocessMp3 = true;
        }

        $radioProgram->delivery_metadata = $metadata;
        $radioProgram->editorial_checks = $this->editorialService->evaluateSubmission($radioProgram, $master);
        $radioProgram->save();

        $this->auditService->record($radioProgram, 'ADMIN_EDITORIAL_UPDATED', 'El administrador actualizó los datos editoriales del episodio.', [
            'admin_id' => Auth::id(),
            'cover_changed' => $coverChanged,
            'cover_update_scope' => $coverChanged ? $coverScope : null,
            'audio_reprocessed' => $shouldReprocessMp3 && $radioProgram->isEditorialApproved(),
        ]);

        if ($shouldReprocessMp3 && $radioProgram->isEditorialApproved() && $sourcePathForReprocess !== '') {
            ProcessMp3Job::dispatch(
                $radioProgram->fresh(['masterProgram']) ?? $radioProgram,
                $sourcePathForReprocess,
                false,
                Auth::id(),
                Auth::user()?->name,
                Auth::user()?->email,
                true,
            );
        }

        return redirect()
            ->route('admin.podcast-uploads.editorial.show', $radioProgram)
            ->with('status', 'Cambios editoriales guardados correctamente.');
    }

    /**
     * Solicita a la IA una propuesta basada en la guía editorial del programa.
     */
    public function requestAiProposal(Request $request, RadioProgram $radioProgram): RedirectResponse|JsonResponse
    {
        $adminNotes = trim((string) $request->input('admin_notes', '')) ?: null;

        if (empty($radioProgram->editorial_original_payload)) {
            $radioProgram->editorial_original_payload = $this->editorialService->buildOriginalPayloadSnapshot($radioProgram, 'preserved_before_ai_proposal');
            $radioProgram->saveQuietly();
        }

        $proposal = $this->editorialService->generateAiProposal($radioProgram, $adminNotes);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'proposal' => $proposal,
            ]);
        }

        return redirect()
            ->route('admin.podcast-uploads.editorial.show', $radioProgram)
            ->with('status', 'Propuesta de IA generada según la guía del programa. Compárala con el original antes de decidir.');
    }

    /**
     * Permite aceptar, modificar o descartar la propuesta de la IA antes de aprobar.
     */
    public function decideProposal(Request $request, RadioProgram $radioProgram): RedirectResponse|JsonResponse
    {
        return $this->decideAiProposal($request, $radioProgram);
    }

    public function decideAiProposal(Request $request, RadioProgram $radioProgram): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'modify', 'discard'])],
            'live_title' => ['nullable', 'string', 'max:255'],
            'editorial_summary' => ['nullable', 'string', 'max:1000'],
            'resena' => ['nullable', 'string'],
        ]);

        $updated = $this->editorialService->applyProposalDecision(
            $radioProgram,
            (string) $data['decision'],
            $data,
            Auth::id()
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'decision' => $data['decision'],
                'episode' => [
                    'live_title' => $updated->live_title,
                    'editorial_summary' => $updated->editorial_summary,
                    'resena' => $updated->resena,
                ],
            ]);
        }

        return redirect()
            ->route('admin.podcast-uploads.editorial.show', $radioProgram)
            ->with('status', 'Decisión sobre la propuesta de IA aplicada correctamente.');
    }

    /**
     * Aprobación desde el panel para una versión concreta.
     * No publica en redes sociales (generar borrador y publicar son acciones separadas).
     */
    public function approve(Request $request, RadioProgram $radioProgram): RedirectResponse|JsonResponse
    {
        $adminUser = $this->requireAuthenticatedAdmin($request);
        $requestedVersion = (int) $request->input('version', $radioProgram->editorial_version ?: 1);

        return DB::transaction(function () use ($request, $radioProgram, $requestedVersion, $adminUser) {
            /** @var RadioProgram $locked */
            $locked = RadioProgram::query()->with('masterProgram')->lockForUpdate()->findOrFail($radioProgram->id);
            $currentVersion = (int) ($locked->editorial_version ?: 1);

            if ($requestedVersion !== $currentVersion) {
                $msg = sprintf('La versión v%d fue sustituida por la versión v%d. Revisa los nuevos cambios antes de aprobar.', $requestedVersion, $currentVersion);

                return $request->expectsJson()
                    ? response()->json(['success' => false, 'code' => 'version_superseded', 'message' => $msg], 409)
                    : back()->withErrors(['version' => $msg]);
            }

            if ($locked->editorial_status === RadioProgram::EDITORIAL_STATUS_APPROVED && (int) $locked->editorial_approved_version === $currentVersion) {
                $msg = sprintf('La versión v%d ya fue aprobada anteriormente.', $currentVersion);

                return $request->expectsJson()
                    ? response()->json(['success' => false, 'code' => 'already_approved', 'message' => $msg], 409)
                    : back()->withErrors(['version' => $msg]);
            }

            $this->executeEditorialApproval($locked, $adminUser, 'admin_panel');

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'version' => $currentVersion,
                    'editorial_status' => $locked->editorial_status,
                    'approved_by' => (int) $adminUser->id,
                ]);
            }

            return redirect()
                ->route('admin.podcast-uploads.editorial.show', $locked)
                ->with('status', sprintf('Episodio aprobado editorialmente (versión v%d).', $currentVersion));
        });
    }

    /**
     * Acción separada para generar borrador de redes sociales sin publicar.
     */
    public function generateSocialDraft(RadioProgram $radioProgram): RedirectResponse
    {
        $radioProgram->loadMissing('masterProgram');
        $programName = (string) ($radioProgram->titulo_programa ?: $radioProgram->masterProgram?->nombre ?: 'Seven Rock Radio');
        $metadata = (array) ($radioProgram->delivery_metadata ?? []);

        $metadata['social_draft'] = [
            'headline' => sprintf('🎸 %s #%d: %s', $programName, (int) $radioProgram->numero_episodio, (string) $radioProgram->live_title),
            'copy' => trim((string) ($radioProgram->editorial_summary ?: $radioProgram->resena ?: '')),
            'status' => 'draft_only',
            'published' => false,
            'generated_at' => now()->toIso8601String(),
        ];
        $metadata['social_published'] = false;

        $radioProgram->forceFill(['delivery_metadata' => $metadata])->saveQuietly();

        return redirect()
            ->route('admin.podcast-uploads.editorial.show', $radioProgram)
            ->with('status', 'Borrador para redes generado (no publicado).');
    }

    /**
     * Autoriza expresamente una excepción de entrega tardía para un episodio específico.
     * La excepción NO aprueba, publica ni envía automáticamente el episodio.
     */
    public function authorizeLateSubmission(Request $request, RadioProgram $radioProgram): RedirectResponse
    {
        $adminUser = $this->requireAuthenticatedAdmin($request);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Debes indicar el motivo por el cual se autoriza la entrega fuera de plazo.',
            'reason.min' => 'El motivo debe tener al menos 5 caracteres.',
        ]);

        $reason = trim((string) $data['reason']);
        $now = now();

        $radioProgram->forceFill([
            'late_submission_authorized_by' => (int) $adminUser->id,
            'late_submission_authorized_at' => $now,
            'late_submission_reason' => $reason,
        ])->save();

        $this->auditService->record(
            $radioProgram,
            'LATE_SUBMISSION_AUTHORIZED',
            'Se autorizó una excepción administrativa de entrega tardía.',
            [
                'authorized_by' => (int) $adminUser->id,
                'authorized_by_name' => (string) $adminUser->name,
                'authorized_at' => $now->toIso8601String(),
                'reason' => $reason,
            ]
        );

        return redirect()
            ->route('admin.podcast-uploads.editorial.show', $radioProgram)
            ->with('status', 'Excepción de entrega tardía autorizada correctamente. El conductor ahora puede entregar fuera de plazo.');
    }

    /**
     * Activa o desactiva el permiso de descarga pública para la ficha compartida.
     */
    public function togglePublicDownload(Request $request, RadioProgram $radioProgram): RedirectResponse
    {
        $adminUser = $this->requireAuthenticatedAdmin($request);

        $allowed = $request->has('allow_public_download')
            ? $request->boolean('allow_public_download')
            : ! $radioProgram->allow_public_download;

        $radioProgram->forceFill([
            'allow_public_download' => $allowed,
        ])->save();

        $this->auditService->record(
            $radioProgram,
            'PUBLIC_DOWNLOAD_TOGGLED',
            $allowed ? 'Se activó el permiso de descarga pública.' : 'Se desactivó el permiso de descarga pública.',
            [
                'allow_public_download' => $allowed,
                'toggled_by' => (int) $adminUser->id,
            ]
        );

        $msg = $allowed
            ? 'Descarga pública activada para la ficha del episodio.'
            : 'Descarga pública desactivada para la ficha del episodio.';

        return redirect()
            ->route('admin.podcast-uploads.editorial.show', $radioProgram)
            ->with('status', $msg);
    }

    private function requireAuthenticatedAdmin(Request $request): User
    {
        $user = $request->user() ?: Auth::user();

        if (! $user instanceof User || ! $user->hasAdminAccess()) {
            abort(403, 'Se requiere una sesión de administrador autorizado para revisar o aprobar episodios.');
        }

        return $user;
    }

    private function executeEditorialApproval(RadioProgram $episode, User $adminUser, string $channel): void
    {
        $approvedBy = (int) $adminUser->id;
        $currentVersion = max(1, (int) ($episode->editorial_version ?: 1));
        $metadata = (array) ($episode->delivery_metadata ?? []);
        $metadata['social_published'] = false;
        $metadata['last_approval_channel'] = $channel;
        $metadata['approved_by_user_id'] = $approvedBy;
        $metadata['approved_by_name'] = (string) $adminUser->name;
        $metadata['approved_by_email'] = (string) $adminUser->email;

        $needsAudioProcessing = ! $episode->hasPreparedBroadcastAudio();

        $episode->forceFill([
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
            'editorial_approved_at' => now(),
            'editorial_approved_by' => $approvedBy,
            'editorial_approved_version' => $currentVersion,
            'radioboss_status' => $needsAudioProcessing ? 'radioboss_pending' : $episode->radioboss_status,
            'archive_org_status' => $needsAudioProcessing ? 'archive_pending' : $episode->archive_org_status,
            'delivery_status' => $needsAudioProcessing ? 'delivery_pending' : $episode->delivery_status,
            'processing_started_at' => $needsAudioProcessing ? ($episode->processing_started_at ?? now()) : $episode->processing_started_at,
            'status_message' => $needsAudioProcessing
                ? sprintf('Aprobado editorialmente (v%d). Procesando audio en R2.', $currentVersion)
                : sprintf('Aprobado editorialmente (v%d). Audio existente conservado.', $currentVersion),
            'delivery_metadata' => $metadata,
        ])->save();

        $this->auditService->record($episode, 'EDITORIAL_APPROVED', 'El episodio fue aprobado editorialmente.', [
            'version' => $currentVersion,
            'approved_by' => $approvedBy,
            'approved_by_name' => (string) $adminUser->name,
            'approved_by_email' => (string) $adminUser->email,
            'channel' => $channel,
            'dispatched_audio_pipeline' => $needsAudioProcessing,
            'social_published' => false,
        ]);

        if ($needsAudioProcessing && filled($episode->archivo_mp3)) {
            ProcessMp3Job::dispatch(
                $episode->fresh(['masterProgram']) ?? $episode,
                (string) $episode->archivo_mp3,
                false,
                $approvedBy,
                (string) $adminUser->name,
                (string) $adminUser->email,
                true,
            );
        }
    }
}
