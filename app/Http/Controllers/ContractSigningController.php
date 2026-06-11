<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Mail\ContractSignedMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractSigningController extends Controller
{
    public function show(string $token): View|RedirectResponse
    {
        $contract = Contract::query()->where('token', $token)->firstOrFail();

        if ($contract->status === 'signed') {
            return redirect()->route('contratos.exito', ['token' => $token]);
        }

        return view('contratos.firmar', compact('contract'));
    }

    public function sign(Request $request, string $token): RedirectResponse
    {
        $contract = Contract::query()->where('token', $token)->firstOrFail();

        if ($contract->status === 'signed') {
            return redirect()->route('contratos.exito', ['token' => $token])
                ->with('status', 'Este contrato ya ha sido firmado.');
        }

        $request->validate([
            'aceptar_terminos' => ['required', 'accepted'],
            'confirmar_mayoria' => ['required', 'accepted'],
            'nombre_completo' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
        ]);

        // 1. Update status and audit data, including user-provided location
        $contract->status = 'signed';
        $contract->signed_at = Carbon::now();
        $contract->signing_ip = $request->ip();
        $contract->signer_name = $request->input('nombre_completo');
        $contract->country = $request->input('country');
        $contract->city = $request->input('city');
        $contract->save();

        // 2. Generate PDF using DomPDF
        $logoBase64 = null;
        try {
            $theme = \App\Models\ThemeSetting::current();
            $logoPath = null;
            if (str_contains($theme->logo_url, 'assets/lucille/logo.png')) {
                $logoPath = public_path('assets/lucille/logo.png');
            } elseif (str_starts_with($theme->logo_url, asset(''))) {
                $relative = str_replace(asset(''), '', $theme->logo_url);
                $logoPath = public_path($relative);
            }
            
            if ($logoPath && file_exists($logoPath)) {
                $logoData = file_get_contents($logoPath);
                $mime = mime_content_type($logoPath) ?: 'image/png';
                $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode($logoData);
            } else {
                $ctx = stream_context_create([
                    'http' => ['timeout' => 5]
                ]);
                $logoData = @file_get_contents($theme->logo_url, false, $ctx);
                if ($logoData) {
                    $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
                }
            }
        } catch (\Exception $e) {
            logger()->error('Error encoding logo for contract PDF: ' . $e->getMessage());
        }

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $html = view('contratos.pdf_template', [
            'contract' => $contract,
            'nombre' => $contract->signer_name,
            'fecha' => $contract->signed_at->format('d/m/Y'),
            'fecha_hora' => $contract->signed_at->toDateTimeString(),
            'ip' => $contract->signing_ip,
            'logo_base64' => $logoBase64,
        ])->render();

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfOutput = $dompdf->output();

        // 3. Save PDF to local storage
        $filename = 'contrato_' . $contract->id . '_' . uniqid() . '.pdf';
        $path = 'contracts/' . $filename;
        Storage::disk('local')->put($path, $pdfOutput);

        $contract->pdf_path = $path;
        $contract->save();

        // 4. Send email notifications
        try {
            // To the signer
            Mail::to($contract->signer_email)->send(new ContractSignedMail($contract));

            // To the admin (copy)
            $adminEmail = config('mail.from.address');
            if ($adminEmail) {
                Mail::to($adminEmail)->send(new ContractSignedMail($contract));
            }
        } catch (\Exception $e) {
            logger()->error('Error al enviar correos de contrato firmado: ' . $e->getMessage());
        }

        return redirect()->route('contratos.exito', ['token' => $token])
            ->with('status', 'Contrato firmado electrónicamente con éxito absoluto.');
    }

    public function exito(string $token): View|RedirectResponse
    {
        $contract = Contract::query()->where('token', $token)->firstOrFail();

        if ($contract->status !== 'signed') {
            return redirect()->route('contratos.firmar', ['token' => $token]);
        }

        return view('contratos.exito', compact('contract'));
    }

    public function download(string $token): StreamedResponse|RedirectResponse
    {
        $contract = Contract::query()->where('token', $token)->firstOrFail();

        if ($contract->status !== 'signed' || !$contract->pdf_path) {
            abort(404, 'El contrato no ha sido firmado o no se encuentra el archivo.');
        }

        if (!Storage::disk('local')->exists($contract->pdf_path)) {
            abort(404, 'El archivo PDF no existe físicamente.');
        }

        return Storage::disk('local')->download($contract->pdf_path, str_replace(' ', '_', $contract->title) . '_firmado.pdf');
    }
}
