<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendProducerInvitationJob;
use App\Models\MasterProgram;
use App\Services\ProgramScheduleFormatter;
use App\Mail\ProgramScheduleMail;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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
            'templates' => collect(), // OutreachTemplate model removed — templates disabled
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

    public function sendInvitation(): RedirectResponse
    {
        return back()->with('error', 'La funcionalidad de invitaciones está deshabilitada en este dominio.');
    }

    public function invitations(): View
    {
        return view('admin.programs.invitations', [
            'programs' => MasterProgram::query()->orderBy('nombre')->get(),
            'templates' => collect(), // OutreachTemplate model removed — templates disabled
        ]);
    }

    public function exportPdf(Request $request, ProgramScheduleFormatter $formatter)
    {
        $ids = $request->input('ids', []);
        if (empty($ids) || !is_array($ids)) {
            return back()->with('error', 'No se seleccionaron programas.');
        }

        $programs = MasterProgram::query()->whereIn('id', $ids)->get();
        if ($programs->isEmpty()) {
            return back()->with('error', 'No se encontraron los programas.');
        }

        $formattedData = $formatter->format($programs);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $html = view('pdf.program-schedule', ['groupedPrograms' => $formattedData])->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="horarios-programas.pdf"',
        ]);
    }

    public function sendEmail(Request $request, ProgramScheduleFormatter $formatter)
    {
        $data = $request->validate([
            'email' => ['required', 'string', function ($attribute, $value, $fail) {
                $emails = array_filter(array_map('trim', explode(',', $value)));
                if (empty($emails)) {
                    $fail('Debes proporcionar al menos un correo electrónico.');
                }
                foreach ($emails as $email) {
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $fail("El correo '{$email}' no es válido.");
                    }
                }
            }],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:master_programs,id'],
        ]);

        $programs = MasterProgram::query()->whereIn('id', $data['ids'])->get();
        $formattedData = $formatter->format($programs);

        // Generate PDF
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $htmlPdf = view('pdf.program-schedule', ['groupedPrograms' => $formattedData])->render();
        $dompdf->loadHtml($htmlPdf);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfOutput = base64_encode($dompdf->output());

        $emails = array_filter(array_map('trim', explode(',', $data['email'])));

        // Send Email
        Mail::to($emails)->queue(new ProgramScheduleMail(
            subjectLine: $data['subject'],
            customMessage: $data['message'] ?? '',
            groupedPrograms: $formattedData,
            pdfAttachment: $pdfOutput
        ));

        return response()->json(['message' => 'El correo ha sido enviado correctamente.']);
    }
}
