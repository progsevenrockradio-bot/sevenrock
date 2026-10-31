<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireConductor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('conductor.login');
        }

        $user = Auth::user();
        if (! $user || ! method_exists($user, 'hasConductorAccess') || ! $user->hasConductorAccess()) {
            abort(403, 'No tienes autorización activa como conductor de programas.');
        }

        return $next($request);
    }
}

