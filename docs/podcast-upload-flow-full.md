# Flujo completo de subida de podcasts

Este archivo contiene, sin omitir contenido, la lógica completa del flujo de subida de MP3 a RadioBOSS y Archive.org, junto con el formulario, la validación del cliente y las pruebas asociadas.

## app/Http/Controllers/Admin/PodcastUploadController.php

`$lang
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\UploadMp3Job;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class PodcastUploadController extends Controller
{
    public function index(): View
    {
        $masterPrograms = MasterProgram::query()
            ->orderByRaw("CASE dia_transmision
                WHEN 'LUNES' THEN 1
                WHEN 'MARTES' THEN 2
                WHEN 'MIERCOLES' THEN 3
                WHEN 'JUEVES' THEN 4
                WHEN 'VIERNES' THEN 5
                WHEN 'SABADO' THEN 6
                WHEN 'DOMINGO' THEN 7
                ELSE 99
            END")
            ->orderByRaw("COALESCE(hora_transmision, '99:99:99')")
            ->orderBy('nombre')
            ->get();

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
            'recentUploads' => RadioProgram::query()->with('masterProgram')->latest('id')->limit(20)->get(),
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
            'archivo_mp3' => ['required', 'file', 'mimetypes:audio/mpeg,audio/mp3', 'max:512000'],
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
            'archivo_mp3.mimetypes' => 'El archivo debe ser un MP3 válido.',
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

        $storedPath = Storage::disk('public')->putFileAs($inboxFolder, $request->file('archivo_mp3'), $fileName);
        if (! is_string($storedPath) || $storedPath === '') {
            return back()->withInput()->withErrors(['archivo_mp3' => 'No se pudo guardar el archivo localmente.']);
        }

        $radioProgram = RadioProgram::withoutEvents(function () use ($master, $data, $storedPath, $syncArchiveOrg, $imagePath, $manualEpisodeNumber): RadioProgram {
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
                'enviado_radioboss' => false,
                'sync_archive_org' => $syncArchiveOrg,
                'imagen_episodio' => $imagePath,
                'caratula_programa' => (string) $master->caratula_url,
                'ruta_ftp_radioboss' => (string) $master->ruta_ftp,
                'dia_transmision' => (string) $master->dia_transmision,
                'genero_musical' => (string) $master->genero,
                'email_notificacion' => (string) ($master->email_notificacion ?? ''),
            ]);
        });

        try {
            UploadMp3Job::dispatchAfterResponse(
                $radioProgram->fresh(['masterProgram']) ?? $radioProgram,
                (string) $radioProgram->archivo_mp3,
                $downloadProcessedMp3,
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
            UploadMp3Job::dispatchSync($radioProgram->fresh(['masterProgram']) ?? $radioProgram, (string) $radioProgram->archivo_mp3);
        } catch (Throwable $exception) {
            return back()->with('status', 'El reintento falló: ' . $exception->getMessage());
        }

        return back()->with('status', 'Episodio reenviado correctamente.');
    }

    public function download(RadioProgram $radioProgram): Response|RedirectResponse
    {
        $path = trim((string) $radioProgram->archivo_mp3);
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return back()->with('status', 'No existe un MP3 local listo para descargar.');
        }

        $downloadName = basename(str_replace('\\', '/', $path));

        return Storage::disk('public')->download($path, $downloadName);
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
                $imageFolder = trim($inboxFolder . '/artwork', '/');

                $storedImagePath = Storage::disk('public')->putFileAs($imageFolder, $file, $imageName);
                if (is_string($storedImagePath) && $storedImagePath !== '') {
                    return $storedImagePath;
                }
            }
        }

        $imageUrl = trim((string) ($data['imagen_episodio_url'] ?? ''));

        return $imageUrl !== '' ? $imageUrl : null;
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
}

```

## app/Jobs/UploadMp3Job.php

`$lang
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\ProgramUploadedNotification;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Models\ThemeSetting;
use App\Services\ArchiveOrgPodcastService;
use App\Support\ExternalHttp;
use App\Support\PublicMediaUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class UploadMp3Job implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    private ?string $radiobossError = null;

    public function __construct(
        public RadioProgram $radioProgram,
        public string $localPath,
        public bool $preserveLocalCopy = false,
    ) {
    }

    public function handle(ArchiveOrgPodcastService $archiveOrgPodcastService): void
    {
        ini_set('memory_limit', '512M');
        set_time_limit(600);

        $uploadOk = false;

        try {
            $master = $this->radioProgram->masterProgram
                ?: MasterProgram::query()->find($this->radioProgram->master_program_id);

            $folder = $this->resolveUploadFolder($master);
            $episodeNumber = $this->syncEpisodeNumberBeforeProcessing($master, $folder);
            $numeroEp = str_pad((string) $episodeNumber, 3, '0', STR_PAD_LEFT);
            $nombreProg = strtoupper(trim((string) $this->radioProgram->titulo_programa));
            $fecha = $this->radioProgram->fecha_emision ? $this->radioProgram->fecha_emision->format('d-m-Y') : date('d-m-Y');
            $fechaTitulo = $this->radioProgram->fecha_emision ? $this->radioProgram->fecha_emision->format('d/m/Y') : date('d/m/Y');
            $anio = $this->radioProgram->fecha_emision ? $this->radioProgram->fecha_emision->format('Y') : date('Y');
            $invitado = trim(strip_tags((string) $this->radioProgram->biografia_invitado));

            $nuevoNombre = "{$numeroEp}.- {$nombreProg}" . ($invitado !== '' ? " ({$invitado})" : '') . " {$fecha}.mp3";
            $nuevoNombre = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '', $nuevoNombre);

            $nombreFtp = function_exists('iconv')
                ? (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nuevoNombre) ?: $nuevoNombre)
                : $nuevoNombre;
            $nombreFtp = preg_replace('/[^A-Za-z0-9\-\.\s#\(\)]/', '', (string) $nombreFtp) ?: $nuevoNombre;

            $nuevaRuta = $this->isProcessedLocalBackup($this->localPath)
                ? (string) $this->localPath
                : 'programas_procesados/' . $nombreFtp;

            if (! Storage::disk('public')->exists($this->localPath)) {
                Log::warning("UploadMp3Job: No se encontró el MP3 crudo: {$this->localPath}");

                return;
            }

            if (! $this->isProcessedLocalBackup($this->localPath)) {
                $copied = Storage::disk('public')->copy($this->localPath, $nuevaRuta);
                if (! $copied) {
                    throw new \RuntimeException("UploadMp3Job: No se pudo copiar el archivo de {$this->localPath} a {$nuevaRuta}");
                }

                Storage::disk('public')->delete($this->localPath);

                RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update(['archivo_mp3' => $nuevaRuta]));
            } elseif ((string) $this->radioProgram->archivo_mp3 !== $nuevaRuta) {
                RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update(['archivo_mp3' => $nuevaRuta]));
            }

            $rutaAbsoluta = storage_path('app/public/' . $nuevaRuta);
            $this->escribirMetadata($rutaAbsoluta, $master, $nombreProg, $invitado, $fecha, $fechaTitulo, $anio);

            $fileName = basename($nuevaRuta);
            $remotePath = $folder . '/' . $fileName;
            $ftpHost = trim((string) config('filesystems.disks.radioboss.host', ''));
            $archiveShouldSync = (bool) ($this->radioProgram->sync_archive_org ?? true);

            if ($ftpHost !== '') {
                $uploadOk = $this->uploadToRadiobossWithRetries($folder, $remotePath, $nuevaRuta);

                RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                    'enviado_radioboss' => $uploadOk,
                ]));

                if (! $uploadOk) {
                    Log::warning('RadioBOSS upload failed after retries; keeping local backup.', [
                        'program_id' => $this->radioProgram->id,
                        'remote_path' => $remotePath,
                        'error' => $this->radiobossError,
                    ]);
                }
            } else {
                Log::warning("RADIOBOSS_FTP_SERVER no está configurado. Se omite subida remota para {$fileName}.");
            }

            $archiveUploadOk = false;
            if ($archiveShouldSync && $archiveOrgPodcastService->canSync()) {
                try {
                    $archiveOrgPodcastService->syncEpisode($this->radioProgram->fresh(['masterProgram']) ?? $this->radioProgram);
                    $archiveUploadOk = true;
                } catch (Throwable $archiveError) {
                    Log::warning('UploadMp3Job: fallo la subida a Archive.org', [
                        'program_id' => $this->radioProgram->id,
                        'exception' => $archiveError->getMessage(),
                    ]);

                    RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                        'archive_org_status' => 'error',
                        'archive_org_last_error' => $archiveError->getMessage(),
                        'archive_org_metadata' => array_merge((array) ($this->radioProgram->archive_org_metadata ?? []), [
                            'status' => 'error',
                            'last_error' => $archiveError->getMessage(),
                            'synced_at' => now()->toIso8601String(),
                        ]),
                    ]));
                }
            } elseif ($archiveShouldSync) {
                RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                    'archive_org_status' => 'skipped',
                    'archive_org_last_error' => null,
                    'archive_org_metadata' => array_merge((array) ($this->radioProgram->archive_org_metadata ?? []), [
                        'status' => 'skipped',
                        'last_error' => null,
                        'synced_at' => now()->toIso8601String(),
                    ]),
                ]));
            } else {
                RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                    'archive_org_status' => 'skipped',
                    'archive_org_last_error' => null,
                ]));
            }

            if ($archiveShouldSync && $archiveUploadOk) {
                RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                    'archive_org_status' => 'synced',
                    'archive_org_last_error' => null,
                ]));
            }

            $emailDestinos = $this->resolveNotificationRecipients($master);
            if ($emailDestinos !== []) {
                try {
                    foreach ($emailDestinos as $emailDestino) {
                        Mail::mailer($this->resolveNotificationMailer())
                            ->to($emailDestino)
                            ->send(new ProgramUploadedNotification($fileName, $uploadOk));
                    }
                } catch (Throwable $mailError) {
                    Log::error('UploadMp3Job: fallo enviando email de notificacion', [
                        'program_id' => $this->radioProgram->id,
                        'emails' => $emailDestinos,
                        'file' => $fileName,
                        'uploaded_to_radioboss' => $uploadOk,
                        'exception' => $mailError,
                    ]);
                }
            } else {
                Log::warning('UploadMp3Job: no se envio correo porque no hay un destinatario valido configurado', [
                    'program_id' => $this->radioProgram->id,
                    'master_program_id' => $master?->id,
                    'email_notificacion' => $master?->email_notificacion,
                    'email_copia_notificacion' => $master?->email_copia_notificacion,
                    'global_copy_email' => $this->resolveGlobalNotificationCopyRecipient(),
                    'file' => $fileName,
                ]);
            }

            if ($ftpHost !== '' && $uploadOk && $this->radioProgram->fresh()?->enviado_radioboss && (! $archiveShouldSync || $archiveUploadOk) && ! $this->preserveLocalCopy) {
                Storage::disk('public')->delete($nuevaRuta);
            } else {
                Log::warning("Archivo NO eliminado (backup local preservado): {$nuevaRuta}");
            }
        } catch (Throwable $e) {
            if (! $uploadOk) {
                RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update(['enviado_radioboss' => false]));
            }

            Log::error('Error en UploadMp3Job', [
                'program_id' => $this->radioProgram->id,
                'localPath' => $this->localPath,
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    protected function uploadToRadioboss(string $folder, string $remotePath, string $localPath): void
    {
        $disk = Storage::disk('radioboss');

        if (method_exists($disk, 'createDirectory')) {
            $disk->createDirectory($folder);
        }

        if (filter_var(config('filesystems.disks.radioboss.clear_before_upload', false), FILTER_VALIDATE_BOOL)) {
            $this->clearRemoteFileBeforeUpload($disk, $folder, $remotePath);
        }

        $stream = Storage::disk('public')->readStream($localPath);
        if (! is_resource($stream)) {
            throw new \RuntimeException("UploadMp3Job: No se pudo abrir stream local para {$localPath}");
        }

        try {
            $uploaded = $disk->writeStream($remotePath, $stream);

            if ($uploaded === false) {
                rewind($stream);
                $binary = stream_get_contents($stream);
                if (is_string($binary) && $binary !== '') {
                    $uploaded = $disk->put($remotePath, $binary);
                }
            }
        } finally {
            fclose($stream);
        }

        $shouldVerifyUpload = filter_var(config('filesystems.disks.radioboss.verify_after_upload', false), FILTER_VALIDATE_BOOL);
        if ($uploaded === false || ($shouldVerifyUpload && ! $disk->exists($remotePath))) {
            throw new \RuntimeException("UploadMp3Job: La subida FTP fallo o no pudo verificarse en {$remotePath}");
        }
    }

    protected function uploadToRadiobossWithRetries(string $folder, string $remotePath, string $localPath): bool
    {
        $attempts = 3;
        $delays = [2, 5, 10];

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $this->uploadToRadioboss($folder, $remotePath, $localPath);
                $this->radiobossError = null;

                return true;
            } catch (Throwable $e) {
                $this->radiobossError = $e->getMessage();

                Log::warning('RadioBOSS upload attempt failed', [
                    'program_id' => $this->radioProgram->id,
                    'attempt' => $attempt,
                    'max_attempts' => $attempts,
                    'folder' => $folder,
                    'remote_path' => $remotePath,
                    'exception_class' => get_class($e),
                    'exception' => $e->getMessage(),
                ]);

                if ($attempt < $attempts) {
                    sleep($delays[$attempt - 1] ?? 1);
                }
            }
        }

        return false;
    }

    protected function escribirMetadata($ruta, ?MasterProgram $master, $programa, $invitado, $fecha, $fechaTitulo, $anio): void
    {
        if (! file_exists((string) $ruta)) {
            Log::warning('Metadata tagging skipped: file not found.', [
                'file' => basename((string) $ruta),
                'program' => (string) $programa,
            ]);

            return;
        }

        $tagWriterPath = base_path('vendor/james-heinrich/getid3/getid3/write.php');
        if (! file_exists($tagWriterPath)) {
            Log::warning('Metadata tagging skipped: getID3 writer not found.', [
                'file' => basename((string) $ruta),
                'path' => $tagWriterPath,
            ]);

            return;
        }

        try {
            if (! class_exists(\getID3::class)) {
                require_once base_path('vendor/james-heinrich/getid3/getid3/getid3.php');
            }

            if (! class_exists(\getid3_writetags::class)) {
                require_once $tagWriterPath;
            }

            $episodeNumber = (int) ($this->radioProgram->numero_episodio ?? 0);
            $title = trim(sprintf('%s - Ep. %d (%s)', (string) $programa, $episodeNumber, (string) $fechaTitulo));
            $artist = trim((string) ($this->radioProgram->conductor ?? 'Seven Rock Radio'));
            $album = trim((string) $programa);
            $genre = trim((string) ($this->radioProgram->genero_musical ?? 'Rock'));
            $comment = collect(array_filter([
                'Emision: ' . (string) $fecha,
                $invitado !== '' ? 'Invitado: ' . $invitado : null,
            ]))->implode(' | ');

            $tagWriter = new \getid3_writetags();
            $tagWriter->filename = (string) $ruta;
            $tagWriter->tagformats = ['id3v1', 'id3v2.3'];
            $tagWriter->overwrite_tags = true;
            $tagWriter->remove_other_tags = false;
            $tagWriter->tag_encoding = 'UTF-8';
            $tagWriter->tag_data = [
                'title' => [$title],
                'artist' => [$artist],
                'album' => [$album],
                'year' => [(string) $anio],
                'genre' => [$genre],
                'comment' => [$comment],
                'tracknumber' => [(string) $episodeNumber],
            ];

            $attachedPicture = $this->resolveAttachedPictureTagData($master);
            if ($attachedPicture !== null) {
                $tagWriter->tag_data['ATTACHED_PICTURE'] = [$attachedPicture];
            }

            if (! $tagWriter->WriteTags()) {
                Log::warning('Metadata tagging failed.', [
                    'file' => basename((string) $ruta),
                    'program' => (string) $programa,
                    'errors' => $tagWriter->errors,
                    'warnings' => $tagWriter->warnings,
                ]);

                return;
            }
        } catch (Throwable $exception) {
            Log::warning('Metadata tagging failed with exception.', [
                'file' => basename((string) $ruta),
                'program' => (string) $programa,
                'exception' => $exception,
            ]);
        }
    }

    private function resolveUploadFolder(?MasterProgram $master): string
    {
        $folder = trim((string) ($this->radioProgram->ruta_ftp_radioboss ?: $master?->ruta_ftp), '/\\');

        return trim(str_replace(['..', '\\'], '', $folder), '/\\') ?: 'Programas';
    }

    private function syncEpisodeNumberBeforeProcessing(?MasterProgram $master, string $folder): int
    {
        $currentEpisodeNumber = max(1, (int) ($this->radioProgram->numero_episodio ?? 0));
        $dbMaxEpisodeNumber = $this->resolveMaxEpisodeNumberFromDatabase($master, $folder);
        $remoteMaxEpisodeNumber = filter_var(config('filesystems.disks.radioboss.scan_remote_for_episode_number', false), FILTER_VALIDATE_BOOL)
            ? $this->resolveMaxEpisodeNumberFromRemoteFolder($folder)
            : 0;

        if ($this->shouldPreserveManualEpisodeNumber($currentEpisodeNumber, $dbMaxEpisodeNumber)) {
            return $currentEpisodeNumber;
        }

        $resolvedEpisodeNumber = max(
            $currentEpisodeNumber,
            $dbMaxEpisodeNumber + 1,
            $remoteMaxEpisodeNumber + 1
        );

        if ($resolvedEpisodeNumber !== $currentEpisodeNumber) {
            $this->radioProgram->numero_episodio = $resolvedEpisodeNumber;
            RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                'numero_episodio' => $resolvedEpisodeNumber,
            ]));

            Log::info('UploadMp3Job: numero de episodio ajustado antes del procesamiento', [
                'program_id' => $this->radioProgram->id,
                'previous' => $currentEpisodeNumber,
                'resolved' => $resolvedEpisodeNumber,
                'db_max' => $dbMaxEpisodeNumber,
                'remote_max' => $remoteMaxEpisodeNumber,
                'folder' => $folder,
            ]);
        }

        return $resolvedEpisodeNumber;
    }

    private function shouldPreserveManualEpisodeNumber(int $currentEpisodeNumber, int $dbMaxEpisodeNumber): bool
    {
        return $currentEpisodeNumber > 0;
    }

    private function resolveMaxEpisodeNumberFromDatabase(?MasterProgram $master, string $folder): int
    {
        $title = trim((string) ($master?->nombre ?: $this->radioProgram->titulo_programa));
        $normalizedTitle = Str::lower(Str::ascii($title));
        $masterProgramId = $master?->id ?: $this->radioProgram->master_program_id;

        return (int) RadioProgram::query()
            ->whereKeyNot($this->radioProgram->id)
            ->where(function ($query) use ($masterProgramId, $normalizedTitle, $folder): void {
                if ($masterProgramId) {
                    $query->where('master_program_id', $masterProgramId);
                }

                if ($normalizedTitle !== '') {
                    $query->orWhereRaw('LOWER(TRIM(titulo_programa)) = ?', [$normalizedTitle]);
                }

                if ($folder !== '') {
                    $query->orWhere('ruta_ftp_radioboss', $folder);
                }
            })
            ->max('numero_episodio');
    }

    private function resolveMaxEpisodeNumberFromRemoteFolder(string $folder): int
    {
        $ftpHost = trim((string) config('filesystems.disks.radioboss.host', ''));
        if ($ftpHost === '') {
            return 0;
        }

        try {
            $disk = Storage::disk('radioboss');
            $files = method_exists($disk, 'allFiles')
                ? $disk->allFiles($folder)
                : $disk->files($folder);
        } catch (Throwable $exception) {
            Log::warning('UploadMp3Job: no se pudo leer la carpeta remota para calcular el siguiente episodio', [
                'program_id' => $this->radioProgram->id,
                'folder' => $folder,
                'exception' => $exception->getMessage(),
            ]);

            return 0;
        }

        $maxEpisode = 0;
        foreach ($files as $file) {
            $maxEpisode = max($maxEpisode, $this->extractEpisodeNumberFromPath((string) $file));
        }

        return $maxEpisode;
    }

    private function extractEpisodeNumberFromPath(string $path): int
    {
        $normalizedPath = str_replace('\\', '/', trim($path));
        $fileName = basename($normalizedPath);

        if (preg_match('/\bEp\.\s*(\d{1,5})\b/i', $normalizedPath, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('/^(\d{1,5})\.-/', $fileName, $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function resolveNotificationMailer(): string
    {
        $configuredMailer = trim((string) config('services.notifications.mailer', ''));
        if ($configuredMailer !== '') {
            return $configuredMailer;
        }

        $defaultMailer = (string) config('mail.default', 'log');
        $smtpHost = trim((string) config('mail.mailers.smtp.host', ''));
        $smtpUsername = trim((string) config('mail.mailers.smtp.username', ''));

        if ($smtpHost !== '' && $smtpUsername !== '') {
            return 'smtp';
        }

        return $defaultMailer;
    }

    private function clearRemoteFileBeforeUpload($disk, string $folder, string $remotePath): void
    {
        try {
            if (method_exists($disk, 'exists') && $disk->exists($remotePath)) {
                $disk->delete($remotePath);

                return;
            }

            $files = method_exists($disk, 'files')
                ? $disk->files($folder)
                : [];

            if ($files === []) {
                return;
            }

            $oldestFile = null;
            $oldestModifiedAt = PHP_INT_MAX;

            foreach ($files as $file) {
                $file = trim((string) $file);
                if ($file === '') {
                    continue;
                }

                $modifiedAt = method_exists($disk, 'lastModified')
                    ? (int) $disk->lastModified($file)
                    : 0;

                if ($modifiedAt <= $oldestModifiedAt) {
                    $oldestModifiedAt = $modifiedAt;
                    $oldestFile = $file;
                }
            }

            if ($oldestFile !== null) {
                $disk->delete($oldestFile);
            }
        } catch (Throwable $exception) {
            Log::warning('UploadMp3Job: no se pudo preparar el archivo remoto antes de subir el MP3', [
                'program_id' => $this->radioProgram->id,
                'folder' => $folder,
                'remote_path' => $remotePath,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveNotificationRecipients(?MasterProgram $master): array
    {
        $candidates = [
            $master?->email_notificacion,
            $master?->email_copia_notificacion ?: $this->resolveGlobalNotificationCopyRecipient(),
        ];

        $normalized = [];
        foreach ($candidates as $candidate) {
            foreach ($this->flattenNotificationCandidates($candidate) as $emailCandidate) {
                $email = filter_var($emailCandidate, FILTER_VALIDATE_EMAIL);
                if ($email !== false) {
                    $normalized[] = Str::lower($email);
                }
            }
        }

        return array_values(array_unique($normalized));
    }

    private function resolveGlobalNotificationCopyRecipient(): ?string
    {
        $themeEmail = trim((string) ThemeSetting::current()->contact_email);

        return $themeEmail !== '' ? $themeEmail : null;
    }

    /**
     * @return array<int, string>
     */
    private function flattenNotificationCandidates(mixed $candidate): array
    {
        if (is_array($candidate)) {
            $flattened = [];

            foreach ($candidate as $value) {
                foreach ($this->flattenNotificationCandidates($value) as $nestedValue) {
                    $flattened[] = $nestedValue;
                }
            }

            return $flattened;
        }

        $stringValue = trim((string) $candidate);
        if ($stringValue === '') {
            return [];
        }

        return preg_split('/\s*[,;]\s*/', $stringValue, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * @return array{data:string,mime:string,description:string,picturetypeid:int}|null
     */
    private function resolveAttachedPictureTagData(?MasterProgram $master): ?array
    {
        $candidates = array_filter([
            $this->radioProgram->imagen_episodio,
            $this->radioProgram->caratula_programa,
            $master?->caratula_url,
        ], fn ($value) => trim((string) $value) !== '');

        foreach ($candidates as $candidate) {
            $picture = $this->loadAttachedPictureFromCandidate((string) $candidate);
            if ($picture !== null) {
                return $picture;
            }
        }

        return null;
    }

    /**
     * @return array{data:string,mime:string,description:string,picturetypeid:int}|null
     */
    private function loadAttachedPictureFromCandidate(string $candidate): ?array
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return null;
        }

        $publicPath = ltrim(str_replace('\\', '/', $candidate), '/');
        if (Storage::disk('public')->exists($publicPath)) {
            return $this->buildAttachedPicturePayloadFromFile(storage_path('app/public/' . $publicPath));
        }

        $normalizedUrl = PublicMediaUrl::normalizePublicUrl($candidate);
        if ($normalizedUrl === '') {
            return null;
        }

        $localPayload = $this->buildAttachedPicturePayloadFromNormalizedUrl($normalizedUrl);
        if ($localPayload !== null) {
            return $localPayload;
        }

        $remoteUrl = str_starts_with($normalizedUrl, '//') ? 'https:' . $normalizedUrl : $normalizedUrl;

        try {
            $response = ExternalHttp::client()->timeout(15)->get($remoteUrl);
            if (! $response->successful()) {
                return null;
            }

            $mime = trim(strtolower((string) $response->header('Content-Type', '')));
            if ($mime === '' || ! str_starts_with($mime, 'image/')) {
                return null;
            }

            $data = $response->body();
            if ($data === '') {
                return null;
            }

            return [
                'data' => $data,
                'mime' => strtok($mime, ';') ?: $mime,
                'description' => 'Cover',
                'picturetypeid' => 3,
            ];
        } catch (Throwable $exception) {
            Log::warning('UploadMp3Job: no se pudo descargar la portada para incrustarla en el MP3', [
                'program_id' => $this->radioProgram->id,
                'candidate' => $candidate,
                'url' => $remoteUrl,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{data:string,mime:string,description:string,picturetypeid:int}|null
     */
    private function buildAttachedPicturePayloadFromNormalizedUrl(string $normalizedUrl): ?array
    {
        $appHost = (string) parse_url((string) config('app.url', ''), PHP_URL_HOST);
        $urlHost = (string) parse_url($normalizedUrl, PHP_URL_HOST);
        $urlPath = ltrim((string) parse_url($normalizedUrl, PHP_URL_PATH), '/');

        if ($urlPath === '') {
            return null;
        }

        if ($appHost !== '' && $urlHost !== '' && ! hash_equals($appHost, $urlHost)) {
            return null;
        }

        if (str_starts_with($urlPath, 'storage/')) {
            $storagePath = substr($urlPath, 8);
            if ($storagePath !== false && Storage::disk('public')->exists($storagePath)) {
                return $this->buildAttachedPicturePayloadFromFile(storage_path('app/public/' . $storagePath));
            }
        }

        $absolutePublicPath = public_path($urlPath);
        if (is_file($absolutePublicPath)) {
            return $this->buildAttachedPicturePayloadFromFile($absolutePublicPath);
        }

        return null;
    }

    /**
     * @return array{data:string,mime:string,description:string,picturetypeid:int}|null
     */
    private function buildAttachedPicturePayloadFromFile(string $absolutePath): ?array
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return null;
        }

        $data = @file_get_contents($absolutePath);
        if ($data === false || $data === '') {
            return null;
        }

        $mime = mime_content_type($absolutePath) ?: '';
        if (! str_starts_with(strtolower($mime), 'image/')) {
            return null;
        }

        return [
            'data' => $data,
            'mime' => $mime,
            'description' => 'Cover',
            'picturetypeid' => 3,
        ];
    }

    private function isProcessedLocalBackup(string $path): bool
    {
        return Str::startsWith(trim($path), 'programas_procesados/');
    }
}

```

## app/Services/ArchiveOrgPodcastService.php

`$lang
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Support\ExternalHttp;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ArchiveOrgPodcastService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 120,
            'connect_timeout' => 20,
            'http_errors' => true,
        ]);
    }

    public function syncEpisode(RadioProgram $episode): array
    {
        $episode->loadMissing('masterProgram');

        if (! $this->canSync()) {
            throw new RuntimeException('Faltan credenciales de Archive.org en services.archive_org.');
        }

        $master = $episode->masterProgram;
        $identifier = $this->resolveIdentifier($episode, $master);
        $absolutePath = $this->resolveLocalPath($episode);
        $remotePath = $this->resolveRemotePath($episode);
        $itemExistsBefore = $this->itemExists($identifier);
        $created = ! $itemExistsBefore;
        $itemMetadata = $this->buildItemMetadata($episode, $master);
        $fileMetadata = $this->buildEpisodeFileMetadata($episode, $master);
        $snapshot = $this->buildArchiveSnapshot(
            episode: $episode,
            master: $master,
            identifier: $identifier,
            remotePath: $remotePath,
            itemExistsBefore: $itemExistsBefore,
            created: $created,
            itemMetadata: $itemMetadata,
            fileMetadata: $fileMetadata,
        );

        $episode->forceFill([
            'archive_org_status' => 'pending',
            'archive_org_last_error' => null,
            'archive_org_metadata' => $snapshot,
        ])->saveQuietly();

        $this->uploadFile($identifier, $remotePath, $absolutePath, $itemMetadata);
        $this->applyEpisodeMetadata($identifier, $remotePath, $fileMetadata);

        if ($master instanceof MasterProgram && blank($master->archive_identifier)) {
            $master->forceFill(['archive_identifier' => $identifier])->saveQuietly();
        }

        $episode->forceFill([
            'archive_org_status' => 'synced',
            'archive_org_remote_path' => $remotePath,
            'archive_org_uploaded_at' => now(),
            'archive_org_last_error' => null,
            'archive_org_metadata' => array_merge($snapshot, [
                'status' => 'synced',
                'synced_at' => now()->toIso8601String(),
                'remote_path' => $remotePath,
            ]),
        ])->saveQuietly();

        return [
            'success' => true,
            'created' => $created,
            'identifier' => $identifier,
            'remote_path' => $remotePath,
            'item_exists_before' => $itemExistsBefore,
        ];
    }

    public function syncEpisodeMetadata(RadioProgram $episode): array
    {
        $episode->loadMissing('masterProgram');

        if (! $this->canSync()) {
            throw new RuntimeException('Faltan credenciales de Archive.org en services.archive_org.');
        }

        $master = $episode->masterProgram;
        $identifier = $this->resolveIdentifier($episode, $master);
        $remotePath = trim((string) ($episode->archive_org_remote_path ?: $this->resolveRemotePath($episode)));

        if ($remotePath === '') {
            throw new RuntimeException('No se pudo determinar el archivo remoto del capítulo.');
        }

        if (! $this->itemExists($identifier)) {
            throw new RuntimeException("El item {$identifier} todavía no existe en Archive.org.");
        }

        $fileMetadata = $this->buildEpisodeFileMetadata($episode, $master);
        $this->applyEpisodeMetadata($identifier, $remotePath, $fileMetadata);

        $snapshot = array_merge((array) ($episode->archive_org_metadata ?? []), [
            'source' => 'archive-org-metadata-sync',
            'record_id' => $episode->id,
            'identifier' => $identifier,
            'remote_path' => $remotePath,
            'status' => 'synced',
            'synced_at' => now()->toIso8601String(),
            'episode_metadata' => $fileMetadata,
        ]);

        $episode->forceFill([
            'archive_org_status' => 'synced',
            'archive_org_remote_path' => $remotePath,
            'archive_org_uploaded_at' => $episode->archive_org_uploaded_at ?? now(),
            'archive_org_last_error' => null,
            'archive_org_metadata' => $snapshot,
        ])->saveQuietly();

        return [
            'success' => true,
            'identifier' => $identifier,
            'remote_path' => $remotePath,
        ];
    }

    public function canSync(): bool
    {
        return trim((string) config('services.archive_org.access_key', '')) !== ''
            && trim((string) config('services.archive_org.secret_key', '')) !== '';
    }

    public function itemExists(string $identifier): bool
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return false;
        }

        try {
            $this->client->request('GET', $this->apiEndpoint() . '/metadata/' . rawurlencode($identifier));

            return true;
        } catch (ClientException $exception) {
            if ($exception->getResponse()?->getStatusCode() === 404) {
                return false;
            }

            throw $exception;
        } catch (Throwable) {
            return false;
        }
    }

    public function resolveIdentifier(RadioProgram $episode, ?MasterProgram $master = null): string
    {
        $configured = trim((string) ($master?->archive_identifier ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        $base = trim((string) ($master?->nombre ?? $episode->titulo_programa ?? 'podcast'));
        $slug = Str::slug($base, '-');
        $suffix = max(1, (int) ($master?->id ?? $episode->id));

        return ($slug !== '' ? $slug : 'podcast') . '-' . $suffix;
    }

    private function resolveLocalPath(RadioProgram $episode): string
    {
        $stored = trim((string) ($episode->archivo_mp3 ?? ''));
        if ($stored === '') {
            throw new RuntimeException('El episodio no tiene archivo MP3 local asociado.');
        }

        if (! Storage::disk('public')->exists($stored)) {
            throw new RuntimeException("No se pudo leer el MP3 local: {$stored}");
        }

        return Storage::disk('public')->path(ltrim($stored, '/'));
    }

    private function resolveRemotePath(RadioProgram $episode): string
    {
        $stored = trim((string) ($episode->archivo_mp3 ?? ''));
        if ($stored === '') {
            return '';
        }

        return basename(str_replace('\\', '/', $stored));
    }

    /**
     * @return array<string, string>
     */
    private function buildItemMetadata(RadioProgram $episode, ?MasterProgram $master): array
    {
        $programTitle = trim((string) ($master?->nombre ?: $episode->titulo_programa));
        $description = trim((string) ($master?->descripcion ?: $episode->resena ?: $episode->comentario_episodio ?: ''));
        $creator = trim((string) ($master?->conductor ?: $episode->conductor ?: ''));

        return array_filter([
            'collection' => trim((string) config('services.archive_org.collection', 'opensource_audio')),
            'mediatype' => trim((string) config('services.archive_org.mediatype', 'audio')),
            'title' => $programTitle,
            'description' => strip_tags($description),
            'subject' => implode(', ', array_filter([
                $programTitle !== '' ? $programTitle : null,
                'podcast',
                'radio',
                trim((string) ($master?->genero ?: $episode->genero_musical ?: '')),
            ])),
            'creator' => $creator,
        ], static fn (string $value): bool => trim($value) !== '');
    }

    /**
     * @return array<string, string>
     */
    private function buildEpisodeFileMetadata(RadioProgram $episode, ?MasterProgram $master): array
    {
        $programTitle = trim((string) ($master?->nombre ?: $episode->titulo_programa));
        $episodeNumber = max(1, (int) ($episode->numero_episodio ?? 0));
        $title = trim((string) ($episode->live_title ?: $episode->comentario_episodio ?: $episode->resena ?: $programTitle));

        return array_filter([
            'title' => $title !== '' ? $title : $programTitle,
            'creator' => trim((string) ($episode->conductor ?: $master?->conductor ?: '')),
            'subject' => implode(', ', array_filter([
                $programTitle !== '' ? $programTitle : null,
                'episode ' . $episodeNumber,
                trim((string) ($master?->genero ?: $episode->genero_musical ?: '')),
            ])),
            'description' => strip_tags(trim((string) ($episode->live_description ?: $episode->comentario_episodio ?: $episode->resena ?: ''))),
            'date' => optional($episode->fecha_emision)->toDateString() ?: now()->toDateString(),
        ], static fn (string $value): bool => trim($value) !== '');
    }

    /**
     * @param array<string, string> $itemMetadata
     * @return array<string, string>
     */
    private function mapItemMetadataToHeaders(array $itemMetadata): array
    {
        $headers = [];

        foreach ($itemMetadata as $key => $value) {
            if (trim($value) === '') {
                continue;
            }

            $headers['x-archive-meta-' . strtolower(trim($key))] = $value;
        }

        return $headers;
    }

    private function uploadFile(string $identifier, string $remotePath, string $absolutePath, array $itemMetadata = []): void
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new RuntimeException("No se pudo leer el MP3 local: {$absolutePath}");
        }

        $stream = fopen($absolutePath, 'rb');
        if ($stream === false) {
            throw new RuntimeException("No se pudo abrir el archivo para lectura: {$absolutePath}");
        }

        $headers = [
            'Authorization' => 'LOW ' . trim((string) config('services.archive_org.access_key', '')) . ':' . trim((string) config('services.archive_org.secret_key', '')),
            'x-archive-auto-make-bucket' => '1',
            'x-archive-interactive-priority' => '1',
            'User-Agent' => config('app.name', 'Laravel') . ' ArchiveOrgPodcastService',
        ];

        if ($itemMetadata !== []) {
            $headers = array_merge($headers, $this->mapItemMetadataToHeaders($itemMetadata));
        }

        try {
            $this->retry(function () use ($identifier, $remotePath, $headers, $stream): void {
                rewind($stream);

                $this->client->request('PUT', $this->s3Endpoint() . '/' . rawurlencode($identifier) . '/' . $this->encodePath($remotePath), [
                    'headers' => $headers,
                    'body' => $stream,
                ]);
            });
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * @param array<string, string> $fileMetadata
     */
    private function applyEpisodeMetadata(string $identifier, string $remotePath, array $fileMetadata): void
    {
        $patches = [];

        foreach ($fileMetadata as $key => $value) {
            if ($value === '') {
                continue;
            }

            $patches[] = [
                'op' => 'replace',
                'path' => '/' . $key,
                'value' => $value,
            ];
        }

        if ($patches === []) {
            return;
        }

        $this->retry(function () use ($identifier, $remotePath, $patches): void {
            $this->client->request('POST', $this->apiEndpoint() . '/metadata/' . rawurlencode($identifier), [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    '-target' => 'files/' . $this->encodePath($remotePath),
                    '-patch' => json_encode($patches, JSON_THROW_ON_ERROR),
                    'access' => trim((string) config('services.archive_org.access_key', '')),
                    'secret' => trim((string) config('services.archive_org.secret_key', '')),
                ],
            ]);
        });
    }

    /**
     * @param array<string, string> $itemMetadata
     * @param array<string, string> $fileMetadata
     * @return array<string, mixed>
     */
    private function buildArchiveSnapshot(
        RadioProgram $episode,
        ?MasterProgram $master,
        string $identifier,
        string $remotePath,
        bool $itemExistsBefore,
        bool $created,
        array $itemMetadata,
        array $fileMetadata,
    ): array {
        return [
            'source' => 'archive-org-upload',
            'record_id' => $episode->id,
            'identifier' => $identifier,
            'remote_path' => $remotePath,
            'item_exists_before' => $itemExistsBefore,
            'created' => $created,
            'item_metadata' => $itemMetadata,
            'episode_metadata' => $fileMetadata,
            'status' => 'pending',
            'episode' => [
                'master_program_id' => $episode->master_program_id,
                'numero_episodio' => $episode->numero_episodio,
                'titulo_programa' => $episode->titulo_programa,
                'conductor' => $episode->conductor,
                'fecha_emision' => optional($episode->fecha_emision)->toDateString(),
                'biografia_invitado' => $episode->biografia_invitado,
                'resena' => $episode->resena,
                'live_title' => $episode->live_title,
                'live_description' => $episode->live_description,
                'comentario_episodio' => $episode->comentario_episodio,
                'sync_archive_org' => $episode->sync_archive_org,
                'master_program_name' => $master?->nombre,
            ],
        ];
    }

    private function apiEndpoint(): string
    {
        $endpoint = trim((string) config('services.archive_org.endpoint', 'https://s3.us.archive.org'));

        return rtrim($endpoint, '/');
    }

    private function s3Endpoint(): string
    {
        return $this->apiEndpoint();
    }

    private function encodePath(string $path): string
    {
        $segments = array_map('rawurlencode', array_filter(explode('/', str_replace('\\', '/', trim($path))), static fn (string $segment): bool => $segment !== ''));

        return implode('/', $segments);
    }

    private function retry(callable $callback, int $attempts = 3): void
    {
        $delayMs = [250, 500, 1000];

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $callback();

                return;
            } catch (Throwable $exception) {
                if ($attempt >= $attempts) {
                    throw $exception;
                }

                usleep(($delayMs[$attempt - 1] ?? 250) * 1000);
            }
        }
    }
}

```

## resources/views/admin/podcast-uploads/index.blade.php

`$lang
<x-layouts.admin :title="'Podcast Uploads - '.$themeSettings->site_name">
    @php
        $selectedProgramId = old('master_program_id');
        $selectedDay = null;

        if ($selectedProgramId) {
            foreach ($programsByDay as $dayKey => $dayPrograms) {
                if ($dayPrograms->contains('id', (int) $selectedProgramId)) {
                    $selectedDay = $dayKey;
                    break;
                }
            }
        }

        $initialDay = $selectedDay ?? $activeDay;
    @endphp

    <div class="grid gap-6 lg:grid-cols-[1.2fr_.8fr] 2xl:grid-cols-[1.3fr_.7fr]">
        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Podcast uploads</h1>
            <p class="mt-3 max-w-2xl text-[#7b7b7b]">
                Sube un MP3, crea el episodio en la base, envíalo a RadioBOSS, sincroniza Archive.org si corresponde y dispara el correo de notificación.
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('admin.master-programs.index') }}" class="lucille-button">Administrar programas maestros</a>
            </div>

            <form
                action="{{ route('admin.podcast-uploads.store') }}"
                method="POST"
                enctype="multipart/form-data"
                class="mt-8 space-y-5"
                x-data="podcastUploadForm({ initialDay: '{{ $initialDay }}' })"
                @submit="submit($event)"
            >
                @csrf

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Programa maestro</label>

                    <div class="flex flex-wrap gap-2 border border-[#242424] bg-[#131313] p-3">
                        @foreach ($dayTabs as $dayKey => $dayLabel)
                            @php $dayPrograms = $programsByDay->get($dayKey, collect()); @endphp
                            <button
                                type="button"
                                @click="activeDay = '{{ $dayKey }}'"
                                class="inline-flex min-w-[8rem] items-center justify-between gap-3 border px-4 py-3 text-sm uppercase tracking-[.18em] transition-colors"
                                :class="activeDay === '{{ $dayKey }}'
                                    ? 'border-[var(--color-lucille-accent)] bg-[rgba(255,255,255,.04)] text-[#f2f2f2]'
                                    : 'border-[#2b2b2b] text-[#7b7b7b] hover:border-[#505050] hover:text-[#dcdcdc]'"
                                aria-label="Ver programas de {{ $dayLabel }}"
                            >
                                <span>{{ $dayLabel }}</span>
                                <span class="text-[11px] tracking-[.2em] text-[#9d9d9d]">{{ $dayPrograms->count() }}</span>
                            </button>
                        @endforeach
                    </div>

                    @foreach ($dayTabs as $dayKey => $dayLabel)
                        @php $dayPrograms = $programsByDay->get($dayKey, collect()); @endphp
                        <div
                            x-cloak
                            x-show="activeDay === '{{ $dayKey }}'"
                            x-transition.opacity.duration.150ms
                            data-day-panel="{{ $dayKey }}"
                            class="mt-4 space-y-2"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $dayLabel }}</div>
                                <div class="text-[11px] uppercase tracking-[.18em] text-[#9d9d9d]">{{ $dayPrograms->count() }} programa{{ $dayPrograms->count() === 1 ? '' : 's' }}</div>
                            </div>

                            <select
                                name="master_program_id"
                                class="lucille-product-field lucille-select-field w-full"
                                :disabled="activeDay !== '{{ $dayKey }}'"
                            >
                                <option value="">-- seleccionar --</option>
                                @forelse ($dayPrograms as $masterProgram)
                                    <option value="{{ $masterProgram->id }}" @selected((string) old('master_program_id') === (string) $masterProgram->id)>
                                        {{ $masterProgram->name }}
                                    </option>
                                @empty
                                    <option value="" disabled>No hay programas para este día</option>
                                @endforelse
                            </select>
                        </div>
                    @endforeach

                    @error('master_program_id')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Capítulo / episodio</label>
                        <input
                            type="number"
                            min="1"
                            name="numero_episodio"
                            value="{{ old('numero_episodio') }}"
                            class="lucille-product-field w-full"
                            placeholder="Automático"
                        >
                        <p class="mt-2 text-xs uppercase tracking-[.16em] text-[#7b7b7b]">
                            Déjalo vacío para usar el siguiente correlativo automático.
                        </p>
                        @error('numero_episodio')
                            <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Título del episodio</label>
                        <input name="live_title" value="{{ old('live_title') }}" class="lucille-product-field w-full" placeholder="Episodio especial">
                        <p class="mt-2 text-xs uppercase tracking-[.16em] text-[#7b7b7b]">
                            Obligatorio. Este título se usa en la ficha, el archivo y la notificación.
                        </p>
                        @error('live_title')
                            <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Fecha de emisión</label>
                        <input type="date" name="fecha_emision" value="{{ old('fecha_emision', now()->toDateString()) }}" class="lucille-product-field w-full">
                        @error('fecha_emision')
                            <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Invitado</label>
                    <input name="biografia_invitado" value="{{ old('biografia_invitado') }}" class="lucille-product-field w-full" placeholder="Opcional">
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Reseña / descripción</label>
                    <textarea name="resena" rows="5" class="lucille-product-field w-full">{{ old('resena') }}</textarea>
                </div>

                <div class="space-y-5 border border-[#242424] bg-[#131313] p-5">
                    <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Imagen del episodio</div>
                    <p class="text-sm text-[#7b7b7b]">
                        Puedes usar una URL pública o subir el archivo directamente. Si completas ambos, el archivo local tiene prioridad y se guarda en la base para reutilizarlo después en podcasts y Archive.org.
                    </p>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">URL de imagen</label>
                            <input name="imagen_episodio_url" value="{{ old('imagen_episodio_url') }}" class="lucille-product-field w-full" placeholder="https://...">
                            @error('imagen_episodio_url')
                                <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Archivo de imagen</label>
                            <input type="file" name="imagen_episodio_file" accept="image/*" class="lucille-product-field w-full">
                            @error('imagen_episodio_file')
                                <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-xs uppercase tracking-[.18em] text-[#7b7b7b]">Archivo MP3</label>
                    <input type="file" name="archivo_mp3" accept="audio/mpeg,audio/mp3" class="lucille-product-field w-full">
                    @error('archivo_mp3')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                    @enderror

                    <div class="mt-3">
                        <div class="mb-2 inline-flex items-center rounded border px-2.5 py-1 text-[10px] uppercase tracking-[.18em]" :class="phaseClass()">
                            <span x-text="phaseLabel"></span>
                        </div>
                        <div class="mb-2 text-[11px] leading-5 text-[#8b8b8b]" x-text="phaseDetailLabel"></div>
                        <div class="h-1.5 overflow-hidden border border-[#242424] bg-[#101010]">
                            <div
                                class="h-full bg-[color:var(--color-lucille-accent)] transition-[width] duration-150"
                                :style="`width:${progress}%`"
                            ></div>
                        </div>
                        <div class="mt-1 flex items-center justify-between text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">
                            <span x-text="uploading ? 'Subiendo' : (statusMessage || 'Listo para subir')"></span>
                            <span x-text="progressLabel"></span>
                        </div>
                        <div class="mt-1 flex items-center justify-between text-[10px] uppercase tracking-[.18em] text-[#5f5f5f]">
                            <span x-text="fileSizeLabel ? `Peso: ${fileSizeLabel}` : ''"></span>
                            <span x-text="uploadEtaLabel"></span>
                        </div>
                    </div>
                </div>

                <label class="flex items-center gap-3 text-sm text-[#7b7b7b]">
                    <input type="checkbox" name="download_processed_mp3" value="1" @checked(old('download_processed_mp3')) class="h-4 w-4">
                    Conservar una copia local descargable del MP3 procesado
                </label>
                <p class="mt-[-.5rem] text-xs uppercase tracking-[.16em] text-[#7b7b7b]">
                    La descarga quedará disponible cuando termine el procesamiento.
                </p>

                <label class="flex items-center gap-3 text-sm text-[#7b7b7b]">
                    <input type="checkbox" name="sync_archive_org" value="1" @checked(old('sync_archive_org', true)) class="h-4 w-4">
                    Sincronizar también con Archive.org
                </label>

                <div x-show="errorMessages.length" x-cloak class="border border-[#4b2f2f] bg-[rgba(49,27,27,.42)] p-4 text-sm text-[#ffd7d7]">
                    <div class="mb-2 text-[11px] uppercase tracking-[.18em] text-[#ff9e9e]">
                        Faltan campos obligatorios
                    </div>
                    <template x-for="message in errorMessages" :key="message">
                        <div class="leading-6" x-text="message"></div>
                    </template>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="lucille-button-solid" :disabled="uploading" x-text="uploading ? 'Procesando...' : 'Crear y procesar'"></button>
                    <a href="{{ route('admin.dashboard') }}" class="lucille-button">Volver al dashboard</a>
                </div>
            </form>
        </section>

        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
            <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">Últimos episodios</h2>
            <div class="mt-6 space-y-4">
                @forelse ($recentUploads as $upload)
                    <article class="border border-[#242424] bg-[#151515] p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-display text-sm uppercase tracking-[.12em] text-white">{{ $upload->live_title ?: $upload->titulo_programa }}</div>
                                <div class="mt-1 text-sm text-[#9d9d9d]">{{ $upload->masterProgram?->name ?? 'Sin programa maestro' }}</div>
                                <div class="mt-1 text-xs uppercase tracking-[.18em] text-[#7b7b7b]">
                                    RadioBOSS: {{ $upload->enviado_radioboss ? 'enviado' : 'pendiente' }}
                                    · Archive.org: {{ $upload->archive_org_status ?: 'sin estado' }}
                                </div>
                            </div>

                            <div class="flex flex-col items-end gap-2">
                                <span class="rounded border border-[#2b2b2b] px-3 py-1 text-[11px] uppercase tracking-[.18em] text-[#9d9d9d]">
                                    Ep. {{ $upload->numero_episodio }}
                                </span>
                                <span class="text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">
                                    {{ optional($upload->fecha_emision)->format('d/m/Y') }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <form action="{{ route('admin.podcast-uploads.retry', $upload) }}" method="POST">
                                @csrf
                                <button type="submit" class="lucille-button">Reprocesar</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <p class="text-sm text-[#7b7b7b]">Todavía no hay episodios en esta sección.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.admin>

```

## resources/js/app.js

`$lang
import Alpine from 'alpinejs';
import { registerRadioPlayer } from './player';

window.Alpine = Alpine;

Alpine.data('rocksHero', (slides = []) => ({
    active: 0,
    slides,
    interval: null,
    init() {
        if (this.slides.length < 2) {
            return;
        }

        this.interval = setInterval(() => this.next(), 3000);
    },
    next() {
        this.active = (this.active + 1) % this.slides.length;
    },
    go(index) {
        this.active = index;
    },
}));

Alpine.data('rocksNav', () => ({
    open: false,
    searchOpen: false,
    sticky: false,
    init() {
        this.sticky = window.scrollY > 100;
        window.addEventListener('scroll', () => {
            this.sticky = window.scrollY > 100;
        }, { passive: true });
    },
}));

Alpine.data('galleryLightbox', (images = []) => ({
    images,
    active: 0,
    open: false,
    touchStartX: 0,
    get current() {
        return this.images[this.active] ?? { src: '', caption: '' };
    },
    show(index) {
        this.active = index;
        this.open = true;
        document.body.classList.add('lb-disable-scrolling');
        this.preloadAround();
    },
    close() {
        this.open = false;
        document.body.classList.remove('lb-disable-scrolling');
    },
    next() {
        if (!this.images.length) {
            return;
        }

        this.active = (this.active + 1) % this.images.length;
        this.preloadAround();
    },
    prev() {
        if (!this.images.length) {
            return;
        }

        this.active = (this.active - 1 + this.images.length) % this.images.length;
        this.preloadAround();
    },
    preloadAround() {
        [this.active - 1, this.active + 1].forEach((index) => {
            const image = this.images[(index + this.images.length) % this.images.length];
            if (image?.src) {
                const preload = new Image();
                preload.src = image.src;
            }
        });
    },
    swipeEnd(event) {
        const delta = event.changedTouches[0].clientX - this.touchStartX;
        if (Math.abs(delta) < 40) {
            return;
        }

        delta < 0 ? this.next() : this.prev();
    },
}));

Alpine.data('bandProfilePicker', (options = {}) => ({
    searchUrl: options.searchUrl ?? '',
    selectedId: options.selectedId ? String(options.selectedId) : '',
    selectedLabel: options.selectedLabel ?? '',
    minLength: Number.isFinite(Number(options.minLength)) ? Math.max(1, Number(options.minLength)) : 3,
    query: options.selectedLabel ?? '',
    results: [],
    activeIndex: -1,
    open: false,
    loading: false,
    timer: null,
    requestToken: 0,
    init() {
        this.query = this.selectedLabel || '';
    },
    onInput() {
        this.selectedId = '';
        this.selectedLabel = '';
        this.activeIndex = -1;
        this.scheduleSearch();
    },
    scheduleSearch() {
        clearTimeout(this.timer);

        if (this.query.trim().length < this.minLength) {
            this.results = [];
            this.open = false;
            this.activeIndex = -1;
            return;
        }

        this.timer = setTimeout(() => {
            this.search();
        }, 160);
    },
    async search() {
        if (! this.searchUrl) {
            return;
        }

        const token = ++this.requestToken;
        this.loading = true;

        try {
            const url = new URL(this.searchUrl, window.location.origin);
            url.searchParams.set('q', this.query.trim());

            this.open = true;
            const response = await fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });

            const payload = await response.json();
            if (token !== this.requestToken) {
                return;
            }

            this.results = Array.isArray(payload?.data?.results) ? payload.data.results : [];
            this.activeIndex = this.results.length ? 0 : -1;
            this.open = true;
        } catch (error) {
            if (token === this.requestToken) {
                this.results = [];
                this.open = false;
                this.activeIndex = -1;
            }
        } finally {
            if (token === this.requestToken) {
                this.loading = false;
            }
        }
    },
    choose(result) {
        this.selectedId = String(result.id ?? '');
        this.selectedLabel = result.text ?? '';
        this.query = this.selectedLabel;
        this.results = [];
        this.open = false;
        this.activeIndex = -1;
    },
    clear() {
        this.selectedId = '';
        this.selectedLabel = '';
        this.query = '';
        this.results = [];
        this.open = false;
        this.activeIndex = -1;
    },
    focus() {
        if (this.results.length > 0) {
            this.open = true;
        }
    },
    closeSoon() {
        window.setTimeout(() => {
            this.open = false;
        }, 120);
    },
    move(delta) {
        if (! this.results.length) {
            return;
        }

        this.open = true;
        this.activeIndex = (this.activeIndex + delta + this.results.length) % this.results.length;
    },
    commitActive(event) {
        if (this.activeIndex < 0 || this.activeIndex >= this.results.length) {
            return;
        }

        event?.preventDefault?.();
        this.choose(this.results[this.activeIndex]);
    },
    handleEnter(event) {
        if (this.open && this.activeIndex >= 0 && this.activeIndex < this.results.length) {
            this.commitActive(event);
        }
    },
}));

Alpine.data('podcastUploadForm', (options = {}) => ({
    activeDay: options.initialDay ?? 'LUNES',
    uploading: false,
    progress: 0,
    progressLabel: '0%',
    statusMessage: '',
    phaseLabel: 'Listo',
    phaseDetailLabel: '',
    errorMessages: [],
    downloading: false,
    uploadEtaLabel: '',
    fileSizeLabel: '',
    estimatedUploadRateBytesPerSec: Number(options.estimatedUploadRateBytesPerSec ?? 8 * 1024 * 1024),
    fieldLabelMap: {
        master_program_id: 'Programa maestro',
        live_title: 'Título del episodio',
        fecha_emision: 'Fecha de emisión',
        archivo_mp3: 'Archivo MP3',
        imagen_episodio_url: 'URL de imagen',
        imagen_episodio_file: 'Archivo de imagen',
    },
    phaseClass() {
        const label = String(this.phaseLabel ?? '').toLowerCase();

        if (label.includes('valid')) {
            return 'border-[#3b3b3b] bg-[rgba(255,255,255,.03)] text-[#c7c7c7]';
        }

        if (label.includes('subiend') || label.includes('transfer')) {
            return 'border-[#5a4315] bg-[rgba(118,86,22,.18)] text-[#f2d89b]';
        }

        if (label.includes('sincronizando')) {
            return 'border-[#31553f] bg-[rgba(30,76,49,.2)] text-[#b8e2c7]';
        }

        if (label.includes('procesando') || label.includes('preparando')) {
            return 'border-[#3f4f2c] bg-[rgba(60,82,35,.2)] text-[#d5e7aa]';
        }

        if (label.includes('error') || label.includes('fall')) {
            return 'border-[#5d2b2b] bg-[rgba(92,35,35,.28)] text-[#ffd0d0]';
        }

        if (label.includes('listo') || label.includes('complet')) {
            return 'border-[#2d5440] bg-[rgba(29,72,45,.18)] text-[#c8ead6]';
        }

        return 'border-[#2b2b2b] bg-[rgba(255,255,255,.03)] text-[#bdbdbd]';
    },
    phaseDetailText() {
        const phase = String(this.phaseLabel ?? '').toLowerCase();

        if (phase.includes('valid')) {
            return 'Validando campos obligatorios antes de iniciar la carga.';
        }

        if (phase.includes('subiend')) {
            return 'Subiendo el MP3 al servidor de la radio.';
        }

        if (phase.includes('procesando')) {
            return 'Escribiendo metadatos y enviando a RadioBOSS.';
        }

        if (phase.includes('sincronizando')) {
            return 'Sincronizando el episodio con Archive.org.';
        }

        if (phase.includes('preparando')) {
            return 'La transferencia terminó. Preparando la descarga local.';
        }

        if (phase.includes('error')) {
            return 'La operación se detuvo por un problema de red, validación o servidor.';
        }

        if (phase.includes('listo')) {
            return 'Todo quedó listo para iniciar una nueva carga.';
        }

        return '';
    },
    formatBytes(bytes) {
        const size = Number(bytes ?? 0);
        if (!Number.isFinite(size) || size <= 0) {
            return '';
        }

        const units = ['B', 'KB', 'MB', 'GB'];
        let value = size;
        let unitIndex = 0;

        while (value >= 1024 && unitIndex < units.length - 1) {
            value /= 1024;
            unitIndex += 1;
        }

        const precision = unitIndex === 0 ? 0 : value >= 10 ? 1 : 2;

        return `${value.toFixed(precision)} ${units[unitIndex]}`;
    },
    formatDuration(seconds) {
        const total = Math.max(0, Math.ceil(Number(seconds ?? 0)));
        const hrs = Math.floor(total / 3600);
        const mins = Math.floor((total % 3600) / 60);
        const secs = total % 60;

        if (hrs > 0) {
            return `${hrs}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }

        return `${mins}:${String(secs).padStart(2, '0')}`;
    },
    updateUploadEstimate(file) {
        const size = Number(file?.size ?? 0);
        this.fileSizeLabel = this.formatBytes(size);

        if (size <= 0) {
            this.uploadEtaLabel = '';
            return;
        }

        const seconds = Math.max(1, Math.ceil(size / Math.max(1, this.estimatedUploadRateBytesPerSec)));
        this.uploadEtaLabel = `Estimado de carga: ~${this.formatDuration(seconds)}`;
    },
    validateBeforeSubmit(form) {
        const errors = [];
        const masterProgram = form.querySelector(`[data-day-panel="${this.activeDay}"] select[name="master_program_id"]`);
        const title = form.querySelector('[name="live_title"]');
        const date = form.querySelector('[name="fecha_emision"]');
        const audioInput = form.querySelector('[name="archivo_mp3"]');
        const audioFile = audioInput?.files?.[0] ?? null;

        if (!masterProgram?.value) {
            errors.push('Programa maestro: selecciona un programa.');
        }

        if (!String(title?.value ?? '').trim()) {
            errors.push('Título del episodio: este campo es obligatorio.');
        }

        if (!String(date?.value ?? '').trim()) {
            errors.push('Fecha de emisión: selecciona una fecha.');
        }

        if (!audioFile) {
            errors.push('Archivo MP3: selecciona un archivo antes de continuar.');
        } else if (audioFile.size <= 0) {
            errors.push('Archivo MP3: el archivo seleccionado no es válido.');
        } else if (audioFile.size > 512000 * 1024) {
            errors.push('Archivo MP3: no puede superar 500 MB.');
        }

        if (errors.length > 0) {
            this.uploading = false;
            this.progress = 0;
            this.progressLabel = '0%';
            this.phaseLabel = 'Validando';
            this.phaseDetailLabel = this.phaseDetailText();
            this.uploadEtaLabel = '';
            this.statusMessage = 'Faltan campos obligatorios. Revisa el formulario.';
            this.errorMessages = errors;

            return null;
        }

        this.updateUploadEstimate(audioFile);

        return audioFile;
    },
    formatValidationErrors(validationErrors = {}) {
        return Object.entries(validationErrors).flatMap(([field, messages]) => {
            const label = this.fieldLabelMap[field] ?? field.replaceAll('_', ' ');
            const normalizedMessages = Array.isArray(messages) ? messages : [messages];

            return normalizedMessages
                .filter(Boolean)
                .map((message) => {
                    const text = String(message).trim();

                    if (field === 'live_title' && /required/i.test(text)) {
                        return `${label}: este campo es obligatorio.`;
                    }

                    if (field === 'archivo_mp3' && /required/i.test(text)) {
                        return `${label}: debes seleccionar un MP3 para continuar.`;
                    }

                    return text
                        .replace(/^The\s+/i, '')
                        .replace(/\s+field is required\.?$/i, '')
                        ? `${label}: ${text}`
                        : `${label}: revisión pendiente`;
                });
        });
    },
    submit(event) {
        const form = event?.target;
        if (!form || this.uploading) {
            return;
        }

        event.preventDefault();
        this.phaseLabel = 'Validando';
        const audioFile = this.validateBeforeSubmit(form);
        if (!audioFile) {
            return;
        }

        this.uploading = true;
        this.downloading = false;
        this.progress = 0;
        this.progressLabel = '0%';
        this.phaseLabel = 'Subiendo';
        this.phaseDetailLabel = this.phaseDetailText();
        this.statusMessage = 'Subiendo archivo...';
        this.errorMessages = [];
        this.uploadEtaLabel = this.uploadEtaLabel || 'Calculando tiempo estimado...';

        const xhr = new XMLHttpRequest();
        xhr.open(form.method || 'POST', form.action, true);
        xhr.responseType = 'text';
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        const uploadStartedAt = performance.now();

        xhr.upload.onprogress = (progressEvent) => {
            if (!progressEvent.lengthComputable) {
                return;
            }

            const percent = Math.min(100, Math.max(0, Math.round((progressEvent.loaded / progressEvent.total) * 100)));
            this.progress = percent;
            this.progressLabel = `${percent}%`;

            if (progressEvent.loaded > 0) {
                const elapsedSeconds = Math.max(0.1, (performance.now() - uploadStartedAt) / 1000);
                const bytesPerSecond = progressEvent.loaded / elapsedSeconds;
                if (bytesPerSecond > 0 && progressEvent.total > progressEvent.loaded) {
                    const remainingSeconds = Math.ceil((progressEvent.total - progressEvent.loaded) / bytesPerSecond);
                    this.uploadEtaLabel = `Restan ~${this.formatDuration(remainingSeconds)}`;
                } else if (percent >= 99) {
                    this.uploadEtaLabel = 'Finalizando transferencia...';
                }
            }
        };

        xhr.onreadystatechange = () => {
            if (xhr.readyState !== XMLHttpRequest.DONE) {
                return;
            }

            this.uploading = false;

            let payload = null;
            try {
                payload = xhr.responseText ? JSON.parse(xhr.responseText) : null;
            } catch {
                payload = null;
            }

            if (xhr.status >= 200 && xhr.status < 300) {
                this.progress = 100;
                this.progressLabel = '100%';
                this.phaseLabel = payload?.download_url
                    ? 'Preparando descarga'
                    : (form.querySelector('[name="sync_archive_org"]')?.checked ? 'Sincronizando Archive.org' : 'Procesando RadioBOSS');
                this.phaseDetailLabel = this.phaseDetailText();
                this.statusMessage = payload?.status ?? 'Episodio procesado correctamente.';
                this.errorMessages = [];
                this.uploadEtaLabel = 'Transferencia completada.';

                if (payload?.download_url) {
                    this.downloading = true;
                    const anchor = document.createElement('a');
                    anchor.href = payload.download_url;
                    anchor.rel = 'noopener';
                    anchor.target = '_self';
                    document.body.appendChild(anchor);
                    anchor.click();
                    anchor.remove();
                }

                if (payload?.redirect_url) {
                    window.setTimeout(() => {
                        window.location.href = payload.redirect_url;
                    }, payload?.download_url ? 900 : 300);
                }

                return;
            }

            const validationErrors = payload?.errors ?? {};
            if (xhr.status === 422 || Object.keys(validationErrors).length > 0) {
                this.phaseLabel = 'Validando';
                this.phaseDetailLabel = this.phaseDetailText();
                this.statusMessage = 'Faltan campos obligatorios. Revisa el formulario.';
                this.errorMessages = this.formatValidationErrors(validationErrors);
                this.uploadEtaLabel = '';
                return;
            }

            this.phaseLabel = 'Procesando RadioBOSS';
            this.phaseDetailLabel = this.phaseDetailText();
            this.statusMessage = 'No se pudo completar la subida.';
            this.errorMessages = payload?.message ? [payload.message] : [];
            this.uploadEtaLabel = '';
        };

        xhr.onerror = () => {
            this.uploading = false;
            this.phaseLabel = 'Error';
            this.phaseDetailLabel = this.phaseDetailText();
            this.statusMessage = 'La subida falló por un error de red o del servidor.';
            this.uploadEtaLabel = '';
        };

        xhr.send(new FormData(form));
    },
}));

registerRadioPlayer(Alpine);

Alpine.start();

```

## config/filesystems.php

`$lang
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'radioboss' => [
            'driver' => 'ftp',
            'host' => env('RADIOBOSS_FTP_SERVER', env('RADIOBOSS_FTP_HOST', '')),
            'username' => env('RADIOBOSS_FTP_USER', env('RADIOBOSS_FTP_USERNAME', '')),
            'password' => env('RADIOBOSS_FTP_PASS', env('RADIOBOSS_FTP_PASSWORD', '')),
            'port' => (int) env('RADIOBOSS_FTP_PORT', 21),
            'root' => env('RADIOBOSS_FTP_ROOT', '/'),
            'passive' => filter_var(env('RADIOBOSS_FTP_PASSIVE', true), FILTER_VALIDATE_BOOL),
            'ssl' => filter_var(env('RADIOBOSS_FTP_SSL', false), FILTER_VALIDATE_BOOL),
            'timeout' => (int) env('RADIOBOSS_FTP_TIMEOUT', 60),
            'clear_before_upload' => filter_var(env('RADIOBOSS_FTP_CLEAR_BEFORE_UPLOAD', false), FILTER_VALIDATE_BOOL),
            'verify_after_upload' => filter_var(env('RADIOBOSS_FTP_VERIFY_AFTER_UPLOAD', false), FILTER_VALIDATE_BOOL),
            'scan_remote_for_episode_number' => filter_var(env('RADIOBOSS_FTP_SCAN_REMOTE_FOR_EPISODE_NUMBER', false), FILTER_VALIDATE_BOOL),
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];

```

## config/services.php

`$lang
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'discogs' => [
        'token' => env('DISCOGS_API_TOKEN', env('DISCOGS_TOKEN')),
    ],

    'lastfm' => [
        'key' => env('LASTFM_KEY'),
        'api_key' => env('LASTFM_API_KEY'),
        'secret' => env('LASTFM_API_SECRET'),
    ],

    'genius' => [
        'token' => env('GENIUS_API_TOKEN'),
    ],

    'musixmatch' => [
        'key' => env('MUSIXMATCH_API_KEY'),
    ],

    'archive_org' => [
        'access_key' => env('ARCHIVE_ORG_ACCESS_KEY'),
        'secret_key' => env('ARCHIVE_ORG_SECRET_KEY'),
        'region' => env('ARCHIVE_REGION', 'us-east-1'),
        'bucket' => env('ARCHIVE_BUCKET'),
        'endpoint' => env('ARCHIVE_ENDPOINT', 'https://s3.us.archive.org'),
        'collection' => env('ARCHIVE_COLLECTION', 'opensource_audio'),
        'mediatype' => env('ARCHIVE_MEDIATYPE', 'audio'),
    ],

    'notifications' => [
        'mailer' => env('NOTIFICATION_MAILER'),
    ],

    'podcast_ingest' => [
        'enabled' => env('PODCAST_INGEST_ENABLED', true),
        'inbox_dir' => env('PODCAST_INGEST_INBOX_DIR', 'podcast-inbox'),
        'processing_dir' => env('PODCAST_INGEST_PROCESSING_DIR', 'podcast-inbox/processing'),
        'error_dir' => env('PODCAST_INGEST_ERROR_DIR', 'podcast-inbox/error'),
        'json_dir' => env('PODCAST_INGEST_JSON_DIR', 'podcast-inbox/generated-json'),
        'default_sync_archive_org' => env('PODCAST_INGEST_DEFAULT_SYNC_ARCHIVE_ORG', true),
    ],

];

```

## tests/Feature/AdminPodcastUploadTest.php

`$lang
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\ProgramUploadedNotification;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPodcastUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_processes_a_podcast_upload_from_the_admin_section(): void
    {
        Storage::fake('public');
        Storage::fake('radioboss');
        Mail::fake();
        config(['services.archive_org.access_key' => '', 'services.archive_org.secret_key' => '']);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $master = MasterProgram::query()->create([
            'nombre' => 'Metal Adicto',
            'conductor' => 'John Doe',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '20:00:00',
            'genero' => 'Metal',
            'ruta_ftp' => 'Programas',
            'email_notificacion' => 'press@example.test',
            'email_copia_notificacion' => null,
            'activo' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.podcast-uploads.store'), [
                'master_program_id' => $master->id,
                'numero_episodio' => 42,
                'live_title' => 'Especial de prueba',
                'fecha_emision' => '2026-05-19',
                'biografia_invitado' => 'Invitado X',
                'resena' => 'Descripción de prueba',
                'imagen_episodio_url' => 'https://example.com/cover.jpg',
                'imagen_episodio_file' => UploadedFile::fake()->image('cover.jpg'),
                'sync_archive_org' => true,
                'archivo_mp3' => UploadedFile::fake()->create('episode.mp3', 1024, 'audio/mpeg'),
            ]);

        $response->assertRedirect(route('admin.podcast-uploads.index'));

        $episode = RadioProgram::query()->latest('id')->first();
        $this->assertNotNull($episode, 'No se creó el episodio.');
        $this->assertSame(42, $episode->numero_episodio, 'El capítulo manual no se respetó.');
        $this->assertTrue((bool) $episode->enviado_radioboss, 'RadioBOSS no quedó marcado como enviado.');
        $this->assertSame('skipped', $episode->archive_org_status, 'Archive.org no quedó en estado skipped.');
        $this->assertNotEmpty($episode->imagen_episodio, 'La imagen del episodio no quedó guardada en la base.');
        $this->assertTrue(Storage::disk('public')->exists((string) $episode->imagen_episodio), 'La imagen del episodio no quedó en el disco public.');

        $this->assertTrue(Storage::disk('public')->exists((string) $episode->archivo_mp3), 'El MP3 no quedó en el disco public.');
        $this->assertNotEmpty(Storage::disk('radioboss')->files('Programas'), 'No se subió ningún archivo al disco radioboss fake.');

        Mail::assertSent(ProgramUploadedNotification::class);
    }

    public function test_it_auto_assigns_episode_number_when_field_is_empty(): void
    {
        Storage::fake('public');
        Storage::fake('radioboss');
        Mail::fake();
        config(['services.archive_org.access_key' => '', 'services.archive_org.secret_key' => '']);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $master = MasterProgram::query()->create([
            'nombre' => 'Metal Adicto',
            'conductor' => 'John Doe',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '20:00:00',
            'genero' => 'Metal',
            'ruta_ftp' => 'Programas',
            'email_notificacion' => 'press@example.test',
            'email_copia_notificacion' => null,
            'activo' => true,
        ]);

        RadioProgram::query()->create([
            'master_program_id' => $master->id,
            'titulo_programa' => $master->nombre,
            'conductor' => $master->conductor,
            'numero_episodio' => 7,
            'fecha_emision' => '2026-05-18',
            'archivo_mp3' => 'podcast-inbox/programa/test.mp3',
            'enviado_radioboss' => false,
            'sync_archive_org' => false,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.podcast-uploads.store'), [
                'master_program_id' => $master->id,
                'numero_episodio' => null,
                'live_title' => 'Especial sin capítulo',
                'fecha_emision' => '2026-05-19',
                'resena' => 'Descripción de prueba',
                'sync_archive_org' => true,
                'archivo_mp3' => UploadedFile::fake()->create('episode.mp3', 1024, 'audio/mpeg'),
            ]);

        $response->assertRedirect(route('admin.podcast-uploads.index'));

        $episode = RadioProgram::query()->latest('id')->first();
        $this->assertNotNull($episode, 'No se creó el episodio.');
        $this->assertSame(8, $episode->numero_episodio, 'El correlativo automático no continuó desde el máximo existente.');
        Mail::assertSent(ProgramUploadedNotification::class);
    }

    public function test_it_preserves_a_local_copy_when_download_is_requested(): void
    {
        Storage::fake('public');
        Storage::fake('radioboss');
        Mail::fake();
        config(['services.archive_org.access_key' => '', 'services.archive_org.secret_key' => '']);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $master = MasterProgram::query()->create([
            'nombre' => 'Metal Adicto',
            'conductor' => 'John Doe',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '20:00:00',
            'genero' => 'Metal',
            'ruta_ftp' => 'Programas',
            'email_notificacion' => 'press@example.test',
            'email_copia_notificacion' => null,
            'activo' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.podcast-uploads.store'), [
                'master_program_id' => $master->id,
                'live_title' => 'Especial para descargar',
                'fecha_emision' => '2026-05-19',
                'resena' => 'Descripción de prueba',
                'download_processed_mp3' => true,
                'sync_archive_org' => true,
                'archivo_mp3' => UploadedFile::fake()->create('episode.mp3', 1024, 'audio/mpeg'),
            ]);

        $response->assertRedirect(route('admin.podcast-uploads.index'));

        $episode = RadioProgram::query()->latest('id')->first();
        $this->assertNotNull($episode, 'No se creó el episodio.');
        $this->assertNotEmpty($episode->archivo_mp3, 'No se guardó el MP3 procesado.');
        $this->assertTrue(Storage::disk('public')->exists((string) $episode->archivo_mp3), 'La copia local no se preservó para descarga.');

        $downloadResponse = $this->actingAs($admin)->get(route('admin.podcast-uploads.download', $episode));
        $downloadResponse->assertOk();
    }

    public function test_podcast_uploads_index_groups_programs_by_day_and_opens_current_day(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 19, 10, 0, 0, 'America/Caracas'));

        try {
            $admin = User::factory()->create([
                'is_admin' => true,
            ]);

            MasterProgram::query()->create([
                'nombre' => 'Lunes Show',
                'conductor' => 'DJ Monday',
                'dia_transmision' => 'LUNES',
                'hora_transmision' => '08:00:00',
                'genero' => 'Rock',
                'timezone' => 'America/Caracas',
                'activo' => true,
            ]);

            MasterProgram::query()->create([
                'nombre' => 'Martes Show',
                'conductor' => 'DJ Tuesday',
                'dia_transmision' => 'MARTES',
                'hora_transmision' => '09:00:00',
                'genero' => 'Rock',
                'timezone' => 'America/Caracas',
                'activo' => true,
            ]);

            $response = $this->actingAs($admin)->get(route('admin.podcast-uploads.index'));

            $response->assertOk();
            $response->assertSee("x-data=\"podcastUploadForm({ initialDay: 'MARTES' })\"", false);
            $response->assertSee('data-day-panel="MARTES"', false);
            $response->assertSee('Martes Show');
            $response->assertSee('Lunes Show');
        } finally {
            Carbon::setTestNow();
        }
    }
}

```

