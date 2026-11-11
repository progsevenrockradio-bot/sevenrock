<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DvAutoPublishPolicy
{
    public const PUBLICATION_MODE_AUTO = 'auto_policy';

    public function __construct(
        protected EmailSafetyValidator $safetyValidator
    ) {}

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

        // ── CRITERIO 3: Sin revisión editorial pendiente ────────────────────
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

        // ── CRITERIO 4: Verificación editorial de investigación (afirmaciones y fuentes) ──
        $text = trim((string) ($elem['texto'] ?? ''));
        if (preg_match('/\b(?:\[?(?:POR CONFIRMAR|PENDIENTE(?: DE REVISI[OÓ]N)?|VERIFICAR|TODO|TBD)\]?|investigaci[oó]n pendiente|datos? sin confirmar)\b/iu', $text, $markerMatch)) {
            return $this->rejection("El texto contiene marcas o advertencias de investigación editorial no resuelta ('{$markerMatch[0]}').");
        }

        if (mb_strlen($text) < 60) {
            return $this->rejection("Contenido editorial insuficiente para publicación automática (longitud menor a 60 caracteres).");
        }

        $rawFuentes = $elem['fuentes'] ?? [];
        if (! is_array($rawFuentes) || empty($rawFuentes)) {
            return $this->rejection('El elemento no incluye fuentes consultadas verificables.');
        }

        $validSources = [];
        $sourceHosts = [];
        $linkClassifier = $this->safetyValidator->getLinkClassifier();

        foreach ($rawFuentes as $rawUrl) {
            $candidate = trim((string) $rawUrl);
            if ($candidate === '') {
                continue;
            }

            // Validar formato de URL y esquemas permitidos
            if (! filter_var($candidate, FILTER_VALIDATE_URL)) {
                return $this->rejection("Fuente no cumple formato URL válido: '{$candidate}'");
            }

            $scheme = strtolower((string) parse_url($candidate, PHP_URL_SCHEME));
            if (! in_array($scheme, ['http', 'https'], true)) {
                return $this->rejection("Protocolo no permitido en fuente: '{$candidate}'");
            }

            // Bloquear credenciales incrustadas (user:password@)
            if (parse_url($candidate, PHP_URL_USER) !== null || parse_url($candidate, PHP_URL_PASS) !== null) {
                return $this->rejection("Fuente contiene credenciales de autenticación no permitidas: '{$candidate}'");
            }

            // Clasificación de seguridad (SSRF, IP privada, phishing, malware, embargo)
            $cl = $linkClassifier->classify($candidate);
            $classification = $cl['classification'] ?? 'unknown';

            if (in_array($classification, ['suspicious', 'restricted_private', 'under_embargo'], true)) {
                return $this->rejection("Fuente no admitida por seguridad editorial ({$classification}): '{$candidate}'");
            }

            $validSources[] = $candidate;
            $host = strtolower((string) parse_url($candidate, PHP_URL_HOST));
            $host = preg_replace('/^www\./', '', $host);
            if ($host !== '') {
                $sourceHosts[$host] = true;
            }
        }

        if (count($validSources) === 0) {
            return $this->rejection('No se encontraron fuentes válidas tras la verificación de seguridad.');
        }

        // Para NOTICIA: requiere al menos 2 fuentes independientes con dominios distintos,
        // o al menos 1 fuente primaria oficial contrastada (agencia/sello/medio de rock de referencia)
        if ($tipo === 'NOTICIA') {
            $distinctHostsCount = count($sourceHosts);
            $hasPrimaryOfficial = false;
            $primaryAgencies = [
                'blabbermouth.net', 'loudwire.com', 'rollingstone.com', 'billboard.com',
                'metalblade.com', 'nuclearblast.com', 'centurymedia.com', 'napalmrecords.com',
                'discogs.com', 'allmusic.com', 'bravewords.com', 'ultimateclassicrock.com',
                'theprp.com', 'metalsucks.net', 'kerrang.com', 'metalhammer.com',
            ];

            foreach (array_keys($sourceHosts) as $h) {
                foreach ($primaryAgencies as $agency) {
                    if ($h === $agency || str_ends_with($h, '.' . $agency)) {
                        $hasPrimaryOfficial = true;
                        break 2;
                    }
                }
            }

            if ($distinctHostsCount < 2 && ! $hasPrimaryOfficial) {
                return $this->rejection(
                    "Evidencia de investigación insuficiente: las noticias requieren al menos dos fuentes independientes o una fuente primaria oficial contrastada."
                );
            }
        }

        // ── CRITERIO 5: Permisos de imagen acreditados mediante evidencia estructurada ──
        if (empty($imagePath)) {
            return $this->rejection('Imagen no disponible o descarga fallida para el artículo.');
        }

        // Comprobar existencia física en disco
        $storageRelative = str_starts_with($imagePath, 'storage/') ? substr($imagePath, 8) : $imagePath;
        $fullPath = storage_path('app/public/' . $storageRelative);
        $publicPath = public_path($imagePath);

        if (! file_exists($fullPath) && ! file_exists($publicPath)) {
            return $this->rejection("Archivo de imagen no encontrado físicamente en disco: '{$imagePath}'");
        }

        // Crédito y texto alternativo completos
        $safeCredit = trim((string) $imageCredit);
        if ($safeCredit === '' || $safeCredit === 'NO_CONFIRMADO' || $safeCredit === 'none' || $safeCredit === 'NINGUNA') {
            return $this->rejection('Crédito de imagen incompleto o no confirmado.');
        }

        $safeAlt = trim((string) $imageAlt);
        if ($safeAlt === '' || mb_strlen($safeAlt) < 8) {
            return $this->rejection('Texto alternativo de imagen ausente o insuficiente.');
        }

        // Evidencia estructurada de la imagen (NO se concede permiso por prefijo de archivo ni regex ciega sobre crédito)
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
                // Recurso local con registro estructurado de acreditación
                $hasStructuredLocal = true;
            }
        }

        if (! $hasStructuredCommons && ! $hasStructuredLocal) {
            return $this->rejection(
                "Permiso de imagen no acreditado mediante evidencia estructurada (se requiere ficha de Wikimedia Commons verificada o registro de acreditación para recursos locales; un crédito declarado sin acreditación estructurada no es suficiente). Crédito: '{$safeCredit}'"
            );
        }

        // ── CRITERIO 6: Categoría, fechas y vigencia ─────────────────────────
        if (! in_array($tipo, ['NOTICIA', 'EFEMERIDE'], true)) {
            return $this->rejection("Tipo de publicación no reconocido: '{$tipo}'");
        }

        $fechaAcontecimiento = trim((string) ($elem['fecha_acontecimiento'] ?? ''));
        if ($fechaAcontecimiento === '') {
            return $this->rejection('Fecha de acontecimiento obligatoria ausente.');
        }

        try {
            $eventDate = Carbon::parse($fechaAcontecimiento);
            $editorialDate = Carbon::parse($elem['fecha_editorial'] ?? date('Y-m-d'));
        } catch (\Throwable) {
            return $this->rejection("Fecha de acontecimiento inválida: '{$fechaAcontecimiento}'");
        }

        if ($tipo === 'EFEMERIDE') {
            if ($eventDate->year >= $editorialDate->year) {
                return $this->rejection(
                    "Fecha de efeméride no es histórica ({$fechaAcontecimiento}). El año ({$eventDate->year}) debe ser anterior al año editorial ({$editorialDate->year})."
                );
            }

            // Coincidencia estricta de mes y día, con soporte para 29 de febrero en años comunes
            $isLeapEvent = ($eventDate->month === 2 && $eventDate->day === 29);
            $isCommonEditorialYear = ! $editorialDate->isLeapYear();

            if ($isLeapEvent && $isCommonEditorialYear) {
                $matchesMonthDay = ($editorialDate->month === 2 && $editorialDate->day === 28);
            } else {
                $matchesMonthDay = ($editorialDate->month === $eventDate->month && $editorialDate->day === $eventDate->day);
            }

            if (! $matchesMonthDay) {
                return $this->rejection(
                    "La fecha de la efeméride ({$eventDate->format('m-d')}) no coincide con el día y mes de la fecha editorial ({$editorialDate->format('m-d')})."
                );
            }

            // Titular con año o contexto histórico explícito (regex corregida para capturar años como 1972)
            $hasHistoricalContext = preg_match('/\b(?:17|18|19|20)\d{2}\b/', $title)
                || preg_match('/\b(efem[eé]ride|nace|aniversario|fallece en|hist[oó]ric[ao])\b/iu', $title);

            if (! $hasHistoricalContext) {
                return $this->rejection(
                    "El titular de la efeméride ('{$title}') no identifica expresamente el año o contexto histórico y podría presentarse como un suceso de actualidad."
                );
            }
        } elseif ($tipo === 'NOTICIA') {
            // No admitir acontecimientos futuros respecto a la fecha editorial
            if ($eventDate->greaterThan($editorialDate->copy()->addDay())) {
                return $this->rejection(
                    "Fecha de acontecimiento no válida para noticia ({$fechaAcontecimiento}): posterior a la fecha editorial ({$editorialDate->format('Y-m-d')})."
                );
            }

            // Vigencia temporal de noticias: máximo 7 días de desfase respecto a la fecha editorial
            if ($eventDate->diffInDays($editorialDate, false) > 7) {
                return $this->rejection(
                    "Noticia fuera de vigencia editorial ({$fechaAcontecimiento}). Tiene más de 7 días de desfase respecto a la fecha editorial ({$editorialDate->format('Y-m-d')}). Requiere revisión editorial."
                );
            }

            // Detección de lotes demorados: fecha editorial con más de 3 días de desfase respecto al momento de ejecución
            $now = Carbon::now();
            if ($editorialDate->diffInDays($now, false) > 3) {
                return $this->rejection(
                    "Lote demorado: la fecha editorial ({$editorialDate->format('Y-m-d')}) tiene más de 3 días de retraso respecto a la fecha actual ({$now->format('Y-m-d')}). Requiere revisión editorial."
                );
            }
        }

        // ── CRITERIO 7: Validación de seguridad final del post y metadatos ───
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
