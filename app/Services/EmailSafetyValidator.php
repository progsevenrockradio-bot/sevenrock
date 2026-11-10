<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmailPostSanitizedMeta;
use App\Models\Post;
use Illuminate\Support\Facades\Log;

class EmailSafetyValidator
{
    protected LinkSecurityClassifier $linkClassifier;

    public function __construct(?LinkSecurityClassifier $linkClassifier = null)
    {
        $this->linkClassifier = $linkClassifier ?: new LinkSecurityClassifier();
    }

    public function getLinkClassifier(): LinkSecurityClassifier
    {
        return $this->linkClassifier;
    }

    /**
     * Valida de manera exhaustiva el contenido final de un Post listo para ser publicado.
     * Inspecciona: título, excerpt, content HTML, featured_image, enlaces y embeds.
     *
     * @param array<string, mixed> $context Opciones adicionales (ej. approved_urls pasadas en la llamada)
     * @return array [
     *     'is_safe'     => bool,
     *     'violations'  => array<string>,
     *     'has_embargo' => bool,
     * ]
     */
    public function validatePost(Post $post, ?\Carbon\Carbon $targetPublishDate = null, array $context = []): array
    {
        $violations = [];
        $hasEmbargo = false;

        $title = is_array($post->title) ? json_encode($post->title) : (string) ($post->title ?? '');
        $excerpt = is_array($post->excerpt) ? json_encode($post->excerpt) : (string) ($post->excerpt ?? '');
        
        // El mutator de Post::content siempre devuelve un array de bloques renderizables
        $rawContent = $post->content;
        $content = '';
        if (is_array($rawContent)) {
            foreach ($rawContent as $block) {
                if (is_string($block)) {
                    $content .= $block . ' ';
                } elseif (is_array($block) && isset($block['value'])) {
                    $content .= (string) $block['value'] . ' ';
                } else {
                    $content .= json_encode($block, JSON_UNESCAPED_SLASHES) . ' ';
                }
            }
        } else {
            $content = (string) $rawContent;
        }

        $image = is_array($post->featured_image) ? json_encode($post->featured_image) : (string) ($post->featured_image ?? '');

        $fullText = mb_strtolower("{$title} {$excerpt} {$content}");

        // 1. Detección de contraseñas o tokens en texto visible
        if (preg_match('/\b(?:password|passcode|contrase[ñn]a|c[oó]digo\s+de\s+descarga|download\s+code)\s*[:=]\s*([^\s<"\'&]+)/iu', $fullText, $m)) {
            if ($m[1] !== '[protected_credential]') {
                $violations[] = "Contraseña o credencial de descarga expuesta en el contenido público ('{$m[0]}').";
            }
        }

        // 2. Detección de enlaces restringidos, EPK, tokens o streams privados en el HTML
        $approvedUrls = $this->resolveApprovedUrls($post, $context);

        if (preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\']/i', $content, $linkMatches)) {
            foreach ($linkMatches[1] as $href) {
                $href = trim($href);
                if ($href === '') {
                    continue;
                }

                // Detección estricta de credenciales incrustadas en URL
                if (parse_url($href, PHP_URL_USER) !== null || parse_url($href, PHP_URL_PASS) !== null) {
                    $violations[] = "Enlace con credenciales incrustadas en URL no permitido: '{$this->linkClassifier->redactUrl($href)}'.";
                    continue;
                }

                $classified = $this->linkClassifier->classify($href);

                // Enlaces sospechosos, restringidos (EPK, cloud drive, token) o bajo embargo NUNCA se permiten
                if (in_array($classified['classification'], ['suspicious', 'restricted_private', 'under_embargo'], true)) {
                    $violations[] = "Enlace no permitido en contenido público: [{$classified['classification']}] {$classified['redacted_url']} (motivo: {$classified['reason']}).";
                    continue;
                }

                // Enlaces ya permitidos por plataforma pública verificada (YouTube, Spotify, etc.)
                if ($classified['is_allowed_in_post']) {
                    continue;
                }

                // Enlaces a la web externa estándar (ej. Wikipedia, medios de prensa, sellos)
                if ($classified['classification'] === 'unverified_public') {
                    if ($this->isUrlApproved($href, $approvedUrls)) {
                        // URL concreta aprobada tras verificación editorial
                        continue;
                    }

                    $violations[] = "Enlace no permitido en contenido público: [{$classified['classification']}] {$classified['redacted_url']} (motivo: {$classified['reason']}). Requiere aprobación editorial de la URL concreta.";
                    continue;
                }

                if (! $classified['is_allowed_in_post']) {
                    $violations[] = "Enlace no permitido en contenido público: [{$classified['classification']}] {$classified['redacted_url']} (motivo: {$classified['reason']}).";
                }
            }
        }

        // 3. Detección de embeds o iframes con URLs no autorizadas
        if (preg_match_all('/<iframe\s+[^>]*src=["\']([^"\']+)["\']/i', $content, $iframeMatches)) {
            foreach ($iframeMatches[1] as $src) {
                $classified = $this->linkClassifier->classify($src);
                if (! $classified['is_allowed_in_post']) {
                    $violations[] = "Embed o iframe no autorizado en contenido público: {$classified['redacted_url']}.";
                }
            }
        }

        // 4. Verificación de imagen destacada: normalización de almacenamiento público local y validación de seguridad
        if ($image !== '') {
            $imgValidation = $this->validateFeaturedImage($image);
            if (! $imgValidation['is_safe']) {
                $violations[] = $imgValidation['violation'];
            }
        }

        // 5. Verificación de Estado de Seguridad en Metadata
        $evalDate = $targetPublishDate ?? now();
        $meta = EmailPostSanitizedMeta::where('post_id', $post->id)->first();
        if ($meta) {
            // Retenciones técnicas críticas no son embargos temporales y no se liberan por fecha
            if (in_array($meta->security_status, ['held_security', 'rejected'], true)) {
                $violations[] = "Material retenido cautelarmente por seguridad editorial (estado: {$meta->security_status}). Requiere resolución expresa del editor.";
            }

            // 6. Verificación de Embargo Activo
            if ($meta->is_embargo_active) {
                // Si está activo pero no hay fecha precisa o la fecha está en el futuro: bloqueo estricto
                if ($meta->embargo_until === null || $evalDate->lessThan($meta->embargo_until)) {
                    $hasEmbargo = true;
                    $embargoLimitStr = $meta->embargo_until ? $meta->embargo_until->toDateTimeString() : 'resolución expresa';
                    $violations[] = "Embargo activo hasta {$embargoLimitStr}. Publicación no permitida.";
                }
            }
        }

        // Chequeo sintáctico de embargo en texto por si no existiera registro en metadata
        if ($meta === null || ($meta->embargo_until && $evalDate->lessThan($meta->embargo_until))) {
            $embargoCheck = \App\Support\EmbargoDetector::detect($title, $content);
            if ($embargoCheck['is_embargoed']) {
                $hasEmbargo = true;
                $violations[] = "Patrón de embargo o confidencialidad detectado en el contenido final: {$embargoCheck['matched_pattern']}.";
            }
        }

        return [
            'is_safe'     => empty($violations),
            'violations'  => $violations,
            'has_embargo' => $hasEmbargo,
        ];
    }

    /**
     * Comprueba si el post cumple todos los criterios de seguridad para ser publicado.
     */
    public function isSafeToPublish(Post $post, ?\Carbon\Carbon $targetPublishDate = null): bool
    {
        $res = $this->validatePost($post, $targetPublishDate);
        return $res['is_safe'];
    }

    /**
     * Compuerta para publicación en scheduler o Bandeja Editorial.
     * Si no es seguro, lanza una excepción de seguridad impidiendo la acción.
     *
     * @throws \RuntimeException
     */
    public function assertSafeForPublishing(Post $post, ?\Carbon\Carbon $targetPublishDate = null): void
    {
        $res = $this->validatePost($post, $targetPublishDate);
        if (! $res['is_safe']) {
            $reason = implode(' | ', $res['violations']);
            Log::error("EmailSafetyValidator: Publicación bloqueada por violación de seguridad en post ID {$post->id}.", [
                'post_id'    => $post->id,
                'title'      => $post->title,
                'violations' => $res['violations'],
            ]);

            throw new \RuntimeException("Publicación bloqueada por seguridad editorial: {$reason}");
        }
    }

    /**
     * Valida la seguridad editorial de los datos de un borrador antes de su persistencia.
     */
    public function validateEditorialSafety(array $draftData): array
    {
        $violations = [];
        $title = (string) ($draftData['title'] ?? '');
        $excerpt = (string) ($draftData['excerpt'] ?? '');
        $content = (string) ($draftData['content'] ?? '');
        $fullText = mb_strtolower("{$title} {$excerpt} {$content}");

        // 1. Detección de contraseñas o tokens en texto
        if (preg_match('/\b(?:password|passcode|contrase[ñn]a|c[oó]digo\s+de\s+descarga|download\s+code)\s*[:=]\s*([^\s<"\'&]+)/iu', $fullText, $m)) {
            $violations[] = "Texto contiene posible credencial o contraseña: '{$m[0]}'";
        }

        // 2. Detección de enlaces privados o restringidos
        if (preg_match_all('#https?://[^\s<>"\'()]+#i', $content, $matches)) {
            foreach ($matches[0] as $url) {
                $class = $this->linkClassifier->classify($url);
                if (in_array($class['classification'], ['restricted_private', 'suspicious'], true)) {
                    $violations[] = "Enlace restringido o sospechoso detectado: '{$class['redacted_url']}' ({$class['classification']})";
                }
            }
        }

        return [
            'is_safe'    => empty($violations),
            'violations' => $violations,
            'warnings'   => $violations,
        ];
    }

    /**
     * Normaliza y valida la imagen destacada:
     * - Admite rutas del almacenamiento público autorizado (ej. storage/posts/covers/..., posts/covers/...).
     * - Protege contra escapes de directorio (path traversal), esquemas peligrosos, SSRF y credenciales en URL.
     *
     * @return array [
     *     'is_safe'         => bool,
     *     'violation'       => ?string,
     *     'normalized_path' => ?string,
     * ]
     */
    public function validateFeaturedImage(mixed $image): array
    {
        if (empty($image)) {
            return ['is_safe' => true, 'violation' => null, 'normalized_path' => null];
        }

        // Extraer string si es array o JSON
        if (is_array($image)) {
            $raw = $image['url'] ?? $image['path'] ?? $image['key'] ?? reset($image);
        } else {
            $raw = (string) $image;
            if (str_starts_with($raw, '{') || str_starts_with($raw, '[')) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $raw = $decoded['url'] ?? $decoded['path'] ?? $decoded['key'] ?? reset($decoded);
                }
            }
        }

        $clean = trim(str_replace('\\', '/', (string) $raw));
        if ($clean === '') {
            return ['is_safe' => true, 'violation' => null, 'normalized_path' => null];
        }

        // 1. Detección estricta de Path Traversal y bytes nulos
        if (str_contains($clean, "\0") || preg_match('#(^|/)\.\.(/|$)#', $clean) || str_contains($clean, '..') || preg_match('#%2e%2e#i', $clean)) {
            return [
                'is_safe'         => false,
                'violation'       => "Imagen destacada sospechosa: contiene secuencias no permitidas de escape de directorio (path traversal).",
                'normalized_path' => null,
            ];
        }

        // 2. Esquemas maliciosos o no permitidos (file://, phar://, data:, gopher://, php://)
        if (preg_match('#^([a-z0-9+.-]+):#i', $clean, $schemeMatch)) {
            $scheme = strtolower($schemeMatch[1]);
            if (! in_array($scheme, ['http', 'https'], true)) {
                return [
                    'is_safe'         => false,
                    'violation'       => "Imagen destacada sospechosa o esquema no permitido: '{$scheme}://'.",
                    'normalized_path' => null,
                ];
            }
        }

        // 3. Credenciales incrustadas en URL (http://user:pass@host)
        if (parse_url($clean, PHP_URL_USER) !== null || parse_url($clean, PHP_URL_PASS) !== null) {
            return [
                'is_safe'         => false,
                'violation'       => "Imagen destacada sospechosa o con credenciales expuestas en la URL.",
                'normalized_path' => null,
            ];
        }

        // 4. Normalización si es URL absoluta local (apuntando al dominio de la app o localhost en dev)
        $hasScheme = (bool) preg_match('#^https?://#i', $clean);
        if ($hasScheme) {
            $host = strtolower(parse_url($clean, PHP_URL_HOST) ?? '');
            $appHost = strtolower(parse_url((string) config('app.url', ''), PHP_URL_HOST) ?? '');
            $isLocalDomain = in_array($host, array_filter(['localhost', '127.0.0.1', '::1', $appHost, 'sevenrockradio.com', 'www.sevenrockradio.com']), true);

            if ($isLocalDomain) {
                $path = parse_url($clean, PHP_URL_PATH) ?? '';
                $clean = ltrim($path, '/');
                $hasScheme = false;
            }
        }

        // 5. Validación de imagen local en almacenamiento público
        if (! $hasScheme) {
            $relPath = ltrim($clean, '/');

            // Prefijos autorizados para almacenamiento público de portadas y medios
            $allowedPrefixes = [
                'storage/posts/',
                'posts/',
                'storage/catalog/',
                'catalog/',
                'storage/covers/',
                'covers/',
                'assets/',
                'images/',
            ];

            $isAllowedPrefix = false;
            foreach ($allowedPrefixes as $prefix) {
                if (str_starts_with($relPath, $prefix)) {
                    $isAllowedPrefix = true;
                    break;
                }
            }

            if (! $isAllowedPrefix) {
                return [
                    'is_safe'         => false,
                    'violation'       => "Imagen destacada fuera de la ruta de almacenamiento público autorizada: '{$relPath}'.",
                    'normalized_path' => null,
                ];
            }

            // Validar extensión de imagen permitida
            $ext = strtolower(pathinfo(parse_url($relPath, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'svg'];
            if (! in_array($ext, $allowedExtensions, true)) {
                return [
                    'is_safe'         => false,
                    'violation'       => "Extensión de archivo de imagen destacada no permitida: '.{$ext}'.",
                    'normalized_path' => null,
                ];
            }

            return [
                'is_safe'         => true,
                'violation'       => null,
                'normalized_path' => $relPath,
            ];
        }

        // 6. Validación de URL externa (SSRF, redes privadas, IP interna)
        $classified = $this->linkClassifier->classify($clean);
        if ($classified['classification'] === 'suspicious') {
            return [
                'is_safe'         => false,
                'violation'       => "Imagen destacada sospechosa o apuntando a dirección interna/SSRF.",
                'normalized_path' => null,
            ];
        }

        if ($classified['classification'] === 'restricted_private') {
            return [
                'is_safe'         => false,
                'violation'       => "Imagen destacada apunta a servicio privado o restringido ({$classified['reason']}).",
                'normalized_path' => null,
            ];
        }

        return [
            'is_safe'         => true,
            'violation'       => null,
            'normalized_path' => $clean,
        ];
    }

    /**
     * Resuelve la lista de URLs concretas aprobadas para un post.
     *
     * @return array<int, string>
     */
    public function resolveApprovedUrls(Post $post, array $context = []): array
    {
        $urls = [];

        // 1. URLs pasadas explícitamente en el contexto
        if (! empty($context['approved_urls']) && is_array($context['approved_urls'])) {
            $urls = array_merge($urls, $context['approved_urls']);
        }

        // 2. URLs almacenadas en los metadatos sanitizados
        $meta = $post->relationLoaded('sanitizedMeta')
            ? $post->sanitizedMeta
            : EmailPostSanitizedMeta::where('post_id', $post->id)->first();

        if ($meta) {
            if (! empty($meta->approved_urls) && is_array($meta->approved_urls)) {
                $urls = array_merge($urls, $meta->approved_urls);
            }
        }

        return array_values(array_unique(array_filter(array_map('trim', $urls))));
    }

    /**
     * Comprueba si una URL concreta coincide de forma exacta con alguna de las URLs aprobadas.
     *
     * @param array<int, string> $approvedUrls
     */
    public function isUrlApproved(string $url, array $approvedUrls): bool
    {
        $normalizedTarget = $this->normalizeUrlForComparison($url);
        if ($normalizedTarget === '') {
            return false;
        }

        foreach ($approvedUrls as $approved) {
            $normalizedApproved = $this->normalizeUrlForComparison((string) $approved);
            if ($normalizedApproved !== '' && $normalizedApproved === $normalizedTarget) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normaliza una URL para comparación determinista exacta sin autorizar dominios completos.
     */
    public function normalizeUrlForComparison(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parsed = parse_url($url);
        if (! $parsed || empty($parsed['host'])) {
            return strtolower(rtrim($url, '/'));
        }

        $scheme = strtolower($parsed['scheme'] ?? 'https');
        $host = strtolower($parsed['host']);
        
        $port = isset($parsed['port']) ? (int) $parsed['port'] : null;
        $portStr = '';
        if ($port && ! ($scheme === 'http' && $port === 80) && ! ($scheme === 'https' && $port === 443)) {
            $portStr = ":{$port}";
        }

        $path = $parsed['path'] ?? '/';
        $path = (string) preg_replace('#/{2,}#', '/', $path);
        if ($path === '') {
            $path = '/';
        }
        if (strlen($path) > 1 && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        $query = isset($parsed['query']) && $parsed['query'] !== '' ? '?' . $parsed['query'] : '';
        $fragment = isset($parsed['fragment']) && $parsed['fragment'] !== '' ? '#' . $parsed['fragment'] : '';

        return "{$scheme}://{$host}{$portStr}{$path}{$query}{$fragment}";
    }
}

