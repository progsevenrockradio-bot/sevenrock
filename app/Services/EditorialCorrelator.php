<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EditorialCorrelator
{
    public function __construct(
        protected EmailSecurityVerifier $securityVerifier
    ) {}

    /**
     * Busca y correlaciona un correo de corrección contra publicaciones previas en una ventana de 30 días.
     *
     * @param array $incomingData Datos del correo entrante (sender, subject, body, message_id, raw_headers, artist_name)
     * @return array [
     *     'has_match'         => bool,
     *     'is_confident'      => bool,      // Puntuación >= 70 y evidencia de identidad
     *     'is_ambiguous'      => bool,      // Puntuación entre 40 y 69
     *     'is_spoofed'        => bool,      // Intento de suplantación detectado
     *     'target_post'       => ?Post,
     *     'correlation_score' => int,
     *     'evidences'         => array<string>,
     *     'detected_diff'     => array,
     * ]
     */
    public function correlate(array $incomingData): array
    {
        $sender = strtolower(trim((string) ($incomingData['sender'] ?? '')));
        $subject = (string) ($incomingData['subject'] ?? '');
        $body = (string) ($incomingData['body'] ?? '');
        $rawHeaders = (string) ($incomingData['raw_headers'] ?? '');
        $incomingArtist = trim((string) ($incomingData['artist_name'] ?? ''));

        // Candidatos en ventana de 30 días
        $candidates = Post::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->orderByDesc('created_at')
            ->get();

        $bestMatch = null;
        $highestScore = 0;
        $bestEvidences = [];
        $isSpoofed = false;

        $normalizedSubject = mb_strtolower(trim(preg_replace('/\s+/', ' ', $subject)));
        $cleanSubject = preg_replace('/^(?:re|fwd|fe\s+de\s+erratas?|correcci[oó]n|rectificaci[oó]n|actualizaci[oó]n)\s*[:—–-]\s*/iu', '', $normalizedSubject);

        foreach ($candidates as $post) {
            $score = 0;
            $evidences = [];

            $postSubject = mb_strtolower(trim((string) ($post->source_subject ?: $post->title)));
            $cleanPostSubject = preg_replace('/^(?:re|fwd|fe\s+de\s+erratas?|correcci[oó]n|rectificaci[oó]n|actualizaci[oó]n)\s*[:—–-]\s*/iu', '', $postSubject);
            $postAuthor = strtolower(trim((string) ($post->author_email ?? '')));

            // 1. REGLA DE BLOQUEO DE ARTISTA: Si ambos definen artista y difieren claramente, descartar candidato
            if ($incomingArtist !== '') {
                $postTitleLower = mb_strtolower($post->title);
                $incomingArtistLower = mb_strtolower($incomingArtist);

                // Si el post anterior no menciona al artista entrante de ninguna forma
                if (!str_contains($postTitleLower, $incomingArtistLower) && !str_contains($postSubject, $incomingArtistLower)) {
                    continue; // Puntuación 0 inmediata para este candidato
                } else {
                    $score += 30;
                    $evidences[] = "Coincidencia exacta de entidad artística: '{$incomingArtist}'";
                }
            }

            // 2. Identidad del Remitente
            if ($sender !== '' && $sender === $postAuthor) {
                // Verificar si el remitente supera validaciones criptográficas si se suministran cabeceras
                if ($rawHeaders !== '') {
                    $authResult = $this->securityVerifier->verify($sender, $rawHeaders);
                    if (($authResult['is_spoofing'] ?? false) || ($authResult['status'] ?? '') === 'spoofing_detected') {
                        $isSpoofed = true;
                        Log::warning("EditorialCorrelator: Intento de correlación con remitente falsificado.", [
                            'sender'    => $sender,
                            'target_id' => $post->id,
                        ]);
                        continue; // No permitir que un remitente falsificado se correlacione
                    }
                }
                $score += 30;
                $evidences[] = "Mismo remitente verificado: '{$sender}'";
            }


            // 3. Referencia en Asunto / Titular
            if ($cleanSubject !== '' && $cleanPostSubject !== '') {
                if ($cleanSubject === $cleanPostSubject || str_contains($cleanSubject, $cleanPostSubject) || str_contains($cleanPostSubject, $cleanSubject)) {
                    $score += 25;
                    $evidences[] = "Coincidencia temática directa en asunto: '{$cleanPostSubject}'";
                } else {
                    similar_text($cleanSubject, $cleanPostSubject, $perc);
                    if ($perc >= 80.0) {
                        $score += 20;
                        $evidences[] = "Alta similitud en asunto (" . round($perc, 1) . "%)";
                    }
                }
            }

            // 4. Proximidad Temporal
            $hoursDiff = $post->created_at ? abs(now()->diffInHours($post->created_at)) : 999;
            if ($hoursDiff <= 72) {
                $score += 15;
                $evidences[] = "Comunicado original recibido hace {$hoursDiff} horas (<= 72h)";
            } elseif ($hoursDiff <= 168) {
                $score += 5;
                $evidences[] = "Comunicado original recibido hace {$hoursDiff} horas (<= 7 días)";
            }

            if ($score > $highestScore) {
                $highestScore = $score;
                $bestMatch = $post;
                $bestEvidences = $evidences;
            }
        }

        // Evaluar nivel de confianza
        $isConfident = $highestScore >= 70 && $bestMatch !== null;
        $isAmbiguous = $highestScore >= 40 && $highestScore < 70 && $bestMatch !== null;

        $detectedDiff = [];
        if ($bestMatch) {
            $detectedDiff = $this->calculateDiff($bestMatch, $incomingData);
        }

        return [
            'has_match'         => $bestMatch !== null,
            'is_confident'      => $isConfident,
            'is_ambiguous'      => $isAmbiguous,
            'is_spoofed'        => $isSpoofed,
            'target_post'       => $bestMatch,
            'correlation_score' => $highestScore,
            'evidences'         => $bestEvidences,
            'detected_diff'     => $detectedDiff,
        ];
    }

    /**
     * Calcula las diferencias esenciales entre el post existente y los datos entrantes.
     */
    protected function calculateDiff(Post $post, array $incomingData): array
    {
        $diff = [
            'original_title' => $post->title,
            'incoming_subject' => $incomingData['subject'] ?? '',
            'original_status' => $post->status,
            'is_published' => $post->is_published,
        ];

        // Detección de posibles cambios de fecha
        if (preg_match('/\b(?:el\s+\d{1,2}\s+de\s+[a-z]+(?:\s+de\s+\d{4})?)\b/iu', (string) ($incomingData['body'] ?? ''), $m)) {
            $diff['new_date_mentioned'] = $m[0];
        }

        // Detección de posibles cambios de recinto
        if (preg_match('/\b(?:en\s+la\s+sala\s+[a-zA-Z0-9\s]+|en\s+el\s+[a-zA-Z0-9\s]+(?:club|arena|estadio|teatro))\b/iu', (string) ($incomingData['body'] ?? ''), $mVenue)) {
            $diff['new_venue_mentioned'] = trim($mVenue[0]);
        }

        return $diff;
    }
}
