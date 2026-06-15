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
            'audio_file'    => ['required', 'file', 'mimes:mp3,wav,flac', 'max:51200'], 
        ]);

        // 2. Almacenamiento Seguro en Cloudflare R2
        $file = $request->file('audio_file');
        
        // Generamos un slug limpio con el nombre de la banda para organizar las carpetas en el bucket
        $bandSlug = Str::slug($validated['band_name']);
        $folderPath = "submissions/{$bandSlug}";

        // Subimos el archivo a R2. putFile() genera automáticamente un nombre hash único para el archivo.
        $path = Storage::disk('r2')->putFile($folderPath, $file);

        // 3. Guardar en Base de Datos
        $submission = TrackSubmission::create([
            'band_name'     => $validated['band_name'],
            'song_title'    => $validated['song_title'],
            'contact_email' => $validated['contact_email'],
            'social_link'   => $validated['social_link'] ?? null,
            'file_path'     => $path,
            'status'        => 'pending',
        ]);

        // 4. Enviar correo de confirmación al artista
        Mail::to($submission->contact_email)->send(new TrackSubmissionReceived($submission));

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
