<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MasterProgram;
use App\Models\ProgramInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class ProgramInvitationController extends Controller
{
    public function store(Request $request, MasterProgram $masterProgram): JsonResponse
    {
        $validated = $request->validate([
            'requested_fields' => ['required', 'array', 'min:1'],
            'requested_fields.*' => ['string'],
            'expires_in_days' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $invitation = ProgramInvitation::create([
            'master_program_id' => $masterProgram->id,
            'requested_fields' => $validated['requested_fields'],
            'expires_at' => now()->addDays((int) $validated['expires_in_days']),
        ]);

        $url = URL::temporarySignedRoute(
            'invitation.program.edit',
            $invitation->expires_at,
            ['invitation' => $invitation->id]
        );

        return response()->json([
            'success' => true,
            'url' => $url,
            'invitation_id' => $invitation->id,
            'message' => 'Enlace de invitación generado correctamente.',
        ]);
    }

    public function sendEmail(Request $request, MasterProgram $masterProgram, ProgramInvitation $invitation): JsonResponse
    {
        $url = URL::temporarySignedRoute(
            'invitation.program.edit',
            $invitation->expires_at,
            ['invitation' => $invitation->id]
        );

        $email = $request->input('email', $masterProgram->email_notificacion);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'success' => false,
                'message' => 'El programa no tiene un correo válido configurado.'
            ], 400);
        }

        try {
            \Illuminate\Support\Facades\Mail::to($email)->send(new \App\Mail\ProducerInvitationMail($masterProgram, $url));
            
            return response()->json([
                'success' => true,
                'message' => "Correo enviado exitosamente a {$email}"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el correo: ' . $e->getMessage()
            ], 500);
        }
    }
}
