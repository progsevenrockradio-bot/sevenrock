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
            file_put_contents($tempPath, Storage::disk('r2')->get($submission->file_path));

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
}
