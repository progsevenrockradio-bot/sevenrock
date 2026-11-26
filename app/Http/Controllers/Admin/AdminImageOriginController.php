<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuthorizedImageOrigin;
use App\Services\AuditTrailService;
use App\Services\EmailSafetyValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminImageOriginController extends Controller
{
    public function __construct(
        protected AuditTrailService $auditTrail,
        protected EmailSafetyValidator $safetyValidator
    ) {}

    public function index(): View
    {
        $this->ensureAuthorized();

        $origins = AuthorizedImageOrigin::query()
            ->with(['creator', 'updater'])
            ->orderByDesc('is_active')
            ->orderBy('label')
            ->get();

        return view('admin.editorial.image-origins.index', [
            'origins'      => $origins,
            'builtInHosts' => $this->safetyValidator->getAllowedImageHosts(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAuthorized();

        $data = $this->validateOrigin($request);

        $origin = AuthorizedImageOrigin::create([
            'label'         => $data['label'],
            'host'          => $data['host'],
            'path_prefix'   => $data['path_prefix'],
            'require_https' => $data['require_https'],
            'is_active'     => $data['is_active'],
            'scope'         => $data['scope'],
            'notes'         => $data['notes'],
            'created_by'    => auth()->id(),
            'updated_by'    => auth()->id(),
        ]);

        $this->auditTrail->recordModel(
            event: 'image_origin_authorized',
            model: $origin,
            after: $origin->getAttributes(),
            context: ['actor_id' => auth()->id()]
        );

        return redirect()->route('admin.editorial.image-origins.index')
            ->with('success', "Origen de imágenes '{$origin->label}' guardado correctamente.");
    }

    public function update(Request $request, AuthorizedImageOrigin $origin): RedirectResponse
    {
        $this->ensureAuthorized();

        $data = $this->validateOrigin($request);
        $before = $origin->getOriginal();

        $origin->update([
            'label'         => $data['label'],
            'host'          => $data['host'],
            'path_prefix'   => $data['path_prefix'],
            'require_https' => $data['require_https'],
            'is_active'     => $data['is_active'],
            'scope'         => $data['scope'],
            'notes'         => $data['notes'],
            'updated_by'    => auth()->id(),
        ]);

        $this->auditTrail->recordModel(
            event: 'image_origin_updated',
            model: $origin,
            before: $before,
            after: $origin->getAttributes(),
            context: ['actor_id' => auth()->id()]
        );

        return redirect()->route('admin.editorial.image-origins.index')
            ->with('success', "Origen de imágenes '{$origin->label}' actualizado correctamente.");
    }

    public function toggle(AuthorizedImageOrigin $origin): RedirectResponse
    {
        $this->ensureAuthorized();

        $before = $origin->getOriginal();
        $origin->update([
            'is_active'  => ! $origin->is_active,
            'updated_by' => auth()->id(),
        ]);

        $this->auditTrail->recordModel(
            event: $origin->is_active ? 'image_origin_enabled' : 'image_origin_disabled',
            model: $origin,
            before: $before,
            after: $origin->getAttributes(),
            context: ['actor_id' => auth()->id()]
        );

        $state = $origin->is_active ? 'activado' : 'desactivado';

        return redirect()->route('admin.editorial.image-origins.index')
            ->with('success', "Origen '{$origin->label}' {$state} correctamente.");
    }

    public function destroy(AuthorizedImageOrigin $origin): RedirectResponse
    {
        $this->ensureAuthorized();

        $before = $origin->getAttributes();
        $label = $origin->label;

        $this->auditTrail->recordModel(
            event: 'image_origin_deleted',
            model: $origin,
            before: $before,
            context: ['actor_id' => auth()->id()]
        );

        $origin->delete();

        return redirect()->route('admin.editorial.image-origins.index')
            ->with('success', "Origen '{$label}' eliminado correctamente.");
    }

    /**
     * @return array{label: string, host: string, path_prefix: ?string, require_https: bool, is_active: bool, scope: string, notes: ?string}
     */
    protected function validateOrigin(Request $request): array
    {
        $validated = $request->validate([
            'label'         => ['required', 'string', 'max:150'],
            'host'          => ['required', 'string', 'max:255'],
            'path_prefix'   => ['nullable', 'string', 'max:255'],
            'require_https' => ['nullable', 'boolean'],
            'is_active'     => ['nullable', 'boolean'],
            'scope'         => ['required', Rule::in([AuthorizedImageOrigin::SCOPE_MANUAL_EDITORIAL, AuthorizedImageOrigin::SCOPE_ALL])],
            'notes'         => ['nullable', 'string', 'max:1000'],
        ]);

        $normalizedHost = AuthorizedImageOrigin::normalizeHost($validated['host']);
        if ($normalizedHost === '' || ! str_contains($normalizedHost, '.') || $this->safetyValidator->isPrivateOrLoopbackHost($normalizedHost)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'host' => 'Debe indicar un dominio público válido (no se permiten IPs privadas, localhost ni direcciones internas).',
            ]);
        }

        $normalizedPath = AuthorizedImageOrigin::normalizePathPrefix($validated['path_prefix'] ?? null);

        // Salvaguarda: para CDNs multiinquilino como res.cloudinary.com exigir siempre prefijo de cuenta/ruta
        if ($normalizedHost === 'res.cloudinary.com' && $normalizedPath === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'path_prefix' => 'Para res.cloudinary.com es obligatorio indicar el prefijo de ruta de la cuenta concreta (por ejemplo /dutx97jzy/image/upload/) para no abrir el permiso a todas las cuentas.',
            ]);
        }

        return [
            'label'         => trim($validated['label']),
            'host'          => $normalizedHost,
            'path_prefix'   => $normalizedPath,
            'require_https' => $request->boolean('require_https', true),
            'is_active'     => $request->boolean('is_active', true),
            'scope'         => $validated['scope'],
            'notes'         => trim((string) ($validated['notes'] ?? '')) ?: null,
        ];
    }

    protected function ensureAuthorized(): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(401, 'No autenticado.');
        }

        $isAuthorized = false;
        if (method_exists($user, 'hasAnyRole')) {
            $isAuthorized = $user->hasAnyRole(['Admin', 'Super Admin']);
        } elseif (method_exists($user, 'can')) {
            $isAuthorized = $user->can('admin');
        }

        if (! $isAuthorized && ! ($user->is_admin ?? false)) {
            abort(403, 'Acceso denegado: se requieren permisos administrativos para gestionar orígenes de imágenes autorizados.');
        }
    }
}

