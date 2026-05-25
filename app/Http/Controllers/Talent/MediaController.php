<?php

declare(strict_types=1);

namespace App\Http\Controllers\Talent;

use App\Http\Controllers\Controller;
use App\Models\TalentMedia;
use App\Services\BackblazeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function index(): View
    {
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

        return view('talentos.media', [
            'talent' => $talent,
            'media' => $media,
            'limits' => $limits,
            'usage' => $usage,
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $talent = Auth::guard('talent')->user();
        if (! $talent) {
            return redirect()->route('talents.login');
        }

        if (! in_array($talent->subscription_status, ['active'], true)) {
            return back()->withErrors([
                'file' => 'Tu suscripción está suspendida. Reactiva tu plan para subir contenido.',
            ]);
        }

        $limits = $talent->planLimits();
        $maxFileKb = max(1, (int) ($limits['storage_mb'] ?? 50) * 1024);

        $validated = $request->validate([
            'type' => ['required', 'in:photo,mp3,document,video'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file' => ['required', 'file', 'max:' . $maxFileKb],
        ]);

        try {
            $file = $request->file('file');
            if (! $talent->canUpload((string) $validated['type'])) {
                return back()->withInput()->withErrors([
                    'type' => 'Tu plan ya alcanzó el límite para este tipo de contenido.',
                ]);
            }

            $maxStorageBytes = ((int) ($limits['storage_mb'] ?? 0)) * 1024 * 1024;
            $currentStorageBytes = (int) $talent->storageUsed();
            $fileSize = (int) $file->getSize();

            if ($maxStorageBytes > 0 && ($currentStorageBytes + $fileSize) > $maxStorageBytes) {
                return back()->withInput()->withErrors([
                    'file' => 'Has superado el límite de almacenamiento de tu plan.',
                ]);
            }

            $folder = "talents/{$talent->id}/media/{$validated['type']}";
            $upload = app(BackblazeService::class)->upload($file, $folder);

            TalentMedia::query()->create([
                'talent_id' => $talent->id,
                'type' => $validated['type'],
                'filename' => $file->getClientOriginalName(),
                'backblaze_key' => $upload['key'],
                'url' => $upload['url'],
                'title' => $validated['title'] ?? null,
                'description' => $validated['description'] ?? null,
                'mime_type' => (string) $file->getMimeType(),
                'size' => $fileSize,
            ]);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors([
                'file' => 'No se pudo subir el archivo.',
            ]);
        }

        return redirect()->route('talents.media.index')->with('status', 'Archivo subido.');
    }

    public function store(Request $request): RedirectResponse
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
            abort(403);
        }

        try {
            app(BackblazeService::class)->delete($talentMedia->backblaze_key);
        } catch (\Throwable) {
            //
        }

        $talentMedia->delete();

        return redirect()->route('talents.media.index')->with('status', 'Archivo eliminado.');
    }
}
