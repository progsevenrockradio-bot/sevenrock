<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::query()->with('user')->latest();

        if ($search = trim((string) $request->string('q'))) {
            $query->where(function ($inner) use ($search): void {
                $inner->where('summary', 'like', '%' . $search . '%')
                    ->orWhere('route_name', 'like', '%' . $search . '%')
                    ->orWhere('event', 'like', '%' . $search . '%')
                    ->orWhere('actor_name', 'like', '%' . $search . '%')
                    ->orWhere('actor_email', 'like', '%' . $search . '%');
            });
        }

        if ($category = trim((string) $request->string('category'))) {
            $query->where('category', $category);
        }

        if ($level = trim((string) $request->string('level'))) {
            $query->where('level', $level);
        }

        if ($event = trim((string) $request->string('event'))) {
            $query->where('event', $event);
        }

        return view('admin.audit-logs.index', [
            'logs' => $query->paginate(50)->withQueryString(),
            'filters' => [
                'q' => $request->string('q')->toString(),
                'category' => $request->string('category')->toString(),
                'level' => $request->string('level')->toString(),
                'event' => $request->string('event')->toString(),
            ],
            'stats' => [
                'total' => AuditLog::query()->count(),
                'errors' => AuditLog::query()->where('level', 'error')->count(),
                'warnings' => AuditLog::query()->where('level', 'warning')->count(),
                'today' => AuditLog::query()->whereDate('created_at', now()->toDateString())->count(),
            ],
        ]);
    }
}
