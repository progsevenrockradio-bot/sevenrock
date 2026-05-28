<x-layouts.admin title="Convocatoria Bandas">
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Band Outreach Manager</h1>
                <p class="mt-3 max-w-3xl text-sm text-[#7b7b7b]">
                    Gestiona contactos, plantillas, programas y campañas para invitar bandas a registrarse como talentos.
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
                ['label' => 'Hoy', 'value' => $stats['today_sent']],
                ['label' => 'Semana', 'value' => $stats['week_sent']],
            ] as $card)
                <div class="border border-[#2b2b2b] bg-[#151515] p-5">
                    <div class="text-xs uppercase tracking-[.2em] text-[#7b7b7b]">{{ $card['label'] }}</div>
                    <div class="mt-2 font-display text-3xl text-[#dcdcdc]">{{ $card['value'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-[1.2fr_.8fr]">
        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Bandas por programa</h2>
                <a href="{{ route('admin.programs.index') }}" class="lucille-button">Programas</a>
            </div>
            <div class="mt-5 space-y-3">
                @forelse ($programStats as $row)
                    <div class="border border-[#2b2b2b] bg-[#151515] p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <div class="text-sm text-[#dcdcdc]">{{ $row['program']->program_code }} - {{ $row['program']->name }}</div>
                                <div class="text-xs text-[#7b7b7b]">{{ $row['program']->conductor }}</div>
                            </div>
                            <div class="text-right text-xs text-[#7b7b7b]">
                                <div>{{ $row['total'] }} contactos</div>
                                <div>{{ $row['ratio'] }}% conversion</div>
                            </div>
                        </div>
                        <div class="mt-3 h-2 w-full bg-[#202020]">
                            <div class="h-2 bg-[var(--color-lucille-accent)]" style="width: {{ min(100, $row['ratio']) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-[#7b7b7b]">No hay programas con contactos todavía.</p>
                @endforelse
            </div>
        </section>

        <section class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Últimas bandas con material</h2>
                <a href="{{ route('admin.outreach.contacts.index') }}" class="lucille-button">Ver contactos</a>
            </div>
            <div class="mt-5 space-y-3">
                @forelse ($recentSubmissions as $contact)
                    <div class="border border-[#2b2b2b] bg-[#151515] p-4">
                        <div class="text-sm text-[#dcdcdc]">{{ $contact->displayName() }}</div>
                        <div class="text-xs text-[#7b7b7b]">{{ $contact->programLabel() }} · {{ $contact->materials_received_at?->format('Y-m-d H:i') }}</div>
                        <div class="mt-1 text-xs text-[#9f9f9f]">{{ $contact->specsBadge() }}</div>
                    </div>
                @empty
                    <p class="text-sm text-[#7b7b7b]">Todavía no hay materiales recibidos.</p>
                @endforelse
            </div>
        </section>
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
                        <div class="text-xs text-[#7b7b7b]">{{ $contact->programLabel() }} · {{ $contact->email ?: 'Sin email' }} · {{ $contact->status }}</div>
                    </div>
                @empty
                    <p class="text-sm text-[#7b7b7b]">Aún no hay contactos.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.admin>
