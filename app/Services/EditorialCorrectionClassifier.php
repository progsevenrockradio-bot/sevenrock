<?php

declare(strict_types=1);

namespace App\Services;

class EditorialCorrectionClassifier
{
    /**
     * Patrones de Fe de Erratas y Corrección de Datos.
     */
    protected const PATTERNS_RECTIFICATION = [
        '/\b(?:fe\s+de\s+erratas?|correcci[oó]n|rectificaci[oó]n|correction|amendment|errata)\b/iu',
        '/\b(?:cambio\s+de\s+(?:fecha|sala|recinto|horario|ubicaci[oó]n))\b/iu',
        '/\b(?:venue\s+change|date\s+change|rescheduled)\b/iu',
        '/\b(?:en\s+lugar\s+de|instead\s+of)\b/iu',
    ];

    /**
     * Patrones de Retirada de Comunicado o Solicitud de Descarte.
     */
    protected const PATTERNS_RETRACTION = [
        '/por\s+favor\s*,\s*ignoren\s+(?:el\s+)?correo(?:\s+electr[oó]nico)?\s+anterior/iu',
        '/please\s+ignore\s+(?:the\s+)?previous\s+email/iu',
        '/\b(?:retirada\s+de\s+comunicado|retirar\s+comunicado|solicitud\s+de\s+retirada|withdraw(?:al)?\s+of\s+press\s+release)\b/iu',
        '/\b(?:do\s+not\s+publish\s+previous|no\s+publicar\s+el\s+anterior)\b/iu',
        '/\b(?:anulaci[oó]n\s+de\s+nota\s+de\s+prensa|anular\s+nota\s+de\s+prensa)\b/iu',
    ];

    /**
     * Patrones de Cancelación de Gira / Concierto / Lanzamiento.
     */
    protected const PATTERNS_CANCELLATION = [
        '/\b(?:concierto\s+cancelado|gira\s+cancelada|evento\s+cancelado|show\s+cancelado)\b/iu',
        '/\b(?:tour\s+cancelled|show\s+cancelled|concert\s+cancelled|tour\s+canceled)\b/iu',
        '/\b(?:suspensi[oó]n\s+definitiva|cancelaci[oó]n\s+de\s+(?:gira|concierto|festival|lanzamiento))\b/iu',
        '/\b(?:se\s+cancela\s+(?:la\s+gira|el\s+concierto|el\s+show))\b/iu',
    ];

    /**
     * Patrones de Actualización Legítima Progresiva.
     */
    protected const PATTERNS_UPDATE = [
        '/\b(?:updated\s+press\s+release|comunicado\s+actualizado|nota\s+actualizada)\b/iu',
        '/\b(?:nuevas\s+fechas\s+a[ñn]adidas|new\s+dates\s+added)\b/iu',
        '/\b(?:videoclip\s+oficial\s+ya\s+disponible|official\s+video\s+now\s+available)\b/iu',
        '/\b(?:revelada\s+la\s+portada|cover\s+art\s+revealed)\b/iu',
        '/\b(?:segunda\s+fecha\s+anunciada)\b/iu',
    ];

    /**
     * Clasifica un mensaje determinando si corresponde a una rectificación, retirada, cancelación o actualización.
     */
    public function classify(string $subject, string $body): array
    {
        $text = $subject . ' ' . strip_tags($body);

        // 1. Detección de Retiradas (Prioridad Máxima)
        foreach (self::PATTERNS_RETRACTION as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return [
                    'is_correction'   => true,
                    'incident_type'   => 'retraction_withdrawal',
                    'priority'        => 'urgent',
                    'matched_pattern' => $m[0],
                    'notes'           => "Solicitud expresa de retirada o ignorar comunicado previo: '{$m[0]}'",
                ];
            }
        }

        // 2. Detección de Cancelaciones
        foreach (self::PATTERNS_CANCELLATION as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return [
                    'is_correction'   => true,
                    'incident_type'   => 'event_cancellation',
                    'priority'        => 'high',
                    'matched_pattern' => $m[0],
                    'notes'           => "Cancelación de evento detectada: '{$m[0]}'",
                ];
            }
        }

        // 3. Detección de Fe de Erratas / Corrección de Datos
        foreach (self::PATTERNS_RECTIFICATION as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return [
                    'is_correction'   => true,
                    'incident_type'   => 'rectification_data',
                    'priority'        => 'high',
                    'matched_pattern' => $m[0],
                    'notes'           => "Corrección o Fe de Erratas detectada: '{$m[0]}'",
                ];
            }
        }

        // 4. Detección de Actualizaciones Legítimas
        foreach (self::PATTERNS_UPDATE as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return [
                    'is_correction'   => true,
                    'incident_type'   => 'legitimate_update',
                    'priority'        => 'medium',
                    'matched_pattern' => $m[0],
                    'notes'           => "Actualización informativa de material o fechas: '{$m[0]}'",
                ];
            }
        }

        return [
            'is_correction'   => false,
            'incident_type'   => 'standard_news',
            'priority'        => 'low',
            'matched_pattern' => null,
            'notes'           => null,
        ];
    }
}
