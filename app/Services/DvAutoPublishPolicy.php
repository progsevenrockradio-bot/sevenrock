<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmailPostSanitizedMeta;
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
        $senderEmail = strtolower(trim((string) ($context['sender_email'] ?? 'dark.vader.agent@gmail.com')));
        $isSenderAuthenticated = (bool) ($context['is_sender_authenticated'] ?? true);

        if ($senderEmail !== 'dark.vader.agent@gmail.com' || ! $isSenderAuthenticated) {
            return $this->rejection(
                "Remitente no autenticado como agente Darth Vader autorizado ({$senderEmail})."
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

        // ── CRITERIO 4: Fuentes válidas y acreditadas ─────────────────────────
        $rawFuentes = $elem['fuentes'] ?? [];
        if (! is_array($rawFuentes) || empty($rawFuentes)) {
            return $this->rejection('El elemento no incluye fuentes consultadas verificables.');
        }

        $validSources = [];
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
        }

        if (count($validSources) === 0) {
            return $this->rejection('No se encontraron fuentes válidas tras la verificación de seguridad.');
        }

        // ── CRITERIO 5: Permisos de imagen acreditados conforme al procedimiento ──
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

        // Acreditación de licencia y procedencia
        $isCommons = str_contains($safeCredit, 'Wikimedia Commons')
            || str_contains($safeCredit, 'commons.wikimedia.org')
            || (isset($elem['imagen_url']) && str_contains((string) $elem['imagen_url'], 'commons.wikimedia.org'));

        $isPreinstalledLocal = str_starts_with($imagePath, 'storage/posts/covers/post-dv-')
            || str_starts_with($imagePath, 'assets/');

        $hasApprovedLicense = preg_match('/\b(CC BY|CC BY-SA|CC0|Dominio P[uú]blico|Public domain)\b/i', $safeCredit);

        if (! ($isCommons && $hasApprovedLicense) && ! ($isPreinstalledLocal && $hasApprovedLicense)) {
            return $this->rejection(
                "Permiso de imagen no acreditado conforme al procedimiento aprobado (requiere ficha de Wikimedia Commons o recurso local verificado con licencia CC/Dominio Público). Crédito: '{$safeCredit}'"
            );
        }

        // ── CRITERIO 6: Categoría y fechas correctas ─────────────────────────
        if (! in_array($tipo, ['NOTICIA', 'EFEMERIDE'], true)) {
            return $this->rejection("Tipo de publicación no reconocido: '{$tipo}'");
        }

        $fechaAcontecimiento = trim((string) ($elem['fecha_acontecimiento'] ?? ''));
        if ($fechaAcontecimiento === '') {
            return $this->rejection('Fecha de acontecimiento obligatoria ausente.');
        }

        // Si es efeméride: debe ser hecho histórico y el titular debe identificarlo
        if ($tipo === 'EFEMERIDE') {
            try {
                $eventDate = Carbon::parse($fechaAcontecimiento);
                if ($eventDate->year >= (int) date('Y')) {
                    return $this->rejection("Fecha de efeméride no es histórica ({$fechaAcontecimiento}). El año debe ser anterior al actual.");
                }
            } catch (\Throwable) {
                return $this->rejection("Fecha de acontecimiento de efeméride inválida: '{$fechaAcontecimiento}'");
            }

            // El titular debe identificar expresamente que se trata de un hecho histórico o año
            $hasHistoricalContext = preg_match('/\b(18|19|20\d{2})\b/', $title)
                || preg_match('/\b(efem[eé]ride|nace|aniversario|fallece en|hist[oó]ric[ao])\b/i', $title);

            if (! $hasHistoricalContext) {
                return $this->rejection(
                    "El titular de la efeméride ('{$title}') no identifica expresamente el año o contexto histórico y podría presentarse como un suceso de actualidad."
                );
            }
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
