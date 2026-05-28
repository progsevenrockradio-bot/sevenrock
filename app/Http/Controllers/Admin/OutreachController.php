<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendOutreachEmailsJob;
use App\Mail\OutreachMail;
use App\Models\BandContact;
use App\Models\OutreachCampaign;
use App\Models\OutreachLog;
use App\Models\OutreachTemplate;
use App\Models\RadioArtist;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class OutreachController extends Controller
{
    public function index(): View
    {
        return view('admin.outreach.index', [
            'stats' => $this->stats(),
            'campaigns' => OutreachCampaign::query()->with('template')->latest()->limit(8)->get(),
            'recentContacts' => BandContact::query()->with('radioArtist')->latest()->limit(8)->get(),
        ]);
    }

    public function templates(): View
    {
        return view('admin.outreach.templates.index', [
            'templates' => OutreachTemplate::query()->latest()->paginate(20),
        ]);
    }

    public function templatesCreate(): View
    {
        return view('admin.outreach.templates.create', [
            'template' => new OutreachTemplate([
                'variables' => OutreachTemplate::defaultVariables(),
                'is_active' => true,
            ]),
            'availableVariables' => OutreachTemplate::defaultVariables(),
        ]);
    }

    public function templatesStore(Request $request): RedirectResponse
    {
        $data = $this->validateTemplate($request);
        OutreachTemplate::query()->create($data);

        return redirect()->route('admin.outreach.templates.index')->with('status', 'Plantilla creada.');
    }

    public function templatesEdit(OutreachTemplate $template): View
    {
        return view('admin.outreach.templates.edit', [
            'template' => $template,
            'availableVariables' => OutreachTemplate::defaultVariables(),
        ]);
    }

    public function templatesUpdate(Request $request, OutreachTemplate $template): RedirectResponse
    {
        $template->update($this->validateTemplate($request));

        return redirect()->route('admin.outreach.templates.index')->with('status', 'Plantilla actualizada.');
    }

    public function templatesDestroy(OutreachTemplate $template): RedirectResponse
    {
        $template->delete();

        return back()->with('status', 'Plantilla eliminada.');
    }

    public function templatePreview(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string'],
            'body' => ['required', 'string'],
            'band_name' => ['nullable', 'string'],
            'band_genre' => ['nullable', 'string'],
            'band_country' => ['nullable', 'string'],
            'contact_person' => ['nullable', 'string'],
        ]);

        $template = new OutreachTemplate([
            'subject' => $data['subject'],
            'body' => $data['body'],
        ]);

        $contact = new BandContact([
            'contact_person' => $data['contact_person'] ?? 'Contacto demo',
        ]);

        if (($data['band_name'] ?? '') !== '') {
            $contact->setRelation('radioArtist', new RadioArtist([
                'name' => (string) $data['band_name'],
                'genre' => (string) ($data['band_genre'] ?? 'Rock'),
                'country' => (string) ($data['band_country'] ?? 'ES'),
            ]));
        }

        return response()->json([
            'subject' => $template->renderSubject($contact),
            'body' => $template->renderBody($contact),
        ]);
    }

    public function contacts(Request $request): View
    {
        $query = BandContact::query()->with('radioArtist')->latest();

        if ($status = trim((string) $request->input('status', ''))) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($inner) use ($search): void {
                $inner->where('email', 'like', '%' . $search . '%')
                    ->orWhere('contact_person', 'like', '%' . $search . '%')
                    ->orWhereHas('radioArtist', function ($artistQuery) use ($search): void {
                        $artistQuery->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        return view('admin.outreach.contacts.index', [
            'contacts' => $query->paginate(20)->withQueryString(),
            'statusFilter' => (string) $request->input('status', ''),
            'search' => (string) $request->input('search', ''),
        ]);
    }

    public function contactsCreate(): View
    {
        return view('admin.outreach.contacts.create', [
            'contact' => new BandContact([
                'status' => 'pending',
            ]),
        ]);
    }

    public function contactsEdit(BandContact $contact): View
    {
        return view('admin.outreach.contacts.edit', [
            'contact' => $contact->load('radioArtist'),
        ]);
    }

    public function contactsImport(): RedirectResponse
    {
        $created = 0;

        RadioArtist::query()->orderBy('name')->get()->each(function (RadioArtist $artist) use (&$created): void {
            $exists = BandContact::query()->where('radio_artist_id', $artist->id)->exists();
            if ($exists) {
                return;
            }

            BandContact::query()->create([
                'radio_artist_id' => $artist->id,
                'email' => $this->extractArtistEmail($artist),
                'phone' => null,
                'facebook' => $this->extractArtistLink($artist, 'facebook'),
                'instagram' => $this->extractArtistLink($artist, 'instagram'),
                'contact_person' => null,
                'notes' => 'Importado desde Radio Artists el ' . Carbon::now()->toDateTimeString(),
                'status' => 'pending',
            ]);

            $created++;
        });

        return back()->with('status', "Se importaron {$created} contactos.");
    }

    public function contactsStore(Request $request): RedirectResponse
    {
        $data = $this->validateContact($request);
        BandContact::query()->create($data);

        return redirect()->route('admin.outreach.contacts.index')->with('status', 'Contacto creado.');
    }

    public function contactsUpdate(Request $request, BandContact $contact): RedirectResponse
    {
        $contact->update($this->validateContact($request));

        return redirect()->route('admin.outreach.contacts.index')->with('status', 'Contacto actualizado.');
    }

    public function campaigns(): View
    {
        return view('admin.outreach.campaigns.index', [
            'campaigns' => OutreachCampaign::query()->with('template')->latest()->paginate(20),
        ]);
    }

    public function campaignsCreate(): View
    {
        return view('admin.outreach.campaigns.create', [
            'templates' => OutreachTemplate::query()->active()->latest()->get(),
            'contacts' => BandContact::query()->with('radioArtist')->whereNotNull('email')->where('email', '<>', '')->orderBy('status')->orderBy('email')->get(),
        ]);
    }

    public function campaignsStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer', 'exists:outreach_templates,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'contact_ids' => ['required', 'array', 'min:1'],
            'contact_ids.*' => ['integer', 'exists:band_contacts,id'],
        ]);

        $template = OutreachTemplate::query()->findOrFail((int) $data['template_id']);
        $contacts = BandContact::query()->with('radioArtist')->whereIn('id', $data['contact_ids'])->get();
        if ($contacts->isEmpty()) {
            return back()->withInput()->withErrors(['contact_ids' => 'Debes seleccionar al menos un contacto válido.']);
        }

        $campaign = OutreachCampaign::query()->create([
            'template_id' => $template->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sent_count' => 0,
            'opened_count' => 0,
            'responded_count' => 0,
            'sent_at' => now(),
        ]);

        SendOutreachEmailsJob::dispatch($campaign->id, $contacts->pluck('id')->all());

        return redirect()->route('admin.outreach.campaigns.show', $campaign)->with('status', 'Campaña creada y enviada a cola.');
    }

    public function campaignsShow(OutreachCampaign $campaign): View
    {
        return view('admin.outreach.campaigns.show', [
            'campaign' => $campaign->load(['template', 'logs.bandContact.radioArtist']),
            'logs' => $campaign->logs()->latest()->paginate(30),
        ]);
    }

    public function sendTest(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'template_id' => ['nullable', 'integer', 'exists:outreach_templates,id'],
        ]);

        $template = isset($data['template_id'])
            ? OutreachTemplate::query()->findOrFail((int) $data['template_id'])
            : OutreachTemplate::query()->active()->first();

        if (! $template instanceof OutreachTemplate) {
            return back()->withInput()->withErrors(['template_id' => 'No hay plantillas activas disponibles.']);
        }

        $sample = BandContact::query()->with('radioArtist')->first() ?? new BandContact([
            'contact_person' => 'Contacto demo',
        ]);

        if (! $sample->relationLoaded('radioArtist')) {
            $sample->setRelation('radioArtist', new RadioArtist([
                'name' => 'Seven Rock Band',
                'genre' => 'Rock',
                'country' => 'ES',
            ]));
        }

        Mail::to($data['email'])->send(new OutreachMail(
            subjectLine: $template->renderSubject($sample),
            bodyHtml: $template->renderBody($sample),
            campaignName: 'Prueba de campaña',
            bandName: $sample->bandName(),
            contactPerson: (string) ($sample->contact_person ?? ''),
        ));

        return back()->with('status', 'Correo de prueba enviado.');
    }

    private function stats(): array
    {
        return [
            'contacts' => BandContact::query()->count(),
            'sent' => OutreachLog::query()->where('status', 'sent')->count(),
            'opened' => OutreachLog::query()->where('status', 'opened')->count(),
            'responded' => OutreachLog::query()->where('status', 'responded')->count(),
            'registered' => BandContact::query()->where('status', 'registered')->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]) + [
            'is_active' => $request->boolean('is_active'),
            'variables' => $request->input('variables', OutreachTemplate::defaultVariables()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateContact(Request $request): array
    {
        return $request->validate([
            'radio_artist_id' => ['nullable', 'integer', 'exists:radio_artists,id'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'facebook' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'in:pending,contacted,responded,registered,not_interested,invalid'],
        ]);
    }

    private function extractArtistEmail(RadioArtist $artist): ?string
    {
        foreach ((array) ($artist->official_links ?? []) as $link) {
            if (is_string($link) && filter_var($link, FILTER_VALIDATE_EMAIL)) {
                return $link;
            }

            if (is_array($link)) {
                foreach (['email', 'mail'] as $key) {
                    $candidate = trim((string) ($link[$key] ?? ''));
                    if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                        return $candidate;
                    }
                }

                foreach (['url', 'href'] as $key) {
                    $candidate = trim((string) ($link[$key] ?? ''));
                    if (str_starts_with(strtolower($candidate), 'mailto:')) {
                        $candidate = substr($candidate, 7);
                    }

                    if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                        return $candidate;
                    }
                }
            }
        }

        return null;
    }

    private function extractArtistLink(RadioArtist $artist, string $needle): ?string
    {
        foreach ((array) ($artist->official_links ?? []) as $link) {
            if (! is_array($link)) {
                continue;
            }

            $label = strtolower(trim((string) ($link['label'] ?? '')));
            $url = trim((string) ($link['url'] ?? $link['href'] ?? ''));

            if ($label !== '' && str_contains($label, $needle) && filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
        }

        return null;
    }
}
