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
use App\Models\MasterProgram;
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
            'campaigns' => OutreachCampaign::query()->with('template', 'program')->latest()->limit(8)->get(),
            'recentContacts' => BandContact::query()->with(['radioArtist', 'program'])->latest()->limit(8)->get(),
            'programStats' => $this->programStats(),
            'recentSubmissions' => BandContact::query()
                ->with(['radioArtist', 'program'])
                ->whereNotNull('materials_received_at')
                ->latest('materials_received_at')
                ->limit(5)
                ->get(),
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
            'programs' => MasterProgram::query()->orderBy('nombre')->get(),
            'sampleContacts' => BandContact::query()->with('radioArtist')->orderBy('id')->limit(20)->get(),
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
            'programs' => MasterProgram::query()->orderBy('nombre')->get(),
            'sampleContacts' => BandContact::query()->with('radioArtist')->orderBy('id')->limit(20)->get(),
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
            'program_id' => ['nullable', 'integer', 'exists:master_programs,id'],
            'contact_id' => ['nullable', 'integer', 'exists:band_contacts,id'],
            'band_name' => ['nullable', 'string'],
            'band_genre' => ['nullable', 'string'],
            'band_country' => ['nullable', 'string'],
            'contact_person' => ['nullable', 'string'],
        ]);

        $program = isset($data['program_id'])
            ? MasterProgram::query()->find($data['program_id'])
            : null;
        $contact = isset($data['contact_id'])
            ? BandContact::query()->with('radioArtist', 'program')->find($data['contact_id'])
            : new BandContact([
                'contact_person' => $data['contact_person'] ?? 'Contacto demo',
            ]);

        if ($contact === null) {
            $contact = new BandContact([
                'contact_person' => $data['contact_person'] ?? 'Contacto demo',
            ]);
        }

        if ($program instanceof MasterProgram) {
            $contact->setRelation('program', $program);
        }

        $template = new OutreachTemplate([
            'subject' => $data['subject'],
            'body' => $data['body'],
        ]);

        if (($data['band_name'] ?? '') !== '') {
            $contact->setRelation('radioArtist', new RadioArtist([
                'name' => (string) $data['band_name'],
                'genre' => (string) ($data['band_genre'] ?? 'Rock'),
                'country' => (string) ($data['band_country'] ?? 'ES'),
            ]));
        }

        return response()->json([
            'subject' => $template->renderSubject($program, $contact),
            'body' => $template->renderBody($program, $contact),
        ]);
    }

    public function contacts(Request $request): View
    {
        $query = BandContact::query()->with(['radioArtist', 'program'])->latest();

        if ($status = trim((string) $request->input('status', ''))) {
            $query->where('status', $status);
        }

        if ($programCode = trim((string) $request->input('program_code', ''))) {
            $query->where('program_code', $programCode);
        }

        if ($referralSource = trim((string) $request->input('referral_source', ''))) {
            $query->where('referral_source', $referralSource);
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($inner) use ($search): void {
                $inner->where('email', 'like', '%' . $search . '%')
                    ->orWhere('contact_person', 'like', '%' . $search . '%')
                    ->orWhereHas('radioArtist', function ($artistQuery) use ($search): void {
                        $artistQuery->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('program', function ($programQuery) use ($search): void {
                        $programQuery->where('program_code', 'like', '%' . $search . '%')
                            ->orWhere('nombre', 'like', '%' . $search . '%');
                    });
            });
        }

        return view('admin.outreach.contacts.index', [
            'contacts' => $query->paginate(20)->withQueryString(),
            'statusFilter' => (string) $request->input('status', ''),
            'programCodeFilter' => (string) $request->input('program_code', ''),
            'referralSourceFilter' => (string) $request->input('referral_source', ''),
            'search' => (string) $request->input('search', ''),
            'programs' => MasterProgram::query()->orderBy('nombre')->get(),
        ]);
    }

    public function contactsCreate(): View
    {
        return view('admin.outreach.contacts.create', [
            'contact' => new BandContact([
                'status' => 'pending',
                'referral_source' => 'producer',
            ]),
            'programs' => MasterProgram::query()->orderBy('nombre')->get(),
        ]);
    }

    public function contactsEdit(BandContact $contact): View
    {
        return view('admin.outreach.contacts.edit', [
            'contact' => $contact->load('radioArtist'),
            'programs' => MasterProgram::query()->orderBy('nombre')->get(),
        ]);
    }

    public function contactsShow(BandContact $contact): View
    {
        return view('admin.outreach.contacts.show', [
            'contact' => $contact->load(['radioArtist', 'program', 'logs.campaign.template']),
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
                'program_code' => null,
                'referral_source' => 'producer',
                'email' => $this->extractArtistEmail($artist),
                'phone' => null,
                'facebook' => $this->extractArtistLink($artist, 'facebook'),
                'instagram' => $this->extractArtistLink($artist, 'instagram'),
                'contact_person' => null,
                'notes' => 'Importado desde Radio Artists el ' . Carbon::now()->toDateTimeString(),
                'status' => 'pending',
                'image_specs_met' => false,
                'audio_specs_met' => false,
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
            'contacts' => BandContact::query()->with(['radioArtist', 'program'])->whereNotNull('email')->where('email', '<>', '')->orderBy('status')->orderBy('email')->get(),
            'programs' => MasterProgram::query()->orderBy('nombre')->get(),
        ]);
    }

    public function campaignsStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer', 'exists:outreach_templates,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'recipient_mode' => ['required', 'in:contacts,program,producers'],
            'program_code' => ['nullable', 'string', 'exists:master_programs,program_code'],
            'status_filter' => ['nullable', 'in:pending,contacted,responded,registered,not_interested,invalid'],
            'contact_ids' => ['nullable', 'array'],
            'contact_ids.*' => ['integer', 'exists:band_contacts,id'],
        ]);

        $template = OutreachTemplate::query()->findOrFail((int) $data['template_id']);
        $recipientMode = (string) $data['recipient_mode'];
        $programCode = trim((string) ($data['program_code'] ?? '')) ?: null;
        $statusFilter = trim((string) ($data['status_filter'] ?? '')) ?: null;

        $contacts = collect();
        $producerRecipients = collect();
        if ($recipientMode === 'contacts') {
            $contactQuery = BandContact::query()->with(['radioArtist', 'program']);
            if ($programCode !== null) {
                $contactQuery->where('program_code', $programCode);
            }
            if ($statusFilter !== null) {
                $contactQuery->where('status', $statusFilter);
            }
            if (! empty($data['contact_ids'])) {
                $contactQuery->whereIn('id', $data['contact_ids']);
            }
            $contacts = $contactQuery->get();
            if ($contacts->isEmpty()) {
                return back()->withInput()->withErrors(['contact_ids' => 'Debes seleccionar al menos un contacto válido.']);
            }
        } elseif ($recipientMode === 'program') {
            $contacts = BandContact::query()
                ->with(['radioArtist', 'program'])
                ->where('program_code', $programCode)
                ->when($statusFilter !== null, fn ($query) => $query->where('status', $statusFilter))
                ->get();
            if ($contacts->isEmpty()) {
                return back()->withInput()->withErrors(['program_code' => 'No se encontraron contactos para ese programa.']);
            }
        } else {
            $producerRecipients = MasterProgram::query()
                ->whereNotNull('email_notificacion')
                ->where('email_notificacion', '<>', '')
                ->get();
            if ($producerRecipients->isEmpty()) {
                return back()->withInput()->withErrors(['recipient_mode' => 'No hay productores con correo disponible.']);
            }
        }

        $campaign = OutreachCampaign::query()->create([
            'template_id' => $template->id,
            'program_code' => $programCode,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sent_count' => 0,
            'opened_count' => 0,
            'responded_count' => 0,
            'sent_at' => now(),
        ]);

        SendOutreachEmailsJob::dispatch(
            $campaign->id,
            $recipientMode,
            $contacts->pluck('id')->all(),
            $programCode,
            $statusFilter,
            $producerRecipients->pluck('id')->all()
        );

        return redirect()->route('admin.outreach.campaigns.show', $campaign)->with('status', 'Campaña creada y enviada a cola.');
    }

    public function campaignsShow(OutreachCampaign $campaign): View
    {
        return view('admin.outreach.campaigns.show', [
            'campaign' => $campaign->load(['template', 'program', 'logs.bandContact.radioArtist']),
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

        $sampleProgram = MasterProgram::query()->orderBy('nombre')->first();
        $sample = BandContact::query()->with(['radioArtist', 'program'])->first() ?? new BandContact([
            'contact_person' => 'Contacto demo',
        ]);

        if (! $sample->relationLoaded('radioArtist')) {
            $sample->setRelation('radioArtist', new RadioArtist([
                'name' => 'Seven Rock Band',
                'genre' => 'Rock',
                'country' => 'ES',
            ]));
        }

        if ($sampleProgram instanceof MasterProgram) {
            $sample->setRelation('program', $sampleProgram);
        }

        Mail::to($data['email'])->send(new OutreachMail(
            subjectLine: $template->renderSubject($sampleProgram, $sample),
            bodyHtml: $template->renderBody($sampleProgram, $sample),
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
            'today_sent' => OutreachLog::query()->whereDate('sent_at', today())->count(),
            'week_sent' => OutreachLog::query()->whereBetween('sent_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
        ];
    }

    /**
     * @return array<int, array{program: MasterProgram, total: int, registered: int, ratio: float}>
     */
    private function programStats(): array
    {
        return MasterProgram::query()
            ->orderBy('nombre')
            ->get()
            ->map(function (MasterProgram $program): array {
                $total = (int) $program->outreachContacts()->count();
                $registered = (int) $program->outreachContacts()->where('status', 'registered')->count();

                return [
                    'program' => $program,
                    'total' => $total,
                    'registered' => $registered,
                    'ratio' => $total > 0 ? round(($registered / $total) * 100, 1) : 0.0,
                ];
            })
            ->all();
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
            'program_code' => ['nullable', 'string', 'exists:master_programs,program_code'],
            'referral_source' => ['required', 'in:producer,self,other'],
            'image_specs_met' => ['nullable', 'boolean'],
            'audio_specs_met' => ['nullable', 'boolean'],
            'submission_deadline' => ['nullable', 'date'],
            'materials_received_at' => ['nullable', 'date'],
            'materials_note' => ['nullable', 'string'],
            'backblaze_path' => ['nullable', 'string', 'max:255'],
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
