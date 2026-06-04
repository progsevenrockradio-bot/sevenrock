<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=()'
        );

        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=' . (int) config('security.hsts_max_age', 31536000) . '; includeSubDomains'
            );
        }

        $response->headers->set(
            'Content-Security-Policy',
            $this->contentSecurityPolicy($request)
        );

        return $response;
    }

    private function contentSecurityPolicy(Request $request): string
    {
        $streamOrigins = $this->streamOrigins();
        $archiveOrigins = ['https://archive.org', 'https://*.archive.org'];
        $videoFrameSources = [
            'https://www.youtube.com',
            'https://youtube.com',
            'https://www.youtube-nocookie.com',
            'https://youtu.be',
            'https://player.vimeo.com',
        ];
        $viteOrigins = app()->environment('local') ? ['http://127.0.0.1:5173', 'http://localhost:5173', 'ws://127.0.0.1:5173', 'ws://localhost:5173'] : [];
        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            $this->policyDirective('script-src', array_merge(["'self'"], $viteOrigins)),
            $this->policyDirective('style-src', array_merge(["'self'"], $viteOrigins)),
            $this->policyDirective('font-src', ["'self'", 'data:']),
            $this->policyDirective('img-src', array_merge(["'self'", 'data:', 'blob:'], $archiveOrigins)),
            $this->policyDirective('media-src', array_merge(["'self'", 'data:', 'blob:'], $streamOrigins, $archiveOrigins)),
            $this->policyDirective('connect-src', array_merge(["'self'"], $streamOrigins, $archiveOrigins, $viteOrigins)),
            $this->policyDirective('frame-src', array_merge(["'self'"], $videoFrameSources)),
        ];

        if ($request->secure()) {
            $directives[] = 'upgrade-insecure-requests';
            $directives[] = 'block-all-mixed-content';
        }

        return implode('; ', $directives);
    }

    /**
     * @param array<int, string> $sources
     */
    private function policyDirective(string $name, array $sources): string
    {
        $sources = array_values(array_unique(array_filter($sources, static fn ($value): bool => is_string($value) && trim($value) !== '')));

        return $name . ' ' . implode(' ', $sources);
    }

    /**
     * @return array<int, string>
     */
    private function streamOrigins(): array
    {
        $streams = Arr::wrap(config('player.streams', []));
        $origins = [];

        foreach ($streams as $streamUrl) {
            if (! is_string($streamUrl) || trim($streamUrl) === '') {
                continue;
            }

            $parsed = parse_url($streamUrl);
            if (! is_array($parsed) || empty($parsed['host'])) {
                continue;
            }

            $scheme = strtolower((string) ($parsed['scheme'] ?? 'https'));
            $host = (string) $parsed['host'];
            $port = isset($parsed['port']) ? ':' . (int) $parsed['port'] : '';
            $origins[] = $scheme . '://' . $host . $port;
        }

        return array_values(array_unique($origins));
    }
}
