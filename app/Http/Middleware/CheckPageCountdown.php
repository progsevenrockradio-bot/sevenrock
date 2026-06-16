<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\PageCountdown;
use Illuminate\Support\Facades\Auth;

class CheckPageCountdown
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow admins to bypass the countdown
        if (Auth::guard('web')->check() && Auth::guard('web')->user()?->hasAdminAccess()) {
            return $next($request);
        }

        $path = $request->path();

        // Find an active countdown matching the path
        $countdown = PageCountdown::where('is_enabled', true)
            ->where(function($query) use ($path) {
                // Exact match or wildcard match
                $query->where('route_path', $path)
                      ->orWhere('route_path', '/' . $path)
                      ->orWhereRaw('? LIKE REPLACE(route_path, "*", "%")', [$path]);
            })
            ->first();

        if ($countdown) {
            // Check if active_at is in the future
            if ($countdown->active_at && $countdown->active_at->isFuture()) {
                return response()->view('pages.coming-soon', compact('countdown'));
            }
        }

        return $next($request);
    }
}
