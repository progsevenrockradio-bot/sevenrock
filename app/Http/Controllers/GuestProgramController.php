<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ProgramInvitation;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class GuestProgramController extends Controller
{
    public function edit(Request $request, ProgramInvitation $invitation): View|RedirectResponse
    {
        if ($invitation->completed_at) {
            return redirect('/')->with('status', 'Esta invitación ya ha sido completada y no se puede reutilizar.');
        }

        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            abort(403, 'El enlace temporal ha caducado.');
        }

        $program = $invitation->masterProgram;
        $requestedFields = (array) $invitation->requested_fields;

        return view('pages.program-invitation', [
            'invitation' => $invitation,
            'program' => $program,
            'requestedFields' => $requestedFields,
        ]);
    }

    public function update(Request $request, ProgramInvitation $invitation): RedirectResponse
    {
        if ($invitation->completed_at) {
            return redirect('/')->with('error', 'Esta invitación ya ha sido completada.');
        }

        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            abort(403, 'El enlace temporal ha caducado.');
        }

        $requestedFields = (array) $invitation->requested_fields;

        // Construir las reglas de validación dinámicamente según lo que se haya pedido
        $rules = [];
        if (in_array('nombre', $requestedFields, true)) $rules['nombre'] = ['required', 'string', 'max:255'];
        if (in_array('conductor', $requestedFields, true)) $rules['conductor'] = ['nullable', 'string', 'max:255'];
        if (in_array('genero', $requestedFields, true)) $rules['genero'] = ['nullable', 'string', 'max:255'];
        if (in_array('descripcion', $requestedFields, true)) $rules['descripcion'] = ['nullable', 'string'];
        if (in_array('red_social1_url', $requestedFields, true)) $rules['red_social1_url'] = ['nullable', 'url', 'max:255'];
        if (in_array('red_social2_url', $requestedFields, true)) $rules['red_social2_url'] = ['nullable', 'url', 'max:255'];
        if (in_array('dia_transmision', $requestedFields, true)) $rules['dia_transmision'] = ['nullable', 'string', 'max:255'];
        if (in_array('hora_transmision', $requestedFields, true)) $rules['hora_transmision'] = ['nullable', 'string', 'max:255'];
        if (in_array('caratula_url', $requestedFields, true)) $rules['caratula_url'] = ['nullable', 'url', 'max:2048'];

        $validated = $request->validate($rules);

        $invitation->masterProgram->update($validated);

        $invitation->update(['completed_at' => now()]);

        return redirect('/')->with('status', '¡Información guardada exitosamente! Gracias por tu colaboración.');
    }
}
