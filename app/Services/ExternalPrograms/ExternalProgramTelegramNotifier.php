<?php

declare(strict_types=1);

namespace App\Services\ExternalPrograms;

use App\Models\ExternalProgramDelivery;
use App\Models\ExternalProgramSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExternalProgramTelegramNotifier
{
    public function resolveBotToken(): ?string
    {
        $token = trim((string) config('services.external_programs.telegram_bot_token', ''));

        return $token !== '' ? $token : null;
    }

    public function resolveAdminChatId(): ?string
    {
        $fromSetting = ExternalProgramSetting::getValue('telegram_chat_id');
        if ($fromSetting !== null && $fromSetting !== '') {
            return $fromSetting;
        }

        $fromConfig = trim((string) config('services.external_programs.telegram_chat_id', ''));

        return $fromConfig !== '' ? $fromConfig : null;
    }

    /**
     * Envía el aviso inicial por Telegram al destino administrativo confirmado.
     * Nunca usa el correo del productor como destinatario.
     * Si falla, conserva el pendiente y registra el error para mostrarlo en administración.
     *
     * @return array{ok: bool, error: ?string}
     */
    public function sendInitialAlert(ExternalProgramDelivery $delivery): array
    {
        $message = $this->formatAlertMessage($delivery, 'initial');
        $result = $this->dispatchTelegramMessage($message);

        if ($result['ok']) {
            $delivery->forceFill([
                'telegram_status' => ExternalProgramDelivery::TELEGRAM_SENT,
                'telegram_last_error' => null,
                'telegram_notified_at' => now(),
                'initial_notified_at' => $delivery->initial_notified_at ?? now(),
            ])->save();
        } else {
            $delivery->forceFill([
                'telegram_status' => ExternalProgramDelivery::TELEGRAM_FAILED,
                'telegram_last_error' => $result['error'],
            ])->save();
        }

        return $result;
    }

    /**
     * Envía un recordatorio diario o aviso de caducidad por Telegram.
     *
     * @param  'daily'|'expiry'  $type
     * @return array{ok: bool, error: ?string}
     */
    public function sendReminder(ExternalProgramDelivery $delivery, string $type = 'daily'): array
    {
        $message = $this->formatAlertMessage($delivery, $type);
        $result = $this->dispatchTelegramMessage($message);

        if ($result['ok']) {
            $updates = [
                'telegram_status' => ExternalProgramDelivery::TELEGRAM_SENT,
                'telegram_last_error' => null,
                'telegram_notified_at' => now(),
            ];

            if ($type === 'expiry') {
                $updates['expiry_alert_sent_at'] = now();
            } else {
                $updates['last_reminded_at'] = now();
                $updates['reminder_count'] = ((int) $delivery->reminder_count) + 1;
            }

            $delivery->forceFill($updates)->save();
        } else {
            $delivery->forceFill([
                'telegram_status' => ExternalProgramDelivery::TELEGRAM_FAILED,
                'telegram_last_error' => $result['error'],
            ])->save();
        }

        return $result;
    }

    public function formatAlertMessage(ExternalProgramDelivery $delivery, string $kind = 'initial'): string
    {
        $delivery->loadMissing('masterProgram');

        $header = match ($kind) {
            'expiry' => '⏳ <b>Caducidad próxima — Programa recibido</b>',
            'daily' => '🔔 <b>Recordatorio — Programa pendiente de descarga</b>',
            default => '📻 <b>Nuevo programa recibido</b>',
        };

        $program = e($delivery->programDisplayName());
        $sender = e($delivery->sender_email ?: ($delivery->raw_from_email ?: 'Remitente no especificado'));
        $received = e($delivery->formattedReceivedAt());
        $platform = e($delivery->platformLabel());
        $expiry = e($delivery->formattedExpiresAt());
        $status = e($delivery->statusLabel());
        $url = e($delivery->delivery_url);

        $lines = [
            $header,
            '',
            "<b>Programa:</b> {$program}",
            "<b>Remitente:</b> {$sender}",
            "<b>Recibido:</b> {$received}",
            "<b>Plataforma:</b> {$platform}",
            "<b>Caducidad:</b> {$expiry}",
            "<b>Estado:</b> {$status}",
        ];

        if (filled($delivery->incident_reason)) {
            $lines[] = '<b>Observación:</b> ' . e((string) $delivery->incident_reason);
        }

        $lines[] = '';
        $lines[] = "🔗 <a href=\"{$url}\">Abrir enlace de entrega</a>";

        $adminUrl = rtrim((string) config('app.url', ''), '/') . '/admin/podcast-uploads';
        if (str_starts_with($adminUrl, 'http')) {
            $lines[] = '🎛️ <a href="' . e($adminUrl) . '">Ver en Emisiones y podcasts</a>';
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{ok: bool, error: ?string}
     */
    protected function dispatchTelegramMessage(string $htmlMessage): array
    {
        $token = $this->resolveBotToken();
        $chatId = $this->resolveAdminChatId();

        if ($token === null) {
            return [
                'ok' => false,
                'error' => 'TELEGRAM_BOT_TOKEN no está configurado.',
            ];
        }

        if ($chatId === null) {
            return [
                'ok' => false,
                'error' => 'Destino administrativo de Telegram (chat_id) no configurado.',
            ];
        }

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $htmlMessage,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            if ($response->successful() && (bool) $response->json('ok', true)) {
                return ['ok' => true, 'error' => null];
            }

            $desc = (string) ($response->json('description') ?: $response->body() ?: ('HTTP ' . $response->status()));
            $error = 'Telegram API respondió con error: ' . substr($desc, 0, 240);
            Log::warning('[ExternalProgramTelegramNotifier] ' . $error);

            return ['ok' => false, 'error' => $error];
        } catch (Throwable $e) {
            $error = 'Error de conexión con Telegram: ' . substr($e->getMessage(), 0, 240);
            Log::warning('[ExternalProgramTelegramNotifier] ' . $error);

            return ['ok' => false, 'error' => $error];
        }
    }
}

