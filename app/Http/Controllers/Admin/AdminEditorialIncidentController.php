<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EditorialIncident;
use App\Models\Post;
use App\Services\AuditTrailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminEditorialIncidentController extends Controller
{
    public function __construct(
        protected AuditTrailService $auditTrail
    ) {}

    public function index(Request $request): View
    {
        $this->ensureAuthorized();

        $query = EditorialIncident::query()->with(['post', 'resolver']);

        $status = $request->string('status', 'pending_review')->value();
        if ($status !== 'all') {
            $query->where('resolution_status', $status);
        }

        $priority = $request->string('priority')->value();
        if ($priority && $priority !== 'all') {
            $query->where('priority', $priority);
        }

        $type = $request->string('type')->value();
        if ($type && $type !== 'all') {
            $query->where('incident_type', $type);
        }

        $incidents = $query->orderByRaw("CASE 
            WHEN priority = 'urgent' THEN 1 
            WHEN priority = 'high' THEN 2 
            WHEN priority = 'medium' THEN 3 
            ELSE 4 END ASC")
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'pending'   => EditorialIncident::where('resolution_status', 'pending_review')->count(),
            'urgent'    => EditorialIncident::where('resolution_status', 'pending_review')->where('priority', 'urgent')->count(),
            'applied'   => EditorialIncident::where('resolution_status', 'resolved_applied')->count(),
            'dismissed' => EditorialIncident::where('resolution_status', 'resolved_dismissed')->count(),
            'all'       => EditorialIncident::count(),
        ];

        return view('admin.editorial.incidents.index', [
            'incidents'       => $incidents,
            'counts'          => $counts,
            'currentStatus'   => $status,
            'currentPriority' => $priority ?: 'all',
            'currentType'     => $type ?: 'all',
        ]);
    }

    public function diff(EditorialIncident $incident): JsonResponse
    {
        $this->ensureAuthorized();

        $incident->loadMissing('post');

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                   => $incident->id,
                'incident_type'        => $incident->incident_type,
                'priority'             => $incident->priority,
                'correlation_score'    => $incident->correlation_score,
                'correlation_evidence' => $incident->correlation_evidence,
                'detected_diff'        => $incident->detected_diff,
                'action_taken'         => $incident->action_taken,
                'post'                 => $incident->post ? [
                    'id'               => $incident->post->id,
                    'title'            => $incident->post->title,
                    'status'           => $incident->post->status,
                    'is_published'     => $incident->post->is_published,
                    'published_at'     => $incident->post->published_at?->format('d/m/Y H:i'),
                ] : null,
                'resolution_status'    => $incident->resolution_status,
                'resolution_notes'     => $incident->resolution_notes,
                'resolved_at'          => $incident->resolved_at?->format('d/m/Y H:i'),
            ],
        ]);
    }

    public function resolve(Request $request, EditorialIncident $incident): RedirectResponse|JsonResponse
    {
        $this->ensureAuthorized();

        $request->validate([
            'resolution_action' => ['required', 'in:apply_changes,dismiss,editorial_note'],
            'resolution_notes'  => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $action = $request->input('resolution_action');
        $notes = $request->input('resolution_notes');

        DB::beginTransaction();
        try {
            $post = $incident->post;

            if ($action === 'apply_changes' && $post) {
                // REGLA FUNDAMENTAL: NUNCA despublicar automáticamente noticias ya publicadas
                if ($post->is_published || $post->status === 'published') {
                    // Para noticias publicadas, aplicar cambios requiere editar el texto con fe de erratas explícita
                    $diff = $incident->detected_diff ?? [];
                    if (!empty($diff['title_correction'])) {
                        $post->title = $diff['title_correction'];
                    }
                    $errataNotice = "\n\n<p class=\"editorial-errata\"><strong>[Fe de Erratas]:</strong> " . e($notes) . "</p>";
                    $post->content = (is_array($post->content) ? json_encode($post->content) : $post->content) . $errataNotice;
                    $post->save();
                } elseif ($post->status === 'scheduled') {
                    // Si estaba programada y fue pausada cautelarmente, puede reanudarse si se especifica en notas
                    $post->status = 'scheduled';
                    $post->save();
                }
            }

            $before = $incident->getOriginal();
            $newResolutionStatus = ($action === 'dismiss') ? 'resolved_dismissed' : 'resolved_applied';

            $incident->resolution_status = $newResolutionStatus;
            $incident->resolved_by_user_id = auth()->id();
            $incident->resolved_at = now();
            $incident->resolution_notes = $notes;
            $incident->save();

            $this->auditTrail->recordModel(
                event: 'incident_resolved',
                model: $incident,
                before: $before,
                after: $incident->getAttributes(),
                context: ['action' => $action, 'notes' => $notes, 'actor_id' => auth()->id()]
            );

            DB::commit();

            $msg = "Incidencia #" . $incident->id . " resuelta con éxito (" . ($newResolutionStatus === 'resolved_applied' ? 'Cambios aplicados' : 'Descartada') . ").";
            return $request->wantsJson()
                ? response()->json(['success' => true, 'message' => $msg])
                : back()->with('success', $msg);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => $e->getMessage()], 500)
                : back()->with('error', $e->getMessage());
        }
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
            abort(403, 'Acceso denegado: se requieren permisos administrativos para gestionar incidencias.');
        }
    }
}
