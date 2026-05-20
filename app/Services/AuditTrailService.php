<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AuditTrailService
{
    /**
     * @param array<string, mixed> $context
     */
    public function recordHttp(Request $request, ?Response $response, array $context = []): ?AuditLog
    {
        if (! Schema::hasTable('audit_logs')) {
            return null;
        }

        $route = $request->route();
        $routeName = $route?->getName();
        $actor = $this->resolveActor($context);
        $durationMs = (int) round(((microtime(true) - (float) ($request->attributes->get('_audit_started_at') ?? microtime(true))) * 1000));
        $statusCode = $response?->getStatusCode();
        $event = $statusCode !== null && $statusCode >= 400 ? 'request.failed' : 'request.completed';
        $level = $statusCode !== null && $statusCode >= 500 ? 'error' : ($statusCode !== null && $statusCode >= 400 ? 'warning' : 'info');

        return AuditLog::create([
            'user_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,
            'actor_type' => $actor !== null ? 'admin' : 'guest',
            'category' => 'http',
            'event' => $event,
            'level' => $level,
            'summary' => $this->buildHttpSummary($request, $routeName, $statusCode),
            'method' => $request->method(),
            'route_name' => $routeName,
            'url' => $request->fullUrl(),
            'status_code' => $statusCode,
            'duration_ms' => $durationMs,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => (string) ($request->attributes->get('_audit_request_id') ?? null),
            'request_payload' => $this->sanitizePayload($request->except(['password', 'password_confirmation', '_token'])),
            'request_meta' => [
                'query' => $this->sanitizePayload($request->query()),
                'files' => $this->describeFiles($request),
            ],
            'response_meta' => $this->describeResponse($response, $request),
            'context' => $context,
        ]);
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @param array<string, mixed> $changes
     * @param array<string, mixed> $context
     */
    public function recordModel(string $event, Model $model, array $before = [], array $after = [], array $changes = [], array $context = [], string $level = 'info'): ?AuditLog
    {
        if (! Schema::hasTable('audit_logs')) {
            return null;
        }

        $actor = $this->resolveActor($context);
        $subjectType = $model::class;
        $subjectId = (string) $model->getKey();
        $subjectLabel = method_exists($model, 'getAuditLabel') ? (string) $model->getAuditLabel() : class_basename($model);

        return AuditLog::create([
            'user_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,
            'actor_type' => $actor !== null ? 'admin' : 'system',
            'category' => 'model',
            'event' => $event,
            'level' => $level,
            'summary' => $this->buildModelSummary($event, $subjectLabel, $subjectId),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'before_state' => $this->sanitizePayload($before),
            'after_state' => $this->sanitizePayload($after),
            'changes' => $this->sanitizePayload($changes),
            'context' => $this->sanitizePayload($context),
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function recordSystem(string $event, string $summary, array $context = [], string $level = 'info'): ?AuditLog
    {
        if (! Schema::hasTable('audit_logs')) {
            return null;
        }

        $actor = $this->resolveActor($context);

        return AuditLog::create([
            'user_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,
            'actor_type' => $actor !== null ? 'admin' : 'system',
            'category' => 'system',
            'event' => $event,
            'level' => $level,
            'summary' => $summary,
            'context' => $this->sanitizePayload($context),
        ]);
    }

    private function resolveActor(array $context): ?User
    {
        if (isset($context['actor']) && $context['actor'] instanceof User) {
            return $context['actor'];
        }

        if (isset($context['actor']) && is_array($context['actor'])) {
            $actor = new User();
            $actor->forceFill([
                'id' => $context['actor']['id'] ?? null,
                'name' => $context['actor']['name'] ?? null,
                'email' => $context['actor']['email'] ?? null,
            ]);

            return $actor;
        }

        $user = Auth::user();
        return $user instanceof User ? $user : null;
    }

    private function buildHttpSummary(Request $request, ?string $routeName, ?int $statusCode): string
    {
        $action = $routeName ?: $request->path();
        $status = $statusCode !== null ? (string) $statusCode : 'pending';

        return strtoupper($request->method()) . ' ' . $action . ' [' . $status . ']';
    }

    private function buildModelSummary(string $event, string $subjectLabel, string $subjectId): string
    {
        return trim($event . ' ' . $subjectLabel . ' #' . $subjectId);
    }

    /**
     * @return array<string, mixed>
     */
    private function describeResponse(?Response $response, Request $request): array
    {
        if ($response === null) {
            return [];
        }

        $meta = [
            'class' => $response::class,
            'status' => $response->getStatusCode(),
        ];

        $location = $response->headers->get('Location');
        if (is_string($location) && $location !== '') {
            $meta['location'] = $location;
        }

        if ($request->expectsJson()) {
            $meta['expects_json'] = true;
        }

        return $meta;
    }

    /**
     * @return array<string, mixed>
     */
    private function describeFiles(Request $request): array
    {
        $files = [];

        foreach ($request->allFiles() as $key => $value) {
            $files[$key] = $this->describeUploadedFile($value);
        }

        return $files;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function sanitizePayload(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return $this->describeUploadedFile($value);
        }

        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $item) {
                $keyName = is_string($key) ? $key : (string) $key;
                if ($this->isSensitiveKey($keyName)) {
                    $sanitized[$keyName] = '[redacted]';
                    continue;
                }

                $sanitized[$keyName] = $this->sanitizePayload($item);
            }

            return $sanitized;
        }

        if (is_object($value)) {
            if ($value instanceof \Stringable) {
                return (string) $value;
            }

            return class_basename($value);
        }

        if (is_string($value)) {
            return $this->looksSensitive($value) ? '[redacted]' : $value;
        }

        return $value;
    }

    private function describeUploadedFile(UploadedFile $file): array
    {
        return [
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ];
    }

    private function isSensitiveKey(string $key): bool
    {
        return Str::contains(Str::lower($key), [
            'password',
            'password_confirmation',
            'secret',
            'token',
            'authorization',
            'api_key',
            'api_secret',
            'access_key',
            'private_key',
            'smtp',
            'mail_password',
            'ftp_password',
        ]);
    }

    private function looksSensitive(string $value): bool
    {
        return Str::contains(Str::lower($value), ['bearer ', 'authorization:', 'low ']);
    }
}
