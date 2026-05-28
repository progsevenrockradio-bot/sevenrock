<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendProducerInvitationJob;
use App\Models\MasterProgram;
use App\Models\OutreachTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProgramCodeController extends Controller
{
    public function index(Request $request): View
    {
        $query = MasterProgram::query()->orderBy('nombre');

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($inner) use ($search): void {
                $inner->where('nombre', 'like', '%' . $search . '%')
                    ->orWhere('program_code', 'like', '%' . $search . '%')
                    ->orWhere('conductor', 'like', '%' . $search . '%')
                    ->orWhere('email_notificacion', 'like', '%' . $search . '%');
            });
        }

        return view('admin.programs.index', [
            'programs' => $query->paginate(20)->withQueryString(),
            'templates' => OutreachTemplate::query()->active()->orderBy('name')->get(),
            'search' => (string) $request->input('search', ''),
        ]);
    }

    public function generateCode(MasterProgram $program): RedirectResponse
    {
        $program->forceFill([
            'program_code' => MasterProgram::generateUniqueProgramCode((string) $program->name, $program->id),
            'code_prefix' => MasterProgram::normalizeProgramCode((string) $program->name),
        ])->saveQuietly();

        return back()->with('status', 'Código regenerado.');
    }

    public function sendInvitation(Request $request, MasterProgram $program): RedirectResponse
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer', 'exists:outreach_templates,id'],
        ]);

        SendProducerInvitationJob::dispatch($program->id, (int) $data['template_id']);

        return back()->with('status', 'Invitación encolada para el productor.');
    }

    public function invitations(): View
    {
        return view('admin.programs.invitations', [
            'programs' => MasterProgram::query()->orderBy('nombre')->get(),
            'templates' => OutreachTemplate::query()->active()->orderBy('name')->get(),
        ]);
    }
}
