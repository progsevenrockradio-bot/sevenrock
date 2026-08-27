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
     */
    public function publishToHub(Request $request, TrackSubmission $submission): RedirectResponse
    {
        if ($submission->status !== 'approved') {
            return redirect()->back()->with('error', 'Solo las maquetas aprobadas pueden publicarse en el Hub.');
        }

        if ($submission->published_to_hub) {
            return redirect()->back()->with('error', 'Esta maqueta ya ha sido publicada en el Hub.');
        }

        $existing = \App\Models\NewRelease::where('title', $submission->song_title)
            ->where('artist_name', $submission->band_name)
            ->exists();

        if ($existing) {
            $submission->update(['published_to_hub' => true]);
            return redirect()->back()->with('error', 'Ya existe un lanzamiento con este artista y título (Duplicado prevenido).');
        }

        try {
            // Generar slug basado en título y artista
            $slugBase = \Illuminate\Support\Str::slug($submission->song_title . '-' . $submission->band_name);
            $slug = $slugBase;
            $count = 1;
            while (\App\Models\NewRelease::where('slug', $slug)->exists()) {
                $slug = $slugBase . '-' . $count;
                $count++;
            }

            // Mapear enlace social a youtube/spotify
            $youtubeUrl = null;
            $spotifyUrl = null;
            if ($submission->social_link) {
                if (str_contains(strtolower($submission->social_link), 'youtube.com') || str_contains(strtolower($submission->social_link), 'youtu.be')) {
                    $youtubeUrl = $submission->social_link;
                } elseif (str_contains(strtolower($submission->social_link), 'spotify.com')) {
                    $spotifyUrl = $submission->social_link;
                }
            }

            $newRelease = \App\Models\NewRelease::create([
                'title' => $submission->song_title,
                'slug' => $slug,
                'artist_name' => $submission->band_name,
                'released_at' => now(),
                'audio_path' => $submission->file_path, // Uses same path in R2
                'youtube_url' => $youtubeUrl,
                'spotify_url' => $spotifyUrl,
                'description' => 'Maqueta descubierta y promocionada por el A&R de Seven Rock Radio.',
                'is_active' => true,
                'show_in_feed' => $request->boolean('show_in_feed'),
                'author_email' => $submission->contact_email,
            ]);

            $submission->update(['published_to_hub' => true]);

            try {
                $mail = new \App\Mail\SubmissionPublishedToHub($submission, $newRelease);
                \Illuminate\Support\Facades\Mail::to($submission->contact_email)->send($mail);

                \App\Models\EmailLog::create([
                    'track_submission_id' => $submission->id,
                    'to_email' => $submission->contact_email,
                    'subject' => $mail->envelope()->subject,
                    'body' => $mail->render(),
                    'status' => 'sent',
                ]);
            } catch (\Throwable $mailException) {
                Log::error('Error sending hub publication email', ['error' => $mailException->getMessage(), 'submission_id' => $submission->id]);
            }

            return redirect()->back()->with('success', 'Maqueta publicada exitosamente en el Catálogo Musical (Hub). Se ha enviado un correo a la banda.');
        } catch (\Throwable $e) {
            Log::error('Error al publicar maqueta al hub ID ' . $submission->id . ': ' . $e->getMessage());
            return redirect()->back()->with('error', 'Ocurrió un error al publicar la maqueta.');
        }
    }

    public function toggleFeed(Request $request, TrackSubmission $submission)
    {
        if (!$submission->published_to_hub) {
            return response()->json(['success' => false, 'message' => 'La maqueta no está publicada en el Hub.']);
        }

        $updatedCount = \App\Models\NewRelease::where('title', $submission->song_title)
            ->where('artist_name', $submission->band_name)
            ->update(['show_in_feed' => $request->boolean('show_in_feed')]);
            
        if ($updatedCount > 0) {
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'No se encontró el lanzamiento asociado.']);
    }

    public function bulkPublish(Request $request): RedirectResponse
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
        $publishedCount = 0;

        foreach ($submissions as $submission) {
            $existing = \App\Models\NewRelease::where('title', $submission->song_title)
                ->where('artist_name', $submission->band_name)
                ->exists();

            if ($existing) {
                $submission->update(['published_to_hub' => true]);
                continue;
            }

            // Re-use logic from publishToHub (inline for bulk)
            $slugBase = \Illuminate\Support\Str::slug($submission->song_title . '-' . $submission->band_name);
            $slug = $slugBase;
            $count = 1;
            while (\App\Models\NewRelease::where('slug', $slug)->exists()) {
                $slug = $slugBase . '-' . $count;
                $count++;
            }

            $youtubeUrl = null;
            $spotifyUrl = null;
            if ($submission->social_link) {
                if (str_contains(strtolower($submission->social_link), 'youtube.com') || str_contains(strtolower($submission->social_link), 'youtu.be')) {
                    $youtubeUrl = $submission->social_link;
                } elseif (str_contains(strtolower($submission->social_link), 'spotify.com')) {
                    $spotifyUrl = $submission->social_link;
                }
            }

            $newRelease = \App\Models\NewRelease::create([
                'title' => $submission->song_title,
                'slug' => $slug,
                'artist_name' => $submission->band_name,
                'released_at' => now(),
                'audio_path' => $submission->file_path,
                'youtube_url' => $youtubeUrl,
                'spotify_url' => $spotifyUrl,
                'description' => 'Maqueta descubierta y promocionada por el A&R de Seven Rock Radio.',
                'is_active' => true,
                'show_in_feed' => $showInFeed,
                'author_email' => $submission->contact_email,
            ]);

            $submission->update(['published_to_hub' => true]);
            $publishedCount++;

            try {
                $mail = new \App\Mail\SubmissionPublishedToHub($submission, $newRelease);
                \Illuminate\Support\Facades\Mail::to($submission->contact_email)->send($mail);

                \App\Models\EmailLog::create([
                    'track_submission_id' => $submission->id,
                    'to_email' => $submission->contact_email,
                    'subject' => $mail->envelope()->subject,
                    'body' => $mail->render(),
                    'status' => 'sent',
                ]);
            } catch (\Throwable $mailException) {
                Log::error('Error sending hub publication email', ['error' => $mailException->getMessage(), 'submission_id' => $submission->id]);
            }
        }

        return redirect()->back()->with('success', "Se publicaron $publishedCount maquetas al Hub correctamente.");
    }
}
