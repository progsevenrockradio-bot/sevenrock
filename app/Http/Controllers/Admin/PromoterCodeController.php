<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromoterCode;
use App\Models\Talent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\PromoterCodeMail;

class PromoterCodeController extends Controller
{
    public function index(Request $request)
    {
        $query = PromoterCode::query()->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('owner_name', 'like', "%{$search}%")
                  ->orWhere('owner_email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_archived', false);
            } elseif ($request->status === 'archived') {
                $query->where('is_archived', true);
            }
        }

        $promoters = $query->paginate(20)->withQueryString();

        return view('admin.talents.promoters.index', compact('promoters'));
    }

    public function create()
    {
        $talents = Talent::orderBy('band_name')->get();
        return view('admin.talents.promoters.create', compact('talents'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'owner_name' => 'required|string|max:255',
            'owner_email' => 'required|email|max:255|unique:promoter_codes,owner_email,NULL,id,is_archived,0',
            'owner_type' => 'required|in:conductor,persona,banda',
            'campaign' => 'nullable|string|max:64',
            'max_bands' => 'nullable|integer|min:1',
            'assigned_talent_id' => 'nullable|exists:talents,id',
            'notes' => 'nullable|string',
        ]);

        $maxBands = $validated['max_bands'] ?? PromoterCode::DEFAULT_MAX_BANDS;

        $promoter = PromoterCode::create([
            'code' => PromoterCode::generateUniqueCode(),
            'owner_name' => $validated['owner_name'],
            'owner_email' => $validated['owner_email'],
            'owner_type' => $validated['owner_type'],
            'campaign' => $validated['campaign'] ?: 'pilot_2026',
            'max_bands' => $maxBands,
            'assigned_talent_id' => $validated['assigned_talent_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('admin.talents.promoters.index')
            ->with('success', "Código {$promoter->code} creado con éxito.");
    }

    public function grantCollaboratorCourtesy(Request $request, PromoterCode $promoter, \App\Services\TalentSubscriptionService $subscriptionService)
    {
        if (! $promoter->assigned_talent_id || ! $promoter->assignedTalent) {
            return back()->with('error', 'Este promotor no tiene una banda asociada. Los locutores sin banda solo tienen seguimiento de resultados; no se asignan meses a cuentas ficticias.');
        }

        /** @var \App\Models\User $admin */
        $admin = auth()->user() ?? \App\Models\User::first() ?? new \App\Models\User(['id' => 1]);

        try {
            $sub = $subscriptionService->grantCourtesy(
                talent: $promoter->assignedTalent,
                admin: $admin,
                months: 2,
                courtesyType: 'initial_collaborator',
                notes: "Cortesía inicial (2 meses Básico) concedida al colaborador {$promoter->owner_name} (Código {$promoter->code})"
            );

            return back()->with('success', "Cortesía de 2 meses iniciales concedida con éxito a la banda '{$promoter->assignedTalent->band_name}'. Vigencia hasta el {$sub->end_date->format('d/m/Y')}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al conceder la cortesía: ' . $e->getMessage());
        }
    }

    public function expand(Request $request, PromoterCode $promoter)
    {
        $validated = $request->validate([
            'new_limit' => 'required|integer|min:1',
        ]);

        $activeCount = $promoter->activeBandsCount();
        if ($validated['new_limit'] < $activeCount) {
            return back()->with('error', "El nuevo tope debe ser mayor o igual a las {$activeCount} bandas ya registradas.");
        }

        $promoter->expandLimit($validated['new_limit']);

        return back()->with('success', "Tope ampliado a {$validated['new_limit']} bandas.");
    }

    public function renew(PromoterCode $promoter)
    {
        $newCode = $promoter->archiveAndRenew();

        return back()->with('success', "Código renovado. El código anterior quedó archivado y el nuevo es {$newCode->code}.");
    }

    public function sendEmail(PromoterCode $promoter)
    {
        Mail::to($promoter->owner_email)->send(new PromoterCodeMail($promoter));

        $promoter->update([
            'notes' => trim($promoter->notes . "\n[Enviado el " . now()->format('Y-m-d H:i') . "]")
        ]);

        return back()->with('success', 'Correo enviado con éxito al promotor.');
    }
}
