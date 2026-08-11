<?php

require __DIR__ . '/../../../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../../');
$dotenv->safeLoad();

$token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;

if (!$token) {
    echo "Error: TELEGRAM_BOT_TOKEN no encontrado en .env\n";
    exit(1);
}

$action = $argv[1] ?? null;

if ($action === 'getUpdates') {
    $url = "https://api.telegram.org/bot{$token}/getUpdates";
    $response = file_get_contents($url);
    if ($response !== false) {
        $data = json_decode($response, true);
        if (isset($data['result']) && count($data['result']) > 0) {
            foreach (array_reverse($data['result']) as $update) {
                if (isset($update['message'])) {
                    $chatId = $update['message']['chat']['id'];
                    $text = $update['message']['text'] ?? '[No Text]';
                    $user = $update['message']['from']['first_name'] ?? 'Usuario';
                    echo "[$chatId] $user: $text\n";
                }
            }
        } else {
            echo "No hay mensajes recientes.\n";
        }
    } else {
        echo "Error al conectar con la API de Telegram.\n";
    }
} elseif ($action === 'sendMessage') {
    $chatId = $argv[2] ?? null;
    $message = $argv[3] ?? null;

    if (!$chatId || !$message) {
        echo "Uso: php bot.php sendMessage <chat_id> \"<mensaje>\"\n";
        exit(1);
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
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    if ($result === false) {
        echo "Error al enviar mensaje.\n";
        exit(1);
    }
    echo "Mensaje enviado exitosamente a $chatId.\n";
} else {
    echo "Acción no reconocida. Usa 'getUpdates' o 'sendMessage'.\n";
    exit(1);
}
