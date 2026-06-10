<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MarketingContact;
use App\Models\MarketingMailAccount;
use App\Models\ThemeSetting;
use App\Services\GeminiContentParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\ClientManager;

class ScrapeAndEnrichContactsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $mailAccountId,
        public readonly string $folderName = 'INBOX',
        public readonly int $limit = 100
    ) {
        $this->onQueue('marketing');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $account = MarketingMailAccount::find($this->mailAccountId);
        if (! $account || ! $account->is_active) {
            Log::warning("ScrapeAndEnrichContactsJob: Cuenta de correo ID {$this->mailAccountId} no válida o inactiva.");
            return;
        }

        $settings = ThemeSetting::current();
        $geminiKey = trim((string) $settings->gemini_api_key);
        if ($geminiKey === '') {
            Log::error("ScrapeAndEnrichContactsJob: Gemini API Key no configurada.");
            return;
        }

        Log::info("ScrapeAndEnrichContactsJob: Conectando a IMAP de {$account->email}...");

        try {
            $cm = new ClientManager();
            $client = $cm->make([
                'host'          => $account->imap_host,
                'port'          => (int) $account->imap_port,
                'encryption'    => $account->imap_encryption,
                'validate_cert' => config('services.imap.validate_cert', false),
                'username'      => $account->email,
                'password'      => $account->imap_password, // Desencriptado automáticamente
                'protocol'      => 'imap'
            ]);

            $client->connect();
        } catch (\Throwable $e) {
            Log::error("ScrapeAndEnrichContactsJob: Fallo de conexión IMAP para {$account->email}: " . $e->getMessage());
            return;
        }

        try {
            $folders = $client->getFolders();
            $targetFolder = null;

            foreach ($folders as $f) {
                if (strcasecmp($f->path, $this->folderName) === 0 || strcasecmp($f->name, $this->folderName) === 0) {
                    $targetFolder = $f;
                    break;
                }
            }

            if (! $targetFolder) {
                Log::warning("ScrapeAndEnrichContactsJob: No se encontró la carpeta '{$this->folderName}' en la cuenta {$account->email}.");
                return;
            }

            Log::info("ScrapeAndEnrichContactsJob: Escaneando carpeta '{$targetFolder->path}'...");
            $messages = $targetFolder->query()->limit($this->limit)->get();

            Log::info("ScrapeAndEnrichContactsJob: Analizando " . count($messages) . " correos...");
            $parser = app(GeminiContentParser::class);

            foreach ($messages as $message) {
                $fromAttribute = $message->getFrom();
                $senderAddress = $fromAttribute ? $fromAttribute->first() : null;

                if (! ($senderAddress instanceof \Webklex\PHPIMAP\Address)) {
                    continue;
                }

                $email = trim(strtolower((string) $senderAddress->mail));
                $rawName = trim((string) $senderAddress->personal);

                if ($email === '') {
                    continue;
                }

                // Si ya existe en contactos, omitir
                if (MarketingContact::where('email', $email)->exists()) {
                    continue;
                }

                // Si no existe, usar Gemini para analizar e intentar enriquecerlo
                $subject = (string) $message->getSubject();
                $body = $message->getHTMLBody() ?: $message->getTextBody() ?: '';

                $name = $rawName ?: null;
                $companyOrBand = null;
                $role = null;

                // Solo llamar a Gemini si el cuerpo del mensaje tiene contenido útil
                if (trim($body) !== '') {
                    try {
                        Log::info("ScrapeAndEnrichContactsJob: Consultando Gemini para el remitente {$email}...");
                        $enriched = $parser->parseContactInfo($subject, $body, $geminiKey);

                        if ($enriched) {
                            $name = trim((string) ($enriched['name'] ?? $name));
                            $companyOrBand = trim((string) ($enriched['company_or_band'] ?? ''));
                            $role = trim((string) ($enriched['role'] ?? ''));

                            // Limpiar valores por defecto vacíos
                            if (strcasecmp($companyOrBand, 'Independiente') === 0 || strcasecmp($companyOrBand, 'Desconocido') === 0) {
                                $companyOrBand = null;
                            }
                            if (strcasecmp($role, 'Representante') === 0 || strcasecmp($role, 'Músico') === 0 || strcasecmp($role, 'Desconocido') === 0) {
                                $role = null;
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::error("ScrapeAndEnrichContactsJob: Error al enriquecer con Gemini para {$email}: " . $e->getMessage());
                    }
                }

                // Si Gemini no devolvió un nombre válido, caer al nombre del remitente de IMAP
                if (empty($name)) {
                    $name = $rawName ?: explode('@', $email)[0];
                }

                MarketingContact::create([
                    'email' => $email,
                    'name' => $name,
                    'company_or_band' => $companyOrBand,
                    'role' => $role,
                    'is_active' => true,
                    'source_account_id' => $account->id,
                    'source_type' => 'scraped_' . strtolower(str_replace(['[', ']'], '', $this->folderName)),
                    'last_scraped_at' => now(),
                ]);

                Log::info("ScrapeAndEnrichContactsJob: Contacto guardado: {$email} ({$name} - {$companyOrBand})");
            }

            Log::info("ScrapeAndEnrichContactsJob: Sincronización completada con éxito.");

        } catch (\Throwable $e) {
            Log::error("ScrapeAndEnrichContactsJob: Error general al procesar carpeta: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }
}
