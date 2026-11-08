<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\SubmissionMuroInvitationMail;
use App\Models\EmailLog;
use App\Models\MarketingContact;
use App\Models\NewRelease;
use App\Models\SubmissionMuroFollowup;
use App\Models\Talent;
use App\Models\TalentSubscription;
use App\Models\TrackSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

final class SubmissionFollowupService
{
    public function campaignKey(): string
    {
        return (string) config('muro_outreach.campaign_key', SubmissionMuroFollowup::CAMPAIGN_DEFAULT);
    }

    public function delayDays(): int
    {
        return max(1, (int) config('muro_outreach.delay_days', 3));
    }

    public function isCampaignEnabled(): bool
    {
        return (bool) config('muro_outreach.enabled', false);
    }

    public function configuredCtaUrl(): ?string
    {
        $raw = config('muro_outreach.cta_url');
        if ($raw === null) {
            return null;
        }

        $url = trim((string) $raw);
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return url($url);
        }

        return $url;
    }

    /**
     * Comprueba si existe una URL comercial aprobada y que NO apunte al registro
     * directo (/talents/register o /talentos/register).
     */
    public function hasApprovedCtaUrl(): bool
    {
        $url = $this->configuredCtaUrl();
        if ($url === null || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        if (str_contains($path, '/talents/register') || str_contains($path, '/talentos/register')) {
            return false;
        }

        return true;
    }

    /**
     * Registra o sincroniza el seguimiento de una banda tras la primera publicación efectiva.
     * Nunca lanza excepción hacia el flujo de publicación para no interferir con la Fase 1.
     */
    public function syncAfterPublication(TrackSubmission $submission): ?SubmissionMuroFollowup
    {
        if (! Schema::hasTable('submission_muro_followups')) {
            return null;
        }

        if ($submission->published_at === null || ! $submission->new_release_id) {
            return null;
        }

        try {
            return DB::transaction(function () use ($submission): ?SubmissionMuroFollowup {
                $email = trim((string) $submission->contact_email);
                $normalized = SubmissionMuroFollowup::normalizeEmail($email);
                $campaignKey = $this->campaignKey();

                if ($normalized === '') {
                    return null;
                }

                /** @var SubmissionMuroFollowup|null $existing */
                $existing = SubmissionMuroFollowup::query()
                    ->where('email_normalized', $normalized)
                    ->where('campaign_key', $campaignKey)
                    ->lockForUpdate()
                    ->first();

                // Si ya existe un seguimiento activo, pausado, cancelado o enviado para este contacto,
                // se conserva sin duplicar ni reprogramar (deduplicación por contacto y campaña).
                if ($existing && in_array($existing->status, [
                    SubmissionMuroFollowup::STATUS_SCHEDULED,
                    SubmissionMuroFollowup::STATUS_PAUSED,
                    SubmissionMuroFollowup::STATUS_CANCELLED,
                    SubmissionMuroFollowup::STATUS_SENDING,
                    SubmissionMuroFollowup::STATUS_SENT,
                    SubmissionMuroFollowup::STATUS_FAILED,
                    SubmissionMuroFollowup::STATUS_UNKNOWN,
                ], true)) {
                    return $existing;
                }

                $release = NewRelease::query()->find($submission->new_release_id);
                $consentedSubmission = $this->findConsentedSubmissionForEmail($normalized, $submission);
                $publishedAt = $submission->published_at ?? now();
                $scheduledFor = $publishedAt->copy()->addDays($this->delayDays());

                [$initialStatus, $reason, $detail] = $this->determineInitialState(
                    $email,
                    $normalized,
                    $submission,
                    $consentedSubmission,
                    $release
                );

                $payload = [
                    'contact_email' => $email,
                    'band_name' => $submission->band_name,
                    'track_submission_id' => ($consentedSubmission ?? $submission)->id,
                    'new_release_id' => $submission->new_release_id,
                    'status' => $initialStatus,
                    'status_reason' => $reason,
                    'status_detail' => $detail,
                    'first_published_at' => $existing?->first_published_at ?? $publishedAt,
                    'scheduled_for' => $initialStatus === SubmissionMuroFollowup::STATUS_SCHEDULED ? $scheduledFor : null,
                ];

                if ($existing) {
                    $existing->forceFill($payload)->save();

                    return $existing->fresh();
                }

                return SubmissionMuroFollowup::query()->create(array_merge([
                    'campaign_key' => $campaignKey,
                    'email_normalized' => $normalized,
                ], $payload));
            });
        } catch (Throwable $e) {
            Log::error('Error al sincronizar seguimiento de Muro del Rock', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Evalúa todas las reglas antes de enviar (o en modo simulación/vista previa).
     *
     * @return array{
     *     eligible_recipient: bool,
     *     can_send_now: bool,
     *     reason_code: string,
     *     reason_text: string,
     *     should_mark_skipped: bool
     * }
     */
    public function evaluateFollowup(SubmissionMuroFollowup $followup): array
    {
        $email = trim((string) $followup->contact_email);
        $normalized = $followup->email_normalized ?: SubmissionMuroFollowup::normalizeEmail($email);

        if ($followup->status === SubmissionMuroFollowup::STATUS_SENT || $followup->sent_at !== null) {
            return [
                'eligible_recipient' => false,
                'can_send_now' => false,
                'reason_code' => 'already_sent',
                'reason_text' => 'La invitación ya fue enviada a este contacto en esta campaña.',
                'should_mark_skipped' => false,
            ];
        }

        if (in_array($followup->status, [SubmissionMuroFollowup::STATUS_SENDING, SubmissionMuroFollowup::STATUS_UNKNOWN], true)) {
            return [
                'eligible_recipient' => false,
                'can_send_now' => false,
                'reason_code' => 'uncertain_or_sending',
                'reason_text' => 'Envío en curso o con estado de registro incierto; bloqueado contra reenvío automático.',
                'should_mark_skipped' => false,
            ];
        }

        if ($followup->status === SubmissionMuroFollowup::STATUS_CANCELLED) {
            return [
                'eligible_recipient' => false,
                'can_send_now' => false,
                'reason_code' => SubmissionMuroFollowup::REASON_ADMIN_CANCELLED,
                'reason_text' => 'Seguimiento cancelado.',
                'should_mark_skipped' => false,
            ];
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return [
                'eligible_recipient' => false,
                'can_send_now' => false,
                'reason_code' => SubmissionMuroFollowup::REASON_INVALID_EMAIL,
                'reason_text' => 'Dirección de correo electrónico inválida.',
                'should_mark_skipped' => true,
            ];
        }

        // 1. Verificar baja antes que cualquier otra cosa
        if ($this->isEmailUnsubscribed($normalized)) {
            return [
                'eligible_recipient' => false,
                'can_send_now' => false,
                'reason_code' => SubmissionMuroFollowup::REASON_UNSUBSCRIBED,
                'reason_text' => 'El contacto figura dado de baja en las preferencias de correo.',
                'should_mark_skipped' => true,
            ];
        }

        // 2. Verificar consentimiento explícito registrado
        $consentedSubmission = $this->findConsentedSubmissionForEmail($normalized, $followup->trackSubmission);
        if (! $consentedSubmission) {
            $isHistoric = $this->isHistoricContactWithoutConsentRecord($normalized, $followup->trackSubmission);
            $code = $isHistoric
                ? SubmissionMuroFollowup::REASON_HISTORIC_NO_CONSENT
                : SubmissionMuroFollowup::REASON_MISSING_CONSENT;

            return [
                'eligible_recipient' => false,
                'can_send_now' => false,
                'reason_code' => $code,
                'reason_text' => $isHistoric
                    ? 'Contacto histórico previo a la casilla de consentimiento (excluido).'
                    : 'La banda no aceptó la casilla opcional para recibir información comercial.',
                'should_mark_skipped' => true,
            ];
        }

        // 3. Verificar que no tenga ya un plan de pago activo en el Muro del Rock
        if ($this->hasActivePaidPlan($normalized)) {
            return [
                'eligible_recipient' => false,
                'can_send_now' => false,
                'reason_code' => SubmissionMuroFollowup::REASON_ACTIVE_PAID_PLAN,
                'reason_text' => 'La banda ya tiene un plan de pago activo en el Muro del Rock.',
                'should_mark_skipped' => true,
            ];
        }

        // 4. Verificar que la publicación asociada siga activa y públicamente accesible
        $release = $followup->new_release_id ? NewRelease::query()->find($followup->new_release_id) : null;
        if (! $release || ! $release->isPubliclyAccessible()) {
            return [
                'eligible_recipient' => false,
                'can_send_now' => false,
                'reason_code' => SubmissionMuroFollowup::REASON_RELEASE_WITHDRAWN,
                'reason_text' => 'La publicación asociada fue retirada, desactivada o está bajo embargo.',
                'should_mark_skipped' => true,
            ];
        }

        if ($followup->status === SubmissionMuroFollowup::STATUS_PAUSED) {
            return [
                'eligible_recipient' => true,
                'can_send_now' => false,
                'reason_code' => SubmissionMuroFollowup::REASON_ADMIN_PAUSED,
                'reason_text' => 'Destinatario válido, pero el seguimiento está pausado manualmente.',
                'should_mark_skipped' => false,
            ];
        }

        // 5. Verificar que hayan transcurrido los 3 días desde la primera publicación efectiva
        if ($followup->scheduled_for && $followup->scheduled_for->isFuture()) {
            return [
                'eligible_recipient' => true,
                'can_send_now' => false,
                'reason_code' => SubmissionMuroFollowup::REASON_READY_WAIT_WINDOW,
                'reason_text' => 'En espera del plazo de 3 días (previsto para ' . $followup->scheduled_for->format('d/m/Y H:i') . ').',
                'should_mark_skipped' => false,
            ];
        }

        // 6. Bloqueo de seguridad hasta que exista URL comercial aprobada y campaña activa
        if (! $this->hasApprovedCtaUrl()) {
            return [
                'eligible_recipient' => true,
                'can_send_now' => false,
                'reason_code' => 'cta_url_pending',
                'reason_text' => 'Destinatario listo, pero el envío está bloqueado hasta configurar una URL comercial aprobada (MURO_FOLLOWUP_CTA_URL).',
                'should_mark_skipped' => false,
            ];
        }

        if (! $this->isCampaignEnabled()) {
            return [
                'eligible_recipient' => true,
                'can_send_now' => false,
                'reason_code' => 'campaign_disabled',
                'reason_text' => 'Destinatario listo, pero la campaña permanece desactivada por defecto (MURO_FOLLOWUP_ENABLED=false).',
                'should_mark_skipped' => false,
            ];
        }

        return [
            'eligible_recipient' => true,
            'can_send_now' => true,
            'reason_code' => 'ready_to_send',
            'reason_text' => 'Cumple consentimiento, sin baja, sin plan de pago, publicación activa y plazo de 3 días cumplido.',
            'should_mark_skipped' => false,
        ];
    }

    /**
     * Procesa los seguimientos vencidos o simula qué ocurriría sin enviar nada.
     *
     * @return array{
     *     dry_run: bool,
     *     campaign_enabled: bool,
     *     has_approved_cta_url: bool,
     *     reply_to: string,
     *     sent: int,
     *     skipped: int,
     *     failed: int,
     *     blocked_or_waiting: int,
     *     items: array<int, array<string, mixed>>
     * }
     */
    public function processDueFollowups(bool $dryRun = false): array
    {
        $summary = [
            'dry_run' => $dryRun,
            'campaign_enabled' => $this->isCampaignEnabled(),
            'has_approved_cta_url' => $this->hasApprovedCtaUrl(),
            'reply_to' => SubmissionMuroInvitationMail::configuredReplyToAddress(),
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
            'blocked_or_waiting' => 0,
            'items' => [],
        ];

        if (! Schema::hasTable('submission_muro_followups')) {
            return $summary;
        }

        $followups = SubmissionMuroFollowup::query()
            ->with(['trackSubmission', 'release'])
            ->where('campaign_key', $this->campaignKey())
            ->orderBy('id')
            ->get();

        foreach ($followups as $followup) {
            $evaluation = $this->evaluateFollowup($followup);

            if ($dryRun) {
                if ($evaluation['should_mark_skipped'] || $followup->status === SubmissionMuroFollowup::STATUS_SKIPPED) {
                    $summary['skipped']++;
                } elseif ($evaluation['can_send_now']) {
                    $summary['sent']++;
                } else {
                    $summary['blocked_or_waiting']++;
                }

                $summary['items'][] = [
                    'id' => $followup->id,
                    'band_name' => $followup->band_name,
                    'contact_email' => $followup->contact_email,
                    'status' => $followup->status,
                    'scheduled_for' => $followup->scheduled_for?->format('d/m/Y H:i'),
                    'eligible_recipient' => $evaluation['eligible_recipient'],
                    'can_send_now' => $evaluation['can_send_now'],
                    'reason_code' => $evaluation['reason_code'],
                    'reason_text' => $evaluation['reason_text'],
                ];
                continue;
            }

            if ($followup->status !== SubmissionMuroFollowup::STATUS_SCHEDULED) {
                continue;
            }

            if ($evaluation['should_mark_skipped']) {
                $followup->forceFill([
                    'status' => SubmissionMuroFollowup::STATUS_SKIPPED,
                    'status_reason' => $evaluation['reason_code'],
                    'status_detail' => $evaluation['reason_text'],
                ])->save();
                $summary['skipped']++;
                continue;
            }

            if (! $evaluation['can_send_now']) {
                $summary['blocked_or_waiting']++;
                continue;
            }

            $result = $this->dispatchInvitation($followup, false);
            if ($result === SubmissionMuroFollowup::STATUS_SENT) {
                $summary['sent']++;
            } elseif ($result === SubmissionMuroFollowup::STATUS_SKIPPED) {
                $summary['skipped']++;
            } elseif ($result === SubmissionMuroFollowup::STATUS_FAILED) {
                $summary['failed']++;
            } else {
                $summary['blocked_or_waiting']++;
            }
        }

        return $summary;
    }

    /**
     * Pausa un seguimiento programado o fallido.
     *
     * @return array{ok: bool, message: string}
     */
    public function pause(SubmissionMuroFollowup $followup): array
    {
        if (! in_array($followup->status, [SubmissionMuroFollowup::STATUS_SCHEDULED, SubmissionMuroFollowup::STATUS_FAILED], true)) {
            return ['ok' => false, 'message' => 'Solo se pueden pausar seguimientos programados o con fallo pendiente.'];
        }

        $followup->forceFill([
            'status' => SubmissionMuroFollowup::STATUS_PAUSED,
            'status_reason' => SubmissionMuroFollowup::REASON_ADMIN_PAUSED,
            'status_detail' => 'Pausado manualmente desde el panel.',
            'paused_at' => now(),
        ])->save();

        return ['ok' => true, 'message' => 'Seguimiento pausado correctamente.'];
    }

    /**
     * Reanuda un seguimiento pausado, sin permitir nunca saltarse consentimiento ni bajas.
     *
     * @return array{ok: bool, message: string}
     */
    public function resume(SubmissionMuroFollowup $followup): array
    {
        if ($followup->status !== SubmissionMuroFollowup::STATUS_PAUSED) {
            return ['ok' => false, 'message' => 'Solo se pueden reanudar seguimientos que estén pausados.'];
        }

        $normalized = $followup->email_normalized ?: SubmissionMuroFollowup::normalizeEmail((string) $followup->contact_email);

        if ($this->isEmailUnsubscribed($normalized)) {
            $followup->forceFill([
                'status' => SubmissionMuroFollowup::STATUS_SKIPPED,
                'status_reason' => SubmissionMuroFollowup::REASON_UNSUBSCRIBED,
                'status_detail' => 'No se puede reanudar: el contacto está dado de baja.',
            ])->save();

            return ['ok' => false, 'message' => 'No se puede reanudar un seguimiento cuyo contacto se ha dado de baja.'];
        }

        if (! $this->findConsentedSubmissionForEmail($normalized, $followup->trackSubmission)) {
            $followup->forceFill([
                'status' => SubmissionMuroFollowup::STATUS_SKIPPED,
                'status_reason' => SubmissionMuroFollowup::REASON_MISSING_CONSENT,
                'status_detail' => 'No se puede reanudar: el contacto no tiene consentimiento registrado.',
            ])->save();

            return ['ok' => false, 'message' => 'No se puede reanudar un seguimiento sin consentimiento explícito de la banda.'];
        }

        if ($this->hasActivePaidPlan($normalized)) {
            $followup->forceFill([
                'status' => SubmissionMuroFollowup::STATUS_SKIPPED,
                'status_reason' => SubmissionMuroFollowup::REASON_ACTIVE_PAID_PLAN,
                'status_detail' => 'No se puede reanudar: la banda ya tiene un plan de pago activo.',
            ])->save();

            return ['ok' => false, 'message' => 'La banda ya cuenta con un plan de pago activo en el Muro del Rock.'];
        }

        $release = $followup->new_release_id ? NewRelease::query()->find($followup->new_release_id) : null;
        if (! $release || ! $release->isPubliclyAccessible()) {
            $followup->forceFill([
                'status' => SubmissionMuroFollowup::STATUS_SKIPPED,
                'status_reason' => SubmissionMuroFollowup::REASON_RELEASE_WITHDRAWN,
                'status_detail' => 'No se puede reanudar: la publicación ya no está activa.',
            ])->save();

            return ['ok' => false, 'message' => 'No se puede reanudar porque la publicación asociada ya no está disponible públicamente.'];
        }

        $baseDate = $followup->first_published_at ?? now();
        $scheduledFor = $followup->scheduled_for ?? $baseDate->copy()->addDays($this->delayDays());

        $followup->forceFill([
            'status' => SubmissionMuroFollowup::STATUS_SCHEDULED,
            'status_reason' => SubmissionMuroFollowup::REASON_READY_WAIT_WINDOW,
            'status_detail' => 'Reanudado manualmente por administración.',
            'scheduled_for' => $scheduledFor,
            'paused_at' => null,
        ])->save();

        return ['ok' => true, 'message' => 'Seguimiento reanudado y programado correctamente.'];
    }

    /**
     * Cancela definitivamente un seguimiento pendiente.
     *
     * @return array{ok: bool, message: string}
     */
    public function cancel(SubmissionMuroFollowup $followup): array
    {
        if (in_array($followup->status, [SubmissionMuroFollowup::STATUS_SENT, SubmissionMuroFollowup::STATUS_SENDING, SubmissionMuroFollowup::STATUS_UNKNOWN], true)) {
            return ['ok' => false, 'message' => 'No se puede cancelar un seguimiento que ya fue enviado o está en estado incierto.'];
        }

        $followup->forceFill([
            'status' => SubmissionMuroFollowup::STATUS_CANCELLED,
            'status_reason' => SubmissionMuroFollowup::REASON_ADMIN_CANCELLED,
            'status_detail' => 'Cancelado manualmente desde el panel.',
            'cancelled_at' => now(),
        ])->save();

        return ['ok' => true, 'message' => 'Seguimiento cancelado. No se enviará la invitación.'];
    }

    /**
     * Reintenta únicamente un fallo de envío confirmado ('failed').
     * Respeta estrictamente consentimiento, bajas, vigencia de la publicación y configuración.
     *
     * @return array{ok: bool, status: string, message: string}
     */
    public function retryFailed(SubmissionMuroFollowup $followup): array
    {
        $fresh = SubmissionMuroFollowup::query()->find($followup->id);

        if (! $fresh || $fresh->status !== SubmissionMuroFollowup::STATUS_FAILED) {
            return [
                'ok' => false,
                'status' => $fresh?->status ?? 'missing',
                'message' => 'Solo se puede reintentar un seguimiento con fallo de envío confirmado.',
            ];
        }

        $result = $this->dispatchInvitation($fresh, true);

        return match ($result) {
            SubmissionMuroFollowup::STATUS_SENT => [
                'ok' => true,
                'status' => $result,
                'message' => 'Invitación al Muro del Rock reenviada correctamente.',
            ],
            SubmissionMuroFollowup::STATUS_SKIPPED => [
                'ok' => false,
                'status' => $result,
                'message' => 'El seguimiento quedó excluido tras verificar sus requisitos: ' . ($fresh->fresh()?->reasonLabel() ?? ''),
            ],
            'cta_url_pending' => [
                'ok' => false,
                'status' => $result,
                'message' => 'Envío bloqueado: aún no se ha configurado una URL comercial aprobada (MURO_FOLLOWUP_CTA_URL).',
            ],
            'campaign_disabled' => [
                'ok' => false,
                'status' => $result,
                'message' => 'Envío bloqueado: la campaña permanece desactivada (MURO_FOLLOWUP_ENABLED=false).',
            ],
            default => [
                'ok' => false,
                'status' => $result,
                'message' => 'El reintento falló nuevamente. Revisa el error registrado.',
            ],
        };
    }

    /**
     * Genera el HTML de vista previa de la invitación sin enviar nada ni tocar estados.
     */
    public function renderPreviewHtml(?SubmissionMuroFollowup $followup = null): string
    {
        $submission = $followup?->trackSubmission ?? new TrackSubmission([
            'band_name' => $followup?->band_name ?: 'Los Ejemplo',
            'song_title' => 'Canción de Prueba',
            'contact_email' => $followup?->contact_email ?: 'banda@example.com',
            'status' => 'approved',
        ]);

        $release = $followup?->release ?? new NewRelease([
            'title' => $submission->song_title ?: 'Canción de Prueba',
            'slug' => 'cancion-de-prueba-los-ejemplo',
            'artist_name' => $submission->band_name ?: 'Los Ejemplo',
            'is_active' => true,
        ]);

        $sampleFollowup = $followup ?? new SubmissionMuroFollowup([
            'band_name' => $submission->band_name ?: 'Los Ejemplo',
            'contact_email' => $submission->contact_email ?: 'banda@example.com',
            'email_normalized' => SubmissionMuroFollowup::normalizeEmail((string) ($submission->contact_email ?: 'banda@example.com')),
        ]);

        $ctaUrl = $this->hasApprovedCtaUrl()
            ? (string) $this->configuredCtaUrl()
            : url('/muro-del-rock#planes-pendientes-de-aprobacion');

        $unsubscribeUrl = url('/baja/vista-previa-sin-sesion');

        $mail = new SubmissionMuroInvitationMail(
            $sampleFollowup,
            $submission,
            $release,
            $ctaUrl,
            $unsubscribeUrl
        );

        return $mail->render();
    }

    /**
     * Marca como cancelados por baja todos los seguimientos pendientes de un correo.
     */
    public function cancelPendingForUnsubscribedEmail(string $email): int
    {
        if (! Schema::hasTable('submission_muro_followups')) {
            return 0;
        }

        $normalized = SubmissionMuroFollowup::normalizeEmail($email);
        if ($normalized === '') {
            return 0;
        }

        return SubmissionMuroFollowup::query()
            ->where('email_normalized', $normalized)
            ->whereIn('status', [
                SubmissionMuroFollowup::STATUS_SCHEDULED,
                SubmissionMuroFollowup::STATUS_PAUSED,
                SubmissionMuroFollowup::STATUS_FAILED,
            ])
            ->update([
                'status' => SubmissionMuroFollowup::STATUS_CANCELLED,
                'status_reason' => SubmissionMuroFollowup::REASON_UNSUBSCRIBED,
                'status_detail' => 'Cancelado automáticamente tras darse de baja el contacto.',
                'cancelled_at' => now(),
            ]);
    }

    /**
     * Ejecuta el envío real con reserva atómica contra ejecuciones simultáneas.
     */
    private function dispatchInvitation(SubmissionMuroFollowup $followup, bool $isRetry): string
    {
        $evaluation = $this->evaluateFollowup($followup);

        if ($evaluation['should_mark_skipped']) {
            $followup->forceFill([
                'status' => SubmissionMuroFollowup::STATUS_SKIPPED,
                'status_reason' => $evaluation['reason_code'],
                'status_detail' => $evaluation['reason_text'],
            ])->save();

            return SubmissionMuroFollowup::STATUS_SKIPPED;
        }

        if (! $evaluation['can_send_now']) {
            return $evaluation['reason_code'];
        }

        $allowedCurrentStatuses = $isRetry
            ? [SubmissionMuroFollowup::STATUS_FAILED]
            : [SubmissionMuroFollowup::STATUS_SCHEDULED];

        // Reserva atómica: evita envíos duplicados si dos procesos/crons coinciden.
        $claimed = SubmissionMuroFollowup::query()
            ->whereKey($followup->id)
            ->whereIn('status', $allowedCurrentStatuses)
            ->update([
                'status' => SubmissionMuroFollowup::STATUS_SENDING,
                'last_error' => null,
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            return (string) SubmissionMuroFollowup::query()->whereKey($followup->id)->value('status');
        }

        $fresh = SubmissionMuroFollowup::query()->with(['trackSubmission', 'release'])->find($followup->id);
        $submission = $fresh?->trackSubmission
            ?? $this->findConsentedSubmissionForEmail($fresh?->email_normalized ?? '', null);
        $release = $fresh?->release;

        if (! $fresh || ! $submission || ! $release || ! $release->isPubliclyAccessible()) {
            SubmissionMuroFollowup::query()->whereKey($followup->id)->update([
                'status' => SubmissionMuroFollowup::STATUS_SKIPPED,
                'status_reason' => SubmissionMuroFollowup::REASON_RELEASE_WITHDRAWN,
                'status_detail' => 'La publicación dejó de ser accesible justo antes del envío.',
            ]);

            return SubmissionMuroFollowup::STATUS_SKIPPED;
        }

        $email = trim((string) $fresh->contact_email);
        $token = MarketingContact::getUnsubscribeTokenForEmail($email);
        $unsubscribeUrl = URL::signedRoute('marketing.unsubscribe', ['token' => $token]);
        $ctaUrl = (string) $this->configuredCtaUrl();

        $mail = new SubmissionMuroInvitationMail($fresh, $submission, $release, $ctaUrl, $unsubscribeUrl);

        try {
            $body = $mail->render();
            Mail::to($email)->send($mail);
        } catch (Throwable $e) {
            Log::error('Fallo confirmado al enviar invitación al Muro del Rock', [
                'followup_id' => $fresh->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            try {
                SubmissionMuroFollowup::query()->whereKey($fresh->id)->update([
                    'status' => SubmissionMuroFollowup::STATUS_FAILED,
                    'status_reason' => SubmissionMuroFollowup::REASON_SEND_FAILED,
                    'status_detail' => 'Fallo en el envío SMTP (reintentable).',
                    'last_error' => Str::limit($e->getMessage(), 500),
                ]);

                EmailLog::query()->create([
                    'track_submission_id' => $submission->id,
                    'to_email' => $email,
                    'subject' => $mail->envelope()->subject,
                    'body' => 'Fallo de envío (Invitación Muro del Rock): ' . Str::limit($e->getMessage(), 500),
                    'status' => 'failed',
                ]);
            } catch (Throwable $inner) {
                Log::critical('No se pudo registrar el fallo de invitación al Muro del Rock', [
                    'followup_id' => $fresh->id,
                    'error' => $inner->getMessage(),
                ]);
            }

            return SubmissionMuroFollowup::STATUS_FAILED;
        }

        // El correo ya se entregó al transporte; un error al guardar el log NO debe provocar reenvío.
        try {
            EmailLog::query()->create([
                'track_submission_id' => $submission->id,
                'to_email' => $email,
                'subject' => $mail->envelope()->subject,
                'body' => $body,
                'status' => 'sent',
            ]);

            SubmissionMuroFollowup::query()->whereKey($fresh->id)->update([
                'status' => SubmissionMuroFollowup::STATUS_SENT,
                'status_reason' => SubmissionMuroFollowup::REASON_SENT_OK,
                'status_detail' => 'Invitación enviada correctamente.',
                'sent_at' => now(),
                'last_error' => null,
            ]);
        } catch (Throwable $e) {
            Log::critical('Invitación al Muro enviada pero falló el registro local; queda en unknown sin reenvío automático', [
                'followup_id' => $fresh->id,
                'error' => $e->getMessage(),
            ]);

            try {
                SubmissionMuroFollowup::query()->whereKey($fresh->id)->update([
                    'status' => SubmissionMuroFollowup::STATUS_UNKNOWN,
                    'status_reason' => SubmissionMuroFollowup::REASON_LOG_FAILED,
                    'status_detail' => 'Correo enviado, pero falló el registro local; no reenviar automáticamente.',
                    'last_error' => Str::limit($e->getMessage(), 400),
                ]);
            } catch (Throwable) {
                // Permanece en 'sending', que tampoco es reintentable.
            }

            return SubmissionMuroFollowup::STATUS_UNKNOWN;
        }

        return SubmissionMuroFollowup::STATUS_SENT;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function determineInitialState(
        string $email,
        string $normalizedEmail,
        TrackSubmission $triggerSubmission,
        ?TrackSubmission $consentedSubmission,
        ?NewRelease $release
    ): array {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return [
                SubmissionMuroFollowup::STATUS_SKIPPED,
                SubmissionMuroFollowup::REASON_INVALID_EMAIL,
                'Dirección de correo electrónico no válida.',
            ];
        }

        if ($this->isEmailUnsubscribed($normalizedEmail)) {
            return [
                SubmissionMuroFollowup::STATUS_SKIPPED,
                SubmissionMuroFollowup::REASON_UNSUBSCRIBED,
                'El contacto figura dado de baja en las comunicaciones.',
            ];
        }

        if (! $consentedSubmission) {
            $isHistoric = $this->isHistoricContactWithoutConsentRecord($normalizedEmail, $triggerSubmission);

            return [
                SubmissionMuroFollowup::STATUS_SKIPPED,
                $isHistoric ? SubmissionMuroFollowup::REASON_HISTORIC_NO_CONSENT : SubmissionMuroFollowup::REASON_MISSING_CONSENT,
                $isHistoric
                    ? 'Contacto histórico previo a la casilla de consentimiento (excluido).'
                    : 'No marcó la casilla opcional de consentimiento en el formulario.',
            ];
        }

        if ($this->hasActivePaidPlan($normalizedEmail)) {
            return [
                SubmissionMuroFollowup::STATUS_SKIPPED,
                SubmissionMuroFollowup::REASON_ACTIVE_PAID_PLAN,
                'La banda ya cuenta con un plan de pago activo en el Muro del Rock.',
            ];
        }

        if (! $release || ! $release->isPubliclyAccessible()) {
            return [
                SubmissionMuroFollowup::STATUS_SKIPPED,
                SubmissionMuroFollowup::REASON_RELEASE_WITHDRAWN,
                'La publicación asociada no está accesible públicamente.',
            ];
        }

        return [
            SubmissionMuroFollowup::STATUS_SCHEDULED,
            SubmissionMuroFollowup::REASON_READY_WAIT_WINDOW,
            'Programado para 3 días después de la primera publicación efectiva.',
        ];
    }

    private function isEmailUnsubscribed(string $normalizedEmail): bool
    {
        if (! Schema::hasTable('marketing_contacts')) {
            return false;
        }

        return MarketingContact::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
            ->where(function ($q): void {
                $q->where('is_active', false)->orWhereNotNull('unsubscribed_at');
            })
            ->exists();
    }

    private function findConsentedSubmissionForEmail(string $normalizedEmail, ?TrackSubmission $preferred): ?TrackSubmission
    {
        if ($preferred && $preferred->hasValidMarketingConsent()) {
            return $preferred;
        }

        if ($normalizedEmail === '' || ! Schema::hasColumn('track_submissions', 'marketing_opt_in')) {
            return null;
        }

        return TrackSubmission::query()
            ->whereRaw('LOWER(TRIM(contact_email)) = ?', [$normalizedEmail])
            ->where('marketing_opt_in', true)
            ->whereNotNull('marketing_opt_in_at')
            ->whereNotNull('marketing_opt_in_source')
            ->whereNotNull('marketing_opt_in_version')
            ->latest('marketing_opt_in_at')
            ->first();
    }

    private function isHistoricContactWithoutConsentRecord(string $normalizedEmail, ?TrackSubmission $submission): bool
    {
        if ($submission && $submission->marketing_opt_in_source === null && $submission->marketing_opt_in_version === null) {
            return true;
        }

        if ($normalizedEmail === '' || ! Schema::hasColumn('track_submissions', 'marketing_opt_in_version')) {
            return true;
        }

        return ! TrackSubmission::query()
            ->whereRaw('LOWER(TRIM(contact_email)) = ?', [$normalizedEmail])
            ->whereNotNull('marketing_opt_in_version')
            ->exists();
    }

    private function hasActivePaidPlan(string $normalizedEmail): bool
    {
        if (! Schema::hasTable('talents')) {
            return false;
        }

        $paidPlans = ['basic', 'pro', 'premium'];

        $hasActiveTalentPlan = Talent::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
            ->whereIn('plan', $paidPlans)
            ->whereIn('subscription_status', ['active', 'grace'])
            ->exists();

        if ($hasActiveTalentPlan) {
            return true;
        }

        if (! Schema::hasTable('talent_subscriptions')) {
            return false;
        }

        return TalentSubscription::query()
            ->where('status', 'active')
            ->whereIn('plan', $paidPlans)
            ->where(function ($q): void {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
            })
            ->whereHas('talent', function ($q) use ($normalizedEmail): void {
                $q->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail]);
            })
            ->exists();
    }
}

