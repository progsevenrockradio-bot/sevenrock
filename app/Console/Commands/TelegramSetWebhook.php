<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:webhook {--remove : Eliminar el webhook en lugar de registrarlo}';
    protected $description = 'Configura o elimina el Webhook de Telegram para producción';

    public function handle()
    {
        $token = env('TELEGRAM_BOT_TOKEN');
        if (!$token) {
            $this->error('TELEGRAM_BOT_TOKEN no encontrado en el archivo .env');
            return Command::FAILURE;
        }

        if ($this->option('remove')) {
            $url = "https://api.telegram.org/bot{$token}/deleteWebhook";
            $response = Http::get($url);

            if ($response->successful()) {
                $this->info('Webhook eliminado exitosamente. Ahora puedes usar el bot por polling local.');
                return Command::SUCCESS;
            } else {
                $this->error('Error al eliminar Webhook: ' . $response->body());
                return Command::FAILURE;
            }
        }

        $appUrl = env('APP_URL');
        if (!$appUrl || !str_starts_with($appUrl, 'https://')) {
            $this->error('APP_URL debe estar configurada en el .env y comenzar con https://');
            return Command::FAILURE;
        }

        $webhookUrl = rtrim($appUrl, '/') . '/api/telegram/webhook';
        $apiUrl = "https://api.telegram.org/bot{$token}/setWebhook?url={$webhookUrl}";

        $this->info("Registrando webhook en: {$webhookUrl}");
        $response = Http::get($apiUrl);

        if ($response->successful()) {
            $this->info('Webhook registrado exitosamente en Telegram.');
            return Command::SUCCESS;
        } else {
            $this->error('Error al registrar Webhook: ' . $response->body());
            return Command::FAILURE;
        }
    }
}
