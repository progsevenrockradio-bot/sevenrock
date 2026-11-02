<x-layouts.admin title="Contactos e invitaciones a bandas">
    @php
        $statusLabels = [
            'pending' => 'Pendiente',
            'contacted' => 'Contactada',
            'responded' => 'Con respuesta',
            'registered' => 'Registrada',
            'not_interested' => 'Sin interés',
            'invalid' => 'Correo inválido',
        ];
    @endphp

    <div class="mx-auto max-w-7xl space-y-5 font-sans">
        {{-- Cabecera compacta --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Comunicación · Circuito de convocatorias por programa</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">Contactos e invitaciones</h1>
                <p class="mt-1 max-w-3xl text-xs text-gray-400">
                    Seguimiento de bandas propuestas por programas de radio, recepción de material técnico (audio e imagen) y envío de invitaciones para registrarse en el Muro del Rock.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.outreach.contacts.index') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    <span>Contactos ({{ $stats['contacts'] }})</span>
                </a>
                <a href="{{ route('admin.outreach.templates.index') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    <span>Plantillas de invitación</span>
                </a>
                <a href="{{ route('admin.outreach.campaigns.index') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    <span>Campañas de invitación</span>
                </a>
                <a href="{{ route('admin.outreach.campaigns.create') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#c32720] px-3.5 text-xs font-semibold text-white hover:bg-[#a8201a] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    <span>+ Nueva campaña de invitación</span>
                </a>
            </div>
        </header>

        {{-- Nota de alcance y separación respecto a Campañas de difusión --}}
        <div class="rounded-xl border border-white/10 bg-[#101012] px-4 py-3 text-xs text-gray-300 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <strong class="text-white font-semibold">Circuito independiente de convocatorias (Outreach):</strong>
                este módulo utiliza su propio directorio (<code class="text-[11px] text-gray-400">BandContact</code>) e historial de envíos por programa (<code class="text-[11px] text-gray-400">OutreachLog</code>), separado de los boletines y contactos generales de <a href="{{ route('admin.marketing.index') }}" class="text-[#ff8a84] underline hover:text-white">Campañas de difusión</a>.
            </div>
            <a href="{{ route('admin.outreach.contacts.create') }}" class="shrink-0 inline-flex h-8 items-center rounded-lg border border-white/10 bg-white/[0.04] px-2.5 text-[11px] font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                + Registrar contacto de banda
            </a>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-xs text-emerald-200" role="status">
                {{ session('status') }}
            </div>
        @endif

        {{-- Indicadores principales --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-xl border border-white/10 bg-[#101012] p-3.5">
                <div class="text-[11px] font-medium text-gray-400">Contactos en seguimiento</div>
                <div class="mt-1 font-display text-2xl text-white">{{ $stats['contacts'] }}</div>
                <div class="mt-0.5 text-[11px] text-gray-400">Registradas en el Muro: <span class="text-emerald-400 font-medium">{{ $stats['registered'] ?? 0 }}</span></div>
            </div>
            <div class="rounded-xl border border-white/10 bg-[#101012] p-3.5">
                <div class="text-[11px] font-medium text-gray-400">Invitaciones despachadas</div>
                <div class="mt-1 font-display text-2xl text-white">{{ $stats['sent'] }}</div>
                <div class="mt-0.5 text-[11px] text-gray-400">Con respuesta: {{ $stats['responded'] ?? 0 }}</div>
            </div>
            <div class="rounded-xl border border-white/10 bg-[#101012] p-3.5">
                <div class="text-[11px] font-medium text-gray-400">Enviadas hoy</div>
                <div class="mt-1 font-display text-2xl text-white">{{ $stats['today_sent'] }}</div>
                <div class="mt-0.5 text-[11px] text-gray-400">Actividad del día actual</div>
            </div>
            <div class="rounded-xl border border-white/10 bg-[#101012] p-3.5">
                <div class="text-[11px] font-medium text-gray-400">Enviadas esta semana</div>
                <div class="mt-1 font-display text-2xl text-white">{{ $stats['week_sent'] }}</div>
                <div class="mt-0.5 text-[11px] text-gray-400">Acumulado semanal</div>
            </div>
        </div>

        <div class="grid gap-5 xl:grid-cols-[1.2fr_.8fr]">
            {{-- Bandas por programa --}}
            <section class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3.5">
                <div class="flex items-center justify-between gap-3 border-b border-white/5 pb-3">
                    <div>
                        <h2 class="font-display text-sm uppercase tracking-wider text-white">Bandas vinculadas por programa</h2>
                        <p class="text-[11px] text-gray-400">Contactos asignados y porcentaje de registro en el Muro del Rock.</p>
                    </div>
                    <a href="{{ route('admin.programs.index') }}" class="inline-flex h-8 items-center rounded-lg border border-white/10 bg-white/[0.04] px-2.5 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                        Invitaciones a programas
                    </a>
                </div>

                <div class="space-y-2.5 max-h-[380px] overflow-y-auto pr-1">
                    @forelse ($programStats as $row)
                        <div class="rounded-lg border border-white/5 bg-[#151518] p-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-xs font-semibold text-white truncate">
                                        <span class="font-mono text-[11px] text-gray-300">{{ $row['program']->program_code ?: 'S/C' }}</span>
                                        · {{ $row['program']->name }}
                                    </div>
                                    <div class="text-[11px] text-gray-400 truncate">{{ $row['program']->conductor ?: 'Sin conductor asignado' }}</div>
                                </div>
                                <div class="text-right shrink-0 text-[11px] text-gray-300">
                                    <div class="font-medium text-white">{{ $row['total'] }} contactos</div>
                                    <div class="text-gray-400">{{ $row['ratio'] }}% registradas</div>
                                </div>
                            </div>
                            <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-white/5">
                                <div class="h-full bg-[#c32720]" style="width: {{ min(100, $row['ratio']) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-white/5 bg-[#151518]/50 p-6 text-center text-xs text-gray-400">
                            No hay programas con contactos registrados todavía.
                        </div>
                    @endforelse
                </div>
            </section>

            {{-- Últimas bandas con material recibido --}}
            <section class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3.5">
                <div class="flex items-center justify-between gap-3 border-b border-white/5 pb-3">
                    <div>
                        <h2 class="font-display text-sm uppercase tracking-wider text-white">Últimas bandas con material recibido</h2>
                        <p class="text-[11px] text-gray-400">Verificación de especificaciones técnicas de imagen y audio.</p>
                    </div>
                    <a href="{{ route('admin.outreach.contacts.index') }}" class="inline-flex h-8 items-center rounded-lg border border-white/10 bg-white/[0.04] px-2.5 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                        Ver contactos
                    </a>
                </div>

                <div class="space-y-2.5">
                    @forelse ($recentSubmissions as $contact)
                        <div class="rounded-lg border border-white/5 bg-[#151518] p-3 flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('admin.outreach.contacts.show', $contact) }}" class="text-xs font-semibold text-white hover:text-[#ff8a84] truncate block">
                                    {{ $contact->displayName() }}
                                </a>
                                <div class="mt-0.5 text-[11px] text-gray-400">
                                    {{ $contact->programLabel() }} · {{ $contact->materials_received_at?->format('d/m/Y H:i') }}
                                </div>
                                <div class="mt-1 text-[11px] text-gray-300">{{ $contact->specsBadge() }}</div>
                            </div>
                            <a href="{{ route('admin.outreach.contacts.edit', $contact) }}" class="shrink-0 inline-flex h-7 items-center rounded border border-white/10 bg-white/[0.04] px-2 text-[11px] text-gray-200 hover:bg-white/[0.08] hover:text-white">
                                Editar
                            </a>
                        </div>
                    @empty
                        <div class="rounded-lg border border-white/5 bg-[#151518]/50 p-6 text-center text-xs text-gray-400">
                            Todavía no hay recepciones de material registradas.
                        </div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            {{-- Campañas recientes --}}
            <section class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3.5">
                <div class="flex items-center justify-between gap-3 border-b border-white/5 pb-3">
                    <div>
                        <h2 class="font-display text-sm uppercase tracking-wider text-white">Campañas de invitación recientes</h2>
                        <p class="text-[11px] text-gray-400">Lotes enviados a contactos o productores de programas.</p>
                    </div>
                    <a href="{{ route('admin.outreach.campaigns.create') }}" class="inline-flex h-8 items-center rounded-lg bg-[#c32720] px-3 text-xs font-semibold text-white hover:bg-[#a8201a]">
                        Nueva campaña
                    </a>
                </div>

                <div class="space-y-2.5">
                    @forelse ($campaigns as $campaign)
                        <a href="{{ route('admin.outreach.campaigns.show', $campaign) }}" class="block rounded-lg border border-white/5 bg-[#151518] p-3 transition hover:border-white/15">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-xs font-semibold text-white truncate">{{ $campaign->name }}</div>
                                    <div class="mt-0.5 text-[11px] text-gray-400 truncate">Plantilla: {{ $campaign->template?->name ?? 'Sin plantilla' }}</div>
                                </div>
                                <div class="text-right shrink-0 text-[11px]">
                                    <div class="font-medium text-gray-200">{{ $campaign->sent_count }} enviados</div>
                                    <div class="{{ $campaign->completed_at ? 'text-emerald-400' : 'text-amber-300' }}">
                                        {{ $campaign->completed_at ? 'Completada' : 'En cola' }}
                                    </div>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="rounded-lg border border-white/5 bg-[#151518]/50 p-6 text-center text-xs text-gray-400">
                            Todavía no se han lanzado campañas de invitación.
                        </div>
                    @endforelse
                </div>
            </section>

            {{-- Contactos recientes --}}
            <section class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3.5">
                <div class="flex items-center justify-between gap-3 border-b border-white/5 pb-3">
                    <div>
                        <h2 class="font-display text-sm uppercase tracking-wider text-white">Contactos añadidos recientemente</h2>
                        <p class="text-[11px] text-gray-400">Últimas fichas incorporadas al directorio de convocatorias.</p>
                    </div>
                    <a href="{{ route('admin.outreach.contacts.index') }}" class="inline-flex h-8 items-center rounded-lg border border-white/10 bg-white/[0.04] px-2.5 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                        Abrir listado completo
                    </a>
                </div>

                <div class="space-y-2.5">
                    @forelse ($recentContacts as $contact)
                        <div class="rounded-lg border border-white/5 bg-[#151518] p-3 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('admin.outreach.contacts.show', $contact) }}" class="text-xs font-semibold text-white hover:text-[#ff8a84] truncate block">
                                    {{ $contact->displayName() }}
                                </a>
                                <div class="mt-0.5 text-[11px] text-gray-400 truncate">
                                    {{ $contact->programLabel() }} · {{ $contact->email ?: 'Sin correo' }}
                                </div>
                            </div>
                            <span class="shrink-0 inline-flex items-center rounded-md border border-white/10 bg-white/[0.04] px-2 py-0.5 text-[11px] text-gray-300">
                                {{ $statusLabels[$contact->status] ?? $contact->status }}
                            </span>
                        </div>
                    @empty
                        <div class="rounded-lg border border-white/5 bg-[#151518]/50 p-6 text-center text-xs text-gray-400">
                            Aún no hay contactos registrados.
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-layouts.admin>
