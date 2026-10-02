<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ThemeSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AirplayTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = ThemeSetting::get('airplay_api_token');

        if (empty($token) || strlen(trim((string) $token)) < 10) {
            return response()->json([
                'ok'    => false,
                'error' => 'API de programación no configurada en el servidor (503 Service Unavailable).'
            ], 503);
        }

        $incoming = $request->header('X-Token') ?? $request->header('Authorization');
        if (str_starts_with((string) $incoming, 'Bearer ')) {
            $incoming = substr((string) $incoming, 7);
        }

        if (empty($incoming) || !hash_equals((string) $token, (string) $incoming)) {
            return response()->json([
                'ok'    => false,
                'error' => 'Token de autenticación no válido o no proporcionado (401 Unauthorized).'
            ], 401);
        }

        return $next($request);
    }
}
