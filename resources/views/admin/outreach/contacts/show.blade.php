<x-layouts.admin title="Ficha de contacto de banda">
    @php
        $statusLabels = [
            'pending' => 'Pendiente',
            'contacted' => 'Contactada',
            'responded' => 'Con respuesta',
            'registered' => 'Registrada',
            'not_interested' => 'Sin interés',
            'invalid' => 'Correo inválido',
        ];
        $sourceLabels = [
            'producer' => 'Productor / Programa',
            'self' => 'Solicitud propia',
            'other' => 'Otro origen',
        ];
    @endphp

    <div class="mx-auto max-w-6xl space-y-5 font-sans">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Contactos e invitaciones · Ficha de seguimiento</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">{{ $contact->displayName() }}</h1>
                <p class="mt-1 text-xs text-gray-400">
                    {{ $contact->programLabel() }} · Estado: <strong class="text-white">{{ $statusLabels[$contact->status] ?? $contact->status }}</strong>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.outreach.contacts.edit', $contact) }}" class="inline-flex h-9 items-center rounded-lg bg-[#c32720] px-3.5 text-xs font-semibold text-white hover:bg-[#a8201a]">
                    Editar contacto
                </a>
                <a href="{{ route('admin.outreach.contacts.index') }}" class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                    Volver a contactos
                </a>
            </div>
        </header>

        <section class="grid gap-5 lg:grid-cols-[1fr_1.2fr]">
            <div class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-4">
                <h2 class="font-display text-sm uppercase tracking-wider text-white border-b border-white/5 pb-2.5">Datos de la banda y requisitos</h2>
                <dl class="grid grid-cols-1 gap-2.5 text-xs sm:grid-cols-2">
                    <div class="rounded-lg border border-white/5 bg-[#151518] p-2.5">
                        <dt class="text-[11px] text-gray-400">Programa asignado</dt>
                        <dd class="mt-0.5 font-medium text-white">{{ $contact->program_code ?: 'Sin código' }} ({{ $contact->programLabel() }})</dd>
                    </div>
                    <div class="rounded-lg border border-white/5 bg-[#151518] p-2.5">
                        <dt class="text-[11px] text-gray-400">Origen</dt>
                        <dd class="mt-0.5 font-medium text-white">{{ $sourceLabels[$contact->referral_source ?: 'producer'] ?? $contact->referral_source }}</dd>
                    </div>
                    <div class="rounded-lg border border-white/5 bg-[#151518] p-2.5">
                        <dt class="text-[11px] text-gray-400">Correo electrónico</dt>
                        <dd class="mt-0.5 font-medium text-white break-all">{{ $contact->email ?: 'Sin correo' }}</dd>
                    </div>
                    <div class="rounded-lg border border-white/5 bg-[#151518] p-2.5">
                        <dt class="text-[11px] text-gray-400">Persona / Teléfono</dt>
                        <dd class="mt-0.5 font-medium text-white">{{ $contact->contact_person ?: 'Sin contacto' }} · {{ $contact->phone ?: 'Sin teléfono' }}</dd>
                    </div>
                    <div class="rounded-lg border border-white/5 bg-[#151518] p-2.5">
                        <dt class="text-[11px] text-gray-400">Requisitos técnicos</dt>
                        <dd class="mt-0.5 font-medium text-white">{{ $contact->specsBadge() }}</dd>
                    </div>
                    <div class="rounded-lg border border-white/5 bg-[#151518] p-2.5">
                        <dt class="text-[11px] text-gray-400">Último contacto / Material</dt>
                        <dd class="mt-0.5 font-medium text-white">{{ $contact->last_contacted_at?->format('d/m/Y H:i') ?? 'Nunca' }} · {{ $contact->materials_received_at?->format('d/m/Y H:i') ?? 'Pendiente' }}</dd>
                    </div>
                </dl>

                <div class="rounded-lg border border-white/5 bg-[#151518] p-3 text-xs text-gray-300">
                    <div class="mb-1 text-[11px] font-semibold text-gray-400">Notas de seguimiento</div>
                    {!! nl2br(e((string) $contact->notes)) ?: '<span class="text-gray-500">Sin notas registradas.</span>' !!}
                </div>
            </div>

            <div class="rounded-xl border border-white/10 bg-[#101012] p-5 space-y-3.5">
                <div class="flex items-center justify-between border-b border-white/5 pb-2.5">
                    <h2 class="font-display text-sm uppercase tracking-wider text-white">Historial de invitaciones de esta banda</h2>
                    <span class="text-[11px] text-gray-400">{{ $contact->logs->count() }} registros</span>
                </div>
                <div class="space-y-2.5">
                    @forelse ($contact->logs as $log)
                        <div class="rounded-lg border border-white/5 bg-[#151518] p-3 text-xs space-y-1">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-white">{{ $log->campaign?->name ?? 'Campaña de invitación' }}</span>
                                <span class="inline-flex items-center rounded border border-white/10 bg-white/[0.04] px-2 py-0.5 text-[10px] font-medium text-gray-300">
                                    {{ $log->status === 'sent' ? 'Enviado (SMTP)' : $log->status }}
                                </span>
                            </div>
                            <div class="text-[11px] text-gray-400">{{ $log->sent_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</div>
                            <div class="text-xs text-gray-300">{{ $log->subject }}</div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-white/5 bg-[#151518]/50 p-6 text-center text-xs text-gray-400">
                            No hay envíos de invitación registrados para este contacto.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
</x-layouts.admin>
