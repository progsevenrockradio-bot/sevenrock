<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrackSubmission;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AdminTrackSubmissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        // Get all submissions ordered by latest first
        $submissions = TrackSubmission::query()
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'maquetas_page');
            
        // Get all email logs associated with track submissions
        $emailLogs = \App\Models\EmailLog::query()
            ->whereNotNull('track_submission_id')
            ->with('trackSubmission')
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'logs_page');

        return view('admin.submissions.index', compact('submissions', 'emailLogs'));
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
            if ($submission->file_path && Storage::disk(config('filesystems.default'))->exists($submission->file_path)) {
                Storage::disk(config('filesystems.default'))->delete($submission->file_path);
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
        if (!$submission->file_path || !Storage::disk(config('filesystems.default'))->exists($submission->file_path)) {
            return redirect()->back()->with('error', 'El archivo de audio no se encontró en el servidor.');
        }

        $cleanBandName = \Illuminate\Support\Str::slug($submission->band_name);
        $cleanSongTitle = \Illuminate\Support\Str::slug($submission->song_title);
        $fileName = "{$cleanBandName}-{$cleanSongTitle}.mp3";

        return Storage::disk(config('filesystems.default'))->download($submission->file_path, $fileName);
    }
}
