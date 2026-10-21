<?php

declare(strict_types=1);

namespace App\Services;

class EmailContentSanitizer
{
    /**
     * Etiquetas HTML permitidas en el contenido editorial final.
     */
    protected const ALLOWED_TAGS = '<p><br><strong><b><em><i><h1><h2><h3><h4><ul><ol><li><blockquote><a><img>';

    /**
     * Patrones de inicio de bloques de notas internas para periodistas.
     */
    protected const EDITORIAL_NOTES_PATTERNS = [
        '/(?:###|\*\*|---\s*|\b)notes\s+to\s+editors\b/i',
        '/(?:###|\*\*|---\s*|\b)for\s+(?:reviewers|press|journalists|media)\s+(?:only|use)\b/i',
        '/(?:###|\*\*|---\s*|\b)press\s+(?:contact|exclusive|instructions)\b/i',
        '/(?:###|\*\*|---\s*|\b)notas\s+para\s+la\s+prensa\b/i',
        '/(?:###|\*\*|---\s*|\b)contacto\s+de\s+prensa\b/i',
        '/(?:###|\*\*|---\s*|\b)do\s+not\s+(?:publish|share)\s+publicly\b/i',
    ];

    /**
     * Patrones de bloques administrativos y pies de desuscripción.
     */
    protected const FOOTER_JUNK_PATTERNS = [
        '/(?:update\s+your\s+preferences|unsubscribe(?:\s+from\s+this\s+list)?|darse\s+de\s+baja|para\s+desuscribirse)[\s\S]*$/i',
        '/this\s+email\s+was\s+sent\s+to\s+[\s\S]*$/i',
        '/este\s+correo\s+fue\s+enviado\s+a\s+[\s\S]*$/i',
        '/you\s+are\s+receiving\s+this\s+email\s+because\s+[\s\S]*$/i',
        '/(?:our\s+mailing\s+address\s+is|nuestra\s+dirección\s+postal\s+es):?[\s\S]*$/i',
    ];

    /**
     * Patrones de detección de contraseñas y credenciales de descarga.
     */
    protected const CREDENTIAL_PATTERNS = [
        '/\b(?:password|passcode|contrase[ñn]a|c[oó]digo\s+de\s+descarga|download\s+code)\s*[:=]\s*([^\s<"\'&]+)/iu',
    ];

    /**
     * Sanitiza el contenido y extrae notas editoriales y credenciales protegidas.
     *
     * @return array [
     *     'clean_html'        => string,
     *     'clean_text'        => string,
     *     'editorial_notes'   => ?string,
     *     'redacted_count'    => int,
     *     'removed_trackers'  => int,
     * ]
     */
    public function sanitize(string $htmlOrText): array
    {
        $raw = trim($htmlOrText);
        $editorialNotes = null;
        $redactedCount = 0;
        $removedTrackers = 0;

        // 1. Extraer y separar notas para periodistas
        foreach (self::EDITORIAL_NOTES_PATTERNS as $pattern) {
            if (preg_match($pattern, $raw, $matches, PREG_OFFSET_CAPTURE)) {
                $offset = $matches[0][1];
                $editorialNotes = trim(substr($raw, $offset));
                $raw = trim(substr($raw, 0, $offset));
                break;
            }
        }

        // 2. Eliminar pies de correo administrativo (Unsubscribe, direcciones postales)
        foreach (self::FOOTER_JUNK_PATTERNS as $junkPattern) {
            $raw = preg_replace($junkPattern, '', $raw) ?? $raw;
        }

        // 3. Detectar y enmascarar contraseñas/credenciales
        foreach (self::CREDENTIAL_PATTERNS as $credPattern) {
            $raw = preg_replace_callback($credPattern, function ($m) use (&$redactedCount) {
                $redactedCount++;
                return str_replace($m[1], '[PROTECTED_CREDENTIAL]', $m[0]);
            }, $raw) ?? $raw;

            if ($editorialNotes !== null) {
                $editorialNotes = preg_replace_callback($credPattern, function ($m) use (&$redactedCount) {
                    $redactedCount++;
                    return str_replace($m[1], '[PROTECTED_CREDENTIAL]', $m[0]);
                }, $editorialNotes) ?? $editorialNotes;
            }
        }

        // 4. Sanitización estructural con DOMDocument (Eliminar scripts, estilos ocultos, píxeles espía)
        $cleanDomHtml = $this->sanitizeDomStructure($raw, $removedTrackers);

        // 5. Aplicar lista blanca de etiquetas permitidas
        $finalHtml = strip_tags($cleanDomHtml, self::ALLOWED_TAGS);

        // Limpiar texto plano representativo
        $cleanText = trim(strip_tags($finalHtml));

        return [
            'clean_html'       => $finalHtml,
            'clean_text'       => $cleanText,
            'editorial_notes'  => $editorialNotes,
            'redacted_count'   => $redactedCount,
            'removed_trackers' => $removedTrackers,
        ];
    }

    /**
     * Recorre el árbol DOM para purgar elementos peligrosos o de rastreo.
     */
    protected function sanitizeDomStructure(string $html, int &$removedTrackers): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = new \DOMDocument();
        $libxmlState = libxml_use_internal_errors(true);

        // Envolver en contenedor UTF-8 para parsing confiable
        $wrapped = '<?xml encoding="utf-8" ?><div>' . $html . '</div>';
        @$dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($libxmlState);

        $xpath = new \DOMXPath($dom);

        // Eliminar scripts, styles, iframes, objetos
        $removeTags = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'noscript'];
        foreach ($removeTags as $tag) {
            $nodes = $xpath->query('//' . $tag);
            if ($nodes) {
                foreach ($nodes as $node) {
                    $node->parentNode?->removeChild($node);
                }
            }
        }

        // Eliminar píxeles de rastreo: imágenes con 1x1, width=0, height=0 o display:none
        $images = $xpath->query('//img');
        if ($images) {
            foreach ($images as $img) {
                /** @var \DOMElement $img */
                $width = trim($img->getAttribute('width'));
                $height = trim($img->getAttribute('height'));
                $style = strtolower($img->getAttribute('style'));
                $src = strtolower($img->getAttribute('src'));

                $isPixel = ($width === '1' && $height === '1') || ($width === '0' || $height === '0');
                $isHidden = str_contains($style, 'display:none') || str_contains($style, 'display: none') || str_contains($style, 'visibility:hidden');
                $isTrackerUrl = str_contains($src, 'track') || str_contains($src, 'beacon') || str_contains($src, 'pixel') || str_contains($src, 'open.php');

                if ($isPixel || $isHidden || $isTrackerUrl) {
                    $img->parentNode?->removeChild($img);
                    $removedTrackers++;
                }
            }
        }

        // Purgar atributos peligrosos (on*, style peligroso, javascript: en href/src)
        $allElements = $xpath->query('//*');
        if ($allElements) {
            foreach ($allElements as $element) {
                /** @var \DOMElement $element */
                if ($element->hasAttributes()) {
                    $attributesToRemove = [];
                    foreach ($element->attributes as $attr) {
                        $name = strtolower($attr->nodeName);
                        $value = strtolower($attr->nodeValue);

                        // Atributos de evento javascript (onclick, onload, onerror, etc.)
                        if (str_starts_with($name, 'on')) {
                            $attributesToRemove[] = $name;
                        }

                        // URLs javascript: o data:
                        if (($name === 'href' || $name === 'src') && (str_starts_with($value, 'javascript:') || str_starts_with($value, 'data:'))) {
                            $attributesToRemove[] = $name;
                        }

                        // Limpiar estilos ocultos
                        if ($name === 'style' && (str_contains($value, 'display:none') || str_contains($value, 'display: none'))) {
                            $attributesToRemove[] = $name;
                        }
                    }

                    foreach ($attributesToRemove as $attrName) {
                        $element->removeAttribute($attrName);
                    }
                }
            }
        }

        $div = $dom->getElementsByTagName('div')->item(0);
        if (! $div) {
            return '';
        }

        $innerHtml = '';
        foreach ($div->childNodes as $child) {
            $innerHtml .= $dom->saveHTML($child);
        }

        return $innerHtml;
    }
}
