<?php

namespace App\Http\Controllers;

use App\Models\TrackSubmission;
use App\Mail\TrackSubmissionReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SubmissionController extends Controller
{
    /**
     * Muestra el formulario para enviar una maqueta.
     */
    public function create()
    {
        return view('submissions.create');
    }

    /**
     * Almacena una nueva maqueta (Track Submission).
     */
    public function store(Request $request)
    {
        // 1. Validación Estricta
        $validated = $request->validate([
            'band_name'     => ['required', 'string', 'max:255'],
            'song_title'    => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'social_link'   => ['nullable', 'url', 'max:255'],
            // Tamaño en KB: 50MB = 50 * 1024 = 51200 KB
            'audio_file'       => ['required', 'file', 'mimes:mp3,wav,flac', 'max:51200'],
            'marketing_opt_in' => ['nullable', 'boolean'],
        ]);

        $marketingOptIn = $request->boolean('marketing_opt_in');

        // 2. Almacenamiento Seguro en Cloudflare R2
        $file = $request->file('audio_file');
        
        // Generamos un slug limpio con el nombre de la banda para organizar las carpetas en el bucket
        $bandSlug = Str::slug($validated['band_name']);
        $folderPath = "submissions/{$bandSlug}";

        // Subimos el archivo a R2. putFile() genera automáticamente un nombre hash único para el archivo.
        $path = Storage::disk('r2')->putFile($folderPath, $file);

        // 3. Guardar en Base de Datos (incluyendo respuesta, fecha, origen y versión del consentimiento opcional)
        $submission = TrackSubmission::create([
            'band_name'                => $validated['band_name'],
            'song_title'               => $validated['song_title'],
            'contact_email'            => $validated['contact_email'],
            'social_link'              => $validated['social_link'] ?? null,
            'file_path'                => $path,
            'status'                   => 'pending',
            'marketing_opt_in'         => $marketingOptIn,
            'marketing_opt_in_at'      => $marketingOptIn ? now() : null,
            'marketing_opt_in_source'  => TrackSubmission::CONSENT_SOURCE_FORM,
            'marketing_opt_in_version' => TrackSubmission::CONSENT_TEXT_VERSION,
        ]);

        if ($marketingOptIn) {
            try {
                $normalizedEmail = mb_strtolower(trim($submission->contact_email));
                $existingContact = \App\Models\MarketingContact::whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();
                if (! $existingContact) {
                    \App\Models\MarketingContact::create([
                        'email' => trim($submission->contact_email),
                        'name' => $submission->band_name,
                        'company_or_band' => $submission->band_name,
                        'is_active' => true,
                        'source_type' => TrackSubmission::CONSENT_SOURCE_FORM,
                    ]);
                } elseif (empty($existingContact->unsubscribe_token)) {
                    $existingContact->update(['unsubscribe_token' => bin2hex(random_bytes(32))]);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('No se pudo sincronizar MarketingContact tras opt-in de maqueta', ['submission_id' => $submission->id, 'error' => $e->getMessage()]);
            }
        }

        app(\App\Services\ModerationService::class)->register('submission', [
            'subject_type' => TrackSubmission::class,
            'subject_id' => $submission->id,
            'title' => "Maqueta: {$submission->band_name} - {$submission->song_title}",
            'summary' => "Enlace: " . ($submission->social_link ?? 'N/A'),
            'submitter_name' => $submission->band_name,
            'submitter_email' => $submission->contact_email,
        ]);

        // 4. Enviar correo de confirmación al artista y registrarlo en el historial (solo envíos nuevos;
        //    las solicitudes antiguas no reciben acuse retroactivo). Un fallo del correo no tumba el envío ya guardado.
        $ack = new TrackSubmissionReceived($submission);
        try {
            Mail::to($submission->contact_email)->send($ack);
            $ackStatus = 'sent';
            $ackBody = $ack->render();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Fallo al enviar el acuse de recepción de maqueta', ['submission_id' => $submission->id, 'error' => $e->getMessage()]);
            $ackStatus = 'failed';
            $ackBody = 'Fallo de envío: ' . Str::limit($e->getMessage(), 500);
        }

        try {
            \App\Models\EmailLog::create([
                'track_submission_id' => $submission->id,
                'to_email' => $submission->contact_email,
                'subject' => $ack->envelope()->subject,
                'body' => $ackBody,
                'status' => $ackStatus,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('No se pudo registrar el acuse de recepción', ['submission_id' => $submission->id, 'error' => $e->getMessage()]);
        }

        // 5. Respuesta
        // Si la petición viene de Axios/Fetch API (Javascript)
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => '¡Maqueta recibida correctamente! Nuestro equipo la revisará pronto.'
            ], 201);
        }

        // Si la petición viene de un formulario HTML clásico
        return back()->with('success', '¡Maqueta recibida correctamente! Nuestro equipo de A&R la revisará muy pronto.');
    }
}
