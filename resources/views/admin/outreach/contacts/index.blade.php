<x-layouts.admin title="Contactos de bandas (Convocatorias)">
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
        $hasActiveFilters = $search !== '' || $programCodeFilter !== '' || $referralSourceFilter !== '' || $statusFilter !== '';
    @endphp

    <div class="mx-auto max-w-7xl space-y-5 font-sans">
        {{-- Cabecera compacta --}}
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/10 pb-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#c32720]">Comunicación · Contactos e invitaciones</div>
                <h1 class="mt-0.5 font-display text-2xl uppercase tracking-[.08em] text-white">Directorio de contactos de bandas</h1>
                <p class="mt-1 max-w-3xl text-xs text-gray-400">
                    Bandas candidatas vinculadas a programas de radio y verificación de entrega de materiales técnicos (independiente de la lista general de <a href="{{ route('admin.marketing.index') }}" class="text-gray-300 underline hover:text-white">Campañas de difusión</a>).
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <form
                    action="{{ route('admin.outreach.contacts.import') }}"
                    method="POST"
                    data-confirm="¿Importar perfiles de artistas que aún no tengan ficha en este directorio de convocatorias?"
                    data-confirm-title="Importar desde Perfiles de artistas"
                    data-confirm-action="Importar"
                    data-confirm-tone="soft"
                >
                    @csrf
                    <button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]" title="Crea contactos pendientes a partir de Perfiles de artistas sin duplicar los existentes">
                        <span>Importar desde Perfiles de artistas</span>
                    </button>
                </form>
                <a href="{{ route('admin.outreach.index') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    <span>Resumen</span>
                </a>
                <a href="{{ route('admin.outreach.contacts.create') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#c32720] px-3.5 text-xs font-semibold text-white hover:bg-[#a8201a] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    <span>+ Nuevo contacto</span>
                </a>
            </div>
        </header>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-xs text-emerald-200" role="status">
                {{ session('status') }}
            </div>
        @endif

        {{-- Filtros con etiquetas visibles --}}
        <form method="GET" action="{{ route('admin.outreach.contacts.index') }}" class="rounded-xl border border-white/10 bg-[#101012] p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="filter-outreach-search" class="mb-1 block text-xs font-medium text-gray-300">Buscar banda, persona o correo</label>
                    <input
                        id="filter-outreach-search"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Nombre, correo o programa..."
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white placeholder-gray-500 focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    >
                </div>
                <div>
                    <label for="filter-outreach-program" class="mb-1 block text-xs font-medium text-gray-300">Programa asignado</label>
                    <select
                        id="filter-outreach-program"
                        name="program_code"
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    >
                        <option value="">Todos los programas</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->program_code }}" @selected($programCodeFilter === $program->program_code)>
                                {{ $program->program_code }} - {{ $program->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filter-outreach-source" class="mb-1 block text-xs font-medium text-gray-300">Origen del contacto</label>
                    <select
                        id="filter-outreach-source"
                        name="referral_source"
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    >
                        <option value="">Todos los orígenes</option>
                        @foreach ($sourceLabels as $value => $label)
                            <option value="{{ $value }}" @selected($referralSourceFilter === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filter-outreach-status" class="mb-1 block text-xs font-medium text-gray-300">Estado de seguimiento</label>
                    <select
                        id="filter-outreach-status"
                        name="status"
                        class="h-9 w-full rounded-lg border border-white/10 bg-[#151518] px-3 text-xs text-white focus:border-[#c32720] focus:outline-none focus:ring-1 focus:ring-[#c32720]"
                    >
                        <option value="">Todos los estados</option>
                        @foreach ($statusLabels as $statusKey => $statusName)
                            <option value="{{ $statusKey }}" @selected($statusFilter === $statusKey)>{{ $statusName }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-white/5 pt-3">
                <button type="submit" class="inline-flex h-8 items-center justify-center rounded-lg bg-[#c32720] px-3.5 text-xs font-semibold text-white hover:bg-[#a8201a] focus:outline-none focus:ring-2 focus:ring-[#c32720]">
                    Aplicar filtros
                </button>
                @if ($hasActiveFilters)
                    <a href="{{ route('admin.outreach.contacts.index') }}" class="inline-flex h-8 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-300 hover:bg-white/[0.08] hover:text-white">
                        Restablecer filtros
                    </a>
                @endif
            </div>
        </form>

        {{-- Tabla en escritorio --}}
        <section class="hidden md:block rounded-xl border border-white/10 bg-[#101012] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-white/10 bg-[#151518] text-[11px] font-semibold text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Banda y persona</th>
                            <th class="px-4 py-3">Programa y origen</th>
                            <th class="px-4 py-3">Correo electrónico</th>
                            <th class="px-4 py-3">Requisitos técnicos</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3">Último contacto / Material</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse ($contacts as $contact)
                            <tr class="hover:bg-white/[0.02] align-middle">
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.outreach.contacts.show', $contact) }}" class="font-semibold text-white hover:text-[#ff8a84]">
                                        {{ $contact->displayName() }}
                                    </a>
                                    <div class="mt-0.5 text-[11px] text-gray-400">{{ $contact->contact_person ?: 'Sin persona de contacto' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-mono text-[11px] text-gray-200">{{ $contact->program_code ?: 'Sin código' }}</div>
                                    <div class="mt-0.5 text-[11px] text-gray-400">{{ $contact->programLabel() }} · {{ $sourceLabels[$contact->referral_source ?: 'producer'] ?? $contact->referral_source }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-300">
                                    {{ $contact->email ?: 'Sin correo' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1.5">
                                        <span class="inline-flex items-center rounded border px-1.5 py-0.5 text-[10px] font-medium {{ $contact->image_specs_met ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-white/10 bg-white/[0.03] text-gray-400' }}">
                                            {{ $contact->image_specs_met ? 'Imagen OK' : 'Imagen pend.' }}
                                        </span>
                                        <span class="inline-flex items-center rounded border px-1.5 py-0.5 text-[10px] font-medium {{ $contact->audio_specs_met ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-white/10 bg-white/[0.03] text-gray-400' }}">
                                            {{ $contact->audio_specs_met ? 'Audio OK' : 'Audio pend.' }}
                                        </span>
                                    </div>
                                    <div class="mt-1 text-[11px] text-gray-400">{{ $contact->specsBadge() }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-md border border-white/10 bg-white/[0.04] px-2 py-0.5 text-[11px] font-medium text-gray-200">
                                        {{ $statusLabels[$contact->status] ?? $contact->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-[11px] text-gray-300">
                                    <div>Contacto: {{ $contact->last_contacted_at?->format('d/m/Y H:i') ?? 'Nunca' }}</div>
                                    <div class="mt-0.5 text-gray-400">Material: {{ $contact->materials_received_at?->format('d/m/Y H:i') ?? 'Pendiente' }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <a href="{{ route('admin.outreach.contacts.show', $contact) }}" class="inline-flex h-8 items-center rounded-lg border border-white/10 bg-white/[0.04] px-2.5 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                                            Ver ficha
                                        </a>
                                        <a href="{{ route('admin.outreach.contacts.edit', $contact) }}" class="inline-flex h-8 items-center rounded-lg border border-white/10 bg-white/[0.04] px-2.5 text-xs font-medium text-gray-200 hover:bg-white/[0.08] hover:text-white">
                                            Editar
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-xs text-gray-400">
                                    No se encontraron contactos con los filtros seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Tarjetas en móvil (390 px) --}}
        <section class="space-y-3 md:hidden">
            @forelse ($contacts as $contact)
                <article class="rounded-xl border border-white/10 bg-[#101012] p-4 space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <a href="{{ route('admin.outreach.contacts.show', $contact) }}" class="text-sm font-semibold text-white hover:text-[#ff8a84] block truncate">
                                {{ $contact->displayName() }}
                            </a>
                            <div class="text-xs text-gray-400 truncate">{{ $contact->email ?: 'Sin correo' }}</div>
                        </div>
                        <span class="shrink-0 inline-flex items-center rounded-md border border-white/10 bg-white/[0.04] px-2 py-0.5 text-[11px] font-medium text-gray-200">
                            {{ $statusLabels[$contact->status] ?? $contact->status }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 rounded-lg border border-white/5 bg-[#151518] p-2.5 text-[11px]">
                        <div>
                            <div class="text-gray-400">Programa</div>
                            <div class="font-medium text-white truncate">{{ $contact->programLabel() }}</div>
                        </div>
                        <div>
                            <div class="text-gray-400">Requisitos</div>
                            <div class="font-medium text-gray-200">{{ $contact->specsBadge() }}</div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-1">
                        <a href="{{ route('admin.outreach.contacts.show', $contact) }}" class="inline-flex h-8 flex-1 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] px-3 text-xs font-medium text-gray-200">
                            Ver ficha
                        </a>
                        <a href="{{ route('admin.outreach.contacts.edit', $contact) }}" class="inline-flex h-8 flex-1 items-center justify-center rounded-lg bg-[#c32720] px-3 text-xs font-semibold text-white">
                            Editar
                        </a>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-white/10 bg-[#101012] p-6 text-center text-xs text-gray-400">
                    No se encontraron contactos.
                </div>
            @endforelse
        </section>

        <div>{{ $contacts->links() }}</div>
    </div>
</x-layouts.admin>
