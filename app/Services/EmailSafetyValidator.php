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

    /**
     * Valida de manera exhaustiva el contenido final de un Post listo para ser publicado.
     * Inspecciona: título, excerpt, content HTML, featured_image, enlaces y embeds.
     *
     * @return array [
     *     'is_safe'     => bool,
     *     'violations'  => array<string>,
     *     'has_embargo' => bool,
     * ]
     */
    public function validatePost(Post $post, ?\Carbon\Carbon $targetPublishDate = null): array
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
        if (preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\']/i', $content, $linkMatches)) {
            foreach ($linkMatches[1] as $href) {
                $classified = $this->linkClassifier->classify($href);
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

        // 4. Verificación de imagen destacada (no puede apuntar a IP privada o URL sospechosa)
        if ($image !== '') {
            $classifiedImg = $this->linkClassifier->classify($image);
            if ($classifiedImg['classification'] === 'suspicious') {
                $violations[] = "Imagen destacada sospechosa o apuntando a dirección interna/SSRF.";
            }
        }

        // 5. Verificación de Embargo Activo
        $evalDate = $targetPublishDate ?? now();

        // Chequeo en la metadata sanitizada si existe
        $meta = EmailPostSanitizedMeta::where('post_id', $post->id)->first();
        if ($meta && $meta->is_embargo_active && $meta->embargo_until && $evalDate->lessThan($meta->embargo_until)) {
            $hasEmbargo = true;
            $violations[] = "Embargo activo hasta {$meta->embargo_until->toDateTimeString()}. Publicación no permitida.";
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
    public function isSafeToPublish(Post $post): bool
    {
        $res = $this->validatePost($post);
        return $res['is_safe'];
    }

    /**
     * Compuerta para publicación en scheduler o Bandeja Editorial.
     * Si no es seguro, lanza una excepción de seguridad impidiendo la acción.
     *
     * @throws \RuntimeException
     */
    public function assertSafeForPublishing(Post $post): void
    {
        $res = $this->validatePost($post);
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
                if (in_array($class['category'], ['restricted_private', 'suspicious'])) {
                    $violations[] = "Enlace restringido o sospechoso detectado: '{$url}' ({$class['category']})";
                }
            }
        }

        return [
            'is_safe'    => empty($violations),
            'violations' => $violations,
            'warnings'   => $violations,
        ];
    }
}

