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
    public function index(Request $request): View
    {
        $query = Contract::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('signer_name', 'like', "%{$search}%")
                  ->orWhere('signer_email', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('country', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('country')) {
            $query->where('country', $request->input('country'));
        }

        $contracts = $query->latest()->paginate(15)->withQueryString();

        $countries = Contract::query()
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->pluck('country');

        return view('admin.contracts.index', compact('contracts', 'countries'));
    }

    public function create(): View
    {
        $templates = config('contracts.templates', []);
        $defaultTemplate = config('contracts.templates.free.body', '');

        return view('admin.contracts.create', compact('templates', 'defaultTemplate'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'signer_name' => ['required', 'string', 'max:255'],
            'signer_email' => ['required', 'email', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'band_name' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        $contract = Contract::query()->create([
            'token' => Str::random(32),
            'signer_name' => $validated['signer_name'],
            'signer_email' => $validated['signer_email'],
            'title' => $validated['title'],
            'band_name' => $validated['band_name'],
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

    public function show(Contract $contract): View
    {
        return view('admin.contracts.show', compact('contract'));
    }

    public function destroy(Contract $contract): RedirectResponse
    {
        if ($contract->pdf_path && Storage::disk('local')->exists($contract->pdf_path)) {
            Storage::disk('local')->delete($contract->pdf_path);
        }

        $contract->delete();

        return redirect()
            ->route('admin.contracts.index')
            ->with('success', 'Contrato y su archivo asociado eliminados con éxito.');
    }
}
