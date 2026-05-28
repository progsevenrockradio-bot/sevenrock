<x-layouts.admin title="Campañas Outreach">
    <div class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">Campañas</h1>
                <p class="mt-3 max-w-3xl text-sm text-[#7b7b7b]">Lanza campañas seleccionando una plantilla y una lista de contactos.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.outreach.index') }}" class="lucille-button">Panel</a>
                <a href="{{ route('admin.outreach.campaigns.create') }}" class="lucille-button-solid">Nueva campaña</a>
            </div>
        </div>
    </div>

    <section class="mt-8 border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-6">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[960px] text-left text-sm">
                <thead class="text-xs uppercase tracking-[.18em] text-[#7b7b7b]">
                    <tr>
                        <th class="py-3 pr-4">Nombre</th>
                        <th class="py-3 pr-4">Plantilla</th>
                        <th class="py-3 pr-4">Enviados</th>
                        <th class="py-3 pr-4">Estado</th>
                        <th class="py-3 pr-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($campaigns as $campaign)
                        <tr class="border-t border-[#242424]">
                            <td class="py-4 pr-4 text-[#dcdcdc]">{{ $campaign->name }}</td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">{{ $campaign->template?->name ?? 'Sin plantilla' }}</td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">{{ $campaign->sent_count }}</td>
                            <td class="py-4 pr-4 text-[#9f9f9f]">{{ $campaign->completed_at ? 'Completada' : 'En cola' }}</td>
                            <td class="py-4 pr-4">
                                <a href="{{ route('admin.outreach.campaigns.show', $campaign) }}" class="lucille-button">Ver</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-[#7b7b7b]">Todavía no hay campañas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $campaigns->links() }}</div>
    </section>
</x-layouts.admin>
