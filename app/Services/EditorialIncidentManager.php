<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EditorialIncident;
use App\Models\Post;
use Illuminate\Support\Facades\Log;

class EditorialIncidentManager
{
    /**
     * Procesa un incidente editorial según la clasificación y correlación fáctica.
     *
     * @param array $classification Clasificación de EditorialCorrectionClassifier
     * @param array $correlation Correlación de EditorialCorrelator
     * @param array $emailData Datos del correo entrante (message_id, sender, subject, etc.)
     * @return array Resumen de la acción adoptada e identificador del incidente
     */
    public function handleIncident(array $classification, array $correlation, array $emailData): array
    {
        $messageId = (string) ($emailData['message_id'] ?? 'unknown');
        $incidentType = $classification['incident_type'] ?? 'rectification_data';
        $priority = $classification['priority'] ?? 'medium';
        $targetPost = $correlation['target_post'] ?? null;
        $isConfident = (bool) ($correlation['is_confident'] ?? false);
        $isAmbiguous = (bool) ($correlation['is_ambiguous'] ?? false);
        $isSpoofed = (bool) ($correlation['is_spoofed'] ?? false);

        // 1. Manejo de Intento de Suplantación / Remitente Falsificado
        if ($isSpoofed) {
            $incident = $this->createIncidentRecord([
                'post_id'              => $targetPost?->id,
                'source_message_id'    => $messageId,
                'original_message_id'  => $targetPost?->source_message_id ?? null,
                'incident_type'        => 'suspected_impersonation',
                'priority'             => 'high',
                'post_previous_status' => $targetPost?->status ?? 'none',
                'action_taken'         => 'blocked_impersonation',
                'correlation_score'    => $correlation['correlation_score'] ?? 0,
                'correlation_evidence' => $correlation['evidences'] ?? [],
                'detected_diff'        => ['warning' => 'Intento de falsificación detectado. La publicación original no fue alterada.'],
            ]);

            return [
                'action'        => 'blocked_impersonation',
                'incident_id'   => $incident->id,
                'target_post'   => $targetPost,
                'halted'        => false,
                'notes'         => 'Corrección bloqueada por remitente no autenticado (posible suplantación).',
            ];
        }

        // 2. Si no hay post correlacionado o la coincidencia carece de alta certeza (evidencias insuficientes)
        if (!$targetPost || !$isConfident) {
            $incident = $this->createIncidentRecord([
                'post_id'              => $targetPost?->id,
                'source_message_id'    => $messageId,
                'original_message_id'  => $targetPost?->source_message_id ?? null,
                'incident_type'        => $incidentType,
                'priority'             => 'medium',
                'post_previous_status' => $targetPost?->status ?? 'none',
                'action_taken'         => 'retained_ambiguous',
                'correlation_score'    => $correlation['correlation_score'] ?? 0,
                'correlation_evidence' => $correlation['evidences'] ?? [],
                'detected_diff'        => $correlation['detected_diff'] ?? [],
            ]);

            return [
                'action'        => 'retained_ambiguous',
                'incident_id'   => $incident->id,
                'target_post'   => $targetPost,
                'halted'        => false,
                'notes'         => 'Corrección ambigua conservada como borrador independiente para revisión.',
            ];
        }

        $postStatus = $targetPost->status;
        $isPublished = (bool) $targetPost->is_published;

        // 3. Caso: El Post original está en BORRADOR (draft)
        if ($postStatus === 'draft' && !$isPublished) {
            $incident = $this->createIncidentRecord([
                'post_id'              => $targetPost->id,
                'source_message_id'    => $messageId,
                'original_message_id'  => $targetPost->source_message_id ?? null,
                'incident_type'        => $incidentType,
                'priority'             => 'medium',
                'post_previous_status' => 'draft',
                'action_taken'         => 'linked_to_draft',
                'correlation_score'    => $correlation['correlation_score'] ?? 0,
                'correlation_evidence' => $correlation['evidences'] ?? [],
                'detected_diff'        => $correlation['detected_diff'] ?? [],
            ]);

            Log::info("EditorialIncidentManager: Corrección vinculada a borrador ID {$targetPost->id}.", [
                'incident_id' => $incident->id,
            ]);

            return [
                'action'        => 'linked_to_draft',
                'incident_id'   => $incident->id,
                'target_post'   => $targetPost,
                'halted'        => false,
                'notes'         => "Corrección vinculada al borrador existente ID {$targetPost->id}.",
            ];
        }

        // 4. Caso: El Post original está PROGRAMADO (scheduled)
        if ($postStatus === 'scheduled' || ($targetPost->scheduled_at && !$isPublished)) {
            // DETENCIÓN CAUTELAR DE SEGURIDAD
            $previousScheduledAt = $targetPost->scheduled_at ? $targetPost->scheduled_at->toDateTimeString() : 'sin fecha';
            $targetPost->status = 'draft'; // Pasa a borrador para detener la publicación programada
            $targetPost->save();

            $incident = $this->createIncidentRecord([
                'post_id'              => $targetPost->id,
                'source_message_id'    => $messageId,
                'original_message_id'  => $targetPost->source_message_id ?? null,
                'incident_type'        => $incidentType,
                'priority'             => 'high',
                'post_previous_status' => 'scheduled',
                'action_taken'         => 'halted_scheduled_post',
                'correlation_score'    => $correlation['correlation_score'] ?? 0,
                'correlation_evidence' => $correlation['evidences'] ?? [],
                'detected_diff'        => array_merge($correlation['detected_diff'] ?? [], [
                    'halted_reason'       => "Publicación programada (para {$previousScheduledAt}) suspendida cautelarmente por recepción de corrección verificada.",
                    'halted_at'           => now()->toDateTimeString(),
                ]),
            ]);

            Log::warning("EditorialIncidentManager: Publicación programada pausada para post ID {$targetPost->id}.", [
                'incident_id' => $incident->id,
                'reason'      => $classification['notes'],
            ]);

            return [
                'action'        => 'halted_scheduled_post',
                'incident_id'   => $incident->id,
                'target_post'   => $targetPost,
                'halted'        => true,
                'notes'         => "Publicación programada pausada con éxito para post ID {$targetPost->id}.",
            ];
        }

        // 5. Caso: El Post original ya está PUBLICADO (published)
        if ($isPublished || $postStatus === 'published') {
            // NUNCA despublicar automáticamente; registrar incidencia urgente
            $incident = $this->createIncidentRecord([
                'post_id'              => $targetPost->id,
                'source_message_id'    => $messageId,
                'original_message_id'  => $targetPost->source_message_id ?? null,
                'incident_type'        => $incidentType,
                'priority'             => 'urgent',
                'post_previous_status' => 'published',
                'action_taken'         => 'flagged_published_post',
                'correlation_score'    => $correlation['correlation_score'] ?? 0,
                'correlation_evidence' => $correlation['evidences'] ?? [],
                'detected_diff'        => $correlation['detected_diff'] ?? [],
            ]);

            Log::warning("EditorialIncidentManager: Incidencia URGENTE creada sobre post publicado ID {$targetPost->id}.", [
                'incident_id' => $incident->id,
            ]);

            return [
                'action'        => 'flagged_published_post',
                'incident_id'   => $incident->id,
                'target_post'   => $targetPost,
                'halted'        => false,
                'notes'         => "Incidencia urgente generada para noticia publicada ID {$targetPost->id}. Publicación intacta en espera de decisión editorial.",
            ];
        }

        return [
            'action'      => 'retained_ambiguous',
            'incident_id' => null,
            'target_post' => $targetPost,
            'halted'      => false,
            'notes'       => 'Tratamiento por defecto: borrador independiente.',
        ];
    }

    /**
     * Persiste el registro de incidencia si la tabla existe.
     */
    protected function createIncidentRecord(array $data): EditorialIncident
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('editorial_incidents')) {
                return EditorialIncident::create($data);
            }
        } catch (\Throwable $e) {
            Log::error("EditorialIncidentManager: Error al crear EditorialIncident: " . $e->getMessage());
        }

        return new EditorialIncident($data);
    }
}
