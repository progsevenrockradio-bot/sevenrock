<?php

declare(strict_types=1);

namespace App\Services;

class LinkSecurityClassifier
{
    /**
     * Dominios de plataformas EPK y distribución cerrada para prensa.
     */
    protected const EPK_DOMAINS = [
        'haulix.com',
        'promojukebox.com',
        'ipool.info',
        'disco.ac',
        'fatdrop.co.uk',
        'label-engine.com',
        'presskit.to',
        'plaympe.com',
        'submithub.com',
        'groover.co',
    ];

    /**
     * Dominios de almacenamiento y transferencia de archivos privados.
     */
    protected const CLOUD_STORAGE_DOMAINS = [
        'dropbox.com',
        'drive.google.com',
        'wetransfer.com',
        'mega.nz',
        'box.com',
        'onedrive.live.com',
        '1drv.ms',
    ];

    /**
     * Parámetros que denotan autenticación, tokens temporales o acceso reservado.
     */
    protected const SENSITIVE_QUERY_PARAMS = [
        'token',
        'key',
        'secret',
        'passcode',
        'password',
        'access_token',
        'auth',
        'signature',
        'sig',
        'expires',
        'download_code',
    ];

    /**
     * Clasifica una URL individual según su nivel de riesgo y características de privacidad.
     *
     * @return array [
     *     'url'                 => string (original),
     *     'unwrapped_url'       => string (desenvuelta estructuralmente si aplica),
     *     'redacted_url'        => string (con tokens enmascarados),
     *     'classification'      => string ('verified_public'|'unverified_public'|'restricted_private'|'under_embargo'|'suspicious'|'unknown'),
     *     'is_allowed_in_post'  => bool,
     *     'reason'              => string,
     * ]
     */
    public function classify(string $url, array $context = []): array
    {
        $url = trim($url);
        $unwrapped = $this->unwrapStructuralRedirect($url);
        $redacted = $this->redactUrl($unwrapped);

        // 1. Chequeo de seguridad: esquemas maliciosos o SSRF/redes privadas
        if ($this->isSuspiciousOrInternal($unwrapped)) {
            return [
                'url'                => $url,
                'unwrapped_url'      => $unwrapped,
                'redacted_url'       => $redacted,
                'classification'     => 'suspicious',
                'is_allowed_in_post' => false,
                'reason'             => 'ssrf_or_malicious_scheme_detected',
            ];
        }

        $host = strtolower(parse_url($unwrapped, PHP_URL_HOST) ?? '');
        $path = parse_url($unwrapped, PHP_URL_PATH) ?? '';
        $query = parse_url($unwrapped, PHP_URL_QUERY) ?? '';

        // 2. Enlaces EPK y plataformas de prensa
        foreach (self::EPK_DOMAINS as $epkDomain) {
            if ($host === $epkDomain || str_ends_with($host, '.' . $epkDomain)) {
                return [
                    'url'                => $url,
                    'unwrapped_url'      => $unwrapped,
                    'redacted_url'       => $redacted,
                    'classification'     => 'restricted_private',
                    'is_allowed_in_post' => false,
                    'reason'             => 'epk_promo_platform',
                ];
            }
        }

        // 3. SoundCloud privado (secret token en path: /s-xxxxx)
        if ($host === 'soundcloud.com' || str_ends_with($host, '.soundcloud.com')) {
            if (preg_match('/\/s-[a-zA-Z0-9_-]+/i', $path)) {
                return [
                    'url'                => $url,
                    'unwrapped_url'      => $unwrapped,
                    'redacted_url'       => $redacted,
                    'classification'     => 'restricted_private',
                    'is_allowed_in_post' => false,
                    'reason'             => 'soundcloud_private_secret_token',
                ];
            }
        }

        // 4. Parámetros de token o firma en cualquier URL
        parse_str($query, $queryParams);
        foreach ($queryParams as $paramKey => $val) {
            $paramKeyLower = strtolower((string) $paramKey);
            if (in_array($paramKeyLower, self::SENSITIVE_QUERY_PARAMS, true)) {
                return [
                    'url'                => $url,
                    'unwrapped_url'      => $unwrapped,
                    'redacted_url'       => $redacted,
                    'classification'     => 'restricted_private',
                    'is_allowed_in_post' => false,
                    'reason'             => "sensitive_parameter_present: {$paramKeyLower}",
                ];
            }
        }

        // 5. Almacenamiento en la nube (Dropbox, Drive, etc.)
        foreach (self::CLOUD_STORAGE_DOMAINS as $cloudDomain) {
            if ($host === $cloudDomain || str_ends_with($host, '.' . $cloudDomain)) {
                return [
                    'url'                => $url,
                    'unwrapped_url'      => $unwrapped,
                    'redacted_url'       => $redacted,
                    'classification'     => 'restricted_private',
                    'is_allowed_in_post' => false,
                    'reason'             => 'cloud_storage_file_share',
                ];
            }
        }

        // 6. Restricciones editoriales en contexto (ej. unlisted YouTube con embargo o advertencia)
        if (! empty($context['is_embargoed']) || ! empty($context['press_exclusive'])) {
            return [
                'url'                => $url,
                'unwrapped_url'      => $unwrapped,
                'redacted_url'       => $redacted,
                'classification'     => 'under_embargo',
                'is_allowed_in_post' => false,
                'reason'             => 'press_exclusive_or_embargoed_context',
            ];
        }

        // 7. Plataformas públicas verificadas
        if ($this->isVerifiedPublicPlatform($host, $path)) {
            return [
                'url'                => $url,
                'unwrapped_url'      => $unwrapped,
                'redacted_url'       => $redacted,
                'classification'     => 'verified_public',
                'is_allowed_in_post' => true,
                'reason'             => 'verified_public_streaming_or_social',
            ];
        }

        // 8. Dominios web HTTP/HTTPS estándar no verificados
        if (in_array(parse_url($unwrapped, PHP_URL_SCHEME), ['http', 'https'], true) && ! empty($host)) {
            return [
                'url'                => $url,
                'unwrapped_url'      => $unwrapped,
                'redacted_url'       => $redacted,
                'classification'     => 'unverified_public',
                'is_allowed_in_post' => false, // No admitido para auto-publicación hasta revisión
                'reason'             => 'unverified_external_web',
            ];
        }

        // 9. Desconocido
        return [
            'url'                => $url,
            'unwrapped_url'      => $unwrapped,
            'redacted_url'       => $redacted,
            'classification'     => 'unknown',
            'is_allowed_in_post' => false,
            'reason'             => 'unknown_link_structure',
        ];
    }

    /**
     * Enmascara tokens y credenciales en una URL.
     */
    public function redactUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! isset($parts['query'])) {
            // Ocultar secret tokens en rutas de SoundCloud
            return preg_replace('/(\/s-)[a-zA-Z0-9_-]+/i', '$1***REDACTED***', $url);
        }

        parse_str($parts['query'], $queryParams);
        $modified = false;

        foreach ($queryParams as $k => $v) {
            if (in_array(strtolower((string) $k), self::SENSITIVE_QUERY_PARAMS, true)) {
                $queryParams[$k] = '***REDACTED***';
                $modified = true;
            }
        }

        if ($modified) {
            $parts['query'] = str_replace('%2A', '*', http_build_query($queryParams));
            return $this->buildUrlFromParts($parts);
        }

        return preg_replace('/(\/s-)[a-zA-Z0-9_-]+/i', '$1***REDACTED***', $url);
    }

    /**
     * Desanida URLs de redirección de plataformas masivas de correo de forma estrictamente estructural.
     * NUNCA realiza solicitudes HTTP remotas desde el servidor, evitando SSRF.
     */
    public function unwrapStructuralRedirect(string $url): string
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        $query = parse_url($url, PHP_URL_QUERY) ?? '';

        // Mailchimp (list-manage.com) o Brevo (sendibm.com)
        if (str_contains($host, 'list-manage.com') || str_contains($host, 'sendibm.com') || str_contains($host, 'mailjet.com')) {
            parse_str($query, $queryParams);
            // Parámetros típicos de destino
            foreach (['url', 'u', 'redirect', 'target'] as $targetParam) {
                if (! empty($queryParams[$targetParam])) {
                    $extracted = urldecode((string) $queryParams[$targetParam]);
                    if (filter_var($extracted, FILTER_VALIDATE_URL)) {
                        return $extracted;
                    }
                }
            }
        }

        return $url;
    }

    /**
     * Comprueba si la URL apunta a recursos internos, redes privadas o esquemas no permitidos.
     */
    protected function isSuspiciousOrInternal(string $url): bool
    {
        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            return true;
        }

        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
            return true;
        }

        // Servicios de metadatos de Cloud (169.254.169.254)
        if ($host === '169.254.169.254' || str_starts_with($host, '169.254.')) {
            return true;
        }

        // Rango de IPs privadas
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return true;
            }
        }

        return false;
    }

    protected function isVerifiedPublicPlatform(string $host, string $path): bool
    {
        // YouTube público
        if (in_array($host, ['youtube.com', 'www.youtube.com', 'youtu.be', 'm.youtube.com'], true)) {
            return true;
        }

        // Spotify público
        if ($host === 'open.spotify.com') {
            return str_starts_with($path, '/album/') || str_starts_with($path, '/track/') || str_starts_with($path, '/artist/');
        }

        // Bandcamp público
        if (str_ends_with($host, '.bandcamp.com') || $host === 'bandcamp.com') {
            return str_starts_with($path, '/album/') || str_starts_with($path, '/track/') || $path === '' || $path === '/';
        }

        // Apple Music público
        if ($host === 'music.apple.com') {
            return true;
        }

        // Redes sociales públicas
        if (in_array($host, ['instagram.com', 'www.instagram.com', 'facebook.com', 'www.facebook.com', 'twitter.com', 'x.com'], true)) {
            return true;
        }

        return false;
    }

    protected function buildUrlFromParts(array $parts): string
    {
        $scheme   = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host     = $parts['host'] ?? '';
        $port     = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path     = $parts['path'] ?? '';
        $query    = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return "{$scheme}{$host}{$port}{$path}{$query}{$fragment}";
    }
}
