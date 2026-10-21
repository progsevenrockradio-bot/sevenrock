<?php

declare(strict_types=1);

namespace App\Support;

class EmbargoDetector
{
    /**
     * Patrones de expresión regular para detectar condiciones de embargo y confidencialidad.
     */
    protected static array $patterns = [
        '/\bunder\s+embargo\b/i',
        '/\bbajo\s+embargo\b/i',
        '/\b(?:estricto\s+)?embargo(?:\s+hasta)?\b/i',
        '/\bnot\s+for\s+immediate\s+release\b/i',
        '/\bembargoed\s+until\b/i',
        '/\bdo\s+not\s+publish\s+before\b/i',
        '/\bno\s+publicar\s+antes\s+de\b/i',
        '/\bstrictly\s+confidential\b/i',
        '/\bestrictamente\s+confidencial\b/i',
        '/\bconfidential\s+and\s+privileged\b/i',
        '/\bmaterial\s+privado\b/i',
        '/\bprivate\s+release\b/i',
        '/\b(?:^|\s)\[?\s*embargo\s*\]?/i',
        '/\b(?:^|\s)\[?\s*confidencial\s*\]?/i',
    ];


    /**
     * Evalúa si el asunto o el cuerpo del correo contienen restricciones de publicación o embargo.
     *
     * @return array ['is_embargoed' => bool, 'reason' => ?string, 'matched_pattern' => ?string]
     */
    public static function detect(string $subject, string $body): array
    {
        $subject = mb_strtolower($subject);
        $cleanBody = mb_strtolower(strip_tags($body));

        foreach (self::$patterns as $pattern) {
            if (preg_match($pattern, $subject, $matches)) {
                return [
                    'is_embargoed'    => true,
                    'reason'          => 'subject_embargo_detected',
                    'matched_pattern' => $matches[0],
                ];
            }

            if (preg_match($pattern, $cleanBody, $matches)) {
                return [
                    'is_embargoed'    => true,
                    'reason'          => 'body_embargo_detected',
                    'matched_pattern' => $matches[0],
                ];
            }
        }

        return [
            'is_embargoed'    => false,
            'reason'          => null,
            'matched_pattern' => null,
        ];
    }

    /**
     * Comprobación booleana rápida de si un texto contiene indicadores de embargo.
     */
    public static function isEmbargoed(string $text): bool
    {
        return self::detect('', $text)['is_embargoed'];
    }
}

