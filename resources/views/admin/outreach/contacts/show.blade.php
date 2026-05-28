<x-layouts.admin title="Contacto outreach">
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $contact->displayName() }}</h1>
                <p class="mt-3 text-sm text-[#7b7b7b]">{{ $contact->programLabel() }} · {{ $contact->status }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.outreach.contacts.edit', $contact) }}" class="lucille-button">Editar</a>
                <a href="{{ route('admin.outreach.contacts.index') }}" class="lucille-button-solid">Volver</a>
            </div>
        </div>
    </div>

    <section class="mt-8 grid gap-6 lg:grid-cols-[1fr_1.2fr]">
        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
            <div class="space-y-3 text-sm text-[#9f9f9f]">
                <p><span class="text-[#dcdcdc]">Programa:</span> {{ $contact->program_code ?: 'Sin código' }}</p>
                <p><span class="text-[#dcdcdc]">Origen:</span> {{ $contact->referral_source ?: 'producer' }}</p>
                <p><span class="text-[#dcdcdc]">Email:</span> {{ $contact->email ?: 'Sin email' }}</p>
                <p><span class="text-[#dcdcdc]">Teléfono:</span> {{ $contact->phone ?: 'Sin teléfono' }}</p>
                <p><span class="text-[#dcdcdc]">Contacto:</span> {{ $contact->contact_person ?: 'Sin contacto' }}</p>
                <p><span class="text-[#dcdcdc]">Specs:</span> {{ $contact->specsBadge() }}</p>
                <p><span class="text-[#dcdcdc]">Último contacto:</span> {{ $contact->last_contacted_at?->format('Y-m-d H:i') ?? 'Nunca' }}</p>
                <p><span class="text-[#dcdcdc]">Material recibido:</span> {{ $contact->materials_received_at?->format('Y-m-d H:i') ?? 'Pendiente' }}</p>
                <p><span class="text-[#dcdcdc]">Backblaze:</span> {{ $contact->backblaze_path ?: 'Sin ruta' }}</p>
            </div>
            <div class="mt-6 border border-[#2b2b2b] bg-[#151515] p-4 text-sm text-[#cfcfcf]">
                {!! nl2br(e((string) $contact->notes)) ?: '<span class="text-[#7b7b7b]">Sin notas.</span>' !!}
            </div>
        </div>

        <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
            <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Historial de envíos</h2>
            <div class="mt-5 space-y-3">
                @forelse ($contact->logs as $log)
                    <div class="border border-[#2b2b2b] bg-[#151515] p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div class="text-sm text-[#dcdcdc]">{{ $log->campaign?->name ?? 'Campaña' }}</div>
                            <div class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">{{ $log->status }}</div>
                        </div>
                        <div class="mt-2 text-xs text-[#7b7b7b]">{{ $log->sent_at?->format('Y-m-d H:i') ?? 'Sin fecha' }}</div>
                        <div class="mt-2 text-sm text-[#9f9f9f]">{{ $log->subject }}</div>
                    </div>
                @empty
                    <p class="text-sm text-[#7b7b7b]">No hay envíos registrados.</p>
                @endforelse
            </div>
        </div>
    </section>
</x-layouts.admin>
