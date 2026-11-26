<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExternalProgramDelivery;
use App\Models\MasterProgram;
use App\Models\MasterProgramDeliverySender;
use App\Services\ExternalPrograms\ExternalDeliveryLinkDetector;
use App\Services\ExternalPrograms\ExternalProgramTelegramNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

final class ExternalProgramDeliveryController extends Controller
{
    public function storeManual(
        Request $request,
        ExternalDeliveryLinkDetector $linkDetector,
        ExternalProgramTelegramNotifier $telegramNotifier
    ): RedirectResponse {
        $validated = $request->validate([
            'delivery_url' => ['required', 'url', 'max:1000'],
            'master_program_id' => ['nullable', 'integer', 'exists:master_programs,id'],
            'sender_email' => ['nullable', 'email', 'max:255'],
            'associate_sender' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            'subject' => ['nullable', 'string', 'max:500'],
            'send_telegram' => ['nullable', 'boolean'],
        ], [
            'delivery_url.required' => 'Debes indicar el enlace de entrega.',
            'delivery_url.url' => 'El enlace de entrega debe ser una URL válida (http:// o https://).',
            'sender_email.email' => 'El correo del remitente debe ser una dirección válida.',
        ]);

        $tz = (string) config('app.timezone', 'Europe/Madrid');
        $url = trim((string) $validated['delivery_url']);
        $platform = $linkDetector->detectPlatformFromUrl($url);
        $programId = ! empty($validated['master_program_id']) ? (int) $validated['master_program_id'] : null;
        $senderEmail = ! empty($validated['sender_email']) ? strtolower(trim((string) $validated['sender_email'])) : null;
        $expiresAt = ! empty($validated['expires_at']) ? Carbon::parse((string) $validated['expires_at'], $tz) : null;

        $delivery = ExternalProgramDelivery::query()->create([
            'master_program_id' => $programId,
            'source_type' => ExternalProgramDelivery::SOURCE_MANUAL,
            'sender_email' => $senderEmail,
            'raw_from_email' => $senderEmail,
            'subject' => trim((string) ($validated['subject'] ?? '')) ?: 'Registro manual desde administración',
            'received_at' => now($tz),
            'received_timezone' => $tz,
            'platform' => $platform,
            'delivery_url' => $url,
            'expires_at' => $expiresAt,
            'expiry_source' => $expiresAt !== null ? 'manual' : null,
            'auth_status' => 'not_applicable',
            'auth_reason' => 'manual_admin_entry',
            'identification_status' => $programId !== null ? ExternalProgramDelivery::ID_MANUAL : ExternalProgramDelivery::ID_UNIDENTIFIED,
            'status' => $programId !== null ? ExternalProgramDelivery::STATUS_PENDING : ExternalProgramDelivery::STATUS_NEEDS_REVIEW,
            'incident_reason' => $programId === null ? 'Programa pendiente de identificar.' : null,
            'telegram_status' => ExternalProgramDelivery::TELEGRAM_SKIPPED,
        ]);

        $associatedMsg = '';
        if ($request->boolean('associate_sender') && $programId !== null && $senderEmail !== null) {
            $this->associateSenderWithProgram($programId, $senderEmail);
            $associatedMsg = " Remitente {$senderEmail} asociado al programa.";
        }

        if ($request->boolean('send_telegram')) {
            $telegramNotifier->sendInitialAlert($delivery);
        }

        return redirect()
            ->route('admin.podcast-uploads.index', ['external_panel' => 1])
            ->with('status', 'Entrega externa registrada manualmente.' . $associatedMsg);
    }

    public function assignProgram(Request $request, ExternalProgramDelivery $delivery): RedirectResponse
    {
        $validated = $request->validate([
            'master_program_id' => ['required', 'integer', 'exists:master_programs,id'],
            'associate_sender' => ['nullable', 'boolean'],
        ]);

        $programId = (int) $validated['master_program_id'];
        $updates = [
            'master_program_id' => $programId,
            'identification_status' => ExternalProgramDelivery::ID_MANUAL,
        ];

        if ($delivery->status === ExternalProgramDelivery::STATUS_NEEDS_REVIEW) {
            $updates['status'] = ExternalProgramDelivery::STATUS_PENDING;
        }

        $delivery->forceFill($updates)->save();

        $associatedMsg = '';
        $senderEmail = strtolower(trim((string) ($delivery->sender_email ?: $delivery->raw_from_email)));
        if ($request->boolean('associate_sender') && $senderEmail !== '' && filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            $this->associateSenderWithProgram($programId, $senderEmail);
            $associatedMsg = " Remitente {$senderEmail} guardado como correo alternativo de entrega.";
        }

        return redirect()
            ->route('admin.podcast-uploads.index', ['external_panel' => 1])
            ->with('status', 'Programa asignado a la entrega.' . $associatedMsg);
    }

    public function snooze(Request $request, ExternalProgramDelivery $delivery): RedirectResponse
    {
        if ($delivery->isResolved()) {
            return redirect()
                ->route('admin.podcast-uploads.index', ['external_panel' => 1])
                ->with('status', 'Esta entrega ya fue resuelta.');
        }

        $validated = $request->validate([
            'hours' => ['nullable', 'integer', Rule::in([4, 12, 24, 48, 72])],
            'until' => ['nullable', 'date', 'after:now'],
        ]);

        $tz = $delivery->effectiveTimezone();
        if (! empty($validated['until'])) {
            $snoozedUntil = Carbon::parse((string) $validated['until'], $tz);
        } else {
            $hours = (int) ($validated['hours'] ?? 24);
            $snoozedUntil = now($tz)->addHours($hours);
        }

        $delivery->forceFill([
            'snoozed_until' => $snoozedUntil,
        ])->save();

        return redirect()
            ->route('admin.podcast-uploads.index', ['external_panel' => 1])
            ->with('status', 'Recordatorio aplazado hasta ' . $snoozedUntil->format('d/m/Y H:i') . " ({$tz}).");
    }

    public function markDownloaded(Request $request, ExternalProgramDelivery $delivery): RedirectResponse
    {
        $validated = $request->validate([
            'resolution_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $delivery->forceFill([
            'status' => ExternalProgramDelivery::STATUS_DOWNLOADED,
            'snoozed_until' => null,
            'resolved_at' => now(),
            'resolved_by' => $request->user()?->id,
            'resolution_notes' => trim((string) ($validated['resolution_notes'] ?? '')) ?: 'Confirmado manualmente como descargado.',
        ])->save();

        return redirect()
            ->route('admin.podcast-uploads.index', ['external_panel' => 1])
            ->with('status', 'Entrega marcada como descargada. Los recordatorios se han detenido.');
    }

    public function discard(Request $request, ExternalProgramDelivery $delivery): RedirectResponse
    {
        $validated = $request->validate([
            'resolution_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $delivery->forceFill([
            'status' => ExternalProgramDelivery::STATUS_DISCARDED,
            'snoozed_until' => null,
            'resolved_at' => now(),
            'resolved_by' => $request->user()?->id,
            'resolution_notes' => trim((string) ($validated['resolution_notes'] ?? '')) ?: 'Aviso descartado por administración.',
        ])->save();

        return redirect()
            ->route('admin.podcast-uploads.index', ['external_panel' => 1])
            ->with('status', 'Aviso descartado conservando su trazabilidad.');
    }

    public function retryTelegram(
        ExternalProgramDelivery $delivery,
        ExternalProgramTelegramNotifier $telegramNotifier
    ): RedirectResponse {
        $result = $telegramNotifier->sendInitialAlert($delivery);

        if ($result['ok']) {
            return redirect()
                ->route('admin.podcast-uploads.index', ['external_panel' => 1])
                ->with('status', 'Aviso enviado por Telegram correctamente.');
        }

        return redirect()
            ->route('admin.podcast-uploads.index', ['external_panel' => 1])
            ->with('error', 'No se pudo enviar el aviso por Telegram: ' . ($result['error'] ?? 'Error desconocido.'));
    }

    protected function associateSenderWithProgram(int $programId, string $email): void
    {
        $program = MasterProgram::query()->find($programId);
        if (! $program) {
            return;
        }

        $primaryEmail = strtolower(trim((string) $program->email_notificacion));
        if ($primaryEmail !== '' && $primaryEmail === $email) {
            if (! $program->recognize_delivery_from_notification_email) {
                $program->forceFill(['recognize_delivery_from_notification_email' => true])->save();
            }
            return;
        }

        MasterProgramDeliverySender::query()->updateOrCreate(
            [
                'master_program_id' => $programId,
                'email' => $email,
            ],
            [
                'is_active' => true,
            ]
        );
    }
}
