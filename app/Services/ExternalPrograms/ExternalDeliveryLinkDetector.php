<?php

declare(strict_types=1);

namespace App\Services\ExternalPrograms;

use App\Models\ExternalProgramDelivery;
use Illuminate\Support\Carbon;

class ExternalDeliveryLinkDetector
{
    /**
     * Extrae enlaces de entrega compatibles sin abrir ni descargar ningún archivo.
     *
     * @return array<int, array{url: string, platform: string}>
     */
    public function extractLinks(string $text, ?string $html = null): array
    {
        $candidates = [];

        if ($html !== null && trim($html) !== '') {
            if (preg_match_all('/<a\b[^>]*\bhref\s*=\s*["\']([^"\']+)["\']/i', $html, $hrefMatches)) {
                foreach ($hrefMatches[1] as $rawHref) {
                    $candidates[] = html_entity_decode($rawHref, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }
        }

        $combinedText = $text;
        if ($html !== null && trim($html) !== '') {
            $combinedText .= "\n" . strip_tags($html);
        }

        if (preg_match_all('/https?:\/\/[^\s<>"\'\]\)]+/i', $combinedText, $urlMatches)) {
            foreach ($urlMatches[0] as $rawUrl) {
                $candidates[] = html_entity_decode($rawUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        $results = [];
        $seen = [];

        foreach ($candidates as $raw) {
            $url = $this->cleanUrl($raw);
            if ($url === '') {
                continue;
            }

            $platform = $this->detectSupportedPlatform($url);
            if ($platform === null) {
                continue;
            }

            if ($this->isNonDeliveryUtilityUrl($url, $platform)) {
                continue;
            }

            $dedupeKey = strtolower($url);
            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;
            $results[] = [
                'url' => $url,
                'platform' => $platform,
            ];
        }

        return $results;
    }

    /**
     * Detecta la plataforma de una URL (para registros manuales o enlaces detectados).
     */
    public function detectPlatformFromUrl(string $url): string
    {
        return $this->detectSupportedPlatform($this->cleanUrl($url))
            ?? ExternalProgramDelivery::PLATFORM_OTHER;
    }

    /**
     * Devuelve la plataforma soportada o null si el enlace no pertenece a una plataforma reconocida.
     */
    public function detectSupportedPlatform(string $url): ?string
    {
        $parsed = parse_url($url);
        if (! is_array($parsed)) {
            return null;
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        $host = strtolower((string) ($parsed['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        if ($host === 'drive.google.com' || $host === 'drive.usercontent.google.com') {
            return ExternalProgramDelivery::PLATFORM_GOOGLE_DRIVE;
        }

        if ($host === 'fromsmash.com' || str_ends_with($host, '.fromsmash.com')) {
            return ExternalProgramDelivery::PLATFORM_SMASH;
        }

        if ($host === 'transfernow.net' || str_ends_with($host, '.transfernow.net')) {
            return ExternalProgramDelivery::PLATFORM_TRANSFERNOW;
        }

        if ($host === 'we.tl' || $host === 'wetransfer.com' || str_ends_with($host, '.wetransfer.com')) {
            return ExternalProgramDelivery::PLATFORM_WETRANSFER;
        }

        return null;
    }

    /**
     * Extrae una fecha de caducidad SOLO cuando aparece explícita y de forma fiable en el texto del correo.
     * Nunca inventa una fecha. Si no es fiable, devuelve null.
     */
    public function extractExpiryFromContent(string $text, ?Carbon $receivedAt = null, string $timezone = 'Europe/Madrid'): ?Carbon
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?: '');
        if ($plain === '') {
            return null;
        }

        $tz = in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : (string) config('app.timezone', 'Europe/Madrid');

        // 1. Formato ISO explícito tras palabra clave de caducidad: "caduca el 2026-12-03" / "expires on 2026-12-03"
        if (preg_match('/(?:caduca|expira|vence|disponible\s+hasta|expires?(?:\s+on)?|available\s+until|valid\s+until)\s*(?:el|:)?\s*(\d{4}-\d{2}-\d{2})(?:\s+(?:a\s+las\s+)?(\d{1,2}:\d{2}))?/iu', $plain, $m)) {
            $time = $m[2] ?? '23:59';
            return $this->parseValidatedDate($m[1] . ' ' . $time, 'Y-m-d H:i', $tz, $receivedAt);
        }

        // 2. Formato DD/MM/YYYY o DD-MM-YYYY tras palabra clave de caducidad
        if (preg_match('/(?:caduca|expira|vence|disponible\s+hasta|expires?(?:\s+on)?|available\s+until|valid\s+until)\s*(?:el|:)?\s*(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})(?:\s+(?:a\s+las\s+)?(\d{1,2}:\d{2}))?/iu', $plain, $m)) {
            $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            $year = $m[3];
            $time = $m[4] ?? '23:59';
            return $this->parseValidatedDate("{$year}-{$month}-{$day} {$time}", 'Y-m-d H:i', $tz, $receivedAt);
        }

        // 3. Fecha con mes en texto español: "caduca el 3 de diciembre de 2026" / "disponible hasta 30 noviembre 2026"
        $esMonths = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
        ];
        $esMonthPattern = implode('|', array_keys($esMonths));
        if (preg_match('/(?:caduca|expira|vence|disponible\s+hasta|válido\s+hasta|valido\s+hasta)\s*(?:el|:)?\s*(\d{1,2})\s+(?:de\s+)?(' . $esMonthPattern . ')\s+(?:de\s+|del\s+)?(\d{4})/iu', $plain, $m)) {
            $monthNum = $esMonths[mb_strtolower($m[2])] ?? null;
            if ($monthNum !== null) {
                $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                $month = str_pad((string) $monthNum, 2, '0', STR_PAD_LEFT);
                return $this->parseValidatedDate("{$m[3]}-{$month}-{$day} 23:59", 'Y-m-d H:i', $tz, $receivedAt);
            }
        }

        // 4. Fecha con mes en texto inglés: "Expires on 30 November 2026" o "Expires on November 30, 2026"
        $enMonths = [
            'january' => 1, 'jan' => 1, 'february' => 2, 'feb' => 2, 'march' => 3, 'mar' => 3,
            'april' => 4, 'apr' => 4, 'may' => 5, 'june' => 6, 'jun' => 6, 'july' => 7, 'jul' => 7,
            'august' => 8, 'aug' => 8, 'september' => 9, 'sep' => 9, 'sept' => 9,
            'october' => 10, 'oct' => 10, 'november' => 11, 'nov' => 11, 'december' => 12, 'dec' => 12,
        ];
        $enMonthPattern = implode('|', array_keys($enMonths));

        if (preg_match('/(?:expires?(?:\s+on)?|available\s+until|valid\s+until)\s*:?\s*(\d{1,2})\s+(' . $enMonthPattern . '),?\s+(\d{4})/iu', $plain, $m)) {
            $monthNum = $enMonths[strtolower($m[2])] ?? null;
            if ($monthNum !== null) {
                $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                $month = str_pad((string) $monthNum, 2, '0', STR_PAD_LEFT);
                return $this->parseValidatedDate("{$m[3]}-{$month}-{$day} 23:59", 'Y-m-d H:i', $tz, $receivedAt);
            }
        }

        if (preg_match('/(?:expires?(?:\s+on)?|available\s+until|valid\s+until)\s*:?\s*(' . $enMonthPattern . ')\s+(\d{1,2}),?\s+(\d{4})/iu', $plain, $m)) {
            $monthNum = $enMonths[strtolower($m[1])] ?? null;
            if ($monthNum !== null) {
                $day = str_pad($m[2], 2, '0', STR_PAD_LEFT);
                $month = str_pad((string) $monthNum, 2, '0', STR_PAD_LEFT);
                return $this->parseValidatedDate("{$m[3]}-{$month}-{$day} 23:59", 'Y-m-d H:i', $tz, $receivedAt);
            }
        }

        return null;
    }

    protected function cleanUrl(string $raw): string
    {
        $url = trim($raw);
        $url = rtrim($url, ".,;:!?\"'<>)]}");

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        return $url;
    }

    protected function isNonDeliveryUtilityUrl(string $url, string $platform): bool
    {
        $parsed = parse_url($url);
        $path = strtolower(trim((string) ($parsed['path'] ?? ''), '/'));
        $query = (string) ($parsed['query'] ?? '');

        // Enlaces raíz sin ruta ni parámetros no son enlaces de descarga
        if ($path === '' && $query === '') {
            return true;
        }

        $ignoredPrefixes = [
            'legal', 'privacy', 'terms', 'help', 'support', 'about', 'pricing', 'blog',
            'unsubscribe', 'preferences', 'account', 'login', 'signup', 'sign-in',
            'en/terms', 'en/privacy', 'es/terms', 'es/privacy', 'fr/terms', 'fr/privacy',
            'contact', 'faq', 'security', 'cookies', 'status',
        ];

        foreach ($ignoredPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        // Recursos estáticos (logos/imágenes del correo)
        if (preg_match('/\.(png|jpe?g|gif|svg|ico|css|js)$/i', $path)) {
            return true;
        }

        if ($platform === ExternalProgramDelivery::PLATFORM_GOOGLE_DRIVE) {
            // Aceptar enlaces de archivo, carpeta o apertura/descarga
            $isDriveDelivery = str_starts_with($path, 'file/d/')
                || str_starts_with($path, 'drive/folders/')
                || str_starts_with($path, 'drive/u/')
                || $path === 'open'
                || $path === 'uc'
                || str_starts_with($path, 'download');

            return ! $isDriveDelivery;
        }

        if ($platform === ExternalProgramDelivery::PLATFORM_WETRANSFER) {
            $host = strtolower((string) ($parsed['host'] ?? ''));
            if ($host === 'we.tl') {
                return ! str_starts_with($path, 't-');
            }

            return ! (str_starts_with($path, 'downloads/') || str_starts_with($path, 'download/'));
        }

        if ($platform === ExternalProgramDelivery::PLATFORM_TRANSFERNOW) {
            return ! (str_contains($path, 'dl/') || str_starts_with($path, 'dl') || str_contains($path, 'download'));
        }

        // Smash: fromsmash.com/<transfer-slug>
        return false;
    }

    protected function parseValidatedDate(string $value, string $format, string $tz, ?Carbon $receivedAt): ?Carbon
    {
        try {
            $parsed = Carbon::createFromFormat($format, $value, $tz);
            if ($parsed === false) {
                return null;
            }

            // Verificar que la fecha formateada coincide (evita fechas imposibles como 31/02)
            if ($parsed->format($format) !== $value) {
                return null;
            }

            // Si se conoce receivedAt, descartar fechas anteriores al día de recepción o absurdas (> 2 años)
            if ($receivedAt !== null) {
                $minDate = $receivedAt->copy()->setTimezone($tz)->startOfDay();
                $maxDate = $receivedAt->copy()->setTimezone($tz)->addYears(2);
                if ($parsed->lt($minDate) || $parsed->gt($maxDate)) {
                    return null;
                }
            }

            return $parsed;
        } catch (\Throwable) {
            return null;
        }
    }
}

