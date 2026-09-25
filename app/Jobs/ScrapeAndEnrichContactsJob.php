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

    public $timeout = 600;

    public function __construct(
        public readonly int $mailAccountId,
        public readonly string $folderName = 'INBOX',
        public readonly int $limit = 100,
        public readonly bool $isDryRun = false
    ) {
        $this->onQueue('marketing');
    }

    public function handle(): array
    {
        $stats = [
            'total_reviewed' => 0,
            'total_new' => 0,
            'total_skipped' => 0,
            'total_discarded' => 0,
            'total_errors' => 0,
            'new_contacts' => []
        ];

        $account = MarketingMailAccount::find($this->mailAccountId);
        if (! $account || ! $account->is_active) {
            Log::warning("ScrapeAndEnrichContactsJob: Cuenta de correo ID {$this->mailAccountId} no válida o inactiva.");
            $stats['total_errors']++;
            return $stats;
        }

        // RESTRICCION: NUNCA escanear prog.sevenrockradio@gmail.com
        if ($account->email === 'prog.sevenrockradio@gmail.com') {
            Log::warning("ScrapeAndEnrichContactsJob: Saltando cuenta {$account->email} por restriccion estricta.");
            $stats['total_skipped']++;
            return $stats;
        }

        $settings = ThemeSetting::current();
        $geminiKey = trim((string) $settings->gemini_api_key) ?: config('services.gemini.api_key');
        if ($geminiKey === '') {
            Log::error("ScrapeAndEnrichContactsJob: Gemini API Key no configurada.");
            $stats['total_errors']++;
            return $stats;
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
                'password'      => $account->imap_password,
                'protocol'      => 'imap'
            ]);

            $client->connect();
        } catch (\Throwable $e) {
            Log::error("ScrapeAndEnrichContactsJob: Fallo de conexión IMAP para {$account->email}: " . $e->getMessage());
            $stats['total_errors']++;
            return $stats;
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
                $stats['total_errors']++;
                return $stats;
            }

            Log::info("ScrapeAndEnrichContactsJob: Escaneando carpeta '{$targetFolder->path}'...");
            $messages = $targetFolder->query()->all()->setFetchOrder("desc")->limit($this->limit)->get();
            
            $stats['total_reviewed'] = count($messages);
            Log::info("ScrapeAndEnrichContactsJob: Analizando {$stats['total_reviewed']} correos...");
            
            $parser = app(GeminiContentParser::class);
            $processedEmails = [];

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

                // Descartar si el mismo remitente aparece varias veces en el lote (procesar una sola vez)
                if (in_array($email, $processedEmails)) {
                    $stats['total_skipped']++;
                    continue;
                }
                $processedEmails[] = $email;

                // Aplicar Filtro de calidad A) Correos técnicos y de software ANTES de nada
                $reason = '';
                if (!$this->isQualityContactEmail($email, $reason)) {
                    Log::info("ScrapeAndEnrichContactsJob: Descartado por filtro de email ({$email}) -> Motivo: {$reason}");
                    $stats['total_discarded']++;
                    continue;
                }

                // Comprobar si ya existe en la base de datos ANTES de llamar a Gemini
                $existingContact = MarketingContact::where('email', $email)->first();
                if ($existingContact) {
                    if (!$this->isDryRun) {
                        $existingContact->update(['last_scraped_at' => now()]);
                    }
                    Log::info("ScrapeAndEnrichContactsJob: Contacto ya existente, last_scraped_at actualizado: {$email}");
                    $stats['total_skipped']++;
                    continue;
                }

                // Usar Gemini para analizar e intentar enriquecerlo
                $subject = (string) $message->getSubject();
                $body = $message->getHTMLBody() ?: $message->getTextBody() ?: '';

                $name = $rawName ?: null;
                $companyOrBand = null;
                $role = null;

                if (trim($body) !== '') {
                    try {
                        Log::info("ScrapeAndEnrichContactsJob: Consultando Gemini para el remitente {$email}...");
                        
                        // Mock en entorno de test para evitar llamas reales. El test usará un mock de GeminiContentParser
                        $enriched = $parser->parseContactInfo($subject, $body, $geminiKey);

                        if ($enriched) {
                            $name = trim((string) ($enriched['name'] ?? $name));
                            $companyOrBand = trim((string) ($enriched['company_or_band'] ?? ''));
                            $role = trim((string) ($enriched['role'] ?? ''));

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

                if (empty($name)) {
                    $name = $rawName ?: explode('@', $email)[0];
                }

                // Aplicar Filtro de calidad B) Rol no comercial
                if (!$this->isQualityContactRole($role, $reason)) {
                    Log::info("ScrapeAndEnrichContactsJob: Descartado por filtro de rol ({$email}) -> Rol: {$role}, Motivo: {$reason}");
                    $stats['total_discarded']++;
                    continue;
                }

                if (!$this->isDryRun) {
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
                }

                $stats['total_new']++;
                $stats['new_contacts'][] = [
                    'email' => $email,
                    'name' => $name,
                    'company' => $companyOrBand ?: 'N/A',
                    'role' => $role ?: 'N/A'
                ];

                Log::info("ScrapeAndEnrichContactsJob: Contacto guardado" . ($this->isDryRun ? " (DRY-RUN)" : "") . ": {$email} ({$name} - {$companyOrBand})");
            }

            Log::info("ScrapeAndEnrichContactsJob: Sincronización completada con éxito.");

        } catch (\Throwable $e) {
            Log::error("ScrapeAndEnrichContactsJob: Error general al procesar carpeta: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $stats['total_errors']++;
        }

        return $stats;
    }

    private function isQualityContactEmail(string $email, string &$reason): bool
    {
        $domain = substr(strrchr($email, "@"), 1);
        $prefix = explode('@', $email)[0];

        $badDomains = ['wpallimport', 'apob', 'hubspot', 'mailchimp', 'sendgrid', 'mailgun', 'constantcontact', 'shopify', 'wordpress', 'elementor', 'cloudflare'];
        foreach ($badDomains as $bd) {
            if (str_contains($domain, $bd)) {
                $reason = "Dominio de software/herramienta ($bd)";
                return false;
            }
        }

        $badPrefixes = ['noreply', 'no-reply', 'donotreply', 'postmaster', 'bounce', 'mailer-daemon', 'support', 'soporte', 'help', 'billing', 'invoice', 'notification', 'newsletter', 'marketing', 'unsubscribe'];
        if (in_array($prefix, $badPrefixes)) {
            $reason = "Prefijo técnico ($prefix)";
            return false;
        }

        $relayDomains = ['mailchimpapp', 'sendgrid.net', 'mailgun.org', 'amazonses', 'mandrillapp', 'hubspotemail'];
        foreach ($relayDomains as $rd) {
            if (str_contains($domain, $rd)) {
                $reason = "Relé de email ($rd)";
                return false;
            }
        }

        return true;
    }

    private function isQualityContactRole(?string $role, string &$reason): bool
    {
        if (empty($role)) {
            return true; // Cualquier rol vacío o dudoso se guarda igual
        }

        $validRoles = ['banda', 'artista', 'manager', 'representante', 'booking', 'agencia', 'sello', 'discografico', 'productora', 'festival', 'promotor', 'prensa', 'pr', 'radio', 'sincronizacion', 'distribucion'];
        
        // Normalize role to lower without accents could be good, but str_contains handles exact match.
        // It's safer to check str_contains case insensitive. (str_contains is case sensitive in PHP 8, so use stripos).
        foreach ($validRoles as $vr) {
            if (stripos($role, $vr) !== false) {
                return true;
            }
        }

        $reason = "Rol no comercial ($role)";
        return false;
    }
}
