<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Mail\ContractSignRequestMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractController extends Controller
{
    public function index(): View
    {
        $contracts = Contract::query()->latest()->paginate(15);
        return view('admin.contracts.index', compact('contracts'));
    }

    public function create(): View
    {
        $defaultTemplate = '<p><strong>CONTRATO DE ALQUILER DE ESPACIO DIGITAL Y DIFUSIÓN MULTIMEDIA</strong></p>
<p>Reunidos por una parte, <strong>SEVEN ROCK RADIO</strong> (en adelante, el "Proveedor"), y por la otra parte, el firmante cuyos datos figuran en el formulario de aceptación (en adelante, el "Arrendatario" o "Artista").</p>
<p>Ambas partes declaran tener y reconocerse mutuamente la capacidad legal necesaria para el otorgamiento del presente contrato, de conformidad con las siguientes:</p>
<p><strong>CLÁUSULAS</strong></p>
<p><strong>PRIMERA. Objeto del Contrato.</strong> El Proveedor concede al Artista un espacio digital de almacenamiento en la plataforma web de Seven Rock Radio para alojar archivos multimedia (audios en formato MP3, imágenes promocionales, notas de prensa y material audiovisual). Asimismo, se autoriza la difusión de dichos contenidos a través de los canales de la emisora.</p>
<p><strong>SEGUNDA. Gratuidad del Servicio.</strong> El presente acuerdo se establece con carácter de apoyo cultural y promoción mutua, por lo que no conlleva ninguna contraprestación económica por el espacio digital otorgado por el Proveedor ni por la cesión de contenidos promocionales del Artista.</p>
<p><strong>TERCERA. Propiedad Intelectual y Responsabilidad.</strong> El Artista declara bajo su total responsabilidad ser el autor legítimo de las obras subidas a la plataforma o poseer todos los derechos, licencias y autorizaciones necesarias. El Artista conserva la propiedad intelectual de sus obras y cede de manera no exclusiva y temporal los derechos de reproducción y comunicación pública al Proveedor para fines de difusión en la radio.</p>
<p><strong>CUARTA. Validez Legal de la Firma Clickwrap.</strong> De conformidad con la legislación internacional de comercio electrónico y firma digital, ambas partes aceptan de mutuo propio que el consentimiento expresado electrónicamente mediante el marcado de la casilla de aceptación y el envío del formulario constituye una firma electrónica vinculante con plena validez legal y probatoria.</p>';

        return view('admin.contracts.create', compact('defaultTemplate'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'signer_name' => ['required', 'string', 'max:255'],
            'signer_email' => ['required', 'email', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        $contract = Contract::query()->create([
            'token' => Str::random(32),
            'signer_name' => $validated['signer_name'],
            'signer_email' => $validated['signer_email'],
            'title' => $validated['title'],
            'content' => $validated['content'],
            'status' => 'pending',
        ]);

        try {
            Mail::to($contract->signer_email)->send(new ContractSignRequestMail($contract));
        } catch (\Exception $e) {
            logger()->error('Error al enviar correo de firma de contrato: ' . $e->getMessage());
        }

        return redirect()
            ->route('admin.contracts.index')
            ->with('success', 'Contrato creado y correo de firma enviado con éxito.');
    }

    public function send(Contract $contract): RedirectResponse
    {
        try {
            Mail::to($contract->signer_email)->send(new ContractSignRequestMail($contract));
            return back()->with('success', 'Enlace de firma reenviado con éxito a ' . $contract->signer_email);
        } catch (\Exception $e) {
            logger()->error('Error al reenviar correo de firma: ' . $e->getMessage());
            return back()->with('error', 'No se pudo enviar el correo: ' . $e->getMessage());
        }
    }

    public function download(Contract $contract): StreamedResponse|RedirectResponse
    {
        if ($contract->status !== 'signed' || !$contract->pdf_path) {
            return back()->with('error', 'El contrato aún no ha sido firmado o el archivo no existe.');
        }

        if (!Storage::disk('local')->exists($contract->pdf_path)) {
            return back()->with('error', 'El archivo PDF no se encuentra físicamente en el servidor.');
        }

        return Storage::disk('local')->download($contract->pdf_path, str_replace(' ', '_', $contract->title) . '_firmado.pdf');
    }
}
