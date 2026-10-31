<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Services\FileUploadService;
use App\Support\PublicMediaUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicPodcastEpisodeController extends Controller
{
    public function show(string $programSlug, int $episodeNumber): View
    {
        $master = $this->resolveMasterProgramBySlug($programSlug);
        $episode = RadioProgram::query()
            ->with(['masterProgram', 'submittedBy'])
            ->where('master_program_id', $master->id)
            ->where('numero_episodio', $episodeNumber)
            ->firstOrFail();

        // Proteger borradores, pendientes, rechazados o versiones invalidadas
        if (! $episode->isApproved()) {
            abort(404, 'El episodio solicitado no está disponible públicamente o se encuentra pendiente de revisión editorial.');
        }

        $coverUrl = PublicMediaUrl::normalizePublicUrl(
            $episode->imagen_episodio ?: $episode->caratula_programa ?: $master->cover_url ?: asset('assets/lucille/logo.png')
        );

        $audioPlaybackUrl = $episode->audioPlaybackUrl();

        // Otros programas activos para sección de descubrimiento
        $otherPrograms = MasterProgram::query()
            ->where('activo', true)
            ->where('id', '!=', $master->id)
            ->orderBy('nombre')
            ->take(4)
            ->get();

        return view('pages.public-podcast-episode', [
            'master' => $master,
            'episode' => $episode,
            'coverUrl' => $coverUrl,
            'audioPlaybackUrl' => $audioPlaybackUrl,
            'allowPublicDownload' => $episode->isPublicDownloadAllowed(),
            'otherPrograms' => $otherPrograms,
        ]);
    }

    public function download(string $programSlug, int $episodeNumber): BinaryFileResponse|StreamedResponse|RedirectResponse
    {
        $master = $this->resolveMasterProgramBySlug($programSlug);
        $episode = RadioProgram::query()
            ->with('masterProgram')
            ->where('master_program_id', $master->id)
            ->where('numero_episodio', $episodeNumber)
            ->firstOrFail();

        // 1. Verificar aprobación editorial y procesamiento definitivo
        if (! $episode->isApproved() || ! $episode->isProcessedInR2()) {
            abort(403, 'Este episodio no está disponible para descarga.');
        }

        // 2. Verificar permiso administrativo específico de descarga pública
        if (! $episode->allow_public_download) {
            abort(403, 'La descarga pública no ha sido habilitada por la administración para este episodio.');
        }

        $normalizedPath = ltrim(str_replace('\\', '/', trim((string) $episode->archivo_mp3)), '/');
        $disk = (string) ($episode->archivo_mp3_disk ?: 'r2');
        $downloadFilename = $episode->readableDownloadFilename();

        if ($disk === 'r2') {
            if (! Storage::disk('r2')->exists($normalizedPath)) {
                abort(404, 'El archivo de audio no se encuentra en el almacenamiento definitivo.');
            }

            // Transmisión directa segura desde R2 sin persistir copias en el disco local de Hostinger
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
                    'Content-Length' => $size,
                    'Content-Disposition' => sprintf('attachment; filename="%s"', addslashes($downloadFilename)),
                    'Cache-Control' => 'no-cache, private',
                ]
            );
        }

        // Si fuera disco local temporal
        $fileUploadService = app(FileUploadService::class);
        $localPath = $fileUploadService->localPath($normalizedPath, $disk);
        if ($localPath === null || ! is_file($localPath)) {
            abort(404, 'El archivo solicitado no está disponible.');
        }

        $isTemporaryDownload = str_starts_with($localPath, storage_path('app/tmp/'));

        return response()->download($localPath, $downloadFilename)->deleteFileAfterSend($isTemporaryDownload);
    }

    private function resolveMasterProgramBySlug(string $slug): MasterProgram
    {
        $normalizedSlug = Str::slug($slug);

        /** @var MasterProgram|null $program */
        $program = MasterProgram::query()
            ->where('storage_folder', $slug)
            ->orWhere('storage_folder', $normalizedSlug)
            ->first();

        if (! $program) {
            $programs = MasterProgram::all();
            foreach ($programs as $p) {
                if ($p->publicSlug() === $normalizedSlug || Str::slug($p->nombre) === $normalizedSlug) {
                    return $p;
                }
            }
        }

        if (! $program && is_numeric($slug)) {
            $program = MasterProgram::query()->find((int) $slug);
        }

        if (! $program) {
            abort(404, 'Programa maestro no encontrado.');
        }

        return $program;
    }
}
