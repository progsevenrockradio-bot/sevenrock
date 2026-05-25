<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireTalent
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('talent')->check()) {
            return redirect()->route('talents.login');
        }

        return $next($request);
    }
}
