<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Genera un par de claves VAPID (pública/privada) para las Push Notifications
 * de la PWA usando OpenSSL nativo de PHP y las escribe en el archivo .env.
 *
 * Uso:
 *   php artisan pwa:vapid-keys
 *   php artisan pwa:vapid-keys --force   # Para regenerar
 */
class GeneratePwaVapidKeys extends Command
{
    protected $signature   = 'pwa:vapid-keys {--force : Regenerar aunque ya existan las claves}';
    protected $description = 'Genera claves VAPID para Push Notifications de la PWA y las escribe en .env';

    public function handle(): int
    {
        if (! extension_loaded('openssl')) {
            $this->error('La extensión OpenSSL de PHP no está disponible.');
            return self::FAILURE;
        }

        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            $this->error('No se encontró el archivo .env');
            return self::FAILURE;
        }

        $envContent = file_get_contents($envPath);

        // Verificar si ya existen claves no vacías
        preg_match('/^VAPID_PUBLIC_KEY=(.+)$/m', $envContent, $m);
        $alreadyExists = ! empty(trim($m[1] ?? ''));

        if ($alreadyExists && ! $this->option('force')) {
            $this->warn('Las claves VAPID ya están configuradas en .env.');
            $this->line('Usa --force para regenerarlas (esto invalidará suscripciones actuales).');
            return self::SUCCESS;
        }

        $this->info('Generando par de claves VAPID...');

        try {
            [$publicKey, $privateKey] = $this->generateVapidKeys();
        } catch (\Throwable $e) {
            $this->error('Error al generar claves VAPID: ' . $e->getMessage());
            return self::FAILURE;
        }

        $subject = (string)(env('VAPID_SUBJECT') ?: 'mailto:prog.sevenrockradio@gmail.com');

        // Actualizar/añadir variables en .env
        $envContent = $this->setEnvVar($envContent, 'VAPID_PUBLIC_KEY',  $publicKey);
        $envContent = $this->setEnvVar($envContent, 'VAPID_PRIVATE_KEY', $privateKey);
        $envContent = $this->setEnvVar($envContent, 'VAPID_SUBJECT',     $subject);

        file_put_contents($envPath, $envContent);

        $this->newLine();
        $this->info('✅ Claves VAPID generadas y guardadas en .env:');
        $this->line('  VAPID_PUBLIC_KEY  = ' . substr($publicKey, 0, 30) . '...');
        $this->line('  VAPID_PRIVATE_KEY = (oculta)');
        $this->line("  VAPID_SUBJECT     = {$subject}");
        $this->newLine();
        $this->comment('La clave pública se sirve automáticamente en GET /app/push/vapid-key.');

        return self::SUCCESS;
    }

    /**
     * Genera el par de claves VAPID usando la curva P-256 via OpenSSL.
     * Devuelve [publicKeyBase64Url, privateKeyBase64Url].
     *
     * @return array{0: string, 1: string}
     */
    private function generateVapidKeys(): array
    {
        $options = [
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];

        // Buscar openssl.cnf en entornos Windows / Laragon si existe
        $cnf = $this->findOpenSslConfig();
        if ($cnf) {
            $options['config'] = $cnf;
        }

        $key = openssl_pkey_new($options);

        if (! $key) {
            throw new \RuntimeException('openssl_pkey_new() falló: ' . openssl_error_string());
        }

        $details = openssl_pkey_get_details($key);

        if (! $details || ! isset($details['ec'])) {
            throw new \RuntimeException('No se pudieron obtener los detalles EC de la clave.');
        }

        $ec = $details['ec'];

        // Clave pública sin comprimir: 0x04 || x || y (65 bytes)
        $x = str_pad($ec['x'], 32, "\x00", STR_PAD_LEFT);
        $y = str_pad($ec['y'], 32, "\x00", STR_PAD_LEFT);
        $publicKeyRaw = "\x04" . $x . $y;

        // Clave privada d (32 bytes)
        $privateKeyRaw = str_pad($ec['d'], 32, "\x00", STR_PAD_LEFT);

        return [
            $this->base64UrlEncode($publicKeyRaw),
            $this->base64UrlEncode($privateKeyRaw),
        ];
    }

    /** Busca el archivo openssl.cnf en Laragon/Windows. */
    private function findOpenSslConfig(): ?string
    {
        $candidates = array_merge(
            glob('C:/laragon/bin/php/*/extras/ssl/openssl.cnf') ?: [],
            ['C:/laragon/bin/openssl/openssl.cnf']
        );

        foreach ($candidates as $file) {
            if (file_exists($file)) {
                return $file;
            }
        }

        return null;
    }

    /** Codifica bytes en base64url (sin padding). */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /** Actualiza o añade una variable en el contenido .env. */
    private function setEnvVar(string $content, string $key, string $value): string
    {
        $pattern = "/^{$key}=.*$/m";
        $line    = "{$key}={$value}";

        if (preg_match($pattern, $content)) {
            return preg_replace($pattern, $line, $content);
        }

        return $content . PHP_EOL . $line;
    }
}
