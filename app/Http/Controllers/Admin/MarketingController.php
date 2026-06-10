<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ScrapeAndEnrichContactsJob;
use App\Jobs\SendMarketingCampaignJob;
use App\Models\MarketingCampaign;
use App\Models\MarketingContact;
use App\Models\MarketingMailAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Webklex\PHPIMAP\ClientManager;

class MarketingController extends Controller
{
    /**
     * Display the marketing dashboard.
     */
    public function index(Request $request): View
    {
        $accounts = MarketingMailAccount::latest()->get();
        $campaigns = MarketingCampaign::with('senderAccount')->latest()->get();

        $contactsQuery = MarketingContact::with('sourceAccount')->latest();

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $contactsQuery->where(function ($q) use ($search) {
                $q->where('email', 'like', $search)
                  ->orWhere('name', 'like', $search)
                  ->orWhere('company_or_band', 'like', $search)
                  ->orWhere('role', 'like', $search);
            });
        }

        $contacts = $contactsQuery->paginate(50)->withQueryString();

        return view('admin.marketing.index', [
            'accounts' => $accounts,
            'contacts' => $contacts,
            'campaigns' => $campaigns,
            'templates' => [
                'promo_service' => 'Promoción de Servicio (Dark Rock)',
                'newsletter' => 'Boletín de Noticias (Newsletter)',
                'offer' => 'Oferta Especial',
                'direct' => 'Contacto Directo (Persona a Persona)',
                'event' => 'Lanzamiento o Evento (Rock Show)',
            ]
        ]);
    }

    /**
     * Store a new mail account.
     */
    public function storeAccount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'unique:marketing_mail_accounts,email'],
            'sender_name' => ['required', 'string', 'max:255'],
            'imap_host' => ['required', 'string', 'max:255'],
            'imap_port' => ['required', 'integer'],
            'imap_encryption' => ['required', 'string', 'in:ssl,tls,none'],
            'imap_password' => ['required', 'string'],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer'],
            'smtp_encryption' => ['required', 'string', 'in:ssl,tls,none'],
            'smtp_password' => ['required', 'string'],
        ]);

        MarketingMailAccount::create($validated);

        return redirect()->route('admin.marketing.index', ['tab' => 'accounts'])
            ->with('status', 'Cuenta de correo agregada con éxito.');
    }

    /**
     * Update an existing mail account.
     */
    public function updateAccount(Request $request, int $id): RedirectResponse
    {
        $account = MarketingMailAccount::findOrFail($id);

        $rules = [
            'sender_name' => ['required', 'string', 'max:255'],
            'imap_host' => ['required', 'string', 'max:255'],
            'imap_port' => ['required', 'integer'],
            'imap_encryption' => ['required', 'string', 'in:ssl,tls,none'],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer'],
            'smtp_encryption' => ['required', 'string', 'in:ssl,tls,none'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($request->filled('imap_password')) {
            $rules['imap_password'] = ['required', 'string'];
        }
        if ($request->filled('smtp_password')) {
            $rules['smtp_password'] = ['required', 'string'];
        }

        $validated = $request->validate($rules);
        $validated['is_active'] = $request->boolean('is_active', true);

        $account->update($validated);

        return redirect()->route('admin.marketing.index', ['tab' => 'accounts'])
            ->with('status', 'Cuenta de correo actualizada con éxito.');
    }

    /**
     * Delete a mail account.
     */
    public function deleteAccount(int $id): RedirectResponse
    {
        $account = MarketingMailAccount::findOrFail($id);
        $account->delete();

        return redirect()->route('admin.marketing.index', ['tab' => 'accounts'])
            ->with('status', 'Cuenta de correo eliminada.');
    }

    /**
     * Test IMAP and SMTP connections for an account.
     */
    public function testConnection(int $id): RedirectResponse
    {
        $account = MarketingMailAccount::findOrFail($id);
        
        $imapSuccess = false;
        $imapError = '';
        $smtpSuccess = false;
        $smtpError = '';

        // Test IMAP
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
            $imapSuccess = true;
        } catch (\Throwable $e) {
            $imapError = $e->getMessage();
        }

        // Test SMTP
        try {
            $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
                $account->smtp_host,
                (int) $account->smtp_port,
                $account->smtp_encryption === 'ssl' || $account->smtp_encryption === 'tls',
                null,
                null
            );
            $transport->setUsername($account->email);
            $transport->setPassword($account->smtp_password);
            $transport->start();
            $smtpSuccess = true;
        } catch (\Throwable $e) {
            $smtpError = $e->getMessage();
        }

        $message = "Resultado de pruebas para {$account->email}:\n";
        $message .= "• IMAP: " . ($imapSuccess ? "CONECTADO CON ÉXITO" : "FALLÓ (" . $imapError . ")") . "\n";
        $message .= "• SMTP: " . ($smtpSuccess ? "CONECTADO CON ÉXITO" : "FALLÓ (" . $smtpError . ")");

        return redirect()->route('admin.marketing.index', ['tab' => 'accounts'])
            ->with($imapSuccess && $smtpSuccess ? 'status' : 'error', $message);
    }

    /**
     * Trigger background contacts scraping from IMAP.
     */
    public function triggerScrape(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account_id' => ['required', 'exists:marketing_mail_accounts,id'],
            'folder' => ['required', 'string'],
            'limit' => ['required', 'integer', 'min:10', 'max:500'],
        ]);

        ScrapeAndEnrichContactsJob::dispatch(
            (int) $validated['account_id'],
            $validated['folder'],
            (int) $validated['limit']
        );

        return redirect()->route('admin.marketing.index', ['tab' => 'contacts'])
            ->with('status', 'Sincronización de contactos iniciada en segundo plano. Los contactos enriquecidos aparecerán en unos instantes.');
    }

    /**
     * Store and enqueue a marketing campaign.
     */
    public function storeCampaign(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'sender_account_id' => ['required', 'exists:marketing_mail_accounts,id'],
            'template' => ['required', 'string'],
            'body_content' => ['required', 'string'],
            'button_text' => ['nullable', 'string', 'max:100'],
            'button_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $campaign = MarketingCampaign::create($validated);

        // Encolar envío automáticamente
        SendMarketingCampaignJob::dispatch($campaign->id);

        return redirect()->route('admin.marketing.index', ['tab' => 'campaigns'])
            ->with('status', 'Campaña creada y envío encolado en segundo plano con éxito.');
    }

    /**
     * Store a contact manually.
     */
    public function storeContact(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'unique:marketing_contacts,email'],
            'name' => ['nullable', 'string', 'max:255'],
            'company_or_band' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['source_type'] = 'manual';
        $validated['is_active'] = true;

        MarketingContact::create($validated);

        return redirect()->route('admin.marketing.index', ['tab' => 'contacts'])
            ->with('status', 'Contacto agregado manualmente con éxito.');
    }

    /**
     * Delete a contact.
     */
    public function deleteContact(int $id): RedirectResponse
    {
        $contact = MarketingContact::findOrFail($id);
        $contact->delete();

        return redirect()->route('admin.marketing.index', ['tab' => 'contacts'])
            ->with('status', 'Contacto eliminado.');
    }
}
