<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModerationItem;
use App\Services\ModerationService;
use Illuminate\Http\Request;

class ModerationController extends Controller
{
    public function __construct(private ModerationService $service)
    {
    }

    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');
        $items = ModerationItem::with('subject')
            ->where('status', $status)
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return view('admin.moderation.index', compact('items', 'status'));
    }

    public function show(ModerationItem $item)
    {
        return view('admin.moderation.show', compact('item'));
    }

    public function approve(Request $request, ModerationItem $item)
    {
        $this->service->approve($item, auth()->id(), $request->input('note'));
        return redirect()->back()->with('success', 'Aprobado correctamente.');
    }

    public function reject(Request $request, ModerationItem $item)
    {
        $this->service->reject($item, auth()->id(), $request->input('note'));
        return redirect()->back()->with('success', 'Denegado correctamente.');
    }

    // Rutas para aprobación directa desde correo (Signed URLs)
    public function approveFromEmail(Request $request, ModerationItem $item)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'El enlace ha caducado o no es válido.');
        }

        $this->service->approve($item, null, 'Aprobado desde el correo');
        return response("Aprobado: {$item->title}");
    }

    public function rejectFromEmail(Request $request, ModerationItem $item)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'El enlace ha caducado o no es válido.');
        }

        $this->service->reject($item, null, 'Denegado desde el correo');
        return response("Denegado: {$item->title}");
    }
}
