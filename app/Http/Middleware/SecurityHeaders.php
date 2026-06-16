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

        // Solo aplicar a respuestas HTTP completas (no a StreamedResponse, JsonResponse, etc.)
        if ($response instanceof Response) {

            // Prevención de Clickjacking — evita que el sitio sea embebido en iframes externos
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

            // Prevención de Sniffing de tipos MIME — el navegador respeta el Content-Type declarado
            $response->headers->set('X-Content-Type-Options', 'nosniff');

            // Fuerza HTTPS estricto (HSTS) — solo activo en producción con SSL
            // En local (HTTP) el navegador ignora este header correctamente
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

            // Protección XSS clásica (navegadores legacy)
            $response->headers->set('X-XSS-Protection', '1; mode=block');

            // Política de referrer — envía el origen sin path en requests cross-origin
            // Evita filtrar URLs internas del admin en estadísticas externas
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

            // Permissions Policy — deshabilita APIs del navegador no utilizadas
            // Reduce la superficie de ataque ante scripts de terceros
            $response->headers->set(
                'Permissions-Policy',
                'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()'
            );

            // CSP (Content Security Policy)
            // NOTA: Se usa 'unsafe-inline' y 'unsafe-eval' porque Alpine.js y Tailwind CSS JIT
            // los requieren actualmente. Fase 2 planificada: migrar a CSP con nonces por request,
            // lo que requiere agregar nonce="{{ $cspNonce }}" en cada <script> y <style> de las vistas.
            // Fuentes externas permitidas: Google Fonts, RadioBOSS streams, archive.org y CDNs de uso.
            $csp = implode(' ', [
                "default-src 'self';",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net;",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net;",
                "font-src 'self' https://fonts.gstatic.com data:;",
                "img-src 'self' data: https: blob:;",
                "media-src 'self' https: blob: https://*.radioboss.fm https://*.archive.org;",
                "frame-src 'self' https://www.youtube.com https://player.vimeo.com https://open.spotify.com https://embed.spotify.com;",
                "connect-src 'self' https:;",
                "object-src 'none';",
                "base-uri 'self';",
                "form-action 'self';",
            ]);
            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }
}