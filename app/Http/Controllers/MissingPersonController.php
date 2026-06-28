<?php

namespace App\Http\Controllers;

use App\Models\MissingPerson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MissingPersonController extends Controller
{
    // === PUBLIC METHODS ===

    public function index(Request $request)
    {
        $query = MissingPerson::approved()->active()->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('cedula', 'like', "%{$search}%")
                  ->orWhere('place_of_residence', 'like', "%{$search}%");
            });
        }

        $missingPersons = $query->paginate(25)->withQueryString();

        return view('pages.missing-persons.index', compact('missingPersons', 'search'));
    }

    public function create()
    {
        return view('pages.missing-persons.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'cedula' => 'nullable|string|max:20',
            'age' => 'nullable|integer|min:0|max:150',
            'sex' => 'nullable|in:masculino,femenino,otro',
            'place_of_residence' => 'nullable|string|max:255',
            'emergency_contact_number' => 'nullable|string|max:100',
            'last_seen_location' => 'nullable|string|max:255',
            'missing_since' => 'nullable|date|before_or_equal:today',
            'description' => 'nullable|string|max:2000',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $data['is_approved'] = true;
        $data['status'] = 'active';

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('missing-persons', 'public');
        }

        MissingPerson::create($data);

        return redirect()->route('missing-persons.index')
            ->with('success', 'Reporte enviado y publicado exitosamente en nuestra plataforma.');
    }

    // === MODERATION METHODS ===

    public function moderationIndex(Request $request)
    {
        $query = MissingPerson::query()->latest();

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }
        if ($request->has('approved') && $request->approved !== '') {
            $query->where('is_approved', $request->boolean('approved'));
        }

        $missingPersons = $query->paginate(20)->withQueryString();

        return view('pages.missing-persons.moderation', compact('missingPersons'));
    }

    public function approve(MissingPerson $missingPerson)
    {
        $missingPerson->update(['is_approved' => true]);
        return back()->with('success', 'Reporte aprobado exitosamente.');
    }

    public function markAsFound(MissingPerson $missingPerson)
    {
        $missingPerson->update(['status' => 'found']);
        return back()->with('success', 'Persona marcada como encontrada.');
    }

    public function edit(MissingPerson $missingPerson)
    {
        return view('pages.missing-persons.edit', compact('missingPerson'));
    }

    public function update(Request $request, MissingPerson $missingPerson)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'cedula' => 'nullable|string|max:20',
            'age' => 'nullable|integer|min:0|max:150',
            'sex' => 'nullable|in:masculino,femenino,otro',
            'place_of_residence' => 'nullable|string|max:255',
            'emergency_contact_number' => 'nullable|string|max:100',
            'last_seen_location' => 'nullable|string|max:255',
            'missing_since' => 'nullable|date|before_or_equal:today',
            'description' => 'nullable|string|max:2000',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'is_approved' => 'boolean',
            'status' => 'in:active,found',
        ]);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('missing-persons', 'public');
        }

        $data['is_approved'] = $request->boolean('is_approved');

        $missingPerson->update($data);

        return redirect()->route('missing-persons.moderation.index')
            ->with('success', 'Registro actualizado exitosamente.');
    }

    public function destroy(MissingPerson $missingPerson)
    {
        $missingPerson->delete();
        return back()->with('success', 'Registro eliminado exitosamente.');
    }
}
