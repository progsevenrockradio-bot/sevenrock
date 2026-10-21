<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailSenderSource;
use App\Services\AuditTrailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminEditorialSourceController extends Controller
{
    public function __construct(
        protected AuditTrailService $auditTrail
    ) {}

    public function index(Request $request): View
    {
        $this->ensureAuthorized();

        $query = EmailSenderSource::query();

        $status = $request->string('status')->trim()->value();
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $search = $request->string('search')->trim()->value();
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $sources = $query->orderBy('name')->paginate(20)->withQueryString();

        $counts = [
            'all'        => EmailSenderSource::count(),
            'authorized' => EmailSenderSource::where('status', 'authorized')->count(),
            'pending'    => EmailSenderSource::where('status', 'pending')->count(),
            'suspended'  => EmailSenderSource::where('status', 'suspended')->count(),
            'blocked'    => EmailSenderSource::where('status', 'blocked')->count(),
        ];

        return view('admin.editorial.sources.index', [
            'sources'       => $sources,
            'counts'        => $counts,
            'currentStatus' => $status ?: 'all',
            'currentSearch' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAuthorized();

        $data = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'organization_type' => ['required', 'string', 'in:label,agency,promoter,artist,distributor,media'],
            'authorized_emails' => ['nullable', 'string'],
            'verified_domains'  => ['nullable', 'string'],
            'status'            => ['required', 'in:authorized,pending,suspended,blocked'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ]);

        $emails = array_values(array_filter(array_map('trim', explode(',', (string) ($data['authorized_emails'] ?? '')))));
        $domains = array_values(array_filter(array_map('trim', explode(',', (string) ($data['verified_domains'] ?? '')))));

        $source = EmailSenderSource::create([
            'name'                 => $data['name'],
            'slug'                 => Str::slug($data['name']),
            'organization_type'    => $data['organization_type'],
            'authorized_emails'    => $emails,
            'verified_domains'     => $domains,
            'status'               => $data['status'],
            'auto_publish_allowed' => false, // Modo B siempre estrictamente desactivado
            'authorized_at'        => ($data['status'] === 'authorized') ? now() : null,
            'authorized_by'        => ($data['status'] === 'authorized') ? auth()->id() : null,
            'notes'                => $data['notes'] ?? null,
        ]);

        $this->auditTrail->recordModel(
            event: 'source_registered',
            model: $source,
            after: $source->getAttributes(),
            context: ['actor_id' => auth()->id()]
        );

        return redirect()->route('admin.editorial.sources.index')
            ->with('success', "Fuente '{$source->name}' registrada correctamente.");
    }

    public function update(Request $request, EmailSenderSource $source): RedirectResponse
    {
        $this->ensureAuthorized();

        $data = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'organization_type' => ['required', 'string', 'in:label,agency,promoter,artist,distributor,media'],
            'authorized_emails' => ['nullable', 'string'],
            'verified_domains'  => ['nullable', 'string'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ]);

        $emails = array_values(array_filter(array_map('trim', explode(',', (string) ($data['authorized_emails'] ?? '')))));
        $domains = array_values(array_filter(array_map('trim', explode(',', (string) ($data['verified_domains'] ?? '')))));

        $before = $source->getOriginal();
        $source->update([
            'name'              => $data['name'],
            'organization_type' => $data['organization_type'],
            'authorized_emails' => $emails,
            'verified_domains'  => $domains,
            'notes'             => $data['notes'] ?? null,
        ]);

        $this->auditTrail->recordModel(
            event: 'source_updated',
            model: $source,
            before: $before,
            after: $source->getAttributes(),
            context: ['actor_id' => auth()->id()]
        );

        return redirect()->route('admin.editorial.sources.index')
            ->with('success', "Fuente '{$source->name}' actualizada correctamente.");
    }

    public function updateStatus(Request $request, EmailSenderSource $source): RedirectResponse|JsonResponse
    {
        $this->ensureAuthorized();

        $request->validate([
            'status' => ['required', 'in:authorized,pending,suspended,blocked'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $newStatus = $request->input('status');
        $reason = $request->input('reason', 'Cambio de estado administrativo');

        $before = $source->getOriginal();
        $source->status = $newStatus;
        if ($newStatus === 'authorized') {
            $source->authorized_at = now();
            $source->authorized_by = auth()->id();
        }

        if ($reason) {
            $source->notes = trim(($source->notes ? $source->notes . "\n" : "") . "[Estado a {$newStatus} el " . now()->format('d/m/Y H:i') . " por usuario #" . auth()->id() . "]: {$reason}");
        }

        $source->save();

        $this->auditTrail->recordModel(
            event: 'source_status_changed',
            model: $source,
            before: $before,
            after: $source->getAttributes(),
            context: ['new_status' => $newStatus, 'reason' => $reason, 'actor_id' => auth()->id()]
        );

        $msg = "El estado de la fuente '{$source->name}' cambió a '{$newStatus}'.";
        return $request->wantsJson()
            ? response()->json(['success' => true, 'message' => $msg])
            : back()->with('success', $msg);
    }

    protected function ensureAuthorized(): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(401, 'No autenticado.');
        }

        $isAuthorized = false;
        if (method_exists($user, 'hasAnyRole')) {
            $isAuthorized = $user->hasAnyRole(['Admin', 'Super Admin']);
        } elseif (method_exists($user, 'can')) {
            $isAuthorized = $user->can('admin');
        }

        if (!$isAuthorized && !($user->is_admin ?? false)) {
            abort(403, 'Acceso denegado: se requieren permisos administrativos para gestionar fuentes editoriales.');
        }
    }
}
