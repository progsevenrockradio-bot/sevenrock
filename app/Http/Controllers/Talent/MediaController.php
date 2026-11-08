<?php

declare(strict_types=1);

namespace App\Http\Controllers\Talent;

use App\Http\Controllers\Controller;
use App\Models\TalentMedia;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    /**
     * Devuelve las especificaciones unificadas y límites por tipo de medio para un talento.
     * Fuente de verdad compartida entre validación del backend y la interfaz reactiva.
     *
     * @return array{types: array<string, array<string, mixed>>, storage: array<string, mixed>}
     */
    public static function getMediaTypeSpecs(\App\Models\Talent $talent): array
    {
        $limits = $talent->planLimits();
        $media = $talent->media()->get();
        $storageLimitMb = (int) ($limits['storage_mb'] ?? 0);
        $storageUsedBytes = (int) $talent->storageUsed();
        $storageUsedMb = round($storageUsedBytes / 1024 / 1024, 2);
        $storageRemainingMb = max(0, round($storageLimitMb - $storageUsedMb, 2));

        $types = [
            'photo' => [
                'type' => 'photo',
                'name' => 'Foto',
                'singular' => 'foto',
                'plural' => 'fotografías',
                'category' => 'foto',
                'exts' => ['jpg', 'jpeg', 'png', 'webp'],
                'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
                'accept' => '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp',
                'label' => 'JPG, PNG o WEBP',
                'max_kb' => $talent->maxFileSizeKb('photo'),
                'max_mb' => (int) ($talent->maxFileSizeKb('photo') / 1024),
                'limit' => (int) ($limits['photos'] ?? 0),
                'used' => (int) $media->where('type', 'photo')->count(),
            ],
            'mp3' => [
                'type' => 'mp3',
                'name' => 'MP3',
                'singular' => 'canción',
                'plural' => 'canciones',
                'category' => 'canción',
                'exts' => ['mp3'],
                'mimes' => ['audio/mpeg', 'audio/mp3', 'audio/x-mpeg', 'audio/x-mp3'],
                'accept' => '.mp3,audio/mpeg,audio/mp3',
                'label' => 'MP3',
                'max_kb' => $talent->maxFileSizeKb('mp3'),
                'max_mb' => (int) ($talent->maxFileSizeKb('mp3') / 1024),
                'limit' => (int) ($limits['songs'] ?? 0),
                'used' => (int) $media->where('type', 'mp3')->count(),
            ],
            'document' => [
                'type' => 'document',
                'name' => 'Documento',
                'singular' => 'documento',
                'plural' => 'documentos',
                'category' => 'documento',
                'exts' => ['pdf'],
                'mimes' => ['application/pdf'],
                'accept' => '.pdf,application/pdf',
                'label' => 'PDF',
                'max_kb' => $talent->maxFileSizeKb('document'),
                'max_mb' => (int) ($talent->maxFileSizeKb('document') / 1024),
                'limit' => (int) ($limits['documents'] ?? 0),
                'used' => (int) $media->where('type', 'document')->count(),
            ],
            'video' => [
                'type' => 'video',
                'name' => 'Video',
                'singular' => 'vídeo',
                'plural' => 'vídeos',
                'category' => 'vídeo',
                'exts' => ['mp4', 'webm'],
                'mimes' => ['video/mp4', 'video/webm'],
                'accept' => '.mp4,.webm,video/mp4,video/webm',
                'label' => 'MP4 o WEBM',
                'max_kb' => $talent->maxFileSizeKb('video'),
                'max_mb' => (int) ($talent->maxFileSizeKb('video') / 1024),
                'limit' => (int) ($limits['videos'] ?? 0),
                'used' => (int) $media->where('type', 'video')->count(),
            ],
        ];

        foreach ($types as $key => &$spec) {
            $spec['included'] = $spec['limit'] > 0;
            $spec['remaining'] = max(0, $spec['limit'] - $spec['used']);
            $spec['quota_full'] = $spec['included'] && ($spec['used'] >= $spec['limit']);
        }
        unset($spec);

        return [
            'types' => $types,
            'storage' => [
                'limit_mb' => $storageLimitMb,
                'used_mb' => $storageUsedMb,
                'remaining_mb' => $storageRemainingMb,
            ],
        ];
    }

    public function index(): View
    {
        /** @var \App\Models\Talent|null $talent */
        $talent = Auth::guard('talent')->user();
        $media = $talent ? $talent->media()->latest()->get() : collect();
        $limits = $talent?->planLimits() ?? [];
        $usage = [
            'photos' => (int) $media->where('type', 'photo')->count(),
            'songs' => (int) $media->where('type', 'mp3')->count(),
            'documents' => (int) $media->where('type', 'document')->count(),
            'videos' => (int) $media->where('type', 'video')->count(),
            'storage_used_mb' => round(((int) ($talent?->storageUsed() ?? 0)) / 1024 / 1024, 2),
        ];

        $mediaTypeSpecs = $talent ? self::getMediaTypeSpecs($talent) : ['types' => [], 'storage' => []];

        return view('talentos.media', [
            'talent' => $talent,
            'media' => $media,
            'limits' => $limits,
            'usage' => $usage,
            'typeSpecs' => $mediaTypeSpecs['types'],
            'storageInfo' => $mediaTypeSpecs['storage'],
        ]);
    }

    public function upload(Request $request): RedirectResponse|JsonResponse
    {
        /** @var \App\Models\Talent|null $talent */
        $talent = Auth::guard('talent')->user();
        if (! $talent) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No autenticado.'], 401);
            }
            return redirect()->route('talents.login');
        }

        if (! in_array($talent->subscription_status, ['active'], true) || $talent->is_hidden) {
            $suspendedMsg = 'Tu suscripción está pausada, suspendida o vencida. Reactiva o renueva tu plan para subir contenido.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $suspendedMsg, 'errors' => ['file' => [$suspendedMsg]]], 422);
            }
            return back()->withErrors(['file' => $suspendedMsg]);
        }

        $type = (string) $request->input('type', 'photo');
        $specs = self::getMediaTypeSpecs($talent);
        $spec = $specs['types'][$type] ?? null;

        $maxFileKb = $spec ? $spec['max_kb'] : 10240;
        $maxMb = $spec ? $spec['max_mb'] : 10;
        $singular = $spec ? $spec['singular'] : 'archivo';

        $rules = [
            'type' => ['required', 'in:photo,mp3,document,video'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'file' => ['required', 'file', 'max:' . $maxFileKb],
            'is_exclusive' => ['nullable', 'boolean'],
        ];

        $messages = [
            'file.required' => 'Selecciona un archivo antes de subir.',
            'file.file' => 'El archivo seleccionado no es válido.',
            'file.max' => "El archivo supera el tamaño máximo permitido para tu plan ({$maxMb} MB por {$singular}).",
            'type.required' => 'El tipo de archivo es obligatorio.',
            'type.in' => 'El tipo de archivo seleccionado no es válido.',
        ];

        $validated = $request->validate($rules, $messages);
        $file = $request->file('file');

        // Validación estricta de extensiones y tipos MIME
        $mime = strtolower((string) $file->getMimeType());
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $allowedSpec = $specs['types'][$validated['type']] ?? null;

        if (! $allowedSpec || ! in_array($ext, $allowedSpec['exts'], true) || ! in_array($mime, $allowedSpec['mimes'], true)) {
            $expectedLabel = $allowedSpec['label'] ?? 'archivo válido';
            $formatError = "El archivo no tiene el formato permitido para {$validated['type']} (formatos aceptados: {$expectedLabel}).";
            if ($request->expectsJson()) {
                return response()->json(['message' => $formatError, 'errors' => ['file' => [$formatError]]], 422);
            }
            return back()->withInput()->withErrors(['file' => $formatError]);
        }

        if (! $talent->canUpload($validated['type'])) {
            $limitVal = (int) ($allowedSpec['limit'] ?? 0);
            $usedVal = (int) ($allowedSpec['used'] ?? 0);
            $pluralName = $allowedSpec['plural'] ?? $validated['type'];

            if ($allowedSpec && ! ($allowedSpec['included'] ?? true)) {
                $quotaError = ($validated['type'] === 'video')
                    ? 'Tu plan actual no incluye la subida de archivos de vídeo (puedes añadir enlaces a YouTube en tu perfil).'
                    : "El tipo {$allowedSpec['name']} no está incluido en tu plan actual.";
            } elseif ($validated['type'] === 'mp3') {
                $quotaError = "Tu plan ya alcanzó el límite de {$limitVal} canciones permitidas en tu plan (actualmente tienes {$usedVal}). Para subir una nueva canción debes sustituir o eliminar una existente o actualizar tu plan.";
            } else {
                $quotaError = "Tu plan ya alcanzó el límite de {$limitVal} {$pluralName} permitidas en tu plan (actualmente tienes {$usedVal}). Para subir nuevo contenido debes sustituir o eliminar uno existente o actualizar tu plan.";
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => $quotaError, 'errors' => ['type' => [$quotaError]]], 422);
            }
            return back()->withInput()->withErrors(['type' => $quotaError]);
        }

        $limits = $talent->planLimits();
        $maxStorageBytes = ((int) ($limits['storage_mb'] ?? 0)) * 1024 * 1024;
        $currentStorageBytes = (int) $talent->storageUsed();
        $fileSize = (int) $file->getSize();

        if ($maxStorageBytes > 0 && ($currentStorageBytes + $fileSize) > $maxStorageBytes) {
            $storageError = 'Has superado el límite total de almacenamiento de tu plan.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $storageError, 'errors' => ['file' => [$storageError]]], 422);
            }
            return back()->withInput()->withErrors(['file' => $storageError]);
        }

        try {
            $folder = "talents/{$talent->id}/media/{$validated['type']}";
            $fileUploadService = app(FileUploadService::class);

            $isExclusive = $request->boolean('is_exclusive');
            $isPrivateDoc = $validated['type'] === 'document';
            $isPrivate = $isPrivateDoc || $isExclusive;
            $targetDisk = $isPrivate ? 'r2_private' : 'r2';

            if ($targetDisk === 'r2_private' && ! $fileUploadService->isR2PrivateConfigured()) {
                throw new \RuntimeException('El almacenamiento privado R2 no está configurado explícitamente (R2_PRIVATE_BUCKET ausente).');
            }
            if ($targetDisk === 'r2' && ! $fileUploadService->isR2Configured() && ! app()->environment('testing')) {
                throw new \RuntimeException('El servicio de almacenamiento en la nube R2 no está disponible.');
            }

            $upload = $fileUploadService->upload($file, $folder, $targetDisk);

            $isPending = app(\App\Services\ModerationService::class)->needsModeration('media');

            $media = TalentMedia::query()->create([
                'talent_id' => $talent->id,
                'type' => $validated['type'],
                'filename' => $file->getClientOriginalName(),
                'disk' => $targetDisk,
                'backblaze_key' => $upload['key'],
                'url' => $upload['url'],
                'title' => $validated['title'] ?? null,
                'description' => $validated['description'] ?? null,
                'mime_type' => $mime,
                'size' => $fileSize,
                'is_exclusive' => $isExclusive,
                'status' => $isPending ? 'pending' : 'approved',
            ]);

            // Para archivos privados o exclusivos: la URL de acceso es la ruta protegida autenticada
            if ($isExclusive) {
                $media->update([
                    'url' => route('talents.media.stream', ['id' => $media->id]),
                ]);
            } elseif ($isPrivateDoc) {
                $media->update([
                    'url' => route('talents.media.download', ['id' => $media->id]),
                ]);
            }

            app(\App\Services\ModerationService::class)->registerIfRequired('media', [
                'subject_type' => TalentMedia::class,
                'subject_id' => $media->id,
                'title' => "Archivo multimedia de talento: {$media->title}",
                'summary' => "Tipo: {$media->type}",
                'submitter_name' => $talent->band_name,
                'submitter_email' => $talent->email,
            ]);
        } catch (\Throwable $e) {
            Log::error('Talent Media Upload Failed', [
                'talent_id' => $talent->id,
                'type' => $validated['type'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            $friendlyMsg = str_contains($e->getMessage(), 'R2_PRIVATE_BUCKET')
                ? 'El almacenamiento privado no está configurado (R2_PRIVATE_BUCKET ausente).'
                : 'Error en el almacenamiento en la nube (R2): no se pudo guardar el archivo. Por favor, inténtalo más tarde.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $friendlyMsg, 'errors' => ['file' => [$friendlyMsg]]], 422);
            }

            return back()->withInput()->withErrors([
                'file' => $friendlyMsg,
            ]);
        }

        $statusMsg = $media->status === 'approved'
            ? 'Archivo subido y publicado correctamente.'
            : 'Archivo subido correctamente. Pendiente de revisión editorial.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $statusMsg,
                'status' => $media->status,
                'status_label' => $media->status === 'approved' ? 'Publicado' : 'Pendiente de revisión',
            ]);
        }

        return redirect()->route('talents.media.index')->with('status', $statusMsg);
    }

    public function stream(string $id, Request $request): StreamedResponse
    {
        /** @var \App\Models\Talent|null $talent */
        $talent = Auth::guard('talent')->user();
        $webUser = Auth::guard('web')->user();
        $isAdmin = (bool) ($webUser?->isAdmin() ?? false);

        $media = TalentMedia::query()->whereKey((int) $id)->firstOrFail();

        // Control estricto de autorización por tipo y exclusividad
        if ($media->is_exclusive) {
            $isOwner = $talent && (int) $talent->id === (int) $media->talent_id;
            $isAuthenticatedUser = $webUser !== null;
            if (! $isAdmin && ! $isOwner && ! $isAuthenticatedUser) {
                abort(403, 'Contenido exclusivo para usuarios registrados o afiliados.');
            }
        } elseif ($media->type === 'document') {
            $isOwner = $talent && (int) $talent->id === (int) $media->talent_id;
            if (! $isAdmin && ! $isOwner) {
                abort(403, 'No tienes autorización para acceder a este documento.');
            }
        }

        $disk = $media->resolvedDisk();
        if (! $disk) {
            abort(404, 'Este archivo no tiene un disco de almacenamiento demostrado.');
        }
        $storage = Storage::disk($disk);

        if (! $storage->exists($media->backblaze_key)) {
            abort(404, 'El archivo solicitado no se encuentra en el almacenamiento.');
        }

        $mime = $media->mime_type ?: $storage->mimeType($media->backblaze_key) ?: 'application/octet-stream';
        $stream = $storage->readStream($media->backblaze_key);
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition . '; filename="' . addslashes($media->filename) . '"',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }

    public function download(string $id): StreamedResponse
    {
        /** @var \App\Models\Talent|null $talent */
        $talent = Auth::guard('talent')->user();
        $webUser = Auth::guard('web')->user();
        $isAdmin = (bool) ($webUser?->isAdmin() ?? false);

        $media = TalentMedia::query()->whereKey((int) $id)->firstOrFail();

        if ($media->is_exclusive) {
            $isOwner = $talent && (int) $talent->id === (int) $media->talent_id;
            $isAuthenticatedUser = $webUser !== null;
            if (! $isAdmin && ! $isOwner && ! $isAuthenticatedUser) {
                abort(403, 'Contenido exclusivo para usuarios registrados o afiliados.');
            }
        } elseif (! $isAdmin && (! $talent || (int) $talent->id !== (int) $media->talent_id)) {
            abort(403, 'No tienes autorización para acceder a este archivo.');
        }

        $disk = $media->resolvedDisk();
        if (! $disk) {
            abort(404, 'Este archivo no tiene un disco de almacenamiento demostrado.');
        }
        $storage = Storage::disk($disk);

        if (! $storage->exists($media->backblaze_key)) {
            abort(404, 'El archivo solicitado no se encuentra en el almacenamiento.');
        }

        return $storage->download($media->backblaze_key, $media->filename);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        return $this->upload($request);
    }

    public function destroy(string $id): RedirectResponse
    {
        $talent = Auth::guard('talent')->user();
        if (! $talent) {
            abort(403);
        }

        $talentMedia = TalentMedia::query()->whereKey((int) $id)->firstOrFail();
        if ((int) $talentMedia->talent_id !== (int) $talent->id) {
            abort(403, 'No tienes autorización para eliminar este archivo.');
        }

        if (! $talentMedia->hasDemonstratedDisk()) {
            Log::warning("MediaController::destroy: registro sin disco demostrado bloquea la eliminación", [
                'media_id' => $talentMedia->id,
                'talent_id' => $talent->id,
            ]);
            return back()->withErrors(['file' => 'Este archivo histórico no tiene un disco demostrado registrado. Su eliminación está bloqueada para proteger la integridad del almacenamiento.']);
        }

        $disk = (string) $talentMedia->resolvedDisk();
        $key = $talentMedia->backblaze_key;

        // Proteger objetos compartidos: no borrar si otro medio activo utiliza la misma clave en el mismo disco
        $isShared = TalentMedia::query()
            ->where('id', '!=', $talentMedia->id)
            ->where('backblaze_key', $key)
            ->where(function ($q) use ($disk): void {
                $q->where('disk', $disk)
                  ->orWhereNull('disk');
            })
            ->exists();

        if (filled($key) && ! $isShared) {
            try {
                $storage = Storage::disk($disk);
                if ($storage->exists($key)) {
                    $result = $storage->delete($key);
                    if ($result === false) {
                        Log::error("MediaController::destroy: el almacenamiento retornó false al borrar", [
                            'media_id' => $talentMedia->id,
                            'disk' => $disk,
                            'key' => $key,
                        ]);
                        return back()->withErrors(['file' => 'No se pudo eliminar el archivo físico del almacenamiento. El registro se ha conservado.']);
                    }
                }
            } catch (\Throwable $e) {
                Log::error("MediaController::destroy: error al eliminar archivo físico", [
                    'media_id' => $talentMedia->id,
                    'disk' => $disk,
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
                return back()->withErrors(['file' => 'Error de conexión con el almacenamiento. El registro se ha conservado.']);
            }
        }

        $talentMedia->delete();

        return redirect()->route('talents.media.index')->with('status', 'Archivo eliminado.');
    }
}
