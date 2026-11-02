<x-layouts.admin title="Campañas de invitación a bandas">
    <div class="mx-auto max-w-7xl space-y-5 font-sans">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Contactos e invitaciones · Envíos por lote</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">Campañas de invitación</h1>
                <p class="mt-1 max-w-3xl text-xs text-gray-400">
                    Lotes de invitación dirigidos a bandas seleccionadas, contactos de un programa o productores de radio (independiente de <a href="{{ route('admin.marketing.index') }}" class="text-gray-300 underline hover:text-white">Campañas de difusión</a>).
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.outreach.index') }}" class="inline-flex h-9 items-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                    Resumen
                </a>
                <a href="{{ route('admin.outreach.campaigns.create') }}" class="inline-flex h-9 items-center rounded-lg bg-[#c32720] px-3.5 text-xs font-semibold text-white hover:bg-[#a8201a]">
                    + Nueva campaña de invitación
                </a>
            </div>
        </header>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-xs text-emerald-200" role="status">
                {{ session('status') }}
            </div>
        @endif

        {{-- Tabla en escritorio --}}
        <section class="hidden md:block rounded-xl border border-white/10 bg-[#101012] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-white/10 bg-[#151518] text-[11px] font-semibold text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Nombre de campaña</th>
                            <th class="px-4 py-3">Programa</th>
                            <th class="px-4 py-3">Plantilla aplicada</th>
                            <th class="px-4 py-3">Despachados</th>
                            <th class="px-4 py-3">Abiertos</th>
                            <th class="px-4 py-3">Respondidos</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse ($campaigns as $campaign)
                            <tr class="hover:bg-white/[0.02] align-middle">
                                <td class="px-4 py-3 font-semibold text-white">{{ $campaign->name }}</td>
                                <td class="px-4 py-3 font-mono text-[11px] text-gray-300">{{ $campaign->program?->program_code ?: 'Todos' }}</td>
                                <td class="px-4 py-3 text-gray-300">{{ $campaign->template?->name ?? 'Sin plantilla' }}</td>
                                <td class="px-4 py-3 text-gray-200">{{ $campaign->sent_count }}</td>
                                <td class="px-4 py-3 text-gray-300">{{ $campaign->opened_count }}</td>
                                <td class="px-4 py-3 text-gray-300">{{ $campaign->responded_count }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-medium {{ $campaign->completed_at ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-amber-500/30 bg-amber-500/10 text-amber-300' }}">
                                        {{ $campaign->completed_at ? 'Completada' : 'En cola' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.outreach.campaigns.show', $campaign) }}" class="inline-flex h-8 items-center rounded-lg border border-white/10 bg-white/[0.04] px-2.5 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                                        Ver envíos
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-xs text-gray-400">
                                    Todavía no se han creado campañas de invitación.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Tarjetas en móvil (390 px) --}}
        <section class="space-y-3 md:hidden">
            @forelse ($campaigns as $campaign)
                <article class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="text-sm font-semibold text-white">{{ $campaign->name }}</div>
                            <div class="text-xs text-gray-400">Plantilla: {{ $campaign->template?->name ?? 'Sin plantilla' }}</div>
                        </div>
                        <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-medium {{ $campaign->completed_at ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-amber-500/30 bg-amber-500/10 text-amber-300' }}">
                            {{ $campaign->completed_at ? 'Completada' : 'En cola' }}
                        </span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 rounded-lg border border-white/5 bg-[#151518] p-2.5 text-center text-[11px]">
                        <div>
                            <div class="text-gray-400">Enviados</div>
                            <div class="font-semibold text-white">{{ $campaign->sent_count }}</div>
                        </div>
                        <div>
                            <div class="text-gray-400">Abiertos</div>
                            <div class="font-semibold text-white">{{ $campaign->opened_count }}</div>
                        </div>
                        <div>
                            <div class="text-gray-400">Respuestas</div>
                            <div class="font-semibold text-white">{{ $campaign->responded_count }}</div>
                        </div>
                    </div>
                    <a href="{{ route('admin.outreach.campaigns.show', $campaign) }}" class="inline-flex h-8 w-full items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200">
                        Ver envíos individuales
                    </a>
                </article>
            @empty
                <div class="rounded-xl border border-white/10 bg-[#101012] p-6 text-center text-xs text-gray-400">
                    Todavía no hay campañas.
                </div>
            @endforelse
        </section>

        <div>{{ $campaigns->links() }}</div>
    </div>
</x-layouts.admin>
