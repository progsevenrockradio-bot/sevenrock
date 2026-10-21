<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ReleaseScheduleManager
{
    /**
     * Patrones para identificar correcciones y fe de erratas.
     */
    protected const RECTIFICATION_PATTERNS = [
        '/^(?:fe\s+de\s+erratas?|correcci[oó]n|rectificaci[oó]n|correction|amendment)\s*[:—–-]/iu',
        '/por\s+favor\s*,\s*ignoren\s+el\s+correo(?:\s+electr[oó]nico)?\s+anterior/iu',
        '/please\s+ignore\s+(?:the\s+)?previous\s+email/iu',
        '/updated\s+press\s+release\s*[:—–-]/iu',
        '/comunicado\s+actualizado\s*[:—–-]/iu',
    ];

    /**
     * Determina rápidamente si el asunto o cuerpo constituyen una rectificación / fe de erratas.
     */
    public function isRectification(string $subject, string $body): bool
    {
        foreach (self::RECTIFICATION_PATTERNS as $pattern) {
            if (preg_match($pattern, $subject) || preg_match($pattern, $body)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Alias de conveniencia para evaluateSchedule.
     */
    public function resolveSchedule(string $text, ?Carbon $receivedAt = null): array
    {
        $evaluation = $this->evaluateSchedule('', $text, $receivedAt);
        return [
            'is_under_embargo'     => $evaluation['is_embargo_active'],
            'embargo_until'        => $evaluation['embargo_until'],
            'publish_permitted_at' => $evaluation['publish_permitted_at'],
            'musical_release_date' => $evaluation['musical_release_date'],
            'notes'                => $evaluation['notes'],
        ];
    }


    /**
     * Evalúa las 4 fechas del comunicado:
     * - Fecha de recepción (received_at)
     * - Fecha de lanzamiento musical (musical_release_date)
     * - Fecha de embargo estricto (embargo_until)
     * - Fecha permitida de publicación (publish_permitted_at)
     *
     * @return array [
     *     'received_at'           => Carbon,
     *     'musical_release_date'  => ?Carbon,
     *     'embargo_until'         => ?Carbon,
     *     'publish_permitted_at'  => Carbon,
     *     'is_embargo_active'     => bool,
     *     'is_future_release'     => bool,
     *     'notes'                 => ?string,
     * ]
     */
    public function evaluateSchedule(string $subject, string $body, ?Carbon $receivedAt = null): array
    {
        $receivedAt = $receivedAt ?: now();
        $text = mb_strtolower($subject . ' ' . strip_tags($body));

        $embargoUntil = null;
        $musicalReleaseDate = null;
        $isEmbargoActive = false;
        $isFutureRelease = false;
        $notes = null;

        // 1. Detección de Embargo Explícito con Fecha/Hora
        // Ej: "under embargo until 25 oct 2026", "embargado hasta el 25 de octubre", "estricto embargo hasta"
        if (preg_match('/\b(?:under\s+embargo\s+until|embargoed\s+until|embargado\s+hasta(?:\s+el)?|(?:estricto\s+)?embargo\s+hasta(?:\s+el)?|no\s+publicar\s+antes\s+de)\s+([0-9a-zA-Z\s,:-]+)/iu', $text, $matches)) {
            $dateString = trim($matches[1]);
            // Limitar longitud para evitar capturar todo el párrafo
            $dateString = preg_split('/\r?\n|[.]/', $dateString)[0] ?? $dateString;
            $dateString = mb_substr($dateString, 0, 40);

            try {
                $parsedDate = Carbon::parse($dateString);
                // Si la fecha parseada es en el futuro respecto a la recepción:
                if ($parsedDate->greaterThan($receivedAt)) {
                    $embargoUntil = $parsedDate;
                    $isEmbargoActive = true;
                    $notes = "Embargo activo hasta {$parsedDate->toDateTimeString()}";
                }
            } catch (\Throwable $e) {
                // Si hay indicación de embargo pero fecha ambigua, forzar retención
                $embargoUntil = $receivedAt->copy()->addYears(1); // Fecha centinela de retención
                $isEmbargoActive = true;
                $notes = "Embargo detectado con fecha ambigua: '{$dateString}'. Retenido para revisión.";
            }
        } elseif (\App\Support\EmbargoDetector::detect($subject, $body)['is_embargoed']) {
            // Embargo genérico sin fecha precisa
            $embargoUntil = $receivedAt->copy()->addYears(1);
            $isEmbargoActive = true;
            $notes = "Embargo detectado sin fecha exacta. Retenido para revisión humana.";
        }

        // 2. Detección de Fecha de Lanzamiento Musical (Álbum / Single)
        // Ej: "out on november 15", "available nov 15", "lanzamiento 15 de noviembre", "a la venta el 25 de noviembre"
        if (preg_match('/\b(?:out\s+(?:on|now)?|album\s+release|released\s+on|lanzamiento(?:\s+el)?|a\s+la\s+venta(?:\s+el)?|disco\s+para\s+el)\s+(\d{1,2}(?:\s+de)?\s+[a-z]+(?:\s+de|\s+del)?(?:\s+\d{4})?|[a-z]+\s+\d{1,2}(?:st|nd|rd|th)?(?:\s*,\s*\d{4})?)/iu', $text, $mRelease)) {
            $relString = trim($mRelease[1]);

            // Mapeo amigable de meses en español para Carbon::parse
            $normalizedRelString = str_ireplace(
                ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre', ' de ', ' del '],
                ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december', ' ', ' '],
                $relString
            );

            try {
                $parsedRel = Carbon::parse($normalizedRelString);
                $musicalReleaseDate = $parsedRel;
                $isFutureRelease = $parsedRel->isFuture();
            } catch (\Throwable $e) {
                // No se pudo parsear como fecha estricta
            }
        }

        // 3. Fecha permitida de publicación:
        // Si hay embargo activo, es la fecha del embargo. De lo contrario, es inmediata (now o received_at).
        $publishPermittedAt = $isEmbargoActive && $embargoUntil ? $embargoUntil : $receivedAt;

        return [
            'received_at'          => $receivedAt,
            'musical_release_date' => $musicalReleaseDate,
            'embargo_until'        => $isEmbargoActive ? $embargoUntil : null,
            'publish_permitted_at' => $publishPermittedAt,
            'is_embargo_active'    => $isEmbargoActive,
            'is_future_release'    => $isFutureRelease,
            'notes'                => $notes,
        ];
    }

    /**
     * Identifica si un correo es una rectificación de un comunicado previo.
     * En caso afirmativo, relaciona con el comunicado anterior mediante evidencias verificables
     * SIN despublicar ni sustituir automáticamente a ciegas.
     *
     * @return array|null [
     *     'is_rectification'     => bool,
     *     'target_post'          => ?Post,
     *     'incident_description' => string,
     * ]
     */
    public function detectRectification(string $subject, string $body, string $senderEmail, ?string $messageId = null): ?array
    {
        $isRectification = false;
        foreach (self::RECTIFICATION_PATTERNS as $pattern) {
            if (preg_match($pattern, $subject) || preg_match($pattern, $body)) {
                $isRectification = true;
                break;
            }
        }

        if (! $isRectification) {
            return null;
        }

        // Buscar evidencias de comunicado previo:
        // Mismo remitente en las últimas 48 horas
        $recentCandidates = Post::query()
            ->where('author_email', $senderEmail)
            ->where('created_at', '>=', now()->subHours(48))
            ->latest()
            ->get();

        $targetPost = null;
        if ($recentCandidates->isNotEmpty()) {
            // Seleccionar el candidato más cercano en tiempo
            $targetPost = $recentCandidates->first();
        }

        $incidentDescription = "Rectificación o Fe de Erratas recibida de {$senderEmail}.";
        if ($targetPost) {
            $incidentDescription .= " Relacionada con post ID {$targetPost->id} ('{$targetPost->title}'). Se requiere revisión editorial antes de alterar el contenido existente.";
            Log::warning("ReleaseScheduleManager: Rectificación detectada.", [
                'sender'       => $senderEmail,
                'target_post'  => $targetPost->id,
                'is_published' => $targetPost->is_published,
            ]);
        } else {
            $incidentDescription .= " No se localizó un post previo inequívoco del mismo remitente en las últimas 48h. Retenido para revisión.";
        }

        return [
            'is_rectification'     => true,
            'target_post'          => $targetPost,
            'incident_description' => $incidentDescription,
        ];
    }
}
