<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Política estricta de evaluación para publicación automática DV-1.
 *
 * PRINCIPIO Y RESPONSABILIDAD EDITORIAL:
 * El software valida la estructura, completitud, conformidad técnica y coherencia
 * de seguridad de la evidencia declarada por el asistente editorial (Darth Vader).
 * Dicha validación automatizada NO constituye ni debe presentarse ante el público
 * como una comprobación periodística independiente externa a la redacción.
 */
class DvAutoPublishPolicy
{
    public const PUBLICATION_MODE_AUTO = 'auto_policy';

    public function __construct(
        protected EmailSafetyValidator $safetyValidator
    ) {}

    /**
     * Valida estrictamente que una cadena cumpla con el formato YYYY-MM-DD
     * y corresponda a una fecha real del calendario gregoriano (sin normalización automática).
     *
     * @return array{valid: bool, year: int, month: int, day: int, error: ?string}
     */
    public static function validateStrictCalendarDate(string $dateStr): array
    {
        $trimmed = trim($dateStr);
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $trimmed, $matches)) {
            return [
                'valid' => false,
                'year'  => 0,
                'month' => 0,
                'day'   => 0,
                'error' => "Formato de fecha inválido ('{$dateStr}'). Debe ser YYYY-MM-DD estricto.",
            ];
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];

        if ($month < 1 || $month > 12) {
            return [
                'valid' => false,
                'year'  => $year,
                'month' => $month,
                'day'   => $day,
                'error' => "Mes fuera de rango ('{$month}') en fecha '{$dateStr}'.",
            ];
        }

        if (! checkdate($month, $day, $year)) {
            return [
                'valid' => false,
                'year'  => $year,
                'month' => $month,
                'day'   => $day,
                'error' => "Fecha inexistente en el calendario gregoriano ('{$dateStr}').",
            ];
        }

        return [
            'valid' => true,
            'year'  => $year,
            'month' => $month,
            'day'   => $day,
            'error' => null,
        ];
    }

    /**
     * Evalúa si un elemento estructurado DV-1 cumple con todos los criterios
     * estrictos para ser publicado automáticamente, o si debe quedar como borrador.
     *
     * @param array<string, mixed> $elem Datos extraídos del elemento DV-1
     * @param Post $post Modelo de Post persistido o a persistir
     * @param string|null $imagePath Ruta de la imagen en disco
     * @param string|null $imageCredit Crédito de la imagen
     * @param string|null $imageAlt Texto alternativo de la imagen
     * @param array<string, mixed> $context Metadatos adicionales (remitente, autenticación, colisión)
     * @return array{
     *     can_auto_publish: bool,
     *     reason: ?string,
     *     approved_urls: array<int, string>,
     *     publication_mode: string
     * }
     */
    public function evaluate(
        array $elem,
        Post $post,
        ?string $imagePath,
        ?string $imageCredit,
        ?string $imageAlt,
        array $context = []
    ): array {
        $elemId = $elem['id'] ?? 'DESCONOCIDO';
        $tipo = strtoupper(trim((string) ($elem['tipo'] ?? '')));
        $title = trim((string) ($elem['titulo'] ?? ''));

        // ── CRITERIO 1: Identidad y autenticación del remitente ─────────────
        $senderEmail = strtolower(trim((string) ($context['sender_email'] ?? '')));
        $isSenderAuthenticated = (bool) ($context['is_sender_authenticated'] ?? false);
        $isLocalImport = (bool) ($context['is_local_import'] ?? false);
        $allowLocalAutoPublish = (bool) ($context['allow_local_auto_publish'] ?? false);

        if ($senderEmail !== 'dark.vader.agent@gmail.com' || ! $isSenderAuthenticated) {
            return $this->rejection(
                "Remitente no autenticado o falta evidencia técnica de autenticación criptográfica ({$senderEmail})."
            );
        }

        if ($isLocalImport && ! $allowLocalAutoPublish) {
            return $this->rejection(
                "Importación local no autorizada para publicación automática (debe crearse como borrador salvo autorización explícita y auditada)."
            );
        }

        // ── CRITERIO 2: Lote válido, sin duplicados ni conflictos ────────────
        if (! empty($context['is_conflict'])) {
            return $this->rejection(
                "Conflicto o colisión detectada con publicación previa en elemento {$elemId}."
            );
        }

        // ── CRITERIO 3: Sin revisión editorial pendiente ni observaciones ───
        $revisionRequerida = (bool) ($elem['revision_requerida'] ?? false);
        $motivoRevision = trim((string) ($elem['motivo_revision'] ?? ''));

        if ($revisionRequerida) {
            $motivo = ($motivoRevision !== '' && $motivoRevision !== 'NINGUNO')
                ? $motivoRevision
                : 'Marcado con revisión requerida por el emisor';
            return $this->rejection("Revisión editorial expresamente requerida: {$motivo}");
        }

        if ($motivoRevision !== '' && $motivoRevision !== 'NINGUNO') {
            return $this->rejection("Observación editorial pendiente de resolución: {$motivoRevision}");
        }

        // ── CRITERIO 4: Detección inequívoca de contenido incompleto ────────
        // (Sin falsos positivos por palabras corrientes como «todo el disco»)
        $text = trim((string) ($elem['texto'] ?? ''));
        $unresolvedMarkersPattern = '/(?:\[\s*(?:POR\s+CONFIRMAR|PENDIENTE(?:_REVISION)?|VERIFICAR|TODO|TBD|FIXME)\s*\]|\b(?:TODO|TBD|FIXME)\s*:|\b(?:investigaci[oó]n\s+pendiente|datos?\s+sin\s+confirmar|pendiente\s+de\s+confirmaci[oó]n)\b)/u';

        if (preg_match($unresolvedMarkersPattern, $text, $markerMatch)) {
            return $this->rejection("El texto contiene marcas de investigación editorial no resuelta ('{$markerMatch[0]}').");
        }

        if (mb_strlen($text) < 60) {
            return $this->rejection("Contenido editorial insuficiente para publicación automática (longitud menor a 60 caracteres).");
        }

        // ── CRITERIO 5: Evidencia estructurada de investigación editorial ────
        // No se equivalen dos dominios a dos fuentes independientes ni se clasifica
        // automáticamente un medio como primario por su dominio. Se exige evidencia
        // estructurada: fuente original/primaria, corroboración, afirmaciones verificadas y discrepancias.
        $fuentePrimaria = trim((string) ($elem['fuente_primaria'] ?? ($elem['fuente_original'] ?? '')));
        $corroboracion = trim((string) ($elem['corroboracion'] ?? ($elem['fuente_secundaria'] ?? '')));
        $afirmaciones = trim((string) ($elem['afirmaciones_verificadas'] ?? ''));
        $discrepancias = trim((string) ($elem['discrepancias'] ?? 'NINGUNA'));

        if ($fuentePrimaria === '') {
            return $this->rejection('Evidencia editorial incompleta: falta declarar la fuente original o primaria investigada.');
        }

        if ($afirmaciones === '') {
            return $this->rejection('Evidencia editorial incompleta: falta declaración de afirmaciones contrastadas/verificadas.');
        }

        if ($discrepancias !== '' && ! in_array(strtoupper($discrepancias), ['NINGUNA', 'NONE', 'RESUELTA', 'RESUELTAS'], true)) {
            return $this->rejection("Discrepancias editoriales pendientes de resolución en la investigación: '{$discrepancias}'");
        }

        if ($tipo === 'NOTICIA') {
            if ($corroboracion === '' || strtoupper($corroboracion) === 'NINGUNA') {
                return $this->rejection(
                    'Evidencia editorial insuficiente: las noticias requieren corroboración independiente acreditada (fuente secundaria o contrastación declarada).'
                );
            }

            // Detección estricta de fuentes sindicadas / notas de prensa idénticas que no constituyen corroboración independiente
            if (preg_match('/\b(?:sindicad[ao]|nota\s+de\s+prensa\s+id[eé]ntica|mismo\s+comunicado\s+sin\s+corroboraci[oó]n|republicaci[oó]n\s+sindicada)\b/iu', $corroboracion)) {
                return $this->rejection(
                    'Evidencia editorial insuficiente: la noticia requiere corroboración independiente acreditada (fuentes sindicadas o agregadas en distintos dominios no equivalen a corroboración independiente).'
                );
            }
        }

        // Validación de seguridad de las URLs declaradas en el elemento
        $rawFuentes = $elem['fuentes'] ?? [];
        $candidateUrls = [];
        if (is_array($rawFuentes)) {
            foreach ($rawFuentes as $f) {
                $fStr = trim((string) $f);
                if ($fStr !== '') $candidateUrls[] = $fStr;
            }
        }
        if (filter_var($fuentePrimaria, FILTER_VALIDATE_URL)) {
            $candidateUrls[] = $fuentePrimaria;
        }
        if (filter_var($corroboracion, FILTER_VALIDATE_URL)) {
            $candidateUrls[] = $corroboracion;
        }

        $candidateUrls = array_values(array_unique($candidateUrls));
        if (empty($candidateUrls)) {
            return $this->rejection('El elemento no incluye URLs de fuentes consultadas verificables.');
        }

        $validSources = [];
        $linkClassifier = $this->safetyValidator->getLinkClassifier();

        foreach ($candidateUrls as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }

            if (! filter_var($candidate, FILTER_VALIDATE_URL)) {
                return $this->rejection("Fuente no cumple formato URL válido: '{$candidate}'");
            }

            $scheme = strtolower((string) parse_url($candidate, PHP_URL_SCHEME));
            if (! in_array($scheme, ['http', 'https'], true)) {
                return $this->rejection("Protocolo no permitido en fuente: '{$candidate}'");
            }

            if (parse_url($candidate, PHP_URL_USER) !== null || parse_url($candidate, PHP_URL_PASS) !== null) {
                return $this->rejection("Fuente contiene credenciales de autenticación no permitidas: '{$candidate}'");
            }

            $cl = $linkClassifier->classify($candidate);
            $classification = $cl['classification'] ?? 'unknown';

            if (in_array($classification, ['suspicious', 'restricted_private', 'under_embargo'], true)) {
                return $this->rejection("Fuente no admitida por seguridad editorial ({$classification}): '{$candidate}'");
            }

            $validSources[] = $candidate;
        }

        // ── CRITERIO 6: Fechas estrictas de calendario y vigencia del lote ──
        if (! in_array($tipo, ['NOTICIA', 'EFEMERIDE'], true)) {
            return $this->rejection("Tipo de publicación no reconocido: '{$tipo}'");
        }

        $editorialTz = (string) ($context['timezone'] ?? 'Europe/Madrid');
        $refNow = isset($context['reference_now'])
            ? Carbon::parse((string) $context['reference_now'], $editorialTz)
            : Carbon::now($editorialTz);
        $todayStr = $refNow->format('Y-m-d');

        // 6.1 Validación estricta de FECHA_EDITORIAL
        $rawEditorialDate = trim((string) ($elem['fecha_editorial'] ?? ($context['fecha_editorial'] ?? '')));
        $editorialDateCheck = self::validateStrictCalendarDate($rawEditorialDate);
        if (! $editorialDateCheck['valid']) {
            return $this->rejection("Fecha editorial no válida: " . $editorialDateCheck['error']);
        }

        // Rechazar fechas editoriales futuras
        if ($rawEditorialDate > $todayStr) {
            return $this->rejection(
                "Fecha editorial futura no permitida ({$rawEditorialDate} posterior a {$todayStr} en zona {$editorialTz})."
            );
        }

        // Vigencia diaria del lote: debe corresponder a la fecha editorial del día en la zona acordada
        // (una efeméride o lote de ayer no debe publicarse hoy automáticamente como la pieza del día)
        if ($rawEditorialDate !== $todayStr) {
            return $this->rejection(
                "Lote fuera de fecha editorial diaria ({$rawEditorialDate} vs {$todayStr} en {$editorialTz}). Las efemérides y noticias diarias deben corresponder exactamente al día de emisión para publicación automática."
            );
        }

        // 6.2 Validación estricta de FECHA_ACONTECIMIENTO
        $rawEventDate = trim((string) ($elem['fecha_acontecimiento'] ?? ''));
        $eventDateCheck = self::validateStrictCalendarDate($rawEventDate);
        if (! $eventDateCheck['valid']) {
            return $this->rejection("Fecha de acontecimiento no válida o inexistente en el calendario: " . $eventDateCheck['error']);
        }

        $eventYear = $eventDateCheck['year'];
        $eventMonth = $eventDateCheck['month'];
        $eventDay = $eventDateCheck['day'];

        $editorialYear = $editorialDateCheck['year'];
        $editorialMonth = $editorialDateCheck['month'];
        $editorialDay = $editorialDateCheck['day'];

        if ($tipo === 'EFEMERIDE') {
            if ($eventYear >= $editorialYear) {
                return $this->rejection(
                    "Fecha de efeméride no es histórica ({$rawEventDate}). El año ({$eventYear}) debe ser anterior al año editorial ({$editorialYear})."
                );
            }

            // Manejo de año bisiesto: no trasladar automáticamente el 29 de febrero al 28 en años comunes
            $isLeapEvent = ($eventMonth === 2 && $eventDay === 29);
            $isCommonEditorialYear = ! Carbon::createFromDate($editorialYear, 1, 1)->isLeapYear();

            if ($isLeapEvent && $isCommonEditorialYear) {
                return $this->rejection(
                    "Efeméride de año bisiesto (29 de febrero) recibida en año común ({$editorialYear}): requiere criterio y revisión editorial expresa para determinar su fecha de conmemoración."
                );
            }

            // Coincidencia estricta de mes y día de calendario
            if ($eventMonth !== $editorialMonth || $eventDay !== $editorialDay) {
                return $this->rejection(
                    "La fecha de la efeméride ({$eventMonth}-{$eventDay}) no coincide con el día y mes de la fecha editorial ({$editorialMonth}-{$editorialDay})."
                );
            }

            // Titular con año o contexto histórico explícito (regex corregida para capturar años como 1972)
            $hasHistoricalContext = preg_match('/\b(?:17|18|19|20)\d{2}\b/', $title)
                || preg_match('/\b(?:efem[eé]ride|nace|aniversario|fallece en|hist[oó]ric[ao])\b/iu', $title);

            if (! $hasHistoricalContext) {
                return $this->rejection(
                    "El titular de la efeméride ('{$title}') no identifica expresamente el año o contexto histórico y podría presentarse como un suceso de actualidad."
                );
            }
        } elseif ($tipo === 'NOTICIA') {
            // Diferenciar la fecha del anuncio de la fecha futura de un concierto o lanzamiento
            // La fecha de acontecimiento (anuncio/declaración) no puede ser futura respecto al día de publicación
            if ($rawEventDate > $todayStr) {
                return $this->rejection(
                    "Fecha de acontecimiento no válida para noticia ({$rawEventDate}): la fecha del anuncio no puede ser futura respecto al día de publicación. Las fechas futuras de conciertos o lanzamientos deben detallarse en el contenido, no en la fecha de acontecimiento."
                );
            }

            // Vigencia temporal de noticias: máximo 7 días de desfase respecto a la fecha editorial
            $eventCarbon = Carbon::createFromDate($eventYear, $eventMonth, $eventDay, $editorialTz);
            $editorialCarbon = Carbon::createFromDate($editorialYear, $editorialMonth, $editorialDay, $editorialTz);
            $daysDiff = $editorialCarbon->diffInDays($eventCarbon, false);

            if ($daysDiff < -7) {
                return $this->rejection(
                    "Noticia fuera de vigencia editorial ({$rawEventDate}). Tiene más de 7 días de desfase respecto a la fecha editorial ({$rawEditorialDate}). Requiere revisión editorial."
                );
            }
        }

        // ── CRITERIO 7: Permisos de imagen con evidencia estructurada ───────
        if (empty($imagePath)) {
            return $this->rejection('Imagen no disponible o descarga fallida para el artículo.');
        }

        $storageRelative = str_starts_with($imagePath, 'storage/') ? substr($imagePath, 8) : $imagePath;
        $fullPath = storage_path('app/public/' . $storageRelative);
        $publicPath = public_path($imagePath);

        if (! file_exists($fullPath) && ! file_exists($publicPath)) {
            return $this->rejection("Archivo de imagen no encontrado físicamente en disco: '{$imagePath}'");
        }

        $safeCredit = trim((string) $imageCredit);
        if ($safeCredit === '' || $safeCredit === 'NO_CONFIRMADO' || $safeCredit === 'none' || $safeCredit === 'NINGUNA') {
            return $this->rejection('Crédito de imagen incompleto o no confirmado.');
        }

        $safeAlt = trim((string) $imageAlt);
        if ($safeAlt === '' || mb_strlen($safeAlt) < 8) {
            return $this->rejection('Texto alternativo de imagen ausente o insuficiente.');
        }

        $imageMeta = $context['image_meta'] ?? [];
        $metaData = is_array($imageMeta) ? ($imageMeta['meta'] ?? $imageMeta) : [];

        $hasStructuredCommons = false;
        $hasStructuredLocal = false;

        $author = trim((string) ($metaData['artist'] ?? $metaData['author'] ?? ''));
        $license = trim((string) ($metaData['license'] ?? ''));
        $isVerified = (bool) ($metaData['verified'] ?? $metaData['is_verified'] ?? ($metaData['is_archive'] ?? false));
        $sourceUrl = (string) ($metaData['url'] ?? $metaData['page_url'] ?? $metaData['commons_page'] ?? '');

        $hasValidLicense = (bool) preg_match('/\b(CC BY|CC BY-SA|CC0|Dominio P[uú]blico|Public domain)\b/i', $license);
        $hasValidAuthor = ($author !== '' && strcasecmp($author, 'desconocido') !== 0 && strcasecmp($author, 'unknown') !== 0)
            || preg_match('/\b(Dominio P[uú]blico|Public domain|CC0)\b/i', $license);

        if ($hasValidLicense && $hasValidAuthor) {
            if (str_contains($sourceUrl, 'commons.wikimedia.org') || str_contains($sourceUrl, 'wikipedia.org') || ($metaData['source'] ?? '') === 'wikimedia_commons') {
                $hasStructuredCommons = true;
            } elseif ($isVerified || ! empty($metaData['accreditation_id']) || ! empty($metaData['is_archive'])) {
                $hasStructuredLocal = true;
            }
        }

        if (! $hasStructuredCommons && ! $hasStructuredLocal) {
            return $this->rejection(
                "Permiso de imagen no acreditado mediante evidencia estructurada (se requiere ficha de Wikimedia Commons verificada o registro de acreditación para recursos locales; un crédito declarado sin acreditación estructurada no es suficiente). Crédito: '{$safeCredit}'"
            );
        }

        // ── CRITERIO 8: Validación de seguridad final del post y metadatos ───
        $safetyResult = $this->safetyValidator->validatePost($post, targetPublishDate: now(), context: [
            'approved_urls' => $validSources,
        ]);

        if (! $safetyResult['is_safe']) {
            $violationsSummary = implode(' | ', $safetyResult['violations'] ?? ['Violación de seguridad en contenido final']);
            return $this->rejection("Validación de seguridad final fallida: {$violationsSummary}");
        }

        if ($safetyResult['has_embargo']) {
            return $this->rejection("Artículo bajo embargo activo o retención editorial.");
        }

        // ── APROBACIÓN POR POLÍTICA AUTOMÁTICA CONCEDIDA ──────────────────────
        return [
            'can_auto_publish' => true,
            'reason'           => null,
            'approved_urls'    => $validSources,
            'publication_mode' => self::PUBLICATION_MODE_AUTO,
        ];
    }

    /**
     * @return array{can_auto_publish: false, reason: string, approved_urls: array<empty>, publication_mode: string}
     */
    protected function rejection(string $reason): array
    {
        return [
            'can_auto_publish' => false,
            'reason'           => $reason,
            'approved_urls'    => [],
            'publication_mode' => 'draft_pending_review',
        ];
    }
}
