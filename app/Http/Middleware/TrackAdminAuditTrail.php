<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AuditTrailService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class TrackAdminAuditTrail
{
    public function __construct(private readonly AuditTrailService $auditTrailService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('_audit_started_at', microtime(true));
        $request->attributes->set('_audit_request_id', (string) Str::uuid());

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->auditTrailService->recordSystem('http.exception', $request->route()?->getName() ?: $request->path(), [
                'request_id' => $request->attributes->get('_audit_request_id'),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'exception' => class_basename($exception),
                'message' => $exception->getMessage(),
            ], 'error');

            throw $exception;
        }

        $this->auditTrailService->recordHttp($request, $response);

        return $response;
    }
}
