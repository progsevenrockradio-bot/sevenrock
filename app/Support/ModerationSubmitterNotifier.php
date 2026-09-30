<?php
declare(strict_types=1);
namespace App\Support;
use App\Mail\ModerationDecisionMail;
use App\Mail\SubmissionStatusUpdated;
use App\Models\EmailLog;
use App\Models\ModerationItem;
use App\Models\TrackSubmission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
/**
 * Avisa al REMITENTE cuando su envio se aprueba o se deniega.
 *
 * ============================================================================
 * Antes: aprobar o denegar solo cambiaba el estado en la base de datos. El que
 * habia enviado la maqueta, el evento, el producto… NO se enteraba de nada.
 *
 * Ahora, al decidir (desde el enlace del correo o desde el panel de moderacion)
 * se le manda su aviso:
 *
 * - Maqueta -> SubmissionStatusUpdated (SU plantilla de siempre, la misma que
 *   manda el panel de envios). El asunto dice aprobada / rechazada.
 * - El resto -> ModerationDecisionMail (plantilla nueva, en el mismo estilo
 *   x-mail::message que las demas): evento, producto, album, muro,
 *   comentario, contacto, afiliado, banda de agencia, contrato,
 *   alta de talento, multimedia…
 *
 * Reglas de seguridad:
 * - Solo avisa de decisiones reales (approved / rejected).
 * - Sin correo de destino, no manda nada.
 * - Si el correo falla, NO rompe la decision: se anota en el log y devuelve false.
 * - Queda registrado en EmailLog igual que los correos del panel.
 * ============================================================================
 */
final class ModerationSubmitterNotifier
{
    /**
     * Devuelve true si se ha enviado el aviso al remitente.
     */
    public static function notify(ModerationItem $item): bool
    {
        if (! in_array((string) $item->status, ['approved', 'rejected'], true)) {
            return false;
        }
        $email = self::destinatario($item);
        if ($email === '') {
            return false;
        }
        $submission = $item->subject instanceof TrackSubmission ? $item->subject : null;
        try {
            $mail = $submission !== null ? new SubmissionStatusUpdated($submission) : new ModerationDecisionMail($item);
            Mail::to($email)->send($mail);
            $registro = [
                'to_email' => $email,
                'subject' => $mail->envelope()->subject,
                'body' => $mail->render(),
                'status' => 'sent',
            ];
            if ($submission !== null) {
                $registro['track_submission_id'] = $submission->id;
            }
            EmailLog::create($registro);
            return true;
        } catch (\Throwable $e) {
            Log::error('Error al avisar al remitente de una decision de moderacion', [
                'error' => $e->getMessage(),
                'moderation_item_id' => $item->id ?? null,
                'type' => $item->type ?? null,
            ]);
            return false;
        }
    }
    /**
     * A quien se le avisa.
     *
     * - Maquetas: igual que el panel -> primero el correo de contacto de la maqueta.
     * - Resto de tipos: primero el correo de quien hizo el envio (submitter_email).
     */
    private static function destinatario(ModerationItem $item): string
    {
        $delContenido = [
            data_get($item->subject, 'contact_email'),
            data_get($item->subject, 'email'),
        ];
        $candidatos = $item->subject instanceof TrackSubmission
            ? array_merge($delContenido, [$item->submitter_email ?? null])
            : array_merge([$item->submitter_email ?? null], $delContenido);
        foreach ($candidatos as $candidato) {
            $texto = trim((string) $candidato);
            if ($texto !== '') {
                return $texto;
            }
        }
        return '';
    }
}
