<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ScrapeAndEnrichContactsJob;
use App\Models\MarketingMailAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ScrapeContactsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'marketing:scrape-contacts
                            {--account= : ID de una cuenta concreta (por defecto, todas las activas)}
                            {--folder=INBOX : Carpeta a escanear}
                            {--limit=500 : Correos a revisar por cuenta}
                            {--dry-run : Solo informa, no guarda nada}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rutina programada para captar contactos de los buzones de marketing.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $accountId = $this->option('account');
        $folder = $this->option('folder');
        $limit = (int) $this->option('limit');
        $isDryRun = $this->option('dry-run');

        if ($accountId) {
            $accounts = MarketingMailAccount::where('id', $accountId)->where('is_active', true)->get();
        } else {
            $accounts = MarketingMailAccount::where('is_active', true)->get();
        }

        if ($accounts->isEmpty()) {
            $this->warn('No hay cuentas de correo activas para procesar.');
            return 0;
        }

        $allStats = [];
        $totalNewContacts = [];

        foreach ($accounts as $account) {
            $this->info("Procesando cuenta: {$account->email}...");
            
            // Si es dry-run, podemos pasar la flag al job o simular
            // Modificaremos el job para que soporte dry-run o lo manejamos aqui.
            // Para simplificar, pasamos isDryRun al constructor del job.
            
            $job = new ScrapeAndEnrichContactsJob($account->id, $folder, $limit, $isDryRun);
            $stats = dispatch_sync($job);

            if (is_array($stats)) {
                $this->table(
                    ['Revisados', 'Nuevos', 'Saltados', 'Descartados', 'Errores'],
                    [[$stats['total_reviewed'], $stats['total_new'], $stats['total_skipped'], $stats['total_discarded'], $stats['total_errors']]]
                );

                $allStats[$account->email] = $stats;
                foreach ($stats['new_contacts'] as $nc) {
                    $totalNewContacts[] = $nc;
                }
            } else {
                $this->error("Fallo al obtener estadisticas de la cuenta {$account->email}");
            }
        }

        // Si hay contactos nuevos y NO es dry-run, mandar correo
        if (!$isDryRun && count($totalNewContacts) > 0) {
            $this->info('Enviando correo de resumen...');
            $this->sendSummaryEmail($allStats, $totalNewContacts);
        }

        return 0;
    }

    private function sendSummaryEmail(array $stats, array $newContacts)
    {
        $settings = \App\Models\ThemeSetting::current();
        
        $recipients = [];
        if (!empty($settings->notification_email)) $recipients[] = $settings->notification_email;
        if (!empty($settings->notification_copy_email)) $recipients[] = $settings->notification_copy_email;
        
        $admins = \App\Models\User::where('role', 'admin')->pluck('email')->toArray();
        $recipients = array_merge($recipients, $admins);

        if (!empty($settings->moderation_extra_emails)) {
            $extras = array_map('trim', explode(',', $settings->moderation_extra_emails));
            $recipients = array_merge($recipients, $extras);
        }

        $recipients = array_unique(array_filter($recipients));

        if (empty($recipients)) {
            return;
        }

        $subject = 'Resumen Captacion de Contactos: ' . count($newContacts) . ' nuevos';
        
        $body = "<h1>Resumen de Captacion</h1>";
        $body .= "<p>Se ha ejecutado la rutina de captacion de contactos de marketing.</p>";
        
        $body .= "<h2>Estadisticas por cuenta:</h2><ul>";
        foreach ($stats as $email => $s) {
            $body .= "<li><strong>{$email}:</strong> Revisados: {$s['total_reviewed']}, Nuevos: {$s['total_new']}, Descartados: {$s['total_discarded']}</li>";
        }
        $body .= "</ul>";

        $body .= "<h2>Nuevos Contactos (" . count($newContacts) . "):</h2><ul>";
        foreach ($newContacts as $nc) {
            $body .= "<li>{$nc['email']} ({$nc['name']}) - <strong>Rol:</strong> {$nc['role']} - <strong>Origen:</strong> {$nc['company']}</li>";
        }
        $body .= "</ul>";

        // Usar la fachada Mail para enviar un raw HTML
        Mail::html($body, function ($msg) use ($recipients, $subject) {
            $msg->to($recipients)
                ->subject($subject);
        });
    }
}
