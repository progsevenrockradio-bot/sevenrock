<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubmissionMuroFollowup;
use App\Models\TrackSubmission;
use App\Services\SubmissionFollowupService;
use App\Services\SubmissionPublisher;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AdminTrackSubmissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(?Request $request = null): View
    {
        $request ??= request();

        $query = TrackSubmission::query()->orderByDesc('created_at');

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($inner) use ($search): void {
                $inner->where('band_name', 'like', '%' . $search . '%')
                    ->orWhere('song_title', 'like', '%' . $search . '%')
                    ->orWhere('contact_email', 'like', '%' . $search . '%');
            });
        }

        if ($status = trim((string) $request->input('status', ''))) {
            if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
                $query->where('status', $status);
            }
        }

        $submissions = $query->paginate(20, ['*'], 'maquetas_page')->withQueryString();

        // Get all email logs associated with track submissions
        $emailLogs = \App\Models\EmailLog::query()
            ->whereNotNull('track_submission_id')
            ->with('trackSubmission')
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'logs_page')
            ->withQueryString();

        $followupService = app(SubmissionFollowupService::class);
        $muroFollowups = Schema::hasTable('submission_muro_followups')
            ? SubmissionMuroFollowup::query()
                ->with(['trackSubmission', 'release'])
                ->orderByDesc('updated_at')
                ->paginate(20, ['*'], 'followups_page')
                ->withQueryString()
            : new LengthAwarePaginator([], 0, 20, 1, ['pageName' => 'followups_page']);

        $followupSimulation = $followupService->processDueFollowups(true);
        $followupPreviewHtml = $followupService->renderPreviewHtml($muroFollowups->first());

        return view('admin.submissions.index', [
            'submissions' => $submissions,
            'emailLogs' => $emailLogs,
            'muroFollowups' => $muroFollowups,
            'followupSimulation' => $followupSimulation,
            'followupPreviewHtml' => $followupPreviewHtml,
            'filters' => [
                'search' => (string) $request->input('search', ''),
                'status' => (string) $request->input('status', ''),
            ],
        ]);
    }

    /**
     * Update the status of the specified submission.
     */
    public function updateStatus(Request $request, TrackSubmission $submission): RedirectResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:pending,approved,rejected'],
        ]);

        $oldStatus = $submission->status;
        $newStatus = $request->input('status');

        $submission->update([
            'status' => $newStatus,
        ]);

        if ($oldStatus !== $newStatus && in_array($newStatus, ['approved', 'rejected'])) {
            try {
                $mail = new \App\Mail\SubmissionStatusUpdated($submission);
                \Illuminate\Support\Facades\Mail::to($submission->contact_email)->send($mail);

                \App\Models\EmailLog::create([
                    'track_submission_id' => $submission->id,
                    'to_email' => $submission->contact_email,
                    'subject' => $mail->envelope()->subject,
                    'body' => $mail->render(),
                    'status' => 'sent',
                ]);

                return redirect()->back()->with('success', 'Estado actualizado y correo automático enviado a la banda.');
            } catch (\Throwable $e) {
                Log::error('Error sending submission status email', ['error' => $e->getMessage(), 'submission_id' => $submission->id]);
                return redirect()->back()->with('error', 'Estado actualizado, pero hubo un error al enviar el correo automático.');
            }
        }

        return redirect()->back()->with('success', 'Estado de la maqueta actualizado correctamente.');
    }

    /**
     * Remove the specified submission from storage.
     */
    public function destroy(TrackSubmission $submission): RedirectResponse
    {
        try {
            // Eliminar el archivo MP3 del disco (R2) para no acumular basura
            if ($submission->file_path && Storage::disk('r2')->exists($submission->file_path)) {
                Storage::disk('r2')->delete($submission->file_path);
            }

            $submission->delete();

            return redirect()->back()->with('success', 'Maqueta eliminada permanentemente.');
        } catch (\Throwable $e) {
            Log::error('Error al eliminar maqueta ID ' . $submission->id . ': ' . $e->getMessage());
            return redirect()->back()->with('error', 'Ocurrió un error al intentar eliminar la maqueta.');
        }
    }

    /**
     * Download the specified submission MP3 file with a clean name.
     */
    public function download(TrackSubmission $submission)
    {
        try {
            if (!$submission->file_path || !Storage::disk('r2')->exists($submission->file_path)) {
                return redirect()->back()->with('error', 'El archivo de audio no se encontró en el servidor.');
            }

            $cleanBandName = \Illuminate\Support\Str::slug($submission->band_name);
            $cleanSongTitle = \Illuminate\Support\Str::slug($submission->song_title);
            $fileName = "{$cleanBandName}-{$cleanSongTitle}.mp3";

            // 1. Download file from R2 to a temporary local file
            $tempPath = tempnam(sys_get_temp_dir(), 'mp3_');
            $mp3TempPath = $tempPath . '.mp3';
            rename($tempPath, $mp3TempPath);
            $tempPath = $mp3TempPath;

            $readStream = Storage::disk('r2')->readStream($submission->file_path);
            $writeStream = fopen($tempPath, 'w');
            stream_copy_to_stream($readStream, $writeStream);
            fclose($writeStream);
            if (is_resource($readStream)) {
                fclose($readStream);
            }

            // 2. Initialize getID3 tag writer
            $tagwriter = new \JamesHeinrich\GetID3\WriteTags();
            $tagwriter->filename = $tempPath;
            $tagwriter->tagformats = ['id3v2.3'];
            $tagwriter->overwrite_tags = true; // Overwrite to ensure clean tags
            $tagwriter->tag_encoding = 'UTF-8';
            $tagwriter->remove_other_tags = false;

            // 3. Set the metadata from the database
            $tagData = [
                'title'  => [$submission->song_title],
                'artist' => [$submission->band_name],
                'album'  => ['Maquetas Seven Rock Radio'],
                'year'   => [date('Y')],
            ];

            $tagwriter->tag_data = $tagData;
            $tagwriter->WriteTags();

            // 4. Return the modified file as download and automatically delete the temp file
            return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            Log::error('Error downloading file: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Ocurrió un error al intentar descargar el archivo.');
        }
    }

    /**
     * Publish the approved submission to the NewReleases Hub catalog.
     * Usa el mismo servicio que la publicación en lote (SubmissionPublisher).
     */
    public function publishToHub(Request $request, SubmissionPublisher $publisher, TrackSubmission $submission): RedirectResponse
    {
        $outcome = $publisher->publish($submission, $request->boolean('show_in_feed'));

        return match ($outcome['result']) {
            SubmissionPublisher::PUBLISHED => redirect()->back()->with(
                $outcome['notice'] === TrackSubmission::NOTICE_SENT ? 'success' : 'error',
                $this->noticeMessage($outcome['notice'], true)
            ),
            SubmissionPublisher::ALREADY_PUBLISHED => redirect()->back()->with('error', $outcome['message']),
            default => redirect()->back()->with('error', $outcome['message']),
        };
    }

    /**
     * Reintenta únicamente el aviso de publicación tras un fallo de envío confirmado.
     * No crea ni modifica el lanzamiento.
     */
    public function retryNotice(SubmissionPublisher $publisher, TrackSubmission $submission): RedirectResponse
    {
        $notice = $publisher->retryNotice($submission);

        if ($notice === TrackSubmission::NOTICE_SENT) {
            return redirect()->back()->with('success', 'Aviso de publicación enviado a la banda.');
        }

        return redirect()->back()->with('error', $notice === 'not_retriable'
            ? 'Esta maqueta no tiene un aviso fallido que reintentar.'
            : $this->noticeMessage($notice, false));
    }

    public function pauseFollowup(SubmissionFollowupService $service, SubmissionMuroFollowup $followup): RedirectResponse
    {
        $res = $service->pause($followup);

        return redirect()->back()->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    public function resumeFollowup(SubmissionFollowupService $service, SubmissionMuroFollowup $followup): RedirectResponse
    {
        $res = $service->resume($followup);

        return redirect()->back()->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    public function cancelFollowup(SubmissionFollowupService $service, SubmissionMuroFollowup $followup): RedirectResponse
    {
        $res = $service->cancel($followup);

        return redirect()->back()->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    public function retryFollowup(SubmissionFollowupService $service, SubmissionMuroFollowup $followup): RedirectResponse
    {
        $res = $service->retryFailed($followup);

        return redirect()->back()->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    public function toggleFeed(Request $request, TrackSubmission $submission)
    {
        if (!$submission->published_to_hub) {
            return response()->json(['success' => false, 'message' => 'La maqueta no está publicada en el Hub.']);
        }

        // Si hay vínculo explícito se usa; los registros históricos sin vínculo conservan el comportamiento anterior.
        $query = $submission->new_release_id
            ? \App\Models\NewRelease::where('id', $submission->new_release_id)
            : \App\Models\NewRelease::where('title', $submission->song_title)
                ->where('artist_name', $submission->band_name);

        $updatedCount = $query->update(['show_in_feed' => $request->boolean('show_in_feed')]);

        if ($updatedCount > 0) {
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'No se encontró el lanzamiento asociado.']);
    }

    public function bulkPublish(Request $request, SubmissionPublisher $publisher): RedirectResponse
    {
        $action = $request->input('action');
        $submissionIds = $request->input('submissions', []);

        if (empty($submissionIds)) {
            return redirect()->back()->with('error', 'No se seleccionaron maquetas.');
        }

        $submissions = TrackSubmission::whereIn('id', $submissionIds)->where('status', 'approved')->where('published_to_hub', false)->get();

        if ($submissions->isEmpty()) {
            return redirect()->back()->with('error', 'Ninguna de las maquetas seleccionadas es válida para publicar (deben estar aprobadas y no publicadas).');
        }

        $showInFeed = $action === 'publish_visible';
        $counts = ['published' => 0, 'review' => 0, 'errors' => 0, 'notice_failed' => 0];

        foreach ($submissions as $submission) {
            $outcome = $publisher->publish($submission, $showInFeed);

            match ($outcome['result']) {
                SubmissionPublisher::PUBLISHED => $counts['published']++,
                SubmissionPublisher::NEEDS_REVIEW => $counts['review']++,
                SubmissionPublisher::ERROR => $counts['errors']++,
                default => null,
            };

            if ($outcome['result'] === SubmissionPublisher::PUBLISHED && $outcome['notice'] !== TrackSubmission::NOTICE_SENT) {
                $counts['notice_failed']++;
            }
        }

        $message = "Se publicaron {$counts['published']} maquetas al Hub correctamente.";
        if ($counts['notice_failed'] > 0) {
            $message .= " {$counts['notice_failed']} aviso(s) no se enviaron; revisa el historial y reintenta.";
        }
        if ($counts['review'] > 0) {
            $message .= " {$counts['review']} requieren revisión manual (posible duplicado o registro histórico).";
        }
        if ($counts['errors'] > 0) {
            $message .= " {$counts['errors']} con error.";
        }

        $hasProblems = $counts['notice_failed'] + $counts['review'] + $counts['errors'] > 0;

        return redirect()->back()->with($counts['published'] > 0 && ! $hasProblems ? 'success' : 'error', $message);
    }

    private function noticeMessage(?string $notice, bool $published): string
    {
        $prefix = $published ? 'Maqueta publicada en el Catálogo Musical (Hub). ' : '';

        return $prefix . match ($notice) {
            TrackSubmission::NOTICE_SENT => 'Se ha enviado el aviso a la banda.',
            TrackSubmission::NOTICE_FAILED => 'El aviso por correo falló; la publicación se conserva y puedes reintentar solo el aviso.',
            TrackSubmission::NOTICE_UNKNOWN => 'El aviso pudo enviarse pero no se registró; no se reenviará automáticamente, revisa el historial.',
            TrackSubmission::NOTICE_SKIPPED => 'El correo de contacto no es válido; no se envió aviso.',
            'not_public' => 'La ficha aún no es pública (inactiva, fecha futura o embargo); no se envió aviso.',
            default => 'El aviso queda pendiente.',
        };
    }
}
