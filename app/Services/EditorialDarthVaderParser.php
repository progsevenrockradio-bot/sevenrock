<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\TextNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Str;

class EditorialDarthVaderParser
{
    public const FORMAT_DV1 = 'DV-1';
    public const FORMAT_LEGACY_EFEMERIDES = 'LEGACY_EFEMERIDES';
    public const FORMAT_LEGACY_NOTICIA = 'LEGACY_NOTICIA';
    public const FORMAT_UNKNOWN = 'UNKNOWN';

    /**
     * Detecta el formato del correo según el asunto y el cuerpo.
     */
    public function detectFormat(string $subject, string $body): string
    {
        if ($this->isDv1Format($subject, $body)) {
            return self::FORMAT_DV1;
        }

        if ($this->isLegacyEfemerides($subject, $body)) {
            return self::FORMAT_LEGACY_EFEMERIDES;
        }

        if ($this->isLegacyNoticia($subject, $body)) {
            return self::FORMAT_LEGACY_NOTICIA;
        }

        return self::FORMAT_UNKNOWN;
    }

    /**
     * Determina si el mensaje cumple con la especificación estructurada DV-1.
     */
    public function isDv1Format(string $subject, string $body): bool
    {
        $hasSubjectTag = (bool) preg_match('/\[DV-1\]/i', $subject);
        $hasHeader = (bool) preg_match('/FORMATO:\s*DV-1/i', $body);
        $hasElements = str_contains($body, '=== INICIO ELEMENTO ===') || str_contains($body, '===INICIO ELEMENTO===');

        return ($hasSubjectTag && $hasElements) || ($hasHeader && $hasElements);
    }

    /**
     * Determina si el mensaje es de efemérides en formato anterior.
     */
    public function isLegacyEfemerides(string $subject, string $body): bool
    {
        $subjectLower = mb_strtolower(trim($subject));

        $matchesSubject = str_starts_with($subjectLower, 'hoy en el rock')
            || str_contains($subjectLower, 'efeméride')
            || str_contains($subjectLower, 'efemerides');

        if (! $matchesSubject) {
            return false;
        }

        // Si además tiene delimitadores DV-1, no es legado
        if ($this->isDv1Format($subject, $body)) {
            return false;
        }

        return true;
    }

    /**
     * Determina si el mensaje es una noticia individual en formato anterior.
     */
    public function isLegacyNoticia(string $subject, string $body): bool
    {
        if ($this->isDv1Format($subject, $body)) {
            return false;
        }

        if ($this->isLegacyEfemerides($subject, $body)) {
            return false;
        }

        $plain = trim(strip_tags($body));

        // Debe tener un título de longitud razonable y contenido
        return mb_strlen(trim($subject)) >= 5 && mb_strlen($plain) >= 30;
    }

    /**
     * Parsea un mensaje estructurado bajo el contrato DV-1.
     *
     * @return array{
     *     valid: bool,
     *     batch_id: string,
     *     editorial_date: string,
     *     timezone: string,
     *     total_declared: int,
     *     total_extracted: int,
     *     has_count_discrepancy: bool,
     *     discrepancy_message: ?string,
     *     mode: string,
     *     elements: array<int, array{
     *         id: string,
     *         tipo: string,
     *         fecha_editorial: string,
     *         fecha_acontecimiento: ?string,
     *         artista_banda: ?string,
     *         titulo: string,
     *         texto: string,
     *         fuentes: array<int, string>,
     *         imagen_url: ?string,
     *         imagen_credito: ?string,
     *         revision_requerida: bool,
     *         motivo_revision: ?string,
     *         corrige_id: ?string,
     *         raw_block: string
     *     }>,
     *     errors: array<int, string>
     * }
     */
    public function parseDv1(string $subject, string $body): array
    {
        $errors = [];

        // Extraer metadatos de cabecera antes del primer elemento
        $firstElemPos = mb_strpos($body, '=== INICIO ELEMENTO');
        if ($firstElemPos === false) {
            $firstElemPos = mb_strpos($body, '===INICIO ELEMENTO');
        }

        $headerText = $firstElemPos !== false ? mb_substr($body, 0, $firstElemPos) : $body;

        // Extraer LOTE_ID
        $batchId = null;
        if (preg_match('/LOTE_ID:\s*([^\r\n]+)/i', $headerText, $m)) {
            $batchId = trim($m[1]);
        } elseif (preg_match('/\[DARTH-VADER\]\[DV-1\]\[[^\]]+\]\[([^\]]+)\]/i', $subject, $m)) {
            $batchId = trim($m[1]);
        }
        $batchId = $batchId ?: 'LOTE-' . date('Ymd-His');

        // Extraer FECHA_EDITORIAL
        $editorialDate = null;
        if (preg_match('/FECHA_EDITORIAL:\s*([^\r\n]+)/i', $headerText, $m)) {
            $editorialDate = trim($m[1]);
        } elseif (preg_match('/\[DARTH-VADER\]\[DV-1\]\[(\d{4}-\d{2}-\d{2})\]/i', $subject, $m)) {
            $editorialDate = trim($m[1]);
        }
        $editorialDate = $editorialDate ?: date('Y-m-d');

        // Extraer ZONA_HORARIA
        $timezone = 'Europe/Madrid';
        if (preg_match('/ZONA_HORARIA:\s*([^\r\n]+)/i', $headerText, $m)) {
            $timezone = trim($m[1]);
        }

        // Extraer TOTAL_ELEMENTOS declarados
        $totalDeclared = 0;
        if (preg_match('/TOTAL_ELEMENTOS:\s*(\d+)/i', $headerText, $m)) {
            $totalDeclared = (int) $m[1];
        }

        // Extraer MODO
        $mode = 'BORRADOR';
        if (preg_match('/MODO:\s*([^\r\n]+)/i', $headerText, $m)) {
            $mode = strtoupper(trim($m[1]));
        }

        // Extraer bloques delimitados por === INICIO ELEMENTO === y === FIN ELEMENTO ===
        $pattern = '/===\s*INICIO\s+ELEMENTO\s*===(.*?)===\s*FIN\s+ELEMENTO\s*===/isu';
        preg_match_all($pattern, $body, $matches);

        $rawBlocks = $matches[1] ?? [];
        $elements = [];
        $index = 1;

        foreach ($rawBlocks as $block) {
            $parsed = $this->parseDv1Element(trim($block), $batchId, $editorialDate, $index);
            if ($parsed !== null) {
                $elements[] = $parsed;
                $index++;
            }
        }

        $totalExtracted = count($elements);
        $hasCountDiscrepancy = ($totalDeclared > 0 && $totalExtracted !== $totalDeclared);
        $discrepancyMessage = null;

        if ($hasCountDiscrepancy) {
            $discrepancyMessage = "Discrepancia en lote {$batchId}: se declararon {$totalDeclared} elementos pero se extrajeron {$totalExtracted}.";
        }

        return [
            'valid'                 => $totalExtracted > 0,
            'batch_id'              => $batchId,
            'editorial_date'        => $editorialDate,
            'timezone'              => $timezone,
            'total_declared'        => $totalDeclared,
            'total_extracted'       => $totalExtracted,
            'has_count_discrepancy' => $hasCountDiscrepancy,
            'discrepancy_message'   => $discrepancyMessage,
            'mode'                  => $mode,
            'elements'              => $elements,
            'errors'                => $errors,
        ];
    }

    /**
     * Parsea un bloque individual de elemento DV-1.
     *
     * @return array{
     *     id: string,
     *     tipo: string,
     *     fecha_editorial: string,
     *     fecha_acontecimiento: ?string,
     *     artista_banda: ?string,
     *     titulo: string,
     *     texto: string,
     *     fuentes: array<int, string>,
     *     imagen_url: ?string,
     *     imagen_credito: ?string,
     *     revision_requerida: bool,
     *     motivo_revision: ?string,
     *     corrige_id: ?string,
     *     raw_block: string
     * }|null
     */
    protected function parseDv1Element(string $block, string $batchId, string $editorialDate, int $fallbackIndex): ?array
    {
        if (trim($block) === '') {
            return null;
        }

        // Extraer campos clave-valor iniciales hasta el campo TEXTO:
        $id = $this->extractField($block, 'ID') ?: "{$batchId}-ITEM-" . str_pad((string) $fallbackIndex, 2, '0', STR_PAD_LEFT);
        $tipoRaw = strtoupper($this->extractField($block, 'TIPO') ?: 'NOTICIA');
        $tipo = str_contains($tipoRaw, 'EFEMERIDE') ? 'EFEMERIDE' : 'NOTICIA';

        $elementEditorialDate = $this->extractField($block, 'FECHA_EDITORIAL') ?: $editorialDate;
        $fechaAcontecimiento = $this->extractField($block, 'FECHA_ACONTECIMIENTO');
        $artistaBanda = $this->extractField($block, 'ARTISTA_BANDA');
        $titulo = $this->extractField($block, 'TITULO');

        $fuentes = $this->extractFuentes($block);
        $fuentePrimaria = $this->extractLongTextField($block, 'FUENTE_PRIMARIA') ?: $this->extractField($block, 'FUENTE_PRIMARIA') ?: $this->extractField($block, 'FUENTE_ORIGINAL');
        $corroboracion = $this->extractLongTextField($block, 'CORROBORACION') ?: $this->extractField($block, 'CORROBORACION') ?: $this->extractField($block, 'FUENTE_SECUNDARIA');
        $afirmacionesVerificadas = $this->extractLongTextField($block, 'AFIRMACIONES_VERIFICADAS') ?: $this->extractField($block, 'AFIRMACIONES_VERIFICADAS');
        $discrepancias = $this->extractLongTextField($block, 'DISCREPANCIAS') ?: $this->extractField($block, 'DISCREPANCIAS') ?: 'NINGUNA';

        $imagenUrl = $this->extractField($block, 'IMAGEN_URL');
        $imagenCredito = $this->extractField($block, 'IMAGEN_CREDITO');

        $revisionRaw = strtolower($this->extractField($block, 'REVISION_REQUERIDA') ?: 'false');
        $revisionRequerida = in_array($revisionRaw, ['true', '1', 'si', 'sí', 'yes'], true);
        $motivoRevision = $this->extractField($block, 'MOTIVO_REVISION');

        $corrigeId = $this->extractField($block, 'CORRIGE_ID');
        if ($corrigeId !== null && (trim($corrigeId) === '' || trim($corrigeId) === 'null' || trim($corrigeId) === 'none')) {
            $corrigeId = null;
        }

        // Extraer el texto completo del elemento (desde TEXTO: hasta el siguiente campo o final)
        $texto = $this->extractLongTextField($block, 'TEXTO');

        if (! empty($titulo)) {
            $titulo = TextNormalizer::normalizeTitle((string) $titulo);
            $titulo = trim(preg_replace('/^\[?(?:EFEMERIDE|NOTICIA)\]?\s*[:-]?\s*/i', '', $titulo));
        } else {
            $titulo = '';
        }

        if ($titulo === '' && trim($texto) === '') {
            return null;
        }

        return [
            'id'                       => $id,
            'tipo'                     => $tipo,
            'fecha_editorial'          => $elementEditorialDate,
            'fecha_acontecimiento'     => $fechaAcontecimiento ?: null,
            'artista_banda'            => $artistaBanda ?: null,
            'titulo'                   => $titulo,
            'texto'                    => $texto,
            'fuentes'                  => $fuentes,
            'fuente_primaria'          => $fuentePrimaria ?: null,
            'corroboracion'            => $corroboracion ?: null,
            'afirmaciones_verificadas' => $afirmacionesVerificadas ?: null,
            'discrepancias'            => $discrepancias,
            'imagen_url'               => $imagenUrl ?: null,
            'imagen_credito'           => $imagenCredito ?: null,
            'revision_requerida'       => $revisionRequerida,
            'motivo_revision'          => $motivoRevision ?: null,
            'corrige_id'               => $corrigeId,
            'raw_block'                => $block,
        ];
    }

    /**
     * Extrae un campo de una sola línea con sintaxis CAMPO: valor.
     */
    protected function extractField(string $block, string $fieldName): ?string
    {
        $escaped = preg_quote($fieldName, '/');
        // El campo comienza al inicio de una línea y termina al fin de esa misma línea
        if (preg_match('/^[ \t]*' . $escaped . '[ \t]*:[ \t]*([^\r\n]*)/imu', $block, $matches)) {
            $val = trim($matches[1]);
            return $val !== '' ? $val : null;
        }

        return null;
    }

    /**
     * Extrae texto potencialmente multilínea preservando saltos de línea hasta encontrar
     * otra cabecera de metadatos o el final del bloque.
     */
    protected function extractLongTextField(string $block, string $fieldName): string
    {
        $escaped = preg_quote($fieldName, '/');
        $allKnownFields = [
            'ID', 'TIPO', 'FECHA_EDITORIAL', 'FECHA_ACONTECIMIENTO', 'ARTISTA_BANDA', 'TITULO',
            'TEXTO', 'FUENTE_PRIMARIA', 'FUENTE_ORIGINAL', 'CORROBORACION', 'FUENTE_SECUNDARIA',
            'AFIRMACIONES_VERIFICADAS', 'DISCREPANCIAS', 'FUENTES', 'IMAGEN_URL', 'IMAGEN_CREDITO',
            'REVISION_REQUERIDA', 'MOTIVO_REVISION', 'CORRIGE_ID',
        ];
        $delimiters = array_filter($allKnownFields, fn ($f) => strcasecmp($f, $fieldName) !== 0);
        $delimitersRegex = implode('|', array_map('preg_quote', $delimiters));
        $pattern = '/^[ \t]*' . $escaped . '[ \t]*:[ \t]*(.*?)(?=(?:^[ \t]*(?:' . $delimitersRegex . ')[ \t]*:)|\z)/imsu';

        if (preg_match($pattern, $block, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    /**
     * Extrae las fuentes del bloque, soportando tanto formato en una sola línea separado por comas
     * como listas multilínea precedidas por viñetas (- o *).
     *
     * @return array<int, string>
     */
    protected function extractFuentes(string $block): array
    {
        $pattern = '/^\s*FUENTES\s*:\s*(.*?)(?=(?:^\s*(?:IMAGEN_URL|IMAGEN_CREDITO|REVISION_REQUERIDA|MOTIVO_REVISION|CORRIGE_ID|FUENTE_PRIMARIA|FUENTE_ORIGINAL|CORROBORACION|FUENTE_SECUNDARIA|AFIRMACIONES_VERIFICADAS|DISCREPANCIAS)\s*:)|\z)/imsu';
        if (preg_match($pattern, $block, $matches)) {
            $raw = trim($matches[1]);
            if (preg_match_all('/https?:\/\/[^\s<"\'\)]+/i', $raw, $urlMatches)) {
                return array_values(array_unique(array_map('trim', $urlMatches[0])));
            }
            $lines = preg_split('/[\r\n,]+/', $raw) ?: [];
            return array_values(array_filter(array_map(function ($l) {
                return trim(ltrim(trim($l), '-* '));
            }, $lines)));
        }

        return [];
    }

    /**
     * Parsea un correo de efemérides en formato legado.
     *
     * @return array<int, array{
     *     titulo: string,
     *     texto: string,
     *     fecha_acontecimiento: ?string
     * }>
     */
    public function parseLegacyEfemerides(string $subject, string $body): array
    {
        $preparedBody = preg_replace('/<(?:br|\/p|\/div|\/li|\/tr|\/h[1-6])[^>]*>/i', "\n", $body) ?? $body;
        $plainBody = strip_tags($preparedBody);
        $plainBody = html_entity_decode($plainBody, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Estrategia 1: items numerados
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\r?\n/', $plainBody) ?: [])
        ));

        $itemPattern = '/^(?:[\x{1F300}-\x{1FFFF}\x{2600}-\x{27BF}]\s*)?(?:\[?\d+[.)\-\]]+|\d+\s*[-–—])\s*/u';

        $items = [];
        $current = null;
        foreach ($lines as $line) {
            if (preg_match($itemPattern, $line)) {
                if ($current !== null) {
                    $items[] = $current;
                }
                $current = $line;
            } elseif ($current !== null && $line !== '') {
                $current .= ' ' . $line;
            }
        }
        if ($current !== null) {
            $items[] = $current;
        }

        // Estrategia 2: fallback por doble salto de línea
        if (count($items) < 2) {
            $chunks = preg_split('/\n{2,}|\r\n{2,}/', $plainBody) ?: [];
            $items = array_values(array_filter(array_map('trim', $chunks)));
        }

        $results = [];
        foreach ($items as $item) {
            $cleanItem = preg_replace($itemPattern, '', $item) ?? $item;
            $cleanItem = preg_replace('/^[\x{1F300}-\x{1FFFF}\x{2600}-\x{27BF}]\s*/u', '', $cleanItem) ?? $cleanItem;
            $cleanItem = trim($cleanItem);

            if ($cleanItem === '' || mb_strlen($cleanItem) < 8) {
                continue;
            }

            // Título = primera frase (máx 120 caracteres)
            $efTitle = $cleanItem;
            if (preg_match('/^(.{10,120}?[.!?])\s/u', $cleanItem, $m)) {
                $efTitle = rtrim($m[1], '.!?');
            } elseif (mb_strlen($cleanItem) > 100) {
                $efTitle = Str::limit($cleanItem, 100, '');
                $efTitle = preg_replace('/\s+\S+$/', '', $efTitle) ?: $efTitle;
            }
            $efTitle = trim($efTitle, " .,;:—-\t\n\r");

            // Intentar detectar año de acontecimiento en el texto (ej: "En 1975,", "En 1980")
            $eventYear = null;
            if (preg_match('/\b(19\d{2}|20\d{2})\b/', $cleanItem, $yearMatch)) {
                $eventYear = $yearMatch[1];
            }

            $results[] = [
                'titulo'               => $efTitle,
                'texto'                => $cleanItem,
                'fecha_acontecimiento' => $eventYear,
            ];
        }

        return $results;
    }

    /**
     * Parsea un correo de noticia en formato legado.
     *
     * @return array{
     *     titulo: string,
     *     texto: string,
     *     fuente_url: ?string
     * }
     */
    public function parseLegacyNoticia(string $subject, string $body): array
    {
        $noticiaTitle = TextNormalizer::normalizeTitle($subject);
        $noticiaTitle = preg_replace('/^noticias?:\s*/iu', '', $noticiaTitle);
        $noticiaTitle = trim($noticiaTitle);

        $sourceUrl = null;
        if (preg_match('/(?:FUENTE|SOURCE):\s*(https?:\/\/[^\s<"\'\)]+)/i', $body, $m)) {
            $sourceUrl = trim($m[1]);
        }

        return [
            'titulo'     => $noticiaTitle,
            'texto'      => $body,
            'fuente_url' => $sourceUrl,
        ];
    }
}