<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ThemeSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Webklex\PHPIMAP\ClientManager;

class ScrapeSenders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:scrape-senders 
                            {--folder=INBOX : Carpeta IMAP a escanear (INBOX, [Gmail]/Papelera, Trash, etc.)} 
                            {--limit=1000 : Límite de correos a escanear} 
                            {--output=senders.csv : Nombre del archivo de salida CSV}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Escanea una carpeta de Gmail vía IMAP para extraer remitentes únicos y contar la frecuencia de correos recibidos.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $settings = ThemeSetting::current();

        $imapHost = config('services.imap.host', 'imap.gmail.com');
        $imapPort = (int) config('services.imap.port', 993);
        $imapEncryption = config('services.imap.encryption', 'ssl');
        $imapUsername = config('services.imap.username') ?: $settings->notification_email;
        $imapPassword = config('services.imap.password');

        if (empty($imapPassword)) {
            $this->error('La contraseña de IMAP (IMAP_PASSWORD) no está configurada en el archivo .env.');
            return 1;
        }

        $folderName = $this->option('folder');
        $limit = (int) $this->option('limit');
        $outputFile = $this->option('output');

        $this->info("Conectando a {$imapHost}:{$imapPort}...");
        
        try {
            $cm = new ClientManager();
            $client = $cm->make([
                'host'          => $imapHost,
                'port'          => $imapPort,
                'encryption'    => $imapEncryption,
                'validate_cert' => config('services.imap.validate_cert', false),
                'username'      => $imapUsername,
                'password'      => $imapPassword,
                'protocol'      => 'imap'
            ]);

            $client->connect();
        } catch (\Throwable $e) {
            $this->error("Error de conexión IMAP: " . $e->getMessage());
            return 1;
        }

        try {
            // Obtener todas las carpetas para listar opciones si la carpeta no existe
            $folders = $client->getFolders();
            $targetFolder = null;
            $availableFolderNames = [];

            foreach ($folders as $f) {
                $availableFolderNames[] = $f->path;
                if (strcasecmp($f->path, $folderName) === 0 || strcasecmp($f->name, $folderName) === 0) {
                    $targetFolder = $f;
                }
            }

            if (! $targetFolder) {
                $this->warn("No se encontró la carpeta '{$folderName}'. Carpetas disponibles en tu cuenta:");
                foreach ($availableFolderNames as $name) {
                    $this->line(" - {$name}");
                }
                return 1;
            }

            $this->info("Escaneando hasta {$limit} correos en la carpeta '{$targetFolder->path}'...");
            
            // Obtener correos
            $query = $targetFolder->query()->all();
            $messages = $query->limit($limit)->get();

            $this->info("Encontrados " . count($messages) . " correos para analizar.");

            $senders = [];

            foreach ($messages as $idx => $message) {
                $fromAttribute = $message->getFrom();
                $senderAddress = $fromAttribute ? $fromAttribute->first() : null;

                if ($senderAddress instanceof \Webklex\PHPIMAP\Address) {
                    $email = trim(strtolower((string) $senderAddress->mail));
                    $name = trim((string) $senderAddress->personal);

                    if ($email !== '') {
                        if (! isset($senders[$email])) {
                            $senders[$email] = [
                                'email' => $email,
                                'name' => $name,
                                'count' => 0,
                                'domain' => substr($email, strpos($email, '@') + 1),
                            ];
                        }
                        $senders[$email]['count']++;
                        
                        // Si el nombre estaba vacío pero ahora viene con contenido, guardarlo
                        if ($name !== '' && $senders[$email]['name'] === '') {
                            $senders[$email]['name'] = $name;
                        }
                    }
                }

                if (($idx + 1) % 100 === 0) {
                    $this->line("Procesados " . ($idx + 1) . " correos...");
                }
            }

            // Ordenar por número de correos recibidos descendente
            uasort($senders, function ($a, $b) {
                return $b['count'] <=> $a['count'];
            });

            // Generar CSV
            $csvData = [];
            $csvData[] = ['Email', 'Nombre', 'Dominio', 'Total Correos Enviados'];
            foreach ($senders as $s) {
                $csvData[] = [$s['email'], $s['name'], $s['domain'], $s['count']];
            }

            $tempPath = storage_path('app/' . $outputFile);
            $fp = fopen($tempPath, 'w');
            
            // Agregar UTF-8 BOM para soporte correcto de caracteres especiales en Excel
            fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
            
            foreach ($csvData as $row) {
                fputcsv($fp, $row);
            }
            fclose($fp);

            $this->info("¡Scraping finalizado!");
            $this->info("Remitentes únicos encontrados: " . count($senders));
            $this->info("El archivo CSV ha sido guardado exitosamente en: " . $tempPath);
            $this->line("Puedes descargarlo o copiar remitentes frecuentes para tu lista blanca.");

        } catch (\Throwable $e) {
            $this->error("Excepción durante el scraping: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
