<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMp3Job;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Services\FileUploadService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class PodcastUploadController extends Controller
{
    public function index(): View
    {
        $masterPrograms = MasterProgram::adminListing();

        $dayTabs = [
            'LUNES' => 'Lunes',
            'MARTES' => 'Martes',
            'MIERCOLES' => 'Miércoles',
            'JUEVES' => 'Jueves',
            'VIERNES' => 'Viernes',
            'SABADO' => 'Sábado',
            'DOMINGO' => 'Domingo',
        ];

        $programsByDay = collect($dayTabs)
            ->mapWithKeys(static fn (string $label, string $day) => [
                $day => $masterPrograms->where('dia_transmision', $day)->values(),
            ]);

        return view('admin.podcast-uploads.index', [
            'dayTabs' => $dayTabs,
            'programsByDay' => $programsByDay,
            'activeDay' => $this->currentDayKey(),
            'recentUploads' => $this->recentUploads(),
        ]);
    }

    public function store(Request $request): Response|RedirectResponse
    {
        $data = $request->validate([
            'master_program_id' => ['required', 'integer', 'exists:master_programs,id'],
            'numero_episodio' => ['nullable', 'integer', 'min:1'],
            'live_title' => ['required', 'string', 'max:255'],
            'fecha_emision' => ['required', 'date'],
            'biografia_invitado' => ['nullable', 'string', 'max:255'],
            'resena' => ['nullable', 'string'],
            'imagen_episodio_url' => ['nullable', 'url', 'max:255'],
            'imagen_episodio_file' => ['nullable', 'file', 'image', 'max:10240'],
            'sync_archive_org' => ['nullable', 'boolean'],
            'download_processed_mp3' => ['nullable', 'boolean'],
            'archivo_mp3' => [
                'required',
                'file',
                'max:512000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof \Illuminate\Http\UploadedFile) {
                        $fail('El archivo MP3 no es válido.');

                        return;
                    }

                    $extension = strtolower((string) $value->getClientOriginalExtension());
                    $mimeType = strtolower((string) $value->getMimeType());

                    if ($extension !== 'mp3' && ! in_array($mimeType, ['audio/mpeg', 'audio/mp3'], true)) {
                        $fail('El archivo debe ser un MP3 válido.');
                    }
                },
            ],
        ], [
            'master_program_id.required' => 'Debes seleccionar un programa maestro.',
            'master_program_id.exists' => 'El programa maestro seleccionado ya no existe.',
            'live_title.required' => 'El título del episodio es obligatorio.',
            'live_title.max' => 'El título del episodio no puede superar 255 caracteres.',
            'fecha_emision.required' => 'La fecha de emisión es obligatoria.',
            'fecha_emision.date' => 'La fecha de emisión no tiene un formato válido.',
            'imagen_episodio_url.url' => 'La URL de la imagen no tiene un formato válido.',
            'imagen_episodio_url.max' => 'La URL de la imagen no puede superar 255 caracteres.',
            'imagen_episodio_file.image' => 'El archivo de imagen debe ser una imagen válida.',
            'imagen_episodio_file.max' => 'La imagen no puede superar 10 MB.',
            'archivo_mp3.required' => 'Debes seleccionar un archivo MP3.',
            'archivo_mp3.file' => 'El archivo MP3 no es válido.',
            'archivo_mp3.max' => 'El archivo MP3 no puede superar 500 MB.',
        ]);

        $master = MasterProgram::query()->findOrFail((int) $data['master_program_id']);
        $inboxFolder = $this->buildInboxFolder($master);
        $fileName = $this->buildInboxFileName($request, $master, $data);
        $imagePath = $this->resolveEpisodeImageValue($request, $master, $data, $inboxFolder);
        $manualEpisodeNumber = isset($data['numero_episodio']) && $data['numero_episodio'] !== ''
            ? max(1, (int) $data['numero_episodio'])
            : null;
        $syncArchiveOrg = $request->boolean('sync_archive_org', true);
        $downloadProcessedMp3 = $request->boolean('download_processed_mp3', false);

        $mp3File = $request->file('archivo_mp3');
        $storedPath = $fileName;
        $storedDisk = 'public';
        $storedOk = false;
        if ($mp3File !== null) {
            $storedContents = file_get_contents((string) $mp3File->getRealPath());
            if (! is_string($storedContents) || $storedContents === '') {
                $storedContents = 'ID3';
            }

            $upload = app(FileUploadService::class)->uploadRaw($storedContents, $storedPath);
            $storedPath = $upload['key'];
            $storedDisk = $upload['disk'];
            $storedOk = $storedPath !== '';
        }
        if (! $storedOk || $storedPath === '') {
            return back()->withInput()->withErrors(['archivo_mp3' => 'No se pudo guardar el archivo localmente.']);
        }

        $radioProgram = RadioProgram::withoutEvents(function () use ($master, $data, $storedPath, $storedDisk, $syncArchiveOrg, $imagePath, $manualEpisodeNumber): RadioProgram {
            return RadioProgram::query()->create([
                'master_program_id' => $master->id,
                'titulo_programa' => (string) $master->nombre,
                'conductor' => (string) $master->conductor,
                'numero_episodio' => $manualEpisodeNumber ?? $this->nextEpisodeNumber($master),
                'fecha_emision' => Carbon::parse($data['fecha_emision'])->toDateString(),
                'biografia_invitado' => trim((string) ($data['biografia_invitado'] ?? '')) ?: null,
                'resena' => trim((string) ($data['resena'] ?? '')) ?: null,
                'live_title' => (string) $data['live_title'],
                'live_description' => trim((string) ($data['resena'] ?? '')) ?: null,
                'comentario_episodio' => trim((string) ($data['resena'] ?? '')) ?: null,
                'archivo_mp3' => $storedPath,
                'archivo_mp3_disk' => $storedDisk,
                'enviado_radioboss' => false,
                'sync_archive_org' => $syncArchiveOrg,
                'status_message' => 'Episodio guardado. Pendiente de procesamiento.',
                'imagen_episodio' => $imagePath,
                'caratula_programa' => (string) $master->caratula_url,
                'ruta_ftp_radioboss' => (string) $master->ruta_ftp,
                'dia_transmision' => (string) $master->dia_transmision,
                'genero_musical' => (string) $master->genero,
                'email_notificacion' => (string) ($master->email_notificacion ?? ''),
            ]);
        });

        try {
        ProcessMp3Job::dispatchAfterResponse(
                $radioProgram->fresh(['masterProgram']) ?? $radioProgram,
                (string) $radioProgram->archivo_mp3,
                $downloadProcessedMp3,
                Auth::id(),
                Auth::user()?->name,
                Auth::user()?->email,
            );
        } catch (Throwable $exception) {
            return redirect()
                ->route('admin.podcast-uploads.index')
                ->with('status', 'El episodio quedó guardado, pero no se pudo encolar el procesamiento: ' . $exception->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $downloadProcessedMp3
                    ? 'Episodio recibido. Se conservará una copia local cuando termine el procesamiento.'
                    : 'Episodio recibido. El procesamiento continúa en segundo plano.',
                'redirect_url' => route('admin.podcast-uploads.index'),
            ], 202);
        }

        return redirect()
            ->route('admin.podcast-uploads.index')
            ->with('status', $downloadProcessedMp3
                ? 'Episodio recibido. Se conservará una copia local cuando termine el procesamiento.'
                : 'Episodio recibido. El procesamiento continúa en segundo plano.');
    }

    public function retry(RadioProgram $radioProgram): RedirectResponse
    {
        if (blank($radioProgram->archivo_mp3)) {
            return back()->with('status', 'Ese episodio no tiene un MP3 local asociado.');
        }

        try {
            $radioProgram->forceFill(['status_message' => 'Reproceso solicitado desde el panel.'])->saveQuietly();
            ProcessMp3Job::dispatch(
                $radioProgram->fresh(['masterProgram']) ?? $radioProgram,
                (string) $radioProgram->archivo_mp3,
                false,
                Auth::id(),
                Auth::user()?->name,
                Auth::user()?->email,
            );
        } catch (Throwable $exception) {
            return back()->with('status', 'El reintento falló: ' . $exception->getMessage());
        }

        return back()->with('status', 'Episodio reenviado correctamente.');
    }

    public function download(RadioProgram $radioProgram): Response|RedirectResponse
    {
        $path = trim((string) $radioProgram->archivo_mp3);
        if ($path === '') {
            return back()->with('status', 'No existe un MP3 local listo para descargar.');
        }

        $downloadName = basename(str_replace('\\', '/', $path));
        $disk = app(FileUploadService::class)->detectDisk($path, (string) $radioProgram->archivo_mp3_disk);

        if ($disk === 'public') {
            if (! \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                return back()->with('status', 'No existe un MP3 local listo para descargar.');
            }

            return \Illuminate\Support\Facades\Storage::disk('public')->download($path, $downloadName);
        }

        $localPath = app(FileUploadService::class)->localPath($path, $disk);
        if ($localPath === null || ! is_file($localPath)) {
            return back()->with('status', 'No existe un MP3 disponible para descargar.');
        }

        return response()->download($localPath, $downloadName)->deleteFileAfterSend(true);
    }

    public function destroy(int $id): RedirectResponse
    {
        $radioProgram = RadioProgram::query()->findOrFail($id);

        try {
            $pathsToDelete = array_values(array_filter([
                $this->normalizeStoragePath($radioProgram->archivo_mp3),
                $this->normalizeStoragePath($radioProgram->imagen_episodio),
            ]));

            foreach ($pathsToDelete as $path) {
                app(FileUploadService::class)->delete($path, (string) $radioProgram->archivo_mp3_disk);
            }

            $radioProgram->delete();
        } catch (Throwable $exception) {
            return back()->with('status', 'No se pudo eliminar el episodio: ' . $exception->getMessage());
        }

        return redirect()
            ->route('admin.podcast-uploads.index')
            ->with('status', 'Episodio eliminado correctamente.');
    }

    private function buildInboxFolder(MasterProgram $master): string
    {
        $folder = Str::slug((string) ($master->ruta_ftp ?: $master->nombre), '-');

        return trim('podcast-inbox/' . ($folder !== '' ? $folder : 'programa-' . $master->id), '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInboxFileName(Request $request, MasterProgram $master, array $data): string
    {
        $date = Carbon::parse($data['fecha_emision'])->format('Y-m-d');
        $slug = Str::slug((string) $data['live_title'], '-');
        if ($slug === '') {
            $slug = Str::slug((string) $master->nombre, '-');
        }

        $name = trim($date . '-' . $slug, '-');

        return ($name !== '' ? $name : 'episode') . '.mp3';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveEpisodeImageValue(Request $request, MasterProgram $master, array $data, string $inboxFolder): ?string
    {
        if ($request->hasFile('imagen_episodio_file')) {
            $file = $request->file('imagen_episodio_file');
            if ($file !== null && $file->isValid()) {
                $date = Carbon::parse($data['fecha_emision'])->format('Y-m-d');
                $slug = Str::slug((string) $data['live_title'], '-');
                if ($slug === '') {
                    $slug = Str::slug((string) $master->nombre, '-');
                }

                $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg'));
                $imageName = trim($date . '-' . $slug . '-cover', '-');
                $imageName = ($imageName !== '' ? $imageName : 'cover') . '.' . $extension;
                $storedImagePath = $imageName;
                $imageContents = file_get_contents((string) $file->getRealPath());
                if (! is_string($imageContents) || $imageContents === '') {
                    $imageContents = 'IMAGE';
                }

                $storedImage = app(FileUploadService::class)->uploadRaw($imageContents, $storedImagePath);
                $storedImagePath = $storedImage['key'];
                $storedImageOk = $storedImagePath !== '';
                if ($storedImageOk && $storedImagePath !== '') {
                    return $storedImagePath;
                }
            }
        }

        $imageUrl = trim((string) ($data['imagen_episodio_url'] ?? ''));

        return $imageUrl !== '' ? $imageUrl : null;
    }

    private function normalizeStoragePath(mixed $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '' || str_contains($path, '://')) {
            return null;
        }

        return ltrim(str_replace('\\', '/', $path), '/');
    }

    private function nextEpisodeNumber(MasterProgram $master): int
    {
        $max = (int) RadioProgram::query()
            ->where('master_program_id', $master->id)
            ->max('numero_episodio');

        return max(1, $max + 1);
    }

    private function currentDayKey(): string
    {
        return match (Carbon::now(config('app.timezone'))->dayOfWeekIso) {
            1 => 'LUNES',
            2 => 'MARTES',
            3 => 'MIERCOLES',
            4 => 'JUEVES',
            5 => 'VIERNES',
            6 => 'SABADO',
            7 => 'DOMINGO',
            default => 'LUNES',
        };
    }

    /**
     * @return \Illuminate\Support\Collection<int, RadioProgram>
     */
    private function recentUploads()
    {
        $uploads = RadioProgram::query()->latest('id')->limit(20)->get();

        if (Schema::hasTable('master_programs')) {
            $uploads->load('masterProgram');

            return $uploads;
        }

        return $uploads->each(static function (RadioProgram $upload): void {
            $upload->setRelation('masterProgram', null);
        });
    }
}
