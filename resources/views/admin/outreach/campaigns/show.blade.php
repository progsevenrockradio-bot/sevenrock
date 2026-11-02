<x-layouts.admin title="Detalle de campaña de invitación">
    <div class="mx-auto max-w-7xl space-y-5 font-sans">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Contactos e invitaciones · Detalle de campaña</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">{{ $campaign->name }}</h1>
                <p class="mt-1 text-xs text-gray-400">
                    {{ $campaign->description ?: 'Sin descripción.' }}
                    @if ($campaign->program_code)
                        · Programa: <strong class="text-gray-200">{{ $campaign->program_code }}</strong>
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.outreach.campaigns.index') }}" class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                    Volver a campañas
                </a>
                <a href="{{ route('admin.outreach.campaigns.create') }}" class="inline-flex h-9 items-center rounded-lg bg-[#c32720] px-3.5 text-xs font-semibold text-white hover:bg-[#a8201a]">
                    + Nueva campaña
                </a>
            </div>
        </header>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-xs text-emerald-200" role="status">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ([
                ['label' => 'Despachados (SMTP)', 'value' => $campaign->sent_count],
                ['label' => 'Abiertos', 'value' => $campaign->opened_count],
                ['label' => 'Respondidos', 'value' => $campaign->responded_count],
                ['label' => 'Estado', 'value' => $campaign->completed_at ? 'Completada' : 'En proceso'],
            ] as $card)
                <div class="rounded-xl border border-white/10 bg-[#101012] p-3.5">
                    <div class="text-[11px] font-medium text-gray-400">{{ $card['label'] }}</div>
                    <div class="mt-1 font-display text-2xl text-white">{{ $card['value'] }}</div>
                </div>
            @endforeach
        </div>

        <section class="rounded-xl border border-white/10 bg-[#101012] overflow-hidden">
            <div class="border-b border-white/5 px-4 py-3">
                <h2 class="font-display text-sm uppercase tracking-wider text-white">Envíos individuales registrados</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-white/10 bg-[#151518] text-[11px] font-semibold text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Banda / Programa</th>
                            <th class="px-4 py-3">Destinatario</th>
                            <th class="px-4 py-3">Asunto</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3">Detalle de error</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse ($logs as $log)
                            <tr class="hover:bg-white/[0.02] align-middle">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-white">{{ $log->bandContact?->displayName() ?? $log->recipient_email }}</div>
                                    <div class="mt-0.5 text-[11px] text-gray-400">{{ $log->bandContact?->programLabel() ?? $campaign->program?->name ?? 'Productor' }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-300">{{ $log->recipient_email }}</td>
                                <td class="px-4 py-3 text-gray-200">{{ $log->subject }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded border border-white/10 bg-white/[0.04] px-2 py-0.5 text-[11px] text-gray-200">
                                        {{ $log->status === 'sent' ? 'Enviado (SMTP)' : $log->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-[11px] text-gray-400">{{ $log->error_message ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-xs text-gray-400">Aún no hay envíos registrados en esta campaña.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div>{{ $logs->links() }}</div>
    </div>
</x-layouts.admin>
