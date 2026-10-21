<?php

declare(strict_types=1);

namespace App\Services;

class FactConsistencyChecker
{
    /**
     * Contrasta los hechos objetivos extraídos en la Etapa 1 contra el borrador redactado en la Etapa 2.
     *
     * @param array $extractedFacts Datos estructurados de la Etapa 1
     * @param array $editorialDraft Borrador redactado en la Etapa 2 (title, excerpt, content, editorial_warning)
     * @return array Resumen de consistencia con 'is_consistent', 'discrepancies' y 'editorial_warning'
     */
    public function check(array $extractedFacts, array $editorialDraft): array
    {
        $discrepancies = [];
        $facts = $extractedFacts['facts'] ?? $extractedFacts;

        $title = (string) ($editorialDraft['title'] ?? '');
        $excerpt = (string) ($editorialDraft['excerpt'] ?? '');
        $content = (string) ($editorialDraft['content'] ?? '');
        $fullText = $title . ' ' . $excerpt . ' ' . $content;
        $fullTextLower = mb_strtolower($fullText);

        // 1. Verificación de Nombre del Artista
        $expectedArtist = trim((string) ($facts['artist_name'] ?? ''));
        if ($expectedArtist !== '' && $expectedArtist !== 'null') {
            $artistLower = mb_strtolower($expectedArtist);
            if (!str_contains($fullTextLower, $artistLower)) {
                $discrepancies[] = "El artista extraído ('{$expectedArtist}') no figura en el texto redactado.";
            }
        }

        // 2. Verificación de Título de Lanzamiento
        $expectedRelease = trim((string) ($facts['release_title'] ?? ''));
        if ($expectedRelease !== '' && $expectedRelease !== 'null') {
            $releaseLower = mb_strtolower($expectedRelease);
            if (!str_contains($fullTextLower, $releaseLower)) {
                $discrepancies[] = "El título del lanzamiento ('{$expectedRelease}') no figura en el texto redactado.";
            }
        }

        // 3. Verificación de Fechas Inventadas
        $declaredDate = trim((string) ($facts['musical_release_date'] ?? ''));
        if (empty($declaredDate) || $declaredDate === 'null') {
            // Si la fecha era desconocida o nula, verificar si el texto inventa una fecha en formato formal
            if (preg_match('/\b(el\s+\d{1,2}\s+de\s+(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre))\b/i', $fullText, $m)) {
                $discrepancies[] = "Posible fecha inventada en la redacción ('{$m[1]}') cuando la fuente no confirmó fecha de publicación.";
            }
        }

        // 4. Verificación de Enlaces en el Texto
        // Si el texto incluye URLs, verificar que pertenezcan a verified_evidence o links permitidos
        if (preg_match_all('#https?://[^\s<>"\'()]+#i', $content, $urlMatches)) {
            $allowedUrls = [];
            if (!empty($facts['verified_evidence'])) {
                foreach ($facts['verified_evidence'] as $val) {
                    if (is_string($val)) $allowedUrls[] = $val;
                    if (is_array($val)) $allowedUrls = array_merge($allowedUrls, $val);
                }
            }

            foreach ($urlMatches[0] as $foundUrl) {
                $matched = false;
                foreach ($allowedUrls as $allowed) {
                    if (str_starts_with($foundUrl, $allowed) || str_starts_with($allowed, $foundUrl)) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    $discrepancies[] = "El texto redactado contiene un enlace ('{$foundUrl}') que no fue verificado en los hechos de la Etapa 1.";
                }
            }
        }

        $isConsistent = empty($discrepancies);
        $finalWarning = $editorialDraft['editorial_warning'] ?? null;

        if (!$isConsistent) {
            $warningPrefix = "[ALERTA EDITORIAL - POSIBLE ALUCINACIÓN O DISCREPANCIA]: " . implode(' | ', $discrepancies);
            $finalWarning = $finalWarning ? ($finalWarning . ' // ' . $warningPrefix) : $warningPrefix;
        }

        return [
            'is_consistent'     => $isConsistent,
            'discrepancies'     => $discrepancies,
            'editorial_warning' => $finalWarning,
        ];
    }
}
