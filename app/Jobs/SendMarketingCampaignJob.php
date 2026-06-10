<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\MarketingMail;
use App\Models\MarketingCampaign;
use App\Models\MarketingContact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendMarketingCampaignJob implements ShouldQueue
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
    public $timeout = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $campaignId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $campaign = MarketingCampaign::find($this->campaignId);
        if (! $campaign) {
            Log::error("SendMarketingCampaignJob: Campaña ID {$this->campaignId} no encontrada.");
            return;
        }

        if ($campaign->status === 'sent') {
            Log::info("SendMarketingCampaignJob: La campaña ID {$this->campaignId} ya fue enviada anteriormente.");
            return;
        }

        $account = $campaign->senderAccount;
        if (! $account || ! $account->is_active) {
            Log::error("SendMarketingCampaignJob: Cuenta remitente no configurada o inactiva para la campaña ID {$this->campaignId}.");
            $campaign->update(['status' => 'failed']);
            return;
        }

        $contacts = MarketingContact::where('is_active', true)->get();
        $totalContacts = count($contacts);

        if ($totalContacts === 0) {
            Log::info("SendMarketingCampaignJob: No hay contactos activos para enviar en la campaña ID {$this->campaignId}.");
            $campaign->update([
                'status' => 'sent',
                'total_contacts' => 0,
                'sent_contacts' => 0
            ]);
            return;
        }

        $campaign->update([
            'status' => 'sending',
            'total_contacts' => $totalContacts,
            'sent_contacts' => 0
        ]);

        Log::info("SendMarketingCampaignJob: Iniciando envío de campaña '{$campaign->subject}' a {$totalContacts} destinatarios.");

        foreach ($contacts as $contact) {
            // Volver a consultar el estado por si fue cancelada manualmente
            $campaign->refresh();
            if ($campaign->status !== 'sending') {
                Log::info("SendMarketingCampaignJob: El envío de la campaña ID {$this->campaignId} fue interrumpido o modificado.");
                break;
            }

            try {
                // Configurar SMTP dinámico
                config([
                    'mail.mailers.dynamic' => [
                        'transport' => 'smtp',
                        'host' => $account->smtp_host,
                        'port' => (int) $account->smtp_port,
                        'encryption' => $account->smtp_encryption,
                        'username' => $account->email,
                        'password' => $account->smtp_password, // Desencriptado automáticamente por Eloquent cast
                        'timeout' => null,
                        'local_domain' => env('MAIL_EHLO_DOMAIN'),
                    ]
                ]);

                Mail::mailer('dynamic')->to($contact->email)->send(new MarketingMail(
                    $campaign->template,
                    $campaign->subject,
                    $campaign->body_content,
                    $campaign->button_text,
                    $campaign->button_url,
                    $account->email,
                    $account->sender_name,
                    $contact->name
                ));

                $campaign->increment('sent_contacts');

            } catch (\Throwable $e) {
                Log::error("SendMarketingCampaignJob: Error al enviar a {$contact->email}: " . $e->getMessage());
            }

            // Pausa de 3 segundos entre envíos para respetar límites de Gmail
            sleep(3);
        }

        $campaign->update(['status' => 'sent']);
        Log::info("SendMarketingCampaignJob: Campaña ID {$this->campaignId} enviada con éxito.");
    }
}
