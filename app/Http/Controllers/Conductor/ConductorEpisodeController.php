<?php

declare(strict_types=1);

namespace App\Http\Controllers\Conductor;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMp3Job;
use App\Mail\PodcastEditorialReviewMail;
use App\Models\ConductorUploadAuthorization;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\PodcastEditorialReviewService;
use App\Services\PodcastPipelineAuditService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConductorEpisodeController extends Controller
{
    private const MAX_AUDIO_SIZE_BYTES = 524288000; // 500 MB
    private const MAX_COVER_SIZE_BYTES = 10485760;  // 10 MB

    public function __construct(
        protected PodcastEditorialReviewService $editorialService,
        protected PodcastPipelineAuditService $auditService,
        protected FileUploadService $fileUploadService,
    ) {
    }

    public function showLogin(): View
    {
        return view('conductor.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Credenciales inválidas.',
            ])->onlyInput('email');
        }

        /** @var User|null $user */
        $user = Auth::user();
        if (! $user || ! $user->hasConductorAccess()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Esta cuenta no tiene programas autorizados como conductor.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->route('conductor.episodes.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('conductor.login');
    }

    public function index(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $authorizedPrograms = $user->authorizedMasterPrograms()->orderBy('nombre')->get();
        $authorizedIds = $authorizedPrograms->pluck('id')->map(static fn ($id) => (int) $id)->all();

        $episodes = RadioProgram::query()
            ->with('masterProgram')
            ->where('submitted_by_user_id', $user->id)
            ->whereIn('master_program_id', $authorizedIds !== [] ? $authorizedIds : [-1])
            ->orderByDesc('id')
            ->paginate(15);

        return view('conductor.episodes.index', [
            'user' => $user,
            'conductor' => $user,
            'authorizedPrograms' => $authorizedPrograms,
            'episodes' => $episodes,
        ]);
    }

    /**
     * Emite una autorización de subida directa a R2 vinculada al conductor, programa,
     * clave exacta, caducidad y estado de consumo.
     */
    public function presign(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $data = $request->validate([
            'master_program_id' => ['required', 'integer', 'exists:master_programs,id'],
            'radio_program_id' => ['nullable', 'integer', 'exists:radio_programs,id'],
            'filename' => ['required', 'string', 'max:255'],
            'contentType' => ['required', 'string', Rule::in(['audio/mpeg', 'audio/mp3'])],
            'size' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_AUDIO_SIZE_BYTES],
        ]);

        $master = MasterProgram::query()->findOrFail((int) $data['master_program_id']);
        if (! $user->isAuthorizedForMasterProgram($master)) {
            abort(403, 'No tienes autorización para subir episodios en este programa.');
        }

        $radioProgramId = isset($data['radio_program_id']) ? (int) $data['radio_program_id'] : null;
        if ($radioProgramId !== null) {
            $targetEpisode = RadioProgram::query()->findOrFail($radioProgramId);
            if (! $user->canManageConductorEpisode($targetEpisode) || (int) $targetEpisode->master_program_id !== (int) $master->id) {
                abort(403, 'No tienes autorización para sustituir el audio de este episodio.');
            }

            $targetDeadline = $master->calculateEmissionDeadline($targetEpisode->fecha_emision);
            if (!empty($targetDeadline['is_closed']) && ! $targetEpisode->hasActiveLateSubmissionException()) {
                return response()->json([
                    'error' => 'El plazo de entrega para esta emisión ha vencido (' . ($targetDeadline['deadline_human'] ?? '') . '). No se permiten nuevas subidas sin autorización de excepción administrativa.',
                    'deadline_closed' => true,
                ], 422);
            }
        } elseif ($request->filled('emission_date')) {
            $targetDeadline = $master->calculateEmissionDeadline((string) $request->input('emission_date'));
            if (!empty($targetDeadline['is_closed'])) {
                return response()->json([
                    'error' => 'El plazo de entrega para la fecha seleccionada ha vencido (' . ($targetDeadline['deadline_human'] ?? '') . '). No se admiten nuevas entregas para esta emisión.',
                    'deadline_closed' => true,
                ], 422);
            }
        }

        $ext = strtolower(pathinfo((string) $data['filename'], PATHINFO_EXTENSION));
        if ($ext !== 'mp3') {
            return response()->json([
                'error' => 'Solo se admiten archivos con extensión .mp3.',
            ], 422);
        }

        if (! $this->fileUploadService->isR2Configured()) {
            return response()->json([
                'error' => 'El almacenamiento definitivo en Cloudflare R2 no está disponible.',
            ], 503);
        }

        $authorization = ConductorUploadAuthorization::issueForConductor(
            conductor: $user,
            masterProgram: $master,
            originalFilename: (string) $data['filename'],
            expectedMime: (string) $data['contentType'],
            radioProgramId: $radioProgramId,
            expiresAt: now()->addMinutes(60),
            maxSizeBytes: self::MAX_AUDIO_SIZE_BYTES,
        );

        $urlStr = '';
        $headers = ['Content-Type' => (string) $data['contentType']];

        try {
            $presignedData = Storage::disk('r2')->temporaryUploadUrl(
                $authorization->r2_key,
                $authorization->expires_at
            );
            $urlStr = is_array($presignedData) ? (string) ($presignedData['url'] ?? '') : (string) $presignedData;
            if (is_array($presignedData) && isset($presignedData['headers']) && is_array($presignedData['headers'])) {
                $headers = $presignedData['headers'];
            }
        } catch (\Throwable) {
            // En entorno local/pruebas con disco fake que no soporta temporaryUploadUrl, devolvemos URL firmada simulada
            $urlStr = 'https://r2-upload.sevenrockradio.test/' . $authorization->r2_key;
        }

        return response()->json([
            'url' => $urlStr,
            'method' => 'PUT',
            'headers' => $headers,
            'key' => $authorization->r2_key,
            'upload_token' => $authorization->upload_token,
            'expires_at' => $authorization->expires_at->toIso8601String(),
            'master_program_id' => $master->id,
        ]);
    }

    public function create(Request $request, ?MasterProgram $masterProgram = null): View
    {
        /** @var User $user */
        $user = Auth::user();
        $authorizedPrograms = $user->authorizedMasterPrograms()->orderBy('nombre')->get();

        if ($masterProgram === null) {
            $requestedId = (int) $request->query('master_program_id', 0);
            if ($requestedId > 0) {
                $masterProgram = MasterProgram::query()->findOrFail($requestedId);
            } else {
                $masterProgram = $authorizedPrograms->first();
            }
        }

        if (! $masterProgram instanceof MasterProgram || ! $user->isAuthorizedForMasterProgram($masterProgram)) {
            abort(403, 'No tienes autorización para entregar episodios en este programa.');
        }

        $nextEpisodeNumber = max(
            1,
            ((int) RadioProgram::query()->where('master_program_id', $masterProgram->id)->max('numero_episodio')) + 1
        );

        $requestedDate = $request->query('fecha_emision') ?: now()->toDateString();
        $deadlineInfo = $masterProgram->calculateEmissionDeadline($requestedDate);

        return view('conductor.episodes.create', [
            'user' => $user,
            'conductor' => $user,
            'masterProgram' => $masterProgram,
            'selectedProgram' => $masterProgram,
            'authorizedPrograms' => $authorizedPrograms,
            'nextEpisodeNumber' => $nextEpisodeNumber,
            'editorialGuide' => $masterProgram->effectiveEditorialGuide(),
            'deadlineInfo' => $deadlineInfo,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // 1. Validar y comprobar permisos sobre el programa ANTES de procesar audio o portada
        $programCheck = $request->validate([
            'master_program_id' => ['required', 'integer', 'exists:master_programs,id'],
        ]);

        $master = MasterProgram::query()->findOrFail((int) $programCheck['master_program_id']);
        if (! $user->isAuthorizedForMasterProgram($master)) {
            abort(403, 'No estás autorizado para entregar episodios en este programa.');
        }

        // 2. Comprobar que Cloudflare R2 esté configurado ANTES de aceptar o guardar archivos (nunca caer a public)
        if (! $this->fileUploadService->isR2Configured()) {
            return back()->withInput()->withErrors([
                'archivo_mp3' => 'El almacenamiento definitivo en Cloudflare R2 no está configurado o no está disponible. No se guardó la entrega en el disco local; inténtalo nuevamente cuando R2 esté operativo.',
            ]);
        }

        $data = $request->validate([
            'master_program_id' => ['required', 'integer', 'exists:master_programs,id'],
            'numero_episodio' => ['nullable', 'integer', 'min:1'],
            'fecha_emision' => ['required', 'date'],
            'live_title' => ['required', 'string', 'max:255'],
            'editorial_summary' => ['required', 'string', 'max:1000'],
            'resena' => ['required', 'string'],
            'biografia_invitado' => ['nullable', 'string', 'max:500'],
            'imagen_episodio_file' => ['nullable', 'file', 'max:10240'],
            'imagen_portada' => ['nullable', 'file', 'max:10240'],
            'r2_key' => ['nullable', 'string', 'max:255'],
            'upload_token' => ['nullable', 'string', 'max:128'],
            'archivo_mp3' => [
                'required_without:r2_key',
                'nullable',
                'file',
                'max:512000',
            ],
        ]);

        // 3. Comprobar fecha límite de entrega (la subida y envío deben terminar antes del límite)
        $deadlineInfo = $master->calculateEmissionDeadline($data['fecha_emision']);
        if (!empty($deadlineInfo['is_closed'])) {
            return back()->withInput()->withErrors([
                'fecha_emision' => 'El plazo de entrega para la emisión del ' . Carbon::parse((string) $data['fecha_emision'])->format('d/m/Y') . ' ha vencido (' . ($deadlineInfo['deadline_human'] ?? '') . '). Tanto la subida del MP3 como el envío del formulario deben completarse antes de la fecha límite.',
            ]);
        }

        // 3. Validar tipo real y tamaño de portada y audio antes de almacenarlos
        $this->validateUploadedCoverIfPresent($request);
        if (! $request->filled('r2_key')) {
            $this->validateUploadedMp3File($request->file('archivo_mp3'));
        }

        $storageFolder = $master->resolveStorageFolder();
        $uploadedNewMp3ToR2 = false;
        $uploadedNewCoverToR2 = false;
        $rawPath = null;
        $coverPath = null;

        try {
            [$rawPath, $disk, $audioSizeBytes, $authRecord, $uploadedNewMp3ToR2] = $this->storeIncomingMp3(
                request: $request,
                user: $user,
                master: $master,
                storageFolder: $storageFolder,
                data: $data,
                existingEpisode: null,
            );

            [$coverPath, $uploadedNewCoverToR2] = $this->resolveEpisodeCover(
                request: $request,
                master: $master,
                storageFolder: $storageFolder,
                data: $data,
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($uploadedNewMp3ToR2 && is_string($rawPath) && $rawPath !== '') {
                try {
                    Storage::disk('r2')->delete($rawPath);
                } catch (\Throwable) {
                }
            }
            if ($uploadedNewCoverToR2 && is_string($coverPath) && $coverPath !== '') {
                try {
                    Storage::disk('r2')->delete($coverPath);
                } catch (\Throwable) {
                }
            }

            return back()->withInput()->withErrors([
                'archivo_mp3' => 'Error al transferir o verificar la entrega en Cloudflare R2: ' . $e->getMessage(),
            ]);
        }

        $episodeNumber = isset($data['numero_episodio']) && (int) $data['numero_episodio'] > 0
            ? (int) $data['numero_episodio']
            : max(1, ((int) RadioProgram::query()->where('master_program_id', $master->id)->max('numero_episodio')) + 1);

        $uploadToken = trim((string) $request->input('upload_token', ''));

        $episode = DB::transaction(function () use ($user, $master, $data, $episodeNumber, $rawPath, $disk, $coverPath, $storageFolder, $authRecord, $uploadToken): RadioProgram {
            $claimedAuth = null;
            if ($authRecord instanceof ConductorUploadAuthorization) {
                $claimedAuth = ConductorUploadAuthorization::claimAtomically(
                    r2Key: (string) $authRecord->r2_key,
                    conductor: $user,
                    masterProgram: $master,
                    existingEpisode: null,
                    uploadToken: $uploadToken,
                );
            }

            $created = RadioProgram::withoutEvents(function () use ($user, $master, $data, $episodeNumber, $rawPath, $disk, $coverPath, $storageFolder, $claimedAuth): RadioProgram {
                return RadioProgram::query()->create([
                    'master_program_id' => $master->id,
                    'submitted_by_user_id' => $user->id,
                    'titulo_programa' => (string) $master->nombre,
                    'conductor' => (string) ($master->conductor ?: $user->name),
                    'pais' => $master->pais,
                    'numero_episodio' => $episodeNumber,
                    'fecha_emision' => Carbon::parse((string) $data['fecha_emision'])->toDateString(),
                    'live_title' => trim((string) $data['live_title']),
                    'editorial_summary' => trim((string) $data['editorial_summary']),
                    'resena' => trim((string) $data['resena']),
                    'live_description' => trim((string) $data['resena']),
                    'comentario_episodio' => trim((string) $data['resena']),
                    'biografia_invitado' => trim((string) ($data['biografia_invitado'] ?? '')) ?: null,
                    'duration_seconds' => (int) (($master->duracion_minutos ?: 120) * 60),
                    'archivo_mp3' => $rawPath,
                    'archivo_mp3_disk' => $disk,
                    'enviado_radioboss' => false,
                    'radioboss_status' => 'pending_editorial',
                    'schedule_inclusion_status' => 'not_scheduled',
                    'sync_archive_org' => (bool) ($master->sync_archive_org ?? true),
                    'archive_org_status' => 'pending_editorial',
                    'delivery_status' => 'pending_editorial',
                    'editorial_status' => RadioProgram::EDITORIAL_STATUS_PENDING,
                    'editorial_version' => 1,
                    'imagen_episodio' => $coverPath,
                    'caratula_programa' => (string) $master->caratula_url,
                    'ruta_ftp_radioboss' => (string) $master->ruta_ftp,
                    'dia_transmision' => (string) $master->dia_transmision,
                    'hora_inicio' => (string) $master->hora_transmision,
                    'genero_musical' => (string) $master->genero,
                    'email_notificacion' => (string) ($master->email_notificacion ?? ''),
                    'status_message' => 'Recibido, pendiente de revisión editorial',
                    'delivery_metadata' => [
                        'preserve_local_copy' => false,
                        'storage_folder' => $storageFolder,
                        'source' => 'conductor-portal',
                        'submitted_by_user_id' => $user->id,
                        'submitted_by_email' => $user->email,
                        'upload_authorization_id' => $claimedAuth?->id,
                    ],
                ]);
            });

            if ($claimedAuth instanceof ConductorUploadAuthorization) {
                $claimedAuth->forceFill([
                    'consumed_by_radio_program_id' => $created->id,
                ])->save();
            }

            return $created;
        });

        $versionToken = $this->editorialService->generateVersionToken((int) $episode->id, 1);
        $evaluation = $this->editorialService->evaluateSubmission($episode, $master, [
            'audio_size_bytes' => $audioSizeBytes,
        ]);

        $episode->editorial_version_token = $versionToken;
        $episode->editorial_checks = $evaluation;
        $episode->editorial_original_payload = $this->editorialService->buildOriginalPayloadSnapshot($episode, 'initial_submission');

        // Modo B: Entrega autónoma expresamente autorizada (solo si el máster lo tiene autorizado y no hay incidencias)
        if ($master->isAutonomousDeliveryAuthorized()) {
            if ($evaluation['can_auto_approve_in_autonomous_mode']) {
                $meta = (array) ($episode->delivery_metadata ?? []);
                $meta['approval_mode'] = 'autonomous';
                $episode->delivery_metadata = $meta;
                $episode->editorial_status = RadioProgram::EDITORIAL_STATUS_APPROVED;
                $episode->editorial_approved_at = now();
                $episode->editorial_approved_version = 1;
                $episode->radioboss_status = 'radioboss_pending';
                $episode->archive_org_status = 'archive_pending';
                $episode->delivery_status = 'delivery_pending';
                $episode->processing_started_at = now();
                $episode->status_message = 'Recibido, pendiente de revisión editorial (validado en modo autónomo autorizado).';
                $episode->saveQuietly();

                $this->auditService->record($episode, 'CONDUCTOR_AUTONOMOUS_APPROVED', 'Entrega aprobada automáticamente bajo modo autónomo autorizado.', [
                    'conductor_id' => $user->id,
                    'version' => 1,
                ]);

                ProcessMp3Job::dispatch(
                    $episode->fresh(['masterProgram']) ?? $episode,
                    $rawPath,
                    false,
                    $user->id,
                    $user->name,
                    $user->email,
                    true,
                );

                return redirect()
                    ->route('conductor.episodes.show', $episode)
                    ->with('status', 'Recibido, pendiente de revisión editorial');
            }

            // Si hay incidencias técnicas o editoriales importantes en modo autónomo, retener la entrega
            $episode->editorial_status = RadioProgram::EDITORIAL_STATUS_RETAINED;
            $episode->status_message = 'Recibido, pendiente de revisión editorial (retenido por incidencias).';
            $episode->saveQuietly();

            $this->auditService->record($episode, 'CONDUCTOR_AUTONOMOUS_RETAINED', 'Entrega retenida en modo autónomo por incidencias técnicas o editoriales.', [
                'conductor_id' => $user->id,
                'evaluation' => $evaluation,
            ]);

            $this->sendEditorialReviewNotification($episode, $user);

            return redirect()
                ->route('conductor.episodes.show', $episode)
                ->with('status', 'Recibido, pendiente de revisión editorial');
        }

        // Modo A (Predeterminado): Revisión obligatoria
        $episode->saveQuietly();

        $this->auditService->record($episode, 'CONDUCTOR_SUBMISSION_RECEIVED', 'El conductor entregó un episodio para revisión editorial.', [
            'conductor_id' => $user->id,
            'version' => 1,
        ]);

        $this->sendEditorialReviewNotification($episode, $user);

        return redirect()
            ->route('conductor.episodes.show', $episode)
            ->with('status', 'Recibido, pendiente de revisión editorial');
    }

    public function show(RadioProgram $radioProgram): View
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user->canManageConductorEpisode($radioProgram)) {
            abort(403, 'No tienes permiso para consultar esta entrega.');
        }

        $radioProgram->loadMissing('masterProgram');
        $deadlineInfo = $radioProgram->masterProgram?->calculateEmissionDeadline($radioProgram->fecha_emision);

        return view('conductor.episodes.show', [
            'user' => $user,
            'conductor' => $user,
            'episode' => $radioProgram,
            'radioProgram' => $radioProgram,
            'masterProgram' => $radioProgram->masterProgram,
            'operationalStates' => $radioProgram->operationalStates(),
            'deadlineInfo' => $deadlineInfo,
            'hasLateException' => $radioProgram->hasActiveLateSubmissionException(),
            'canShareAndDownload' => $radioProgram->canConductorShareAndDownload(),
            'publicShareUrl' => $radioProgram->publicShareUrl(),
        ]);
    }

    public function edit(RadioProgram $radioProgram): View
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user->canManageConductorEpisode($radioProgram)) {
            abort(403, 'No tienes permiso para modificar esta entrega.');
        }

        $radioProgram->loadMissing('masterProgram');
        $masterProgram = $radioProgram->masterProgram;
        $deadlineInfo = $masterProgram?->calculateEmissionDeadline($radioProgram->fecha_emision);

        return view('conductor.episodes.edit', [
            'user' => $user,
            'conductor' => $user,
            'episode' => $radioProgram,
            'radioProgram' => $radioProgram,
            'masterProgram' => $masterProgram,
            'editorialGuide' => $masterProgram?->effectiveEditorialGuide() ?? ThemeSetting::currentPodcastEditorialGuide(),
            'deadlineInfo' => $deadlineInfo,
            'hasLateException' => $radioProgram->hasActiveLateSubmissionException(),
        ]);
    }

    public function update(Request $request, RadioProgram $radioProgram): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user->canManageConductorEpisode($radioProgram)) {
            abort(403, 'No tienes permiso para modificar esta entrega.');
        }

        $radioProgram->loadMissing('masterProgram');
        $master = $radioProgram->masterProgram ?? MasterProgram::query()->findOrFail($radioProgram->master_program_id);

        $hasNewCoverUpload = $request->hasFile('imagen_portada') || $request->hasFile('imagen_episodio_file');
        $hasNewAudio = $request->hasFile('archivo_mp3') || $request->filled('r2_key');
        $hadPreparedAudio = $radioProgram->hasPreparedBroadcastAudio();

        if (($hasNewCoverUpload || $hasNewAudio) && ! $this->fileUploadService->isR2Configured()) {
            return back()->withInput()->withErrors([
                'archivo_mp3' => 'El almacenamiento en Cloudflare R2 no está disponible. Se conservó intacta tu entrega anterior sin guardar copias en el disco local.',
            ]);
        }

        $data = $request->validate([
            'numero_episodio' => ['required', 'integer', 'min:1'],
            'fecha_emision' => ['required', 'date'],
            'live_title' => ['required', 'string', 'max:255'],
            'editorial_summary' => ['required', 'string', 'max:1000'],
            'resena' => ['required', 'string'],
            'biografia_invitado' => ['nullable', 'string', 'max:500'],
            'imagen_episodio_file' => ['nullable', 'file', 'max:10240'],
            'imagen_portada' => ['nullable', 'file', 'max:10240'],
            'cover_update_scope' => [
                Rule::requiredIf($hasNewCoverUpload),
                'nullable',
                Rule::in(['sheet_only', 'reembed_and_redistribute']),
            ],
            'archivo_mp3' => ['nullable', 'file', 'max:512000'],
            'r2_key' => ['nullable', 'string', 'max:255'],
            'upload_token' => ['nullable', 'string', 'max:128'],
            'confirm_replace_prepared_audio' => ['nullable', 'boolean'],
        ]);

        $deadlineInfo = $master->calculateEmissionDeadline($data['fecha_emision']);
        if (!empty($deadlineInfo['is_closed']) && ! $radioProgram->hasActiveLateSubmissionException()) {
            return back()->withInput()->withErrors([
                'fecha_emision' => 'El plazo de entrega para la emisión del ' . Carbon::parse((string) $data['fecha_emision'])->format('d/m/Y') . ' venció el ' . ($deadlineInfo['deadline_human'] ?? '') . '. Las sustituciones o modificaciones posteriores al cierre requieren una excepción autorizada por administración.',
            ]);
        }

        if ($hadPreparedAudio && $hasNewAudio && ! $request->boolean('confirm_replace_prepared_audio')) {
            return back()->withInput()->withErrors([
                'confirm_replace_prepared_audio' => 'Este episodio ya tiene un audio preparado para emisión. Debes confirmar explícitamente su sustitución.',
            ]);
        }

        if ($hasNewCoverUpload) {
            $this->validateUploadedCoverIfPresent($request);
        }
        if ($request->hasFile('archivo_mp3')) {
            $this->validateUploadedMp3File($request->file('archivo_mp3'));
        }

        $newDate = Carbon::parse((string) $data['fecha_emision'])->toDateString();
        $newTitle = trim((string) $data['live_title']);
        $newSummary = trim((string) $data['editorial_summary']);
        $newDescription = trim((string) $data['resena']);
        $newBio = trim((string) ($data['biografia_invitado'] ?? '')) ?: null;
        $newEpisodeNumber = (int) $data['numero_episodio'];

        $storageFolder = $radioProgram->storageFolderSlug();

        $uploadedPath = null;
        $uploadedDisk = null;
        $authRecord = null;
        $uploadedNewMp3ToR2 = false;
        $uploadedNewCoverToR2 = false;
        $newCover = $radioProgram->imagen_episodio;

        try {
            if ($hasNewCoverUpload) {
                [$newCover, $uploadedNewCoverToR2] = $this->resolveEpisodeCover(
                    request: $request,
                    master: $master,
                    storageFolder: $storageFolder,
                    data: $data,
                    existingCover: $radioProgram->imagen_episodio,
                );
            }

            if ($hasNewAudio) {
                [$uploadedPath, $uploadedDisk, , $authRecord, $uploadedNewMp3ToR2] = $this->storeIncomingMp3(
                    request: $request,
                    user: $user,
                    master: $master,
                    storageFolder: $storageFolder,
                    data: $data,
                    existingEpisode: $radioProgram,
                );
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($uploadedNewMp3ToR2 && is_string($uploadedPath) && $uploadedPath !== '') {
                try {
                    Storage::disk('r2')->delete($uploadedPath);
                } catch (\Throwable) {
                }
            }
            if ($uploadedNewCoverToR2 && is_string($newCover) && $newCover !== '' && $newCover !== $radioProgram->imagen_episodio) {
                try {
                    Storage::disk('r2')->delete($newCover);
                } catch (\Throwable) {
                }
            }

            return back()->withInput()->withErrors([
                'archivo_mp3' => 'Error al almacenar la actualización en Cloudflare R2; se conservó tu entrega anterior: ' . $e->getMessage(),
            ]);
        }

        $contentChanged = $newTitle !== trim((string) $radioProgram->live_title)
            || $newSummary !== trim((string) $radioProgram->editorial_summary)
            || $newDescription !== trim((string) $radioProgram->resena)
            || (string) $newBio !== (string) $radioProgram->biografia_invitado
            || $newDate !== optional($radioProgram->fecha_emision)->toDateString()
            || $newEpisodeNumber !== (int) $radioProgram->numero_episodio
            || $newCover !== $radioProgram->imagen_episodio
            || $hasNewAudio;

        if (! $contentChanged) {
            return redirect()
                ->route('conductor.episodes.show', $radioProgram)
                ->with('status', 'No se detectaron cambios en la entrega.');
        }

        $wasApproved = $radioProgram->isEditorialApproved();
        $uploadToken = trim((string) $request->input('upload_token', ''));

        $newVersion = DB::transaction(function () use (
            $radioProgram,
            $user,
            $master,
            $authRecord,
            $uploadToken,
            $hasNewAudio,
            $uploadedPath,
            $uploadedDisk,
            $hadPreparedAudio,
            $newCover,
            $newEpisodeNumber,
            $newDate,
            $newTitle,
            $newSummary,
            $newDescription,
            $newBio,
            $wasApproved
        ): int {
            $claimedAuth = null;
            if ($hasNewAudio && $authRecord instanceof ConductorUploadAuthorization) {
                $claimedAuth = ConductorUploadAuthorization::claimAtomically(
                    r2Key: (string) $authRecord->r2_key,
                    conductor: $user,
                    masterProgram: $master,
                    existingEpisode: $radioProgram,
                    uploadToken: $uploadToken,
                );
            }

            $metadata = (array) ($radioProgram->delivery_metadata ?? []);
            if ($claimedAuth instanceof ConductorUploadAuthorization) {
                $metadata['upload_authorization_id'] = $claimedAuth->id;
            }

            if ($hasNewAudio && is_string($uploadedPath) && $uploadedPath !== '') {
                if ($hadPreparedAudio) {
                    $metadata['staged_replacement_mp3_path'] = $uploadedPath;
                    $metadata['staged_replacement_mp3_disk'] = $uploadedDisk;
                    $metadata['staged_replacement_at'] = now()->toIso8601String();
                } else {
                    $radioProgram->archivo_mp3 = $uploadedPath;
                    $radioProgram->archivo_mp3_disk = $uploadedDisk;
                }
            }

            if ($newCover !== $radioProgram->imagen_episodio && $hadPreparedAudio) {
                $metadata['cover_embedded_in_mp3_outdated'] = true;
            }

            $version = ((int) ($radioProgram->editorial_version ?: 1)) + 1;
            $versionToken = $this->editorialService->generateVersionToken((int) $radioProgram->id, $version);

            $radioProgram->numero_episodio = $newEpisodeNumber;
            $radioProgram->fecha_emision = $newDate;
            $radioProgram->live_title = $newTitle;
            $radioProgram->editorial_summary = $newSummary;
            $radioProgram->resena = $newDescription;
            $radioProgram->live_description = $newDescription;
            $radioProgram->comentario_episodio = $newDescription;
            $radioProgram->biografia_invitado = $newBio;
            $radioProgram->imagen_episodio = $newCover;
            $radioProgram->editorial_version = $version;
            $radioProgram->editorial_version_token = $versionToken;
            $radioProgram->editorial_status = RadioProgram::EDITORIAL_STATUS_PENDING;
            if ($wasApproved) {
                $radioProgram->editorial_approved_at = null;
                $radioProgram->editorial_approved_by = null;
                $radioProgram->editorial_approved_version = null;
            }
            $radioProgram->status_message = 'Recibido, pendiente de revisión editorial';
            $radioProgram->delivery_metadata = $metadata;

            $evaluation = $this->editorialService->evaluateSubmission($radioProgram, $master);
            $radioProgram->editorial_checks = $evaluation;
            $radioProgram->editorial_original_payload = $this->editorialService->buildOriginalPayloadSnapshot(
                $radioProgram,
                $wasApproved ? 'post_approval_modification' : 'conductor_revision'
            );

            $radioProgram->saveQuietly();

            if ($claimedAuth instanceof ConductorUploadAuthorization) {
                $claimedAuth->forceFill([
                    'consumed_by_radio_program_id' => $radioProgram->id,
                ])->save();
            }

            return $version;
        });

        $this->auditService->record(
            $radioProgram,
            $wasApproved ? 'CONDUCTOR_SUBMISSION_UPDATED_AFTER_APPROVAL' : 'CONDUCTOR_SUBMISSION_UPDATED',
            $wasApproved
                ? 'El conductor modificó contenido previamente aprobado; se generó una nueva revisión editorial.'
                : 'El conductor actualizó su entrega.',
            [
                'conductor_id' => $user->id,
                'new_version' => $newVersion,
                'was_approved' => $wasApproved,
                'had_prepared_audio' => $hadPreparedAudio,
            ]
        );

        $this->sendEditorialReviewNotification($radioProgram, $user);

        return redirect()
            ->route('conductor.episodes.show', $radioProgram)
            ->with('status', 'Recibido, pendiente de revisión editorial');
    }

    /**
     * Valida y resuelve el archivo MP3 entrante (por autorización de subida directa o subida por formulario)
     * exclusivamente hacia Cloudflare R2.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: string, 2: int, 3: ?ConductorUploadAuthorization, 4: bool}
     */
    private function storeIncomingMp3(
        Request $request,
        User $user,
        MasterProgram $master,
        string $storageFolder,
        array $data,
        ?RadioProgram $existingEpisode = null,
    ): array {
        if (! $this->fileUploadService->isR2Configured()) {
            throw new \RuntimeException('Cloudflare R2 no está configurado.');
        }

        if ($request->filled('r2_key')) {
            $r2Key = ltrim(str_replace('\\', '/', trim((string) $request->input('r2_key'))), '/');
            $uploadToken = trim((string) $request->input('upload_token', ''));

            $authRecord = ConductorUploadAuthorization::validatePreClaim(
                r2Key: $r2Key,
                conductor: $user,
                masterProgram: $master,
                existingEpisode: $existingEpisode,
                uploadToken: $uploadToken,
            );

            if (! Storage::disk('r2')->exists($r2Key)) {
                throw ValidationException::withMessages([
                    'r2_key' => 'El archivo de audio autorizado no existe en Cloudflare R2.',
                ]);
            }

            $size = (int) Storage::disk('r2')->size($r2Key);
            $maxAllowed = max(1, (int) ($authRecord->max_size_bytes ?: self::MAX_AUDIO_SIZE_BYTES));

            if ($size <= 0 || $size > $maxAllowed) {
                throw ValidationException::withMessages([
                    'r2_key' => sprintf('El archivo en R2 tiene un tamaño inválido (%d bytes).', $size),
                ]);
            }

            $headBytes = $this->readR2HeaderBytes($r2Key, 4096);
            if (! $this->isValidMp3BinarySignature($headBytes)) {
                throw ValidationException::withMessages([
                    'r2_key' => 'El objeto almacenado en R2 no supera la validación de formato real de audio MP3 (cabecera inválida o tipo no permitido).',
                ]);
            }

            return [$r2Key, 'r2', $size, $authRecord, false];
        }

        /** @var UploadedFile $file */
        $file = $request->file('archivo_mp3');
        $this->validateUploadedMp3File($file);

        $date = Carbon::parse((string) $data['fecha_emision'])->format('Y-m-d');
        $slug = Str::slug((string) $data['live_title'], '-') ?: 'episode';
        $fileName = sprintf('%s-%s-%s.mp3', $date, $slug, Str::lower(Str::uuid()->toString()));
        $inboxDir = 'podcast-inbox/' . $storageFolder;

        $storedPath = Storage::disk('r2')->putFileAs($inboxDir, $file, $fileName);
        if (! is_string($storedPath) || $storedPath === '' || ! Storage::disk('r2')->exists(trim($storedPath, '/'))) {
            throw new \RuntimeException('No se pudo almacenar ni verificar el archivo MP3 en Cloudflare R2.');
        }

        $normalizedStoredPath = trim($storedPath, '/');
        $size = (int) ($file->getSize() ?: Storage::disk('r2')->size($normalizedStoredPath));

        return [$normalizedStoredPath, 'r2', $size, null, true];
    }

    /**
     * Valida y almacena la portada del episodio exclusivamente en R2 (nunca en public).
     *
     * @param  array<string, mixed>  $data
     * @return array{0: ?string, 1: bool}
     */
    private function resolveEpisodeCover(
        Request $request,
        MasterProgram $master,
        string $storageFolder,
        array $data,
        ?string $existingCover = null
    ): array {
        if ($request->hasFile('imagen_episodio_file') || $request->hasFile('imagen_portada')) {
            if (! $this->fileUploadService->isR2Configured()) {
                throw new \RuntimeException('Cloudflare R2 no está configurado para almacenar la portada.');
            }

            /** @var UploadedFile $file */
            $file = $request->file('imagen_episodio_file') ?: $request->file('imagen_portada');
            $detectedMime = $this->validateUploadedCoverFile($file);

            $ext = match ($detectedMime) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };

            $date = Carbon::parse((string) $data['fecha_emision'])->format('Y-m-d');
            $slug = Str::slug((string) $data['live_title'], '-') ?: 'cover';
            $name = sprintf('%s-%s-cover-%s.%s', $date, $slug, Str::lower(Str::uuid()->toString()), $ext);

            $stored = Storage::disk('r2')->putFileAs('podcast-inbox/covers/' . $storageFolder, $file, $name);
            if (! is_string($stored) || $stored === '' || ! Storage::disk('r2')->exists(trim($stored, '/'))) {
                throw new \RuntimeException('No se pudo guardar la portada del episodio en Cloudflare R2.');
            }

            return [trim($stored, '/'), true];
        }

        if ($existingCover !== null && trim($existingCover) !== '') {
            return [$existingCover, false];
        }

        $masterCover = trim((string) ($master->caratula_url ?? ''));

        return [$masterCover !== '' ? $masterCover : null, false];
    }

    private function validateUploadedMp3File(mixed $file): void
    {
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            throw ValidationException::withMessages([
                'archivo_mp3' => 'El archivo de audio MP3 es obligatorio y debe ser válido.',
            ]);
        }

        $size = (int) ($file->getSize() ?: 0);
        if ($size <= 0 || $size > self::MAX_AUDIO_SIZE_BYTES) {
            throw ValidationException::withMessages([
                'archivo_mp3' => 'El archivo MP3 está vacío o supera el tamaño máximo permitido (500 MB).',
            ]);
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext !== 'mp3') {
            throw ValidationException::withMessages([
                'archivo_mp3' => 'El archivo de audio debe tener extensión .mp3.',
            ]);
        }

        $realPath = $file->getRealPath();
        $headBytes = (is_string($realPath) && is_file($realPath))
            ? (string) file_get_contents($realPath, false, null, 0, 4096)
            : '';

        $finfoMime = (is_string($realPath) && is_file($realPath) && function_exists('finfo_open'))
            ? (function (string $path): string {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = $finfo ? (string) finfo_file($finfo, $path) : '';
                if ($finfo) {
                    finfo_close($finfo);
                }

                return strtolower(trim($mime));
            })($realPath)
            : strtolower((string) $file->getMimeType());

        $clientMime = strtolower((string) $file->getClientMimeType());

        // Rechazar si las firmas binarias corresponden a otro formato (imagen, script, HTML, PHP, PDF, ZIP, WAV)
        if ($headBytes !== '' && ! $this->isValidMp3BinarySignature($headBytes)) {
            throw ValidationException::withMessages([
                'archivo_mp3' => 'El archivo subido no es un audio MP3 real (su firma binaria no corresponde a MPEG/ID3).',
            ]);
        }

        $allowedMimes = ['audio/mpeg', 'audio/mp3', 'application/octet-stream', 'application/x-empty'];
        if (! in_array($finfoMime, $allowedMimes, true) || ! in_array($clientMime, ['audio/mpeg', 'audio/mp3', 'application/octet-stream'], true)) {
            throw ValidationException::withMessages([
                'archivo_mp3' => sprintf('Tipo MIME real no permitido para audio (%s). Solo se acepta audio/mpeg.', $finfoMime ?: $clientMime),
            ]);
        }

        // Si el archivo tiene contenido en disco pero finfo detectó application/octet-stream, exigir firma binaria MP3
        if ($finfoMime === 'application/octet-stream' && ! $this->isValidMp3BinarySignature($headBytes)) {
            throw ValidationException::withMessages([
                'archivo_mp3' => 'El archivo binario no contiene una cabecera MP3 (ID3 / MPEG frame sync) válida.',
            ]);
        }
    }

    private function validateUploadedCoverIfPresent(Request $request): void
    {
        if (! $request->hasFile('imagen_episodio_file') && ! $request->hasFile('imagen_portada')) {
            return;
        }

        $file = $request->file('imagen_episodio_file') ?: $request->file('imagen_portada');
        $fieldKey = $request->hasFile('imagen_portada') ? 'imagen_portada' : 'imagen_episodio_file';

        try {
            $this->validateUploadedCoverFile($file, $fieldKey);
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    private function validateUploadedCoverFile(mixed $file, string $fieldKey = 'imagen_portada'): string
    {
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            throw ValidationException::withMessages([
                $fieldKey => 'El archivo de portada no es válido.',
            ]);
        }

        $size = (int) ($file->getSize() ?: 0);
        if ($size <= 0 || $size > self::MAX_COVER_SIZE_BYTES) {
            throw ValidationException::withMessages([
                $fieldKey => 'La portada está vacía o supera el tamaño máximo permitido (10 MB).',
            ]);
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw ValidationException::withMessages([
                $fieldKey => 'La portada debe tener extensión .jpg, .jpeg, .png o .webp.',
            ]);
        }

        $realPath = $file->getRealPath();
        if (! is_string($realPath) || ! is_file($realPath)) {
            throw ValidationException::withMessages([
                $fieldKey => 'No se pudo verificar el archivo temporal de la portada.',
            ]);
        }

        $imageInfo = @getimagesize($realPath);
        if (! is_array($imageInfo) || empty($imageInfo[0]) || empty($imageInfo[1]) || empty($imageInfo[2])) {
            throw ValidationException::withMessages([
                $fieldKey => 'El archivo de portada no es una imagen real válida.',
            ]);
        }

        [$width, $height, $imageType] = $imageInfo;
        if (! in_array($imageType, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw ValidationException::withMessages([
                $fieldKey => 'El tipo real de la imagen debe ser JPEG, PNG o WEBP.',
            ]);
        }

        if ($width < 50 || $height < 50 || $width > 8000 || $height > 8000) {
            throw ValidationException::withMessages([
                $fieldKey => 'Las dimensiones de la portada están fuera del rango permitido (50x50 a 8000x8000 píxeles).',
            ]);
        }

        $detectedMime = strtolower((string) ($imageInfo['mime'] ?? ''));
        if (! in_array($detectedMime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages([
                $fieldKey => 'El tipo MIME real de la portada no es válido.',
            ]);
        }

        return $detectedMime;
    }

    private function readR2HeaderBytes(string $r2Key, int $length = 4096): string
    {
        $stream = Storage::disk('r2')->readStream($r2Key);
        if (is_resource($stream)) {
            try {
                $bytes = fread($stream, $length);

                return is_string($bytes) ? $bytes : '';
            } finally {
                fclose($stream);
            }
        }

        $content = Storage::disk('r2')->get($r2Key);

        return is_string($content) ? substr($content, 0, $length) : '';
    }

    private function isValidMp3BinarySignature(string $bytes): bool
    {
        if (strlen($bytes) < 4) {
            return false;
        }

        // Rechazar explícitamente formatos no-MP3 disfrazados (PNG, JPEG, GIF, PDF, ZIP, RIFF/WAV, PHP, HTML, XML, JSON)
        $forbiddenPrefixes = [
            "\x89PNG",
            "\xFF\xD8\xFF",
            'GIF87a',
            'GIF89a',
            '%PDF-',
            "PK\x03\x04",
            'RIFF',
            'OggS',
            'fLaC',
            '<?php',
            '<!DOC',
            '<html',
            '<script',
            '{"',
        ];

        $trimmed = ltrim($bytes);
        foreach ($forbiddenPrefixes as $prefix) {
            if (str_starts_with($bytes, $prefix) || str_starts_with(strtolower($trimmed), strtolower($prefix))) {
                return false;
            }
        }

        // Cabecera ID3v2 ("ID3")
        if (strncmp($bytes, 'ID3', 3) === 0) {
            return true;
        }

        // MPEG Audio Frame Sync (11 bits en 1: 0xFF seguido de byte con bits altos 0xE0)
        $b0 = ord($bytes[0]);
        $b1 = ord($bytes[1]);
        if ($b0 === 0xFF && ($b1 & 0xE0) === 0xE0 && ($b1 & 0x18) !== 0x08 && ($b1 & 0x06) !== 0x00) {
            return true;
        }

        return false;
    }

    /**
     * Envía el correo con enlaces firmados de aprobación EXCLUSIVAMENTE a destinatarios
     * con permisos de administrador autorizados para aprobar, nunca a direcciones genéricas
     * de notificación del programa maestro ni al propio conductor.
     */
    private function sendEditorialReviewNotification(RadioProgram $episode, User $submitter): void
    {
        $episode->loadMissing('masterProgram');
        $recipients = $this->resolveAuthorizedEditorialApproverRecipients($submitter);

        if ($recipients === []) {
            return;
        }

        Mail::to($recipients)->send(new PodcastEditorialReviewMail(
            episode: $episode,
            submitterName: (string) $submitter->name,
            submitterEmail: (string) $submitter->email,
            version: (int) ($episode->editorial_version ?: 1),
            versionToken: (string) $episode->editorial_version_token,
        ));
    }

    /**
     * Devuelve únicamente los correos de administradores autorizados para aprobar entregas editoriales.
     * Excluye explícitamente cualquier correo de notificación del programa (`email_notificacion`, `email_copia_notificacion`)
     * que no pertenezca a una cuenta de administrador verificada, así como al propio conductor que entrega.
     *
     * @return array<int, string>
     */
    public function resolveAuthorizedEditorialApproverRecipients(?User $submitter = null): array
    {
        $submitterEmail = strtolower(trim((string) ($submitter?->email ?? '')));

        $adminEmails = User::query()
            ->where('is_admin', true)
            ->pluck('email')
            ->map(static fn ($e): string => trim((string) $e))
            ->filter(static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->filter(static fn (string $email): bool => strtolower($email) !== $submitterEmail)
            ->values()
            ->all();

        $recipients = array_values(array_unique($adminEmails));

        if ($recipients === []) {
            $fallbackEditorialAdmin = 'prog.sevenrockradio@gmail.com';
            if (strtolower($fallbackEditorialAdmin) !== $submitterEmail) {
                $recipients = [$fallbackEditorialAdmin];
            }
        }

        return $recipients;
    }

    /**
     * Permite al conductor descargar su propio programa cuando la versión vigente esté
     * aprobada editorialmente, procesada y disponible en Cloudflare R2 con nombre legible.
     */
    public function download(RadioProgram $radioProgram): StreamedResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user->canManageConductorEpisode($radioProgram)) {
            abort(403, 'No tienes autorización para descargar este episodio.');
        }

        if (! $radioProgram->canConductorShareAndDownload()) {
            abort(403, 'El archivo definitivo de este episodio no está disponible para descarga hasta que sea aprobado editorialmente y procesado en Cloudflare R2.');
        }

        $normalizedPath = ltrim(str_replace('\\', '/', trim((string) $radioProgram->archivo_mp3)), '/');
        $disk = (string) ($radioProgram->archivo_mp3_disk ?: 'r2');
        $downloadFilename = $radioProgram->readableDownloadFilename();

        if ($disk === 'r2') {
            if (! Storage::disk('r2')->exists($normalizedPath)) {
                abort(404, 'El archivo de audio definitivo no se encuentra en el almacenamiento en la nube.');
            }

            $stream = Storage::disk('r2')->readStream($normalizedPath);
            $size = Storage::disk('r2')->size($normalizedPath);

            return response()->stream(
                function () use ($stream): void {
                    fpassthru($stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                },
                200,
                [
                    'Content-Type' => 'audio/mpeg',
                    'Content-Length' => (string) $size,
                    'Content-Disposition' => sprintf('attachment; filename="%s"; filename*=UTF-8\'\'%s', addslashes($downloadFilename), rawurlencode($downloadFilename)),
                    'Cache-Control' => 'no-cache, private',
                ]
            );
        }

        abort(404, 'Almacenamiento no admitido para descarga.');
    }
}
