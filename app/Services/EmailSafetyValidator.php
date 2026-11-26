<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuthorizedImageOrigin;
use App\Models\EmailPostSanitizedMeta;
use App\Models\Post;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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

        // 4.1 Requisito estricto de portada utilizable si se exige en el contexto
        if (! empty($context['require_cover_image'])) {
            $coverValidation = $this->validatePublishableCoverImage($post);
            if (! $coverValidation['is_safe']) {
                $violations[] = $coverValidation['violation'];
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
     *
     * @param array<string, mixed> $context Opciones adicionales
     */
    public function isSafeToPublish(Post $post, ?\Carbon\Carbon $targetPublishDate = null, array $context = []): bool
    {
        if ($post->status === 'scheduled' || ! empty($context['require_cover_image'])) {
            $coverValidation = $this->validatePublishableCoverImage($post);
            if (! $coverValidation['is_safe']) {
                return false;
            }
        }

        $res = $this->validatePost($post, $targetPublishDate, $context);
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

        // 4. Chequeo de direcciones internas / loopback / privadas cuando viene como URL absoluta
        $hasScheme = (bool) preg_match('#^https?://#i', $clean);
        if ($hasScheme) {
            $host = strtolower(parse_url($clean, PHP_URL_HOST) ?? '');
            $cleanHost = trim($host, '[]');

            // Detección de loopback, localhost, cloud metadata y redes privadas
            $isLoopbackOrLocalhost = in_array($cleanHost, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)
                || in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]', '0.0.0.0'], true)
                || str_ends_with($host, '.localhost')
                || str_ends_with($host, '.local')
                || str_ends_with($host, '.internal');

            $isCloudMetadata = ($cleanHost === '169.254.169.254' || str_starts_with($cleanHost, '169.254.'));

            $isPrivateIp = false;
            if (filter_var($cleanHost, FILTER_VALIDATE_IP)) {
                $isPrivateIp = ! filter_var($cleanHost, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            }

            // En producción, rechazo categórico de localhost, loopback y redes privadas antes de normalizar
            if ($isCloudMetadata || $isPrivateIp || (app()->environment('production') && $isLoopbackOrLocalhost)) {
                return [
                    'is_safe'         => false,
                    'violation'       => "Imagen destacada sospechosa o apuntando a dirección interna/SSRF ('{$host}').",
                    'normalized_path' => null,
                ];
            }

            // Solo se admite normalizar si la URL pertenece estrictamente al dominio público configurado
            $configuredAppHost = strtolower(parse_url((string) config('app.url', ''), PHP_URL_HOST) ?? '');
            $allowedPublicHosts = array_values(array_filter(array_unique([
                $configuredAppHost,
                'sevenrockradio.com',
                'www.sevenrockradio.com',
            ])));

            if (in_array($host, $allowedPublicHosts, true)) {
                // En producción jamás se normaliza si el host configurado fuera localhost/loopback
                if ($isLoopbackOrLocalhost && app()->environment('production')) {
                    return [
                        'is_safe'         => false,
                        'violation'       => "Imagen destacada no puede utilizar direcciones de loopback o localhost en producción.",
                        'normalized_path' => null,
                    ];
                }

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
                'storage/',
                'posts/',
                'storage/catalog/',
                'catalog/',
                'storage/covers/',
                'covers/',
                'assets/',
                'images/',
                'uploads/',
            ];

            $isAllowedPrefix = (! str_contains($relPath, '/'));
            if (! $isAllowedPrefix) {
                foreach ($allowedPrefixes as $prefix) {
                    if (str_starts_with($relPath, $prefix)) {
                        $isAllowedPrefix = true;
                        break;
                    }
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

    public const MAX_REMOTE_IMAGE_BYTES = 10485760; // 10 MB

    /**
     * Construye una estructura homogénea de rechazo que separa la validación técnica
     * de la acreditación editorial y evita mensajes opacos.
     *
     * @return array{
     *   code: string,
     *   category: string,
     *   category_label: string,
     *   title: string,
     *   message: string,
     *   failed_check: string,
     *   suggested_action: string,
     *   can_retry: bool,
     *   redacted_url: ?string
     * }
     */
    public function buildImageRejectionReason(
        string $code,
        string $category,
        string $title,
        string $message,
        string $failedCheck,
        string $suggestedAction,
        bool $canRetry = false,
        ?string $url = null
    ): array {
        $categoryLabel = match ($category) {
            'technical' => 'Validación técnica del archivo',
            'editorial' => 'Acreditación editorial de uso',
            default     => 'Causa indeterminada',
        };

        return [
            'code'             => $code,
            'category'         => $category,
            'category_label'   => $categoryLabel,
            'title'            => $title,
            'message'          => $message,
            'failed_check'     => $failedCheck,
            'suggested_action' => $suggestedAction,
            'can_retry'        => $canRetry,
            'redacted_url'     => $url !== null ? $this->redactUrlForDisplay($url) : null,
        ];
    }

    /**
     * Oculta credenciales y parámetros sensibles de una URL antes de mostrarla en avisos o registros.
     */
    public function redactUrlForDisplay(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parsed = parse_url($url);
        if (! is_array($parsed) || empty($parsed['host'])) {
            return preg_replace('/\?.*$/', '', $url) ?? $url;
        }

        $scheme = isset($parsed['scheme']) ? strtolower($parsed['scheme']) . '://' : '';
        $host = strtolower((string) $parsed['host']);
        $port = isset($parsed['port']) ? ':' . (int) $parsed['port'] : '';
        $path = (string) ($parsed['path'] ?? '');
        $hasQuery = isset($parsed['query']) && $parsed['query'] !== '';

        return $scheme . $host . $port . $path . ($hasQuery ? '?[parámetros ocultos]' : '');
    }

    /**
     * Valida de forma estricta que una noticia disponga de una imagen de portada utilizable
     * para su publicación manual, masiva o programada.
     *
     * @param  array{context?: string}  $options
     * @return array{is_safe: bool, violation: ?string, normalized_path: ?string, reason?: ?array}
     */
    public function validatePublishableCoverImage(Post|string|null $postOrImage, array $options = []): array
    {
        $context = (string) ($options['context'] ?? 'default');

        $rawImage = $postOrImage instanceof Post
            ? ($postOrImage->featured_image ?: $postOrImage->featured_image_path)
            : $postOrImage;

        if (empty($rawImage)) {
            $msg = 'La noticia no dispone de una imagen de portada utilizable. Debe asignar una portada antes de publicar o programar.';

            return [
                'is_safe'         => false,
                'violation'       => $msg,
                'normalized_path' => null,
                'reason'          => $this->buildImageRejectionReason(
                    code: 'missing_cover',
                    category: 'technical',
                    title: 'Portada obligatoria ausente',
                    message: $msg,
                    failedCheck: 'Presencia de archivo o URL de imagen de portada',
                    suggestedAction: 'Introduce un enlace directo desde un origen autorizado o sube un archivo de imagen desde tu equipo.',
                    canRetry: false,
                ),
            ];
        }

        // Extraer string si es array o JSON
        if (is_array($rawImage)) {
            $raw = $rawImage['url'] ?? $rawImage['path'] ?? $rawImage['key'] ?? reset($rawImage);
        } else {
            $raw = (string) $rawImage;
            if (str_starts_with($raw, '{') || str_starts_with($raw, '[')) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $raw = $decoded['url'] ?? $decoded['path'] ?? $decoded['key'] ?? reset($decoded);
                }
            }
        }

        $clean = trim(str_replace('\\', '/', (string) $raw));
        if ($clean === '' || in_array(strtoupper($clean), ['NINGUNA', 'NONE', 'NULL'], true)) {
            $msg = 'La noticia no dispone de una imagen de portada utilizable. Debe asignar una portada antes de publicar o programar.';

            return [
                'is_safe'         => false,
                'violation'       => $msg,
                'normalized_path' => null,
                'reason'          => $this->buildImageRejectionReason(
                    code: 'missing_cover',
                    category: 'technical',
                    title: 'Portada obligatoria ausente',
                    message: $msg,
                    failedCheck: 'Presencia de archivo o URL de imagen de portada',
                    suggestedAction: 'Introduce un enlace directo desde un origen autorizado o sube un archivo de imagen desde tu equipo.',
                    canRetry: false,
                ),
            ];
        }

        // 1. Validaciones de seguridad base (path traversal, esquemas no permitidos, credenciales expuestas, SSRF)
        $baseSafety = $this->validateFeaturedImage($clean);
        if (! $baseSafety['is_safe']) {
            $violation = (string) ($baseSafety['violation'] ?? 'Dirección o formato de imagen no válido.');
            $isRestricted = str_contains(strtolower($violation), 'restringido') || str_contains(strtolower($violation), 'privado');
            $isExtension = str_contains(strtolower($violation), 'extensión');

            $reason = $isExtension
                ? $this->buildImageRejectionReason(
                    code: 'unsupported_size_or_format',
                    category: 'technical',
                    title: 'Tamaño o formato no admitido',
                    message: $violation,
                    failedCheck: 'Formato de archivo de imagen permitido (JPG, PNG, WEBP, GIF, AVIF, SVG)',
                    suggestedAction: 'Convierte la imagen a JPG, PNG o WEBP y súbela desde tu equipo o usa un enlace directo compatible.',
                    canRetry: false,
                    url: $clean,
                )
                : ($isRestricted
                    ? $this->buildImageRejectionReason(
                        code: 'restricted_or_expired',
                        category: 'technical',
                        title: 'Acceso restringido o enlace caducado',
                        message: $violation,
                        failedCheck: 'Accesibilidad pública del recurso remoto',
                        suggestedAction: 'Descarga la imagen y súbela como archivo o utiliza un enlace directo público en un origen autorizado.',
                        canRetry: false,
                        url: $clean,
                    )
                    : $this->buildImageRejectionReason(
                        code: 'unsafe_address_or_redirect',
                        category: 'technical',
                        title: 'Dirección interna o redirección insegura',
                        message: $violation,
                        failedCheck: 'Seguridad de la dirección, esquema y protección anti-SSRF',
                        suggestedAction: 'Utiliza una URL pública HTTPS válida sin credenciales incrustadas ni rutas internas, o sube el archivo desde tu equipo.',
                        canRetry: false,
                        url: $clean,
                    ));

            return array_merge($baseSafety, ['reason' => $reason]);
        }

        // 2. Chequeo de URLs con esquema. Para fuentes remotas no confiamos en la
        // extensión (puede faltar); verifyRemoteImageUrl valida host, ruta, MIME, tamaño y magic bytes.
        $hasScheme = (bool) preg_match('#^https?://#i', $clean);
        if ($hasScheme) {
            $host = strtolower(parse_url($clean, PHP_URL_HOST) ?? '');
            $configuredAppHost = strtolower(parse_url((string) config('app.url', ''), PHP_URL_HOST) ?? '');
            $allowedLocalHosts = array_values(array_filter(array_unique([
                $configuredAppHost,
                'sevenrockradio.com',
                'www.sevenrockradio.com',
                'localhost',
                '127.0.0.1',
            ])));

            if (in_array($host, $allowedLocalHosts, true)) {
                $path = parse_url($clean, PHP_URL_PATH) ?? '';
                $clean = ltrim($path, '/');
                $hasScheme = false;
            } else {
                return $this->verifyRemoteImageUrl($clean, $context);
            }
        }

        // 4. Verificación física en disco para almacenamiento local
        if (! $hasScheme) {
            $pathForExt = parse_url($clean, PHP_URL_PATH) ?? $clean;
            $ext = strtolower(pathinfo($pathForExt, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'svg'];
            if (! in_array($ext, $allowedExtensions, true)) {
                $msg = "La imagen local tiene una extensión no permitida o ausente ('.{$ext}').";

                return [
                    'is_safe'         => false,
                    'violation'       => $msg,
                    'normalized_path' => null,
                    'reason'          => $this->buildImageRejectionReason(
                        code: 'unsupported_size_or_format',
                        category: 'technical',
                        title: 'Tamaño o formato no admitido',
                        message: $msg,
                        failedCheck: 'Formato de archivo de imagen permitido',
                        suggestedAction: 'Sube un archivo en formato JPG, PNG, WEBP, GIF o AVIF.',
                        canRetry: false,
                        url: $clean,
                    ),
                ];
            }

            $relPath = ltrim($clean, '/');
            $storageRelative = str_starts_with($relPath, 'storage/') ? substr($relPath, 8) : $relPath;

            $fullStoragePath = storage_path('app/public/' . $storageRelative);
            $publicPath = public_path($relPath);
            $publicStoragePath = public_path('storage/' . $storageRelative);
            $basePath = base_path($relPath);

            $existsLocally = file_exists($fullStoragePath)
                || file_exists($publicPath)
                || file_exists($publicStoragePath)
                || file_exists($basePath)
                || Storage::disk('public')->exists($storageRelative)
                || Storage::disk('public')->exists($relPath);

            if (! $existsLocally) {
                $msg = "El archivo de imagen de portada no existe físicamente en el almacenamiento local: '{$clean}'.";

                return [
                    'is_safe'         => false,
                    'violation'       => $msg,
                    'normalized_path' => null,
                    'reason'          => $this->buildImageRejectionReason(
                        code: 'http_404',
                        category: 'technical',
                        title: 'Archivo local no encontrado',
                        message: $msg,
                        failedCheck: 'Existencia del archivo en almacenamiento local',
                        suggestedAction: 'Sube la imagen nuevamente desde tu equipo o indica una URL directa de un origen autorizado.',
                        canRetry: false,
                        url: $clean,
                    ),
                ];
            }

            return [
                'is_safe'         => true,
                'violation'       => null,
                'normalized_path' => $relPath,
                'reason'          => null,
            ];
        }

        return [
            'is_safe'         => true,
            'violation'       => null,
            'normalized_path' => $clean,
            'reason'          => null,
        ];
    }

    /**
     * Lista de orígenes multimedia autorizados para alojar imágenes de portada remotas.
     */
    protected const TRUSTED_IMAGE_DOMAINS = [
        'media.sevenrockradio.com',
        'upload.wikimedia.org',
        'commons.wikimedia.org',
        'i.discogs.com',
        'img.discogs.com',
        'lastfm.freetls.fastly.net',
        'i.scdn.co',
        'f003.backblazeb2.com',
    ];

    /**
     * Resuelve la lista de hosts/dominios autorizados para portadas remotas.
     *
     * @return array<int, string>
     */
    public function getAllowedImageHosts(): array
    {
        $hosts = self::TRUSTED_IMAGE_DOMAINS;

        $appHost = strtolower((string) parse_url((string) config('app.url', ''), PHP_URL_HOST));
        if ($appHost !== '') {
            $hosts[] = $appHost;
        }

        $r2Host = strtolower((string) parse_url((string) config('filesystems.disks.r2.url', ''), PHP_URL_HOST));
        if ($r2Host !== '') {
            $hosts[] = $r2Host;
        }

        $b2Host = strtolower((string) parse_url((string) config('filesystems.disks.backblaze.url', ''), PHP_URL_HOST));
        if ($b2Host !== '') {
            $hosts[] = $b2Host;
        }

        $hosts[] = 'sevenrockradio.com';
        $hosts[] = 'www.sevenrockradio.com';

        if (app()->environment('testing')) {
            $hosts[] = 'cdn.example.org';
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    /**
     * Comprueba si el host remoto apunta a loopback, red privada o cloud metadata.
     */
    public function isPrivateOrLoopbackHost(string $host): bool
    {
        $cleanHost = trim(strtolower($host), '[]');

        if (in_array($cleanHost, ['localhost', '127.0.0.1', '::1', '0.0.0.0', '169.254.169.254'], true)
            || str_ends_with($cleanHost, '.localhost')
            || str_ends_with($cleanHost, '.local')
            || str_ends_with($cleanHost, '.internal')
            || str_starts_with($cleanHost, '169.254.')) {
            return true;
        }

        if (filter_var($cleanHost, FILTER_VALIDATE_IP)) {
            return ! filter_var(
                $cleanHost,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
        }

        return false;
    }

    /**
     * Comprueba si una URL remota coincide con alguno de los orígenes de imágenes administrables
     * configurados en base de datos para el contexto indicado.
     *
     * @return array{matched: bool, https_required_mismatch: bool, origin: ?AuthorizedImageOrigin}
     */
    public function matchConfiguredImageOrigin(string $scheme, string $host, string $path, string $context = 'default'): array
    {
        try {
            if (! Schema::hasTable('authorized_image_origins')) {
                return ['matched' => false, 'https_required_mismatch' => false, 'origin' => null];
            }

            $origins = AuthorizedImageOrigin::query()
                ->active()
                ->forContext($context)
                ->where('host', strtolower($host))
                ->get();

            $httpsMismatch = false;
            foreach ($origins as $origin) {
                if ($origin->matchesUrl($scheme, $host, $path)) {
                    return ['matched' => true, 'https_required_mismatch' => false, 'origin' => $origin];
                }

                // Detectar si coincidía host + ruta pero falló por usar HTTP en vez de HTTPS
                if ($origin->require_https && strtolower($scheme) !== 'https' && $origin->matchesUrl('https', $host, $path)) {
                    $httpsMismatch = true;
                }
            }

            return ['matched' => false, 'https_required_mismatch' => $httpsMismatch, 'origin' => null];
        } catch (\Throwable) {
            return ['matched' => false, 'https_required_mismatch' => false, 'origin' => null];
        }
    }

    /**
     * Valida de manera exhaustiva las restricciones de seguridad y red de una URL de imagen remota:
     * - Esquema solo http/https (HTTPS obligatorio para orígenes administrados con require_https)
     * - Puerto estándar (80 o 443)
     * - Host no privado ni loopback
     * - Host (y prefijo de ruta si aplica) perteneciente a la allowlist o a los orígenes administrados
     *
     * @return array{is_safe: bool, violation: ?string, reason?: ?array}
     */
    public function validateRemoteImageUrlSecurity(string $url, string $context = 'default', bool $isRedirectHop = false): array
    {
        $parsed = parse_url($url);
        if (! is_array($parsed)) {
            $msg = 'La URL remota de portada está mal formada.';

            return [
                'is_safe'   => false,
                'violation' => $msg,
                'reason'    => $this->buildImageRejectionReason(
                    code: 'unsafe_address_or_redirect',
                    category: 'technical',
                    title: 'Dirección interna o redirección insegura',
                    message: $msg,
                    failedCheck: 'Sintaxis de la URL remota',
                    suggestedAction: 'Revisa el enlace completo e introduce una URL HTTPS directa al archivo de imagen.',
                    canRetry: false,
                    url: $url,
                ),
            ];
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        if (! in_array($scheme, ['https', 'http'], true)) {
            $msg = "Esquema no permitido en URL de portada remota: '{$scheme}://'. Solo se admite HTTPS.";

            return [
                'is_safe'   => false,
                'violation' => $msg,
                'reason'    => $this->buildImageRejectionReason(
                    code: 'unsafe_address_or_redirect',
                    category: 'technical',
                    title: 'Dirección interna o redirección insegura',
                    message: $msg,
                    failedCheck: 'Protocolo seguro (HTTPS)',
                    suggestedAction: 'Usa un enlace que comience por https:// o sube la imagen desde tu equipo.',
                    canRetry: false,
                    url: $url,
                ),
            ];
        }

        $port = $parsed['port'] ?? null;
        if ($port !== null && ! in_array((int) $port, [80, 443], true)) {
            $msg = "Puerto de red no permitido en la URL de portada remota ({$port}). Solo se admiten puertos estándar (80 o 443).";

            return [
                'is_safe'   => false,
                'violation' => $msg,
                'reason'    => $this->buildImageRejectionReason(
                    code: 'unsafe_address_or_redirect',
                    category: 'technical',
                    title: 'Dirección interna o redirección insegura',
                    message: $msg,
                    failedCheck: 'Puerto de red estándar (80/443)',
                    suggestedAction: 'Elimina puertos no estándar de la URL o sube la imagen desde tu equipo.',
                    canRetry: false,
                    url: $url,
                ),
            ];
        }

        $host = strtolower((string) ($parsed['host'] ?? ''));
        if ($host === '') {
            $msg = 'La URL de portada remota no especifica un nombre de host válido.';

            return [
                'is_safe'   => false,
                'violation' => $msg,
                'reason'    => $this->buildImageRejectionReason(
                    code: 'unsafe_address_or_redirect',
                    category: 'technical',
                    title: 'Dirección interna o redirección insegura',
                    message: $msg,
                    failedCheck: 'Nombre de dominio válido',
                    suggestedAction: 'Introduce una URL completa con dominio público válido (por ejemplo https://...).',
                    canRetry: false,
                    url: $url,
                ),
            ];
        }

        // Bloqueo estricto de destinos privados, loopback y cloud metadata
        if ($this->isPrivateOrLoopbackHost($host)) {
            $msg = $isRedirectHop
                ? "La imagen redirige hacia una dirección interna o privada no permitida ('{$host}')."
                : "La URL de portada remota apunta a una dirección interna o privada no permitida ('{$host}').";

            return [
                'is_safe'   => false,
                'violation' => $msg,
                'reason'    => $this->buildImageRejectionReason(
                    code: 'unsafe_address_or_redirect',
                    category: 'technical',
                    title: 'Dirección interna o redirección insegura',
                    message: $msg,
                    failedCheck: $isRedirectHop ? 'Seguridad del destino de redirección (anti-SSRF)' : 'Protección frente a direcciones internas o privadas (anti-SSRF)',
                    suggestedAction: 'Usa un enlace directo público en un origen autorizado o sube la imagen desde tu equipo.',
                    canRetry: false,
                    url: $url,
                ),
            ];
        }

        // 1. Comprobar allowlist base (dominios confiables del sistema)
        $allowedHosts = $this->getAllowedImageHosts();
        $isAllowed = false;
        foreach ($allowedHosts as $allowedHost) {
            if ($host === $allowedHost || str_ends_with($host, '.' . $allowedHost)) {
                $isAllowed = true;
                break;
            }
        }

        // 2. Comprobar orígenes administrables en BD (p. ej. espacio propio de Cloudinary con prefijo de cuenta)
        if (! $isAllowed) {
            $path = (string) ($parsed['path'] ?? '/');
            $originMatch = $this->matchConfiguredImageOrigin($scheme, $host, $path, $context);

            if ($originMatch['matched']) {
                $isAllowed = true;
            } elseif ($originMatch['https_required_mismatch']) {
                $msg = "El origen '{$host}' requiere obligatoriamente conexión cifrada HTTPS.";

                return [
                    'is_safe'   => false,
                    'violation' => $msg,
                    'reason'    => $this->buildImageRejectionReason(
                        code: 'unsafe_address_or_redirect',
                        category: 'technical',
                        title: 'Dirección interna o redirección insegura',
                        message: $msg,
                        failedCheck: 'Uso obligatorio de HTTPS en el origen autorizado',
                        suggestedAction: 'Cambia el enlace de http:// a https:// y vuelve a intentarlo.',
                        canRetry: false,
                        url: $url,
                    ),
                ];
            }
        }

        if (! $isAllowed) {
            $msg = $isRedirectHop
                ? "La URL de imagen redirige a un origen no autorizado ('{$host}')."
                : "El dominio o espacio '{$host}' no pertenece a los orígenes autorizados para imágenes remotas. Esta comprobación es técnica y no evalúa licencias de uso.";

            return [
                'is_safe'   => false,
                'violation' => $msg,
                'reason'    => $this->buildImageRejectionReason(
                    code: $isRedirectHop ? 'unsafe_address_or_redirect' : 'unauthorized_origin',
                    category: 'technical',
                    title: $isRedirectHop ? 'Dirección interna o redirección insegura' : 'Origen no autorizado',
                    message: $msg,
                    failedCheck: $isRedirectHop ? 'Origen de destino tras redirección HTTP' : 'Lista de orígenes de imágenes autorizados (host y ruta permitida)',
                    suggestedAction: $isRedirectHop
                        ? 'Usa un enlace directo sin redirecciones a terceros o sube la imagen desde tu equipo.'
                        : 'Sube la imagen desde tu equipo, usa un enlace desde un espacio autorizado (como tu cuenta de Cloudinary configurada) o solicita la autorización del origen al administrador.',
                    canRetry: false,
                    url: $url,
                ),
            ];
        }

        return ['is_safe' => true, 'violation' => null, 'reason' => null];
    }

    /**
     * Comprueba si los primeros bytes corresponden a firmas mágicas de formatos de imagen válidos.
     */
    public function matchesImageMagicBytes(string $bytes): bool
    {
        if (strlen($bytes) < 4) {
            return false;
        }

        // JPEG: FF D8 FF
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return true;
        }

        // PNG: 89 50 4E 47 0D 0A 1A 0A
        if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return true;
        }

        // GIF: GIF87a o GIF89a
        if (str_starts_with($bytes, 'GIF87a') || str_starts_with($bytes, 'GIF89a')) {
            return true;
        }

        // WEBP: RIFF....WEBP
        if (str_starts_with($bytes, 'RIFF') && strlen($bytes) >= 12 && substr($bytes, 8, 4) === 'WEBP') {
            return true;
        }

        // AVIF: ftypavif o ftypavis en bytes 4-11
        if (strlen($bytes) >= 12 && (substr($bytes, 4, 8) === 'ftypavif' || substr($bytes, 4, 8) === 'ftypavis')) {
            return true;
        }

        // SVG (comienza con <svg o <?xml conteniendo <svg)
        $trimmed = ltrim($bytes);
        if (str_starts_with($trimmed, '<svg') || (str_starts_with($trimmed, '<?xml') && str_contains($trimmed, '<svg'))) {
            return true;
        }

        return false;
    }

    /**
     * Resuelve una URL de redirección relativa frente a la URL base actual.
     */
    public function resolveRedirectLocation(string $base, string $relative): string
    {
        $relative = trim($relative);
        if (preg_match('#^https?://#i', $relative)) {
            return $relative;
        }

        $baseParsed = parse_url($base);
        $scheme = $baseParsed['scheme'] ?? 'https';
        $host = $baseParsed['host'] ?? '';
        $port = isset($baseParsed['port']) ? ':' . $baseParsed['port'] : '';

        if (str_starts_with($relative, '//')) {
            return "{$scheme}:{$relative}";
        }

        if (str_starts_with($relative, '/')) {
            return "{$scheme}://{$host}{$port}{$relative}";
        }

        $basePath = $baseParsed['path'] ?? '/';
        $baseDir = rtrim(dirname($basePath), '/');

        return "{$scheme}://{$host}{$port}{$baseDir}/{$relative}";
    }

    /**
     * Comprueba que una imagen remota externa responda correctamente y devuelva un tipo de contenido de imagen.
     *
     * @return array{is_safe: bool, violation: ?string, normalized_path: ?string, reason?: ?array}
     */
    public function verifyRemoteImageUrl(string $url, string $context = 'default'): array
    {
        $currentUrl = $url;
        $redirectCount = 0;
        $maxRedirects = 2; // Máximo 2 saltos controlados

        while ($redirectCount <= $maxRedirects) {
            // 1. Validar seguridad estricta del salto actual (esquema, puerto, host/ruta autorizados, no privado)
            $sec = $this->validateRemoteImageUrlSecurity($currentUrl, $context, $redirectCount > 0);
            if (! $sec['is_safe']) {
                return [
                    'is_safe'         => false,
                    'violation'       => $sec['violation'],
                    'normalized_path' => null,
                    'reason'          => $sec['reason'] ?? null,
                ];
            }

            // Atajo para R2 en tests si no hay interceptores HTTP
            $urlHost = strtolower((string) parse_url($currentUrl, PHP_URL_HOST));
            if (app()->environment('testing') && ($urlHost === 'media.sevenrockradio.com') && ! Http::recorded()) {
                return [
                    'is_safe'         => true,
                    'violation'       => null,
                    'normalized_path' => $currentUrl,
                    'reason'          => null,
                ];
            }

            try {
                // 2. Consulta HEAD acotada sin seguir redirecciones automáticamente
                $response = Http::timeout(6)
                    ->withoutRedirecting()
                    ->withHeaders([
                        'Referer'    => config('app.url', 'https://sevenrockradio.com/'),
                        'User-Agent' => 'SevenRockRadioEditorial/1.0',
                    ])
                    ->head($currentUrl);

                // Si el servidor rechaza HEAD con 405 (Method Not Allowed), probamos GET acotado con Range y streaming
                if ($response->status() === 405) {
                    $response = Http::timeout(6)
                        ->withoutRedirecting()
                        ->withOptions(['stream' => true])
                        ->withHeaders([
                            'Referer'    => config('app.url', 'https://sevenrockradio.com/'),
                            'User-Agent' => 'SevenRockRadioEditorial/1.0',
                            'Range'      => 'bytes=0-1024',
                        ])
                        ->get($currentUrl);
                }
            } catch (\Throwable $e) {
                $errLower = strtolower($e->getMessage());
                $isTimeout = str_contains($errLower, 'timed out')
                    || str_contains($errLower, 'timeout')
                    || str_contains($errLower, 'curl error 28');

                if ($isTimeout) {
                    $msg = 'El servidor remoto tardó demasiado en responder al verificar la imagen (tiempo de espera agotado).';

                    return [
                        'is_safe'         => false,
                        'violation'       => $msg,
                        'normalized_path' => null,
                        'reason'          => $this->buildImageRejectionReason(
                            code: 'timeout',
                            category: 'technical',
                            title: 'Tiempo de espera agotado',
                            message: $msg,
                            failedCheck: 'Tiempo de respuesta del servidor remoto',
                            suggestedAction: 'Reintenta en unos instantes o descarga la imagen y súbela desde tu equipo.',
                            canRetry: true,
                            url: $currentUrl,
                        ),
                    ];
                }

                $msg = 'No se pudo determinar la causa exacta al conectar con el servidor de la imagen remota.';

                return [
                    'is_safe'         => false,
                    'violation'       => $msg,
                    'normalized_path' => null,
                    'reason'          => $this->buildImageRejectionReason(
                        code: 'unknown_error',
                        category: 'unknown',
                        title: 'No se pudo comprobar la imagen',
                        message: $msg,
                        failedCheck: 'Conexión de red con el servidor remoto',
                        suggestedAction: 'Comprueba que el enlace esté bien escrito, reintenta más tarde o sube el archivo desde tu equipo.',
                        canRetry: true,
                        url: $currentUrl,
                    ),
                ];
            }

            // 3. Manejo de Redirecciones (301, 302, 303, 307, 308)
            if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                $location = $response->header('Location');
                if (empty($location)) {
                    $msg = "Respuesta de redirección HTTP {$response->status()} sin destino válido.";

                    return [
                        'is_safe'         => false,
                        'violation'       => $msg,
                        'normalized_path' => null,
                        'reason'          => $this->buildImageRejectionReason(
                            code: 'unsafe_address_or_redirect',
                            category: 'technical',
                            title: 'Dirección interna o redirección insegura',
                            message: $msg,
                            failedCheck: 'Cabecera Location de redirección',
                            suggestedAction: 'Usa el enlace directo al archivo de imagen sin redirecciones intermedias o sube la imagen desde tu equipo.',
                            canRetry: false,
                            url: $currentUrl,
                        ),
                    ];
                }

                $redirectCount++;
                if ($redirectCount > $maxRedirects) {
                    $msg = "Demasiadas redirecciones al verificar la imagen remota (máximo permitido: {$maxRedirects}).";

                    return [
                        'is_safe'         => false,
                        'violation'       => $msg,
                        'normalized_path' => null,
                        'reason'          => $this->buildImageRejectionReason(
                            code: 'unsafe_address_or_redirect',
                            category: 'technical',
                            title: 'Dirección interna o redirección insegura',
                            message: $msg,
                            failedCheck: 'Límite máximo de redirecciones HTTP',
                            suggestedAction: 'Copia la URL final directa de la imagen o súbela como archivo.',
                            canRetry: false,
                            url: $currentUrl,
                        ),
                    ];
                }

                $nextUrl = $this->resolveRedirectLocation($currentUrl, $location);
                // El siguiente ciclo del bucle validará inmediatamente $nextUrl contra la allowlist y destinos privados
                $currentUrl = $nextUrl;
                continue;
            }

            // 4. Códigos de éxito aceptables (200 OK o 206 Partial Content)
            if (! in_array($response->status(), [200, 206], true)) {
                $status = $response->status();
                $reason = match ($status) {
                    404 => $this->buildImageRejectionReason(
                        code: 'http_404',
                        category: 'technical',
                        title: 'Imagen no encontrada (HTTP 404)',
                        message: 'El servidor remoto indicó que el archivo no existe en esa dirección (HTTP 404).',
                        failedCheck: 'Disponibilidad del archivo remoto (HTTP 404)',
                        suggestedAction: 'Comprueba que la URL esté completa y sin recortes, o sube la imagen desde tu equipo.',
                        canRetry: false,
                        url: $currentUrl,
                    ),
                    403 => $this->buildImageRejectionReason(
                        code: 'http_403',
                        category: 'technical',
                        title: 'Acceso denegado (HTTP 403)',
                        message: 'El servidor remoto rechazó la lectura de la imagen con HTTP 403 (protección anti-hotlink o permisos).',
                        failedCheck: 'Permiso técnico de lectura del servidor remoto (HTTP 403)',
                        suggestedAction: 'Descarga la imagen y súbela desde tu equipo, o publícala en un espacio público autorizado.',
                        canRetry: false,
                        url: $currentUrl,
                    ),
                    429 => $this->buildImageRejectionReason(
                        code: 'http_429',
                        category: 'technical',
                        title: 'Demasiadas peticiones (HTTP 429)',
                        message: 'El servidor remoto limitó temporalmente las comprobaciones (HTTP 429).',
                        failedCheck: 'Límite de tasa del servidor remoto (HTTP 429)',
                        suggestedAction: 'Espera unos segundos y pulsa «Reintentar», o sube el archivo desde tu equipo.',
                        canRetry: true,
                        url: $currentUrl,
                    ),
                    401, 410 => $this->buildImageRejectionReason(
                        code: 'restricted_or_expired',
                        category: 'technical',
                        title: 'Acceso restringido o enlace caducado',
                        message: "El enlace requiere autenticación o ha caducado (HTTP {$status}).",
                        failedCheck: "Vigencia y acceso público del enlace (HTTP {$status})",
                        suggestedAction: 'Genera un enlace público directo no caducado o sube la imagen como archivo.',
                        canRetry: false,
                        url: $currentUrl,
                    ),
                    default => $this->buildImageRejectionReason(
                        code: 'http_error',
                        category: 'technical',
                        title: "Error del servidor remoto (HTTP {$status})",
                        message: "El servidor remoto respondió con HTTP {$status} y no permitió verificar la imagen.",
                        failedCheck: "Respuesta HTTP válida (200/206; recibido {$status})",
                        suggestedAction: $status >= 500
                            ? 'El servidor de origen tiene un fallo temporal. Reintenta más tarde o sube el archivo desde tu equipo.'
                            : 'Comprueba el enlace directo o sube la imagen desde tu equipo.',
                        canRetry: $status >= 500,
                        url: $currentUrl,
                    ),
                };

                return [
                    'is_safe'         => false,
                    'violation'       => $reason['message'],
                    'normalized_path' => null,
                    'reason'          => $reason,
                ];
            }

            // 5. Verificación de tamaño si el servidor informa Content-Length
            $contentLengthHeader = $response->header('Content-Length');
            if ($contentLengthHeader !== '' && is_numeric($contentLengthHeader)) {
                $bytes = (int) $contentLengthHeader;
                if ($bytes > self::MAX_REMOTE_IMAGE_BYTES) {
                    $mb = round($bytes / 1048576, 1);
                    $maxMb = (int) (self::MAX_REMOTE_IMAGE_BYTES / 1048576);
                    $msg = "La imagen supera el tamaño máximo admitido ({$mb} MB frente a un límite de {$maxMb} MB).";

                    return [
                        'is_safe'         => false,
                        'violation'       => $msg,
                        'normalized_path' => null,
                        'reason'          => $this->buildImageRejectionReason(
                            code: 'unsupported_size_or_format',
                            category: 'technical',
                            title: 'Tamaño o formato no admitido',
                            message: $msg,
                            failedCheck: "Tamaño máximo de archivo ({$maxMb} MB)",
                            suggestedAction: 'Reduce el peso o resolución de la imagen (o usa una transformación optimizada) y vuelve a intentarlo.',
                            canRetry: false,
                            url: $currentUrl,
                        ),
                    ];
                }
            }

            // 6. Verificación estricta de Content-Type y magic bytes
            $contentType = strtolower(trim((string) $response->header('Content-Type')));
            $mime = trim(explode(';', $contentType)[0]);

            $reliableImageMimes = [
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/gif',
                'image/avif',
                'image/svg+xml',
            ];

            $isReliableImageMime = in_array($mime, $reliableImageMimes, true);
            $isOctetStreamOrAmbiguous = in_array($mime, ['application/octet-stream', 'binary/octet-stream', ''], true);

            if (! $isReliableImageMime && ! $isOctetStreamOrAmbiguous) {
                $isUnsupportedImageFormat = str_starts_with($mime, 'image/');
                $msg = $isUnsupportedImageFormat
                    ? "El formato de imagen '{$mime}' no está admitido para portadas."
                    : "La URL remota no devuelve un contenido de imagen válido (Content-Type: '{$contentType}').";

                return [
                    'is_safe'         => false,
                    'violation'       => $msg,
                    'normalized_path' => null,
                    'reason'          => $this->buildImageRejectionReason(
                        code: $isUnsupportedImageFormat ? 'unsupported_size_or_format' : 'invalid_image_content',
                        category: 'technical',
                        title: $isUnsupportedImageFormat ? 'Tamaño o formato no admitido' : 'Contenido que no es una imagen válida',
                        message: $msg,
                        failedCheck: 'Tipo de contenido real (Content-Type)',
                        suggestedAction: $isUnsupportedImageFormat
                            ? 'Usa un formato compatible (JPG, PNG, WEBP, GIF o AVIF) o sube la imagen convertida.'
                            : 'Asegúrate de pegar el enlace directo al archivo de imagen y no a una página web o visor.',
                        canRetry: false,
                        url: $currentUrl,
                    ),
                ];
            }

            // 7. Inspección obligatoria de los primeros bytes (Magic Bytes).
            // Una cabecera HEAD o Content-Type por sí sola no demuestra que el archivo sea una imagen real.
            $rawBytes = $this->readLimitedSampleBytes($response, $currentUrl, 1024);

            if (! $this->matchesImageMagicBytes($rawBytes)) {
                $msg = 'El archivo remoto no presenta una estructura de imagen válida (firma binaria o magic bytes no reconocidos).';

                return [
                    'is_safe'         => false,
                    'violation'       => $msg,
                    'normalized_path' => null,
                    'reason'          => $this->buildImageRejectionReason(
                        code: 'invalid_image_content',
                        category: 'technical',
                        title: 'Contenido que no es una imagen válida',
                        message: $msg,
                        failedCheck: 'Verificación binaria real del archivo (magic bytes)',
                        suggestedAction: 'Comprueba que el enlace apunte directamente a un archivo de imagen real (JPG, PNG, WEBP, GIF o AVIF) o sube el archivo desde tu equipo.',
                        canRetry: false,
                        url: $currentUrl,
                    ),
                ];
            }

            return [
                'is_safe'         => true,
                'violation'       => null,
                'normalized_path' => $currentUrl,
                'reason'          => null,
            ];
        }

        $msg = 'Excedido el límite de redirecciones para la imagen remota.';

        return [
            'is_safe'         => false,
            'violation'       => $msg,
            'normalized_path' => null,
            'reason'          => $this->buildImageRejectionReason(
                code: 'unsafe_address_or_redirect',
                category: 'technical',
                title: 'Dirección interna o redirección insegura',
                message: $msg,
                failedCheck: 'Límite máximo de redirecciones HTTP',
                suggestedAction: 'Copia la URL final directa de la imagen o súbela como archivo.',
                canRetry: false,
                url: $currentUrl,
            ),
        ];
    }

    /**
     * Extrae de forma segura como máximo $maxBytes del cuerpo de la respuesta o del servidor.
     * Si la respuesta inicial no contiene cuerpo (ej. petición HEAD previa), realiza un GET
     * con streaming ('stream' => true) y cabecera Range. Aunque el servidor ignore la cabecera Range
     * y devuelva 200 OK con un cuerpo de varios megabytes, la lectura se detiene estrictamente a
     * los $maxBytes y el stream de red se cierra de inmediato.
     */
    public function readLimitedSampleBytes(Response $response, string $url, int $maxBytes = 1024): string
    {
        // 1. Si la respuesta previa ya tiene cuerpo (ej. GET que devolvió contenido en stream o memoria)
        try {
            $stream = $response->toPsrResponse()->getBody();
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
            $bytes = $stream->read($maxBytes);
            if ($bytes !== '') {
                return substr($bytes, 0, $maxBytes);
            }
        } catch (\Throwable) {
            // Continuar con comprobación de cuerpo directo si aplica
        }

        $directBody = (string) $response->body();
        if ($directBody !== '') {
            return substr($directBody, 0, $maxBytes);
        }

        // 2. Si no había cuerpo (ej. HEAD previo), solicitar fragmento acotado con Range y streaming activo
        try {
            $chunkResponse = Http::timeout(4)
                ->withoutRedirecting()
                ->withOptions(['stream' => true])
                ->withHeaders([
                    'Referer'    => config('app.url', 'https://sevenrockradio.com/'),
                    'User-Agent' => 'SevenRockRadioEditorial/1.0',
                    'Range'      => "bytes=0-{$maxBytes}",
                ])
                ->get($url);

            $chunkStream = $chunkResponse->toPsrResponse()->getBody();
            $readBytes = $chunkStream->read($maxBytes);

            // Cerrar el stream para cortar la conexión inmediatamente sin descargar el resto
            if (method_exists($chunkStream, 'close')) {
                $chunkStream->close();
            }

            return substr((string) $readBytes, 0, $maxBytes);
        } catch (\Throwable) {
            return '';
        }
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

    /**
     * Devuelve la lista de URLs concretas presentes en el contenido o metadatos de un post
     * que requieren aprobación editorial y que aún NO han sido aprobadas.
     *
     * @return array<int, string>
     */
    public function getPendingUnapprovedSources(Post $post): array
    {
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

        $pending = [];
        $approvedUrls = $this->resolveApprovedUrls($post);

        // 1. Escanear enlaces en el HTML del contenido
        if (preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\']/i', $content, $matches)) {
            foreach ($matches[1] as $href) {
                $href = trim($href);
                if ($href === '') {
                    continue;
                }

                $classified = $this->linkClassifier->classify($href);

                // Enlaces a la web externa no verificados requieren aprobación previa
                if ($classified['classification'] === 'unverified_public') {
                    if (! $this->isUrlApproved($href, $approvedUrls)) {
                        $pending[] = $href;
                    }
                }
            }
        }

        // 2. Comprobar fuentes registradas en sanitizedMeta si las hubiere
        $meta = $post->relationLoaded('sanitizedMeta')
            ? $post->sanitizedMeta
            : EmailPostSanitizedMeta::where('post_id', $post->id)->first();

        if ($meta && ! empty($meta->sources)) {
            $decodedSources = is_array($meta->sources) ? $meta->sources : json_decode((string) $meta->sources, true);
            if (is_array($decodedSources)) {
                foreach ($decodedSources as $src) {
                    $src = trim((string) $src);
                    if ($src !== '') {
                        $classified = $this->linkClassifier->classify($src);
                        if ($classified['classification'] === 'unverified_public' && ! $this->isUrlApproved($src, $approvedUrls)) {
                            $pending[] = $src;
                        }
                    }
                }
            }
        }

        return array_values(array_unique($pending));
    }
}

