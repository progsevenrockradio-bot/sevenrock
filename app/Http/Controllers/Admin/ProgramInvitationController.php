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
            'message' => 'Enlace de invitación generado correctamente.',
        ]);
    }
}
