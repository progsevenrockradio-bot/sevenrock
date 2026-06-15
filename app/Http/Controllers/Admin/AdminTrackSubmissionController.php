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
            ->paginate(20);

        return view('admin.submissions.index', compact('submissions'));
    }

    /**
     * Update the status of the specified submission.
     */
    public function updateStatus(Request $request, TrackSubmission $submission): RedirectResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:pending,approved,rejected'],
        ]);

        $submission->update([
            'status' => $request->input('status'),
        ]);

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
}
