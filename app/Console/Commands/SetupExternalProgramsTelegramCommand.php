<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ExternalProgramSetting;
use App\Services\ExternalPrograms\ExternalProgramTelegramNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class SetupExternalProgramsTelegramCommand extends Command
{
    protected $signature = 'external-programs:telegram-setup
                            {--expected-bot=SevenRock_Bot : Nombre de usuario esperado del bot (sin @)}
                            {--match-text=Prueba de avisos SevenRock : Texto del mensaje de prueba para identificar el chat de destino}
                            {--send-test : Enviar un único aviso de confirmación de conexión al chat detectado}
                            {--check-only : Comprobar identidad del bot y chat sin guardar ni enviar mensajes}';

    protected $description = 'Verifica el token de @SevenRock_Bot (sin mostrar secretos), localiza el chat de destino por el mensaje de prueba, guarda la configuración y envía un único aviso de conexión';

    public function handle(ExternalProgramTelegramNotifier $notifier): int
    {
        $token = $notifier->resolveBotToken();
        if ($token === null) {
            $this->error('No se encontró EXTERNAL_PROGRAMS_TELEGRAM_BOT_TOKEN (ni TELEGRAM_BOT_TOKEN) configurado en .env.');
            return self::FAILURE;
        }

        $expectedBot = ltrim(trim((string) $this->option('expected-bot')), '@');
        $matchText = trim((string) $this->option('match-text'));
        $checkOnly = (bool) $this->option('check-only');
        $sendTest = (bool) $this->option('send-test');

        // 1. Verificar identidad del bot vía getMe (nunca mostrar el token)
        try {
            $meResponse = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getMe");
        } catch (Throwable $e) {
            $this->error('Error de conexión al consultar getMe en Telegram: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (! $meResponse->successful() || ! (bool) $meResponse->json('ok', false)) {
            $this->error('Telegram rechazó el token al consultar getMe (HTTP ' . $meResponse->status() . ').');
            return self::FAILURE;
        }

        $actualUsername = (string) $meResponse->json('result.username', '');
        $botId = (string) $meResponse->json('result.id', '');
        $this->info("Bot verificado: @{$actualUsername} (id: {$botId})");

        if ($expectedBot !== '' && strcasecmp($actualUsername, $expectedBot) !== 0) {
            $this->error("El token configurado corresponde a @{$actualUsername}, NO a @{$expectedBot}. No se modifica ningún destino.");
            return self::FAILURE;
        }

        // 2. Consultar getUpdates para localizar el chat que envió el mensaje de prueba
        try {
            $updatesResponse = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getUpdates", [
                'limit' => 50,
            ]);
        } catch (Throwable $e) {
            $this->error('Error de conexión al consultar getUpdates en Telegram: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (! $updatesResponse->successful() || ! (bool) $updatesResponse->json('ok', false)) {
            $desc = (string) $updatesResponse->json('description', 'HTTP ' . $updatesResponse->status());
            $this->error('No se pudieron obtener actualizaciones de Telegram: ' . $desc);
            return self::FAILURE;
        }

        $updates = (array) $updatesResponse->json('result', []);
        $matchedChatId = null;
        $matchedChatType = null;
        $matchedChatLabel = null;
        $matchedTextFound = null;

        foreach (array_reverse($updates) as $update) {
            $msg = $update['message'] ?? $update['channel_post'] ?? null;
            if (! is_array($msg)) {
                continue;
            }

            $text = trim((string) ($msg['text'] ?? ''));
            $chat = $msg['chat'] ?? null;
            if (! is_array($chat) || ! isset($chat['id'])) {
                continue;
            }

            if ($matchText !== '' && mb_stripos($text, $matchText) !== false) {
                $matchedChatId = (string) $chat['id'];
                $matchedChatType = (string) ($chat['type'] ?? 'private');
                $matchedChatLabel = trim((string) ($chat['title'] ?? (($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? ''))));
                $matchedTextFound = $text;
                break;
            }
        }

        if ($matchedChatId === null) {
            $this->warn("No se encontró ningún mensaje en getUpdates que contenga: \"{$matchText}\".");
            $this->line('Mensajes inspeccionados: ' . count($updates));
            return self::FAILURE;
        }

        $this->info("Chat de destino identificado: {$matchedChatId} ({$matchedChatType}" . ($matchedChatLabel !== '' ? ", {$matchedChatLabel}" : '') . ')');
        $this->line("Mensaje coincidente: \"{$matchedTextFound}\"");

        if ($checkOnly) {
            return self::SUCCESS;
        }

        // 3. Guardar el destino en la configuración de recepción externa (sin tocar otros bots)
        ExternalProgramSetting::setValue('telegram_chat_id', $matchedChatId);
        $this->info('Destino guardado en external_program_settings (telegram_chat_id).');

        // 4. Enviar un único aviso de conexión si se solicita
        if ($sendTest) {
            $envLabel = (string) config('app.url', 'SevenRockRadio');
            $html = implode("\n", [
                '✅ <b>Conexión confirmada — SevenRockRadio</b>',
                '',
                'Este chat quedó registrado como destino administrativo para avisos de <b>Programas recibidos</b>.',
                '<b>Entorno:</b> ' . e($envLabel),
                '<b>Bot:</b> @' . e($actualUsername),
                '<b>Estado:</b> Solo detección y aviso (sin descargas ni distribución automática).',
            ]);

            try {
                $sendResp = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $matchedChatId,
                    'text' => $html,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);

                if ($sendResp->successful() && (bool) $sendResp->json('ok', false)) {
                    $msgId = (string) $sendResp->json('result.message_id', '');
                    $this->info("Aviso único de conexión enviado correctamente (message_id: {$msgId}).");
                } else {
                    $desc = (string) $sendResp->json('description', 'HTTP ' . $sendResp->status());
                    $this->error('No se pudo enviar el aviso de conexión: ' . $desc);
                    return self::FAILURE;
                }
            } catch (Throwable $e) {
                $this->error('Error al enviar el aviso de conexión: ' . $e->getMessage());
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
