<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Validar que la respuesta sea un objeto válido de Symfony/Laravel antes de tocarlo
        if ($response instanceof Response) {
            
            // Prevención de Clickjacking
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
            
            // Prevención de Sniffing de tipos MIME
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            
            // Fuerza HTTPS estricto (HSTS)
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            
            // Protección XSS clásica
            $response->headers->set('X-XSS-Protection', '1; mode=block');

            // CSP (Content Security Policy) - Modo permisivo inicial para no romper la radio
            // Permite 'unsafe-inline' y 'unsafe-eval' temporalmente para asegurar que Vue/Alpine/Blade funcionen
            $csp = "default-src 'self' https: data: 'unsafe-inline' 'unsafe-eval';";
            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }
}