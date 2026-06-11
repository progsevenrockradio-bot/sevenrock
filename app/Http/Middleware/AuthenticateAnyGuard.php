<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAnyGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('web')->check() || Auth::guard('talent')->check()) {
            return $next($request);
        }

        return redirect()->route('afiliados.login')->with('error', 'Debes iniciar sesión para acceder a la comunidad.');
    }
}
