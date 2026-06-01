<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\NotifyPodcastReadyJob;
use App\Jobs\ProcessMp3Job;
use App\Jobs\UploadArchiveOrgJob;
use App\Jobs\UploadRadiobossJob;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Models\ThemeSetting;
use App\Services\FileUploadService;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class PodcastUploadController extends Controller
{
    public function manual(): View
    {
        return view('admin.podcast-uploads-manual', $this->manualViewData());
    }

    public function manualPdf(): Response
    {
        $options = new Options();
        $options->setIsRemoteEnabled(true);
        $options->set('isHtml5ParserEnabled', true);

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('admin.podcast-uploads-manual-pdf', $this->manualViewData())->render());
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="seven-rock-radio-podcast-uploads-manual.pdf"',
        ]);
    }

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

        $suggestedEpisodeNumber = max(
            1,
            ((int) RadioProgram::query()->max('numero_episodio')) + 1
        );

        return view('admin.podcast-uploads.index', [
            'dayTabs' => $dayTabs,
            'programsByDay' => $programsByDay,
            'activeDay' => $this->currentDayKey(),
            'suggestedEpisodeNumber' => $suggestedEpisodeNumber,
            'recentUploads' => $this->recentUploads(),
            'recentPublishedUploads' => $this->recentPublishedUploads(),
        ]);
    }

    public function published(): View
    {
        return view('admin.podcast-uploads-published', [
            'recentPublishedUploads' => $this->recentPublishedUploads(),
        ]);
    }

    public function publishedPrint(): View
    {
        return view('admin.podcast-uploads-published-print', [
            'recentPublishedUploads' => $this->recentPublishedUploads(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function manualViewData(): array
    {
        $themeSettings = ThemeSetting::current();

        return [
            'themeSettings' => $themeSettings,
            'themeAppearance' => [
                'admin_texts' => $themeSettings->adminTexts(),
            ],
            'dayTabs' => [
                'LUNES' => 'Lunes',
                'MARTES' => 'Martes',
                'MIERCOLES' => 'Miércoles',
                'JUEVES' => 'Jueves',
                'VIERNES' => 'Viernes',
                'SABADO' => 'Sábado',
                'DOMINGO' => 'Domingo',
            ],
        ];
    }

    public function recentUploadsFragment(): View
    {
        return view('admin.podcast-uploads.partials.recent-uploads', [
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
            'pipeline_action' => ['nullable', Rule::in(['save', 'process'])],
            'archivo_mp3' => [
                'required',
                'file',
                'max:512000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
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
        $rawFileName = $this->buildRawFileName($data);
        $rawPath = $this->storeRawMp3(
            $request->file('archivo_mp3'),
            $rawFileName,
        );

        if ($rawPath === '') {
            return back()->withInput()->withErrors(['archivo_mp3' => 'No se pudo guardar el archivo localmente.']);
        }

        $imagePath = $this->resolveEpisodeImageValue($request, $master, $data);
        $manualEpisodeNumber = isset($data['numero_episodio']) && $data['numero_episodio'] !== ''
            ? max(1, (int) $data['numero_episodio'])
            : null;
        $syncArchiveOrg = $request->boolean('sync_archive_org', true);
        $downloadProcessedMp3 = $request->boolean('download_processed_mp3', false);
        $pipelineAction = strtolower(trim((string) $request->input('pipeline_action', 'process')));
        $shouldProcessPipeline = $pipelineAction !== 'save';

        $radioProgram = RadioProgram::withoutEvents(function () use ($master, $data, $rawPath, $syncArchiveOrg, $imagePath, $manualEpisodeNumber, $downloadProcessedMp3, $shouldProcessPipeline): RadioProgram {
            $radioBossaStatus = $shouldProcessPipeline ? 'pending' : 'skipped';
            $archiveStatus = $shouldProcessPipeline ? 'pending' : 'skipped';
            $deliveryStatus = $shouldProcessPipeline ? 'pending' : 'skipped';
            $statusMessage = $shouldProcessPipeline
                ? 'Episodio recibido. Procesamiento en segundo plano.'
                : 'Episodio guardado como borrador. Pendiente de procesar.';

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
                'archivo_mp3' => $rawPath,
                'archivo_mp3_disk' => 'public',
                'enviado_radioboss' => false,
                'radioboss_status' => $radioBossaStatus,
                'sync_archive_org' => $syncArchiveOrg,
                'archive_org_status' => $archiveStatus,
                'delivery_status' => $deliveryStatus,
                'status_message' => $statusMessage,
                'imagen_episodio' => $imagePath,
                'caratula_programa' => (string) $master->caratula_url,
                'ruta_ftp_radioboss' => (string) $master->ruta_ftp,
                'dia_transmision' => (string) $master->dia_transmision,
                'genero_musical' => (string) $master->genero,
                'email_notificacion' => (string) ($master->email_notificacion ?? ''),
                'delivery_metadata' => [
                    'preserve_local_copy' => $downloadProcessedMp3,
                    'pipeline' => $shouldProcessPipeline ? 'chain' : 'save-only',
                    'source' => 'admin-podcast-uploads',
                ],
            ]);
        });

        $message = 'Episodio guardado correctamente.';
        if ($shouldProcessPipeline) {
            $this->dispatchPodcastPipeline(
                radioProgram: $radioProgram->fresh(['masterProgram']) ?? $radioProgram,
                sourcePath: $rawPath,
                preserveLocalCopy: $downloadProcessedMp3,
                dispatchNextJobs: false,
            );

            $message = $downloadProcessedMp3
                ? 'Episodio recibido. Se conservará una copia local cuando termine el procesamiento.'
                : 'Episodio recibido. El procesamiento continúa en segundo plano.';
        } else {
            $message = 'Episodio guardado como borrador. El pipeline no se ha iniciado.';
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $message,
                'redirect_url' => route('admin.podcast-uploads.index'),
                'pipeline_action' => $shouldProcessPipeline ? 'process' : 'save',
            ], $shouldProcessPipeline ? 202 : 201);
        }

        return back()->with('status', $message);
    }

    public function retry(RadioProgram $radioProgram): RedirectResponse
    {
        if (blank($radioProgram->archivo_mp3)) {
            return back()->with('status', 'Ese episodio no tiene un MP3 local asociado.');
        }

        try {
            $retryPlan = $this->buildSelectiveRetryPlan($radioProgram);

            if ($retryPlan['jobs'] === []) {
                return back()->with('status', 'No hay errores pendientes que reprocesar.');
            }

            $radioProgram->forceFill([
                'status_message' => 'Reintento selectivo solicitado desde el panel.',
            ])->saveQuietly();

            Bus::chain($retryPlan['jobs'])
                ->catch(function (Throwable $exception) use ($radioProgram): void {
                    Log::error('PodcastUploadController: fallo el reintento selectivo del podcast.', [
                        'program_id' => $radioProgram->id,
                        'message' => $exception->getMessage(),
                        'exception_class' => get_class($exception),
                    ]);

                    RadioProgram::withoutEvents(function () use ($radioProgram, $exception): void {
                        $radioProgram->forceFill([
                            'status_message' => 'El reintento selectivo del podcast falló.',
                            'delivery_last_error' => $exception->getMessage(),
                            'delivery_status' => 'failed',
                        ])->saveQuietly();
                    });
                })
                ->dispatch();
        } catch (Throwable $exception) {
            return back()->with('status', 'El reintento falló: ' . $exception->getMessage());
        }

        return back()->with('status', $retryPlan['message']);
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
            if (! Storage::disk('public')->exists($path)) {
                return back()->with('status', 'No existe un MP3 local listo para descargar.');
            }

            return Storage::disk('public')->download($path, $downloadName);
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

    /**
     * Dispara la cadena de micro-tareas del podcast.
     */
    private function dispatchPodcastPipeline(
        RadioProgram $radioProgram,
        string $sourcePath,
        bool $preserveLocalCopy,
        bool $dispatchNextJobs,
    ): void {
        $audioSource = $radioProgram->fresh(['masterProgram']) ?? $radioProgram;

        Bus::chain([
            new ProcessMp3Job(
                $audioSource,
                $sourcePath,
                $preserveLocalCopy,
                Auth::id(),
                Auth::user()?->name,
                Auth::user()?->email,
                $dispatchNextJobs,
            ),
            new UploadRadiobossJob($radioProgram->id),
            new UploadArchiveOrgJob($radioProgram->id),
            new NotifyPodcastReadyJob($radioProgram->id),
        ])
            ->catch(function (Throwable $exception) use ($radioProgram): void {
                Log::error('PodcastUploadController: fallo la cadena de subida del podcast.', [
                    'program_id' => $radioProgram->id,
                    'message' => $exception->getMessage(),
                    'exception_class' => get_class($exception),
                ]);

                RadioProgram::withoutEvents(function () use ($radioProgram, $exception): void {
                    $radioProgram->forceFill([
                        'status_message' => 'La cadena de procesamiento del podcast falló.',
                        'delivery_last_error' => $exception->getMessage(),
                        'delivery_status' => 'failed',
                    ])->saveQuietly();
                });
            })
            ->dispatch();
    }

    /**
     * @return array{jobs: array<int, object>, message: string}
     */
    private function buildSelectiveRetryPlan(RadioProgram $radioProgram): array
    {
        $jobs = [];
        $labels = [];

        $radiobossStatus = trim((string) ($radioProgram->radioboss_status ?? ''));
        $archiveStatus = trim((string) ($radioProgram->archive_org_status ?? ''));
        $deliveryStatus = trim((string) ($radioProgram->delivery_status ?? ''));

        $retryRadioboss = ! $radioProgram->enviado_radioboss || in_array($radiobossStatus, ['error', 'failed'], true);
        $retryArchive = in_array($archiveStatus, ['error', 'failed', 'pending'], true);
        $retryDelivery = in_array($deliveryStatus, ['failed', 'partial'], true);

        if ($retryRadioboss) {
            $jobs[] = new UploadRadiobossJob($radioProgram->id);
            $labels[] = 'RadioBOSS';
        }

        if ($retryArchive) {
            $jobs[] = new UploadArchiveOrgJob($radioProgram->id);
            $labels[] = 'Archive.org';
        }

        if ($retryRadioboss || $retryArchive || $retryDelivery) {
            $jobs[] = new NotifyPodcastReadyJob($radioProgram->id);
            $labels[] = 'notificación';
        }

        $message = $labels !== []
            ? 'Reintento selectivo enviado para ' . implode(', ', $labels) . '.'
            : 'No hay errores pendientes que reprocesar.';

        return [
            'jobs' => $jobs,
            'message' => $message,
        ];
    }

    private function buildRawFileName(array $data): string
    {
        $date = Carbon::parse((string) $data['fecha_emision'])->format('Y-m-d');
        $slug = Str::slug((string) $data['live_title'], '-');
        if ($slug === '') {
            $slug = 'episode';
        }

        return trim(sprintf('%s-%s-%s.mp3', $date, $slug, Str::lower(Str::uuid()->toString())), '-');
    }

    private function storeRawMp3(?UploadedFile $file, string $fileName): string
    {
        if ($file === null || ! $file->isValid()) {
            return '';
        }

        $stored = Storage::disk('public')->putFileAs('podcast-inbox', $file, $fileName);

        return is_string($stored) ? trim($stored, '/') : '';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveEpisodeImageValue(Request $request, MasterProgram $master, array $data): ?string
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
                $imageName = trim(sprintf('%s-%s-cover-%s.%s', $date, $slug !== '' ? $slug : 'cover', Str::lower(Str::uuid()->toString()), $extension), '-');
                $stored = Storage::disk('public')->putFileAs('podcast-inbox/covers', $file, $imageName);

                if (is_string($stored) && $stored !== '') {
                    return trim($stored, '/');
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
        $uploads = RadioProgram::query()
            ->orderByRaw(
                "CASE
                    WHEN COALESCE(radioboss_status, '') = 'skipped'
                     AND COALESCE(archive_org_status, '') = 'skipped'
                     AND COALESCE(delivery_status, '') = 'skipped'
                    THEN 0
                    ELSE 1
                 END"
            )
            ->latest('id')
            ->limit(20)
            ->get();

        if (Schema::hasTable('master_programs')) {
            $uploads->load('masterProgram');

            return $uploads;
        }

        return $uploads->each(static function (RadioProgram $upload): void {
            $upload->setRelation('masterProgram', null);
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, RadioProgram>
     */
    private function recentPublishedUploads()
    {
        $uploads = RadioProgram::query()
            ->where('delivery_status', 'verified')
            ->orderByDesc('delivery_verified_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        if (Schema::hasTable('master_programs')) {
            $uploads->load('masterProgram');

            return $uploads;
        }

        return $uploads->each(static function (RadioProgram $upload): void {
            $upload->setRelation('masterProgram', null);
        });
    }
}
