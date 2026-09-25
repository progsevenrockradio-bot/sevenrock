<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MarketingContact;
use Illuminate\Support\Facades\Log;

class MarketingPruneContactsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'marketing:prune-contacts {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpia contactos basura históricos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $contacts = MarketingContact::where('is_active', true)->get();
        $deactivated = 0;

        foreach ($contacts as $contact) {
            if (!$this->isQualityContactEmail($contact->email) || !$this->isQualityContactRole($contact->role)) {
                if (!$isDryRun) {
                    $contact->update(['is_active' => false]);
                }
                $deactivated++;
                $this->line("Desactivado: {$contact->email} (Rol: {$contact->role})");
                Log::info("MarketingPruneContactsCommand: Desactivado contacto histórico {$contact->email} por filtro de calidad.");
            }
        }

        $this->info("Operación finalizada. " . ($isDryRun ? "[DRY-RUN] Se desactivarían: " : "Desactivados: ") . $deactivated);
    }

    private function isQualityContactEmail(string $email): bool
    {
        $domain = strtolower(substr(strrchr($email, "@"), 1));
        $prefix = strtolower(strstr($email, '@', true));

        $badDomains = [
            'wpallimport.com', 'apob.ai', 'hubspot.com', 'mailchimp.com', 'sendgrid.com', 'mailgun.org',
            'constantcontact.com', 'shopify.com', 'wordpress.com', 'elementor.com', 'cloudflare.com',
            'sentry-next.wixpress.com', 'sentry.wixpress.com', 'sentry.io'
        ];

        $badDomainFragments = [
            'mailchimpapp', 'sendgrid.net', 'amazonses', 'mandrillapp', 'hubspotemail'
        ];

        $badPrefixes = [
            'noreply', 'no-reply', 'donotreply', 'postmaster', 'bounce', 'mailer-daemon',
            'support', 'soporte', 'help', 'billing', 'invoice', 'notification', 'newsletter',
            'marketing', 'unsubscribe', 'info', 'admin', 'hello', 'contact', 'sales'
        ];

        if (in_array($domain, $badDomains)) return false;
        if (in_array($prefix, $badPrefixes)) return false;

        foreach ($badDomainFragments as $frag) {
            if (str_contains($domain, $frag)) return false;
        }

        return true;
    }

    private function isQualityContactRole(?string $role): bool
    {
        if (empty($role)) return true; // Si está vacío se deja pasar (dudoso)

        $lowerRole = strtolower($role);
        $badRoles = ['fan', 'oyente', 'suscripción', 'robot', 'suscriptor', 'listener'];
        
        foreach ($badRoles as $br) {
            if (str_contains($lowerRole, $br)) return false;
        }

        return true;
    }
}
