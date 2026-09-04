<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramBotController extends Controller
{
    public function handle(Request $request)
    {
        // 1. Validar que la petición venga de Telegram (opcional pero recomendado)
        // Puedes agregar un token secreto en la URL del webhook si lo deseas.

        $data = $request->all();

        // Registrar para debuggear
        Log::info('Telegram Webhook recibido:', ['data' => $data]);

        if (isset($data['message'])) {
            $chatId = $data['message']['chat']['id'] ?? null;
            $text = $data['message']['text'] ?? '';

            if ($chatId && $text) {
                // Aquí puedes procesar comandos (ej. /status, /info)
                if (str_starts_with($text, '/status')) {
                    $this->sendMessage($chatId, "Seven Rock Radio Webhook Activo en Producción. 🎸");
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }

    private function sendMessage(int|string $chatId, string $message): void
    {
        $token = env('TELEGRAM_BOT_TOKEN');
        if (!$token) {
            Log::error('TELEGRAM_BOT_TOKEN no configurado.');
            return;
        }

        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
        ];

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
            ],
        ];
        
        $context = stream_context_create($options);
        @file_get_contents($url, false, $context);
    }
}
