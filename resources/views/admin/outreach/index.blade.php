<x-layouts.admin title="Convocatoria Bandas">
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Band Outreach Manager</h1>
                <p class="mt-3 max-w-3xl text-sm text-[#7b7b7b]">
                    Gestiona contactos, plantillas y campañas para invitar bandas a registrarse como talentos.
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.outreach.templates.index') }}" class="lucille-button">Plantillas</a>
                <a href="{{ route('admin.outreach.contacts.index') }}" class="lucille-button">Contactos</a>
                <a href="{{ route('admin.outreach.campaigns.index') }}" class="lucille-button-solid">Campañas</a>
            </div>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['label' => 'Contactos', 'value' => $stats['contacts']],
                ['label' => 'Enviados', 'value' => $stats['sent']],
                ['label' => 'Abiertos', 'value' => $stats['opened']],
                ['label' => 'Registrados', 'value' => $stats['registered']],
            ] as $card)
                <div class="border border-[#2b2b2b] bg-[#151515] p-5">
                    <div class="text-xs uppercase tracking-[.2em] text-[#7b7b7b]">{{ $card['label'] }}</div>
                    <div class="mt-2 font-display text-3xl text-[#dcdcdc]">{{ $card['value'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Campañas recientes</h2>
                <a href="{{ route('admin.outreach.campaigns.create') }}" class="lucille-button-solid">Nueva campaña</a>
            </div>
            <div class="mt-5 space-y-3">
                @forelse ($campaigns as $campaign)
                    <a href="{{ route('admin.outreach.campaigns.show', $campaign) }}" class="block border border-[#2b2b2b] bg-[#151515] p-4 transition hover:border-[#3b3b3b]">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <div class="text-sm text-[#dcdcdc]">{{ $campaign->name }}</div>
                                <div class="text-xs text-[#7b7b7b]">{{ $campaign->template?->name ?? 'Sin plantilla' }}</div>
                            </div>
                            <div class="text-right text-xs text-[#7b7b7b]">
                                <div>{{ $campaign->sent_count }} enviados</div>
                                <div>{{ $campaign->completed_at ? 'Completada' : 'En cola' }}</div>
                            </div>
                        </div>
                    </a>
                @empty
                    <p class="text-sm text-[#7b7b7b]">Todavía no hay campañas.</p>
                @endforelse
            </div>
        </section>

        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Contactos recientes</h2>
                <a href="{{ route('admin.outreach.contacts.index') }}" class="lucille-button">Abrir listado</a>
            </div>
            <div class="mt-5 space-y-3">
                @forelse ($recentContacts as $contact)
                    <div class="border border-[#2b2b2b] bg-[#151515] p-4">
                        <div class="text-sm text-[#dcdcdc]">{{ $contact->displayName() }}</div>
                        <div class="text-xs text-[#7b7b7b]">{{ $contact->email ?: 'Sin email' }} · {{ $contact->status }}</div>
                    </div>
                @empty
                    <p class="text-sm text-[#7b7b7b]">Aún no hay contactos.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.admin>
