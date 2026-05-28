<x-layouts.admin title="Campaña outreach">
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $campaign->name }}</h1>
                <p class="mt-3 max-w-3xl text-sm text-[#7b7b7b]">
                    {{ $campaign->description ?: 'Sin descripción.' }}
                    @if ($campaign->program_code)
                        · Programa: {{ $campaign->program_code }}
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.outreach.campaigns.index') }}" class="lucille-button">Volver</a>
                <a href="{{ route('admin.outreach.campaigns.create') }}" class="lucille-button-solid">Nueva campaña</a>
            </div>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['label' => 'Enviados', 'value' => $campaign->sent_count],
                ['label' => 'Abiertos', 'value' => $campaign->opened_count],
                ['label' => 'Respondidos', 'value' => $campaign->responded_count],
                ['label' => 'Estado', 'value' => $campaign->completed_at ? 'Completada' : 'En proceso'],
            ] as $card)
                <div class="border border-[#2b2b2b] bg-[#151515] p-5">
                    <div class="text-xs uppercase tracking-[.2em] text-[#7b7b7b]">{{ $card['label'] }}</div>
                    <div class="mt-2 font-display text-3xl text-[#dcdcdc]">{{ $card['value'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <section class="mt-8 border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
        <h2 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">Envíos individuales</h2>
        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[980px] text-left text-sm">
                <thead class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">
                    <tr>
                        <th class="py-3 pr-4">Banda/Programa</th>
                        <th class="py-3 pr-4">Email</th>
                        <th class="py-3 pr-4">Asunto</th>
                        <th class="py-3 pr-4">Status</th>
                        <th class="py-3 pr-4">Error</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="border-t border-[#242424] align-top">
                            <td class="py-4 pr-4 text-[#dcdcdc]">
                                {{ $log->bandContact?->displayName() ?? $log->recipient_email }}
                                <div class="mt-1 text-xs text-[#7b7b7b]">{{ $log->bandContact?->programLabel() ?? $campaign->program?->name ?? 'Productor' }}</div>
                            </td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">{{ $log->recipient_email }}</td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">{{ $log->subject }}</td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">{{ $log->status }}</td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">{{ $log->error_message ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-[#7b7b7b]">Aún no hay envíos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $logs->links() }}</div>
    </section>
</x-layouts.admin>
