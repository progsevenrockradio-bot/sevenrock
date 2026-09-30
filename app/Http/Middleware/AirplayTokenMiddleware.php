<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ThemeSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AirplayTokenMiddleware
{
    public function handle(Request , Closure ): Response
    {
         = ThemeSetting::get('airplay_api_token');

        if (empty() || strlen(trim((string) )) < 10) {
            return response()->json([
                'ok'    => false,
                'error' => 'API de programación no configurada en el servidor (503 Service Unavailable).'
            ], 503);
        }

         = ->header('X-Token') ?? ->header('Authorization');
        if (str_starts_with((string) , 'Bearer ')) {
             = substr((string) , 7);
        }

        if (empty() || !hash_equals((string) , (string) )) {
            return response()->json([
                'ok'    => false,
                'error' => 'Token de autenticación no válido o no proporcionado (401 Unauthorized).'
            ], 401);
        }

        return ();
    }
}
